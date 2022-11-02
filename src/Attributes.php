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
        $this->attributes = self::availableAttrList();
        $this->attributeIsSet = array_fill_keys(
            array_keys($this->attributes),
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
        if (!isset($this->attributes[$name])) {
            throw InvalidArgumentException::dueInvalidAttrRequested($name);
        }

        if (false === $this->attributeIsSet[$name]) {
            return null;
        }

        return $this->attributes[$name];
    }

    /**
     * @return array<class-string, Attr>
     */
    public static function availableAttrList(): array
    {
        static $cached = null;

        if (null === $cached) {
            $cached = [
                Get::class          => new Get(false),
                Set::class          => new Set(false),
                Delete::class       => new Delete(false),
                Mutator::class      => new Mutator(null),
                ICase::class        => new ICase(),
                Immutable::class    => new Immutable(),
            ];
        }

        return $cached;
    }
}
