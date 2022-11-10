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

use function sprintf;
use function ucfirst;

final class BadMethodCallException extends \BadMethodCallException
{
    public static function dueClassNotUsingAccessibleTrait(string $class): self
    {
        return new self(
            sprintf(
                'class "%s" does not use "%s" trait',
                $class,
                Accessible::class
            )
        );
    }

    public static function dueUnknownAccessorMethod(string $class, string $method): self
    {
        return new self(
            sprintf('unknown accessor method %s::%s()', $class, $method)
        );
    }

    public static function dueMutablePropertiesMustBeCalledUsingSet(string $class, string $propertyName): self
    {
        return new self(
            sprintf(
                'property "%s::$%s" is mutable, but method with() is available only for immutable properties (use set() instead)',
                $class,
                $propertyName
            )
        );
    }

    public static function dueImmutablePropertiesMustBeCalledUsingWith(string $class, string $propertyName): self
    {
        return new self(
            sprintf(
                'property "%s::$%s" is immutable, but method set() is available only for mutable properties (use with() instead)',
                $class,
                $propertyName
            )
        );
    }

    public static function dueImmutablePropertiesCantBeSetUsingAssignmentOperator(
        string $class,
        string $propertyName
    ): self {
        return new self(
            sprintf(
                'immutable property "%s::$%s" can\'t be set using assignment operator (use with%s() method instead)',
                $class,
                $propertyName,
                ucfirst($propertyName)
            )
        );
    }
}
