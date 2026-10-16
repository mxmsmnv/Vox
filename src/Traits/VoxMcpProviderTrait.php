<?php namespace ProcessWire;

/** MCP access to content that is already published on a public page. */
trait VoxMcpProviderTrait {
    public function pocketWireProviderInfo(): array {
        return ['id' => 'vox', 'title' => 'Vox', 'module' => 'Vox', 'moduleVersion' => self::VERSION];
    }

    public function pocketWireManifest(User $actor): array {
		$ready = $this->pocketWireCanRead($actor);
        return [
            'id' => 'vox', 'title' => 'Vox', 'module' => 'Vox', 'moduleVersion' => self::VERSION,
            'status' => $ready ? 'native_read_only' : 'unsupported',
            'permissions' => ['scope' => 'providers.read', 'processWire' => ['page-view']],
            'presentation' => ['layout' => 'list', 'icon' => 'chatbubbles-outline', 'summary' => 'Published community entries on public pages.', 'accentColor' => '#DB2777'],
            'resources' => $ready ? [[
                'id' => 'entries', 'title' => 'Public entries', 'method' => 'GET',
                'endpoint' => '/api/pocketwire/v1/providers/vox/entries/{id}',
            ]] : [],
            'actions' => [],
        ];
    }

    public function pocketWireRead(string $resource, string $identifier, array $query, User $actor): array {
		if(!$this->pocketWireCanRead($actor)) throw new WirePermissionException('PocketWire actor context or page-view permission is unavailable.');
        if($resource !== 'entries' || !preg_match('/^[1-9][0-9]*$/D', $identifier)) throw new Wire404Exception('A Vox page identifier is required.');
        $type = strtolower(trim((string)($query['type'] ?? '')));
        if(!in_array($type, ['', 'review', 'question', 'thread', 'comment'], true)) throw new Wire404Exception('Vox entry type was not found.');
        return $this->mcpVoxPublicEntries((int)$identifier, $type, max(1, (int)($query['page'] ?? 1)), min(25, max(1, (int)($query['limit'] ?? 10))));
    }

	private function pocketWireCanRead(User $actor): bool {
		return (int)$this->wire('user')->id === (int)$actor->id
			&& ($actor->isSuperuser() || $actor->hasPermission('page-view'));
	}

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
        $guest = $this->wire('users')->getGuestUser();
        if(!$owner->id || !$owner->viewable($guest) || $owner->isUnpublished() || $owner->isTrash()) {
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
