<?php
namespace Akali\Postgrenerator\DatabaseParsers;

use App;
use Akali\Postgrenerator\DatabaseParsers\ParserBase;
use Akali\Postgrenerator\Models\Field;
use Akali\Postgrenerator\Models\ForeignConstraint;
use Akali\Postgrenerator\Models\ForeignRelationship;
use Akali\Postgrenerator\Models\Index;
use Akali\Postgrenerator\Support\Config;
use Akali\Postgrenerator\Support\FieldTransformer;
use Akali\Postgrenerator\Support\Str;
use Akali\Postgrenerator\Traits\LanguageTrait;
use Akali\Postgrenerator\Traits\ModelTrait;
use DB;
use Exception;

class PgsqlParser extends ParserBase
{
    use ModelTrait, LanguageTrait;

    /**
     * List of the foreign constraints.
     *
     * @var array
     */
    protected $constrains;

    /**
     * List of the foreign relations.
     *
     * @var array
     */
    protected $relations;

    /**
     * List of the data types that hold large data.
     * This will be used to eliminate the column from the index view
     *
     * @var array
     */
    protected $largeDataTypes = ['varbinary', 'blob', 'mediumblob', 'longblob', 'text', 'mediumtext', 'longtext'];

    /**
     * Gets columns meta info from the information schema.
     *
     * @return array
     */
    protected function getColumns()
    {
        return DB::select(
            "SELECT COLUMN_NAME
              ,COLUMN_DEFAULT
              ,UPPER(IS_NULLABLE)  AS IS_NULLABLE
              ,LOWER(DATA_TYPE) AS DATA_TYPE
              ,CHARACTER_MAXIMUM_LENGTH
			  ,NUMERIC_PRECISION
              FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?",
            //[$this->tableName, $this->databaseName]
            [$this->tableName, 'public']
        );
    }

    /**
     * Gets foreign key constraint info for a given column name.
     *
     * @return mix (null|object)
     */
    protected function getConstraint($foreign)
    {
        return [];
        foreach ($this->getConstraints() as $constraint) {
            if ($constraint->foreign == $foreign) {
                return (object) $constraint;
            }
        }

        //return null;
    }

    /**
     * Gets foreign key constraints info from the information schema.
     *
     * @return array
     */
    protected function getConstraints()
    {
        if (is_null($this->constrains)) {
            $this->constrains = DB::select(
                'SELECT COLUMN_NAME
              ,COLUMN_DEFAULT
              ,UPPER(IS_NULLABLE)  AS IS_NULLABLE
              ,LOWER(DATA_TYPE) AS DATA_TYPE
              ,CHARACTER_MAXIMUM_LENGTH
			  ,NUMERIC_PRECISION
              FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?' ,
                /*'SELECT
                    r.referenced_table_name AS `references`
                   ,r.CONSTRAINT_NAME AS `name`
                   ,r.UPDATE_RULE AS `onUpdate`
                   ,r.DELETE_RULE AS `onDelete`
                   ,u.referenced_column_name AS `on`
                   ,u.column_name AS `foreign`
                   ,CASE WHEN u.TABLE_NAME = r.referenced_table_name THEN 1 ELSE 0 END selfReferences
                   FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS AS r
                   INNER JOIN information_schema.key_column_usage AS u ON u.CONSTRAINT_NAME = r.CONSTRAINT_NAME
                                                                       AND u.table_schema = r.constraint_schema
                                                                       AND u.table_name = r.table_name
                   WHERE u.table_name = ? AND u.constraint_schema = ?;',*/
                [$this->tableName, $this->databaseName = 'public']
            );
        }

        return $this->constrains;
    }

