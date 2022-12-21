<?php

/*
 * Copyright (c) 2022. Der Code ist geistiges Eigentum von Tim Anthony Alexander.
 * Der Code wurde geschrieben unter dem Arbeitstitel und im Auftrag von coalla.
 * Verwendung dieses Codes außerhalb von coalla von Dritten ist ohne ausdrückliche Zustimmung von Tim Anthony Alexander nicht gestattet.
 */

namespace src\module\QueryBuilder;

use src\module\BetterSQL\BetterSQL;
use src\module\InstantCache\InstantCache;
use src\module\PrettyJson\PrettyJson;

/**
 * @copyright Tim Anthony Alexander @ coalla
 */
class QueryBuilder
{
    private const REPLACEMENT_STRING = '¢[]|';

    /**
     * @var string
     */
    public string $query = '';

    private bool $select = false;

    private string $currentTable = '';

    public static function create(): self
    {
        return new self();
    }

    /**
     * @return $this
     */
    public function select(
        string $table,
        bool $distinct = false,
        array $columns = ['*'],
    ): self {
        $this->select = true;
        $this->currentTable = $table;
        $verb = $distinct ? 'SELECT DISTINCT' : 'SELECT';
        $columns = self::quotify(true, $columns);
        return $this->withQuery(sprintf('%s %s FROM `%s` ', $verb, implode(', ', $columns), $table));
    }

    /**
     */
    public function selectCount(
        string $table,
        bool $distinct = false,
        array $columns = ['*'],
    ): self {
        $this->select = true;
        $this->currentTable = $table;
        $columns = self::quotify(true, $columns);
        return $this->withQuery(sprintf('SELECT COUNT(%s) FROM `%s` ', implode(', ', $columns), $table));
    }

    /**
     * @return $this
     */
    public function reset(): self
    {
        $this->select = false;
        $this->currentTable = '';
        $this->query = '';
        return $this;
    }

    public function alterTable(
        string $table,
        array $columns = [
            ['name' => 'type']
        ]
    ): self
    {
        $this->select = false;
        $this->currentTable = $table;
        $this->query = sprintf('ALTER TABLE `%s` ', $table);
        foreach ($columns as $column) {
            $this->query .= sprintf('ADD COLUMN `%s` %s ', $column['name'], $column['type']);
        }
        return $this;
    }

    public function createTable(
        string $table,
        array $columns = [
            [
                'name' => 'id',
                'type' => 'int',
                'null' => false,
                'auto_increment' => true,
                'default' => null,
                'primary_key' => true,
            ]
        ]
    ): self
    {
        $this->currentTable = $table;
        $this->query = sprintf('CREATE TABLE `%s` (', $table);
        foreach ($columns as $column) {
            $this->query .= sprintf(
                '`%s` %s %s %s %s %s, ',
                $column['name'],
                $column['type'],
                $column['null'] ? 'NULL' : 'NOT NULL',
                $column['auto_increment'] ? 'AUTO_INCREMENT' : '',
                $column['default'] ? 'DEFAULT ' . $column['default'] : '',
                $column['primary_key'] ? 'PRIMARY KEY' : ''
            );
        }
        $this->query = rtrim($this->query, ', ');
        $this->query .= ')';

        return $this;
    }

    public function isSelect(): bool
    {
        return $this->select;
    }

    private static function buildSetString(array $elements): string
    {
        $set = [];
        foreach ($elements as $key => $value) {
            $set[] = match (true) {
                is_bool($value) => sprintf('`%s` = %s', $key, $value ? 1 : 0),
                is_int($value) => sprintf('`%s` = %d', $key, $value),
                str_contains((string) $value, "'") => sprintf('`%s` = \'%s\'', $key, str_replace("'", "''", (string) $value)),
                default => sprintf("`%s` = '%s'", $key, $value),
            };
        }
        return implode(', ', $set);
    }

    /**
     * @return $this
     */
    public function update(
        string $table,
        array $elements = ['column1' => 'value1', 'column2' => 'value2']
    ): self {
        $GLOBALS['UPDATEDTABLES'][] = strtolower(trim($table));
        $this->currentTable = $table;
        return $this->withQuery(sprintf('UPDATE `%s` ', $table) . sprintf('SET %s ', self::buildSetString($elements)));
    }

