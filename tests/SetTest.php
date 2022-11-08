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
use margusk\Accessors\Attr\Mutator;
use margusk\Accessors\Attr\Set;
use margusk\Accessors\Exception\InvalidArgumentException;

use function call_user_func;

class SetTest extends TestCase
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

                    #[Set] protected string $foo = "foo";

                    public function getFooValue(): string
                    {
                        return $this->foo;
                    }
                }
            ],

            [
                new #[Set] class {
                    use Accessible;

                    protected string $foo = "foo";

                    public function getFooValue(): string
                    {
                        return $this->foo;
                    }
                }
            ],

            [
                new #[Set(false)] class {
                    use Accessible;

                    #[Set(true)] protected string $foo = "foo";

                    public function getFooValue(): string
                    {
                        return $this->foo;
                    }
                }
            ]
        ];
    }

    /** @dataProvider dataProviderForAttributeConfiguration */
    public function testSetMustUpdateValueWithVariousSimpleConfiguration(object $obj): void
    {
        $expectedValue = $this->randomString();
        /** @phpstan-ignore-next-line */
        $obj->foo = $expectedValue;
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->getFooValue()
        );

        $expectedValue = $this->randomString();
        /** @phpstan-ignore-next-line */
        $obj->foo($expectedValue);
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->getFooValue()
        );

        $expectedValue = $this->randomString();
        /** @phpstan-ignore-next-line */
        $obj->setFoo($expectedValue);
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->getFooValue()
        );

        $expectedValue = $this->randomString();
        /** @phpstan-ignore-next-line */
        $obj->set('foo', $expectedValue);
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->getFooValue()
        );

        $expectedValue = $this->randomString();
        /** @phpstan-ignore-next-line */
        $obj->set(['foo' => $expectedValue]);
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->getFooValue()
        );
    }

    public function testSetMustUpdateMultiplePropertiesAtOnce(): void
    {
        $obj = new #[Set] class {
            use Accessible;

            protected string $foo0;
            protected string $foo1;
            protected string $foo2;
            protected string $foo3;

            public function getFooValue(string $name): string
            {
                return $this->{$name};
            }
        };

        $setValues = [];
        for ($i = 0; $i <= 3; $i++) {
            $setValues['foo'.$i] = $this->randomString();
        }

        /** @phpstan-ignore-next-line */
        $obj->set($setValues);

        foreach ($setValues as $n => $expectedValue) {
            $this->assertEquals(
                $expectedValue,
                $obj->getFooValue($n)
            );
        }
    }

    public function testSettingUnknownPropertyUsingMultiplePropertySetterMustFail(): void
    {
        $obj = new #[Set] class {
            use Accessible;

            protected string $foo1 = 'foo';
            protected string $foo5 = 'foo';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to set unknown property|');

        /** @phpstan-ignore-next-line */
        $obj->set([
            'foo1' => 'foo1',
            'foo2' => 'foo2'
        ]);
    }

    /**
     * @return mixed[]
     */
    public function dataProviderForMutatorTests(): array
    {
        $classWithStaticMethod = $this->createClass(
            '
            class %name% {
                public static function staticMutator(string $value): string
                {
                    return \md5($value);
                }

                public static function staticMutatorForFoo(string $value): string
                {
                    return \md5($value);
                }
            }
        '
        );

        $objForThisTest = $this->createObjFromClassCode(
            '
            #['.Set::class.','.Mutator::class.'("\$this->mutatorFor%property%")]
            class %name% {
                use '.Accessible::class.';
                protected string $foo;

                public function getFooValue(): string
                {
                    return $this->foo;
                }

                public function mutatorForFoo(string $value): string
                {
                    return \md5($value);
                }
            }
        '
        );

        return [
            [
                new #[Set, Mutator('\md5')] class {
                    use Accessible;

                    protected string $foo;

                    public function getFooValue(): string
                    {
                        return $this->foo;
                    }
                },
                '\md5'
            ],

            [
                $this->createObjFromClassCode(
                    '
                    #['.Set::class.','.Mutator::class.'(["'.$classWithStaticMethod.'","staticMutator"])]
                    class %name% {
                        use '.Accessible::class.';
                        protected string $foo;
                        public function getFooValue(): string
                        {
                            return $this->foo;
                        }
                    }
                '
                ),
                [$classWithStaticMethod, "staticMutator"]
            ],

            [
                $this->createObjFromClassCode(
                    '
                    #['.Set::class.','.Mutator::class.'(["'.$classWithStaticMethod.'","staticMutatorFor%property%"])]
                    class %name% {
                        use '.Accessible::class.';
                        protected string $foo;
                        public function getFooValue(): string
                        {
                            return $this->foo;
                        }
                    }
                '
                ),
                [$classWithStaticMethod, "staticMutatorForFoo"]
            ],

            [
                $objForThisTest,
                [$objForThisTest, "mutatorForFoo"]
            ],

        ];
    }

    /** @dataProvider dataProviderForMutatorTests */
    public function testMutatorFunction(object $obj, callable $mutator): void
    {
        $inputValue = uniqid();

        /** @phpstan-ignore-next-line */
        $obj->foo = $inputValue;

        $expectedValue = call_user_func($mutator, $inputValue);

        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->getFooValue()
        );
    }

    public function testMutatorMustNotBeCalledWhenDisabledUsingOverride(): void
    {
        $obj = new #[Set, Mutator('\md5')] class {
            use Accessible;

            #[Mutator(null)]
            protected string $foo;

            public function getFooValue(): string
            {
                return $this->foo;
            }
        };

        $expectedValue = $this->randomString();

        /**
         * @phpstan-ignore-next-line
         * @noinspection Annotator
         */
        $obj->foo = $expectedValue;

        $this->assertEquals(
            $expectedValue,
            $obj->getFooValue()
        );
    }

    public function testSetMustFailWithPropertyWhichIsNotMadeAccessibleWithMethodSyntax(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to set misconfigured property|');

        /** @phpstan-ignore-next-line */
        $obj->setBar('new bar');
    }

    /**
     * Returns object where:
     *  $public is PUBLIC property
     *  $foo is WRITABLE and is INITIALIZED
     *  $bar is NOT WRITABLE and is INITIALIZED
     *  $baz must be left UNDECLARED
     *  $uninitialized is WRITABLE and is UNINITIALIZED
     *
     * @return object
     */
    protected function defaultTestObject(): object
    {
        return new #[Set] class {
            use Accessible;

            /** @phpstan-ignore-next-line */
            private string $private = 'private';

            public string $public = 'public';

            protected string $foo = 'foo';

            #[Set(false)]
            protected string $bar = 'bar';

            protected string $uninitialized;
        };
    }

    public function testSetMustFailWithPropertyWhichIsNotMadeAccessibleWithDirectSyntax(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to set misconfigured property|');

        /**
         * @phpstan-ignore-next-line
         */
        $obj->bar = 'new bar';
    }

    public function testSetMustFailWithUnknownPropertyWithDirectSyntax(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to set unknown property|');

        /**
         * @phpstan-ignore-next-line
         */
        $obj->baz = 'new baz';
    }

    public function testSetMustFailWithUnknownPropertyWithMethodSyntax(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to set unknown property|');

        /** @phpstan-ignore-next-line */
        $obj->setBaz('new baz');
    }

    public function testSetPublicPropertyWithMethodSyntaxMustFail(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|implicit setter is not available for public properties|');

        /** @phpstan-ignore-next-line */
        $obj->setPublic('new public');
    }

    public function testSetPrivatePropertyWithDirectSyntaxMustFail(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to set unknown property|');

        /** @phpstan-ignore-next-line */
        $obj->private = 'this must fail';
    }

    public function testSetPrivatePropertyWithMethodSyntaxMustFail(): void
    {
        $obj = $this->defaultTestObject();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to set unknown property|');

        /** @phpstan-ignore-next-line */
        $obj->setPrivate('this must fail');
    }

    /**
     * @return array<array<object>>
     */
    public function dataProviderForHonouringEndpointMethods(): array
    {
        return [
            [
                new #[Set] class {
                    use Accessible;

                    protected string $foo = 'foo';

                    public function setFoo(): void
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
                new #[Set] class {
                    use Accessible;

                    protected string $foo = 'foo';

                    protected function setFoo(): void
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
    public function testHonourEndpointMethod(object $obj): void
    {
        $expectedValue = 'foo from endpoint method';

        /** @phpstan-ignore-next-line */
        $obj->foo = $this->randomString();
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->getFooValue()
        );

        /** @phpstan-ignore-next-line */
        $obj->setFoo($this->randomString());
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->getFooValue()
        );

        /** @phpstan-ignore-next-line */
        $obj->foo($this->randomString());
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->getFooValue()
        );

        /** @phpstan-ignore-next-line */
        $obj->set('foo', $this->randomString());
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->getFooValue()
        );

        /** @phpstan-ignore-next-line */
        $obj->set(['foo' => $this->randomString()]);
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->getFooValue()
        );
    }
}
