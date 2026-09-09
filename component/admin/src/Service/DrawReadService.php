<?php
namespace xdecaro\Component\Draw\Administrator\Service;

defined('_JEXEC') or die;

use DomainException;
use Joomla\Database\DatabaseInterface;

final class DrawReadService
{
    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    public function listDraws(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__xdecarodraw_draws'))
            ->order($this->db->quoteName('id') . ' DESC');

        return $this->db->setQuery($query, 0, $limit)->loadAssocList() ?: [];
    }

    public function getDraw(int $drawId): array
    {
        $drawId = max(1, $drawId);
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__xdecarodraw_draws'))
            ->where($this->db->quoteName('id') . ' = ' . $drawId);
        $row = $this->db->setQuery($query, 0, 1)->loadAssoc();

        if (!$row) {
            throw new DomainException('Draw not found.');
        }

        $row['configuration_array'] = $this->decodeJson((string) ($row['configuration'] ?? ''));

        return $row;
    }

    public function getEntries(int $drawId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__xdecarodraw_entries'))
            ->where($this->db->quoteName('draw_id') . ' = ' . max(1, $drawId))
            ->order($this->db->quoteName('id') . ' ASC');
        $rows = $this->db->setQuery($query)->loadAssocList() ?: [];

        foreach ($rows as &$row) {
            $row['metadata_array'] = $this->decodeJson((string) ($row['metadata'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    public function getTargets(int $drawId): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__xdecarodraw_targets'))
            ->where($this->db->quoteName('draw_id') . ' = ' . max(1, $drawId))
            ->order($this->db->quoteName('id') . ' ASC');
        $rows = $this->db->setQuery($query)->loadAssocList() ?: [];

        foreach ($rows as &$row) {
            $row['metadata_array'] = $this->decodeJson((string) ($row['metadata'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    public function getCurrentExecutionNo(int $drawId): int
    {
        $query = $this->db->getQuery(true)
            ->select('MAX(' . $this->db->quoteName('execution_no') . ')')
            ->from($this->db->quoteName('#__xdecarodraw_assignments'))
            ->where($this->db->quoteName('draw_id') . ' = ' . max(1, $drawId));

        return (int) $this->db->setQuery($query)->loadResult();
    }

    public function countAssignments(int $drawId): int
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__xdecarodraw_assignments'))
            ->where($this->db->quoteName('draw_id') . ' = ' . max(1, $drawId));

        return (int) $this->db->setQuery($query)->loadResult();
    }

    public function getAssignments(int $drawId, ?int $executionNo = null, bool $revealedOnly = false): array
    {
        $executionNo = $executionNo ?? $this->getCurrentExecutionNo($drawId);
        if ($executionNo < 1) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select([
                'a.id', 'a.execution_no', 'a.sequence_no', 'a.revealed_at', 'a.created',
                'e.entry_key', 'e.display_name', 'e.pot_key', 'e.metadata AS entry_metadata',
                't.target_type', 't.target_key', 't.position_no', 't.metadata AS target_metadata',
            ])
            ->from($this->db->quoteName('#__xdecarodraw_assignments', 'a'))
            ->join('INNER', $this->db->quoteName('#__xdecarodraw_entries', 'e') . ' ON e.id = a.entry_id')
            ->join('INNER', $this->db->quoteName('#__xdecarodraw_targets', 't') . ' ON t.id = a.target_id')
            ->where('a.draw_id = ' . max(1, $drawId))
            ->where('a.execution_no = ' . $executionNo)
            ->order('a.sequence_no ASC');

        if ($revealedOnly) {
            $query->where('a.revealed_at IS NOT NULL');
        }

        $rows = $this->db->setQuery($query)->loadAssocList() ?: [];
        foreach ($rows as &$row) {
            $row['entry_metadata'] = $this->decodeJson((string) ($row['entry_metadata'] ?? ''));
            $row['target_metadata'] = $this->decodeJson((string) ($row['target_metadata'] ?? ''));
            $row['position_no'] = $row['position_no'] === null ? null : (int) $row['position_no'];
            $row['sequence_no'] = (int) $row['sequence_no'];
            $row['execution_no'] = (int) $row['execution_no'];
        }
        unset($row);

        return $rows;
    }

    public function getAudit(int $drawId, int $limit = 200): array
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__xdecarodraw_audit'))
            ->where($this->db->quoteName('draw_id') . ' = ' . max(1, $drawId))
            ->order($this->db->quoteName('id') . ' DESC');
        $rows = $this->db->setQuery($query, 0, max(1, min(1000, $limit)))->loadAssocList() ?: [];

        foreach ($rows as &$row) {
            $row['payload_array'] = $this->decodeJson((string) ($row['payload'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    public function getPublicSnapshot(int $drawId): array
    {
        $draw = $this->getDraw($drawId);
        if (!in_array((string) $draw['status'], ['live', 'completed', 'published'], true)) {
            throw new DomainException('Draw is not public.');
        }

        $executionNo = $this->getCurrentExecutionNo($drawId);
        $all = $this->getAssignments($drawId, $executionNo, false);
        $revealed = array_values(array_filter($all, static fn (array $row): bool => !empty($row['revealed_at'])));
        $configuration = $draw['configuration_array'];
        $execution = [];
        foreach (($configuration['executions'] ?? []) as $item) {
            if ((int) ($item['execution_no'] ?? 0) === $executionNo) {
                $execution = is_array($item) ? $item : [];
                break;
            }
        }
        $finished = in_array((string) $draw['status'], ['completed', 'published'], true);

        return [
            'schema' => 'xdecaro.draw.result.v1',
            'draw_id' => (int) $draw['id'],
            'title' => (string) $draw['title'],
            'mode' => (string) $draw['mode'],
            'status' => (string) $draw['status'],
            'execution_no' => $executionNo,
            'total' => count($all),
            'revealed_count' => count($revealed),
            'assignments' => array_map(static fn (array $row): array => [
                'sequence_no' => (int) $row['sequence_no'],
                'entry_key' => (string) $row['entry_key'],
                'display_name' => (string) $row['display_name'],
                'pot_key' => (string) ($row['pot_key'] ?? ''),
                'target_type' => (string) $row['target_type'],
                'target_key' => (string) $row['target_key'],
                'position_no' => $row['position_no'],
                'target_metadata' => $row['target_metadata'],
                'revealed_at' => (string) $row['revealed_at'],
            ], $revealed),
            'input_hash' => (string) ($execution['input_hash'] ?? ''),
            'result_hash' => $finished ? (string) ($execution['result_hash'] ?? '') : '',
            'seed' => $finished ? (string) ($execution['seed'] ?? '') : '',
            'seed_hash' => (string) ($execution['seed_hash'] ?? ''),
            'completed' => $finished,
            'published' => (string) $draw['status'] === 'published',
        ];
    }

    private function decodeJson(string $json): array
    {
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