    /**
     * @return $this
     */
    private function withQuery(string $query, string ...$replacements): self
    {
        foreach ($replacements as $replacement) {
            $pos = strpos($query, self::REPLACEMENT_STRING);
            if ($pos !== false) {
                $query = substr_replace($query, $replacement, $pos, strlen(self::REPLACEMENT_STRING));
            }
        }
        $this->query .= $query;
        return $this;
    }

    /**
     * @return $this
     */
    public function delete(
        string $table,
    ): self {
        $GLOBALS['UPDATEDTABLES'][] = strtolower(trim($table));
        $this->currentTable = $table;
        return $this->withQuery(sprintf('DELETE FROM `%s` ', $table));
    }

    /**
     * @param string|null $custom
     * @param string|null $customColumns
     * @return $this
     */
    public function insert(
        string $table,
        array $elements = ['column1' => 'value1', 'column2' => 'value2'],
        bool $replace = true,
        string $custom = null,
        string $customColumns = null,
    ): self {
        $this->currentTable = $table;
        $GLOBALS['UPDATEDTABLES'][] = strtolower(trim($table));
        $verb = $replace ? 'REPLACE' : 'INSERT';
        if ($custom !== null) {
            return $this->withQuery(
                sprintf('%s INTO %s (%s) VALUES %s', $verb, $table, $customColumns, $custom)
            );
        }
        $columns = self::quotify(true, array_keys($elements));
        $columnsString = implode(', ', $columns);
        $values = self::quotify(false, array_values($elements));
        $valuesString = implode(', ', $values);
        return $this->withQuery(
            self::REPLACEMENT_STRING . ' INTO `' . self::REPLACEMENT_STRING . '` (' . self::REPLACEMENT_STRING . ') VALUES (' . self::REPLACEMENT_STRING . ') ',
            $verb,
            $table,
            $columnsString,
            $valuesString
        );
    }

    public static function quotify(
        bool $isColumn,
        array $elements = ['value1', 'value2'],
    ): array {
        $values = array_values($elements);
        // Unset null values
        $values = array_filter($values, static fn($value): bool => $value !== null);
        if ($isColumn) {
            $return = [];
            foreach ($values as $value) {
                if ($value === '?') {
                    throw new \RuntimeException('Columns cannot contain ?');
                }
                $return[] = in_array($value, [1, '*', 'NULL'], true)
                    ? $value
                    : sprintf("`%s`", $value);
            }
            return $return;
        }
        return array_map(
            static fn($value) => match (true) {
                is_bool($value) => $value ? 'TRUE' : 'FALSE',
                is_int($value) => $value,
                ($value === '?') => $value,
                str_contains((string) $value, "'") => sprintf("'%s'", str_replace("'", "''", (string) $value)),
                default => sprintf("'%s'", $value),
            },
            $values
        );
    }

    /**
     * @return $this
     */
    public function alter(
        string $table,
    ): self {
        $GLOBALS['UPDATEDTABLES'][] = strtolower(trim($table));
        return $this->withQuery('ALTER TABLE ' . sprintf('`%s` ', $table));
    }

    /**
     * @return $this
     */
    public function add(
        string $column,
        string $type,
    ): self {
        return $this->withQuery(sprintf('ADD `%s` %s ', $column, $type));
    }

    /**
     * @return $this
     */
    public function where(
        array $elements = ['column1' => 'value1', 'column2' => 'value2'],
        bool $like = false,
        bool $or = false,
        array $startsWith = [false, false],
        array $endsWith = [false, false],
        bool $custom = false,
        string $customString = '',
        bool $inBrackets = false,
        bool $lower = false,
        bool $not = false,
    ): self {
        if ($custom) {
            return $this->withQuery(sprintf('WHERE `%s` ', $customString));
        }
        $first = true;
        $index = 0;
        foreach ($elements as $key => $value) {
            if (!is_array($value)) {
                $this->advancedWhere(
                    $key,
                    $value,
                    $like,
                    $first,
                    $or,
                    $startsWith[$index] ?? false,
                    $endsWith[$index] ?? false,
                    totallyInBrackets: $inBrackets,
                    lower: $lower,
                    not: $not,
                );
            } else {
                $this->whereIn($key, $value, $first, $or);
            }
            $first = false;
            $index++;
        }
        return $inBrackets ? $this->withQuery(') ') : $this;
    }

