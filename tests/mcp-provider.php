<?php
$module = (string)file_get_contents(dirname(__DIR__) . '/Vox.module.php');
$trait = (string)file_get_contents(dirname(__DIR__) . '/src/Traits/VoxMcpProviderTrait.php');
$checks = [str_contains($module, "'mcpProvider' => true"), str_contains($trait, "'vox_public_entries'"), str_contains($trait, 'STATUS_PUBLISHED'), str_contains($trait, 'enrichEntryPublic('), str_contains($trait, "'maximum' => 25"), str_contains($trait, "'additionalProperties' => false")];
if(in_array(false, $checks, true)) { fwrite(STDERR, "Vox MCP provider contract failed.\n"); exit(1); }
echo "Vox MCP provider contract passed.\n";
