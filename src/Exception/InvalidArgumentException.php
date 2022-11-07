<?php

/**
 * This file is part of the margusk/accessors package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/marguskaidja/php-accessors
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\Accessors\Exception;

use margusk\Accessors\Accessible;
use margusk\Accessors\Format\Contract;

use function gettype;
use function implode;
use function is_array;
use function sprintf;

final class InvalidArgumentException extends \InvalidArgumentException
{
    /**
     * @param  string  $class
     * @param  string  $property
     * @param  string|string[]  $callable
     *
     * @return static
     */
    public static function dueInvalidMutatorCallback(string $class, string $property, string|array $callable): self
    {
        return new self(
            sprintf(
                'mutator callback "%s" for property "%s::$%s" is not valid',
                (is_array($callable) ? implode('::', $callable) : $callable),
                $class,
                $property
            )
        );
    }

    public static function dueClassAttributeCanOccurOnceOnTopOfHierarchy(
        string $parentClass,
        string $childClass,
        string $attribute
    ): self {
        return new self(
            sprintf(
                'Attribute "%s" can be used only once in hierarchy and with the first class using "%s" trait, which is "%s". '
                .
                'Declaring it in child class "%s" is not allowed.',
                $attribute,
                Accessible::class,
                $parentClass,
                $childClass
            )
        );
    }

    public static function dueFormatClassMustImplementValidContract(string $format): self
    {
        return new self(
            sprintf(
                'Format "%s" must implement interface "%s"',
                $format,
                Contract::class
            )
        );
    }

    public static function dueTriedToGetUnknownProperty(string $class, string $property): self
    {
        return new self(
            sprintf('tried to get unknown property "%s::$%s"', $class, $property)
        );
    }

    public static function dueTriedToGetPublicProperty(string $class, string $property): self
    {
        return new self(
            sprintf('implicit getter is not available for public properties like "%s::$%s"', $class, $property)
        );
    }

    public static function dueTriedToGetMisconfiguredProperty(string $class, string $property): self
    {
        return new self(
            sprintf('tried to get misconfigured property "%s::$%s" (missing #[Get] attribute?)', $class, $property)
        );
    }

    public static function dueTriedToSetUnknownProperty(string $class, string $property): self
    {
        return new self(
            sprintf('tried to set unknown property "%s::$%s"', $class, $property)
        );
    }

    public static function dueTriedToSetPublicProperty(string $class, string $property): self
    {
        return new self(
            sprintf('implicit setter is not available for public properties like "%s::$%s"', $class, $property)
        );
    }

    public static function dueTriedToSetMisconfiguredProperty(string $class, string $property): self
    {
        return new self(
            sprintf('tried to set misconfigured property "%s::$%s" (missing #[Set] attribute?)', $class, $property)
        );
    }

    public static function dueTriedToUnsetUnknownProperty(string $class, string $property): self
    {
        return new self(
            sprintf('tried to unset unknown property "%s::$%s"', $class, $property)
        );
    }

    public static function dueTriedToUnsetPublicProperty(string $class, string $property): self
    {
        return new self(
            sprintf('implicit unsetter is not available for public properties like "%s::$%s"', $class, $property)
        );
    }

    public static function dueTriedToUnsetMisconfiguredProperty(string $class, string $property): self
    {
        return new self(
            sprintf('tried to unset misconfigured property "%s::$%s" (missing #[Delete] attribute?)', $class, $property)
        );
    }

    public static function dueImmutablePropertyCantBeUnset(string $class, string $property): self
    {
        return new self(
            sprintf('immutable property "%s::$%s" can\'t be unset', $class, $property)
        );
    }

    public static function dueMultiPropertyAccessorCanHaveExactlyOneArgument(string $class, string $method): self
    {
        return new self(
            sprintf(
                'when first argument is array() then there can\'t be more arguments to method %s::%s()',
                $class,
                $method
            )
        );
    }

    public static function dueMethodIsMissingPropertyNameArgument(string $class, string $method): self
    {
        return new self(
            sprintf('missing argument #1 (property name) to method %s::%s()', $class, $method)
        );
    }

    public static function duePropertyNameArgumentMustBeString(string $class, string $method, int $expectedArgIdx): self
    {
        return new self(
            sprintf(
                'expecting string as argument #%u (property name) to method %s::%s()',
                $expectedArgIdx,
                $class,
                $method
            )
        );
    }

    public static function dueMultiPropertyArrayContainsNonStringProperty(
        string $class,
        string $method,
        mixed $nonStringValue
    ): self {
        return new self(
            sprintf(
                'property names in multi-property array be strings for method %s::%s() (%s encountered)',
                $class,
                $method,
                gettype($nonStringValue)
            )
        );
    }

    public static function dueMethodIsMissingPropertyValueArgument(
        string $class,
        string $method,
        int $expectedArgIdx
    ): self {
        return new self(
            sprintf(
                'missing argument #%u (property value) to method %s::%s()',
                $expectedArgIdx,
                $class,
                $method
            )
        );
    }

    public static function dueMethodHasMoreArgumentsThanExpected(
        string $class,
        string $method,
        int $expectedArgCount
    ): self {
        return new self(
            sprintf(
                'expecting exactly %u argument(s) to method %s::%s()',
                $expectedArgCount,
                $class,
                $method
            )
        );
    }
}