    public function whereIn(
        string $key,
        array $values,
        bool $first = true,
        bool $or = false,
    ): self {
        $values = self::quotify(false, $values);
        $valuesString = implode(', ', $values);

        return $first
            ? $this->withQuery(sprintf(' WHERE %s IN (%s) ', $key, $valuesString))
            : ($or
                ? $this->withQuery(sprintf(' OR `%s` IN (%s) ', $key, $valuesString))
                : $this->withQuery(sprintf(' AND `%s` IN (%s) ', $key, $valuesString))
            );
    }

    /**
     * @param string|null $customOperator
     * @return $this
     */
    public function advancedWhere(
        string $column,
        string $value,
        bool $like = false,
        bool $isFirstWhereInQuery = true,
        bool $or = false,
        bool $startsWith = false,
        bool $endsWith = false,
        bool $custom = false,
        string $customString = '',
        string $customOperator = null,
        bool $inBrackets = false,
        bool $totallyInBrackets = false,
        bool $lower = false,
        bool $not = false,
    ): self {
        if ($custom) {
            return $this->withQuery(sprintf('WHERE `%s` ', $customString));
        }
        if ($like) {
            if (!($startsWith)) {
                $value = '%' . $value;
            }
            if (!($endsWith)) {
                $value .= '%';
            }
        }
        $operator = $isFirstWhereInQuery
            ? 'WHERE'
            : ($or
                ? 'OR'
                : 'AND');
        $operator = $totallyInBrackets ? $operator . ' (' : $operator;
        $likeoperator = $like ? 'LIKE' : ($customOperator ?? $not ? '!=' : '=');
        $where = match (true) {
            str_contains($value, '?') => sprintf(
                $lower ? 'LOWER(`%s`) %s %s' : '`%s` %s %s',
                $column, $likeoperator, $value
            ),
            str_contains($value, "'") => sprintf(
                $lower ? 'LOWER(`%s`) %s \'%s\'' : '`%s` %s \'%s\'',
                $column, $likeoperator, str_replace("'", "''", $value)
            ),
            default => sprintf($lower ? "LOWER(`%s`) %s '%s'" : "`%s` %s '%s'", $column, $likeoperator, $value),
        };
        if ($inBrackets) {
            $where = sprintf('(%s)', $where);
        }
        return $this->withQuery(sprintf('%s %s ', $operator, $where));
    }

    /**
     * @return $this
     */
    public function bracket(string $bracket = '('): self
    {
        return $this->withQuery(sprintf('%s ', $bracket));
    }

    /**
     * @return $this
     */
    public function orderBy(string $order = '', string $oderby = 'DESC'): self
    {
        return $this->withQuery('ORDER BY `' . self::REPLACEMENT_STRING . '` ' . self::REPLACEMENT_STRING . ' ', $order, $oderby);
    }

    /**
     * @return $this
     */
    public function limit(
        string|int $limit = 1
    ): self {
        return $this->withQuery('LIMIT ' . self::REPLACEMENT_STRING . ' ', (string) $limit);
    }

