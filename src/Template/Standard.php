<?php

declare(strict_types=1);

namespace margusk\Accessors\Template;

use margusk\Accessors\Template\Contract as TemplateContract;

use function implode;
use function preg_match;
use function strtolower;

class Standard implements TemplateContract
{
    public function matchEndpointCandidate(string $method): ?Method
    {
        return $this->matchCalled($method);
    }

    public function matchCalled(string $method): ?Method
    {
        if (
            preg_match(
                '/^(' . implode('|', Method::TYPES) . ')(.*)/i',
                strtolower($method),
                $matches
            )
        ) {
            $methodName = $matches[1];
            $propertyName = $matches[2];

            return new Method(
                Method::TYPES[$methodName],
                $propertyName
            );
        }

        return null;
    }

    public function allowPropertyNameOnly(): bool
    {
        return true;
    }
}