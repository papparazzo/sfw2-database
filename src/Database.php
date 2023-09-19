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

use mysqli_result;
use SFW2\Database\Exception as DatabaseException;
use mysqli;

/**
 * @noinspection PhpUnused
 */
final class Database extends DatabaseAbstract {

    protected mysqli $handle;

    /**
     * @throws Exception
     */
    public function __construct(
        private readonly string $host,
        private readonly string $usr,
        private readonly string $pwd,
        private readonly string $db,
        string $prefix = 'sfw2'
    ) {
        parent::__construct($prefix);
        $this->connect($host, $usr, $pwd, $db);
    }

    /**
     * @throws Exception
     */
    protected function connect(string $host, string $usr, string $pwd, string $db): void {
        $this->handle = new mysqli('p:' . $host, $usr, $pwd, $db);
        $err = mysqli_connect_error();

        if($err) {
            throw new DatabaseException("Could not connect to database <$err>", DatabaseException::INIT_CONNECTION_FAILED);
        }
        $this->query("set names 'utf8';");
    }

    /**
     * @throws Exception
     */
    public function __wakeup(): void {
        $this->connect($this->host, $this->usr, $this->pwd, $this->db);
    }

    public function __sleep(): array {
        $this->handle->close();
        return [];
    }

    /**
     * @throws Exception
     */
    public function select(string $stmt, array $params = [], ?int $count = null, int $offset = 0): array {
        $stmt = $this->addLimit($stmt, $count, $offset);

        $res = $this->query($stmt, $params);
        $rv = [];

        /** @noinspection PhpAssignmentInConditionInspection */
        while(($row = $res->fetch_assoc())) {
            $rv[] = $row;
        }
        $res->close();
        return $rv;
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

        /** @noinspection PhpAssignmentInConditionInspection */
        while(($row = $res->fetch_assoc())) {
            $rv[$row['k']] = $row['v'];
        }
        $res->close();
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
    public function query(string $stmt, array $params = []): mysqli_result|null
    {
        if (!empty($params)) {
            $params = array_map([$this, 'escape'], $params);
            $stmt = vsprintf($stmt, $params);
        }

        $stmt = str_replace('{TABLE_PREFIX}', $this->prefix, $stmt);

        $res = $this->handle->query($stmt);
        if($res === false) {
            throw new DatabaseException("query <$stmt> failed! ({$this->handle->error})", DatabaseException::QUERY_FAILED);
        }
        if ($res === true) {
            return null;
        }
        return $res;
    }

    protected function getAffectedRows(): int
    {
        return $this->handle->affected_rows;
    }

    protected function getLastInsertedId(): int {
        return $this->handle->insert_id;
    }

    protected function escapeString(string $string): string
    {
        return $this->handle->real_escape_string($string);
    }
}
