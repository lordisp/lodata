<?php

namespace Flat3\Lodata;

use Flat3\Lodata\Controller\Response;
use Flat3\Lodata\Controller\Transaction;
use Flat3\Lodata\Exception\Internal\LexerException;
use Flat3\Lodata\Exception\Internal\PathNotHandledException;
use Flat3\Lodata\Exception\Protocol\BadRequestException;
use Flat3\Lodata\Exception\Protocol\NoContentException;
use Flat3\Lodata\Exception\Protocol\NotFoundException;
use Flat3\Lodata\Expression\Lexer;
use Flat3\Lodata\Helper\Constants;
use Flat3\Lodata\Interfaces\ContextInterface;
use Flat3\Lodata\Interfaces\EmitInterface;
use Flat3\Lodata\Interfaces\PipeInterface;

class PropertyValue implements ContextInterface, PipeInterface, EmitInterface
{
    protected $entity;

    protected $property;

    protected $value;

    /** @var Transaction $transaction */
    protected $transaction;

    public function setProperty(Property $property): self
    {
        $this->property = $property;
        return $this;
    }

    public function getProperty(): Property
    {
        return $this->property;
    }

    public function setEntity(Entity $entity): self
    {
        $this->entity = $entity;
        return $this;
    }

    public function getEntity(): Entity
    {
        return $this->entity;
    }

    public function setValue($value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function __toString()
    {
        return (string) $this->property;
    }

    public function shouldEmit(Transaction $transaction): bool
    {
        if ($this->value instanceof Primitive) {
            $omitNulls = $transaction->getPreferenceValue(Constants::OMIT_VALUES) === Constants::NULLS;

            if ($omitNulls && $this->value->get() === null && $this->property->isNullable()) {
                return false;
            }
        }

        $select = $transaction->getSelect();

        if ($select->isStar() || !$select->hasValue()) {
            return true;
        }

        $selected = $select->getCommaSeparatedValues();

        if ($selected) {
            if (!in_array($this->property->getName(), $selected)) {
                return false;
            }
        }

        return true;
    }

    public function getContextUrl(): string
    {
        return sprintf(
            '%s(%s)/%s',
            $this->entity->getEntitySet()->getContextUrl(),
            $this->entity->getEntityId()->getValue()->toUrl(),
            $this->property
        );
    }

    public static function pipe(
        Transaction $transaction,
        string $currentComponent,
        ?string $nextComponent,
        ?PipeInterface $argument
    ): ?PipeInterface {
        $lexer = new Lexer($currentComponent);

        try {
            $property = $lexer->identifier();
        } catch (LexerException $e) {
            throw new PathNotHandledException();
        }

        if (null === $argument) {
            throw new PathNotHandledException();
        }

        if (!$argument instanceof Entity) {
            throw new BadRequestException('bad_entity', 'PropertyValue must be passed an entity');
        }

        $property = $argument->getType()->getProperty($property);

        if (null === $property) {
            throw new NotFoundException('unknown_property',
                sprintf('The requested property (%s) was not known', $property));
        }

        return $argument->getProperties()->get($property);
    }

    public function emit(): void
    {
        $this->transaction->outputJsonValue($this);
    }

    public function response(): Response
    {
        $transaction = $this->transaction;

        if (null === $this->value->get()) {
            throw new NoContentException('null_value');
        }

        $transaction->configureJsonResponse();

        $metadata = [
            'context' => $this->getContextUrl(),
        ];

        $metadata = $transaction->getMetadata()->filter($metadata);

        return $transaction->getResponse()->setCallback(function () use ($transaction, $metadata) {
            $transaction->outputJsonObjectStart();

            if ($metadata) {
                $transaction->outputJsonKV($metadata);
                $transaction->outputJsonSeparator();
            }

            $transaction->outputJsonKey('value');
            $this->emit();

            $transaction->outputJsonObjectEnd();
        });
    }

    public function setTransaction(Transaction $transaction): self
    {
        $this->transaction = $transaction;
        return $this;
    }
}
