<?php

declare(strict_types=1);

namespace margusk\GetSet\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
class Delete extends Base
{

}