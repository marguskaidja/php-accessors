<?php

declare(strict_types=1);

namespace margusk\GetSet;

use margusk\GetSet\Attributes\Get;

trait GetSet
{
    protected function loadGetSetConfiguration(): array
    {
        static $conf = [];

        $cl = static::class;

        if (!isset($conf[$cl])) {
            $conf[$cl] = [];

            $reflectionClass = new \ReflectionClass($cl);

            foreach ($reflectionClass->getProperties(\ReflectionProperty::IS_PROTECTED) as $reflectionProperty) {
                $n = $reflectionProperty->getName();

                $conf[$cl][$n] = [
                    'get' => false,
                    'set' => false
                ];

                foreach ($reflectionProperty->getAttributes() as $reflectionAttribute) {
                    if (Get::class === $reflectionAttribute->getName()) {
                        $conf[$cl][$n]['get'] = true;
                    }
                }
            }
        }

        return $conf[$cl];
    }

    public function __set(string $name, mixed $value): void
    {
        $conf = $this->loadGetSetConfiguration();

        if (!isset($conf[$name]['get'])) {
            throw new \InvalidArgumentException(sprintf('tried to set unknown/protected property "%s"', $name));
        }

        $this->$name = $value;
    }

    public function __get(string $name): mixed
    {
        $conf = $this->loadGetSetConfiguration();

        if (!isset($conf[$name]['get'])) {
            throw new \InvalidArgumentException(sprintf('tried to read unknown/protected property "%s"', $name));
        }

        return $this->$name;
    }

}