<?php

declare(strict_types=1);

namespace margusk\Accessors\Tests;

use Exception;
use margusk\Accessors\Accessible;
use margusk\Accessors\Attr\Get;
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
            /**
             * @property string $foo
             */
            class %name%
            {
                use '.Accessible::class.';
    
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
            /** @phpstan-ignore-next-line */
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
            /** @phpstan-ignore-next-line */
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
    public function testParentEndpointMustApplyToChildProperties(): void
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
            /** @phpstan-ignore-next-line */
            $child->foo
        );
    }
}