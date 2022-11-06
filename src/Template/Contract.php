<?php

declare(strict_types=1);

namespace margusk\Accessors\Template;

interface Contract
{
    public function matchEndpointCandidate(string $method): ?Method;

    public function matchCalled(string $method): ?Method;

    public function allowPropertyNameOnly(): bool;
}
