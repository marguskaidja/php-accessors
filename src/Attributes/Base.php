<?php

declare(strict_types=1);

namespace margusk\GetSet\Attributes;

class Base
{
    public function __construct(
        protected bool $enabled = true
    ) {
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }
}