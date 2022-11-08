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
use margusk\Accessors\Attr\{Get, Set};

class CombinedTest extends TestCase
{
    public function testParentClassIsParsedCorrectlyLaterWhenChildIsAccessedFirst(): void
    {
        $parentName = $this->createClass(
            '   
            #['.Get::class.','.Set::class.']
            class %name% {
                use '.Accessible::class.';
                protected string $foo = "foo";
            }
        '
        );

        $child = $this->createObjFromClassCode(
            '
            class %name% extends '.$parentName.' {}
        '
        );

        $parent = new $parentName();

        $expectedValueForChild = 'child foo';
        $expectedValueForParent = 'parent foo';

        /** @phpstan-ignore-next-line */
        $child->foo = $expectedValueForChild;
        /** @phpstan-ignore-next-line */
        $parent->foo = $expectedValueForParent;

        $this->assertEquals(
            $expectedValueForChild,
            $child->foo
        );

        $this->assertEquals(
            $expectedValueForParent,
            $parent->foo
        );
    }

    public function testNamedArgumentsToAccessorMethodActTheSameAsWithoutNamedArguments(): void
    {
        $obj = new #[Get, Set] class {
            use Accessible;

            protected string $foo = 'some value';

            public function getFooValue(): string
            {
                return $this->foo;
            }
        };

        $expectedValue = 'new foo';

        /** @phpstan-ignore-next-line */
        $obj->set(foo: ['foo' => $expectedValue]);

        $this->assertEquals(
            $expectedValue,
            $obj->getFooValue()
        );
    }
}
