<?php

/**
 * This file is part of the GetSet package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/m4r9u5/GetSet
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\GetSet\Tests;

use margusk\GetSet\Attributes\Delete;
use margusk\GetSet\Attributes\Get;
use margusk\GetSet\Exceptions\InvalidArgumentException;
use margusk\GetSet\GetSetTrait;
use PHPUnit\Framework\TestCase;

class UnsetTest extends TestCase
{
    public function test_unset_should_uninitialize_property()
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

    public function test_unset_should_fail_protected_property()
    {
        $this->expectException(InvalidArgumentException::class);

        $obj = new class {
            use GetSetTrait;

            protected string $p1 = 'initial value';
        };

        unset($obj->p1);
    }
}
