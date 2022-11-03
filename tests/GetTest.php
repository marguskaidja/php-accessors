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

use margusk\Accessors\Attr\Get;
use margusk\Accessors\Exception\InvalidArgumentException;
use margusk\Accessors\Accessible;

class GetTest extends TestCase
{
    public function test_get_should_return_correct_value_with_property_attribute(): void
    {
        $obj = new class {
            use Accessible;

            #[Get]
            protected string $p1 = 'this is protected value';
        };


        $this->assertEquals(
            'this is protected value',
            /** @phpstan-ignore-next-line */
            $obj->p1
        );
        $this->assertEquals(
            'this is protected value',
            /** @phpstan-ignore-next-line */
            $obj->p1()
        );
        $this->assertEquals(
            'this is protected value',
            /** @phpstan-ignore-next-line */
            $obj->getP1()
        );
        $this->assertEquals(
            'this is protected value',
            /** @phpstan-ignore-next-line */
            $obj->get('p1')
        );
    }

    public function test_get_should_return_correct_value_with_class_attribute(): void
    {
        $obj = new #[Get] class {
            use Accessible;

            protected string $p1 = 'this is protected value';
        };

        $this->assertEquals(
            'this is protected value',
            /** @phpstan-ignore-next-line */
            $obj->p1
        );
        $this->assertEquals(
            'this is protected value',
            /** @phpstan-ignore-next-line */
            $obj->p1()
        );
        $this->assertEquals(
            'this is protected value',
            /** @phpstan-ignore-next-line */
            $obj->getP1()
        );
        $this->assertEquals(
            'this is protected value',
            /** @phpstan-ignore-next-line */
            $obj->get('p1')
        );
    }

    public function test_get_should_return_correct_value_with_property_attribute_override(): void
    {
        $obj = new #[Get(false)] class {
            use Accessible;

            #[Get(true)]
            protected string $p1 = 'this is protected value';
        };

        $this->assertEquals(
            'this is protected value',
            /** @phpstan-ignore-next-line */
            $obj->p1
        );
        $this->assertEquals(
            'this is protected value',
            /** @phpstan-ignore-next-line */
            $obj->p1()
        );
        $this->assertEquals(
            'this is protected value',
            /** @phpstan-ignore-next-line */
            $obj->getP1()
        );
        $this->assertEquals(
            'this is protected value',
            /** @phpstan-ignore-next-line */
            $obj->get('p1')
        );
    }

    public function test_get_should_fail_with_protected_property(): void
    {
        $obj = new #[Get(true)] class {
            use Accessible;

            protected string $p1 = 'this is protected value';

            #[Get(false)]
            protected string $p2 = 'this is another protected value';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to get misconfigured property|');

        /** @phpstan-ignore-next-line */
        $obj->getP2();
    }

    public function test_get_should_fail_with_unknown_property_using_direct_access(): void
    {
        $obj = new #[Get] class {
            use Accessible;

            protected string $p1 = 'this is protected value';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to get unknown property|');

        /**
         * @noinspection PhpExpressionResultUnusedInspection
         * @phpstan-ignore-next-line
         */
        $obj->p2;
    }

    public function test_get_should_fail_with_unknown_property_using_method_call(): void
    {
        $obj = new #[Get] class {
            use Accessible;

            protected string $p1 = 'this is protected value';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|tried to get unknown property|');

        /** @phpstan-ignore-next-line */
        $obj->getP2();
    }

    public function test_isset_should_return_false_for_uninitialized_property(): void
    {
        $obj = new #[Get] class {
            use Accessible;

            protected string $p1;
        };

        $this->assertEquals(false, isset($obj->p1));
        $this->assertEquals(
            false,
            /** @phpstan-ignore-next-line */
            $obj->issetP1()
        );
    }

    public function test_isset_should_return_true_for_initialized_property(): void
    {
        $obj = new #[Get] class {
            use Accessible;

            protected string $p1 = 'initialized';
        };

        $this->assertEquals(true, isset($obj->p1));
        $this->assertEquals(
            true,
            /** @phpstan-ignore-next-line */
            $obj->issetP1()
        );
    }

    public function test_attributes_must_be_inherited_from_parent_class(): void
    {
        $obj = new class extends ParentTestClass {
            protected string $p1 = 'this is protected value';
        };

        $value = 'this is protected value';
        $this->assertEquals(
            $value,
            /** @phpstan-ignore-next-line */
            $obj->p1
        );
        $this->assertEquals(
            $value,
            /** @phpstan-ignore-next-line */
            $obj->getP1()
        );
        $this->assertEquals(
            $value,
            /** @phpstan-ignore-next-line */
            $obj->p1()
        );
    }

    public function test_honour_existing_getter_method(): void
    {
        $obj = new #[Get] class {
            use Accessible;

            protected string $p1 = 'starting value';

            public function getP1(): string
            {
                return 'value from getter';
            }
        };

        $this->assertEquals(
            'value from getter',
            /** @phpstan-ignore-next-line */
            $obj->p1
        );
    }

    public function test_honour_existing_isset_method(): void
    {
        $obj = new #[Get] class {
            use Accessible;

            protected string $p1 = 'starting value';

            public function issetP1(): bool
            {
                return false;
            }
        };

        $this->assertEquals(false, isset($obj->p1));
    }

    public function test_getting_public_property_must_fail(): void
    {
        $obj = new #[Get] class {
            use Accessible;

            public string $p1 = 'value';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|implicit getter is not available for public properties|');

        /** @phpstan-ignore-next-line */
        $obj->getP1();
    }

    public function test_testing_public_property_with_isset_must_fail(): void
    {
        $obj = new #[Get] class {
            use Accessible;

            public string $p1 = 'value';
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('|implicit getter is not available for public properties|');

        /** @phpstan-ignore-next-line */
        $obj->issetP1();
    }

}
