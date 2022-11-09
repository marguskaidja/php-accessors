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

use Exception;
use margusk\Accessors\Accessible;
use margusk\Accessors\Accessible\WithPHPDocs as AccessibleWithPHPDocs;
use margusk\Accessors\Attr\Get;
use margusk\Accessors\Attr\Mutator;
use margusk\Accessors\Attr\Set;
use margusk\Accessors\Exception\InvalidArgumentException;

class InheritanceTest extends TestCase
{
    /**
     * The '$foo' property in class $parentName MUST not be accessible and should throw error when accessed,
     * because parent class does not use Accessible trait
     *
     * @return void
     */
    public function testPropertiesBeforeAccessibleTraitMustNotBeInherited(): void
    {
        $parentName = $this->createClass(
            '
            class %name% {
                protected string $foo;
            }
        '
        );

        $child = $this->createObjFromClassCode(
            '
            #['.Set::class.']
            class %name% extends '.$parentName.' {
                use '.Accessible::class.';
            }
        '
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tried to set unknown property/');

        /** @phpstan-ignore-next-line */
        $child->foo = 'new foo';
    }

    /**
     * The '$foo' property in class $parentName MUST not be set to writable through child class and should throw
     * error when tried to written to, because child class Attributes can not change parent properties.
     *
     * Though, reading must still work, because that's how it's configured in parent class.
     *
     * @return void
     */
    public function testChildAttributesMustNotChangeBehaviourOfParentClassProperties(): void
    {
        $parentName = $this->createClass(
            '
            class %name% {
                use '.Accessible::class.';
                #['.Get::class.','.Set::class.'(false)]
                protected string $foo = "foo";
            }
        '
        );

        $child = $this->createObjFromClassCode(
            '
            /**
             * @property string $foo
             */
            #['.Get::class.'(false),'.Set::class.'(true)]
            class %name% extends '.$parentName.' {
            }
        '
        );

        $this->assertEquals(
            'foo',
            /** @phpstan-ignore-next-line */
            $child->foo
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tried to set misconfigured property/');

        /** @phpstan-ignore-next-line */
        $child->foo = 'new foo';
    }

    /**
     * If parent class has default attributes defined and child hasn't, then they should be respected
     * by child class.
     *
     * @return void
     * @throws Exception
     */
    public function testParentClassAttributesMustBeInheritedByDefaultByChild(): void
    {
        $parentName = $this->createClass(
            '
            #['.Get::class.','.Set::class.',]
            class %name% {
                use '.Accessible::class.';
            }
        '
        );

        $child = $this->createObjFromClassCode(
            '
            class %name% extends '.$parentName.' {
                protected string $foo;
            }
        '
        );

        $expectedValue = $this->randomString();

        /** @phpstan-ignore-next-line */
        $child->foo = $expectedValue;

        $this->assertEquals(
            $expectedValue,
            $child->foo
        );

        $expectedValue = $this->randomString();

        /** @phpstan-ignore-next-line */
        $child->setFoo($expectedValue);

        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $child->getFoo()
        );

        $expectedValue = $this->randomString();

        /** @phpstan-ignore-next-line */
        $child->set('foo', $expectedValue);

        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $child->get('foo')
        );

        $expectedValue = $this->randomString();
        /** @phpstan-ignore-next-line */
        $child->foo($expectedValue);

        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $child->foo()
        );
    }

    /**
     * @throws Exception
     */
    public function testPropertyTagFromParentClassMustBeInherited(): void
    {
        $parentName = $this->createClass(
            '
            /** @property string $foo */
            class %name%
            {
                use '.AccessibleWithPHPDocs::class.';
    
                protected string $foo;
            }        
        '
        );

        $child = $this->createObjFromClassCode(
            '
            class %name% extends '.$parentName.'
            {
            }
        '
        );

        $expectedValue = $this->randomString();

        /** @phpstan-ignore-next-line */
        $child->setFoo($expectedValue);

        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $child->getFoo()
        );
    }

    /**
     * The '$foo' property in child class can be redefined and must inherit the current default attributes
     *
     * @return void
     */
    public function testChildClassMustBeAbleToRedefineProperties(): void
    {
        $parentName = $this->createClass(
            '
            class %name% {
                use '.Accessible::class.';
                protected string $foo = "parent foo";
            }
        '
        );

        $child = $this->createObjFromClassCode(
            '
            #['.Get::class.'(true),'.Set::class.'(true)]
            class %name% extends '.$parentName.' {
                protected string $foo = "child foo";
            }
        '
        );

        $this->assertEquals(
            'child foo',
            /** @phpstan-ignore-next-line */
            $child->foo
        );

        $expectedValue = $this->randomString();

        /** @phpstan-ignore-next-line */
        $child->foo = $expectedValue;

        $this->assertEquals(
            $expectedValue,
            $child->foo
        );
    }

    /**
     * When parent has property $foo (without any endpoint defined) and child defines method setFoo(),
     * then this method must not be used for endpoint because it breaks the consistency in hierarchy.
     *
     * @return void
     * @throws Exception
     */
    public function testChildCantDefineEndpointForParentProperty(): void
    {
        $parentName = $this->createClass(
            '
            #['.Get::class.'(true),'.Set::class.'(true)]
            class %name% {
                use '.Accessible::class.';
                protected string $foo = "foo";
            }
        '
        );

        $child = $this->createObjFromClassCode(
            '
            class %name% extends '.$parentName.' {
                public function setFoo(string $value): void
                {
                    $this->foo = "this method must not be honoured";
                }
            }
        '
        );

        $expectedValue = $this->randomString();

        /** @phpstan-ignore-next-line */
        $child->foo = $expectedValue;

        $this->assertEquals(
            $expectedValue,
            $child->foo
        );
    }

    /**
     * When child defines a property $foo (without endpoint) and parent contains matching endpoint setFoo(),
     * then parent's endpoint must be honoured.
     *
     * @return void
     * @throws Exception
     */
    public function testParentEndpointMustWorkWithChildProperties(): void
    {
        $expectedValue = 'this is parent endpoint';

        $parentName = $this->createClass(
            '
            #['.Get::class.'(true),'.Set::class.'(true)]
            class %name% {
                use '.Accessible::class.';
                public function setFoo(string $value): void
                {
                    $this->foo = "' . $expectedValue . '";
                }
            }
        '
        );

        $child = $this->createObjFromClassCode(
            '
            class %name% extends '.$parentName.' {
                protected string $foo = "foo";
            }
        '
        );

        /** @phpstan-ignore-next-line */
        $child->foo = $this->randomString();

        $this->assertEquals(
            $expectedValue,
            $child->foo
        );
    }

    /**
     * Test the significance when overriding attributes from furthest approximate definition
     * to most precise definition.
     *
     * The override system is illustrated with following figure.
     * Each time an attribute on the node is left undefined, it's fetched from parent node, so attribute
     * defined for topmost class can propagate down to the last child, if not overridden meanwhile.
     *
     *     ...
     *     |
     *     - class1 attribute
     *       |
     *       - class1 property attribute
     *       |
     *       - class2 attribute
     *           |
     *           - class2 property attribute
     *           |
     *           - class3 attribute
     *               |
     *               - class3 property attribute
     *               |
     *               ...
     *
     *
     * @return void
     * @throws Exception
     */
    public function testAttributesInheritanceChain(): void
    {
        $mutatorsClass = $this->createClass(
            '
            class %name% {
                public static function mutator1(string $value): string
                {
                    return "mutator1";
                }

                public static function mutator2(string $value): string
                {
                    return "mutator2";
                }

                public static function mutator3(string $value): string
                {
                    return "mutator3";
                }

                public static function mutator4(string $value): string
                {
                    return "mutator4";
                }
            }        
        '
        );

        $parentName = $this->createClass(
            '
            #['.Get::class.','.Set::class.','.Mutator::class.'(["' . $mutatorsClass . '","mutator1"])]
            class %name% {
                use '.Accessible::class.';
                protected string $foo;'. "\n" .'

                #['.Mutator::class.'(["' . $mutatorsClass . '","mutator2"])]
                protected string $bar;
            }
        '
        );

        $child = $this->createObjFromClassCode(
            '
            #['.Mutator::class.'(["' . $mutatorsClass . '","mutator3"])]
            class %name% extends '.$parentName.' {
                protected string $baz;

                #['.Mutator::class.'(["' . $mutatorsClass . '","mutator4"])]
                protected string $qux;
            }
        '
        );

        /** @phpstan-ignore-next-line */
        $child->foo = $this->randomString();
        $this->assertEquals(
            'mutator1',
            $child->foo
        );

        /** @phpstan-ignore-next-line */
        $child->bar = $this->randomString();
        $this->assertEquals(
            'mutator2',
            $child->bar
        );

        /** @phpstan-ignore-next-line */
        $child->baz = $this->randomString();
        $this->assertEquals(
            'mutator3',
            $child->baz
        );

        /** @phpstan-ignore-next-line */
        $child->qux = $this->randomString();
        $this->assertEquals(
            'mutator4',
            $child->qux
        );
    }
}
