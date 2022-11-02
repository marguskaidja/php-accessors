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

class Attributes
{
    /** @var array<class-string, Attr> */
    private array $attributes;

    /** @var array<class-string, bool> */
    private array $attributeIsSet;

    /**
     * @param  ReflectionClass<object>|ReflectionProperty  $rfObject
     */
    public function __construct(ReflectionClass|ReflectionProperty $rfObject)
    {
        // Initialize attribute arrays
        $this->attributes = [
            Get::class          => null,
            Set::class          => null,
            Delete::class       => null,
            Mutator::class      => null,
            ICase::class        => null,
            Immutable::class    => null,
        ];

        $this->attributeIsSet = array_fill_keys(
            array_keys($this->attributes),
            false
        );

        // Read attributes from reflection object
        foreach ($rfObject->getAttributes() as $rfAttribute) {
            $n = $rfAttribute->getName();

            if (true === array_key_exists($n, $this->attributes)) {
                /** @var Attr $inst */
                $inst = $rfAttribute->newInstance();
                $this->attributes[$n] = $inst;
                $this->attributeIsSet[$n] = true;
            }
        }
    }

    public function mergeParent(Attributes $parent): static
    {
        $new = clone $this;

        foreach ($this->attributes as $n => $dummy) {
            if (false === $new->attributeIsSet[$n] && true === $parent->attributeIsSet[$n]) {
                $new->attributes[$n] = $parent->attributes[$n];
                $new->attributeIsSet[$n] = true;
            }
        }

        return $new;
    }

    public function get(string $name): ?Attr
    {
        if (false === array_key_exists($name, $this->attributes)) {
            throw InvalidArgumentException::dueInvalidAttrRequested($name);
        }

        return $this->attributes[$name];
    }
}
