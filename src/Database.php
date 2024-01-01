<?php

/**
 *  SFW2 - SimpleFrameWork
 *
 *  Copyright (C) 2020 Stefan Paproth <pappi-@gmx.de>
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
 *
 */

namespace SFW2\Database;

use PDO;
use PDOStatement;
use Stringable;
use Throwable;
use DateTimeInterface;

/**
 * @noinspection PhpUnused
 */
class Database implements DatabaseInterface
{

    protected PDO $handle;

    public function __construct(
        protected string $dsn,
        protected string $usr,
        protected string $pwd,
        protected array $options = [],
        protected string $prefix = 'sfw2'
    )
    {
        $this->connect();
    }

    protected function connect(): void
    {
        $this->handle = new PDO($this->dsn, $this->usr, $this->pwd);
        #  throw new DatabaseException("Could not connect to database <$err>");
        # FIXME: Nur bei mysql:
        # $this->query("set names 'utf8';");
    }

    public function __wakeup(): void
    {
        $this->connect();
    }

    public function __sleep(): array
    {
        #unset($this->handle);
        return [];
    }

    public function delete(string $stmt, array $params = []): int
    {
        return $this->update($stmt, $params);
    }

    public function update(string $stmt, array $params = []): int
    {
        return $this->handle->exec($this->getStatement($stmt, $params));
    }

    public function insert(string $stmt, array $params = []): int
    {
        $this->handle->exec($this->getStatement($stmt, $params));
        return $this->handle->lastInsertId();
    }

    /**
     * @throws DatabaseException
     */
    public function select(string $stmt, array $params = [], ?int $count = null, int $offset = 0): array
    {
        $stmt = $this->addLimit($stmt, $count, $offset);
        $stmt = $this->getStatement($stmt, $params);

        $res = $this->query($stmt);
        $rv = [];

        foreach($res as $row) {
            $rv[] = $row;
        }
        return $rv;
    }

    /**
     * @param string $stmt
     * @param array $params
     * @return false|PDOStatement
     * @throws DatabaseException
     */
    public function query(string $stmt, array $params = []): bool|PDOStatement
    {
        try {
            $res = $this->handle->query($stmt, PDO::FETCH_ASSOC);

        } catch (Throwable) {
            $data = $this->handle->errorInfo();
            throw new DatabaseException("query <$stmt> failed! ($data[0]: $data[1] - $data[2])");
        }
        return $res;
    }

    /**
     * @throws DatabaseException
     */
    public function selectRow(string $stmt, array $params = [], int $row = 0): array
    {
        $res = $this->select($stmt, $params, $row, 1);
        if(empty($res)) {
            return [];
        }
        return array_shift($res);
    }

    /**
     * @throws DatabaseException
     */
    public function selectSingle(string $stmt, array $params = [])
    {
        $res = $this->selectRow($stmt, $params);
        if(empty($res)) {
            return null;
        }
        return array_shift($res);
    }

    /**
     * @throws DatabaseException
     */
    public function selectKeyValue(
        string $key, string $value, string $table, array $conditions = [], array $params = []
    ): array
    {
        $this->checkIdentifier($key);
        $this->checkIdentifier($value);
        $this->checkIdentifier($table);

        /** @noinspection SqlResolve */
        $stmt = "SELECT `$key` AS `k`, `$value` AS `v` FROM `$table`";
        $res = $this->query($this->addConditions($stmt, $conditions), $params);
        $rv = [];

        foreach($res as $row) {
            $rv[$row['k']] = $row['v'];
        }

        return $rv;
    }

    /**
     * @throws DatabaseException
     */
    public function selectKeyValues(
        string $key, array $values, string $table, array $conditions = [], array $params = []
    ): array
    {
        $this->checkIdentifier($key);
        $this->checkIdentifier($table);

        $stmt = "SELECT `$key` AS `k`, `" . implode("`, `", $values) . "` FROM `$table`";
        $res = $this->query($this->addConditions($stmt, $conditions), $params);
        $rv = [];

        foreach($res as $row) {
            $key = $row['k'];
            unset($row['k']);
            $rv[$key] = $row;
        }

        return $rv;
    }

    /**
     * @throws DatabaseException
     */
    public function selectCount(string $table, array $conditions = [], array $params = []): int
    {
        /** @noinspection SqlResolve */
        $stmt = "SELECT COUNT(*) AS `cnt` FROM `$table`";
        return $this->selectSingle($this->addConditions($stmt, $conditions), $params);
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
        if ($data instanceof DateTimeInterface) {
            $data = $data->format('Y-m-d H:i:s');
        }
        return $this->handle->quote((string)$data);
    }

    protected function getStatement(string $stmt, array $params = []): string
    {
        if (!empty($params)) {
            $params = array_map([$this, 'escape'], $params);
            $stmt = vsprintf($stmt, $params);
        }

        return str_replace('{TABLE_PREFIX}', $this->prefix, $stmt);
    }

    protected function addLimit(string $stmt, ?int $count, int $offset = 0): string
    {
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
                $item = "`$column` IN({$this->escape($item)})";
            } else if (is_null($item)) {
                $item = "`$column` IS NULL";
            } else if (is_scalar($item) || $item instanceof Stringable) {
                $item = "`$column` = {$this->escape($item)}";
            } else {
                throw new DatabaseException("Invalid type for column <$column> given");
            }
        }

        return "$stmt WHERE " . implode(' AND ', $conditions);
    }

    /**
     * @throws DatabaseException
     */
    private function checkIdentifier(string $name): void
    {
        if(preg_match('/^[a-zA-Z0-9_{}]+$/', $name) !== 1) {
            throw new DatabaseException("Invalid type for column <$name> given");
        }
    }
}