    protected function getRawIndexes()
    {
        return $result = [];
        $result = DB::select(
            'SELECT
              INDEX_NAME AS name
             ,COUNT(1) AS TotalColumns
             ,GROUP_CONCAT(DISTINCT COLUMN_NAME ORDER BY SEQ_IN_INDEX ASC SEPARATOR \'|||\') AS columns
             FROM INFORMATION_SCHEMA.STATISTICS AS s
             WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?
             GROUP BY INDEX_NAME
             HAVING COUNT(1) > 1;',
            [$this->tableName, $this->databaseName = 'public']
        );

        //return $result;
    }

    /**
     * Get all available relations
     *
     * @return array of Akali\Postgrenerator\Models\ForeignRelationship;
     */
    protected function getRelations()
    {
        $relations = [];
        $rawRelations = $this->getRawRelations();
        if (!is_null($rawRelations)) {
            foreach ($rawRelations as $rawRelation) {
                $relations[] = $this->getRealtion($rawRelation->foreignTable, $rawRelation->foreignKey, $rawRelation->localKey, $rawRelation->selfReferences);
            }
        }

        return $relations;
    }

    /**
     * Gets the raw relations from the database.
     *
     * @return array
     */
    protected function getRawRelations()
    {
        /*if (is_null($this->relations)) {
            $this->relations = DB::select(
                'SELECT DISTINCT
                 u.referenced_column_name AS `localKey`
                ,u.column_name AS `foreignKey`
                ,r.table_name AS `foreignTable`
                ,CASE WHEN u.TABLE_NAME = r.referenced_table_name THEN 1 ELSE 0 END selfReferences
                FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS AS r
                INNER JOIN information_schema.key_column_usage AS u ON u.CONSTRAINT_NAME = r.CONSTRAINT_NAME
                                                                   AND u.table_schema = r.constraint_schema
                                                                   AND u.table_name = r.table_name
                WHERE u.referenced_table_name = ? AND u.constraint_schema = ?;',
                [$this->tableName, $this->databaseName]
            );
        }*/

        return $this->relations = null;
    }

    /**
     * Gets a query to check the relation type.
     *
     * @return string
     */
    protected function getRelationTypeQuery($tableName, $columnName)
    {
        return ' SELECT `' . $columnName . '` AS id, COUNT(1) AS total ' .
            ' FROM `' . $tableName . '` ' .
            ' GROUP BY `' . $columnName . '` ' .
            ' HAVING COUNT(1) > 1 ' .
            ' LIMIT 1 ';
    }

    /**
     * Get a corresponding relation to a given table name, foreign column and local column.
     *
     * @return Akali\Postgrenerator\Models\ForeignRelationship
     */
    protected function getRealtion($foreignTableName, $foreignColumn, $localColumn, $selfReferences)
    {
        $modelName = $this->getModelName($foreignTableName);
        $model = self::guessModelFullName($modelName, self::getModelsPath());

        $params = [
            $model,
            $foreignColumn,
            $localColumn,
        ];

        $relationName = ($selfReferences ? 'child_' : '');

        if ($this->isOneToMany($foreignTableName, $foreignColumn)) {
            return new ForeignRelationship('hasMany', $params, camel_case($relationName . Str::plural($foreignTableName)));
        }

        return new ForeignRelationship('hasOne', $params, camel_case($relationName . Str::singular($foreignTableName)));
    }

    /**
     * Checks of the table has one-to-many relations
     *
     * @return bool
     */
    protected function isOneToMany($tableName, $columnName)
    {
        $query = $this->getRelationTypeQuery($tableName, $columnName);
        $result = DB::select($query);

        return isset($result[0]);
    }

    /**
     * Get all available indexed
     *
     * @return array of Akali\Postgrenerator\Models\Index;
     */
    protected function getIndexes()
    {
        return $indexes = [];
        $indexes = [];
        $rawIndexes = $this->getRawIndexes();

        foreach ($rawIndexes as $rawIndex) {
            $index = new Index($rawIndex->name);
            $index->addColumns(explode('|||', $rawIndex->columns));

            $indexes[] = $index;
        }

        return $indexes;
    }

    /**
     * Gets the field after transfering it from a given query object.
     *
     * @param object $column
     *
     * @return Akali\Postgrenerator\Model\Field;
     */
    protected function getTransfredFields(array $columns)
    {
        $collection = [];

        foreach ($columns as $column) {
            // While constructing the array for each field
            // there is no need to set translations for options
            // or even labels. This step is handled using the FieldTransformer
            
/*'column_name' => 'created_at',
'column_default' => NULL,
'is_nullable' => 'YES',
'data_type' => 'timestamp without time zone',
'character_maximum_length' => NULL,
'numeric_precision' => NULL,*/

            $properties['name'] = $column->column_name;
            $properties['is-nullable'] = ($column->is_nullable == 'YES');
            $properties['data-value'] = $column->column_default;
            $properties['data-type'] = $column->data_type;
            $properties['data-type-params'] = "";
            $properties['is-primary'] = false;
            $properties['is-index'] = false;
            $properties['is-unique'] = false;
            $properties['is-auto-increment'] = false;
            $properties['comment'] = null;
            $properties['options'] = $column->data_type;
            $properties['is-unsigned'] = false;

            $constraint = $this->getForeignConstraint($column->column_name);

            $properties['foreign-constraint'] = !is_null($constraint) ? $constraint->toArray() : null;

            if (intval($column->character_maximum_length) > 255
                || in_array($column->data_type, $this->largeDataTypes)) {
                $properties['is-on-index'] = false;
            }

            $collection[] = $properties;
        }

        $localeGroup = self::makeLocaleGroup($this->tableName);

        $fields = FieldTransformer::fromArray($collection, $localeGroup, $this->languages);

        // At this point we constructed the fields collection with the default html-type
        // We need to set the html-type using the config::getEloquentToHtmlMap() setting
        $this->setHtmlType($fields);

        return $fields;
    }

    /**
     * Gets the type params
     *
     * @param string $length
     * @param string $dataType
     * @param string $columnType
     *
     * @return $this
     */
    protected function getPrecision($length, $dataType, $columnType)
    {
        if (in_array($dataType, ['decimal', 'double', 'float', 'real'])) {
            $match = [];

            preg_match('#\((.*?)\)#', $columnType, $match);

            if (!isset($match[1])) {
                return null;
            }

            return explode(',', $match[1]);
        }

        if (intval($length) > 0) {
            return [$length];
        }

        return [];
    }

    /**
     * Gets the data type for a given field.
     *
     * @param string $type
     * @param string $columnType
     *
     * @return $this
     */
    protected function getDataType($type, $columnType)
    {
        $map = Config::dataTypeMap();

        if (in_array($columnType, ['bit', 'tinyint(1)'])) {
            return 'boolean';
        }

        if (!array_key_exists($type, $map)) {
            throw new Exception("The type " . $type . " is not mapped in the 'eloquent_type_to_method' key in the config file.");
        }

        return $map[$type];
    }

    /**
     * Gets the foreign constrain for the given field.
     *
     * @param string $name
     *
     * @return null || Akali\Postgrenerator\Models\ForeignConstraint
     */
    protected function getForeignConstraint($name)
    {
        $raw = $this->getConstraint($name);

        if (is_null($raw)) {
            return null;
        }
        return null;
        return new ForeignConstraint(
            $raw->foreign,
            $raw->references,
            $raw->on,
            strtolower($raw->onDelete),
            strtolower($raw->onUpdate),
            null,
            $raw->selfReferences
        );
    }

    /**
     * Set the options for a given field.
     *
     * @param Akali\Postgrenerator\Models\Field $field
     * @param string $type
     *
     * @return array
     */
    protected function getHtmlOptions($dataType, $columnType)
    {
        if (($options = $this->getEnumOptions($columnType)) !== null) {
            return $options;
        }

        return [];
    }

    /**
     * Parses out the options from a given type
     *
     * @param string $type
     *
     * @return mix (null|array)
     */
    protected function getEnumOptions($type)
    {
        $match = [];

        preg_match('#enum\((.*?)\)#', $type, $match);

        if (!isset($match[1])) {
            return null;
        }

        $options = array_map(function ($option) {
            return trim($option, "'");
        }, explode(',', $match[1]));

        $finals = [];

        foreach ($options as $option) {
            $finals[$option] = $option;
        }

        return $finals;
    }
}
