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
use margusk\Accessors\Attr\Get;
use margusk\Accessors\Exception\InvalidArgumentException;

class GetTest extends TestCase
{
    /**
     * @return array<array<object>>
     */
    public function dataProviderForAttributeConfiguration(): array
    {
        return [
            [
                new class {
                    use Accessible;

                    #[Get] protected string $foo = "foo";
                }
            ],

            [
                new #[Get] class {
                    use Accessible;

                    protected string $foo = "foo";
                }
            ],

            [
                new #[Get(false)] class {
                    use Accessible;

                    #[Get(true)] protected string $foo = "foo";
                }
            ]
        ];
    }

    /** @dataProvider dataProviderForAttributeConfiguration */
    public function testGetMustReturnCorrectValueWithVariousSimpleConfiguration(object $obj): void
    {
        $expectedValue = 'foo';

        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->foo
        );
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->foo()
        );
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->getFoo()
        );
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->get('foo')
        );
    }

    public function testGetMustFailWithPropertyWhichIsNotMadeAccessibleWithMethodSyntax(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to get misconfigured property|');

        /** @phpstan-ignore-next-line */
        $obj->getBar();
    }

    /**
     * Returns object where:
     *  $public is PUBLIC property
     *  $foo is READABLE and is INITIALIZED
     *  $bar is NOT READABLE and is INITIALIZED
     *  $baz must be left UNDECLARED
     *  $uninitialized is READABLE and is UNINITIALIZED
     *
     * @return object
     */
    protected function defaultTestObject(): object
    {
        return new #[Get] class {
            use Accessible;

            /** @phpstan-ignore-next-line */
            private string $private = 'private';

            public string $public = 'public';

            protected string $foo = 'foo';

            #[Get(false)]
            protected string $bar = 'bar';

            protected string $uninitialized;
        };
    }

    public function testGetMustFailWithPropertyWhichIsNotMadeAccessibleWithDirectSyntax(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to get misconfigured property|');

        /**
         * @noinspection PhpExpressionResultUnusedInspection
         * @phpstan-ignore-next-line
         */
        $obj->bar;
    }

    public function testGetMustFailWithUnknownPropertyWithDirectSyntax(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to get unknown property|');

        /**
         * @noinspection PhpExpressionResultUnusedInspection
         * @phpstan-ignore-next-line
         */
        $obj->baz;
    }

    public function testGetMustFailWithUnknownPropertyWithMethodSyntax(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to get unknown property|');

        /** @phpstan-ignore-next-line */
        $obj->getBaz();
    }

    public function testGetPublicPropertyWithMethodSyntaxMustFail(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|implicit getter is not available for public properties|');

        /** @phpstan-ignore-next-line */
        $obj->getPublic();
    }

    public function testGetPrivatePropertyWithDirectSyntaxMustFail(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to get unknown property|');

        /**
         * @noinspection PhpExpressionResultUnusedInspection
         * @phpstan-ignore-next-line
         */
        $obj->private;
    }

    public function testGetPrivatePropertyWithMethodSyntaxMustFail(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to get unknown property|');

        /** @phpstan-ignore-next-line */
        $obj->getPrivate();
    }

    public function testIssetMustReturnFalseForUninitializedProperty(): void
    {
        $obj = $this->defaultTestObject();

        $this->assertEquals(
            false,
            isset($obj->uninitialized)
        );

        $this->assertEquals(
            false,
            /** @phpstan-ignore-next-line */
            $obj->issetUninitialized()
        );
    }

    public function testIssetMustReturnTrueForInitializedProperty(): void
    {
        $obj = $this->defaultTestObject();

        $this->assertEquals(
            true,
            isset($obj->foo)
        );

        $this->assertEquals(
            true,
            /** @phpstan-ignore-next-line */
            $obj->issetFoo()
        );
    }

    /**
     * @return array<array<object>>
     */
    public function dataProviderForHonouringEndpointMethods(): array
    {
        return [
            [
                new #[Get] class {
                    use Accessible;

                    protected string $foo = 'foo';

                    public function getFoo(): string
                    {
                        return 'foo from endpoint method';
                    }

                    public function issetFoo(): bool
                    {
                        return false;
                    }
                }
            ],
            [
                new #[Get] class {
                    use Accessible;

                    protected string $foo = 'foo';

                    protected function getFoo(): string
                    {
                        return 'foo from endpoint method';
                    }

                    protected function issetFoo(): bool
                    {
                        return false;
                    }
                }
            ]

        ];
    }

    /** @dataProvider dataProviderForHonouringEndpointMethods */
    public function testHonourEndpointMethod(object $obj): void
    {
        // Test getters
        $this->assertEquals(
            'foo from endpoint method',
            /** @phpstan-ignore-next-line */
            $obj->foo
        );

        $this->assertEquals(
            'foo from endpoint method',
            /** @phpstan-ignore-next-line */
            $obj->getFoo()
        );

        $this->assertEquals(
            'foo from endpoint method',
            /** @phpstan-ignore-next-line */
            $obj->get('foo')
        );

        $this->assertEquals(
            'foo from endpoint method',
            /** @phpstan-ignore-next-line */
            $obj->foo()
        );

        // Test issetters
        $this->assertEquals(
            false,
            isset($obj->foo)
        );

        $this->assertEquals(
            false,
            /** @phpstan-ignore-next-line */
            $obj->issetFoo()
        );
    }

    public function testIssetofPublicPropertyMustFail(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|implicit getter is not available for public properties|');

        /** @phpstan-ignore-next-line */
        $obj->issetPublic();
    }
}
