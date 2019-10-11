<?php

namespace Akali\Postgrenerator\Support\Contracts;

interface JsonWriter
{
    /**
     * Get current object as an array.
     *
     * @return array
     */
    public function toArray();
}
