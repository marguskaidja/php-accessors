<?php

declare(strict_types=1);

namespace margusk\GetSet\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
class Set extends Base
{
    protected $mutator;

    public function __construct(bool $enabled = true, string $mutator = null)
    {
        parent::__construct($enabled);
        $this->mutator = $mutator;
    }

    public function mutator(): string|null
    {
        return $this->mutator;
    }
}