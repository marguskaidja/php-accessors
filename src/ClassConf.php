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

use Closure;
use margusk\Accessors\Exception\InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

use function call_user_func;
use function get_parent_class;

final class ClassConf
{
    /** @var self[] */
    private static array $classes = [];

    /** @var Attributes */
    private Attributes $attributes;

    /** @var Properties */
    private Properties $properties;

    /** @var Closure */
    private Closure $getter;

    /** @var Closure */
    private Closure $setter;

    /** @var Closure */
    private Closure $unSetter;

    /** @var Closure */
    private Closure $isSetter;

    /**
     * @param  class-string  $name
     *
     * @throws ReflectionException
     */
    private function __construct(
        protected string $name
    ) {
        /* Verify that the specified class is valid in every aspect */
        $rfClass = new ReflectionClass($this->name);

        /* First parse attributes of current class */
        $this->attributes = Attributes::fromReflection($rfClass);

        /* Next require parent class to be initialized before current, ... */
        $parentName = get_parent_class($this->name);

        /* ...because attributes from parent class need to be merged into current */
        if (false !== $parentName) {
            $parent = self::factory($parentName);
            $this->attributes = $this->attributes->mergeWithParent($parent->attributes);
        }

        $this->properties = new Properties($rfClass, $this->attributes);

        $this->getter = $this->createGetter();
        $this->setter = $this->createSetter();
        $this->isSetter = $this->createIssetter();
        $this->unSetter = $this->createUnsetter();
    }

    private function createGetter(): Closure
    {
        return (function (object $object, string $name, ?Property $propertyConf): mixed {
            if (null === $propertyConf) {
                throw InvalidArgumentException::dueTriedToGetUnknownProperty(self::class, $name);
            }

            if (false === $propertyConf->isGettable()) {
                if ($propertyConf->isPublic()) {
                    throw InvalidArgumentException::dueTriedToGetPublicProperty(self::class, $name);
                }

                throw InvalidArgumentException::dueTriedToGetMisconfiguredProperty(self::class, $name);
            }

            $endpoint = $propertyConf->accessorEndpoint('get');

            if (null !== $endpoint) {
                return $object->{$endpoint}();
            }

            return $object->{$propertyConf->name()};
        })->bindTo(null, $this->name);
    }

    private function createSetter(): Closure
    {
        return (function (
            object $object,
            string $accessorMethod,
            string $name,
            mixed $value,
            ?Property $propertyConf
        ): object {
            if (null === $propertyConf) {
                throw InvalidArgumentException::dueTriedToSetUnknownProperty(self::class, $name);
            }

            if (false === $propertyConf->isSettable()) {
                if ($propertyConf->isPublic()) {
                    throw InvalidArgumentException::dueTriedToSetPublicProperty(self::class, $name);
                }

                throw InvalidArgumentException::dueTriedToSetMisconfiguredProperty(self::class, $name);
            }

            $endpoint = $propertyConf->accessorEndpoint($accessorMethod);

            if (null !== $endpoint) {
                $result = $object->{$endpoint}($value);

                if (
                    'with' === $accessorMethod
                    && ($result instanceof (self::class))
                ) {
                    $object = $result;
                }
            } else {
                $mutatorCb = $propertyConf->mutator($object);

                if (null !== $mutatorCb) {
                    $value = call_user_func($mutatorCb, $value);
                }

                $object->{$propertyConf->name()} = $value;
            }

            return $object;
        })->bindTo(null, $this->name);
    }

    private function createIssetter(): Closure
    {
        return (function (object $object, string $name, ?Property $propertyConf): bool {
            if (null === $propertyConf) {
                throw InvalidArgumentException::dueTriedToGetUnknownProperty(self::class, $name);
            }

            if (false === $propertyConf->isGettable()) {
                if ($propertyConf->isPublic()) {
                    throw InvalidArgumentException::dueTriedToGetPublicProperty(self::class, $name);
                }

                throw InvalidArgumentException::dueTriedToGetMisconfiguredProperty(self::class, $name);
            }

            $endpoint = $propertyConf->accessorEndpoint('isset');

            if (null !== $endpoint) {
                return (bool)$object->{$endpoint}();
            }

            return isset($object->{$propertyConf->name()});
        })->bindTo(null, $this->name);
    }

    private function createUnsetter(): Closure
    {
        return (function (object $object, string $name, ?Property $propertyConf): object {
            if (null === $propertyConf) {
                throw InvalidArgumentException::dueTriedToUnsetUnknownProperty(self::class, $name);
            }

            if (false === $propertyConf->isUnsettable()) {
                if ($propertyConf->isPublic()) {
                    throw InvalidArgumentException::dueTriedToUnsetPublicProperty(self::class, $name);
                }

                throw InvalidArgumentException::dueTriedToUnsetMisconfiguredProperty(self::class, $name);
            }

            if (true === $propertyConf->isImmutable()) {
                throw InvalidArgumentException::dueImmutablePropertyCantBeUnset(
                    self::class,
                    $name
                );
            }

            $endpoint = $propertyConf->accessorEndpoint('unset');

            if (null !== $endpoint) {
                $object->{$endpoint}();
            } else {
                unset($object->{$propertyConf->name()});
            }

            return $object;
        })->bindTo(null, $this->name);
    }

    /**
     * Parses configuration of specified class.
     *
     * Configuration is cached so later requests for same class are returned instantly.
     *
     * @param  class-string  $name  Class name to create configuration for
     *
     * @return ClassConf
     * @throws ReflectionException
     */
    public static function factory(string $name): ClassConf
    {
        if (!isset(self::$classes[$name])) {
            self::$classes[$name] = new self($name);
        }

        return self::$classes[$name];
    }

    public function properties(): Properties
    {
        return $this->properties;
    }

    public function getGetter(): Closure
    {
        return $this->getter;
    }

    public function getSetter(): Closure
    {
        return $this->setter;
    }

    public function getIsSetter(): Closure
    {
        return $this->isSetter;
    }

    public function getUnSetter(): Closure
    {
        return $this->unSetter;
    }
}
