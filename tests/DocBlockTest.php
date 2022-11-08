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
use margusk\Accessors\Exception\InvalidArgumentException;

class DocBlockTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testPropertyTagMustBeSupportedInDocblock(): void
    {
        /**
         * @property string $foo
         */
        $obj = new class {
            use Accessible;

            protected string $foo = 'foo';
        };

        // $foo must be readable/writable
        /** @noinspection Annotator */
        $this->assertEquals(
            'foo',
            /** @phpstan-ignore-next-line */
            $obj->foo
        );

        $expectedValue = $this->randomString();

        /**
         * @phpstan-ignore-next-line
         * @noinspection Annotator
         */
        $obj->foo = $expectedValue;

        /** @noinspection Annotator */
        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->foo
        );
    }

    public function testPropertyreadTagMustBeSupportedInDocblock(): void
    {
        /**
         * @property-read string $foo
         */
        $obj = new class {
            use Accessible;

            protected string $foo = 'foo';
        };

        // $foo must be readable
        /** @noinspection Annotator */
        $this->assertEquals(
            'foo',
            /** @phpstan-ignore-next-line */
            $obj->foo
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tried to set misconfigured property/');

        /**
         * @phpstan-ignore-next-line
         * @noinspection Annotator
         */
        $obj->foo = 'new value';
    }

    public function testPropertywriteTagMustBeSupportedInDocblock(): void
    {
        /**
         * @property-write string $foo
         */
        $obj = new class {
            use Accessible;

            protected string $foo = 'foo';

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

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tried to get misconfigured property/');

        /**
         * @phpstan-ignore-next-line
         * @noinspection PhpExpressionResultUnusedInspection
         * @noinspection Annotator
         */
        $obj->foo;
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

}
