<?php

declare(strict_types=1);

namespace margusk\GetSet\Tests;

use margusk\GetSet\Attributes\Delete;
use margusk\GetSet\Attributes\Get;
use margusk\GetSet\Attributes\Set;
use margusk\GetSet\GetSetTrait;

#[Get,Set,Delete]
class ParentTestClass
{
    use GetSetTrait;
}