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
use SFW2\Database\Exception as DatabaseException;
use Throwable;

/**
 * @noinspection PhpUnused
 */
class Database implements DatabaseInterface {

    protected PDO $handle;

    /**
     * @throws Exception
     */
    public function __construct(
        protected string $dsn,
        protected string $usr,
        protected string $pwd,
        protected array $options = [],
        protected string $prefix = 'sfw2'
    ) {
        $this->connect();
    }

    protected function connect(): void {
        $this->handle = new PDO($this->dsn, $this->usr, $this->pwd);
        #  throw new DatabaseException("Could not connect to database <$err>", DatabaseException::INIT_CONNECTION_FAILED);
        # FIXME: Nur bei mysql:
        # $this->query("set names 'utf8';");
    }

    /**
     * @throws Exception
     */
    public function __wakeup(): void {
        $this->connect();
    }

    public function __sleep(): array {
        #unset($this->handle);
        return [];
    }

    public function delete(string $stmt, array $params = []): int {
        return $this->update($stmt, $params);
    }

    public function update(string $stmt, array $params = []): int {
        return $this->handle->exec($this->getStatement($stmt, $params));
    }

    public function insert(string $stmt, array $params = []): int {
        $this->handle->exec($this->getStatement($stmt, $params));
        return $this->handle->lastInsertId();
    }

    /**
     * @throws Exception
     */
    public function select(string $stmt, array $params = [], ?int $count = null, int $offset = 0): array {
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
     * @throws Exception
     */
    public function query(string $stmt, array $params = [])
    {
        try {
            $res = $this->handle->query($stmt, PDO::FETCH_ASSOC);

        } catch (Throwable) {
            $data = $this->handle->errorInfo();
            throw new DatabaseException("query <$stmt> failed! ($data[0]: $data[1] - $data[2])", DatabaseException::QUERY_FAILED);
        }

        return $res;
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
    public function selectKeyValue(string $key, string $value, string $table, array $conditions = [], array $params = []): array {
        $key = $this->escape($key);
        $value = $this->escape($value);
        $table = $this->escape($table);

        $res = $this->query($this->addConditions("SELECT `$key` AS `k`, `$value` AS `v` FROM `$table`", $conditions), $params);
        $rv = [];

        foreach($res as $row) {
            $rv[] = $row;
        }

        /** @noinspection PhpAssignmentInConditionInspection */
        while(($row = $res->fetch_assoc())) {
            $rv[$row['k']] = $row['v'];
        }

        return $rv;
    }

    /**
     * @throws Exception
     */
    public function selectKeyValues(string $key, array $values, string $table, array $conditions = [], array $params = []): array {
        $key = $this->escape($key);
        $table = $this->escape($table);

        $res = $this->query($this->addConditions("SELECT `$key` AS `k`, `" . implode("`, `", $values) . "` FROM `$table`", $conditions), $params);
        $rv = [];

        /** @noinspection PhpAssignmentInConditionInspection */
        while(($row = $res->fetch_assoc())) {
            $key = $row['k'];
            unset($row['k']);
            $rv[$key] = $row;
        }
        $res->close();
        return $rv;
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
        return $this->handle->quote((string)$data);
    }

    protected function getStatement(string $stmt, array $params = []): string {
        if (!empty($params)) {
            $params = array_map([$this, 'escape'], $params);
            $stmt = vsprintf($stmt, $params);
        }

        return str_replace('{TABLE_PREFIX}', $this->prefix, $stmt);
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
}
