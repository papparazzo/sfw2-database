<?php

/*
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

use DateTimeInterface;
use IteratorAggregate;

/**
 * @phpstan-type StatementParam scalar|string[]|DateTimeInterface|null
 */
interface DatabaseInterface
{
    /**
     * @param string $stmt
     * @param array<string, StatementParam> $params
     * @return int
     * @throws DatabaseException
     */
    public function delete(string $stmt, array $params = []): int;

    /**
     * @param string $stmt
     * @param array<string, StatementParam> $params
     * @return int
     * @throws DatabaseException
     */
    public function update(string $stmt, array $params = []): int;

    /**
     * @param string $stmt
     * @param array<string, StatementParam> $params
     * @return int
     * @throws DatabaseException
     */
    public function insert(string $stmt, array $params = []): int;

    /**
     * @param string $stmt
     * @param array<string, StatementParam> $params
     * @param int|null $count
     * @param int $offset
     * @return list<array<string, string>>
     * @throws DatabaseException
     */
    public function select(string $stmt, array $params = [], ?int $count = null, int $offset = 0): array;

    /**
     * @param StatementParam $data
     * @return string
     */
    public function escape(mixed $data): string;

    /**
     * @param string $stmt
     * @param array<string, StatementParam> $params
     * @return IteratorAggregate
     * @throws DatabaseException
     */
    public function query(string $stmt, array $params = []): IteratorAggregate;

}
