<?php

declare(strict_types=1);

namespace Flat3\Lodata;

use Flat3\Lodata\Controller\Response;
use Flat3\Lodata\Controller\Transaction;
use Flat3\Lodata\Exception\Internal\PathNotHandledException;
use Flat3\Lodata\Exception\Protocol\BadRequestException;
use Flat3\Lodata\Exception\Protocol\InternalServerErrorException;
use Flat3\Lodata\Exception\Protocol\MethodNotAllowedException;
use Flat3\Lodata\Exception\Protocol\NoContentException;
use Flat3\Lodata\Exception\Protocol\NotImplementedException;
use Flat3\Lodata\Helper\Constants;
use Flat3\Lodata\Helper\Gate;
use Flat3\Lodata\Helper\PropertyValue;
use Flat3\Lodata\Interfaces\ContextInterface;
use Flat3\Lodata\Interfaces\EntitySet\DeleteInterface;
use Flat3\Lodata\Interfaces\EntitySet\UpdateInterface;
use Flat3\Lodata\Interfaces\PipeInterface;
use Flat3\Lodata\Interfaces\ResourceInterface;
use Flat3\Lodata\Interfaces\ResponseInterface;
use Flat3\Lodata\Transaction\MetadataContainer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Entity
 * @link https://docs.oasis-open.org/odata/odata/v4.01/os/part1-protocol/odata-v4.01-os-part1-protocol.html#_Toc31358838
 * @package Flat3\Lodata
 */
class Entity extends ComplexValue implements ResourceInterface, ResponseInterface, ContextInterface, PipeInterface
{
    /**
     * The Entity ID
     * @var PropertyValue $id
     */
    private $id;

    /**
     * The type of this complex value
     * @var EntityType $type
     */
    protected $type;

    /**
     * The parent resource this resource belongs to
     * @var EntitySet $entitySet
     */
    protected $entitySet;

    /**
     * Set the entity set that contains this entity
     * @param  EntitySet  $entitySet  Entity set
     * @return $this
     */
    public function setEntitySet(EntitySet $entitySet): self
    {
        $this->entitySet = $entitySet;
        $this->type = $entitySet->getType();
        return $this;
    }

    /**
     * Get the ID of this entity
     * @return PropertyValue|null PropertyValue representing the entity ID
     */
    public function getEntityId(): ?PropertyValue
    {
        return $this->id;
    }

    /**
     * Set the ID of this entity
     * @param  mixed  $id  ID
     * @return $this
     */
    public function setEntityId($id): self
    {
        if ($id instanceof PropertyValue) {
            $this->id = $id;
            $this->propertyValues[] = $id;
            return $this;
        }

        $key = $this->getType()->getKey();

        if (!$key) {
            return $this;
        }

        $type = $key->getType();

        $propertyValue = $this->newPropertyValue();
        $propertyValue->setProperty($key);
        $propertyValue->setValue($id instanceof Primitive ? $id : $type->instance($id));
        $this->id = $propertyValue;
        $this->propertyValues[] = $propertyValue;

        return $this;
    }

    /**
     * Get the entity set that contains this entity
     * @return EntitySet|null
     */
    public function getEntitySet(): ?EntitySet
    {
        return $this->entitySet;
    }

    /**
     * Create a new property value on this entity
     * @param  mixed  $offset  Property name
     * @param  mixed  $value  Property value
     */
    public function offsetSet($offset, $value)
    {
        parent::offsetSet($offset, $value);

        $keyProperty = $this->getType()->getKey();
        if ($keyProperty && $offset === $keyProperty->getName()) {
            $this->setEntityId($this[$offset]);
        }
    }

    public static function pipe(
        Transaction $transaction,
        string $currentSegment,
        ?string $nextSegment,
        ?PipeInterface $argument
    ): ?PipeInterface {
        if ($currentSegment !== '$entity') {
            throw new PathNotHandledException();
        }

        if ($argument) {
            throw new BadRequestException('entity_argument', 'Entity cannot have a path argument');
        }

        $id = $transaction->getIdOption();

        if (!$id->hasValue()) {
            throw new BadRequestException('missing_id', 'The entity id system query option must be provided');
        }

        $entityId = $id->getValue();
        if (Str::startsWith($entityId, ServiceProvider::endpoint())) {
            $entityId = Str::substr($entityId, strlen(ServiceProvider::endpoint()));
        }

        return EntitySet::pipe($transaction, $entityId);
    }

    /**
     * Get the context URL for this entity
     * @param  Transaction  $transaction  Related transaction
     * @return string Context URL
     */
    public function getContextUrl(Transaction $transaction): string
    {
        if ($this->usesReferences()) {
            return $transaction->getContextUrl().'#$ref';
        }

        if ($this->entitySet) {
            $url = $this->entitySet->getContextUrl($transaction);

            return $url.'/$entity';
        }

        $url = $this->type->getContextUrl($transaction);

        $properties = $transaction->getProjectedProperties();

        if ($properties) {
            $url .= sprintf('(%s)', join(',', $properties));
        }

        return $url;
    }

