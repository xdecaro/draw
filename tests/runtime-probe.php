<?php
declare(strict_types=1);

$joomlaRoot = rtrim((string) getenv('DRAW_JOOMLA_ROOT'), DIRECTORY_SEPARATOR);
if ($joomlaRoot === '' || !is_file($joomlaRoot . '/includes/defines.php')) {
    fwrite(STDERR, "DRAW_JOOMLA_ROOT does not point to an installed Joomla site.\n");
    exit(1);
}

define('_JEXEC', 1);
define('JPATH_BASE', $joomlaRoot);
require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use xdecaro\Core\Integration\CapabilityRegistry;

$container = Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias('JSession', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
$app = $container->get(\Joomla\Console\Application::class);
Factory::$application = $app;
$app->createExtensionNamespaceMap();

$component = $app->bootComponent('com_xdecarodraw');
if (!is_object($component) || !method_exists($component, 'getDrawService') || !method_exists($component, 'getReadService')) {
    throw new RuntimeException('Draw public facade unavailable.');
}
$registry = new CapabilityRegistry();
$component->getCoreIntegrationService()->registerCapabilities($registry);
foreach (['draw.create','draw.execute','draw.reveal','draw.publish','draw.query','draw.results'] as $capability) {
    if (!$registry->supports('com_xdecarodraw', $capability)) throw new RuntimeException('Missing Core capability: ' . $capability);
}

$service = $component->getDrawService();
$read = $component->getReadService();
$drawId = $service->create('CI Draw', 'groups', ['component'=>'com_example','entity'=>'competition','id'=>'42'], 1);
$entries = [
 ['entry_key'=>'team-a','display_name'=>'Team A','pot_key'=>'P1','metadata'=>['country'=>'IT']],
 ['entry_key'=>'team-b','display_name'=>'Team B','pot_key'=>'P1','metadata'=>['country'=>'FR']],
 ['entry_key'=>'team-c','display_name'=>'Team C','pot_key'=>'P2','metadata'=>['country'=>'IT']],
 ['entry_key'=>'team-d','display_name'=>'Team D','pot_key'=>'P2','metadata'=>['country'=>'FR']],
];
$targets = [
 ['target_type'=>'group','target_key'=>'A','position_no'=>1,'metadata'=>['group'=>'A']],
 ['target_type'=>'group','target_key'=>'A','position_no'=>2,'metadata'=>['group'=>'A']],
 ['target_type'=>'group','target_key'=>'B','position_no'=>1,'metadata'=>['group'=>'B']],
 ['target_type'=>'group','target_key'=>'B','position_no'=>2,'metadata'=>['group'=>'B']],
];
$constraints = ['one_per_pot'=>true,'distinct_metadata'=>['country']];
$service->configure($drawId, $entries, $targets, $constraints, 1);
$first = $service->execute($drawId, 'ci-seed-one', 1);
if (($first['execution_no'] ?? 0) !== 1) throw new RuntimeException('First execution number is invalid.');
for ($i=0; $i<4; $i++) $service->revealNext($drawId, 1);
$snapshot = $read->getPublicSnapshot($drawId);
if (!$snapshot['completed'] || count($snapshot['assignments']) !== 4 || $snapshot['seed'] !== 'ci-seed-one' || $snapshot['result_hash'] === '') throw new RuntimeException('Completed public snapshot is invalid.');
$service->publish($drawId, 1);
if (!$read->getPublicSnapshot($drawId)['published']) throw new RuntimeException('Publish transition failed.');
$second = $service->restart($drawId, 'ci-seed-two', 1);
if (($second['execution_no'] ?? 0) !== 2) throw new RuntimeException('Restart did not create execution 2.');
for ($i=0; $i<4; $i++) $service->revealNext($drawId, 1);
/** @var DatabaseInterface $db */
$db = $container->get(DatabaseInterface::class);
$q = $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__xdecarodraw_assignments'))->where('draw_id=' . (int) $drawId);
if ((int) $db->setQuery($q)->loadResult() !== 8) throw new RuntimeException('Execution history was overwritten.');
try { $service->configure($drawId, $entries, $targets, $constraints, 1); throw new RuntimeException('Executed draw inputs remained mutable.'); } catch (DomainException $e) {}
if (count($read->getAudit($drawId)) < 12) throw new RuntimeException('Audit history is incomplete.');
echo "Draw runtime lifecycle, Core capabilities and immutable history OK\n";
