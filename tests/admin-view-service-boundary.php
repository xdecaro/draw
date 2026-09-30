<?php
$root = dirname(__DIR__);
$files = [
    'Dashboard' => $root . '/component/admin/src/View/Dashboard/HtmlView.php',
    'Draw' => $root . '/component/admin/src/View/Draw/HtmlView.php',
    'Information' => $root . '/component/admin/src/View/Information/HtmlView.php',
];

$failures = [];

foreach ($files as $name => $path) {
    if (!is_file($path)) {
        $failures[] = $name . ' view is missing.';
        continue;
    }

    $code = file_get_contents($path);

    if (str_contains($code, 'Factory::getContainer()->get(DrawReadService::class)')) {
        $failures[] = $name . ' resolves DrawReadService from the global Joomla container.';
    }

    if (str_contains($code, 'Factory::getContainer()->get(CoreIntegrationService::class)')) {
        $failures[] = $name . ' resolves CoreIntegrationService from the global Joomla container.';
    }

    if (
        (str_contains($code, 'DrawReadService') || str_contains($code, 'CoreIntegrationService'))
        && !str_contains($code, "bootComponent('com_xdecarodraw')")
    ) {
        $failures[] = $name . ' must obtain Draw-owned services through the Draw component facade.';
    }
}

if ($failures) {
    throw new RuntimeException("Draw administrator service-boundary test failed:\n- " . implode("\n- ", $failures));
}

echo "Draw administrator service-boundary test passed.\n";