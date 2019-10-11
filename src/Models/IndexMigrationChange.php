<?php

namespace Akali\Postgrenerator\Models;

use Akali\Postgrenerator\Models\Bases\MigrationChangeBase;
use Akali\Postgrenerator\Support\Contracts\ChangeDetector;
use Akali\Postgrenerator\Support\Contracts\JsonWriter;

class IndexMigrationChange extends MigrationChangeBase implements JsonWriter, ChangeDetector
{
    /**
     * The field to be deleted or added
     *
     * @var Akali\Postgrenerator\Models\Index
     */
    public $index;

    /**
     * Create a new field migration change instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Check whether or not the object has change value
     *
     * @return bool
     */
    public function hasChange()
    {
        foreach ($this as $key => $value) {
            if ($this->isAdded || $this->isDeleted) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get new migration change from the given index
     *
     * @param Akali\Postgrenerator\Models\Index $index
     *
     * @return Akali\Postgrenerator\Models\IndexMigrationChange
     */
    public static function getAdded(Index $index)
    {
        $change = new IndexMigrationChange();
        $change->index = $index;
        $change->isAdded = true;

        return $change;
    }

    /**
     * Get new migration change from the given index
     *
     * @param Akali\Postgrenerator\Models\Index $index
     *
     * @return Akali\Postgrenerator\Models\IndexMigrationChange
     */
    public static function getDeleted(Index $index)
    {
        $change = new IndexMigrationChange();

        $change->isDeleted = true;
        $change->index = $index;

        return $change;
    }
    /**
     * Get the migration change after comparing two given fields
     *
     * @param Akali\Postgrenerator\Models\Field $fieldA
     * @param Akali\Postgrenerator\Models\Field $fieldB
     *
     * @return Akali\Postgrenerator\Models\FieldMigrationChange
     */
    public static function compare(Field $fieldA, Field $fieldB)
    {
        $change = new FieldMigrationChange();
        $change->fromField = $fieldA;
        $change->toField = $fieldB;

        return $change;
    }

    /**
     * Return current object as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'index' => $this->getRawProperty('index'),
            'is-deleted' => $this->isDeleted,
            'is-added' => $this->isAdded,
        ];
    }
}
