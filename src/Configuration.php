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
use margusk\Accessors\Attributes\Delete;
use margusk\Accessors\Attributes\Get;
use margusk\Accessors\Attributes\ICase;
use margusk\Accessors\Attributes\Immutable;
use margusk\Accessors\Attributes\Set;
use margusk\Accessors\Exception\InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionException;

final class Configuration
{
    private static array $propertiesConf = [];

    /**
     * Parses configuration of specified class.
     *
     * Configuration is cached so later requests for same class are returned instantly.
     *
     * @param  string  $curClassName
     *
     * @return array
     * @throws ReflectionException
     */
    public static function load(string $curClassName): array
    {
        if (!isset(self::$propertiesConf[$curClassName]['byCase'])) {
            self::$propertiesConf[$curClassName] = array_merge(self::$propertiesConf[$curClassName] ?? [], [
                'byCase'    => [],
                'byLCase'   => [],
                'getImpl'   => self::createGetImplementation($curClassName),
                'setImpl'   => self::createSetImplementation($curClassName),
                'unsetImpl' => self::createUnsetImplementation($curClassName),
                'issetImpl' => self::createIssetImplementation($curClassName),
            ]);

            $parseAttributes = function (ReflectionClass|ReflectionProperty $reflection): array {
                $conf = [];
                foreach (['get', 'set', 'unset', 'mutator', 'icase', 'immutable'] as $f) {
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
                                    'value' => (count($mutator) > 0 ? $mutator : null)
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
                            $conf['icase'] = [
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
                    foreach (['get', 'set', 'unset', 'mutator', 'icase', 'immutable'] as $f) {
                        if (!$child[$f]['isset']) {
                            $child[$f] = $parent[$f];
                        }
                    }
                }

                return $child;
            };

            if (!isset(self::$propertiesConf[$curClassName]['attributes'])) {
                $classNames = array_reverse(array_merge([$curClassName], class_parents($curClassName)));
                $mergedClassAttr = null;

                foreach ($classNames as $className) {
                    if (!isset(self::$propertiesConf[$className]['attributes'])) {
                        $classAttr = $parseAttributes(new ReflectionClass($className));

                        $mergedClassAttr = $mergeAttributes($mergedClassAttr, $classAttr);
                        self::$propertiesConf[$className]['attributes'] = $mergedClassAttr;
                    } else {
                        $mergedClassAttr = self::$propertiesConf[$className]['attributes'];
                    }
                }
            }

            $reflectionClass = new ReflectionClass($curClassName);

            // Find all existing "set<Property>", "get<Property>", "isset<Property>" and "unset<Property>" methods for
            // cases where key is accessed/modified using magic setter methods. In this case we call the existing method.
            $existingMethods = [];
            foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
                /** @var $reflectionMethod ReflectionMethod */
                if (!$reflectionMethod->isStatic()
                    && preg_match(
                        '/^(set|get|isset|unset|with)(.+)/',
                        strtolower($reflectionMethod->getName()),
                        $matches
                    )
                ) {
                    $existingMethods[$matches[2]][$matches[1]] = $reflectionMethod->getName();
                }
            }

            // Parse attributes of each property
            foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PROTECTED) as $reflectionProperty) {
                $property = $reflectionProperty->getName();
                $lcaseProperty = strtolower($property);

                $propertyConfRaw = $mergeAttributes(
                    self::$propertiesConf[$curClassName]['attributes'],
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
                //      "$this->someMethod"
                //      "someFunction"
                //      "<CLASS>::someMethod"
                //
                // Name can also contain special variable '%property%' which is then replaced by the appropriate
                // property name. This is useful only when specifying mutator through class attributes.
                $mutator = $propertyConf['mutator'];

                if (is_array($mutator) && count($mutator) > 0) {
                    $mutator = array_map(function (string $s) use ($property): string {
                        return str_replace('%property%', $property, $s);
                    }, $propertyConf['mutator']);

                    if (count($mutator) >= 2) {
                        $check = $mutator;

                        if ('$this' === $mutator[0]) {
                            $check[0] = $curClassName;
                            $mutator[0] = null;
                        }

                        // Check if instance or class method exists in current class.
                        //
                        // It doesn't check if it's actually callable in object context, thus it may generate
                        // exceptions later when actually beeing invoked.
                        if (!method_exists($check[0], $check[1])) {
                            throw InvalidArgumentException::dueInvalidMutatorCallback(
                                $curClassName,
                                $property,
                                $check
                            );
                        }
                    } else {
                        if (!is_callable($mutator[0])) {
                            throw InvalidArgumentException::dueInvalidMutatorCallback(
                                $curClassName,
                                $property,
                                $mutator
                            );
                        }

                        $mutator = $mutator[0];
                    }
                } else {
                    $mutator = null;
                }

                $propertyConf['mutator'] = $mutator;

                self::$propertiesConf[$curClassName]['byCase'][$property] = $propertyConf;
                self::$propertiesConf[$curClassName]['byLCase'][$lcaseProperty] = $property;
            }

            // Create closure for retrieving property conf by property name.
            // Case sensitivity setting is also taken in account.
            $getFunc = function (string $property) use ($curClassName): ?array {
                return self::$propertiesConf[$curClassName]['byCase'][$property] ?? null;
            };

            $getFuncICase = function (string &$property) use ($getFunc, $curClassName): ?array {
                $origPropertyName = self::$propertiesConf[$curClassName]['byLCase'][strtolower($property)] ?? null;

                if (!isset($origPropertyName)) {
                    return null;
                }

                $property = $origPropertyName;

                return $getFunc($property);
            };

            if (self::$propertiesConf[$curClassName]['attributes']['icase']['value']) {
                $getFunc = $getFuncICase;
            }

            self::$propertiesConf[$curClassName]['getPropertyConf'] = $getFunc;
            self::$propertiesConf[$curClassName]['getPropertyConfICase'] = $getFuncICase;
        }

