<?php

declare(strict_types=1);

namespace margusk\Accessors;

use margusk\Accessors\Attr\{
    Delete,
    Get,
    ICase,
    Immutable,
    Mutator,
    Set
};
use margusk\Accessors\Exception\InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;

/** @var array<class-string, Attr> */
const _AVAILABLE_ATTR_LIST = [
    Get::class          => new Get(false),
    Set::class          => new Set(false),
    Delete::class       => new Delete(false),
    Mutator::class      => new Mutator(null),
    ICase::class        => new ICase(false),
    Immutable::class    => new Immutable(false),
];

class Attributes
{
    /** @var array<string, Attr|null> */
    private array $attributes;

    /** @var array<string, bool> */
    private array $attributeIsSet;

    /** @var array<class-string, Attr> */
    public const AVAILABLE_ATTR_LIST = _AVAILABLE_ATTR_LIST;

    /**
     * @param  ReflectionClass<object>|ReflectionProperty  $rfObject
     */
    public function __construct(ReflectionClass|ReflectionProperty $rfObject)
    {
        // Initialize attributes array with <null> values
        $this->attributes = self::AVAILABLE_ATTR_LIST;
        $this->attributeIsSet = array_fill_keys(
            array_keys(self::AVAILABLE_ATTR_LIST),
            false
        );

        // Read attributes from reflection object
        foreach ($rfObject->getAttributes() as $rfAttribute) {
            $n = $rfAttribute->getName();

            if (isset($this->attributes[$n])) {
                /** @var Attr $inst */
                $inst = $rfAttribute->newInstance();

                if ($n === ICase::class && !$inst->enabled()) {
                    throw InvalidArgumentException::dueCaseInsensitivityCantBeSetToFalse($rfObject);
                } else {
                    if ($n === Immutable::class && !$inst->enabled()) {
                        throw InvalidArgumentException::dueImmutableCantBeSetToFalse($rfObject);
                    }
                }

                $this->attributes[$n] = $inst;
                $this->attributeIsSet[$n] = true;
            }
        }
    }

    public function mergeParent(Attributes $parent): static
    {
        $new = clone $this;

        foreach (self::AVAILABLE_ATTR_LIST as $n => $dummy) {
            if (false === $new->attributeIsSet[$n] && true === $parent->attributeIsSet[$n]) {
                $new->attributes[$n] = $parent->attributes[$n];
                $new->attributeIsSet[$n] = true;
            }
        }

        return $new;
    }

    public function get(string $name): Attr
    {
        if (!isset($this->attributes[$name])) {
            throw InvalidArgumentException::dueInvalidAttrRequested($name);
        }

        return $this->attributes[$name];
    }
}
