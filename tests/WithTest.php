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

use margusk\Accessors\Accessible;
use margusk\Accessors\Attr\Immutable;
use margusk\Accessors\Attr\Set;
use margusk\Accessors\Exception\BadMethodCallException;

class WithTest extends TestCase
{
    public function testUpdateImmutablePropertyWithSetMethodMustFail(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/is available only for mutable properties/');

        /** @phpstan-ignore-next-line */
        $obj->setFoo('this must fail');
    }

    /**
     * Returns object where:
     *  $foo is WRITABLE, INITIALIZED and IMMUTABLE
     *
     * @param  string  $defaultValue
     *
     * @return object
     */
    protected function defaultTestObject(string $defaultValue = 'foo'): object
    {
        return new #[Set, Immutable] class($defaultValue) {
            use Accessible;

            public function __construct(
                protected string $foo
            ) {
            }

            public function equals(self $other): bool
            {
                /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection */
                return $this === $other;
            }

            public function getFooValue(): string
            {
                return $this->foo;
            }
        };
    }

    public function testUpdateImmutablePropertyWithDirectAssignmentMustFail(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/can\'t be set using assignment operator/');

        /** @phpstan-ignore-next-line */
        $obj->foo = 'this must fail';
    }

    public function testUpdateImmutableObjectMustChangeOnlyClonedObject(): void
    {
        $randomString = $this->randomString();
        $expectedOldValue = 'old is '.$randomString;

        $oldObj = $this->defaultTestObject($expectedOldValue);

        $expectedNewValue = 'new is '.$randomString;
        /** @phpstan-ignore-next-line */
        $newObj = $oldObj->withFoo($expectedNewValue);

        // Old object and new object must be different
        $this->assertNotObjectEquals($newObj, $oldObj);

        // Check values
        $this->assertEquals(
            $expectedOldValue,
            /** @phpstan-ignore-next-line */
            $oldObj->getFooValue()
        );

        $this->assertEquals(
            $expectedNewValue,
            $newObj->getFooValue()
        );
    }

    public function test_updating_multiple_values_should_work(): void
    {
        $oldObj = new #[Set, Immutable] class {
            use Accessible;

            protected string $foo0 = 'foo0';
            protected string $foo1 = 'foo1';
            protected string $foo2 = 'foo2';
            protected string $foo3 = 'foo3';

            public function equals(self $other): bool
            {
                /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection */
                return $this === $other;
            }

            public function getPropertyValue(string $propertyName): string
            {
                return $this->{$propertyName};
            }
        };

        $oldValues = [];
        $newValues = [];
        for ($i = 0; $i <= 3; $i++) {
            $n = 'foo'.$i;
            $newValues[$n] = $this->randomString();
            $oldValues[$n] = $oldObj->getPropertyValue($n);
        }

        /** @phpstan-ignore-next-line */
        $newObj = $oldObj->with($newValues);

        // Old object and new object must be different
        $this->assertNotObjectEquals($newObj, $oldObj);

        foreach ($newValues as $n => $expectedNewValue) {
            $this->assertEquals(
                $oldValues[$n],
                $oldObj->getPropertyValue($n)
            );

            $this->assertEquals(
                $expectedNewValue,
                $newObj->getPropertyValue($n)
            );
        }
    }

    public function testHonourEndpointMethod(): void
    {
        $obj = new #[Set, Immutable] class {
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
