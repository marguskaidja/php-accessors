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
     * The '$foo' property in class $parentName MUST not be accessible through child class and should throw
     * error when accessed, because child class Attributes can not change parent properties.
     *
     * @return void
     */
    public function testChildClassAtrributesMustNotChangeParentClassProperties(): void
    {
        $parentName = $this->createClass(
            '
            class %name% {
                use '.Accessible::class.';
                #['.Set::class.'(false)]
                protected string $foo;
            }
        '
        );

        $child = $this->createObjFromClassCode(
            '
            #['.Set::class.'(true)]
            class %name% extends '.$parentName.' {
            }
        '
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
    public function testParentClassAttributesMustBeInheritedByDefaultIntoChild(): void
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
}
