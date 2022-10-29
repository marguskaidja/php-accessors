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

class BadMethodCallException extends \BadMethodCallException
{
    public static function dueUnknownAccessorMethod(string $method): static
    {
        return new static(
            sprintf('unknown accessor method %s()', $method)
        );
    }

    public static function dueMutablePropertiesMustBeCalledUsingSet(string $propertyName): static
    {
        return new static(
            sprintf(
                'property "%s" is mutable, but method with() is available only for immutable properties (use set() instead)',
                $propertyName
            )
        );
    }

    public static function dueImmutablePropertiesMustBeCalledUsingWith(string $propertyName): static
    {
        return new static(
            sprintf(
                'property "%s" is immutable, but method set() is available only for mutable properties (use with() instead)',
                $propertyName
            )
        );
    }

    public static function dueImmutablePropertiesCantBeSetUsingAssignmentOperator(string $propertyName): static
    {
        return new static(
            sprintf(
                'immutable property "%s" can\'t be set using assignment operator (use with%s() instead)',
                $propertyName,
                ucfirst($propertyName)
            )
        );
    }
}
