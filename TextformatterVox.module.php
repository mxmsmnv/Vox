<?php namespace ProcessWire;

/**
 * TextformatterVox — render Vox widgets from textarea/rich-text tokens.
 *
 * Supported tokens:
 *   [[vox:forum]]
 *   [[vox:reviews]]
 *   [[vox:questions]]
 *   [[vox:discussions]]
 *   [[vox:all]]
 *   [[vox:form]]
 *   [[vox:discussion-form]]
 *   [[vox:profile]]
 *   [[vox:profile-activity]]
 *   [[vox:answers]]
 *
 * Optional token attributes:
 *   [[vox:forum title="Forum" intro="Community discussions"]]
 *   [[vox:form type="question" title="Ask us"]]
 */
class TextformatterVox extends Textformatter {

    public static function getModuleInfo(): array {
        return [
            'title'    => 'Vox Textformatter',
            'summary'  => 'Render Vox widgets from text tokens like [[vox:forum]], [[vox:form]] or [[vox:reviews]].',
            'version'  => 170,
            'author'   => 'Maxim Semenov',
            'href'     => 'https://smnv.org',
            'icon'     => 'comments',
            'singular' => true,
            'requires' => ['Vox'],
        ];
    }

    /**
     * Replace Vox tokens in formatted text.
     */
    public function format(&$str) {
        if (strpos((string)$str, '[[vox:') === false) return;

        $initRendered = false;
        $str = preg_replace_callback(
            '/\[\[vox:([a-z_-]+)([^\]]*)\]\]/i',
            function(array $matches) use (&$initRendered): string {
                $type = strtolower($matches[1] ?? '');
                $attrs = $this->parseAttributes($matches[2] ?? '');
                $includeInit = empty($attrs['init']) || !in_array(strtolower($attrs['init']), ['0', 'false', 'no'], true);
                $renderInit = $includeInit && !$initRendered;
                if ($renderInit) $initRendered = true;
                return $this->renderToken($type, $attrs, $renderInit);
            },
            (string)$str
        );
    }

    private function renderToken(string $type, array $attrs, bool $includeInit): string {
        $map = [
            'forum'       => ['vox.forum.php'],
            'reviews'     => ['vox.reviews.php'],
            'review'      => ['vox.reviews.php'],
            'questions'   => ['vox.questions.php'],
            'qa'          => ['vox.questions.php'],
            'discussions' => ['vox.discussions.php'],
            'discussion'  => ['vox.discussions.php'],
            'all'         => ['vox.reviews.php', 'vox.questions.php', 'vox.discussions.php'],
            'answers'     => ['vox.answers.php'],
            'answers-index' => ['vox.answers.index.php'],
            'answers-ask' => ['vox.answers.ask.php'],
            'answers-question' => ['vox.answers.question.php'],
            'answers-sidebar' => ['vox.answers.sidebar.php'],
            'profile'     => ['vox.profile.php'],
            'profile-header' => ['vox.profile.header.php'],
            'profile-rank' => ['vox.profile.rank.php'],
            'profile-badges' => ['vox.profile.badges.php'],
            'profile-activity' => ['vox.profile.activity.php'],
            'profile-points' => ['vox.profile.points.php'],
            'profile-leaderboard' => ['vox.profile.leaderboard.php'],
        ];

        $page = $this->wire->page;
        if (!$page || !$page->id) return '';

        $vox = $this->wire->modules->get('Vox');
        if (!$vox instanceof Vox) return '';

        $config = $this->wire->config;
        $voxPath = $config->paths->Vox . 'templates/views/';
        $voxForumTitle = $attrs['title'] ?? null;
        $voxForumIntro = $attrs['intro'] ?? null;
        $voxProfileUser = $attrs['user'] ?? null;
        $voxProfile = str_starts_with($type, 'profile') ? $vox->getUserProfileData($voxProfileUser) : null;
        $inlineTypes = [
            'form' => 'thread',
            'discussion-form' => 'thread',
            'thread-form' => 'thread',
            'question-form' => 'question',
            'review-form' => 'review',
        ];

        ob_start();
        if ($includeInit) {
            include $voxPath . 'vox.init.php';
        }
        if (isset($inlineTypes[$type])) {
            $voxInlineType = strtolower((string)($attrs['type'] ?? $inlineTypes[$type]));
            $voxInlineTitle = $attrs['title'] ?? null;
            $voxInlineIntro = $attrs['intro'] ?? null;
            $voxInlinePlaceholder = $attrs['placeholder'] ?? null;
            $voxInlineButton = $attrs['button'] ?? null;
            include $voxPath . 'vox.inline-form.php';
        } elseif (isset($map[$type])) {
            foreach ($map[$type] as $file) {
                include $voxPath . $file;
            }
        }
        return (string)ob_get_clean();
    }

    private function parseAttributes(string $raw): array {
        $attrs = [];
        if ($raw === '') return $attrs;

        preg_match_all('/([a-z_]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s]+))/i', $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $key = strtolower($m[1]);
            $value = $m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : ($m[4] ?? ''));
            $attrs[$key] = $this->wire->sanitizer->text($value);
        }
        return $attrs;
    }
}
