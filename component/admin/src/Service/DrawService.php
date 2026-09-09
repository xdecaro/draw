<?php
namespace xdecaro\Component\Draw\Administrator\Service;

defined('_JEXEC') or die;

use DomainException;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use RuntimeException;
use Throwable;

final class DrawService
{
    public const ALGORITHM_VERSION = '1.0.0';

    private DatabaseInterface $db;
    private DrawReadService $read;

    public function __construct(DatabaseInterface $db, DrawReadService $read)
    {
        $this->db = $db;
        $this->read = $read;
    }

    public function create(string $title, string $mode = 'groups', array $source = [], int $actorId = 0): int
    {
        $title = trim($title);
        $mode = strtolower(trim($mode));
        if ($title === '' || mb_strlen($title) > 255) {
            throw new DomainException('Draw title is required and must be at most 255 characters.');
        }
        if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $mode)) {
            throw new DomainException('Invalid draw mode.');
        }

        $now = Factory::getDate()->toSql();
        $row = (object) [
            'title' => $title,
            'mode' => $mode,
            'status' => 'draft',
            'source_component' => $this->nullableString($source['component'] ?? null, 100),
            'source_entity' => $this->nullableString($source['entity'] ?? null, 100),
            'source_id' => $this->nullableString($source['id'] ?? null, 191),
            'configuration' => $this->encodeJson(['constraints' => [], 'executions' => []]),
            'algorithm_version' => self::ALGORITHM_VERSION,
            'created' => $now,
            'created_by' => max(0, $actorId),
        ];
        $this->db->insertObject('#__xdecarodraw_draws', $row);
        $drawId = (int) $this->db->insertid();
        $this->audit($drawId, 'draw.created', $actorId, ['mode' => $mode]);

        return $drawId;
    }

    public function configure(int $drawId, array $entries, array $targets, array $constraints = [], int $actorId = 0): void
    {
        $draw = $this->read->getDraw($drawId);
        if ($this->read->countAssignments($drawId) > 0) {
            throw new DomainException('Draw inputs are immutable after the first execution. Create a new draw to change entries or targets.');
        }

        $entries = $this->normalizeEntries($entries);
        $targets = $this->normalizeTargets($targets);
        if (count($entries) < 2 || count($entries) !== count($targets)) {
            throw new DomainException('Entries and targets must have the same count and contain at least two items.');
        }
        $constraints = $this->normalizeConstraints($constraints, $entries);
        $configuration = $draw['configuration_array'];
        $configuration['constraints'] = $constraints;
        $configuration['executions'] = [];

        $this->db->transactionStart();
        try {
            foreach (['#__xdecarodraw_entries', '#__xdecarodraw_targets'] as $table) {
                $query = $this->db->getQuery(true)->delete($this->db->quoteName($table))->where($this->db->quoteName('draw_id') . ' = ' . $drawId);
                $this->db->setQuery($query)->execute();
            }
            foreach ($entries as $entry) {
                $row = (object) [
                    'draw_id' => $drawId,
                    'entry_key' => $entry['entry_key'],
                    'display_name' => $entry['display_name'],
                    'source_component' => $entry['source_component'],
                    'source_entity' => $entry['source_entity'],
                    'source_id' => $entry['source_id'],
                    'pot_key' => $entry['pot_key'],
                    'seed_order' => $entry['seed_order'],
                    'metadata' => $this->encodeJson($entry['metadata']),
                ];
                $this->db->insertObject('#__xdecarodraw_entries', $row);
            }
            foreach ($targets as $target) {
                $row = (object) [
                    'draw_id' => $drawId,
                    'target_type' => $target['target_type'],
                    'target_key' => $target['target_key'],
                    'position_no' => $target['position_no'],
                    'metadata' => $this->encodeJson($target['metadata']),
                ];
                $this->db->insertObject('#__xdecarodraw_targets', $row);
            }
            $this->updateDraw($drawId, [
                'status' => 'ready',
                'configuration' => $this->encodeJson($configuration),
                'algorithm_version' => self::ALGORITHM_VERSION,
                'modified' => Factory::getDate()->toSql(),
                'modified_by' => max(0, $actorId),
            ]);
            $this->audit($drawId, 'draw.configured', $actorId, ['entries' => count($entries), 'targets' => count($targets), 'constraints' => $constraints]);
            $this->db->transactionCommit();
        } catch (Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }
    }

    public function execute(int $drawId, ?string $seed = null, int $actorId = 0): array
    {
        $draw = $this->read->getDraw($drawId);
        if (!in_array((string) $draw['status'], ['ready', 'completed', 'published'], true)) {
            throw new DomainException('Draw must be ready or previously completed before execution.');
        }
        $entries = $this->read->getEntries($drawId);
        $targets = $this->read->getTargets($drawId);
        if (count($entries) < 2 || count($entries) !== count($targets)) {
            throw new DomainException('Draw entries and targets are incomplete.');
        }

        $configuration = $draw['configuration_array'];
        $constraints = is_array($configuration['constraints'] ?? null) ? $configuration['constraints'] : [];
        $seed = trim((string) ($seed ?? ''));
        if ($seed === '') {
            $seed = bin2hex(random_bytes(32));
        }
        if (strlen($seed) > 512) {
            throw new DomainException('Seed is too long.');
        }

        $executionNo = $this->read->getCurrentExecutionNo($drawId) + 1;
        $solution = $this->solve($entries, $targets, $constraints, $seed);
        $inputHash = $this->canonicalHash([
            'algorithm' => self::ALGORITHM_VERSION,
            'entries' => array_map([$this, 'entryForHash'], $entries),
            'targets' => array_map([$this, 'targetForHash'], $targets),
            'constraints' => $constraints,
        ]);
        $resultForHash = [];
        foreach ($solution as $pair) {
            $resultForHash[] = [
                'entry_key' => (string) $pair['entry']['entry_key'],
                'target_type' => (string) $pair['target']['target_type'],
                'target_key' => (string) $pair['target']['target_key'],
                'position_no' => $pair['target']['position_no'] === null ? null : (int) $pair['target']['position_no'],
            ];
        }
        $resultHash = $this->canonicalHash($resultForHash);
        $seedHash = hash('sha256', $seed);
        $started = Factory::getDate()->toSql();

        $this->db->transactionStart();
        try {
            foreach ($solution as $index => $pair) {
                $row = (object) [
                    'draw_id' => $drawId,
                    'execution_no' => $executionNo,
                    'sequence_no' => $index + 1,
                    'entry_id' => (int) $pair['entry']['id'],
                    'target_id' => (int) $pair['target']['id'],
                    'revealed_at' => null,
                    'created' => $started,
                ];
                $this->db->insertObject('#__xdecarodraw_assignments', $row);
            }
            $execution = [
                'execution_no' => $executionNo,
                'algorithm_version' => self::ALGORITHM_VERSION,
                'seed' => $seed,
                'seed_hash' => $seedHash,
                'input_hash' => $inputHash,
                'result_hash' => $resultHash,
                'started_at' => $started,
                'assignment_count' => count($solution),
            ];
            $executions = is_array($configuration['executions'] ?? null) ? $configuration['executions'] : [];
            $executions[] = $execution;
            $configuration['executions'] = $executions;
            $configuration['current_execution'] = $executionNo;
            $this->updateDraw($drawId, [
                'status' => 'live',
                'configuration' => $this->encodeJson($configuration),
                'algorithm_version' => self::ALGORITHM_VERSION,
                'modified' => $started,
                'modified_by' => max(0, $actorId),
            ]);
            $this->audit($drawId, 'draw.executed', $actorId, [
                'execution_no' => $executionNo,
                'algorithm_version' => self::ALGORITHM_VERSION,
                'seed_hash' => $seedHash,
                'input_hash' => $inputHash,
                'result_hash' => $resultHash,
                'assignment_count' => count($solution),
            ]);
            $this->db->transactionCommit();
        } catch (Throwable $e) {
            $this->db->transactionRollback();
            throw $e;
        }

        return [
            'execution_no' => $executionNo,
            'assignment_count' => count($solution),
            'seed_hash' => $seedHash,
            'input_hash' => $inputHash,
            'result_hash' => $resultHash,
        ];
    }

    public function restart(int $drawId, ?string $seed = null, int $actorId = 0): array
    {
        if ($this->read->getCurrentExecutionNo($drawId) < 1) {
            throw new DomainException('Draw has never been executed.');
        }

        return $this->execute($drawId, $seed, $actorId);
    }

    public function revealNext(int $drawId, int $actorId = 0): ?array
    {
        $draw = $this->read->getDraw($drawId);
        if ((string) $draw['status'] !== 'live') {
            throw new DomainException('Only a live draw can reveal assignments.');
        }
        $executionNo = $this->read->getCurrentExecutionNo($drawId);
        if ($executionNo < 1) {
            throw new DomainException('No execution exists.');
        }

        $query = $this->db->getQuery(true)
            ->select(['id', 'sequence_no'])
            ->from($this->db->quoteName('#__xdecarodraw_assignments'))
            ->where('draw_id = ' . $drawId)
            ->where('execution_no = ' . $executionNo)
            ->where('revealed_at IS NULL')
            ->order('sequence_no ASC');
        $next = $this->db->setQuery($query, 0, 1)->loadAssoc();
        if (!$next) {
            $this->completeIfNeeded($drawId, $executionNo, $actorId);
            return null;
        }

        $now = Factory::getDate()->toSql();
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__xdecarodraw_assignments'))
            ->set($this->db->quoteName('revealed_at') . ' = ' . $this->db->quote($now))
            ->where($this->db->quoteName('id') . ' = ' . (int) $next['id'])
            ->where($this->db->quoteName('revealed_at') . ' IS NULL');
        $this->db->setQuery($query)->execute();
        $this->audit($drawId, 'draw.revealed', $actorId, ['execution_no' => $executionNo, 'sequence_no' => (int) $next['sequence_no']]);
        $this->completeIfNeeded($drawId, $executionNo, $actorId);

        foreach ($this->read->getAssignments($drawId, $executionNo, true) as $row) {
            if ((int) $row['sequence_no'] === (int) $next['sequence_no']) {
                return $row;
            }
        }

        return null;
    }

    public function publish(int $drawId, int $actorId = 0): void
    {
        $draw = $this->read->getDraw($drawId);
        if ((string) $draw['status'] !== 'completed') {
            throw new DomainException('Only a completed draw can be published.');
        }
        $now = Factory::getDate()->toSql();
        $configuration = $draw['configuration_array'];
        $configuration['published_at'] = $now;
        $this->updateDraw($drawId, [
            'status' => 'published',
            'configuration' => $this->encodeJson($configuration),
            'modified' => $now,
            'modified_by' => max(0, $actorId),
        ]);
        $this->audit($drawId, 'draw.published', $actorId, ['execution_no' => $this->read->getCurrentExecutionNo($drawId)]);
    }

    private function completeIfNeeded(int $drawId, int $executionNo, int $actorId): void
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__xdecarodraw_assignments'))
            ->where('draw_id = ' . $drawId)
            ->where('execution_no = ' . $executionNo)
            ->where('revealed_at IS NULL');
        if ((int) $this->db->setQuery($query)->loadResult() !== 0) {
            return;
        }
        $draw = $this->read->getDraw($drawId);
        if ((string) $draw['status'] === 'live') {
            $this->updateDraw($drawId, [
                'status' => 'completed',
                'modified' => Factory::getDate()->toSql(),
                'modified_by' => max(0, $actorId),
            ]);
            $this->audit($drawId, 'draw.completed', $actorId, ['execution_no' => $executionNo]);
        }
    }

    private function solve(array $entries, array $targets, array $constraints, string $seed): array
    {
        usort($entries, static fn (array $a, array $b): int => strcmp(hash('sha256', $seed . '|entry|' . $a['entry_key']), hash('sha256', $seed . '|entry|' . $b['entry_key'])));
        $solution = $this->backtrack($entries, $targets, $constraints, $seed, 0, [], []);
        if ($solution === null) {
            throw new DomainException('No valid draw result satisfies all configured constraints.');
        }

        return $solution;
    }

    private function backtrack(array $entries, array $targets, array $constraints, string $seed, int $index, array $usedTargets, array $assigned): ?array
    {
        if ($index >= count($entries)) {
            return $assigned;
        }
        $entry = $entries[$index];
        $candidates = $targets;
        usort($candidates, function (array $a, array $b) use ($seed, $entry): int {
            $ka = hash('sha256', $seed . '|candidate|' . $entry['entry_key'] . '|' . $this->targetIdentity($a));
            $kb = hash('sha256', $seed . '|candidate|' . $entry['entry_key'] . '|' . $this->targetIdentity($b));
            return strcmp($ka, $kb);
        });

        foreach ($candidates as $target) {
            $targetId = (int) $target['id'];
            if (isset($usedTargets[$targetId]) || !$this->isAllowed($entry, $target, $constraints, $assigned)) {
                continue;
            }
            $usedTargets[$targetId] = true;
            $assigned[] = ['entry' => $entry, 'target' => $target];
            $result = $this->backtrack($entries, $targets, $constraints, $seed, $index + 1, $usedTargets, $assigned);
            if ($result !== null) {
                return $result;
            }
            array_pop($assigned);
            unset($usedTargets[$targetId]);
        }

        return null;
    }

    private function isAllowed(array $entry, array $target, array $constraints, array $assigned): bool
    {
        $entryKey = (string) $entry['entry_key'];
        $selectors = $this->targetSelectors($target);
        $allowedTargets = $constraints['allowed_targets'][$entryKey] ?? [];
        if ($allowedTargets && !array_intersect($selectors, $allowedTargets)) {
            return false;
        }
        $forbiddenTargets = $constraints['forbidden_targets'][$entryKey] ?? [];
        if ($forbiddenTargets && array_intersect($selectors, $forbiddenTargets)) {
            return false;
        }

        $bucket = $this->bucketKey($target);
        foreach ($assigned as $pair) {
            if ($this->bucketKey($pair['target']) !== $bucket) {
                continue;
            }
            $other = $pair['entry'];
            if (!empty($constraints['one_per_pot']) && (string) ($entry['pot_key'] ?? '') !== '' && (string) ($entry['pot_key'] ?? '') === (string) ($other['pot_key'] ?? '')) {
                return false;
            }
            foreach (($constraints['distinct_metadata'] ?? []) as $metadataKey) {
                $a = $entry['metadata_array'][$metadataKey] ?? null;
                $b = $other['metadata_array'][$metadataKey] ?? null;
                if ($a !== null && $a !== '' && $b !== null && $b !== '' && (string) $a === (string) $b) {
                    return false;
                }
            }
            foreach (($constraints['forbidden_pairs'] ?? []) as $pairKeys) {
                if (!is_array($pairKeys) || count($pairKeys) !== 2) {
                    continue;
                }
                $keys = [(string) $pairKeys[0], (string) $pairKeys[1]];
                sort($keys, SORT_STRING);
                $current = [$entryKey, (string) $other['entry_key']];
                sort($current, SORT_STRING);
                if ($keys === $current) {
                    return false;
                }
            }
        }

        return true;
    }

    private function normalizeEntries(array $entries): array
    {
        $normalized = [];
        $keys = [];
        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                throw new DomainException('Each entry must be an object.');
            }
            $key = $this->requiredKey($entry['entry_key'] ?? '', 'entry_key', 191);
            if (isset($keys[$key])) {
                throw new DomainException('Duplicate entry_key: ' . $key);
            }
            $keys[$key] = true;
            $display = trim((string) ($entry['display_name'] ?? ''));
            if ($display === '' || mb_strlen($display) > 255) {
                throw new DomainException('Each entry requires a display_name of at most 255 characters.');
            }
            $metadata = $entry['metadata'] ?? [];
            if (!is_array($metadata)) {
                throw new DomainException('Entry metadata must be an object.');
            }
            $normalized[] = [
                'entry_key' => $key,
                'display_name' => $display,
                'source_component' => $this->nullableString($entry['source_component'] ?? null, 100),
                'source_entity' => $this->nullableString($entry['source_entity'] ?? null, 100),
                'source_id' => $this->nullableString($entry['source_id'] ?? null, 191),
                'pot_key' => $this->nullableString($entry['pot_key'] ?? null, 64),
                'seed_order' => isset($entry['seed_order']) ? (int) $entry['seed_order'] : $index + 1,
                'metadata' => $metadata,
            ];
        }

        return $normalized;
    }

    private function normalizeTargets(array $targets): array
    {
        $normalized = [];
        $keys = [];
        foreach ($targets as $target) {
            if (!is_array($target)) {
                throw new DomainException('Each target must be an object.');
            }
            $type = $this->requiredKey($target['target_type'] ?? '', 'target_type', 64);
            $key = $this->requiredKey($target['target_key'] ?? '', 'target_key', 191);
            $position = array_key_exists('position_no', $target) && $target['position_no'] !== null ? (int) $target['position_no'] : null;
            $identity = $type . ':' . $key . ':' . ($position === null ? '' : $position);
            if (isset($keys[$identity])) {
                throw new DomainException('Duplicate target: ' . $identity);
            }
            $keys[$identity] = true;
            $metadata = $target['metadata'] ?? [];
            if (!is_array($metadata)) {
                throw new DomainException('Target metadata must be an object.');
            }
            $normalized[] = [
                'target_type' => $type,
                'target_key' => $key,
                'position_no' => $position,
                'metadata' => $metadata,
            ];
        }

        return $normalized;
    }

    private function normalizeConstraints(array $constraints, array $entries): array
    {
        $entryKeys = array_fill_keys(array_column($entries, 'entry_key'), true);
        $normalized = [
            'one_per_pot' => !empty($constraints['one_per_pot']),
            'distinct_metadata' => [],
            'allowed_targets' => [],
            'forbidden_targets' => [],
            'forbidden_pairs' => [],
        ];
        foreach (($constraints['distinct_metadata'] ?? []) as $key) {
            $key = trim((string) $key);
            if ($key !== '' && preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $key)) {
                $normalized['distinct_metadata'][] = $key;
            }
        }
        $normalized['distinct_metadata'] = array_values(array_unique($normalized['distinct_metadata']));
        foreach (['allowed_targets', 'forbidden_targets'] as $mapName) {
            $map = $constraints[$mapName] ?? [];
            if (!is_array($map)) {
                throw new DomainException($mapName . ' must be an object.');
            }
            foreach ($map as $entryKey => $selectors) {
                if (!isset($entryKeys[$entryKey]) || !is_array($selectors)) {
                    throw new DomainException('Invalid entry reference in ' . $mapName . '.');
                }
                $clean = [];
                foreach ($selectors as $selector) {
                    $selector = trim((string) $selector);
                    if ($selector !== '' && strlen($selector) <= 320) {
                        $clean[] = $selector;
                    }
                }
                $normalized[$mapName][$entryKey] = array_values(array_unique($clean));
            }
        }
        foreach (($constraints['forbidden_pairs'] ?? []) as $pair) {
            if (!is_array($pair) || count($pair) !== 2 || !isset($entryKeys[(string) $pair[0]], $entryKeys[(string) $pair[1]]) || (string) $pair[0] === (string) $pair[1]) {
                throw new DomainException('Invalid forbidden pair.');
            }
            $keys = [(string) $pair[0], (string) $pair[1]];
            sort($keys, SORT_STRING);
            $normalized['forbidden_pairs'][$keys[0] . '|' . $keys[1]] = $keys;
        }
        $normalized['forbidden_pairs'] = array_values($normalized['forbidden_pairs']);

        return $normalized;
    }

    private function bucketKey(array $target): string
    {
        $metadata = $target['metadata_array'] ?? $target['metadata'] ?? [];
        if (is_array($metadata) && isset($metadata['group']) && trim((string) $metadata['group']) !== '') {
            return 'group:' . trim((string) $metadata['group']);
        }

        return 'target:' . (string) $target['target_key'];
    }

    private function targetSelectors(array $target): array
    {
        $type = (string) $target['target_type'];
        $key = (string) $target['target_key'];
        $position = $target['position_no'] === null ? '' : (string) $target['position_no'];

        return [$key, $type . ':' . $key, $type . ':' . $key . ':' . $position];
    }

    private function targetIdentity(array $target): string
    {
        return implode(':', [(string) $target['target_type'], (string) $target['target_key'], $target['position_no'] === null ? '' : (string) $target['position_no']]);
    }

    private function entryForHash(array $entry): array
    {
        return [
            'entry_key' => (string) $entry['entry_key'],
            'display_name' => (string) $entry['display_name'],
            'source_component' => (string) ($entry['source_component'] ?? ''),
            'source_entity' => (string) ($entry['source_entity'] ?? ''),
            'source_id' => (string) ($entry['source_id'] ?? ''),
            'pot_key' => (string) ($entry['pot_key'] ?? ''),
            'seed_order' => (int) ($entry['seed_order'] ?? 0),
            'metadata' => $entry['metadata_array'] ?? $entry['metadata'] ?? [],
        ];
    }

    private function targetForHash(array $target): array
    {
        return [
            'target_type' => (string) $target['target_type'],
            'target_key' => (string) $target['target_key'],
            'position_no' => $target['position_no'] === null ? null : (int) $target['position_no'],
            'metadata' => $target['metadata_array'] ?? $target['metadata'] ?? [],
        ];
    }

    private function canonicalHash(mixed $value): string
    {
        $normalize = function (mixed $item) use (&$normalize): mixed {
            if (!is_array($item)) {
                return $item;
            }
            if (array_is_list($item)) {
                return array_map($normalize, $item);
            }
            ksort($item, SORT_STRING);
            foreach ($item as $key => $value) {
                $item[$key] = $normalize($value);
            }
            return $item;
        };

        return hash('sha256', json_encode($normalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function updateDraw(int $drawId, array $fields): void
    {
        $row = (object) array_merge(['id' => $drawId], $fields);
        if (!$this->db->updateObject('#__xdecarodraw_draws', $row, 'id')) {
            throw new RuntimeException('Unable to update draw.');
        }
    }

    private function audit(int $drawId, string $event, int $actorId, array $payload): void
    {
        $row = (object) [
            'draw_id' => $drawId,
            'event_type' => $event,
            'actor_id' => max(0, $actorId),
            'payload' => $this->encodeJson($payload),
            'created' => Factory::getDate()->toSql(),
        ];
        $this->db->insertObject('#__xdecarodraw_audit', $row);
    }

    private function requiredKey(mixed $value, string $label, int $maxLength): string
    {
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > $maxLength || !preg_match('/^[A-Za-z0-9_.:@\/-]+$/', $value)) {
            throw new DomainException('Invalid ' . $label . '.');
        }

        return $value;
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maxLength) {
            throw new DomainException('Value exceeds maximum length.');
        }

        return $value;
    }

    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
