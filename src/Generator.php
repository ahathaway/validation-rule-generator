<?php
/*
 * Copyright (c) Portland Web Design, Inc 2023.
 */

namespace ahathaway\ValidationRuleGenerator;

use App\Models\BaseModel;
use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

//use Illuminate\Console\DetectsApplicationNamespace;

/**
 * Class Generator
 */
class Generator
{
    // use DetectsApplicationNamespace;

    /**
     * @var RuleCombiner
     */
    protected RuleCombiner $combine;
    /**
     * @var Schema
     */
    protected Schema $schema;
    protected $model_instance;
    /**
     * @var RuleCorrector
     */
    private RuleCorrector $correct;

    /**
     * @param $schemaManager
     */
    public function __construct($schemaManager = null)
    {
        $this->combine = new RuleCombiner;
        $this->correct = new RuleCorrector();
        $this->schema = new Schema($schemaManager);
    }

    /**
     * @return static
     */
    public static function make()
    {
        return new static();
    }

    /**
     * Returns rules for the selected object
     *
     * @param string|object $table The table or model for which to get rules
     * @param string $column The column for which to get rules
     * @param array $rules Manual overrides for the automatically-generated rules
     * @param integer $id An id number to ignore for unique fields (current id)
     *
     * @return array                 Array of calculated rules for the given table/model
     */
    public function getRules($table = null, $column = null, $rules = null, $id = null)
    {
        if (is_null($table) && !is_null($column))
            throw new InvalidArgumentException;

        if (is_object($table))
            return $this->getUniqueRules($this->getModelRules($table, $rules, $column), $id);

        if (is_null($table))
            return $this->getAllTableRules();

        if (is_null($column))
            return $this->getUniqueRules($this->getTableRules($table, $rules), $id);

        return $this->getUniqueRules($this->getColumnRules($table, $column, $rules), $id);
    }

    /**
     * @param $rules
     * @param $id
     * @param $idColumn
     * @return array|mixed|string
     */
    public function getUniqueRules($rules, $id, $idColumn = 'id')
    {
        if (is_null($id)) return $rules;

        if (!is_array($rules)) {
            return $this->getColumnUniqueRules($rules, $id, $idColumn);
        }

        $return = [];
        foreach ($rules as $key => $value) {
            $return[$key] = $this->getColumnUniqueRules($value, $id, $idColumn);
        }
        return $return;
    }

    /**
     * Given a set of rules, and an id for a current record,
     * returns a string with any unique rules skipping the current record.
     *
     * @param string $rules A laravel rule string
     * @param string|integer $id The id to skip
     * @param string $idColumn The name of the column
     *
     * @return string The rules, including a string to skip the given id.
     */
    public function getColumnUniqueRules($rules, $id, $idColumn = 'id')
    {
        $upos = strpos($rules, 'unique:');
        if ($upos === False) {
            return $rules;      // no unique rules for this field; return
        }

        $pos = strpos($rules, '|', $upos);
        if ($pos === False) {       // 'unique' is the last rule; append the id
            return $rules . ',' . $id . ',' . $idColumn;
        }

        // inject the id
        return substr($rules, 0, $pos) . ',' . $id . ',' . $idColumn . substr($rules, $pos);
    }

    /**
     * @param $model
     * @param $rules
     * @param $column
     * @return array|string
     */
    public function getModelRules($model, $rules = [], $column = null): array|string
    {
        $instance = $this->getModelInstance($model);
        // $namespace = $this->getAppNamespace();

        $table = $instance->getTable();
        $_rules = $this->combine->tables($instance::$rules ?? [], $rules);

        return ($column)
            ? $this->getColumnRules($table, $column, $_rules)
            : $this->getTableRules($table, $_rules, $model);
    }

    /**
     * @param $model
     * @return BaseModel
     */
    public function getModelInstance($model): BaseModel
    {
        if (is_object($this->model_instance)) return $this->model_instance;

        // $namespace = $this->getAppNamespace();

        $modelClass = str_replace('/', '\\', $model);

        return $this->model_instance = new $modelClass;
    }

    /**
     * Returns all of the rules for a given table/column
     *
     * @param string $table Name of the table which contains the column
     * @param string $column Name of the column for which to get rules
     * @param string|array $rules Additional information or overrides.
     * @return string           The final calculated rules for this column
     * @throws ReflectionException
     */
    public function getColumnRules($table, $column, $rules = null)
    {
        // TODO: Work with foreign keys for exists:table,column statements

        // Get an array of rules based on column data
        $col = $this->schema->columnData($table, $column);

        $columnRuleArray = $this->getColumnRuleArray($col);
        $indexRuleArray = $this->getIndexRuleArray($table, $column);
        $merged = array_merge($columnRuleArray, $indexRuleArray);

        return $this->combine->columns($merged, $rules);
    }

