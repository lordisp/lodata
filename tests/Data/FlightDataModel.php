<?php

namespace Flat3\OData\Tests\Data;

use Exception;
use Flat3\OData\DataModel;
use Flat3\OData\Drivers\Database\Store;
use Flat3\OData\Entity;
use Flat3\OData\EntitySet\Dynamic;
use Flat3\OData\Property;
use Flat3\OData\Resource\EntitySet;
use Flat3\OData\Resource\Operation\Action;
use Flat3\OData\Resource\Operation\Function_;
use Flat3\OData\Tests\Models\Airport as AirportModel;
use Flat3\OData\Tests\Models\Flight as FlightModel;
use Flat3\OData\Type;
use Flat3\OData\Type\String_;

trait FlightDataModel
{
    public function withFlightDataModel(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
        $this->artisan('migrate')->run();

        (new FlightModel([
            'origin' => 'lhr',
            'destination' => 'lax',
        ]))->save();

        (new FlightModel([
            'origin' => 'sam',
            'destination' => 'rgr',
        ]))->save();

        (new AirportModel([
            'code' => 'lhr',
            'name' => 'Heathrow',
            'construction_date' => '1946-03-25',
            'open_time' => '09:00:00',
            'sam_datetime' => '2001-11-10T14:00:00+00:00',
            'is_big' => true,
        ]))->save();

        (new AirportModel([
            'code' => 'lax',
            'name' => 'Los Angeles',
            'construction_date' => '1930-01-01',
            'open_time' => '08:00:00',
            'sam_datetime' => '2000-11-10T14:00:00+00:00',
            'is_big' => false,
        ]))->save();

        (new AirportModel([
            'code' => 'sfo',
            'name' => 'San Francisco',
            'construction_date' => '1930-01-01',
            'open_time' => '15:00:00',
            'sam_datetime' => '2001-11-10T14:00:01+00:00',
            'is_big' => null,
        ]))->save();

        try {
            $flightType = new FlightType();
            $flightType->setKey(new Property\Declared('id', Type::int32()));
            $flightType->addProperty(new Property\Declared('origin', Type::string()));
            $flightType->addProperty(new Property\Declared('destination', Type::string()));
            $flightType->addProperty(new Property\Declared('gate', Type::int32()));
            $flightStore = new Store('flights', $flightType);
            $flightStore->setTable('flights');

            $airportType = new AirportType();
            $airportType->setKey(new Property\Declared('id', Type::int32()));
            $airportType->addProperty(new Property\Declared('name', Type::string()));
            $airportType->addProperty(Property\Declared::factory('code', Type::string())->setSearchable());
            $airportType->addProperty(new Property\Declared('construction_date', Type::date()));
            $airportType->addProperty(new Property\Declared('open_time', Type::timeofday()));
            $airportType->addProperty(new Property\Declared('sam_datetime', Type::datetimeoffset()));
            $airportType->addProperty(new Property\Declared('review_score', Type::decimal()));
            $airportType->addProperty(new Property\Declared('is_big', Type::boolean()));
            $airportStore = new Store('airports', $airportType);
            $airportStore->setTable('airports');

            DataModel::add($flightType);
            DataModel::add($flightStore);

            DataModel::add($airportType);
            DataModel::add($airportStore);

            $nav = new Property\Navigation($airportStore, $airportType);
            $nav->setCollection(true);
            $nav->addConstraint(
                new Property\Constraint(
                    $flightType->getProperty('origin'),
                    $airportType->getProperty('code')
                )
            );
            $nav->addConstraint(
                new Property\Constraint(
                    $flightType->getProperty('destination'),
                    $airportType->getProperty('code')
                )
            );
            $flightType->addProperty($nav);
            $flightStore->addNavigationBinding(new Property\Navigation\Binding($nav, $airportStore));

            $exf1 = new Function_('exf1');
            $exf1->setCallback(function (): String_ {
                return String_::factory('hello');
            });

            $exf2 = Function_::factory('exf2')
                ->setCallback(function (): EntitySet {
                    /** @var DataModel $model */
                    $model = app()->make(DataModel::class);
                    $airports = $model->getResources()->get('airports');
                    $airport = new Airport();
                    $airport->addPrimitive('xyz', $model->getEntityTypes()->get('airport')->getProperty('code'));
                    $set = new Dynamic($airports);
                    $set->addResult($airport);
                    return $set;
                })
                ->setType(new AirportType());

            $exf3 = Function_::factory('exf3')
                ->setCallback(function (String_ $code): Entity {
                    /** @var DataModel $model */
                    $model = app()->make(DataModel::class);
                    $airport = new Airport();
                    $airport->addPrimitive($code->get(), $model->getEntityTypes()->get('airport')->getProperty('code'));
                    return $airport;
                })
                ->setType(new AirportType());

            $exa1 = new Action('exa1');
            $exa1->setCallback(function (): String_ {
                return String_::factory('hello');
            });

            $add = Function_::factory('add', Type::int32())
                ->setCallback(function (Type\Int32 $a, Type\Int32 $b): Type\Int32 {
                    return Type\Int32::factory($a->get() + $b->get());
                });

            DataModel::add($add);
            DataModel::add($exf1);
            DataModel::add($exf2);
            DataModel::add($exf3);
            DataModel::add($exa1);
        } catch (Exception $e) {
        }
    }
}
