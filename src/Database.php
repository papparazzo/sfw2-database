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

use SFW2\Database\Database\Exception as DatabaseException;
use mysqli;

class Database implements DatabaseInterface {

    protected mysqli $handle;

    protected string $host;
    protected string $usr;
    protected string $pwd;
    protected string $db;

    protected string $prefix = '';

    public function __construct(string $host, string $usr, string $pwd, string $db, string $tablePrefix = 'sfw2') {
        $this->host   = $host;
        $this->usr    = $usr;
        $this->pwd    = $pwd;
        $this->db     = $db;
        $this->prefix = $tablePrefix;
        $this->connect($host, $usr, $pwd, $db);
    }

    protected function connect(string $host, string $usr, string $pwd, string $db) : void {
        $this->handle = new mysqli('p:' . $host, $usr, $pwd, $db);
        $err = mysqli_connect_error();

        if($err) {
            throw new DatabaseException("Could not connect to database <$err>", DatabaseException::CON_FAILED);
        }
        $this->query("set names 'utf8';");
    }

    public function __wakeup() : void {
        $this->connect($this->host, $this->usr, $this->pwd, $this->db);
    }

    public function __sleep() : void {
        $this->handle->close();
    }

    public function delete(string $stmt, array $params = []) : int {
        return $this->update($stmt, $params);
    }

    public function update(string $stmt, array $params = []) : int {
        $params = $this->escape($params);
        $stmt = vsprintf($stmt, $params);
        $this->query($stmt);
        return $this->handle->affected_rows;
    }

    public function insert(string $stmt, array $params = []) : int {
        $params = $this->escape($params);
        $stmt = vsprintf($stmt, $params);
        $this->query($stmt);
        return $this->handle->insert_id;
    }

    public function select(string $stmt, Array $params = [], int $offset = -1, int $count = -1) : array {
        $params = $this->escape($params);
        $stmt  = vsprintf($stmt, $params);
        $stmt .= $this->addLimit($offset, $count);

        $res = $this->query($stmt);
        $rv = [];
        while(($row = $res->fetch_assoc())) {
            $rv[] = $row;
        }
        $res->close();
        return $rv;
    }

    public function selectRow(string $stmt, array $params = [], int $row = 0) : array {
        $res = $this->select($stmt, $params, $row, 1);
        if(empty($res)) {
            return [];
        }
        return array_shift($res);
    }

    public function selectSingle(string $stmt, array $params = []) {
        $res = $this->selectRow($stmt, $params);
        if(empty($res)) {
            return null;
        }
        return array_shift($res);
    }

    public function selectKeyValue(string $key, string $value, string $table, string $condition = "", array $params = []) : array {
        $key = $this->escape($key);
        $value = $this->escape($value);
        $table = $this->escape($table);
        $params = $this->escape($params);

        $stmt =
            "SELECT `" . $key . "` AS `k`, `" . $value . "` AS `v` " .
            "FROM `" . $table . "` " .
            $condition;

        $stmt  = vsprintf($stmt, $params);
        $res = $this->query($stmt);
        $rv = [];
        while(($row = $res->fetch_assoc())) {
            $rv[$row['k']] = $row['v'];
        }
        $res->close();
        return $rv;
    }

    public function selectKeyValues(string $key, array $values, string $table, string $condition = "", array $params = []) : array {
        $key = $this->escape($key);
        $table = $this->escape($table);
        $values = $this->escape($values);
        $params = $this->escape($params);

        $stmt =
            "SELECT `" . $key . "` AS `k`, `" .
            implode("`, `", $values) . "` " .
            "FROM `" . $table . "` " .
            $condition;

        $stmt  = vsprintf($stmt, $params);
        $res = $this->query($stmt);
        $rv = [];
        while(($row = $res->fetch_assoc())) {
            $key = $row['k'];
            unset($row['k']);
            $rv[$key] = $row;
        }
        $res->close();
        return $rv;
    }

    public function selectCount(string $table, string $condition = "", array $params = []) {
        $stmt =
            "SELECT COUNT(*) AS `cnt` " .
            "FROM `" . $table . "` " .
            $condition;

        return $this->selectSingle($stmt, $params);
    }

    public function entryExists(string $table, string $column, string $content) : bool {
        $where = [];
        $where[] = '`' . $column . '` = \''. $this->escape($content) . '\'';
        if($this->selectCount($table, $where) == 0) {
            return false;
        }
        return true;
    }

    public function escape($data) {
        if(!is_array($data)) {
            return $this->handle->escape_string($data);
        }
        $rv = [];
        foreach($data as $v) {
            $rv[] = $this->escape($v);
        }
        return $rv;
    }

    public function query(string $stmt) {
        $stmt = str_replace('{TABLE_PREFIX}', $this->prefix, $stmt);

        $res = $this->handle->query($stmt);
        if($res === false) {
            throw new DatabaseException(
                'query "' . $stmt . '" failed!' . PHP_EOL . PHP_EOL . $this->handle->error,
                DatabaseException::QUERY_FAILED
            );
        }
        return $res;
    }

    private function addLimit(int $offset, int $count) : string {
        $offset = preg_replace('/[^0-9-]/', '', $offset);
        $count = preg_replace('/[^0-9-]/', '', $count);

        if($offset == -1 || $count == -1) {
            return "";
        }
        if($offset == "" || $count == "") {
            $offset = 0;
            $count = 10;
        }
        return " LIMIT " . $offset . ", " . $count;
    }
}
