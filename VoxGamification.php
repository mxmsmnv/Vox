<?php namespace ProcessWire;

/**
 * VoxGamification — checks and awards badges after user actions.
 * Loaded as a plain PHP class (not a PW Module), instantiated by Vox.
 */
class VoxGamification extends Wire {

    private Vox $vox;

    public function __construct(Vox $vox) {
        $this->vox = $vox;
        parent::__construct();
    }

    // ── Badge definitions ─────────────────────────────────────────────────

    /**
     * Returns all enabled badge definitions, loaded from the admin-editable
     * vox_badge_defs table. Each entry keeps its key/label/icon plus a check()
     * callable derived from its metric + threshold, so the award logic stays
     * data-driven.
     */
    public function getBadgeDefinitions(): array {
        $out = [];
        foreach ($this->vox->getBadgeDefs(true) as $row) {
            $metric    = (string) $row['metric'];
            $threshold = (int) $row['threshold'];
            $out[] = [
                'key'       => $row['badge_key'],
                'label'     => $row['label'],
                'icon'      => $row['icon'],
                'metric'    => $metric,
                'threshold' => $threshold,
                'check'     => static function (array $s) use ($metric, $threshold): bool {
                    if ($metric === 'leaderboard_top3') return !empty($s['leaderboard_top3']);
                    return (int) ($s[$metric] ?? 0) >= $threshold;
                },
            ];
        }
        return $out;
    }

    // ── Stats collector ───────────────────────────────────────────────────

    /**
     * Collect all stats needed for badge checks in a single query batch.
     */
    public function getUserStats(int $userId): array {
        $db    = $this->wire->database;
        $et    = Vox::TABLE_ENTRIES;
        $vt    = Vox::TABLE_VOTES;
        $pt    = Vox::TABLE_POINTS;

        // Entry counts by type
        $stmt = $db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(type = 'review')   AS reviews,
                SUM(type = 'question') AS questions,
                SUM(type = 'comment')  AS answers,
                SUM(type = 'thread')   AS threads,
                SUM(is_best_answer = 1) AS best_answers,
                COUNT(DISTINCT page_id) AS unique_pages
            FROM `{$et}`
            WHERE user_id = ? AND status = 'published'
        ");
        $stmt->execute([$userId]);
        $counts = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        // Total likes received on user's entries
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(v.value), 0)
            FROM `{$vt}` v
            JOIN `{$et}` e ON e.id = v.entry_id
            WHERE e.user_id = ? AND v.value > 0
        ");
        $stmt->execute([$userId]);
        $likesReceived = (int) $stmt->fetchColumn();

        // Long reviews (body >= 500 chars)
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM `{$et}`
            WHERE user_id = ? AND type = 'review' AND CHAR_LENGTH(body) >= 500 AND status = 'published'
        ");
        $stmt->execute([$userId]);
        $longReviews = (int) $stmt->fetchColumn();

        // Total points
        $points = $this->vox->getUserPoints($userId);

