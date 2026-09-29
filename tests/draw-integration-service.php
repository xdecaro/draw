<?php
namespace xdecaro\Component\Draw\Administrator\Service;

define('_JEXEC', 1);

use DomainException;
use RuntimeException;

final class DrawService
{
    public array $created = [];
    public array $configured = [];

    public function create(string $title, string $mode = 'groups', array $source = [], int $actorId = 0): int
    {
        $this->created = compact('title', 'mode', 'source', 'actorId');
        return 77;
    }

    public function configure(int $drawId, array $entries, array $targets, array $constraints = [], int $actorId = 0): void
    {
        $this->configured = compact('drawId', 'entries', 'targets', 'constraints', 'actorId');
    }
}

final class DrawReadService
{
    public array $result = ['schema' => 'xdecaro.draw.result.v1', 'draw_id' => 77, 'status' => 'published'];

    public function getPublicSnapshot(int $drawId): array
    {
        return $this->result + ['draw_id' => $drawId];
    }
}

require_once __DIR__ . '/../component/admin/src/Service/DrawIntegrationService.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectDomain(callable $callback, string $messagePart): void
{
    try {
        $callback();
    } catch (DomainException $e) {
        assertTrue(str_contains($e->getMessage(), $messagePart), 'Unexpected DomainException: ' . $e->getMessage());
        return;
    }

    throw new RuntimeException('Expected DomainException containing: ' . $messagePart);
}

$draw = new DrawService();
$read = new DrawReadService();
$service = new DrawIntegrationService($draw, $read);

$payload = [
    'schema' => DrawIntegrationService::REQUEST_SCHEMA,
    'title' => 'DCL Futsal Men 2026',
    'source' => [
        'component' => 'com_xdecarocompetitions',
        'entity' => 'season',
        'id' => '42',
    ],
    'mode' => 'groups',
    'entries' => [
        [
            'key' => 'participation:10',
            'source' => ['component' => 'com_xdecarocompetitions', 'entity' => 'participation', 'id' => '10'],
            'name' => 'Club One',
            'pot' => '1',
            'seed' => 1,
            'metadata' => ['country' => 'IT'],
        ],
        [
            'key' => 'participation:11',
            'source' => ['component' => 'com_xdecarocompetitions', 'entity' => 'participation', 'id' => '11'],
            'name' => 'Club Two',
            'pot' => '2',
            'seed' => 2,
            'metadata' => ['country' => 'ES'],
        ],
    ],
    'targets' => [
        ['type' => 'group', 'key' => 'A', 'position' => 1, 'metadata' => ['group' => 'A']],
        ['type' => 'group', 'key' => 'B', 'position' => 1, 'metadata' => ['group' => 'B']],
    ],
    'constraints' => [
        ['type' => 'one_per_pot'],
        ['type' => 'max_same_metadata_per_target', 'field' => 'country', 'max' => 1],
        ['type' => 'forbidden_pair', 'entries' => ['participation:10', 'participation:11']],
    ],
];

$created = $service->createFromPayload($payload, 9);
assertTrue($created['draw_id'] === 77, 'Public facade must return the Draw id.');
assertTrue($created['status'] === 'ready', 'New integrated Draw must be ready after configuration.');
assertTrue($created['result_schema'] === DrawIntegrationService::RESULT_SCHEMA, 'Result schema reference mismatch.');
assertTrue($draw->created['source']['component'] === 'com_xdecarocompetitions', 'Source component was not preserved.');
assertTrue($draw->created['actorId'] === 9, 'Actor id was not preserved.');
assertTrue($draw->configured['entries'][0]['entry_key'] === 'participation:10', 'Entry key normalization failed.');
assertTrue($draw->configured['entries'][0]['source_entity'] === 'participation', 'Entry source normalization failed.');
assertTrue($draw->configured['targets'][0]['target_type'] === 'group', 'Target type normalization failed.');
assertTrue($draw->configured['targets'][0]['position_no'] === 1, 'Target position normalization failed.');
assertTrue($draw->configured['constraints']['one_per_pot'] === true, 'one_per_pot normalization failed.');
assertTrue($draw->configured['constraints']['distinct_metadata'] === ['country'], 'Metadata constraint normalization failed.');
assertTrue($draw->configured['constraints']['forbidden_pairs'] === [['participation:10', 'participation:11']], 'Forbidden pair normalization failed.');

expectDomain(
    fn () => $service->createFromPayload(array_replace($payload, ['schema' => 'xdecaro.draw.request.v2'])),
    'Unsupported Draw request schema'
);

$duplicate = $payload;
$duplicate['entries'][1]['key'] = 'participation:10';
expectDomain(fn () => $service->createFromPayload($duplicate), 'Duplicate Draw entry key');

$mismatch = $payload;
array_pop($mismatch['targets']);
expectDomain(fn () => $service->createFromPayload($mismatch), 'same count');

$unknownConstraint = $payload;
$unknownConstraint['constraints'] = [['type' => 'execute_callback', 'class' => 'UnsafeRule']];
expectDomain(fn () => $service->createFromPayload($unknownConstraint), 'Unsupported Draw constraint type');

$badMax = $payload;
$badMax['constraints'] = [['type' => 'max_same_metadata_per_target', 'field' => 'country', 'max' => 2]];
expectDomain(fn () => $service->createFromPayload($badMax), 'only max=1');

assertTrue($service->getResult(77)['schema'] === DrawIntegrationService::RESULT_SCHEMA, 'Valid result schema must be returned.');
$read->result = ['schema' => 'xdecaro.draw.result.v2'];
expectDomain(fn () => $service->getResult(77), 'Unsupported Draw result schema');

echo "Draw integration service behavior passed.\n";