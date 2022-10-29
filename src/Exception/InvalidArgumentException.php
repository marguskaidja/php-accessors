<?php

/**
 * This file is part of the GetSet package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/marguskaidja/php-getset
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\GetSet\Exception;

class InvalidArgumentException extends \InvalidArgumentException
{
    public static function dueTriedToGetUnknownProperty(string $property): static
    {
        return new static(
            sprintf('tried to get unknown property "%s"', $property)
        );
    }

    public static function dueTriedToGetMisconfiguredProperty(string $property): static
    {
        return new static(
            sprintf('tried to get misconfigured property "%s" (missing #[Get] attribute?)', $property)
        );
    }

    public static function dueTriedToSetUnknownProperty(string $property): static
    {
        return new static(
            sprintf('tried to set unknown property "%s"', $property)
        );
    }

    public static function dueTriedToSetMisconfiguredProperty(string $property): static
    {
        return new static(
            sprintf('tried to set misconfigured property "%s" (missing #[Set] attribute?)', $property)
        );
    }

    public static function dueTriedToUnsetUnknownProperty(string $property): static
    {
        return new static(
            sprintf('tried to unset unknown property "%s"', $property)
        );
    }

    public static function dueTriedToUnsetMisconfiguredProperty(string $property): static
    {
        return new static(
            sprintf('tried to unset misconfigured property "%s" (missing #[Delete] attribute?)', $property)
        );
    }

    public static function dueImmutablePropertyCantBeUnset(string $property): static
    {
        return new static(
            sprintf('immutable property "%s" can\'t be unset', $property)
        );
    }

    public static function dueMultiPropertyAccessorCanHaveExactlyOneArgument(string $method): static
    {
        return new static(
            sprintf(
                'when first argument is array() then there can\'t be more arguments to method %s()',
                $method
            )
        );
    }

    public static function dueMethodIsMissingPropertyNameArgument(string $method): static
    {
        return new static(
            sprintf('missing argument #1 (property name) to method %s()', $method)
        );
    }

    public static function duePropertyNameArgumentMustBeString(string $method, int $expectedArgIdx): static
    {
        return new static(
            sprintf(
                'expecting string as argument #%u (property name) to method %s()',
                $expectedArgIdx,
                $method
            )
        );
    }

    public static function dueMethodIsMissingPropertyValueArgument(string $method, int $expectedArgIdx): static
    {
        return new static(
            sprintf(
                'missing argument #%u (property value) to method %s()',
                $expectedArgIdx,
                $method
            )
        );
    }

    public static function dueMethodHasMoreArgumentsThanExpected(string $method, int $expectedArgCount): static
    {
        return new static(
            sprintf(
                'expecting exactly %u argument(s) to method %s()',
                $expectedArgCount,
                $method
            )
        );
    }

}