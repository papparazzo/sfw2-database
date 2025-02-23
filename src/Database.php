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
 */

namespace SFW2\Database;

use PDO;
use PDOStatement;
use Throwable;
use DateTimeInterface;

/**
 * @noinspection PhpUnused
 */
class Database implements DatabaseInterface
{

    protected PDO $handle;

    /**
     * @param string $dsn
     * @param string $usr
     * @param string $pwd
     * @param array<string, string> $options
     * @param string $prefix
     */
    public function __construct(
        protected string $dsn,
        protected string $usr,
        protected string $pwd,
        protected array  $options = [],
        protected string $prefix = 'sfw2'
    ) {
        $this->connect();
    }

    protected function connect(): void
    {
        $this->handle = new PDO($this->dsn, $this->usr, $this->pwd);
        // throw new DatabaseException("Could not connect to database <$err>");
        // FIXME: Nur bei mysql:
        // $this->query("set names 'utf8';");
    }

    public function __wakeup(): void
    {
        $this->connect();
    }

    public function __sleep(): array
    {
        // unset($this->handle);
        return [];
    }

    public function delete(string $stmt, array $params = []): int
    {
        return $this->update($stmt, $params);
    }

    public function update(string $stmt, array $params = []): int
    {
        return (int)$this->handle->exec($this->getStatement($stmt, $params));
    }

    public function insert(string $stmt, array $params = []): int
    {
        $this->handle->exec($this->getStatement($stmt, $params));
        return (int)$this->handle->lastInsertId();
    }

    /**
     * @throws DatabaseException
     */
    public function select(string $stmt, array $params = [], ?int $count = null, int $offset = 0): array
    {
        $stmt = $this->addLimit($stmt, $count, $offset);
        $res = $this->query($stmt, $params);
        $rv = [];

        foreach ($res as $row) {
            $rv[] = $row;
        }
        return $rv;
    }

    public function query(string $stmt, array $params = []): bool|PDOStatement
    {
        try {
            $stmt = $this->getStatement($stmt, $params);
            $res = $this->handle->query($stmt, PDO::FETCH_ASSOC);
        } catch (Throwable) {
            /** @var array{string, int, string} $data */
            $data = $this->handle->errorInfo();
            throw new DatabaseException("query <$stmt> failed! ($data[0]: $data[1] - $data[2])");
        }
        return $res;
    }

    public function escape(mixed $data): string
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

    /**
     * @param string $stmt
     * @param array<string, mixed> $params
     * @return string
     */
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
}