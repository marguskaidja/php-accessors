<?php

declare(strict_types=1);

namespace margusk\Accessors\Tests;

use margusk\Accessors\Accessible;
use margusk\Accessors\Exception\InvalidArgumentException;

class DocBlockTest extends TestCase
{
    public function test_property_tag_must_be_supported_in_docblock(): void
    {
        /**
         * @property string $p1
         */
        $obj = new class {
            use Accessible;

            protected string $p1 = 'p1';
        };

        // p1 must be readable/writable
        $this->assertEquals(
            'p1',
            /** @phpstan-ignore-next-line */
            $obj->p1
        );

        $expectedValue = 'value changed';

        /** @phpstan-ignore-next-line */
        $obj->p1 = $expectedValue;

        $this->assertEquals(
            $expectedValue,
            /** @phpstan-ignore-next-line */
            $obj->p1
        );
    }

    public function test_propertyread_tag_must_be_supported_in_docblock(): void
    {
        /**
         * @property-read string $p1
         */
        $obj = new class {
            use Accessible;

            protected string $p1 = 'p1';
        };

        // p1 must be readable
        $this->assertEquals(
            'p1',
            /** @phpstan-ignore-next-line */
            $obj->p1
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tried to set misconfigured property/');

        /** @phpstan-ignore-next-line */
        $obj->p1 = 'new value';
    }

    public function test_propertywrite_tag_must_be_supported_in_docblock(): void
    {
        /**
         * @property-write string $p1
         */
        $obj = new class {
            use Accessible;

            protected string $p1 = 'p1';

            public function getP1Value(): string
            {
                return $this->p1;
            }
        };

        $expectedValue = 'new value';

        /** @phpstan-ignore-next-line */
        $obj->p1 = $expectedValue;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tried to get misconfigured property/');

        /**
         * @phpstan-ignore-next-line
         * @noinspection PhpExpressionResultUnusedInspection
         */
        $obj->p1;
    }
}
