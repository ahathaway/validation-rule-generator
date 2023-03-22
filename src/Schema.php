<?php
/*
 * Copyright (c) Portland Web Design, Inc 2023.
 */

namespace ahathaway\ValidationRuleGenerator;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Illuminate\Support\Facades\DB;

/**
 * Class Schema
 */
class Schema
{
    /**
     * @var AbstractSchemaManager|mixed
     */
    protected mixed $schemaManager;
    /**
     * @var array
     */
    protected array $indexes;
    /**
     * @var array
     */
    protected array $foreign_keys;

    /**
     * @param $schemaManager
     */
    public function __construct($schemaManager = null)
    {
        $this->schemaManager = $schemaManager ?:
            DB::connection()
              ->getDoctrineSchemaManager();
    }

    /**
     * @return array|string[]
     * @throws Exception
     */
    public function tables(): array
    {
        return $this->schemaManager->listTableNames();
    }

    /**
     * @param $table
     * @return array|Column[]
     * @throws Exception
     */
    public function columns($table): array
    {
        return $this->schemaManager->listTableColumns($table);
    }

    /**
     * @param $table
     * @param $column
     * @return Column
     */
    public function columnData($table, $column): Column
    {
        return DB::connection()
                 ->getDoctrineColumn($table, $column);
    }

    /**
     * @param $table
     * @return array|Index[]|mixed
     * @throws Exception
     */
    public function indexes($table): mixed
    {
        if (isset($this->indexes[$table]))
            return $this->indexes[$table];

        return $this->indexes[$table] = $this->schemaManager->listTableIndexes($table);
    }

    /**
     * @param $table
     * @return mixed
     * @throws Exception
     */
    public function foreignKeys($table): mixed
    {
        if (isset($this->foreign_keys[$table]))
            return $this->foreign_keys[$table];

        return $this->foreign_keys[$table] = $this->schemaManager->listTableForeignKeys($table);
    }
}
