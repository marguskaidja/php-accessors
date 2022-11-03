<?php

/**
 * This file is part of the margusk/accessors package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/marguskaidja/php-accessors
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\Accessors;

use margusk\Accessors\Attr\{Delete, Get, ICase, Immutable, Mutator, Set};
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

class Attributes
{
    /** @var class-string[] */
    public const AVAILABLE_ATTR_NAMES
        = [
            Get::class,
            Set::class,
            Delete::class,
            Mutator::class,
            ICase::class,
            Immutable::class
        ];

    /** @var array<class-string, Attr|null> */
    private array $attributes;

    /**
     * @param  ReflectionClass<object>|ReflectionProperty|null  $rfObject
     */
    public function __construct(ReflectionClass|ReflectionProperty|null $rfObject)
    {
        $this->attributes = array_fill_keys(self::AVAILABLE_ATTR_NAMES, null);

        /* $rfObject can be null to allow "empty" Attributes to be generated */
        if (null !== $rfObject) {
            // Read attributes from reflection object
            foreach (
                $rfObject->getAttributes(
                    Attr::class,
                    ReflectionAttribute::IS_INSTANCEOF
                ) as $rfAttribute
            ) {
                $n = $rfAttribute->getName();

                if (true === array_key_exists($n, $this->attributes)) {
                    /** @var Attr $inst */
                    $inst = $rfAttribute->newInstance();
                    $this->attributes[$n] = $inst;
                }
            }
        }
    }

    public function mergeToParent(Attributes $parent): static
    {
        $new = clone $this;

        foreach (self::AVAILABLE_ATTR_NAMES as $n) {
            if (null === $new->attributes[$n]) {
                $new->attributes[$n] = $parent->attributes[$n];
            }
        }

        return $new;
    }

    public function get(string $name): ?Attr
    {
        return $this->attributes[$name] ?? null;
    }
}
