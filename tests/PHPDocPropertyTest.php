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
use margusk\Accessors\Accessible\WithPHPDocs as AccessibleWithPHPDocs;
use margusk\Accessors\Attr\Get;
use margusk\Accessors\Attr\Set;
use margusk\Accessors\Exception\InvalidArgumentException;

class PHPDocPropertyTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testPropertyTagMustBeSupported(): void
    {
        /**
         * @property string $foo
         */
        $obj = new class {
            use AccessibleWithPHPDocs;

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

    public function testPropertyreadTagMustBeSupported(): void
    {
        /**
         * @property-read string $foo
         */
        $obj = new class {
            use AccessibleWithPHPDocs;

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

    public function testPropertywriteTagMustBeSupported(): void
    {
        /**
         * @property-write string $foo
         */
        $obj = new class {
            use AccessibleWithPHPDocs;

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

    public function testAttributeMustBeMoreSignificantThanPhpdocTag(): void
    {
        /**
         * @noinspection PhpObjectFieldsAreOnlyWrittenInspection
         * @property string $foo
         */
        $obj = new class {
            use AccessibleWithPHPDocs;

            #[Get(false),Set(false)]
            protected string $foo = 'foo';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to set misconfigured property|');

        /** @phpstan-ignore-next-line */
        $obj->foo = 'this must fail';
    }

    /**
     * This tests situation where PHPDoc defines property $foo with only read accessor, but class
     * has by default both READ and WRITE accessors enabled.
     *
     * In this situation property $foo must remain readonly, unless there's #[Set] attribute defined
     * precisely at property's declaration..
     *
     * @return void
     */
    public function testPhpdocTagMustExplicitlyExcludeUnnamedAccessors(): void
    {
        /**
         * @noinspection PhpObjectFieldsAreOnlyWrittenInspection
         * @property-read string $foo
         */
        $obj = new #[Get,Set] class {
            use AccessibleWithPHPDocs;

            protected string $foo = 'foo';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to set misconfigured property|');

        /** @phpstan-ignore-next-line */
        $obj->foo = 'this must fail';
    }
}
