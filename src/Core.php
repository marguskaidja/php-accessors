<?php

declare(strict_types=1);

namespace margusk\GetSet;

use Closure;
use margusk\GetSet\Attributes\Delete;
use margusk\GetSet\Attributes\Get;
use margusk\GetSet\Attributes\ICase;
use margusk\GetSet\Attributes\Immutable;
use margusk\GetSet\Attributes\Set;
use margusk\GetSet\Exceptions\InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use ReflectionMethod;

final class Core
{
    private static array $classesConf = [];

    private static array $propertiesConf = [];

    public static function loadConfiguration(string $curClassName): array
    {
        if (!isset(self::$propertiesConf[$curClassName])) {
            self::$propertiesConf[$curClassName] = [
                'byCase'    => [],
                'byLCase'   => [],
                'unsetImpl' => self::createUnsetImplementation($curClassName),
                'getImpl'   => self::createGetImplementation($curClassName),
                'issetImpl' => self::createIssetImplementation($curClassName),
            ];

            $parseAttributes = function (ReflectionClass|ReflectionProperty $reflection): array {
                $conf = [];
                foreach (['get', 'set', 'unset', 'mutator', 'ci', 'immutable'] as $f) {
                    $conf[$f] = [
                        'isset' => false,
                        'value' => null
                    ];
                }

                foreach ($reflection->getAttributes() as $reflectionAttribute) {
                    switch ($reflectionAttribute->getName()) {
                        case Get::class:
                            $enabled = $reflectionAttribute->newInstance()->enabled();
                            if (null !== $enabled) {
                                $conf['get'] = [
                                    'isset' => true,
                                    'value' => $enabled
                                ];
                            }
                            break;
                        case Set::class:
                            $inst = $reflectionAttribute->newInstance();
                            $enabled = $inst->enabled();
                            if (null !== $enabled) {
                                $conf['set'] = [
                                    'isset' => true,
                                    'value' => $enabled
                                ];
                            }
                            $mutator = $inst->mutator();
                            if (null !== $mutator) {
                                $conf['mutator'] = [
                                    'isset' => true,
                                    'value' => ("" !== $mutator ? $mutator : null)
                                ];
                            }
                            break;
                        case Delete::class:
                            $enabled = $reflectionAttribute->newInstance()->enabled();
                            if (null !== $enabled) {
                                $conf['unset'] = [
                                    'isset' => true,
                                    'value' => $enabled
                                ];
                            }
                            break;
                        case ICase::class:
                            $conf['ci'] = [
                                'isset' => true,
                                'value' => true
                            ];
                            break;
                        case Immutable::class:
                            $conf['immutable'] = [
                                'isset' => true,
                                'value' => true
                            ];
                            break;
                    }
                }

                return $conf;
            };

            $mergeAttributes = function (?array $parent, array $child): array {
                if (null !== $parent) {
                    foreach (['get', 'set', 'unset', 'mutator', 'ci' ,'immutable'] as $f) {
                        if (!$child[$f]['isset']) {
                            $child[$f] = $parent[$f];
                        }
                    }
                }

                return $child;
            };

            $classNames = array_reverse(array_merge([$curClassName], class_parents($curClassName)));
            $mergedClassAttr = null;

            foreach ($classNames as $className) {
                if (!isset(self::$classesConf[$className])) {
                    $classAttr = $parseAttributes(new ReflectionClass($className));

                    $mergedClassAttr = $mergeAttributes($mergedClassAttr, $classAttr);
                    self::$classesConf[$className] = $mergedClassAttr;
                } else {
                    $mergedClassAttr = self::$classesConf[$className];
                }
            }

            $reflectionClass = new ReflectionClass($curClassName);

            // Find all existing "set<Property>", "get<Property>", "isset<Property>" and "unset<Property>" methods for
            // cases where key is accessed/modified using magic setter methods. In this case we call the existing method.
            $existingMethods = [];
            foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
                /** @var $reflectionMethod ReflectionMethod */
                if (!$reflectionMethod->isStatic()
                    && preg_match('/^(set|get|isset|unset)(.+)/', strtolower($reflectionMethod->getName()), $matches)
                ) {
                    $existingMethods[$matches[2]][$matches[1]] = $reflectionMethod->getName();
                }
            }

            // Parse attributes of each property
            foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PROTECTED) as $reflectionProperty) {
                $property = $reflectionProperty->getName();
                $lcaseProperty = strtolower($property);

                $propertyConfRaw = $mergeAttributes(
                    self::$classesConf[$curClassName],
                    $parseAttributes($reflectionProperty)
                );

                $propertyConf = [];
                foreach ($propertyConfRaw as $f => $v) {
                    if ($v['isset']) {
                        $propertyConf[$f] = $v['value'];
                    } else {
                        $propertyConf[$f] = ('mutator' === $f ? null : false);
                    }
                }

                $propertyConf['existingMethods'] = $existingMethods[$lcaseProperty] ?? [];

                // Mutator methodname can be in format:
                //      "someFunction"
                //      "self::someMethod"
                //      "parent::someMethod"
                //      "static::someMethod"
                //
                // Name can also contain special variable '%property%' which is then replaced by the appropriate
                // property name. This is useful only when specifying mutator through class attributes.
                if (is_string($propertyConf['mutator'])) {
                    $propertyConf['mutator'] = str_replace('%property%', $property, $propertyConf['mutator']);
                    $splits = explode('::', $propertyConf['mutator'], 2);

                    if (2 === count($splits)) {
                        $propertyConf['mutator'] = $splits;
                    }
                }

                self::$propertiesConf[$curClassName]['byCase'][$property] = $propertyConf;
                self::$propertiesConf[$curClassName]['byLCase'][$lcaseProperty] = $property;
            }

            // Create closure for retrieving property conf by property name.
            // Case sensitivity setting is also taken in account.
            $getFunc = function (string & $property) use ($curClassName): ?array {
                return self::$propertiesConf[$curClassName]['byCase'][$property] ?? null;
            };

            if (self::$classesConf[$curClassName]['ci']['value']) {
                $getFunc = function (string & $property) use ($getFunc, $curClassName): ?array {
                    $origPropertyName = self::$propertiesConf[$curClassName]['byLCase'][strtolower($property)] ?? null;

                    if (!isset($origPropertyName)) {
                        return null;
                    }

                    $property = $origPropertyName;

                    return $getFunc($property);
                };
            }

            self::$propertiesConf[$curClassName]['getPropertyConf'] = $getFunc;
        }

        return self::$propertiesConf[$curClassName];
    }

    public static function createSetImplementation(& $saveClosureIntoVar, object $closureCtx): Closure
    {
        if (null === $saveClosureIntoVar) {
            $saveClosureIntoVar = (function (string $property, mixed $value, ?array $propertyConf): object {
                if (!$propertyConf) {
                    throw new InvalidArgumentException(sprintf('tried to set unknown property "%s"', $property));
                }

                if (!$propertyConf['set']) {
                    throw new InvalidArgumentException(
                        sprintf('tried to set private/protected property "%s" (missing #[Set] attribute?)', $property)
                    );
                }

                if ($propertyConf['immutable']) {
                    $object = clone $this;
                } else {
                    $object = $this;
                }

                if (!$propertyConf['immutable'] && isset($propertyConf['existingMethods']['set'])) {
                    $object->{$propertyConf['existingMethods']['set']}($value);
                } else {
                    if (null !== $propertyConf['mutator']) {
                        $value = call_user_func($propertyConf['mutator'], $value);
                    }

                    $object->{$property} = $value;
                }

                return $object;
            })->bindTo($closureCtx, $closureCtx);
        }

        return $saveClosureIntoVar;
    }

    protected static function createUnsetImplementation(string $curClassName): Closure
    {
        return (function (object $object, string $property, ?array $propertyConf) : object {
            if (!$propertyConf) {
                throw new InvalidArgumentException(sprintf('tried to unset unknown property "%s"', $property));
            }

            if (!$propertyConf['unset']) {
                throw new InvalidArgumentException(sprintf('tried to unset private/protected property "%s" (missing #[Delete] attribute?)', $property));
            }

            if ($propertyConf['immutable']) {
                $object = clone $object;
            }

            if (isset($propertyConf['existingMethods']['unset'])) {
                $object->{$propertyConf['existingMethods']['unset']}();
            } else {
                unset($object->{$property});
            }

            return $object;
        })->bindTo(null, $curClassName);
    }

    protected static function createGetImplementation(string $curClassName): Closure
    {
        return (function (object $object, string $property, ?array $propertyConf) : mixed {
            if (!isset($propertyConf)) {
                throw new InvalidArgumentException(sprintf('tried to read unknown property "%s"', $property));
            }

            if (!$propertyConf['get']) {
                throw new InvalidArgumentException(sprintf('tried to read private/protected property "%s"', $property));
            }

            if (isset($propertyConf['existingMethods']['get'])) {
                return $object->{$propertyConf['existingMethods']['get']}();
            }

            return $object->{$property};
        })->bindTo(null, $curClassName);
    }

    protected static function createIssetImplementation(string $curClassName): Closure
    {
        return (function (object $object, string $property, ?array $propertyConf) : bool {
            if (!isset($propertyConf)) {
                throw new InvalidArgumentException(sprintf('tried to query unknown property "%s"', $property));
            }

            if (!$propertyConf['get']) {
                throw new InvalidArgumentException(sprintf('tried to query private/protected property "%s"', $property));
            }

            if (isset($propertyConf['existingMethods']['isset'])) {
                return (bool)$object->{$propertyConf['existingMethods']['isset']}();
            }

            return isset($object->{$property});
        })->bindTo(null, $curClassName);
    }
}