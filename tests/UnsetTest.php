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
use margusk\GetSet\Exceptions\InvalidArgumentException;
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
        $this->expectException(InvalidArgumentException::class);

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
        $this->expectException(InvalidArgumentException::class);

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
        $this->expectExceptionMessageMatches('|' . preg_quote('can\'t be unset using unset() function', '|') . '|');

        unset($obj->p1);
    }

    public function test_immutable_original_object_must_not_be_modified()
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

        $obj->unsetP1();
        $this->assertEquals($oldValue, $obj->getP1Value());
    }

    public function test_cloned_object_must_be_returned_with_modified_value()
    {
        $oldValue = 'old value';

        $obj1 = new #[Delete,Get,Immutable] class($oldValue) {
            use GetSetTrait;

            public function __construct(
                protected string $p1
            ) {
            }

            public function equals(self $other): bool
            {
                return  $this === $other;
            }

            public function getP1Value()
            {
                return $this->p1;
            }

            public function issetP1()
            {
                return isset($this->p1);
            }
        };

        $obj2 = $obj1->unsetP1();

        $this->assertEquals($oldValue, $obj1->getP1Value());
        $this->assertEquals(false, $obj2->issetP1());
        $this->assertNotObjectEquals($obj1, $obj2);
    }
}
