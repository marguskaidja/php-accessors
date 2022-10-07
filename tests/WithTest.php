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

use margusk\GetSet\Attributes\Set;
use margusk\GetSet\Attributes\Immutable;
use margusk\GetSet\Exceptions\BadMethodCallException;
use margusk\GetSet\GetSetTrait;

class WithTest extends TestCase
{
    public function test_set_method_must_fail()
    {
        $obj = new #[Set,Immutable] class('value') {
            use GetSetTrait;

            public function __construct(
                protected string $p1
            ) {
            }
        };

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/is available only for mutable properties/');

        $obj->setP1('value');
    }

    public function test_direct_assignment_must_fail()
    {
        $obj = new #[Set,Immutable] class('old value') {
            use GetSetTrait;

            public function __construct(
                protected string $p1
            ) {
            }
        };

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageMatches('/can\'t be set using assignment operator/');

        $obj->p1 = 'new value';
    }

    public function test_original_object_must_not_be_modified()
    {
        $oldValue = 'old value';
        $obj = new #[Set,Immutable] class($oldValue) {
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

        $obj->withP1('new value');

        $this->assertEquals($oldValue, $obj->getP1Value());
    }

    public function test_cloned_object_must_be_returned_with_modified_value()
    {
        $oldValue = 'old value';

        $obj1 = new #[Set,Immutable] class($oldValue) {
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
        };

        $newValue = 'new value';
        $obj2 = $obj1->withP1($newValue);

        $this->assertEquals($oldValue, $obj1->getP1Value());
        $this->assertEquals($newValue, $obj2->getP1Value());
        $this->assertNotObjectEquals($obj1, $obj2);
    }
}