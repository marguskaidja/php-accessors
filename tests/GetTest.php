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

use margusk\GetSet\Attributes\Get;
use margusk\GetSet\Exceptions\InvalidArgumentException;
use margusk\GetSet\GetSetTrait;

class GetTest extends TestCase
{
    public function test_get_should_return_correct_value_with_property_attribute()
    {
        $obj = new class {
            use GetSetTrait;

            #[Get]
            protected string $p1 = 'this is protected value';
        };

        $this->assertEquals('this is protected value', $obj->p1);
        $this->assertEquals('this is protected value', $obj->p1());
        $this->assertEquals('this is protected value', $obj->getP1());
    }

    public function test_get_should_return_correct_value_with_class_attribute()
    {
        $obj = new #[Get] class {
            use GetSetTrait;

            protected string $p1 = 'this is protected value';
        };

        $this->assertEquals('this is protected value', $obj->p1);
        $this->assertEquals('this is protected value', $obj->p1());
        $this->assertEquals('this is protected value', $obj->getP1());
    }

    public function test_get_should_return_correct_value_with_property_attribute_override()
    {
        $obj = new #[Get(false)] class {
            use GetSetTrait;

            #[Get(true)]
            protected string $p1 = 'this is protected value';
        };

        $this->assertEquals('this is protected value', $obj->p1);
        $this->assertEquals('this is protected value', $obj->p1());
        $this->assertEquals('this is protected value', $obj->getP1());
    }

    public function test_get_should_fail_with_protected_property()
    {
        $obj = new #[Get(true)] class {
            use GetSetTrait;

            protected string $p1 = 'this is protected value';

            #[Get(false)]
            protected string $p2 = 'this is another protected value';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to read private/protected property|');

        $obj->getP2();
    }

    public function test_get_should_fail_with_unknown_property_using_direct_access()
    {
        $obj = new #[Get] class {
            use GetSetTrait;

            protected string $p1 = 'this is protected value';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to read unknown property|');

        $obj->p2;
    }

    public function test_get_should_fail_with_unknown_property_using_method_call()
    {
        $obj = new #[Get] class {
            use GetSetTrait;

            protected string $p1 = 'this is protected value';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to read unknown property|');

        $obj->getP2();
    }

    public function test_isset_should_return_false_for_uninitialized_property()
    {
        $obj = new #[Get] class {
            use GetSetTrait;

            protected string $p1;
        };

        $this->assertEquals(false, isset($obj->p1));
        $this->assertEquals(false, $obj->issetP1());
    }

    public function test_isset_should_return_true_for_initialized_property()
    {
        $obj = new #[Get] class {
            use GetSetTrait;

            protected string $p1 = 'initialized';
        };

        $this->assertEquals(true, isset($obj->p1));
        $this->assertEquals(true, $obj->issetP1());
    }

    public function test_attributes_must_be_inherited_from_parent_class()
    {
        $obj = new class extends ParentTestClass {
            protected string $p1 = 'this is protected value';
        };

        $value = 'this is protected value';
        $this->assertEquals($value, $obj->p1);
        $this->assertEquals($value, $obj->getP1());
        $this->assertEquals($value, $obj->p1());
    }

    public function test_honour_existing_getter_method()
    {
        $obj = new #[Get] class {
            use GetSetTrait;

            protected string $p1 = 'starting value';

            public function getP1()
            {
                return 'value from getter';
            }
        };

        $this->assertEquals('value from getter', $obj->p1);
    }

    public function test_honour_existing_isset_method()
    {
        $obj = new #[Get] class {
            use GetSetTrait;

            protected string $p1 = 'starting value';

            public function issetP1()
            {
                return false;
            }
        };

        $this->assertEquals(false, isset($obj->p1));
    }
}
