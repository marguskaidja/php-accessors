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
use margusk\Accessors\Attr\Set;
use margusk\Accessors\Exception\InvalidArgumentException;

class PropertyNameCasingTest extends TestCase
{
    public function testDirectSyntaxWithInvalidNameCasingMustFail(): void
    {
        $obj = $this->createDummy();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tried to get unknown property/');

        /**
         * @noinspection PhpExpressionResultUnusedInspection
         * @phpstan-ignore-next-line
         */
        $obj->fOO;
    }

    private function createDummy(): object
    {
        return new #[Get, Set] class {
            use Accessible;

            protected string $Foo = 'foo';
        };
    }

    public function testMethodSyntaxWithPropertyAsArgumentWithInvalidNameCasingMustFail(): void
    {
        $obj = $this->createDummy();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tried to get unknown property/');

        /** @phpstan-ignore-next-line */
        $obj->get('fOO');
    }

    public function testMethodSyntaxWithPropertyAsPartOfMethodNameMustSucceed(): void
    {
        $obj = $this->createDummy();

        $this->assertEquals(
            'foo',
            /** @phpstan-ignore-next-line */
            $obj->foO()
        );

        $expected = 'new foo';

        /** @phpstan-ignore-next-line */
        $obj->setFOO($expected);

        $this->assertEquals(
            $expected,
            /** @phpstan-ignore-next-line */
            $obj->getFoO()
        );
    }
}