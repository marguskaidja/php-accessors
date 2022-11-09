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

use margusk\Accessors\Attr\{Delete, Get, Immutable, Mutator, Set, Format};
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
            Immutable::class,
            Format::class
        ];

    /** @var array<class-string, Attr|null> */
    private array $attributes;

    public function __construct()
    {
        $this->attributes = array_fill_keys(self::AVAILABLE_ATTR_NAMES, null);
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

    public static function fromPHPDocs(PhpDocTagNode $tagNode): ?self
    {
        /** @var array<class-string<Attr>, bool> $found */
        $found = match (strtolower($tagNode->name)) {
            '@property' => [Get::class => true, Set::class => true],
            '@property-read' => [Get::class => true, Set::class => false],
            '@property-write' => [Get::class => false, Set::class => true],
            default => []
        };

        if (0 === count($found)) {
            return null;
        }

        $that = new self();

        foreach ($found as $n => $enabled) {
            if (true === array_key_exists($n, $that->attributes)) {
                /** @var Attr $inst */
                $inst = new $n($enabled);
                $that->attributes[$n] = $inst;
            }
        }

        return $that;
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
     * @param  class-string  $name
     * @param  Attr          $attr
     *
     * @return $this
     */
    public function setIfNull(string $name, Attr $attr): self
    {
        if (
            true === array_key_exists($name, $this->attributes)
            && null === $this->attributes[$name]
        ) {
            $this->attributes[$name] = $attr;
        }

        return $this;
    }
}