    /**
     * Returns an array of rules for a given database column, based on field information
     *
     * @param Doctrine\DBAL\Schema\Column $col A database column object (from Doctrine)
     * @return array                                An array of rules for this column
     * @throws ReflectionException
     */
    protected function getColumnRuleArray($col)
    {
        $type = trim((new ReflectionClass($col->getType()))->getShortName(), " \\\t\n\r\0\x0B");

        $className = __NAMESPACE__ . "\Types\\{$type}";

        try {
            if (class_exists($className))
                return (new $className)($col);
        } catch (Types\SkipThisColumn $e) {
            return [];
        }

        // catch (\Exception $e) {
        //     return ['Error: '.$type.' '.$e->getMessage()];
        // }
        // do not return anything for non-implemented classes
        return ['>>>' . $className];
    }

    /**
     * Determine whether a given column should include a 'unique' flag
     *
     * @param string $table
     * @param string $column
     * @return array
     */
    protected function getIndexRuleArray($table, $column)
    {
        // TODO: (maybe) Handle rules for indexes that span multiple columns
        $indexArray = [];
        $indexList = $this->schema->indexes($table);

        foreach ($indexList as $item) {
            $cols = $item->getColumns();
            if (in_array($column, $cols) !== false && count($cols) == 1 && $item->isUnique()) {

                $indexArray['unique'] = $table . ',' . $column;
            }
        }

        return $indexArray;
    }

    /**
     * Returns all rules for a given table
     *
     * @param string|object $table The name of the table to analyze
     * @param array $rules (optional) Additional (user) rules to include
     *                          These will override the automatically generated rules
     * @return array  An associative array of columns and delimited string of rules
     */
    public function getTableRules($table, $rules = null, $model = null): array
    {
        $tableRules = $this->getTableRuleArray($table, $model);
        $tableRules = $this->correct->parseAndCorrect($tableRules);
        return $this->combine->tables($rules, $tableRules);
    }

    /**
     * Returns an array of rules for a given database table
     *
     * @param string $table Name of the table for which to get rules
     * @return array            rules in a nested associative array
     */
    protected function getTableRuleArray($table, $model = null)
    {
        if (!is_string($table))
            throw new InvalidArgumentException;

        $rules = [];
        $columns = $this->schema->columns($table);

        foreach ($columns as $column) {
            $colName = $column->getName();

            // Add generated rules from the database for this column, if any found
            $columnRules = $this->getColumnRuleArray($column);
            if ($columnRules) {
                $rules[$colName] = $columnRules;
            }

            // Add index rules for this column, if any are found
            $indexRules = $this->getIndexRuleArray($table, $colName);

            // Add foreign key rules for this column, if any are found
            $foreignKeyRules = $this->getForeignKeyRuleArray($table, $colName);


            if ($columnRules && $indexRules) {
                $rules[$colName] = array_merge($columnRules, $indexRules);
            }
            if (isset($rules[$colName]) && !empty($foreignKeyRules)) {
                $rules[$colName] = array_merge($rules[$colName], $foreignKeyRules);
            }
        }
        $manyToManyRules = $this->getManyToManyRuleArray($table, $model);
        if (isset($rules) && !empty($manyToManyRules)) {
            $rules = array_merge($rules, $manyToManyRules);
        }
        return $rules;
    }

    /**
     * @param string $table
     * @param string $column
     * @return array
     * @throws Exception
     */
    public function getForeignKeyRuleArray(string $table, string $column): array
    {
        $fkeyList = $this->schema->foreignKeys($table);
        $fkey_rules = [];

        foreach ($fkeyList as $item) {
            $cols = $item->getColumns();
            foreach ($cols as $col) {
                if ($col == $column) {
                    $fkey_rules['exists'] = $item->getForeignTableName() . ',' . $item->getForeignColumns()[0];
                }
            }
        }

        return $fkey_rules;
    }

    /**
     * @param string $table
     * @param string $colName
     * @param string $model
     * @return array
     * @throws Exception
     */
    public function getManyToManyRuleArray(string $table, string $model = null)
    {
        $instance = $this->getModelInstance($model);
        $relation_names = $instance->revealBelongsToManyWith();
        $pivot_rules = [];
        foreach ($relation_names as $relation_name) {
            $relation_table = $instance->$relation_name()
                                       ->getRelated()
                                       ->getTable();
            $pivot_rules[$relation_name] = [
                'nullable' => null,
                'array'    => null
            ];
            $pivot_rules[$relation_name . '.*'] = [
                'numeric' => null,
                'min'     => 1,
                'exists'  => $relation_table . ',id'
            ];
        }
        return $pivot_rules;
    }

    /**
     * Return the DB-specific rules from all tables in the database
     * (this does not contain any user-overrides)
     *
     * @return array   An associative array of columns and delimited string of rules
     */
    public function getAllTableRules()
    {
        $rules = [];

        $tables = $this->schema->tables();
        foreach ($tables as $table) {
            $rules[$table] = $this->getTableRules($table);
        }
        return $rules;
    }


}