        return self::$propertiesConf[$curClassName];
    }

    private static function createGetImplementation(string $curClassName): Closure
    {
        return (function (object $object, string $property, ?array $propertyConf) use ($curClassName): mixed {
            if (!isset($propertyConf)) {
                throw InvalidArgumentException::dueTriedToGetUnknownProperty(
                    $curClassName,
                    $property
                );
            }

            if (!$propertyConf['get']) {
                throw InvalidArgumentException::dueTriedToGetMisconfiguredProperty(
                    $curClassName,
                    $property
                );
            }

            if (isset($propertyConf['existingMethods']['get'])) {
                return $object->{$propertyConf['existingMethods']['get']}();
            }

            return $object->{$property};
        })->bindTo(null, $curClassName);
    }

    private static function createSetImplementation(string $curClassName): Closure
    {
        return (function (
            object $object,
            string $accessorMethod,
            string $property,
            mixed $value,
            ?array $propertyConf
        ) use ($curClassName): object {
            if (!$propertyConf) {
                throw InvalidArgumentException::dueTriedToSetUnknownProperty(
                    $curClassName,
                    $property
                );
            }

            if (!$propertyConf['set']) {
                throw InvalidArgumentException::dueTriedToSetMisconfiguredProperty(
                    $curClassName,
                    $property
                );
            }

            if (isset($propertyConf['existingMethods'][$accessorMethod])) {
                $result = $object->{$propertyConf['existingMethods'][$accessorMethod]}($value);

                if ('with' === $accessorMethod
                    && ($result instanceof $curClassName)
                ) {
                    $object = $result;
                }
            } else {
                $mutator = $propertyConf['mutator'];

                if (null !== $mutator) {
                    if (is_array($mutator) && null === $mutator[0]) {
                        $mutator[0] = $object;
                    }

                    $value = call_user_func($mutator, $value);
                }

                $object->{$property} = $value;
            }

            return $object;
        })->bindTo(null, $curClassName);
    }

    private static function createUnsetImplementation(string $curClassName): Closure
    {
        return (function (object $object, string $property, ?array $propertyConf) use ($curClassName): object {
            if (!$propertyConf) {
                throw InvalidArgumentException::dueTriedToUnsetUnknownProperty(
                    $curClassName,
                    $property
                );
            }

            if (!$propertyConf['unset']) {
                throw InvalidArgumentException::dueTriedToUnsetMisconfiguredProperty(
                    $curClassName,
                    $property
                );
            }

            if ($propertyConf['immutable']) {
                throw InvalidArgumentException::dueImmutablePropertyCantBeUnset(
                    $curClassName,
                    $property
                );
            }

            if (isset($propertyConf['existingMethods']['unset'])) {
                $object->{$propertyConf['existingMethods']['unset']}();
            } else {
                unset($object->{$property});
            }

            return $object;
        })->bindTo(null, $curClassName);
    }

    private static function createIssetImplementation(string $curClassName): Closure
    {
        return (function (object $object, string $property, ?array $propertyConf) use ($curClassName): bool {
            if (!isset($propertyConf)) {
                throw InvalidArgumentException::dueTriedToGetUnknownProperty(
                    $curClassName,
                    $property
                );
            }

            if (!$propertyConf['get']) {
                throw InvalidArgumentException::dueTriedToGetMisconfiguredProperty(
                    $curClassName,
                    $property
                );
            }

            if (isset($propertyConf['existingMethods']['isset'])) {
                return (bool)$object->{$propertyConf['existingMethods']['isset']}();
            }

            return isset($object->{$property});
        })->bindTo(null, $curClassName);
    }
}
