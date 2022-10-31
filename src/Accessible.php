<?php

/**
 * This file is part of the GetSet package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/marguskaidja/php-getset
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\Accessors;

use margusk\Accessors\Exception\BadMethodCallException;
use margusk\Accessors\Exception\InvalidArgumentException;
use ReflectionException;

trait Accessible
{
    /**
     * @param  string  $method
     * @param  array   $args
     *
     * @return mixed
     * @throws ReflectionException
     */
    public function __call(string $method, array $args): mixed
    {
        $classConf = Configuration::load(static::class);

        $lcaseMethod = strtolower($method);

        // Try to extract accessor method from magic method name
        if (!in_array(($accessorMethod = substr($lcaseMethod, 0, 3)), ['get', 'set'])
            && 'with' !== ($accessorMethod = substr($lcaseMethod, 0, 4))
            && !in_array(($accessorMethod = substr($lcaseMethod, 0, 5)), ['unset', 'isset'])
        ) {
            $accessorMethod = null;
        }

        $nArgs = count($args);
        $propertyName = substr($method, strlen((string)$accessorMethod));
        $propertyValue = null;
        $accessorProperties = [];
        $accessorMethodIsSetOrWith = in_array($accessorMethod, ['set', 'with']);

        // Check if the call is multi-property accessor, that is if first
        // argument is array and accessor method is set at this point and is "set", "with" or "unset"
        if ('' === $propertyName
            && $nArgs > 0
            && is_array(current($args))
            && ($accessorMethodIsSetOrWith || 'unset' === $accessorMethod)
        ) {
            if ($nArgs > 1) {
                throw InvalidArgumentException::dueMultiPropertyAccessorCanHaveExactlyOneArgument($method);
            }

            $accessorProperties = array_shift($args);

            // Check if whole method name is property name like
            //  $obj->somePropertyName('somevalue')
        } elseif (null === $accessorMethod && isset($classConf['byLCase'][strtolower($propertyName)])) {
            // If there are zero arguments, then interpret the call as Getter
            // If there are arguments, then it's Setter
            if ($nArgs > 0) {
                $accessorMethodIsSetOrWith = true;
                $accessorMethod = 'set';
            } else {
                $accessorMethod = 'get';
            }
        }

        // Accessor method must be resolved at this point, or we fail
        if (null === $accessorMethod) {
            throw BadMethodCallException::dueUnknownAccessorMethod($method);
        }

        $getPropertyConfFunc = null;

        // If accessorProperties are not set at this point (thus not specified using array
        // as first parameter to set or with), then extract them as separate arguments to current method
        if (0 === count($accessorProperties)) {
            if ('' === $propertyName) {
                if (!count($args)) {
                    throw InvalidArgumentException::dueMethodIsMissingPropertyNameArgument($method);
                }

                $propertyName = array_shift($args);

                if (!is_string($propertyName)) {
                    throw InvalidArgumentException::duePropertyNameArgumentMustBeString(
                        $method,
                        count($args) + 1
                    );
                }
            } else {
                // If we arrive here, then property name was specified inside current method name and
                // in this case we always interpret it as case-insensitive
                $getPropertyConfFunc = $classConf['getPropertyConfICase'];
            }

            if ($accessorMethodIsSetOrWith) {
                if (!count($args)) {
                    throw InvalidArgumentException::dueMethodIsMissingPropertyValueArgument(
                        $method,
                        $nArgs + 1
                    );
                }

                $propertyValue = array_shift($args);
            }

            // Fail if there are more arguments specified than we are willing to process
            if (count($args)) {
                throw InvalidArgumentException::dueMethodHasMoreArgumentsThanExpected(
                    $method,
                    $nArgs - count($args)
                );
            }

            if ($accessorMethodIsSetOrWith) {
                $accessorProperties[$propertyName] = $propertyValue;
            } else {
                $accessorProperties[] = $propertyName;
            }
        }

        if (null === $getPropertyConfFunc) {
            $getPropertyConfFunc = $classConf['getPropertyConf'];
        }

        $result = $this;

        // Call Set or With
        if ($accessorMethodIsSetOrWith) {
            if ('with' === $accessorMethod) {
                $result = clone $result;
            }

            foreach ($accessorProperties as $propertyName => $propertyValue) {
                $propertyName = (string)$propertyName;
                $propertyConf = $getPropertyConfFunc($propertyName);
                $immutable = $propertyConf['immutable'] ?? null;

                // Check if mutable/immutable property was called using correct method:
                //  - mutable properties must be accessed using "set"
                //  - immutable properties must be accessed using "with"
                if (($immutable === true && 'set' === $accessorMethod)
                    || ($immutable === false && 'with' === $accessorMethod)
                ) {
                    if ($immutable) {
                        throw BadMethodCallException::dueImmutablePropertiesMustBeCalledUsingWith($propertyName);
                    } else {
                        throw BadMethodCallException::dueMutablePropertiesMustBeCalledUsingSet($propertyName);
                    }
                }

                $result = $classConf['setImpl']($result, $accessorMethod, $propertyName, $propertyValue, $propertyConf);
            }
        } else {
            foreach ($accessorProperties as $propertyName) {
                $propertyName = (string)$propertyName;
                $propertyConf = $getPropertyConfFunc($propertyName);
                $result = $classConf[$accessorMethod.'Impl']($result, $propertyName, $propertyConf);
            }
        }

        return $result;
    }

    /**
     * @param  string  $property
     *
     * @return mixed
     * @throws ReflectionException
     */
    public function __get(string $property): mixed
    {
        $classConf = Configuration::load(static::class);
        $propertyConf = $classConf['getPropertyConf']($property);

        return $classConf['getImpl']($this, $property, $propertyConf);
    }

    /**
     * @param  string  $property
     * @param  mixed   $value
     *
     * @return void
     * @throws ReflectionException
     */
    public function __set(string $property, mixed $value): void
    {
        $classConf = Configuration::load(static::class);
        $propertyConf = $classConf['getPropertyConf']($property);
        $immutable = $propertyConf['immutable'] ?? false;

        if ($immutable) {
            throw BadMethodCallException::dueImmutablePropertiesCantBeSetUsingAssignmentOperator($property);
        }

        $classConf['setImpl']($this, 'set', $property, $value, $propertyConf);
    }

    /**
     * @param  string  $property
     *
     * @return bool
     * @throws ReflectionException
     */
    public function __isset(string $property): bool
    {
        $classConf = Configuration::load(static::class);
        $propertyConf = $classConf['getPropertyConf']($property);

        return $classConf['issetImpl']($this, $property, $propertyConf);
    }

    /**
     * @param  string  $property
     *
     * @return void
     * @throws ReflectionException
     */
    public function __unset(string $property): void
    {
        $classConf = Configuration::load(static::class);
        $propertyConf = $classConf['getPropertyConf']($property);

        $classConf['unsetImpl']($this, $property, $propertyConf);
    }
}