    /**
     * Get the resource URL for this entity
     * @param  Transaction  $transaction  Related transaction
     * @return string Resource URL
     */
    public function getResourceUrl(Transaction $transaction): string
    {
        if (!$this->entitySet) {
            throw new InternalServerErrorException(
                'no_entity_set',
                'Entity is only a resource as part of an entity set'
            );
        }

        if (!$this->getEntityId()) {
            throw new InternalServerErrorException(
                'no_entity_id',
                'Entity is only a resource if it has an ID',
            );
        }

        return sprintf(
            '%s(%s)',
            $this->entitySet->getResourceUrl($transaction),
            $this->getEntityId()->getPrimitive()->toUrl()
        );
    }

    /**
     * Delete this entity
     * @param  Transaction  $transaction  Related transaction
     * @param  ContextInterface|null  $context  Current context
     * @return Response Client response
     */
    public function delete(Transaction $transaction, ?ContextInterface $context = null): Response
    {
        $entitySet = $this->entitySet;

        if (!$entitySet instanceof DeleteInterface) {
            throw new NotImplementedException('entityset_cannot_delete', 'This entity set cannot delete');
        }

        Gate::check(Gate::delete, $this, $transaction);
        $transaction->assertIfMatchHeader($this->getETag());

        $entitySet->delete($this->getEntityId());

        throw new NoContentException('deleted', 'Content was deleted');
    }

    /**
     * Update this entity
     * @param  Transaction  $transaction  Related transaction
     * @param  ContextInterface|null  $context  Current context
     * @return Response Client response
     */
    public function patch(Transaction $transaction, ?ContextInterface $context = null): Response
    {
        $entitySet = $this->entitySet;

        if (!$entitySet instanceof UpdateInterface) {
            throw new NotImplementedException('entityset_cannot_update', 'This entity set cannot update');
        }

        Gate::check(Gate::update, $this, $transaction);

        $transaction->assertContentTypeJson();
        $transaction->assertIfMatchHeader($this->getETag());

        $entity = $entitySet->update($this->getEntityId(), $entitySet->arrayToPropertyValues($transaction->getBody()));

        $transaction->processDeltaPayloads($entity);

        if (
            $transaction->getPreferenceValue(Constants::return) === Constants::minimal &&
            !$transaction->getSelect()->hasValue() &&
            !$transaction->getExpand()->hasValue()
        ) {
            throw NoContentException::factory()
                ->header(Constants::preferenceApplied, Constants::return.'='.Constants::minimal)
                ->header(Constants::odataEntityId, $entity->getResourceUrl($transaction));
        }

        return $entity->get($transaction, $context);
    }

    /**
     * Read this entity
     * @param  Transaction  $transaction  Related transaction
     * @param  ContextInterface|null  $context  Current context
     * @return Response Client response
     */
    public function get(Transaction $transaction, ?ContextInterface $context = null): Response
    {
        Gate::check(Gate::read, $this, $transaction);

        $context = $context ?: $this;

        $this->metadata = $transaction->createMetadataContainer();
        $this->metadata['context'] = $context->getContextUrl($transaction);

        $response = $transaction->getResponse();
        $transaction->assertIfMatchHeader($this->getETag());
        $transaction->setETagHeader($this->getETag());

        return $response->setResourceCallback($this, function () use ($transaction) {
            $this->emitJson($transaction);
        });
    }

    public function response(Transaction $transaction, ?ContextInterface $context = null): Response
    {
        if ($this->transaction) {
            $transaction = $this->transaction->replaceQueryParams($transaction);
        }

        switch ($transaction->getMethod()) {
            case Request::METHOD_PATCH:
            case Request::METHOD_PUT:
                return $this->patch($transaction, $context);

            case Request::METHOD_DELETE:
                return $this->delete($transaction, $context);

            case Request::METHOD_GET:
                return $this->get($transaction, $context);
        }

        throw new MethodNotAllowedException();
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->getEntityId()->getPrimitiveValue();
    }

    /**
     * @param  Transaction  $transaction
     * @return MetadataContainer
     */
    protected function getMetadata(Transaction $transaction): MetadataContainer
    {
        $metadata = parent::getMetadata($transaction);

        if (!$this->entitySet || !$this->getEntityId()) {
            return $metadata;
        }

        $metadata['id'] = $this->getResourceUrl($transaction);

        if ($this->usesReferences()) {
            $metadata['id'] = sprintf(
                '%s(%s)',
                $this->entitySet->getName(),
                $this->getEntityId()->getPrimitiveValue()
            );

            $metadata->addRequiredProperty('id');
        } else {
            $metadata['readLink'] = $this->getResourceUrl($transaction);
        }

        return $metadata;
    }
}
