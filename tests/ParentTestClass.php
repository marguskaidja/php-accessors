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
use margusk\GetSet\Attributes\Set;
use margusk\GetSet\GetSetTrait;

#[Get, Set, Delete]
class ParentTestClass
{
    use GetSetTrait;
}