<?php

declare(strict_types=1);

namespace Flat3\Lodata\Tests\Unit\Protocol;

use Flat3\Lodata\EntitySet;
use Flat3\Lodata\Facades\Lodata;
use Flat3\Lodata\Interfaces\Operation\FunctionInterface;
use Flat3\Lodata\Operation;
use Flat3\Lodata\Tests\Request;
use Flat3\Lodata\Tests\TestCase;
use Flat3\Lodata\Transaction\MetadataType;

class ContextResourceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withFlightModel();
        $this->withTextModel();
        $this->withSingleton();
    }

    public function test_service_document()
    {
        $this->assertJsonResponse(
            Request::factory()
                ->metadata(MetadataType\Full::name)
                ->path('/')
        );
    }

    public function test_collection_of_entities()
    {
        $this->assertJsonResponse(
            Request::factory()
                ->metadata(MetadataType\Full::name)
                ->path('/flights')
        );
    }

    public function test_entity()
    {
        $this->assertJsonResponse(
            Request::factory()
                ->metadata(MetadataType\Full::name)
                ->path('/flights/1')
        );
    }

    public function test_singleton()
    {
        $this->assertJsonResponse(
            Request::factory()
                ->metadata(MetadataType\Full::name)
                ->path('/sInstance')
        );
    }

    public function test_collection_of_projected_entities()
    {
        $this->assertJsonResponse(
            Request::factory()
                ->metadata(MetadataType\Full::name)
                ->select('name,code')
                ->path('/airports')
        );
    }

    public function test_projected_entity()
    {
        $this->assertJsonResponse(
            Request::factory()
                ->metadata(MetadataType\Full::name)
                ->select('name,code')
                ->path('/airports/1')
        );
    }

    public function test_collection_of_expanded_entities()
    {
        $this->assertJsonResponse(
            Request::factory()
                ->metadata(MetadataType\Full::name)
                ->select('id,gate')
                ->query('expand', 'airports(select=code)')
                ->path('/flights')
        );
    }

    public function test_expanded_entity()
    {
        $this->assertJsonResponse(
            Request::factory()
                ->metadata(MetadataType\Full::name)
                ->select('id,gate')
                ->query('expand', 'airports(select=code)')
                ->path('/flights/1')
        );
    }

    public function test_collection_of_entity_references()
    {
        $this->assertJsonResponse(
            Request::factory()
                ->metadata(MetadataType\Full::name)
                ->path('/flights/1/airports/$ref')
        );
    }

    public function test_entity_reference()
    {
        $this->assertJsonResponse(
            Request::factory()
                ->metadata(MetadataType\Full::name)
                ->path('/flights/1/$ref')
        );
    }

    public function test_property_value()
    {
        $this->assertJsonResponse(
            Request::factory()
                ->metadata(MetadataType\Full::name)
                ->path('/airports/1/code')
        );
    }

    public function test_operation_result()
    {
        Lodata::add((new class('textf1') extends Operation implements FunctionInterface {
            public function invoke(EntitySet $texts): EntitySet
            {
                return $texts;
            }
        })->setReturnType(Lodata::getEntityType('text')));

        $this->assertJsonResponse(
            Request::factory()
                ->metadata(MetadataType\Full::name)
                ->path('/textf1()')
        );
    }

    public function test_all()
    {
        $this->assertJsonResponse(
            Request::factory()
                ->metadata(MetadataType\Full::name)
                ->path('/$all')
        );
    }
}