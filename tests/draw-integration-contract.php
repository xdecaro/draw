<?php
$root = dirname(__DIR__);
$servicePath = $root . '/component/admin/src/Service/DrawIntegrationService.php';
$componentPath = $root . '/component/admin/src/Extension/DrawComponent.php';
$providerPath = $root . '/component/admin/services/provider.php';
$readPath = $root . '/component/admin/src/Service/DrawReadService.php';

$failures = [];

if (!is_file($servicePath)) {
    $failures[] = 'DrawIntegrationService.php is missing.';
} else {
    $service = file_get_contents($servicePath);
    foreach ([
        'final class DrawIntegrationService',
        "REQUEST_SCHEMA = 'xdecaro.draw.request.v1'",
        "RESULT_SCHEMA = 'xdecaro.draw.result.v1'",
        'function createFromPayload',
        'function getResult',
    ] as $needle) {
        if (!str_contains($service, $needle)) {
            $failures[] = 'Integration service missing contract marker: ' . $needle;
        }
    }
    if (str_contains($service, '#__xdecarocompetitions_')) {
        $failures[] = 'Draw integration must not access Competitions private tables.';
    }
}

foreach ([$componentPath, $providerPath, $readPath] as $path) {
    if (!is_file($path)) {
        $failures[] = basename($path) . ' is missing.';
    }
}

if (is_file($componentPath) && !str_contains(file_get_contents($componentPath), 'function getIntegrationService')) {
    $failures[] = 'DrawComponent must expose getIntegrationService().';
}
if (is_file($providerPath) && !str_contains(file_get_contents($providerPath), 'DrawIntegrationService::class')) {
    $failures[] = 'Provider must register DrawIntegrationService.';
}
if (is_file($readPath)) {
    $read = file_get_contents($readPath);
    if (!str_contains($read, 'xdecaro.draw.result.v1')) {
        $failures[] = 'Public result schema must remain xdecaro.draw.result.v1.';
    }
    $snapshot = strstr($read, 'public function getPublicSnapshot');
    foreach (["'source' => [", "'entry_source' => [", "'sequence' =>", "'target' => ["] as $needle) {
        if ($snapshot === false || !str_contains($snapshot, $needle)) {
            $failures[] = 'Public result must expose contract marker: ' . $needle;
        }
    }
}

if ($failures) {
    throw new RuntimeException("Draw integration contract failed:\n- " . implode("\n- ", $failures));
}

echo "Draw integration contract passed.\n";