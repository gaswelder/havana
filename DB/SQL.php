<?php

namespace DB;

class SQL
{
    static function insert($table, $row)
    {
        $cols = array_keys($row);
        $header = '("' . implode('", "', $cols) . '")';

        $placeholders = array_fill(0, count($row), '?');
        $tuple = '(' . implode(', ', $placeholders) . ')';

        $q = "INSERT INTO \"$table\" $header VALUES $tuple";
        $args = array_values($row);
        return [$q, $args];
    }

    static function update($table, $values, $filter)
    {
        $q = 'UPDATE "' . $table . '" SET ';

        $args = [];

        $set = [];
        foreach ($values as $field => $value) {
            $set[] = '"' . $field . '" = ?';
            $args[] = $value;
        }
        $q .= implode(', ', $set);

        $where = [];
        foreach ($filter as $field => $value) {
            $where[] = '"' . $field . '" = ?';
            $args[] = $value;
        }

        $q .= ' WHERE ' . implode(' AND ', $where);

        return [$q, $args];
    }

    static function select($table, $fields, $filter, $order)
    {
        $cond = [];
        $values = [];
        foreach ($filter as $field => $value) {
            if ($value === null) {
                $cond[] = '"' . $field . '" IS NULL';
                continue;
            }
            $cond[] = '"' . $field . '" = ?';
            $values[] = $value;
        }
        $keysList = '"' . implode('", "', $fields) . '"';

        $q = "SELECT $keysList FROM \"$table\"";
        if (!empty($cond)) {
            $q .= ' WHERE ' . implode(' AND ', $cond);
        }
        if ($order) {
            $q .= " ORDER BY $order";
        }
        return [$q, $values];
    }
}
