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
use margusk\GetSet\GetSetTrait;

class CombinedTest extends TestCase
{
    public function test_direct_access_with_case_sensitivity_and_no_match_must_fail()
    {
        $obj = new #[Get] class {
            use GetSetTrait;

            protected string $PropertY = 'some value';
        };

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/tried to read unknown property/');

        $obj->propertY;
    }

    public function test_flexible_method_call_access_with_case_sensitivity_and_no_match_must_fail()
    {
        $obj = new #[Get] class {
            use GetSetTrait;

            protected string $PropertY = 'some value';
        };

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/tried to read unknown property/');

        $obj->get('PROPerty');
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

    public function test_parent_class_is_parsed_correctly_later_when_child_is_accessed_first()
    {
        $child = new #[Get, Set, CI] class extends ParentTestClass {
            use GetSetTrait;
        };

        $parent = new ParentTestClass();

        $childPropertyValue = 'child property value';
        $parentPropertyValue = 'parent property value';

        $child->parentProperty = $childPropertyValue;
        $parent->parentProperty = $parentPropertyValue;

        $this->assertEquals($childPropertyValue, $child->parentProperty);
        $this->assertEquals($parentPropertyValue, $parent->parentProperty);
    }

}