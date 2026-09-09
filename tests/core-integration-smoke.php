<?php
define('_JEXEC', 1);
require_once __DIR__ . '/../component/admin/src/Service/CoreIntegrationService.php';
use xdecaro\Component\Draw\Administrator\Service\CoreIntegrationService;
$service = new CoreIntegrationService();
if ($service->isReferenceApiAvailable()) { throw new RuntimeException('Core must be absent in isolated smoke test.'); }
if ($service->isCapabilityApiAvailable()) { throw new RuntimeException('Capability API must be absent in isolated smoke test.'); }
if ($service->getVersion() !== '') { throw new RuntimeException('Absent Core must report an empty version.'); }
if ($service->getCapabilities() !== []) { throw new RuntimeException('Absent Core must expose no capability objects.'); }
try { $service->createDrawReference(1); throw new RuntimeException('Missing Core must not create references.'); } catch (RuntimeException $e) { if (!str_contains($e->getMessage(), 'Core by xdecaro')) { throw $e; } }
echo "Draw Core fallback smoke passed.\n";
