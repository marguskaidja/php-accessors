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
use margusk\Accessors\Accessible\WithPHPDocs as AccessibleWithPHPDocs;
use margusk\Accessors\Attr\{Immutable, Format};
use margusk\Accessors\Exception\BadMethodCallException;
use margusk\Accessors\Exception\InvalidArgumentException;
use margusk\Accessors\Format\Method;
use margusk\Accessors\Format\Standard;
use ReflectionClass;
use ReflectionException;

use function array_shift;
use function call_user_func;
use function count;
use function current;
use function get_class;
use function get_parent_class;
use function in_array;
use function is_string;

final class ClassConf
{
    /** @var self[] */
    private static array $classes = [];

    /** @var ClassConf|null */
    private ?ClassConf $parent;

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

    /** @var bool */
    private bool $isAccessible;

    /** @var bool */
    private bool $withPHPDocs;

    /**
     * @param  class-string  $name
     *
     * @throws ReflectionException
     */
    private function __construct(
        protected string $name
    ) {
        /* Verify that the specified class is valid */
        $rfClass = new ReflectionClass($this->name);

        /* Require parent class to be initialized before current */
        $parentName = get_parent_class($this->name);

        $this->parent = (
        false !== $parentName
            ?
            self::findConf($parentName)
            :
            null
        );

        /* Check if parent class or current contains 'Accessible' trait */
        if ($this->parent && $this->parent->isAccessible) {
            $isParentAccessible = true;
            $this->isAccessible = true;
            $this->withPHPDocs = $this->parent->withPHPDocs;
        } else {
            $isParentAccessible = false;
            list($this->isAccessible, $this->withPHPDocs) = $this->isClassUsingAccessibleTrait($rfClass);
        }

        /* Don't parse anything unless current class uses Accessible trait */
        if ($this->isAccessible) {
            /* Parse attributes of current class */
            $this->attributes = Attributes::fromReflection($rfClass);

            if ($isParentAccessible) {
                /** @var ClassConf $parent */
                $parent = $this->parent;

                /* Verify that child doesn't declare #[Immutable] and #[Format] */
                foreach ([Immutable::class, Format::class] as $n) {
                    if (null !== $this->attributes->get($n)) {
                        /* Find out the top of hierarchy */
                        while ($parent->parent && $parent->parent->isAccessible) {
                            $parent = $parent->parent;
                        }

                        throw InvalidArgumentException::dueClassAttributeCanOccurOnceOnTopOfHierarchy(
                            $parent->name,
                            $this->name,
                            $n
                        );
                    }
                }

                $this->attributes = $this->attributes->mergeWithParent($parent->attributes);
                $parentProperties = $parent->properties;
            } else {
                /**
                 * This is the top of the hierachy using "Accessible" trait, assign default instance
                 * for #[Format] attribute if it's not custom defined.
                 */
                $this->attributes->setIfNull(
                    Format::class,
                    new Format(Standard::class)
                );

                $parentProperties = null;
            }

            $this->properties = Properties::fromReflection(
                $rfClass,
                $this->attributes,
                $parentProperties,
                $this->withPHPDocs
            );

            $this->getter = $this->createGetter();
            $this->setter = $this->createSetter();
            $this->isSetter = $this->createIssetter();
            $this->unSetter = $this->createUnsetter();
        } else {
            /* Attributes in non-Accessible classes have no effect */
            $this->attributes = new Attributes();
            $this->properties = new Properties();

            $notAccessible = function () {
                throw BadMethodCallException::dueClassNotUsingAccessibleTrait($this->name);
            };

            $this->getter = $notAccessible;
            $this->setter = $notAccessible;
            $this->isSetter = $notAccessible;
            $this->unSetter = $notAccessible;
        }
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
    private static function findConf(string $name): ClassConf
    {
        if (!isset(self::$classes[$name])) {
            self::$classes[$name] = new self($name);
        }

        return self::$classes[$name];
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

            $endpoint = $propertyConf->accessorEndpoint(Method::TYPE_GET);

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
                    Method::TYPE_WITH === $accessorMethod
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

            $endpoint = $propertyConf->accessorEndpoint(Method::TYPE_ISSET);

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

            $endpoint = $propertyConf->accessorEndpoint(Method::TYPE_UNSET);

            if (null !== $endpoint) {
                $object->{$endpoint}();
            } else {
                unset($object->{$propertyConf->name()});
            }

            return $object;
        })->bindTo(null, $this->name);
    }

    /**
     * @param  ReflectionClass<object>  $rfClass
     *
     * @return array{0: bool, 1: bool}
     */
    private function isClassUsingAccessibleTrait(ReflectionClass $rfClass): array
    {
        foreach ($rfClass->getTraits() as $n => $trait) {
            if ($n === AccessibleWithPHPDocs::class) {
                return [true, true];
            }

            if ($n === Accessible::class) {
                return [true, false];
            }

            $retVal = $this->isClassUsingAccessibleTrait($trait);

            if (true === $retVal[0]) {
                return $retVal;
            }
        }

        return [false, false];
    }

    /**
     * @param  string   $method
     * @param  mixed[]  $args
     * @param  object   $object
     *
     * @return mixed
     */
    public static function handleMagicCall(object $object, string $method, array $args): mixed
    {
        $classConf = self::findConf(get_class($object));

        /** @var Format $attr */
        $attr = $classConf->attributes->get(Format::class);
        $format = $attr->instance();

        if (null !== ($parsedMethod = $format->matchCalled($method))) {
            $accessorMethod = $parsedMethod->type();
            $propertyName = $parsedMethod->propertyName();
        } else {
            $accessorMethod = null;
            $propertyName = $method;
        }

        $nArgs = count($args);
        $propertyValue = null;
        $propertiesList = [];
        $accessorMethodIsSetOrWith = in_array(
            $accessorMethod,
            [Method::TYPE_SET, Method::TYPE_WITH],
            true
        );

        // Check if the call is multi-property accessor, that is if first
        // argument is array and accessor method is set at this point and is "set", "with" or "unset"
        if (
            '' === $propertyName
            && $nArgs > 0
            && is_array(current($args))
            && ($accessorMethodIsSetOrWith || Method::TYPE_UNSET === $accessorMethod)
        ) {
            if ($nArgs > 1) {
                throw InvalidArgumentException::dueMultiPropertyAccessorCanHaveExactlyOneArgument(
                    $classConf->name,
                    $method
                );
            }

            /** @var mixed[] $propertiesList */
            $propertiesList = array_shift($args);
        } elseif (
            // Check if whole method name is property name like $obj->somePropertyName('somevalue')
            null === $accessorMethod
            && null !== $classConf->properties->findConf($propertyName, true)
            && $format->allowPropertyNameOnly()
        ) {
            // If there are zero arguments, then interpret the call as Getter
            // If there are arguments, then it's Setter
            if ($nArgs > 0) {
                $accessorMethodIsSetOrWith = true;
                $accessorMethod = Method::TYPE_SET;
            } else {
                $accessorMethod = Method::TYPE_GET;
            }
        }

        // Accessor method must be resolved at this point, or we fail
        if (null === $accessorMethod) {
            throw BadMethodCallException::dueUnknownAccessorMethod($classConf->name, $method);
        }

        $propertyNameCI = false;

        // If accessorProperties are not set at this point (thus not specified using array
        // as first parameter to set or with), then extract them as separate arguments to current method
        if (0 === count($propertiesList)) {
            if ('' === $propertyName) {
                if (!count($args)) {
                    throw InvalidArgumentException::dueMethodIsMissingPropertyNameArgument($classConf->name, $method);
                }

                $propertyName = array_shift($args);

                if (!is_string($propertyName)) {
                    throw InvalidArgumentException::duePropertyNameArgumentMustBeString(
                        $classConf->name,
                        $method,
                        count($args) + 1
                    );
                }
                /** @var string $propertyName */
            } else {
                // If we arrive here, then property name was specified partially or fully in method name and
                // in this case we always interpret it as case-insensitive
                $propertyNameCI = true;
            }

            if ($accessorMethodIsSetOrWith) {
                if (!count($args)) {
                    throw InvalidArgumentException::dueMethodIsMissingPropertyValueArgument(
                        $classConf->name,
                        $method,
                        $nArgs + 1
                    );
                }

                $propertyValue = array_shift($args);
            }

            // Fail if there are more arguments specified than we are willing to process
            if (count($args)) {
                throw InvalidArgumentException::dueMethodHasMoreArgumentsThanExpected(
                    $classConf->name,
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

        $result = $object;

        // Call Set or With
        if ($accessorMethodIsSetOrWith) {
            if (Method::TYPE_WITH === $accessorMethod) {
                $result = clone $result;
            }

            $accessorImpl = $classConf->setter;

            foreach ($propertiesList as $propertyName => $propertyValue) {
                if (!is_string($propertyName)) {
                    throw InvalidArgumentException::dueMultiPropertyArrayContainsNonStringProperty(
                        $classConf->name,
                        $method,
                        $propertyName
                    );
                }

                $propertyConf = $classConf->properties->findConf($propertyName, $propertyNameCI);
                $immutable = ($propertyConf?->isImmutable()) ?? false;

                // Check if mutable/immutable property was called using correct method:
                //  - mutable properties must be accessed using "set"
                //  - immutable properties must be accessed using "with"
                if (
                    ($immutable === true && Method::TYPE_SET === $accessorMethod)
                    || ($immutable === false && Method::TYPE_WITH === $accessorMethod)
                ) {
                    if ($immutable) {
                        throw BadMethodCallException::dueImmutablePropertiesMustBeCalledUsingWith(
                            $classConf->name,
                            $propertyName
                        );
                    } else {
                        throw BadMethodCallException::dueMutablePropertiesMustBeCalledUsingSet(
                            $classConf->name,
                            $propertyName
                        );
                    }
                }

                $result = $accessorImpl($result, $accessorMethod, $propertyName, $propertyValue, $propertyConf);
            }
        } else {
            /** @var 'get'|'isset'|'unset' $accessorMethod */
            $accessorImpl = match ($accessorMethod) {
                Method::TYPE_GET => $classConf->getter,
                Method::TYPE_ISSET => $classConf->isSetter,
                Method::TYPE_UNSET => $classConf->unSetter
            };

            foreach ($propertiesList as $propertyName) {
                if (!is_string($propertyName)) {
                    throw InvalidArgumentException::dueMultiPropertyArrayContainsNonStringProperty(
                        $classConf->name,
                        $method,
                        $propertyName
                    );
                }

                $propertyConf = $classConf->properties->findConf($propertyName, $propertyNameCI);
                $result = $accessorImpl($result, $propertyName, $propertyConf);
            }
        }

        return $result;
    }

    public static function handleMagicGet(object $object, string $propertyName): mixed
    {
        $classConf = self::findConf(get_class($object));

        return ($classConf->getter)(
            $object,
            $propertyName,
            $classConf->properties->findConf($propertyName)
        );
    }

    public static function handleMagicIsset(object $object, string $propertyName): bool
    {
        $classConf = self::findConf(get_class($object));

        return ($classConf->isSetter)(
            $object,
            $propertyName,
            $classConf->properties->findConf($propertyName)
        );
    }

    public static function handleMagicSet(object $object, string $propertyName, mixed $propertyValue): void
    {
        $classConf = self::findConf(get_class($object));
        $propertyConf = $classConf->properties->findConf($propertyName);
        $immutable = $propertyConf?->isImmutable();

        if ($immutable) {
            throw BadMethodCallException::dueImmutablePropertiesCantBeSetUsingAssignmentOperator(
                $classConf->name,
                $propertyName
            );
        }

        ($classConf->setter)(
            $object,
            'set',
            $propertyName,
            $propertyValue,
            $propertyConf
        );
    }

    public static function handleMagicUnset(object $object, string $propertyName): void
    {
        $classConf = self::findConf(get_class($object));

        ($classConf->unSetter)(
            $object,
            $propertyName,
            $classConf->properties->findConf($propertyName)
        );
    }
}
