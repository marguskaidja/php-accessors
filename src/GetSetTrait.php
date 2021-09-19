<?php

declare(strict_types=1);

namespace margusk\GetSet;

use BadMethodCallException;
use InvalidArgumentException;
use margusk\GetSet\Attributes\Delete;
use margusk\GetSet\Attributes\Get;
use margusk\GetSet\Attributes\Set;
use ReflectionClass;
use ReflectionProperty;

trait GetSetTrait
{
    public function __call(string $method, array $args): mixed
    {
        $conf = $this->loadGetSetConfiguration();

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

    protected function loadGetSetConfiguration(): array
    {
        static $conf = [];

        $cl = static::class;

        if (!isset($conf[$cl])) {
            $conf[$cl] = [
                'byCase'  => [],
                'byLCase' => []
            ];

            $parseAttributes = function (ReflectionClass|ReflectionProperty $reflection, ?array $conf): array {
                if (null === $conf) {
                    $conf = [
                        'get'     => false,
                        'set'     => false,
                        'unset'   => false,
                        'mutator' => null
                    ];
                }

                foreach ($reflection->getAttributes() as $reflectionAttribute) {
                    switch ($reflectionAttribute->getName()) {
                        case Get::class:
                            $conf['get'] = $reflectionAttribute->newInstance()->enabled();
                            break;
                        case Set::class:
                            $inst = $reflectionAttribute->newInstance();
                            $conf['set'] = $inst->enabled();
                            $mutator = $inst->mutator();

                            if (null !== $mutator) {
                                if ("" === $mutator) {
                                    $conf['mutator'] = null;
                                } else {
                                    $conf['mutator'] = $inst->mutator();
                                }
                            }
                            break;
                        case Delete::class:
                            $conf['unset'] = $reflectionAttribute->newInstance()->enabled();
                            break;
                    }
                }

                return $conf;
            };

            $reflectionClass = new ReflectionClass($cl);

            // Parse class attributes
            $classConf = $parseAttributes($reflectionClass, null);

            // Parse attributes of each property
            foreach (
                $reflectionClass->getProperties(
                    ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE
                ) as $reflectionProperty
            ) {
                $property = $reflectionProperty->getName();
                $lcaseProperty = strtolower($property);

                $propertyConf = $parseAttributes($reflectionProperty, $classConf);

                // Mutator methodname can be in format:
                //      "$this->someMethod"
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

                $conf[$cl]['byCase'][$property] = $propertyConf;
                $conf[$cl]['byLCase'][$lcaseProperty] = $property;
            }
        }

        return $conf[$cl];
    }

    public function __get(string $property): mixed
    {
        $conf = $this->loadGetSetConfiguration();

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
        $conf = $this->loadGetSetConfiguration();

        if (!isset($conf['byCase'][$property])) {
            throw new InvalidArgumentException(sprintf('tried to set unknown property "%s"', $property));
        }

        $conf = $conf['byCase'][$property];

        if (!$conf['set']) {
            throw new InvalidArgumentException(sprintf('tried to set private/protected property "%s"', $property));
        }

        if (null !== $conf['mutator']) {
            $value = call_user_func($conf['mutator'], $property, $value);
        }

        $this->{$property} = $value;
    }

    public function __isset(string $property): bool
    {
        $conf = $this->loadGetSetConfiguration();

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
        $conf = $this->loadGetSetConfiguration();

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
