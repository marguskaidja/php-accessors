<?php

/**
 * This file is part of the GetSet package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/marguskaidja/php-getset
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\Accessors\Tests;

use margusk\Accessors\Attributes\Delete;
use margusk\Accessors\Attributes\Get;
use margusk\Accessors\Attributes\Immutable;
use margusk\Accessors\Exception\InvalidArgumentException;
use margusk\Accessors\Accessible;

class UnsetTest extends TestCase
{
    public function test_unset_langconstruct_should_uninitialize_property()
    {
        $obj = new class {
            use Accessible;

            #[Get, Delete]
            protected string $p1 = 'initial value';
        };

        unset($obj->p1);
        $this->assertEquals(false, $obj->issetP1());
        $this->assertEquals(false, isset($obj->p1));
    }

    public function test_unset_langconstruct_should_fail_protected_property()
    {
        $obj = new class {
            use Accessible;

            protected string $p1 = 'initial value';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to unset misconfigured property|');

        unset($obj->p1);
    }

    public function test_unset_method_should_uninitialize_property()
    {
        $obj = new class {
            use Accessible;

            #[Get, Delete]
            protected string $p1 = 'initial value';
        };

        $obj->unsetP1();
        $this->assertEquals(false, $obj->issetP1());
        $this->assertEquals(false, isset($obj->p1));
    }

    public function test_unset_method_should_fail_protected_property()
    {
        $obj = new class {
            use Accessible;

            protected string $p1 = 'initial value';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to unset misconfigured property|');

        $obj->unsetP1();
    }

    public function test_honour_existing_unsetter_method()
    {
        $obj = new class {
            use Accessible;

            #[Get, Delete]
            protected string $p1 = 'initial value';

            public function unsetP1(): void
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
            use Accessible;

            public function __construct(
                protected string $p1
            ) {
            }

            public function getP1Value(): string
            {
                return $this->p1;
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|immutable property .+ can\'t be unset|');

        unset($obj->p1);
    }

    public function test_unsetting_immutable_property_using_magic_call_must_fail()
    {
        $oldValue = 'old value';
        $obj = new #[Delete,Immutable] class($oldValue) {
            use Accessible;

            public function __construct(
                protected string $p1
            ) {
            }

            public function getP1Value(): string
            {
                return $this->p1;
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|immutable property .+ can\'t be unset|');

        $obj->unset('p1');
    }

    public function test_unsetting_multiple_values_should_work()
    {
        $obj = new #[Get, Delete] class {
            use Accessible;

            protected string $p0 = 'initialized1';
            protected string $p1 = 'initialized2';
            protected string $p2 = 'initialized3';
            protected string $p3 = 'initialized4';

            public function issetPropertyValue(string $propertyName): bool
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
