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

interface DatabaseInterface {

    public function delete(string $stmt, array $params = []): int;

    public function update(string $stmt, array $params = []): int;

    public function insert(string $stmt, array $params = []): int;

    public function select(string $stmt, Array $params = [], int $offset = -1, int $count = -1): array;

    public function selectRow(string $stmt, array $params = [], int $row = 0): array;

    public function selectSingle(string $stmt, array $params = []);

    public function selectKeyValue(string $key, string $value, string $table, string $condition = "", array $params = []): array;

    public function selectKeyValues(string $key, array $values, string $table, string $condition = "", array $params = []): array;

    public function selectCount(string $table, string $condition = "", array $params = []): int;

    public function entryExists(string $table, string $column, string $content): bool;

    public function escape($data);

    public function query(string $stmt);

}
