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

use margusk\Accessors\Attributes\Set;
use margusk\Accessors\Exception\InvalidArgumentException;
use margusk\Accessors\Accessible;
use TypeError;

class SetTest extends TestCase
{
    public function test_set_should_update_value_with_property_attribute()
    {
        $obj = new class {
            use Accessible;

            #[Set]
            protected string $p1;

            public function getP1Value(): string
            {
                return $this->p1;
            }
        };

        $value = 'this is updated value';
        $obj->p1 = $value;
        $this->assertEquals($value, $obj->getP1Value());

        $value = 'this is updated value2';
        $obj->p1($value);
        $this->assertEquals($value, $obj->getP1Value());

        $value = 'this is updated value3';
        $obj->setP1($value);
        $this->assertEquals($value, $obj->getP1Value());

        $value = 'this is updated value4';
        $obj->set(['p1' => $value]);
        $this->assertEquals($value, $obj->getP1Value());
    }

    public function test_set_should_update_value_with_class_attribute()
    {
        $obj = new #[Set] class {
            use Accessible;

            protected string $p1;

            public function getP1Value(): string
            {
                return $this->p1;
            }
        };

        $value = 'this is updated value';
        $obj->p1 = $value;
        $this->assertEquals($value, $obj->getP1Value());

        $value = 'this is updated value2';
        $obj->p1($value);
        $this->assertEquals($value, $obj->getP1Value());

        $value = 'this is updated value3';
        $obj->setP1($value);
        $this->assertEquals($value, $obj->getP1Value());

        $value = 'this is updated value4';
        $obj->set(['p1' => $value]);
        $this->assertEquals($value, $obj->getP1Value());
    }

    public function test_set_should_update_value_with_property_attribute_override()
    {
        $obj = new #[Set(false)] class {
            use Accessible;

            #[Set(true)]
            protected string $p1;

            public function getP1Value(): string
            {
                return $this->p1;
            }
        };

        $value = 'this is updated value';
        $obj->p1 = $value;
        $this->assertEquals($value, $obj->getP1Value());

        $value = 'this is updated value2';
        $obj->p1($value);
        $this->assertEquals($value, $obj->getP1Value());

        $value = 'this is updated value3';
        $obj->setP1($value);
        $this->assertEquals($value, $obj->getP1Value());

        $value = 'this is updated value4';
        $obj->set(['p1' => $value]);
        $this->assertEquals($value, $obj->getP1Value());
    }

    public function test_set_should_update_multiple_values()
    {
        $obj = new #[Set] class {
            use Accessible;

            protected string $p0 = 'empty';
            protected string $p1 = 'empty';
            protected string $p2 = 'empty';
            protected string $p3 = 'empty';

            public function getPropertyValue(string $propertyName)
            {
                return $this->{$propertyName};
            }
        };

        $values = [
            'value0', 'value1', 'value2', 'value3'
        ];

        $obj->set([
            'p0' => $values[0],
            'p1' => $values[1],
            'p2' => $values[2],
            'p3' => $values[3],
        ]);

        for ($c = 0; $c <= 3; $c++) {
            $this->assertEquals($values[$c], $obj->getPropertyValue('p'.$c));
        }
    }

    public function test_mutator_function_must_be_called_in_setter()
    {
        $obj = new #[Set(true, "htmlspecialchars")] class {
            use Accessible;

            protected string $p1;

            public function getP1Value(): string
            {
                return $this->p1;
            }
        };

        $value = '<b>GetSet</b>';
        $obj->p1 = $value;
        $this->assertEquals(htmlspecialchars($value), $obj->getP1Value());
    }

    public function test_class_mutator_method_with_property_substition_must_be_called()
    {
        $obj = new #[Set(true, [ParentTestClass::class, "staticMutate%property%"])] class {
            use Accessible;

            protected string $p1;

            public function getP1Value(): string
            {
                return $this->p1;
            }
        };

        $value = '<b>GetSet</b>';
        $obj->p1 = $value;
        $this->assertEquals(ParentTestClass::staticMutateP1($value), $obj->getP1Value());
    }

    public function test_object_mutator_method_with_propertyname_substitution_must_be_called()
    {
        $obj = new #[Set(true, '$this->mutate%property%')] class {
            use Accessible;

            protected string $p1;

            public function mutateP1($value): string
            {
                return htmlspecialchars($value);
            }

            public function getP1Value(): string
            {
                return $this->p1;
            }
        };

        $value = '<b>GetSet</b>';
        $obj->p1 = $value;
        $this->assertEquals($obj->mutateP1($value), $obj->getP1Value());
    }

    public function test_disable_mutator_with_property_attribute_override()
    {
        $obj = new #[Set(true, "htmlspecialchars")] class {
            use Accessible;

            #[Set(true, "")]
            protected string $p1;

            public function getP1Value(): string
            {
                return $this->p1;
            }
        };

        $value = '<b>GetSet</b>';
        $obj->p1 = $value;
        $this->assertEquals($value, $obj->getP1Value());
    }

    public function test_set_should_fail_with_protected_value()
    {
        /** @noinspection PhpObjectFieldsAreOnlyWrittenInspection */
        $obj = new #[Set(true)] class {
            use Accessible;

            protected string $p1;

            #[Set(false)]
            protected string $p2;
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to set misconfigured property|');

        $obj->p2 = 'this must fail';
    }

    public function test_set_should_fail_with_unknown_property_through_direct_assignment()
    {
        /** @noinspection PhpObjectFieldsAreOnlyWrittenInspection */
        $obj = new #[Set] class {
            use Accessible;

            protected string $p1 = 'this is protected value';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to set unknown property|');

        $obj->p2 = 'new value';
    }

    public function test_set_should_fail_with_unknown_property_using_method_call()
    {
        $obj = new #[Set] class {
            use Accessible;

            protected string $p1 = 'this is protected value';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to set unknown property|');

        $obj->setP2('new value');
    }

    public function test_set_should_fail_with_unknown_property_using_method_call_with_multiple_properties()
    {
        $obj = new #[Set] class {
            use Accessible;

            protected string $p1 = 'this is protected value';
            protected string $p2 = 'this is protected value';
            protected string $p3 = 'this is protected value';
            protected string $p5 = 'this is protected value';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to set unknown property|');

        $obj->set([
            'p1' => 'new value',
            'p2' => 'new value',
            'p3' => 'new value',
            'p4' => 'new value'
        ]);
    }

    public function test_attributes_must_be_inherited_from_parent_class()
    {
        $obj = new class extends ParentTestClass {
            protected string $p1;

            public function getP1Value(): string
            {
                return $this->p1;
            }
        };

        $value = 'this is protected value';
        $obj->p1 = $value;
        $this->assertEquals($value, $obj->getP1Value());
    }

    public function test_honour_existing_setter_method()
    {
        $obj = new #[Set] class {
            use Accessible;

            protected string $p1;

            public function setP1($value): void
            {
                $this->p1 = 'mutated value';
            }

            public function getP1value(): string
            {
                return $this->p1;
            }
        };

        $obj->p1 = 'updated value';

        $this->assertEquals('mutated value', $obj->getP1value());
    }
}
