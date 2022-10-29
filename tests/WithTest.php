<?php

/**
 * This file is part of the GetSet package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/marguskaidja/php-getset
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\GetSet\Tests;

use margusk\GetSet\Attributes\Set;
use margusk\GetSet\Attributes\Immutable;
use margusk\GetSet\Exception\BadMethodCallException;
use margusk\GetSet\Exception\InvalidArgumentException;
use margusk\GetSet\GetSetTrait;

class WithTest extends TestCase
{
    public function test_set_method_must_fail()
    {
        $obj = new #[Set,Immutable] class('value') {
            use GetSetTrait;

            public function __construct(
                protected string $p1
            ) {
            }
        };

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/is available only for mutable properties/');

        $obj->setP1('value');
    }

    public function test_direct_assignment_must_fail()
    {
        $obj = new #[Set,Immutable] class('old value') {
            use GetSetTrait;

            public function __construct(
                protected string $p1
            ) {
            }
        };

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/can\'t be set using assignment operator/');

        $obj->p1 = 'new value';
    }

    public function test_original_object_must_not_be_modified()
    {
        $oldValue = 'old value';
        $obj = new #[Set,Immutable] class($oldValue) {
            use GetSetTrait;

            public function __construct(
                protected string $p1
            ) {
            }

            public function getP1Value()
            {
                return $this->p1;
            }
        };

        $obj->withP1('new value');

        $this->assertEquals($oldValue, $obj->getP1Value());
    }

    public function test_cloned_object_must_be_returned_with_modified_value()
    {
        $oldValue = 'old value';

        $obj1 = new #[Set,Immutable] class($oldValue) {
            use GetSetTrait;

            public function __construct(
                protected string $p1
            ) {
            }

            public function equals(self $other): bool
            {
                return  $this === $other;
            }

            public function getP1Value()
            {
                return $this->p1;
            }
        };

        $newValue = 'new value';
        $obj2 = $obj1->withP1($newValue);

        $this->assertEquals($oldValue, $obj1->getP1Value());
        $this->assertEquals($newValue, $obj2->getP1Value());
        $this->assertNotObjectEquals($obj1, $obj2);
    }

    public function test_updating_multiple_values_should_work()
    {
        $obj1 = new #[Set,Immutable] class {
            use GetSetTrait;

            protected string $p0 = 'empty0';
            protected string $p1 = 'empty1';
            protected string $p2 = 'empty2';
            protected string $p3 = 'empty3';

            public function equals(self $other): bool
            {
                return  $this === $other;
            }

            public function getPropertyValue(string $propertyName)
            {
                return $this->{$propertyName};
            }
        };

        $values = [
            'value0', 'value1', 'value2', 'value3'
        ];

        $obj2 = $obj1->with([
            'p0' => $values[0],
            'p1' => $values[1],
            'p2' => $values[2],
            'p3' => $values[3],
        ]);

        for ($c = 0; $c <= 3; $c++) {
            $this->assertEquals('empty'.$c, $obj1->getPropertyValue('p'.$c));
            $this->assertEquals($values[$c], $obj2->getPropertyValue('p'.$c));
        }

        $this->assertNotObjectEquals($obj1, $obj2);
    }

    public function test_honour_existing_wither_method()
    {
        $obj = new #[Set,Immutable] class {
            const EXPECTED_VALUE = 'existing method called';

            use GetSetTrait;

            protected string $p1;

            public function withP1($value)
            {
                $obj = clone $this;
                $obj->p1 = self::EXPECTED_VALUE;
                return $obj;
            }

            public function getP1value()
            {
                return $this->p1;
            }
        };

        $cloned = $obj->with(['p1' => 'this value must not be assigned']);

        $this->assertEquals($cloned::EXPECTED_VALUE, $cloned->getP1value());
    }
}