<?php

/**
 * This file is part of the GetSet package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/m4r9u5/GetSet
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\GetSet;

use margusk\GetSet\Attributes\Delete;
use margusk\GetSet\Attributes\Get;
use margusk\GetSet\Attributes\Set;
use margusk\GetSet\Exceptions\BadMethodCallException;
use margusk\GetSet\Exceptions\InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;

trait GetSetTrait
{
    public function __call(string $method, array $args): mixed
    {
        $conf = loadConfiguration(static::class);

        $lcaseMethod = strtolower($method);
        $prefix = substr($lcaseMethod, 0, 3);

        // Try to detect "setProperty", "getProperty", "issetProperty" or "unserProperty" calls.
        if ('set' !== $prefix
            && 'get' !== $prefix
            && !in_array(($prefix = substr($lcaseMethod, 0, 5)), ['unset', 'isset'])
        ) {
            $prefix = null;
        }

        $property = substr($method, strlen((string)$prefix));
        $lcaseProperty = strtolower($property);

        // If not one of the explicit calls above, then check if whole method name is property name like
        //  $obj->somePropertyName('somevalue')
        if (null === $prefix && isset($conf['byLCase'][$lcaseProperty])) {
            // If there is zero arguments, then interpret the call as Getter
            // If there are arguments, then it's Setter
            if (count($args) > 0) {
                $prefix = 's';
            } else {
                $prefix = 'g';
            }
        }

        if (null !== $prefix) {
            $property = $conf['byLCase'][strtolower($property)] ?? $property;

            // Call Setter
            if ('s' === $prefix[0]) {
                if (1 !== count($args)) {
                    throw new BadMethodCallException('expecting 1 argument to method %s', $method);
                }
                $this->__set($property, $args[0]);
                return $this;
                // Getter, Setter, Isset
            } else {
                if (0 !== count($args)) {
                    throw new BadMethodCallException('no arguments expected to method %s', $method);
                }

                // Call Getter
                if ('g' === $prefix[0]) {
                    return $this->__get($property);
                    // Call Isset
                } elseif ('i' === $prefix[0]) {
                    return $this->__isset($property);
                }

                // Call Unsetter
                $this->__unset($property);
                return $this;
            }
        }

        throw new BadMethodCallException(sprintf('unknown method %s', $method));
    }

    public function __get(string $property): mixed
    {
        $conf = loadConfiguration(static::class);

        if (!isset($conf['byCase'][$property])) {
            throw new InvalidArgumentException(sprintf('tried to read unknown property "%s"', $property));
        }

        $conf = $conf['byCase'][$property];

        if (!$conf['get']) {
            throw new InvalidArgumentException(sprintf('tried to read private/protected property "%s"', $property));
        }

        return $this->{$property};
    }

    public function __set(string $property, mixed $value): void
    {
        $conf = loadConfiguration(static::class);

        if (!isset($conf['byCase'][$property])) {
            throw new InvalidArgumentException(sprintf('tried to set unknown property "%s"', $property));
        }

        $conf = $conf['byCase'][$property];

        if (!$conf['set']) {
            throw new InvalidArgumentException(sprintf('tried to set private/protected property "%s"', $property));
        }

        if (null !== $conf['mutator']) {
            $value = call_user_func($conf['mutator'], $value);
        }

        $this->{$property} = $value;
    }

    public function __isset(string $property): bool
    {
        $conf = loadConfiguration(static::class);

        if (!isset($conf['byCase'][$property])) {
            throw new InvalidArgumentException(sprintf('tried to query unknown property "%s"', $property));
        }

        $conf = $conf['byCase'][$property];

        if (!$conf['get']) {
            throw new InvalidArgumentException(sprintf('tried to query private/protected property "%s"', $property));
        }

        return isset($this->{$property});
    }

    public function __unset(string $property): void
    {
        $conf = loadConfiguration(static::class);

        if (!isset($conf['byCase'][$property])) {
            throw new InvalidArgumentException(sprintf('tried to unset unknown property "%s"', $property));
        }

        $conf = $conf['byCase'][$property];

        if (!$conf['unset']) {
            throw new InvalidArgumentException(sprintf('tried to unset private/protected property "%s"', $property));
        }

        unset($this->{$property});
    }
}

function loadConfiguration(string $curClassName): array
{
    static $classesConf = [];
    static $propertiesConf = [];

    if (!isset($propertiesConf[$curClassName])) {
        $propertiesConf[$curClassName] = [
            'byCase'  => [],
            'byLCase' => []
        ];

        $parseAttributes = function (ReflectionClass|ReflectionProperty $reflection): array {
            $conf = [];
            foreach (['get', 'set', 'unset', 'mutator'] as $f) {
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
                }
            }

            return $conf;
        };

        $mergeAttributes = function (?array $parent, array $child): array {
            if (null !== $parent) {
                foreach (['get', 'set', 'unset', 'mutator'] as $f) {
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
            if (!isset($classesConf[$className])) {
                $classAttr = $parseAttributes(new ReflectionClass($className));

                $mergedClassAttr = $mergeAttributes($mergedClassAttr, $classAttr);
                $classesConf[$className] = $mergedClassAttr;
            } else {
                $mergedClassAttr = $classesConf[$className];
            }
        }

        // Parse attributes of each property
        foreach (
            (new ReflectionClass($curClassName))->getProperties(
                ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE
            ) as $reflectionProperty
        ) {
            $property = $reflectionProperty->getName();
            $lcaseProperty = strtolower($property);

            $propertyConfRaw = $mergeAttributes(
                $classesConf[$curClassName],
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

            $propertiesConf[$curClassName]['byCase'][$property] = $propertyConf;
            $propertiesConf[$curClassName]['byLCase'][$lcaseProperty] = $property;
        }
    }

    return $propertiesConf[$curClassName];
}