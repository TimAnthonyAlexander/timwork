<?php

namespace src\model\Entity;

use src\model\Data\DataModel;
use src\module\QueryBuilder\QueryBuilder;

class EntityModel extends DataModel
{
    protected string $table;

    public function __construct(
        protected string $id
    ) {
        $this->table = $this->getTableName();
    }

    private function getTableName(): string
    {
        $className = (new \ReflectionClass($this))->getShortName();
        $className = str_replace('Model', '', $className);
        return strtolower($className);
    }

    public function save(): void
    {
        $data = $this->toArray();
        unset($data['id'], $data['table']);

        $this->update($data);
    }

    public function load(string $id): void
    {
        $data = $this->select($id);
        $this->setData($data);
    }

    public function exists(): bool
    {
        $this->createTableIfNotExists();

        $queryBuilder = new QueryBuilder();
        $query = $queryBuilder
            ->reset()
            ->selectCount($this->table)
            ->where(['id' => $this->id])
            ->run(fetchFirst: true, fetchFirstFirst: true);

        return $query > 0;
    }

    private function alterTable(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder
            ->reset()
            ->alterTable($this->table, $this->getColumns())
            ->run();
    }

    private function getColumns(): array
    {
        $reflectionClass = new \ReflectionClass($this);
        $properties = $reflectionClass->getProperties(\ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PUBLIC);

        $columns = [];
        foreach ($properties as $property) {
            $columns[$property->getName()] = $property->getType()->getName();
        }

        return $columns;
    }

    private function createTableIfNotExists(): void
    {
        $queryBuilder = new QueryBuilder();
        try{
            $queryBuilder->reset()->select($this->table)->run();
        } catch (\PDOException){
            $defaultColumn = [
                'name' => 'id',
                'type' => 'int',
                'null' => false,
                'auto_increment' => false,
                'default' => null,
                'primary_key' => false,
            ];

            // Reflectionclass get properties, but remove table
            $properties = (new \ReflectionClass($this))->getProperties();
            $columns = [];
            foreach ($properties as $property) {
                if ($property->getName() === 'table') {
                    continue;
                }
                // Change the type according to the property type
                $type = match ($property->getType()->getName()) {
                    'int' => 'int',
                    'string' => 'varchar(255)',
                    'bool' => 'tinyint(1)',
                    'float' => 'float',
                    default => 'text',
                };

                $column = $defaultColumn;
                $column['type'] = $type;
                $column['name'] = $property->getName();

                if ($property->getName() === 'id') {
                    $column['primary_key'] = true;
                }

                $columns[] = $column;
            }

            $queryBuilder->reset()->createTable($this->table, $columns)->run();
        }
    }

    public function delete(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder
            ->reset()
            ->delete($this->table)
            ->where(['id' => $this->id])
            ->run();
    }

    private function update(array $data): void
    {
        if (!$this->exists()) {
            $this->insert($data);
            return;
        }

        $queryBuilder = new QueryBuilder();
        $queryBuilder
            ->reset()
            ->update($this->table, $data)
            ->where(['id' => $this->id])
            ->run();
    }

    private function insert(array $data): void
    {
        $data['id'] = $this->id;

        $queryBuilder = new QueryBuilder();
        $queryBuilder
            ->reset()
            ->insert($this->table, $data)
            ->run();
    }

    private function select(string $id): array
    {
        $queryBuilder = new QueryBuilder();
        return $queryBuilder
            ->reset()
            ->select($this->table)
            ->where(['id' => $id])
            ->run(fetchFirst: true);
    }

    private function setData(array $data): void
    {
        // Set the properties
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
}
