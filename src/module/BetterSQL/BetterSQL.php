<?php

/*
 * Copyright (c) 2022. Der Code ist geistiges Eigentum von Tim Anthony Alexander.
 * Der Code wurde geschrieben unter dem Arbeitstitel und im Auftrag von coalla.
 * Verwendung dieses Codes außerhalb von coalla von Dritten ist ohne ausdrückliche Zustimmung von Tim Anthony Alexander nicht gestattet.
 */

namespace src\module\BetterSQL;

use src\module\Factory\Factory;
use src\module\InstantCache\InstantCache;
use src\module\PDO\PDO;
use src\module\QueryBuilder\QueryBuilder;

/**
 * This class is a better ORM than Fasql
 * It handles the cached connection between the QueryBuilder and the Connection class/instances
 */
class BetterSQL
{
    private readonly \PDO $pdo;
    private readonly QueryBuilder $queryBuilder;

    public function __construct(
        private readonly string $table = '',
    ) {
        $this->pdo = PDO::getDriver();
        $this->queryBuilder = self::generateQueryBuilder();
    }

    public function readTable(): array
    {
        $this->queryBuilder->reset()->select(
            $this->table,
        );
        return (array) self::runQuery($this->queryBuilder);
    }

    private static function generateCacheName(array $data): string
    {
        return sprintf('fetched_%s', md5((string) json_encode($data, JSON_THROW_ON_ERROR)));
    }

    private static function getFetched(array $data): mixed
    {
        $cacheName = self::generateCacheName($data);
        if (InstantCache::isset($cacheName)) {
            return InstantCache::get($cacheName);
        }
        return false;
    }

    public function getRowByColumns(
        array $identifiers = [],
        array $startsWith = [],
        array $endsWith = [],
        bool $like = false,
        bool $or = false,
    ): array {
        $hasFetched = self::getFetched(func_get_args());
        if ($hasFetched) {
            return $hasFetched;
        }
        assert(count($identifiers) === count($startsWith) && count($identifiers) === count($endsWith));
        assert($identifiers !== []);
        foreach ($identifiers as $key => $identifier) {
            $startsWithThis = $startsWith[$key];
            $endsWithThis = $endsWith[$key];
            if ($like) {
                if (!$startsWithThis) {
                    $identifiers[$key] = '%' . $identifier;
                }
                if (!$endsWithThis) {
                    $identifiers[$key] = $identifier . '%';
                }
            }
        }
        $this->queryBuilder->reset()->select(
            $this->table,
            true,
            $identifiers
        );
        $return = static::runQuery($this->queryBuilder);
        return InstantCache::set(self::generateCacheName(func_get_args()), $return);
    }

    public function getRowByColumn(
        string $column,
        string $identifier,
        bool $like = false,
        bool $startsWith = false,
        bool $endsWith = false
    ): array {
        $hasFetched = self::getFetched(func_get_args());
        if ($hasFetched) {
            return $hasFetched;
        }
        if ($like) {
            if (!$startsWith) {
                $identifier = '%' . $identifier;
            }
            if (!$endsWith) {
                $identifier .= '%';
            }
        }
        $this->queryBuilder->reset()->select(
            $this->table,
            false,
            ['*'],
        )->where([$column => $identifier], $like);
        $return = self::runQuery($this->queryBuilder);
        return InstantCache::set(self::generateCacheName(func_get_args()), $return);
    }

    public function getRowByUuid(string $uuid): array
    {
        $hasFetched = self::getFetched(func_get_args());
        if ($hasFetched) {
            return $hasFetched;
        }
        $this->queryBuilder->reset()->select(
            $this->table,
        )->where(['uuid' => $uuid]);
        $return = self::runQuery($this->queryBuilder);
        return InstantCache::set(self::generateCacheName(func_get_args()), $return);
    }

    /**
     * @description This function can fetch data or execute a query
     */
    public static function runQuery(
        QueryBuilder $queryBuilder,
        bool $fetchFirst = false,
        bool $fetchFirstFirst = false,
        array $args = []
    ): mixed {
        $self = new self();
        if ($queryBuilder->isSelect()) {
            $fetched = (array) self::fetch($queryBuilder, $self->pdo);
            return $fetchFirstFirst && isset(array_values($fetched)[0])
                ? (array_values(array_values($fetched)[0])[0] ?? [])
                : ($fetchFirst
                    ? (array_values($fetched)[0] ?? [])
                    : $fetched);
        }
        return self::execute($queryBuilder, $self->pdo, $args);
    }

    /**
     * @param \PDO $pdo
     * @throws \Exception
     */
    private static function fetch(
        QueryBuilder $queryBuilder,
        \PDO $pdo,
    ): array {
        $builtStatement = $queryBuilder->build();
        $pdoQuery = $pdo->query($builtStatement);
        assert($pdoQuery instanceof \PDOStatement);
        return $pdoQuery->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param \PDO $pdo
     * @throws \Exception
     */
    private static function execute(
        QueryBuilder $queryBuilder,
        \PDO $pdo,
        array $args = [],
    ): bool {
        $builtStatement = $queryBuilder->build();
        InstantCache::set('sqlexecuction', true);
        try {
            return $pdo->prepare($builtStatement)->execute($args);
        } catch (\PDOException $e) {
            throw new \Exception($e->getMessage() . '-' . $queryBuilder->build(), (int) $e->getCode(), $e);
        }
    }

    public static function generateQueryBuilder(): QueryBuilder
    {
        $queryBuilder = Factory::getClass(QueryBuilder::class);
        assert($queryBuilder instanceof QueryBuilder);
        return $queryBuilder;
    }
}
