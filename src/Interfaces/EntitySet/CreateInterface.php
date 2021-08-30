<?php

declare(strict_types=1);

namespace Flat3\Lodata\Interfaces\EntitySet;

use Flat3\Lodata\Entity;
use Flat3\Lodata\Helper\ObjectArray;
use Flat3\Lodata\Helper\PropertyValue;

/**
 * Create Interface
 * @package Flat3\Lodata\Interfaces\EntitySet
 */
interface CreateInterface
{
    /**
     * Create an entity
     * @param  ObjectArray|PropertyValue[]  $propertyValues  Property values
     * @return Entity
     */
    public function create(ObjectArray $propertyValues): Entity;
}