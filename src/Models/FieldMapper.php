<?php

namespace Akali\Postgrenerator\Models;

use Akali\Postgrenerator\Models\Field;

class FieldMapper
{
    /**
     * The field to optimize
     *
     * @var array of Akali\Postgrenerator\Models\Field
     */
    public $field;

    /**
     * Create a new optimizer instance.
     *
     * @var array
     */
    public $meta;

    /**
     * Creates a new field instance.
     *
     * @param Akali\Postgrenerator\Models\Field $field
     * @param array $meta
     *
     * @return void
     */
    public function __construct(Field $field, array $meta = [])
    {
        $this->field = $field;
        $this->meta = $meta;
    }
}
