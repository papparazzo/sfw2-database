<?php

namespace SFW2\Database;

use SFW2\Database\Exception as DatabaseException;
use SQLite3;

final class Sqlite extends DatabaseAbstract
{
    private SQLite3 $handle;

    public function __construct(string $fileName, string $prefix = 'sfw2') {
        parent::__construct($prefix);
        $this->handle = new SQLite3($fileName);
    }

    /**
     * @throws Exception
     */
    public function select(string $stmt, array $params = [], ?int $count = null, int $offset = 0): array {
        $stmt = $this->addLimit($stmt, $count, $offset);

        $res = $this->query($stmt, $params);
        $rv = [];

        /** @noinspection PhpAssignmentInConditionInspection */
        while(($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $rv[] = $row;
        }
       # $res->close();
        return $rv;
    }

    /**
     * @throws Exception
     */
    public function selectKeyValue(string $key, string $value, string $table, array $conditions = [], array $params = []): array
    {
        $key = $this->escape($key);
        $value = $this->escape($value);
        $table = $this->escape($table);

        $res = $this->query($this->addConditions("SELECT `$key` AS `k`, `$value` AS `v` FROM `$table`", $conditions), $params);
        $rv = [];

        /** @noinspection PhpAssignmentInConditionInspection */
        while(($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $rv[$row['k']] = $row['v'];
        }
      #  $res->close();
        return $rv;
    }

    /**
     * @throws Exception
     */
    public function selectKeyValues(string $key, array $values, string $table, array $conditions = [], array $params = []): array
    {
        $key = $this->escape($key);
        $table = $this->escape($table);

        $res = $this->query($this->addConditions("SELECT `$key` AS `k`, `" . implode("`, `", $values) . "` FROM `$table`", $conditions), $params);
        $rv = [];

        /** @noinspection PhpAssignmentInConditionInspection */
        while(($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $key = $row['k'];
            unset($row['k']);
            $rv[$key] = $row;
        }
       # $res->close();
        return $rv;
    }

    /**
     * @throws Exception
     */
    public function query(string $stmt, array $params = [])
    {
        if (!empty($params)) {
            $params = array_map([$this, 'escape'], $params);
            $stmt = vsprintf($stmt, $params);
        }

        $stmt = str_replace('{TABLE_PREFIX}', $this->prefix, $stmt);

        $res = $this->handle->query($stmt);
        if($res === false) {
            throw new DatabaseException("query <$stmt> failed! ({$this->handle->lastErrorMsg()})", DatabaseException::QUERY_FAILED);
        }
        return $res;
    }

     protected function getAffectedRows(): int
     {
         return $this->handle->changes();
     }

    protected function escapeString(string $string): string
    {
          return SQLite3::escapeString($string);
    }

    protected function getLastInsertedId(): int
    {
        return $this->handle->lastInsertRowID();
    }
}