<?php

namespace SFW2\Database;

use SFW2\Database\Exception as DatabaseException;

abstract class DatabaseAbstract implements DatabaseInterface
{
    public function __construct(
         protected readonly string $prefix = 'sfw2'
    )
    {
    }

    /**
     * @throws Exception
     */
    public function delete(string $stmt, array $params = []): int {
        return $this->update($stmt, $params);
    }

    /**
     * @throws Exception
     */
    public function selectRow(string $stmt, array $params = [], int $row = 0): array {
        $res = $this->select($stmt, $params, $row, 1);
        if(empty($res)) {
            return [];
        }
        return array_shift($res);
    }

    /**
     * @throws Exception
     */
    public function selectSingle(string $stmt, array $params = []) {
        $res = $this->selectRow($stmt, $params);
        if(empty($res)) {
            return null;
        }
        return array_shift($res);
    }

    /**
     * @throws Exception
     */
    public function selectCount(string $table, array $conditions = [], array $params = []): int {
        return $this->selectSingle($this->addConditions("SELECT COUNT(*) AS `cnt` FROM `$table`", $conditions), $params);
    }

    /**
     * @throws Exception
     */
    public function entryExists(string $table, string $column, string $value): bool {
        if($this->selectCount($table, [$column => $value]) == 0) {
            return false;
        }
        return true;
    }

     /**
     * @throws Exception
     */
    public function update(string $stmt, array $params = []): int {
        $this->query($stmt, $params);
        return $this->getAffectedRows();
    }

    /**
     * @throws Exception
     */
    public function insert(string $stmt, array $params = []): int {
        $this->query($stmt, $params);
        return $this->getLastInsertedId();
    }

    public function escape($data): string
    {
        if (is_null($data)) {
            return 'NULL';
        }
        if (is_bool($data) && $data) {
            return '1';
        }
        if (is_bool($data)) {
            return '0';
        }
        if (is_array($data)) {
            foreach ($data as &$item) {
                $item = $this->escape($item);
            }
            return implode(", ", $data);
        }
        return $this->escapeString((string)$data);
    }

    protected function addLimit(string $stmt, ?int $count, int $offset = 0): string {
        if ($count == null) {
            return $stmt;
        }

        /** @noinspection PhpAssignmentInConditionInspection */
        if (($pos = mb_stripos($stmt, ' LIMIT ')) !== false) {
            $stmt = mb_substr($stmt, 0, $pos);
        }

        if ($offset == 0) {
            return "$stmt LIMIT $count";
        }
        return "$stmt LIMIT $offset, $count";
    }

    /**
     * @throws Exception
     */
    protected function addConditions(string $stmt, array $conditions = []): string {
        if (mb_stripos($stmt, ' WHERE ') !== false) {
            throw new DatabaseException("WHERE-Condition in stmt <$stmt> allready set", DatabaseException::WHERE_CONDITON_ALLREADY_SET);
        }

        if (empty($conditions)) {
            return $stmt;
        }

        foreach ($conditions as $column => &$item) {
            if (is_array($item)) {
                $item = "`$column` IN({$this->escape($item)})";
            } else {
                $item = "`$column` = '{$this->escape($item)}'";
            }
        }

        return "$stmt WHERE " . implode(' AND ', $conditions);
    }

    abstract protected function getAffectedRows(): int;

    abstract protected function escapeString(string $string): string;

    abstract protected function getLastInsertedId(): int;
}