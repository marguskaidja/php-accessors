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
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

use function array_fill_keys;
use function array_key_exists;
use function strtolower;

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

    public function __construct()
    {
        $this->attributes = array_fill_keys(self::AVAILABLE_ATTR_NAMES, null);
    }

    public function mergeWithParent(Attributes $parent): static
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

    /**
     * @param  ReflectionClass<object>|ReflectionProperty  $rfObject
     *
     * @return self
     */
    public static function fromReflection(ReflectionClass|ReflectionProperty $rfObject): self
    {
        $that = new self();

        foreach (
            $rfObject->getAttributes(
                Attr::class,
                ReflectionAttribute::IS_INSTANCEOF
            ) as $rfAttribute
        ) {
            $n = $rfAttribute->getName();

            if (true === array_key_exists($n, $that->attributes)) {
                /** @var Attr $inst */
                $inst = $rfAttribute->newInstance();
                $that->attributes[$n] = $inst;
            }
        }

        return $that;
    }

    public static function fromDocBlock(PhpDocTagNode $tagNode): ?self
    {
        /** @var array<class-string<Attr>> $found */
        $found = match(strtolower($tagNode->name)) {
            '@property' => [Get::class, Set::class],
            '@property-read' => [Get::class],
            '@property-write' => [Set::class],
            default => []
        };

        if (0 === count($found)) {
            return null;
        }

        $that = new self();

        foreach ($found as $n) {
            if (true === array_key_exists($n, $that->attributes)) {
                /** @var Attr $inst */
                $inst = new $n(true);
                $that->attributes[$n] = $inst;
            }
        }

        return $that;
    }
}