    public static function unsetNumKeys(array $data): array
    {
        $array_out = [];
        if (array_is_list($data)) {
            foreach ($data as $k => $v) {
                $array_out[$k] = self::unsetNumKeys($v);
            }
            return $array_out;
        }
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $array_out[$k] = self::unsetNumKeys($v);
            } elseif (!is_numeric($k)) {
                $array_out[$k] = $v;
            }
        }
        return $array_out;
    }

    public function run(
        bool $fetchFirst = false,
        bool $fetchFirstFirst = false,
        array $args = [],
        bool $debug = false
    ): mixed {
        if ($debug) {
            printf('%s%s%s', PHP_EOL, $this->query, PHP_EOL);
        }

        $uuid = self::generateUuid($args, $this->query, $fetchFirst, $fetchFirstFirst);

        $query = $GLOBALS['queries'][$uuid]['query'] = $this->build($args);

        $GLOBALS['queries'][$uuid]['args'] = $args;
        $GLOBALS['queries'][$uuid]['uuid'] = $uuid;

        if (!isset($GLOBALS['countsforquery'][$query])) {
            $GLOBALS['countsforquery'][$query] = 0;
        } else {
            $GLOBALS['countsforquery'][$query]++;
        }

        $GLOBALS['queries'][$uuid]['count'] = $GLOBALS['countsforquery'][$query];

        if (!isset($GLOBALS['queries'][$uuid]['totalms'])) {
            $GLOBALS['queries'][$uuid]['totalms'] = 0;
        }

        // And the debug_backtrace()[2]['class'] but only the class name itself

        $before = microtime(true);

        $return = $this->cachedExecute($fetchFirst, $fetchFirstFirst, $args, $query);

        $total = round((microtime(true) - $before) * 1000);
        $GLOBALS['queries'][$uuid]['totalms'] += $total;

        return $return;
    }

    private static function generateUuid(array $args, string $query, bool $fetchFirst, bool $fetchFirstFirst): string
    {
        $fetchString = $fetchFirst ? 'fetchFirst' : 'fetchAll' . ($fetchFirstFirst ? 'First' : 'Not');
        return md5($query . PrettyJson::encode($args) . $fetchString);
    }

    private function cachedExecute(
        bool $fetchFirst = false,
        bool $fetchFirstFirst = false,
        array $args = [],
        string $query = '',
    ): mixed {
        $uuid = self::generateUuid($args, $query, $fetchFirst, $fetchFirstFirst);

        if (!isset($GLOBALS['UPDATEDTABLES'])) {
            $GLOBALS['UPDATEDTABLES'] = [];
        }

        if (instantcache::isset($uuid) && $this->isSelect() && !in_array(strtolower(trim($this->currentTable)), $GLOBALS['UPDATEDTABLES'], true)) {
            return instantcache::get($uuid);
        }

        $beforeActual = microtime(true);
        $return = BetterSQL::runQuery($this, $fetchFirst, $fetchFirstFirst, $args);
        $totalActual = round((microtime(true) - $beforeActual) * 1000);
        if (isset($GLOBALS['queries'][$uuid]['totalmsactual'])) {
            $GLOBALS['queries'][$uuid]['totalmsactual'] += $totalActual;
        } else {
            $GLOBALS['queries'][$uuid]['totalmsactual'] = $totalActual;
        }

        instantcache::set($uuid, $return);

        return $return;
    }

    public static function sortQueries(): void
    {
        if (isset($GLOBALS['queries'])) {
            foreach ($GLOBALS['queries'] as $uuid => $query) {
                if (!isset($GLOBALS['queries'][$uuid]['uuid'])) {
                    unset($GLOBALS['queries'][$uuid]);
                    continue;
                }
                if (!isset($GLOBALS['queries'][$uuid]['totalms'])) {
                    $GLOBALS['queries'][$uuid]['totalms'] = 0;
                }
            }
            usort($GLOBALS['queries'], static fn($a, $b): int => ($a['totalms'] > $b['totalms']) ? -1 : 1);
        }
    }

    public function setSelect(bool $select): void
    {
        $this->select = $select;
    }

    public function setQuery(string $query): self
    {
        $this->query = $query;
        return $this;
    }

    /**
     * This method builds the final
     * @throws \Exception
     */
    public function build(array $values = []): string
    {
        if ($values !== []) {
            $count = substr_count($this->query, '?');
            if (count($values) !== $count) {
                throw new \Exception('Not enough values in the array: ' . $this->query . ' ' . count($values) . ' ' . $count . ' ' . json_encode($values, JSON_THROW_ON_ERROR));
            }

            $query = str_replace(['%', '?'], [self::REPLACEMENT_STRING, '%s'], $this->query);
            $query = sprintf($query, ...$values);
            $query = str_replace(self::REPLACEMENT_STRING, '%', $query);

            $query = trim($query);
            return trim($query, ';') . ';';
        }
        return trim(trim($this->query), ';') . ';';
    }
}
