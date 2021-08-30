<?php

declare(strict_types=1);

namespace Flat3\Lodata\Expression\Node\Operator\Comparison;

use Flat3\Lodata\Exception\Internal\NodeHandledException;
use Flat3\Lodata\Expression\Node\Group;
use Flat3\Lodata\Expression\Node\Operator\Comparison;

/**
 * Not
 * @package Flat3\Lodata\Expression\Node\Operator\Comparison
 */
class Not_ extends Comparison
{
    public const symbol = 'not';
    public const unary = true;
    public const precedence = 7;

    public function compute(): void
    {
        try {
            $this->emit(new Group\Start($this->parser));
            $this->emit($this);
            $this->getLeftNode()->compute();
            $this->emit(new Group\End($this->parser));
        } catch (NodeHandledException $e) {
            return;
        }
    }
}
