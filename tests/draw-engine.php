<?php
define('_JEXEC', 1);
require_once __DIR__ . '/../component/admin/src/Service/DrawService.php';
use xdecaro\Component\Draw\Administrator\Service\DrawService;

$ref = new ReflectionClass(DrawService::class);
$service = $ref->newInstanceWithoutConstructor();
$method = $ref->getMethod('solve');
$method->setAccessible(true);
$entries = [
 ['id'=>1,'entry_key'=>'A','display_name'=>'A','pot_key'=>'P1','metadata_array'=>['country'=>'IT']],
 ['id'=>2,'entry_key'=>'B','display_name'=>'B','pot_key'=>'P1','metadata_array'=>['country'=>'FR']],
 ['id'=>3,'entry_key'=>'C','display_name'=>'C','pot_key'=>'P2','metadata_array'=>['country'=>'IT']],
 ['id'=>4,'entry_key'=>'D','display_name'=>'D','pot_key'=>'P2','metadata_array'=>['country'=>'FR']],
];
$targets = [
 ['id'=>11,'target_type'=>'group','target_key'=>'A','position_no'=>1,'metadata_array'=>['group'=>'A']],
 ['id'=>12,'target_type'=>'group','target_key'=>'A','position_no'=>2,'metadata_array'=>['group'=>'A']],
 ['id'=>13,'target_type'=>'group','target_key'=>'B','position_no'=>1,'metadata_array'=>['group'=>'B']],
 ['id'=>14,'target_type'=>'group','target_key'=>'B','position_no'=>2,'metadata_array'=>['group'=>'B']],
];
$constraints = ['one_per_pot'=>true,'distinct_metadata'=>['country'],'allowed_targets'=>[],'forbidden_targets'=>[],'forbidden_pairs'=>[]];
$seed = 'Draw-1.0-fixed-seed';
$r1 = $method->invoke($service, $entries, $targets, $constraints, $seed);
$r2 = $method->invoke($service, $entries, $targets, $constraints, $seed);
$compact = static fn(array $rows): array => array_map(static fn(array $p): string => $p['entry']['entry_key'].'>'.$p['target']['target_key'].':'.$p['target']['position_no'], $rows);
if ($compact($r1) !== $compact($r2)) throw new RuntimeException('Fixed seed is not reproducible.');
if (count($r1) !== 4) throw new RuntimeException('Solver did not assign every entry.');
$groupPots=[];$groupCountries=[];
foreach($r1 as $p){$g=$p['target']['metadata_array']['group'];$pot=$p['entry']['pot_key'];$country=$p['entry']['metadata_array']['country'];if(isset($groupPots[$g][$pot]))throw new RuntimeException('one_per_pot violated.');if(isset($groupCountries[$g][$country]))throw new RuntimeException('distinct_metadata violated.');$groupPots[$g][$pot]=1;$groupCountries[$g][$country]=1;}
$impossibleEntries = [$entries[0], $entries[1]];
$impossibleTargets = [$targets[0], $targets[1]];
try { $method->invoke($service, $impossibleEntries, $impossibleTargets, ['one_per_pot'=>true,'distinct_metadata'=>[],'allowed_targets'=>[],'forbidden_targets'=>[],'forbidden_pairs'=>[]], $seed); throw new RuntimeException('Impossible constraints were silently weakened.'); } catch (DomainException $e) {}
echo "Draw engine deterministic and constraint tests passed.\n";
