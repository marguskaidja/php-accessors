<?php

/**
 * This file is part of the GetSet package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/marguskaidja/php-getset
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\GetSet;

use margusk\GetSet\Exceptions\BadMethodCallException;
use margusk\GetSet\Exceptions\InvalidArgumentException;

trait GetSetTrait
{
    public function __call(string $method, array $args): mixed
    {
        $classConf = Core::loadConfiguration(static::class);

        $lcaseMethod = strtolower($method);
        $prefix = substr($lcaseMethod, 0, 3);

        // Check if magic method is one of the followings:
        //      set<Property>
        //      with<Property>
        //      get<Property>
        //      isset<Property>
        //      unset<Property>"
        if ('set' !== $prefix
            && 'get' !== $prefix
            && 'with' !== ($prefix = substr($lcaseMethod, 0, 4))
            && !in_array(($prefix = substr($lcaseMethod, 0, 5)), ['unset', 'isset'])
        ) {
            $prefix = null;
        }

        $nArgs = count($args);
        $property = substr($method, strlen((string)$prefix));

        // If not one of the explicit calls above, then check if whole method name is property name like
        //  $obj->somePropertyName('somevalue')
        if (null === $prefix && isset($classConf['byLCase'][strtolower($property)])) {
            // If there are zero arguments, then interpret the call as Getter
            // If there are arguments, then it's Setter
            if ($nArgs > 0) {
                $prefix = 'set';
            } else {
                $prefix = 'get';
            }
        }

        if (null !== $prefix) {
            if ('' === $property) {
                $property = (string)array_shift($args);
                // If property name is read as first argument to current method, then
                // case sensitivity/insensitivity is determined by usage of #[ICase] attribute
                $getPropertyConfFunc = $classConf['getPropertyConf'];
            } else {
                // If property name is the suffix in current method name, then it's always
                // interpreted case insensitive
                $getPropertyConfFunc = $classConf['getPropertyConfICase'];
            }

            if ('' === $property) {
                throw new InvalidArgumentException('missing first argument (property name) to method %s()', $method);
            }

            $propertyConf = $getPropertyConfFunc($property);
            $immutable = $propertyConf['immutable'] ?? false;

            // Call Set/With
            if ((!$immutable && 'set' === $prefix) || ($immutable && 'with' === $prefix)) {
                // Check if exactly 1 argument is left in args stack
                if (1 !== count($args)) {
                    throw new InvalidArgumentException(
                        sprintf('expecting exactly %u argument(s) to method %s()', $nArgs - count($args) + 1, $method)
                    );
                }

                return $classConf['setImpl']($this, $property, array_shift($args), $propertyConf);
            // Get, Set or Isset
            } elseif (in_array($prefix, ['get', 'isset', 'unset'])) {
                if (0 !== count($args)) {
                    throw new InvalidArgumentException(
                        sprintf('expecting exactly %u argument(s) to method %s()', $nArgs - count($args), $method)
                    );
                }

                return $classConf[$prefix . 'Impl']($this, $property, $propertyConf);
            }
        }

        if ('with' === $prefix) {
            throw new BadMethodCallException(
                sprintf(
                    'method %s() is available only for immutable properties (use %s::set%s() instead)',
                    $method,
                    static::class,
                    ucfirst($property)
                )
            );
        } elseif ('set' === $prefix) {
            throw new BadMethodCallException(
                sprintf(
                    'method %s() is available only for mutable properties (use %s::with%s() instead)',
                    $method,
                    static::class,
                    ucfirst($property)
                )
            );
        } else {
            throw new BadMethodCallException(
                sprintf('unknown method %s()', $method)
            );
        }
    }

    public function __get(string $property): mixed
    {
        $classConf = Core::loadConfiguration(static::class);
        $propertyConf = $classConf['getPropertyConf']($property);

        return $classConf['getImpl']($this, $property, $propertyConf);
    }

    public function __set(string $property, mixed $value): void
    {
        $classConf = Core::loadConfiguration(static::class);
        $propertyConf = $classConf['getPropertyConf']($property);
        $immutable = $propertyConf['immutable'] ?? false;

        if ($immutable) {
            throw new BadMethodCallException(
                sprintf(
                    'immutable property "%s" can\'t be set using assignment operator (use %s::with%s() instead)',
                    $property,
                    static::class,
                    ucfirst($property)
                )
            );
        }

        $classConf['setImpl']($this, $property, $value, $propertyConf);
    }

    public function __isset(string $property): bool
    {
        $classConf = Core::loadConfiguration(static::class);
        $propertyConf = $classConf['getPropertyConf']($property);

        return $classConf['issetImpl']($this, $property, $propertyConf);
    }

    public function __unset(string $property): void
    {
        $classConf = Core::loadConfiguration(static::class);
        $propertyConf = $classConf['getPropertyConf']($property);

        $classConf['unsetImpl']($this, $property, $propertyConf);
    }
}

