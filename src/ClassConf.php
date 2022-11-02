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
use margusk\Accessors\Attr\Delete;
use margusk\Accessors\Attr\Get;
use margusk\Accessors\Attr\ICase;
use margusk\Accessors\Attr\Immutable;
use margusk\Accessors\Attr\Mutator;
use margusk\Accessors\Attr\Set;
use margusk\Accessors\Exception\InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionException;

final class ClassConf
{
    /** @var self[] */
    private static array $classes = [];

    /** @var array<string, Attributes> */
    private static array $attributes = [];

    /** @var ReflectionClass<object> */
    private ReflectionClass $rfClass;

    /** @var PropertyConf[] */
    private array $properties = [];

    /** @var PropertyConf[] */
    private array $propertiesByLcase = [];

    private Closure $getter;

    private Closure $setter;

    private Closure $unSetter;

    private Closure $isSetter;

    /**
     * @param  class-string  $name
     *
     * @throws ReflectionException
     */
    private function __construct(
        protected string $name
    )
    {
        $this->rfClass = new ReflectionClass($this->name);

        $this->getter = $this->createGetter();
        $this->setter = $this->createSetter();
        $this->isSetter = $this->createIssetter();
        $this->unSetter = $this->createUnsetter();

        // Parse attributes of current class and all it's ancestors
        if (!isset(self::$attributes[$this->name])) {
            /** @var class-string[] $classHierarchy */
            $classHierarchy = array_reverse([
                $this->name,
                ...(array)class_parents($this->name)
            ]);

            $prevAttributes = null;

            foreach ($classHierarchy as $name) {
                if (!isset(self::$attributes[$name])) {
                    $rf = ($name === $this->name) ? $this->rfClass :  new ReflectionClass($name);

                    $attributes = new Attributes($rf);

                    if (null !== $prevAttributes) {
                        $attributes = $attributes->mergeParent($prevAttributes);
                    }

                    self::$attributes[$name] = $attributes;
                }

                $prevAttributes = self::$attributes[$name];
            }
        }

        /** @var array<string, array<string, string>> $handlerMethodNames */
        $handlerMethodNames = [];

        foreach ($this->rfClass->getMethods(ReflectionMethod::IS_PUBLIC) as $rfMethod) {
            if (!$rfMethod->isStatic()
                && preg_match(
                    '/^(set|get|isset|unset|with)(.+)/',
                    strtolower($rfMethod->name),
                    $matches
                )
            ) {
                $handlerMethodNames[(string) $matches[2]][(string) $matches[1]] = $rfMethod->name;
            }
        }

        // Parse attributes of each property
        foreach ($this->rfClass->getProperties(ReflectionProperty::IS_PROTECTED) as $rfProperty) {
            $name = $rfProperty->getName();
            $nameLowerCase = strtolower($name);

            $this->properties[$name] = new PropertyConf(
                $rfProperty,
                self::$attributes[$this->name],
                ($handlerMethodNames[$nameLowerCase] ?? [])
            );

            $this->propertiesByLcase[$nameLowerCase] = $this->properties[$name];
        }
    }

    public function findPropertyConf(string $name, bool $forceCaseInsensitive = false): ?PropertyConf
    {
        if ($forceCaseInsensitive) {
            $caseInsensitive = true;
        } else {
            $caseInsensitive = self::$attributes[$this->name]
                ->get(ICase::class)
                ->enabled();
        }

        if ($caseInsensitive) {
            $propertyConf = $this->propertiesByLcase[strtolower($name)] ?? null;
        } else {
            $propertyConf = $this->properties[$name] ?? null;
        }

        return $propertyConf;
    }

    /**
     * Parses configuration of specified class.
     *
     * Configuration is cached so later requests for same class are returned instantly.
     *
     * @param  class-string  $name Class name to create configuration for
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

    private function createGetter(): Closure
    {
        return (function (object $object, string $name, ?PropertyConf $propertyConf): mixed {
            if (null === $propertyConf) {
                throw InvalidArgumentException::dueTriedToGetUnknownProperty(self::class, $name);
            }

            if (false === $propertyConf->isGettable()) {
                throw InvalidArgumentException::dueTriedToGetMisconfiguredProperty(self::class, $name);
            }

            $handler = $propertyConf->handlerMethodName('get');

            if (null !== $handler) {
                return $object->{$handler}();
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
            ?PropertyConf $propertyConf
        ): object {
            if (null === $propertyConf) {
                throw InvalidArgumentException::dueTriedToSetUnknownProperty(self::class, $name);
            }

            if (false === $propertyConf->isSettable()) {
                throw InvalidArgumentException::dueTriedToSetMisconfiguredProperty(self::class, $name);
            }

            $handler = $propertyConf->handlerMethodName($accessorMethod);

            if (null !== $handler) {
                $result = $object->{$handler}($value);

                if ('with' === $accessorMethod && ($result instanceof (self::class))
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

    private function createUnsetter(): Closure
    {
        return (function (object $object, string $name, ?PropertyConf $propertyConf): object {
            if (null === $propertyConf) {
                throw InvalidArgumentException::dueTriedToUnsetUnknownProperty(self::class, $name);
            }

            if (false === $propertyConf->isUnsettable()) {
                throw InvalidArgumentException::dueTriedToUnsetMisconfiguredProperty(self::class, $name);
            }

            if (true === $propertyConf->isImmutable()) {
                throw InvalidArgumentException::dueImmutablePropertyCantBeUnset(
                    self::class,
                    $name
                );
            }

            $handler = $propertyConf->handlerMethodName('unset');

            if (null !== $handler) {
                $object->{$handler}();
            } else {
                unset($object->{$propertyConf->name()});
            }

            return $object;
        })->bindTo(null, $this->name);
    }

    private function createIssetter(): Closure
    {
        return (function (object $object, string $name, ?PropertyConf $propertyConf): bool {
            if (null === $propertyConf) {
                throw InvalidArgumentException::dueTriedToGetUnknownProperty(self::class, $name);
            }

            if (false === $propertyConf->isGettable()) {
                throw InvalidArgumentException::dueTriedToGetMisconfiguredProperty(self::class, $name);
            }

            $handler = $propertyConf->handlerMethodName('isset');

            if (null !== $handler) {
                return (bool)$object->{$handler}();
            }

            return isset($object->{$propertyConf->name()});
        })->bindTo(null, $this->name);
    }
}
