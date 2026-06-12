<?php namespace ProcessWire;

/**
 * Database storage for Vox.
 *
 * Modules and templates should use Vox public methods instead of calling this
 * class directly. Keeping SQL here makes the module classes easier to read.
 */
class VoxRepository {

    private Wire $wire;

    public function __construct(Wire $wire) {
        $this->wire = $wire;
    }

    public function getEntryRow(int $id): ?array {
        $stmt = $this->wire->database->prepare("
            SELECT e.*, u.name AS user_name
            FROM `" . Vox::TABLE_ENTRIES . "` e
            LEFT JOIN pages u ON u.id = e.user_id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPendingCount(): int {
        return (int)$this->wire->database
            ->query("SELECT COUNT(*) FROM `" . Vox::TABLE_ENTRIES . "` WHERE status = 'pending'")
            ->fetchColumn();
    }

    public function getOpenReportsCount(): int {
        return (int)$this->wire->database
            ->query("SELECT COUNT(*) FROM `" . Vox::TABLE_REPORTS . "` WHERE status = 'open'")
            ->fetchColumn();
    }

    public function getEntryReportsCount(int $entryId): int {
        $stmt = $this->wire->database->prepare(
            "SELECT COUNT(*) FROM `" . Vox::TABLE_REPORTS . "` WHERE entry_id = ? AND status = 'open'"
        );
        $stmt->execute([$entryId]);
        return (int)$stmt->fetchColumn();
    }

    public function getOpenReports(): array {
        $et = Vox::TABLE_ENTRIES;
        $rt = Vox::TABLE_REPORTS;
        $stmt = $this->wire->database->query("
            SELECT r.*, e.body AS entry_body, e.type AS entry_type,
                   e.user_id AS entry_user_id, e.guest_name AS entry_guest_name,
                   ft.data AS page_name,
                   u.name AS reporter_name
            FROM `{$rt}` r
            JOIN `{$et}` e ON e.id = r.entry_id
            LEFT JOIN field_title ft ON ft.pages_id = e.page_id
            LEFT JOIN pages u ON u.id = r.user_id
            WHERE r.status = 'open'
            ORDER BY r.created DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getReport(int $reportId): ?array {
        $stmt = $this->wire->database->prepare(
            "SELECT * FROM `" . Vox::TABLE_REPORTS . "` WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$reportId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function dismissReport(int $reportId): bool {
        if (!$reportId) return false;
        $this->wire->database
            ->prepare("UPDATE `" . Vox::TABLE_REPORTS . "` SET status = 'dismissed' WHERE id = ?")
            ->execute([$reportId]);
        return true;
    }

    public function setEntryStatus(int $entryId, string $status): bool {
        $valid = [Vox::STATUS_PUBLISHED, Vox::STATUS_PENDING, Vox::STATUS_SPAM];
        if (!in_array($status, $valid, true)) return false;
        $this->wire->database
            ->prepare("UPDATE `" . Vox::TABLE_ENTRIES . "` SET status = ? WHERE id = ?")
            ->execute([$status, $entryId]);
        return true;
    }

    public function setEntriesStatus(array $ids, string $status): bool {
        $ids = array_filter(array_map('intval', $ids));
        if (!$ids) return false;
        $valid = [Vox::STATUS_PUBLISHED, Vox::STATUS_PENDING, Vox::STATUS_SPAM];
        if (!in_array($status, $valid, true)) return false;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $this->wire->database
            ->prepare("UPDATE `" . Vox::TABLE_ENTRIES . "` SET status = ? WHERE id IN ({$in})")
            ->execute(array_merge([$status], $ids));
        return true;
    }

    public function deleteEntry(int $entryId): bool {
        if (!$entryId || !$this->getEntryRow($entryId)) return false;

        $db = $this->wire->database;
        $ids = [$entryId];
        $queue = [$entryId];

        while ($queue) {
            $in = implode(',', array_fill(0, count($queue), '?'));
            $stmt = $db->prepare("SELECT id FROM `" . Vox::TABLE_ENTRIES . "` WHERE parent_id IN ({$in})");
            $stmt->execute($queue);
            $children = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
            $children = array_values(array_diff($children, $ids));
            if (!$children) break;
            $ids = array_merge($ids, $children);
            $queue = $children;
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $in = implode(',', array_fill(0, count($ids), '?'));

        $db->beginTransaction();
        try {
            $photoStmt = $db->prepare("SELECT filename FROM `" . Vox::TABLE_PHOTOS . "` WHERE entry_id IN ({$in})");
            $photoStmt->execute($ids);
            $photoFiles = $photoStmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ([Vox::TABLE_VALUES, Vox::TABLE_PHOTOS, Vox::TABLE_VOTES, Vox::TABLE_REPORTS, Vox::TABLE_POINTS] as $table) {
                $db->prepare("DELETE FROM `{$table}` WHERE entry_id IN ({$in})")->execute($ids);
            }
            $db->prepare("DELETE FROM `" . Vox::TABLE_ENTRIES . "` WHERE id IN ({$in})")->execute($ids);
            $db->commit();

            $photoDir = rtrim($this->wire->config->paths->root, '/') . '/' . trim($this->wire->modules->get('Vox')->cfg('photo_path'), '/') . '/';
            foreach ($photoFiles as $filename) {
                $path = $photoDir . basename((string)$filename);
                if (is_file($path)) @unlink($path);
            }
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return true;
    }

    public function deleteEntries(array $ids): int {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $count = 0;
        foreach ($ids as $id) {
            if ($this->deleteEntry($id)) $count++;
        }
        return $count;
    }

}
