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
use margusk\Accessors\Attr\Delete;
use margusk\Accessors\Attr\Get;
use margusk\Accessors\Attr\Immutable;
use margusk\Accessors\Exception\InvalidArgumentException;

use function assert;

class UnsetTest extends TestCase
{
    public function testUnsetUsingVariousSyntaxesMustUninitializeProperty(): void
    {
        // Preparations
        $objects = [];

        for ($c = 0; $c < 4; $c++) {
            $objects[$c] = $this->defaultTestObject();

            $this->assertEquals(
                true,
                /** @phpstan-ignore-next-line */
                $objects[$c]->issetFoo()
            );

            $this->assertEquals(
                true,
                isset($objects[$c]->foo)
            );
        }

        // Safety check, all objects must be unique
        assert($objects[0] !== $objects[1]);
        assert($objects[1] !== $objects[2]);
        assert($objects[2] !== $objects[3]);

        // Unset $foo in each of the 4 objects using different syntax
        unset($objects[0]->foo);
        /** @phpstan-ignore-next-line */
        $objects[1]->unsetFoo();
        /** @phpstan-ignore-next-line */
        $objects[2]->unset(['foo']);
        /** @phpstan-ignore-next-line */
        $objects[3]->unset('foo');

        // Verify that all $foo-s were unset
        for ($c = 0; $c < 4; $c++) {
            $this->assertEquals(
                false,
                /** @phpstan-ignore-next-line */
                $objects[$c]->issetFoo()
            );

            $this->assertEquals(
                false,
                isset($objects[$c]->foo)
            );
        }
    }

    /**
     * Returns object where:
     *  $public is PUBLIC property
     *  $foo is DELETABLE and is INITIALIZED
     *  $bar is NOT DELETABLE and is INITIALIZED
     *  $baz must be left UNDECLARED
     *  $uninitialized is DELETABLE and is UNINITIALIZED
     *
     * @return object
     */
    protected function defaultTestObject(): object
    {
        return new #[Get, Delete] class {
            use Accessible;

            /** @phpstan-ignore-next-line */
            private string $private = 'private';

            public string $public = 'public';

            protected string $foo = 'foo';

            #[Delete(false)]
            protected string $bar = 'bar';

            #[Immutable]
            protected string $immutable = 'immutable';

            protected string $uninitialized;
        };
    }

    public function testUnsetMisconfiguredPropertyMustFailWithDirectSyntax(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to unset misconfigured property|');

        unset($obj->bar);
    }

    public function testUnsetMisconfiguredPropertyMustFailWithMethodSyntax(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to unset misconfigured property|');

        /** @phpstan-ignore-next-line */
        $obj->unsetBar();
    }

    /**
     * @return array<array<object>>
     */
    public function dataProviderForHonouringEndpointMethods(): array
    {
        return [
            [
                new #[Delete] class {
                    use Accessible;

                    protected string $foo = 'foo';

                    public function unsetFoo(): void
                    {
                        $this->foo = 'foo from endpoint method';
                    }

                    public function getFooValue(): string
                    {
                        return $this->foo;
                    }
                }
            ],
            [
                new #[Delete] class {
                    use Accessible;

                    protected string $foo = 'foo';

                    protected function unsetFoo(): void
                    {
                        $this->foo = 'foo from endpoint method';
                    }

                    public function getFooValue(): string
                    {
                        return $this->foo;
                    }
                }
            ]

        ];
    }


    /** @dataProvider dataProviderForHonouringEndpointMethods */
    public function testHonourUnsetterEndpointMethod(object $obj): void
    {
        $expectedValue = 'foo from endpoint method';

        $clone = clone $obj;
        unset($clone->foo);
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $clone->getFooValue()
        );

        $clone = clone $obj;
        /** @phpstan-ignore-next-line */
        $clone->unsetFoo();
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $clone->getFooValue()
        );

        $clone = clone $obj;
        /** @phpstan-ignore-next-line */
        $clone->unset('foo');
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $clone->getFooValue()
        );

        $clone = clone $obj;
        /** @phpstan-ignore-next-line */
        $clone->unset(['foo']);
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $clone->getFooValue()
        );
    }

    public function testUnsetImmutablePropertyMustFailWithDirectSyntax(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|immutable property .+ can\'t be unset|');

        unset($obj->immutable);
    }

    public function testUnsetImmutablePropertyMustFailWithMethodSyntax(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|immutable property .+ can\'t be unset|');

        /** @phpstan-ignore-next-line */
        $obj->unsetImmutable();
    }

    public function testUnsetMustUnsetMultipleValuesAtOnce(): void
    {
        $obj = new #[Get, Delete] class {
            use Accessible;

            protected string $foo0 = 'foo';
            protected string $foo1 = 'foo';
            protected string $foo2 = 'foo';
            protected string $foo3 = 'foo';

            public function issetPropertyValue(string $propertyName): bool
            {
                return isset($this->{$propertyName});
            }
        };

        $unsetValues = [];
        for ($c = 0; $c <= 3; $c++) {
            $this->assertEquals(
                true,
                $obj->issetPropertyValue('foo'.$c)
            );
            $unsetValues[] = 'foo'.$c;
        }

        /** @phpstan-ignore-next-line */
        $obj->unset($unsetValues);

        for ($c = 0; $c <= 3; $c++) {
            $this->assertEquals(
                false,
                $obj->issetPropertyValue('foo'.$c)
            );
        }
    }

    public function testUnsetPublicPropertyMustFail(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|implicit unsetter is not available for public properties|');

        /** @phpstan-ignore-next-line */
        $obj->unsetPublic();
    }

    public function testUnsetPrivatePropertyWithLangConstructMustFail(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to unset unknown property|');

        unset($obj->private);
    }

    public function testUnsetPrivatePropertyWithMethodSyntaxMustFail(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to unset unknown property|');

        /** @phpstan-ignore-next-line */
        $obj->unsetPrivate();
    }
}
