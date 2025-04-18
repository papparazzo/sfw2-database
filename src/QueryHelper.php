<?php

/**
 *  SFW2 - SimpleFrameWork
 *
 *  Copyright (C) 2024  Stefan Paproth
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/agpl.txt>.
 */

declare(strict_types=1);

namespace SFW2\Database;

use Stringable;

/**
 * @phpstan-import-type StatementParam from DatabaseInterface
 */
class QueryHelper
{
    public function __construct(
        private readonly DatabaseInterface $database
    ) {
    }

    /**
     * @param string $stmt
     * @param array<string, StatementParam> $params
     * @param int $row
     * @return array<string, scalar|null>
     * @throws DatabaseException
     */
    public function selectRow(string $stmt, array $params = [], int $row = 0): array
    {
        $res = $this->database->select($stmt, $params, $row, 1);
        if(empty($res)) {
            return [];
        }
        return array_shift($res);
    }

    /**
     * @param string $stmt
     * @param array<string, StatementParam> $params
     * @return scalar|null
     * @throws DatabaseException
     */
    public function selectSingle(string $stmt, array $params = []): mixed
    {
        $res = $this->selectRow($stmt, $params);
        if(empty($res)) {
            return null;
        }
        return array_shift($res);
    }

    /**
     * @param string $key
     * @param string $value
     * @param string $table
     * @param array<string, string> $conditions
     * @param array<string, StatementParam> $params
     * @return array<string, scalar|null>
     * @throws DatabaseException
     */
    public function selectKeyValue(
        string $key, string $value, string $table, array $conditions = [], array $params = []
    ): array {
        $this->checkIdentifier($key);
        $this->checkIdentifier($value);
        $this->checkIdentifier($table);

        /** @noinspection SqlResolve */
        $stmt = "SELECT `$key` AS `k`, `$value` AS `v` FROM `$table`";
        /** @var list<array{k: string, v: string}> $res */
        $res = $this->database->query($this->addConditions($stmt, $conditions), $params);
        $rv = [];

        foreach($res as $row) {
            $rv[$row['k']] = $row['v'];
        }

        return $rv;
    }

    /**
     * @param string $key
     * @param string[] $values
     * @param string $table
     * @param array<string, string> $conditions
     * @param array<string, StatementParam> $params
     * @return array<string, array<string, scalar|null>>
     * @throws DatabaseException
     */
    public function selectKeyValues(
        string $key, array $values, string $table, array $conditions = [], array $params = []
    ): array {
        $this->checkIdentifier($key);
        $this->checkIdentifier($table);

        $stmt = "SELECT `$key` AS `k`, `" . implode("`, `", $values) . "` FROM `$table`";
        $res = $this->database->query($this->addConditions($stmt, $conditions), $params);
        $rv = [];

        foreach($res as $row) {
            $key = $row['k'];
            unset($row['k']);
            $rv[$key] = $row;
        }

        return $rv;
    }

    /**
     * @param string $stmt
     * @param array<string, StatementParam> $params
     * @return array<string, scalar|null>
     * @throws DatabaseException
     */
    public function selectColumn(string $stmt, array $params = []): array
    {
        $res = $this->database->select($stmt, $params);
        /** @var array<string, string> */
        return array_map(fn($item) => array_shift($item), $res);
    }

    /**
     * @param string $table
     * @param array<string, StatementParam> $conditions
     * @param array<string, StatementParam> $params
     * @return int
     * @throws DatabaseException
     */
    public function selectCount(string $table, array $conditions = [], array $params = []): int
    {
        /** @noinspection SqlResolve */
        $stmt = "SELECT COUNT(*) AS `cnt` FROM `$table`";
        return (int)$this->selectSingle($this->addConditions($stmt, $conditions), $params);
    }

    /**
     * @throws DatabaseException
     */
    public function entryExists(string $table, string $column, string $value): bool
    {
        if($this->selectCount($table, [$column => $value]) == 0) {
            return false;
        }
        return true;
    }

    /**
     * @throws DatabaseException
     */
    /**
     * @param string $stmt
     * @param array<string, StatementParam> $conditions
     * @return string
     * @throws DatabaseException
     */
    protected function addConditions(string $stmt, array $conditions = []): string
    {
        if (mb_stripos($stmt, ' WHERE ') !== false) {
            throw new DatabaseException("WHERE-Condition in stmt <$stmt> allready set");
        }

        if (empty($conditions)) {
            return $stmt;
        }

        foreach ($conditions as $column => &$item) {
            $this->checkIdentifier($column);
            if (is_array($item) && !empty($item)) {
                $item = "`$column` IN({$this->database->escape($item)})";
            } else if (is_null($item)) {
                $item = "`$column` IS NULL";
            } else if (is_scalar($item) || $item instanceof Stringable) {
                $item = "`$column` = {$this->database->escape($item)}";
            } else {
                throw new DatabaseException("Invalid type for column <$column> given");
            }
        }

        /** @var string[] $conditions */
        return "$stmt WHERE " . implode(' AND ', $conditions);
    }

    /**
     * @throws DatabaseException
     */
    protected function checkIdentifier(string $name): void
    {
        if(preg_match('/^[a-zA-Z0-9_{}.]+$/', $name) !== 1) {
            throw new DatabaseException("Invalid type for column <$name> given");
        }
    }
}