        // Published entries with at least one uploaded photo
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT e.id)
            FROM `" . Vox::TABLE_ENTRIES . "` e
            JOIN `" . Vox::TABLE_PHOTOS . "` p ON p.entry_id = e.id
            WHERE e.user_id = ? AND e.status = 'published'
        ");
        $stmt->execute([$userId]);
        $photoEntries = (int) $stmt->fetchColumn();

        // Top-3 leaderboard check
        $lb = $this->vox->getLeaderboard('month', 3);
        $inTop3 = false;
        foreach ($lb as $entry) {
            if ((int)$entry['user_id'] === $userId) { $inTop3 = true; break; }
        }

        return [
            'reviews'         => (int) ($counts['reviews'] ?? 0),
            'answers'         => (int) ($counts['answers'] ?? 0),
            'threads'         => (int) ($counts['threads'] ?? 0),
            'best_answers'    => (int) ($counts['best_answers'] ?? 0),
            'unique_pages'    => (int) ($counts['unique_pages'] ?? 0),
            'likes_received'  => $likesReceived,
            'long_reviews'    => $longReviews,
            'photos'          => $photoEntries,
            'points'          => $points,
            'leaderboard_top3'=> $inTop3,
        ];
    }

    // ── Award checker ─────────────────────────────────────────────────────

    /**
     * Check all badge conditions and award any newly earned badges.
     * Called after every successful entry save or vote.
     */
    public function checkAndAward(int $userId): void {
        if (!$userId) return;

        $stats      = $this->getUserStats($userId);
        $earned     = $this->vox->getUserBadges($userId);
        $definitions= $this->getBadgeDefinitions();

        foreach ($definitions as $def) {
            if (in_array($def['key'], $earned)) continue; // already has it
            if (($def['check'])($stats)) {
                $this->vox->awardBadge($userId, $def['key']);
            }
        }
    }

    // ── Points award helpers ──────────────────────────────────────────────

    public function onEntryPublished(int $userId, int $entryId, string $type): void {
        if (!$userId) return;
        $pts = match($type) {
            Vox::TYPE_REVIEW,
            Vox::TYPE_QUESTION,
            Vox::TYPE_THREAD   => (int) $this->vox->cfg('points_post'),
            default            => 0,
        };
        if (!$pts) return;
        // Idempotent: re-approving a moderated entry must not award again.
        if ($this->vox->getPointsNet($userId, $entryId, ['post']) > 0) return;
        $this->vox->awardPoints($userId, 'post', $pts, $entryId);
        $this->checkAndAward($userId);
    }

    public function onLikeReceived(int $entryOwnerId, int $entryId): void {
        if (!$entryOwnerId) return;
        $pts = (int) $this->vox->cfg('points_like_received');
        if ($pts) {
            $this->vox->awardPoints($entryOwnerId, 'like_received', $pts, $entryId);
            $this->checkAndAward($entryOwnerId);
        }
    }

    /**
     * Revoke like points when a vote is withdrawn, so like/unlike cycles
     * stay net-zero. Never pushes the award/revoke pair below zero.
     */
    public function onLikeRevoked(int $entryOwnerId, int $entryId): void {
        if (!$entryOwnerId) return;
        $pts = (int) $this->vox->cfg('points_like_received');
        if (!$pts) return;
        if ($this->vox->getPointsNet($entryOwnerId, $entryId, ['like_received', 'like_revoked']) < $pts) return;
        $this->vox->awardPoints($entryOwnerId, 'like_revoked', -$pts, $entryId);
    }

    public function onAnswerPosted(int $userId, int $entryId): void {
        if (!$userId) return;
        $pts = (int) $this->vox->cfg('points_answer');
        if (!$pts) return;
        // Idempotent: one answer award per entry.
        if ($this->vox->getPointsNet($userId, $entryId, ['answer']) > 0) return;
        $this->vox->awardPoints($userId, 'answer', $pts, $entryId);
        $this->checkAndAward($userId);
    }

    public function onBestAnswerSelected(int $userId, int $entryId): void {
        if (!$userId) return;
        $pts = (int) $this->vox->cfg('points_best_answer');
        if (!$pts) return;
        // Idempotent: award only when the award/revoke pair is balanced, so
        // re-marking the same answer cannot farm points.
        if ($this->vox->getPointsNet($userId, $entryId, ['best_answer', 'best_answer_revoked']) > 0) return;
        $this->vox->awardPoints($userId, 'best_answer', $pts, $entryId);
        $this->checkAndAward($userId);
    }

    /**
     * Revoke best-answer points when the mark moves to another answer.
     */
    public function onBestAnswerRevoked(int $userId, int $entryId): void {
        if (!$userId) return;
        $pts = (int) $this->vox->cfg('points_best_answer');
        if (!$pts) return;
        if ($this->vox->getPointsNet($userId, $entryId, ['best_answer', 'best_answer_revoked']) < $pts) return;
        $this->vox->awardPoints($userId, 'best_answer_revoked', -$pts, $entryId);
    }
}
