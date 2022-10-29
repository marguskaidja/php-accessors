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
            && is_array($args[0] ?? null)
            && ($accessorMethodIsSetOrWith || 'unset' === $accessorMethod)) {

            if ($nArgs > 1) {
                throw new InvalidArgumentException(
                    sprintf(
                        'when first argument is Array then there can\'t be more arguments to method %s()',
                        $method
                    )
                );
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
            throw new BadMethodCallException(sprintf('unknown method %s()', $method));
        }

        $getPropertyConfFunc = null;

        // If accessorProperties are not set at this point (thus not specified using array
        // as first parameter to set or with), then extract them as separate arguments to current method
        if (0 === count($accessorProperties)) {
            if ('' === $propertyName) {
                if (!count($args)) {
                    throw new InvalidArgumentException(
                        sprintf('missing argument #1 (property name) to method %s()', $method)
                    );
                }

                $propertyName = array_shift($args);

                if (!is_string($propertyName)) {
                    throw new InvalidArgumentException(
                        sprintf('expecting string as argument #%u (property value) to method %s()', count($args) + 1, $method)
                    );
                }
            } else {
                // If we arrive here, then property name was specified inside current method name and
                // in this case we always interpret it as case-insensitive
                $getPropertyConfFunc = $classConf['getPropertyConfICase'];
            }

            if ($accessorMethodIsSetOrWith) {
                if (!count($args)) {
                    throw new InvalidArgumentException(
                        sprintf('missing argument #%u (property value) to method %s()', $nArgs + 1, $method)
                    );
                }

                $propertyValue = array_shift($args);
            }

            // Fail if there are more arguments specified than we are willing to process
            if (count($args)) {
                throw new InvalidArgumentException(
                    sprintf('expecting exactly %u argument(s) to method %s()', $nArgs - count($args), $method)
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
                    || ($immutable === false && 'with' === $accessorMethod)) {
                    throw new BadMethodCallException(
                        sprintf(
                            'property "%s" is %s, but method %s() is available only for %s properties (use %s::%s() instead)',
                            $propertyName,
                            ($immutable ? 'immutable' : 'mutable'),
                            $method,
                            ($immutable ? 'mutable' : 'immutable'),
                            static::class,
                            ($immutable ? 'with' : 'set')
                        )
                    );
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

        $classConf['setImpl']($this, 'set', $property, $value, $propertyConf);
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

