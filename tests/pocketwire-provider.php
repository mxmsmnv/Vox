<?php
$module = (string)file_get_contents(dirname(__DIR__) . '/Vox.module.php');
$trait = (string)file_get_contents(dirname(__DIR__) . '/src/Traits/VoxMcpProviderTrait.php');
$checks = [
    'PocketWire opt-in is explicit' => str_contains($module, "'pocketWireProvider' => true"),
    'SDK methods are exposed' => str_contains($trait, 'pocketWireProviderInfo()') && str_contains($trait, 'pocketWireManifest(User $actor)') && str_contains($trait, 'pocketWireRead(string $resource, string $identifier, array $query, User $actor)'),
    'request actor is matched' => str_contains($trait, "wire('user')->id") && str_contains($trait, '$actor->id') && str_contains($trait, 'WirePermissionException'),
	'page-view permission is required' => str_contains($trait, "hasPermission('page-view')"),
    'only bounded published entries are advertised' => str_contains($trait, "'id' => 'entries'") && str_contains($trait, "'actions' => []") && str_contains($trait, 'min(25'),
    'public page visibility uses the guest account' => str_contains($trait, 'getGuestUser()') && str_contains($trait, '$owner->viewable($guest)') && str_contains($trait, '$owner->isUnpublished()') && str_contains($trait, '$owner->isTrash()'),
    'published DTO path is reused' => str_contains($trait, 'STATUS_PUBLISHED') && str_contains($trait, 'enrichEntryPublic('),
    'no PocketWire mutation exists' => !str_contains($trait, 'pocketWireWrite'),
];
foreach($checks as $label => $passed) if(!$passed) throw new RuntimeException('Vox PocketWire provider failed: ' . $label);
echo 'Vox PocketWire provider contract passed: ' . count($checks) . " checks.\n";
