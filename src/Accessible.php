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

use margusk\Accessors\Exception\BadMethodCallException;
use margusk\Accessors\Exception\InvalidArgumentException;
use ReflectionException;

trait Accessible
{
    /**
     * @param  string  $method
     * @param  mixed[] $args
     *
     * @return mixed
     * @throws ReflectionException
     */
    public function __call(string $method, array $args): mixed
    {
        $classConf = ClassConf::factory(static::class);

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
        $propertiesList = [];
        $accessorMethodIsSetOrWith = in_array($accessorMethod, ['set', 'with']);

        // Check if the call is multi-property accessor, that is if first
        // argument is array and accessor method is set at this point and is "set", "with" or "unset"
        if ('' === $propertyName
            && $nArgs > 0
            && is_array(current($args))
            && ($accessorMethodIsSetOrWith || 'unset' === $accessorMethod)
        ) {
            if ($nArgs > 1) {
                throw InvalidArgumentException::dueMultiPropertyAccessorCanHaveExactlyOneArgument(
                    static::class,
                    $method
                );
            }

            /** @var mixed[] $propertiesList */
            $propertiesList = array_shift($args);

            // Check if whole method name is property name like
            //  $obj->somePropertyName('somevalue')
        } elseif (null === $accessorMethod && null !== $classConf->findPropertyConf($propertyName, true)) {
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
            throw BadMethodCallException::dueUnknownAccessorMethod(static::class, $method);
        }

        $forcePropertyNameToCaseInsensitive = false;

        // If accessorProperties are not set at this point (thus not specified using array
        // as first parameter to set or with), then extract them as separate arguments to current method
        if (0 === count($propertiesList)) {
            if ('' === $propertyName) {
                if (!count($args)) {
                    throw InvalidArgumentException::dueMethodIsMissingPropertyNameArgument(static::class, $method);
                }

                $propertyName = array_shift($args);

                if (!is_string($propertyName)) {
                    throw InvalidArgumentException::duePropertyNameArgumentMustBeString(
                        static::class,
                        $method,
                        count($args) + 1
                    );
                }

                /** @var string $propertyName */
            } else {
                // If we arrive here, then property name was specified partially or fully in method name and
                // in this case we always interpret it as case-insensitive
                $forcePropertyNameToCaseInsensitive = true;
            }

            if ($accessorMethodIsSetOrWith) {
                if (!count($args)) {
                    throw InvalidArgumentException::dueMethodIsMissingPropertyValueArgument(
                        static::class,
                        $method,
                        $nArgs + 1
                    );
                }

                $propertyValue = array_shift($args);
            }

            // Fail if there are more arguments specified than we are willing to process
            if (count($args)) {
                throw InvalidArgumentException::dueMethodHasMoreArgumentsThanExpected(
                    static::class,
                    $method,
                    $nArgs - count($args)
                );
            }

            if ($accessorMethodIsSetOrWith) {
                $propertiesList[$propertyName] = $propertyValue;
            } else {
                $propertiesList[] = $propertyName;
            }
        }

        $result = $this;

        // Call Set or With
        if ($accessorMethodIsSetOrWith) {
            if ('with' === $accessorMethod) {
                $result = clone $result;
            }

            $accessorImpl = $classConf->getSetter();

            foreach ($propertiesList as $propertyName => $propertyValue) {
                if (!is_string($propertyName)) {
                    throw InvalidArgumentException::dueMultiPropertyArrayContainsNonStringProperty(
                        static::class,
                        $method,
                        $propertyName
                    );
                }

                $propertyConf = $classConf->findPropertyConf($propertyName, $forcePropertyNameToCaseInsensitive);
                $immutable = ($propertyConf?->isImmutable()) ?? false;

                // Check if mutable/immutable property was called using correct method:
                //  - mutable properties must be accessed using "set"
                //  - immutable properties must be accessed using "with"
                if (($immutable === true && 'set' === $accessorMethod)
                    || ($immutable === false && 'with' === $accessorMethod)
                ) {
                    if ($immutable) {
                        throw BadMethodCallException::dueImmutablePropertiesMustBeCalledUsingWith(
                            static::class,
                            $propertyName
                        );
                    } else {
                        throw BadMethodCallException::dueMutablePropertiesMustBeCalledUsingSet(
                            static::class,
                            $propertyName
                        );
                    }
                }

                $result = $accessorImpl($result, $accessorMethod, $propertyName, $propertyValue, $propertyConf);
            }
        } else {
            /** @var 'get'|'isset'|'unset' $accessorMethod */
            $accessorImpl = match($accessorMethod) {
                'get'   => $classConf->getGetter(),
                'isset' => $classConf->getIsSetter(),
                'unset' => $classConf->getUnSetter()
            };

            foreach ($propertiesList as $propertyName) {
                if (!is_string($propertyName)) {
                    throw InvalidArgumentException::dueMultiPropertyArrayContainsNonStringProperty(
                        static::class,
                        $method,
                        $propertyName
                    );
                }

                $propertyConf = $classConf->findPropertyConf($propertyName, $forcePropertyNameToCaseInsensitive);
                $result = $accessorImpl($result, $propertyName, $propertyConf);
            }
        }

        return $result;
    }

    /**
     * @param  string  $propertyName
     *
     * @return mixed
     * @throws ReflectionException
     */
    public function __get(string $propertyName): mixed
    {
        $classConf = ClassConf::factory(static::class);
        $propertyConf = $classConf->findPropertyConf($propertyName);

        return ($classConf->getGetter())($this, $propertyName, $propertyConf);
    }

    /**
     * @param  string  $propertyName
     * @param  mixed   $propertyValue
     *
     * @return void
     * @throws ReflectionException
     */
    public function __set(string $propertyName, mixed $propertyValue): void
    {
        $classConf = ClassConf::factory(static::class);
        $propertyConf = $classConf->findPropertyConf($propertyName);
        $immutable = $propertyConf?->isImmutable();

        if ($immutable) {
            throw BadMethodCallException::dueImmutablePropertiesCantBeSetUsingAssignmentOperator(
                static::class,
                $propertyName
            );
        }

        ($classConf->getSetter())($this, 'set', $propertyName, $propertyValue, $propertyConf);
    }

    /**
     * @param  string  $propertyName
     *
     * @return bool
     * @throws ReflectionException
     */
    public function __isset(string $propertyName): bool
    {
        $classConf = ClassConf::factory(static::class);
        $propertyConf = $classConf->findPropertyConf($propertyName);

        return ($classConf->getIsSetter())($this, $propertyName, $propertyConf);
    }

    /**
     * @param  string  $propertyName
     *
     * @return void
     * @throws ReflectionException
     */
    public function __unset(string $propertyName): void
    {
        $classConf = ClassConf::factory(static::class);
        $propertyConf = $classConf->findPropertyConf($propertyName);

        ($classConf->getUnSetter())($this, $propertyName, $propertyConf);
    }
}
