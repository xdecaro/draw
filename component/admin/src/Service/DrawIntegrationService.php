<?php
namespace xdecaro\Component\Draw\Administrator\Service;

defined('_JEXEC') or die;

use DomainException;

final class DrawIntegrationService
{
    public const REQUEST_SCHEMA = 'xdecaro.draw.request.v1';
    public const RESULT_SCHEMA = 'xdecaro.draw.result.v1';

    private DrawService $drawService;
    private DrawReadService $readService;

    public function __construct(DrawService $drawService, DrawReadService $readService)
    {
        $this->drawService = $drawService;
        $this->readService = $readService;
    }

    public function createFromPayload(array $payload, int $actorId = 0): array
    {
        if ((string) ($payload['schema'] ?? '') !== self::REQUEST_SCHEMA) {
            throw new DomainException('Unsupported Draw request schema.');
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            throw new DomainException('Draw title is required and must be at most 255 characters.');
        }

        $mode = strtolower(trim((string) ($payload['mode'] ?? 'groups')));
        if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $mode)) {
            throw new DomainException('Invalid draw mode.');
        }

        $source = $this->normalizeReference($payload['source'] ?? null, 'source');
        $entries = $this->normalizeEntries($payload['entries'] ?? null);
        $targets = $this->normalizeTargets($payload['targets'] ?? null);

        if (count($entries) < 2 || count($entries) !== count($targets)) {
            throw new DomainException('Entries and targets must have the same count and contain at least two items.');
        }

        $constraints = $this->normalizeConstraints($payload['constraints'] ?? [], $entries);
        $drawId = $this->drawService->create($title, $mode, $source, max(0, $actorId));
        $this->drawService->configure($drawId, $entries, $targets, $constraints, max(0, $actorId));

        return [
            'draw_id' => $drawId,
            'source' => $source,
            'mode' => $mode,
            'status' => 'ready',
            'result_schema' => self::RESULT_SCHEMA,
        ];
    }

    public function getResult(int $drawId): array
    {
        $result = $this->readService->getPublicSnapshot(max(1, $drawId));
        if ((string) ($result['schema'] ?? '') !== self::RESULT_SCHEMA) {
            throw new DomainException('Unsupported Draw result schema.');
        }

        return $result;
    }

    private function normalizeEntries(mixed $entries): array
    {
        if (!is_array($entries) || !array_is_list($entries)) {
            throw new DomainException('Draw entries must be a list.');
        }

        $normalized = [];
        $seen = [];

        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                throw new DomainException('Each Draw entry must be an object.');
            }

            $key = $this->requiredIdentifier($entry['key'] ?? null, 'entry key', 191);
            if (isset($seen[$key])) {
                throw new DomainException('Duplicate Draw entry key: ' . $key);
            }
            $seen[$key] = true;

            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 255) {
                throw new DomainException('Each Draw entry requires a name of at most 255 characters.');
            }

            $source = $this->normalizeReference($entry['source'] ?? null, 'entry source');
            $metadata = $entry['metadata'] ?? [];
            if (!is_array($metadata)) {
                throw new DomainException('Entry metadata must be an object.');
            }

            $pot = $this->nullableText($entry['pot'] ?? null, 64, 'entry pot');
            $seed = array_key_exists('seed', $entry) ? (int) $entry['seed'] : $index + 1;
            if ($seed < 1) {
                throw new DomainException('Entry seed must be a positive integer.');
            }

            $normalized[] = [
                'entry_key' => $key,
                'display_name' => $name,
                'source_component' => $source['component'],
                'source_entity' => $source['entity'],
                'source_id' => $source['id'],
                'pot_key' => $pot,
                'seed_order' => $seed,
                'metadata' => $metadata,
            ];
        }

        return $normalized;
    }

    private function normalizeTargets(mixed $targets): array
    {
        if (!is_array($targets) || !array_is_list($targets)) {
            throw new DomainException('Draw targets must be a list.');
        }

        $normalized = [];
        $seen = [];

        foreach ($targets as $target) {
            if (!is_array($target)) {
                throw new DomainException('Each Draw target must be an object.');
            }

            $type = $this->requiredIdentifier($target['type'] ?? null, 'target type', 64);
            $key = $this->requiredIdentifier($target['key'] ?? null, 'target key', 191);
            $position = array_key_exists('position', $target) && $target['position'] !== null
                ? (int) $target['position']
                : null;

            if ($position !== null && $position < 1) {
                throw new DomainException('Target position must be a positive integer.');
            }

            $identity = $type . ':' . $key . ':' . ($position === null ? '' : $position);
            if (isset($seen[$identity])) {
                throw new DomainException('Duplicate Draw target: ' . $identity);
            }
            $seen[$identity] = true;

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

    private function normalizeConstraints(mixed $constraints, array $entries): array
    {
        if (!is_array($constraints) || !array_is_list($constraints)) {
            throw new DomainException('Draw constraints must be a list.');
        }

        $entryKeys = array_fill_keys(array_column($entries, 'entry_key'), true);
        $normalized = [
            'one_per_pot' => false,
            'distinct_metadata' => [],
            'allowed_targets' => [],
            'forbidden_targets' => [],
            'forbidden_pairs' => [],
        ];

        foreach ($constraints as $constraint) {
            if (!is_array($constraint)) {
                throw new DomainException('Each Draw constraint must be an object.');
            }

            $type = $this->requiredIdentifier($constraint['type'] ?? null, 'constraint type', 64);

            if ($type === 'one_per_pot') {
                $normalized['one_per_pot'] = true;
                continue;
            }

            if ($type === 'distinct_metadata' || $type === 'max_same_metadata_per_target') {
                $field = $this->requiredIdentifier($constraint['field'] ?? null, 'constraint field', 64);
                if ($type === 'max_same_metadata_per_target' && (int) ($constraint['max'] ?? 0) !== 1) {
                    throw new DomainException('max_same_metadata_per_target currently supports only max=1.');
                }
                $normalized['distinct_metadata'][] = $field;
                continue;
            }

            if ($type === 'allowed_targets' || $type === 'forbidden_targets') {
                $entryKey = $this->requiredIdentifier($constraint['entry_key'] ?? null, 'constraint entry key', 191);
                if (!isset($entryKeys[$entryKey])) {
                    throw new DomainException('Constraint references an unknown Draw entry.');
                }
                $selectors = $constraint['targets'] ?? null;
                if (!is_array($selectors) || !array_is_list($selectors)) {
                    throw new DomainException('Constraint targets must be a list.');
                }
                $clean = [];
                foreach ($selectors as $selector) {
                    $clean[] = $this->requiredIdentifier($selector, 'target selector', 320);
                }
                $normalized[$type][$entryKey] = array_values(array_unique($clean));
                continue;
            }

            if ($type === 'forbidden_pair') {
                $pair = $constraint['entries'] ?? null;
                if (!is_array($pair) || !array_is_list($pair) || count($pair) !== 2) {
                    throw new DomainException('forbidden_pair requires exactly two entry keys.');
                }
                $left = $this->requiredIdentifier($pair[0] ?? null, 'forbidden pair entry', 191);
                $right = $this->requiredIdentifier($pair[1] ?? null, 'forbidden pair entry', 191);
                if ($left === $right || !isset($entryKeys[$left], $entryKeys[$right])) {
                    throw new DomainException('forbidden_pair references invalid Draw entries.');
                }
                $normalized['forbidden_pairs'][] = [$left, $right];
                continue;
            }

            throw new DomainException('Unsupported Draw constraint type: ' . $type);
        }

        $normalized['distinct_metadata'] = array_values(array_unique($normalized['distinct_metadata']));

        return $normalized;
    }

    private function normalizeReference(mixed $reference, string $label): array
    {
        if (!is_array($reference)) {
            throw new DomainException(ucfirst($label) . ' reference is required.');
        }

        return [
            'component' => $this->requiredIdentifier($reference['component'] ?? null, $label . ' component', 100),
            'entity' => $this->requiredIdentifier($reference['entity'] ?? null, $label . ' entity', 100),
            'id' => $this->requiredIdentifier($reference['id'] ?? null, $label . ' id', 191),
        ];
    }

    private function requiredIdentifier(mixed $value, string $label, int $maxLength): string
    {
        $value = trim((string) $value);
        if (
            $value === ''
            || strlen($value) > $maxLength
            || !preg_match('/^[A-Za-z0-9_.:@\/-]+$/', $value)
        ) {
            throw new DomainException('Invalid ' . $label . '.');
        }

        return $value;
    }

    private function nullableText(mixed $value, int $maxLength, string $label): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maxLength) {
            throw new DomainException(ucfirst($label) . ' exceeds maximum length.');
        }

        return $value;
    }
}