<?php

/**
 * This file is part of the margusk/accessors package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/marguskaidja/php-accessors
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\Accessors\Tests;

use margusk\Accessors\Attr\Set;
use margusk\Accessors\Attr\Immutable;
use margusk\Accessors\Exception\BadMethodCallException;
use margusk\Accessors\Exception\InvalidArgumentException;
use margusk\Accessors\Accessible;

class WithTest extends TestCase
{
    public function test_set_method_must_fail(): void
    {
        $obj = new #[Set,Immutable] class {
            use Accessible;

            protected string $p1;
        };

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/is available only for mutable properties/');

        /** @phpstan-ignore-next-line */
        $obj->setP1('this must fail');
    }

    public function test_direct_assignment_must_fail(): void
    {
        /** @noinspection PhpObjectFieldsAreOnlyWrittenInspection */
        $obj = new #[Set,Immutable] class {
            use Accessible;

            protected string $p1;
        };

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/can\'t be set using assignment operator/');

        /** @phpstan-ignore-next-line */
        $obj->p1 = 'this must fail';
    }

    public function test_original_object_must_not_be_modified(): void
    {
        $oldValue = 'old value';
        $obj = new #[Set,Immutable] class($oldValue) {
            use Accessible;

            public function __construct(
                protected string $p1
            ) {
            }

            public function getP1Value(): string
            {
                return $this->p1;
            }
        };

        /** @phpstan-ignore-next-line */
        $obj->withP1('new value');

        $this->assertEquals($oldValue, $obj->getP1Value());
    }

    public function test_cloned_object_must_be_returned_with_modified_value(): void
    {
        $oldValue = 'old value';

        $obj1 = new #[Set,Immutable] class($oldValue) {
            use Accessible;

            public function __construct(
                protected string $p1
            ) {
            }

            public function equals(self $other): bool
            {
                /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection */
                return  $this === $other;
            }

            public function getP1Value(): string
            {
                return $this->p1;
            }
        };

        $newValue = 'new value';

        /** @phpstan-ignore-next-line */
        $obj2 = $obj1->withP1($newValue);

        $this->assertEquals($oldValue, $obj1->getP1Value());
        $this->assertEquals($newValue, $obj2->getP1Value());
        $this->assertNotObjectEquals($obj1, $obj2);
    }

    public function test_updating_multiple_values_should_work(): void
    {
        $obj1 = new #[Set,Immutable] class {
            use Accessible;

            protected string $p0 = 'empty0';
            protected string $p1 = 'empty1';
            protected string $p2 = 'empty2';
            protected string $p3 = 'empty3';

            public function equals(self $other): bool
            {
                /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection */
                return  $this === $other;
            }

            public function getPropertyValue(string $propertyName): string
            {
                return $this->{$propertyName};
            }
        };

        $values = [
            'value0', 'value1', 'value2', 'value3'
        ];

        /** @phpstan-ignore-next-line */
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

    public function test_honour_existing_wither_method(): void
    {
        $obj = new #[Set,Immutable] class {
            const EXPECTED_VALUE = 'existing method called';

            use Accessible;

            protected string $p1;

            public function withP1(string $value): static
            {
                $obj = clone $this;
                $obj->p1 = self::EXPECTED_VALUE;
                return $obj;
            }

            public function getP1value(): string
            {
                return $this->p1;
            }
        };

        /** @phpstan-ignore-next-line */
        $cloned = $obj->with(['p1' => 'this value must not be assigned']);

        $this->assertEquals($cloned::EXPECTED_VALUE, $cloned->getP1value());
    }
}
