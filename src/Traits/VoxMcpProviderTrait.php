<?php namespace ProcessWire;

/** MCP access to content that is already published on a public page. */
trait VoxMcpProviderTrait {
    public function mcpProviderInfo(): array {
        return ['name' => 'vox', 'title' => 'Vox', 'version' => self::VERSION];
    }

    public function mcpTools(): array {
        return [[
            'name' => 'vox_public_entries',
            'title' => 'Vox public discussions',
            'description' => 'List a bounded page of published Vox entries for one public ProcessWire page with private guest and moderation fields removed.',
            'handler' => [$this, 'mcpVoxPublicEntries'],
            'scope' => 'read', 'read_only' => true, 'destructive' => false,
            'idempotent' => true, 'open_world' => false,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'integer', 'minimum' => 1],
                    'type' => ['type' => 'string', 'enum' => ['review', 'question', 'thread', 'comment']],
                    'page' => ['type' => 'integer', 'minimum' => 1],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 25],
                ],
                'required' => ['page_id'], 'additionalProperties' => false,
            ],
        ]];
    }

    public function mcpVoxPublicEntries(int $page_id, string $type = '', int $page = 1, int $limit = 10): array {
        $owner = $this->wire('pages')->get($page_id);
        if(!$owner->id || !$owner->viewable() || $owner->isUnpublished() || $owner->isTrash()) {
            throw new Wire404Exception('A public viewable page was not found.');
        }
        $result = $this->getEntries([
            'page_id' => $page_id, 'type' => $type, 'status' => self::STATUS_PUBLISHED,
            'page' => max(1, $page), 'per_page' => max(1, min(25, $limit)),
        ]);
        $result['entries'] = array_map(fn(array $entry): array => $this->enrichEntryPublic($entry), $result['entries']);
        return $result;
    }
}
