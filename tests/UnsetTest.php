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

use margusk\GetSet\Attributes\Delete;
use margusk\GetSet\Attributes\Get;
use margusk\GetSet\Attributes\Immutable;
use margusk\GetSet\Exceptions\BadMethodCallException;
use margusk\GetSet\GetSetTrait;

class UnsetTest extends TestCase
{
    public function test_unset_langconstruct_should_uninitialize_property()
    {
        $obj = new class {
            use GetSetTrait;

            #[Get, Delete]
            protected string $p1 = 'initial value';
        };

        unset($obj->p1);
        $this->assertEquals(false, $obj->issetP1());
        $this->assertEquals(false, isset($obj->p1));
    }

    public function test_unset_langconstruct_should_fail_protected_property()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('|tried to unset private/protected property|');

        $obj = new class {
            use GetSetTrait;

            protected string $p1 = 'initial value';
        };

        unset($obj->p1);
    }

    public function test_unset_method_should_uninitialize_property()
    {
        $obj = new class {
            use GetSetTrait;

            #[Get, Delete]
            protected string $p1 = 'initial value';
        };

        $obj->unsetP1();
        $this->assertEquals(false, $obj->issetP1());
        $this->assertEquals(false, isset($obj->p1));
    }

    public function test_unset_method_should_fail_protected_property()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('|tried to unset private/protected property|');

        $obj = new class {
            use GetSetTrait;

            protected string $p1 = 'initial value';
        };

        $obj->unsetP1();
    }

    public function test_honour_existing_unsetter_method()
    {
        $obj = new class {
            use GetSetTrait;

            #[Get, Delete]
            protected string $p1 = 'initial value';

            public function unsetP1()
            {
            }
        };

        unset($obj->p1);
        $this->assertEquals(true, $obj->issetP1());
        $this->assertEquals(true, isset($obj->p1));
    }

    public function test_unsetting_immutable_property_using_native_unset_must_fail()
    {
        $oldValue = 'old value';
        $obj = new #[Delete,Immutable] class($oldValue) {
            use GetSetTrait;

            public function __construct(
                protected string $p1
            ) {
            }

            public function getP1Value()
            {
                return $this->p1;
            }
        };

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('|' . preg_quote('can\'t be unset', '|') . '|');

        unset($obj->p1);
    }

    public function test_unsetting_immutable_property_using_magic_call_must_fail()
    {
        $oldValue = 'old value';
        $obj = new #[Delete,Immutable] class($oldValue) {
            use GetSetTrait;

            public function __construct(
                protected string $p1
            ) {
            }

            public function getP1Value()
            {
                return $this->p1;
            }
        };

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('|' . preg_quote('can\'t be unset', '|') . '|');

        $obj->unset('p1');
    }

    public function test_unsetting_multiple_values_should_work()
    {
        $obj = new #[Get, Delete] class {
            use GetSetTrait;

            protected string $p0 = 'initialized1';
            protected string $p1 = 'initialized2';
            protected string $p2 = 'initialized3';
            protected string $p3 = 'initialized4';

            public function issetPropertyValue(string $propertyName)
            {
                return isset($this->{$propertyName});
            }
        };

        for ($c = 0; $c <= 3; $c++) {
            $this->assertEquals(true, $obj->issetPropertyValue('p' . $c));
        }

        $obj->unset(['p0', 'p1', 'p2', 'p3']);

        for ($c = 0; $c <= 3; $c++) {
            $this->assertEquals(false, $obj->issetPropertyValue('p' . $c));
        }
    }
}
