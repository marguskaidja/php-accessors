<?php

/**
 * This file is part of the GetSet package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/marguskaidja/php-getset
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\GetSet\Tests;

use margusk\GetSet\Attributes\{ICase as CI, Get, Set};
use margusk\GetSet\Exceptions\BadMethodCallException;
use margusk\GetSet\Exceptions\InvalidArgumentException;
use margusk\GetSet\GetSetTrait;
use PHPUnit\Framework\TestCase;

class CombinedTest extends TestCase
{
    public function test_unknown_property_should_throw_InvalidArgumentException()
    {
        $this->expectException(InvalidArgumentException::class);

        $obj = new #[Get, Set] class {
            use GetSetTrait;

            protected string $p1 = 'initial value';
        };

        $obj->getP2();
    }

    public function test_unknown_property_method_should_throw_BadMethodCallException()
    {
        $this->expectException(BadMethodCallException::class);

        $obj = new #[Get, Set] class {
            use GetSetTrait;

            protected string $p1 = 'initial value';
        };

        $obj->P2();
    }

    public function test_property_name_is_case_sensitive_when_accessing_in_direct_syntax()
    {
        $this->expectException(InvalidArgumentException::class);

        $obj = new #[Get] class {
            use GetSetTrait;

            protected string $PropertY = 'some value';
        };

        $obj->propertY;
    }

    public function test_property_name_is_case_insensitive_when_accessing_in_method_syntax()
    {
        $obj = new #[Get, Set] class {
            use GetSetTrait;

            protected string $PropertY = 'some value';
        };

        $this->assertEquals('some value', $obj->propertY());

        $obj->setPROPERTY('new value');

        $this->assertEquals('new value', $obj->getpRoPertY());
    }

    public function test_property_name_is_case_insensitive_when_accessing_in_direct_syntax()
    {
        $obj = new #[Get, Set, CI] class {
            use GetSetTrait;

            protected string $PropertY;
        };

        $value = 'some value';
        $obj->proPerTy = $value;

        $this->assertEquals($value, $obj->prOperTy);
    }
}