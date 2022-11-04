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
use margusk\Accessors\Attr\ICase;
use margusk\Accessors\Exception\InvalidArgumentException;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;

use function call_user_func;
use function get_parent_class;
use function is_string;
use function preg_match;
use function str_starts_with;
use function strtolower;
use function substr;

final class ClassConf
{
    /** @var self[] */
    private static array $classes = [];

    /** @var Attributes */
    private Attributes $attributes;

    /** @var ReflectionClass<object> */
    private ReflectionClass $rfClass;

    /** @var Property[] */
    private array $properties = [];

    /** @var Property[] */
    private array $propertiesByLcase = [];

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
        $this->rfClass = new ReflectionClass($this->name);

        /* Parse attributes of current class */
        $this->attributes = Attributes::fromReflection($this->rfClass);

        /* Require parent class to be parsed before current class, ... */
        $parentName = get_parent_class($this->name);

        /* ...because attributes from current class need to be merged with parent one's */
        if (false !== $parentName) {
            $parent = self::factory($parentName);
            $this->attributes = $this->attributes->mergeWithParent($parent->attributes);
        }

        /* Learn from DocBlock comments which properties should be exposed and how (read-only,write-only or both) */
        $docBlockAttributes = $this->parseDocBlock($this->rfClass);

        /**
         * Collect all manual accessor endpoints.
         *
         * @var array<string, array<string, string>> $accessorEndpoints
         */
        $accessorEndpoints = [];

        foreach (
            $this->rfClass->getMethods(
                ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PRIVATE | ReflectionMethod::IS_PUBLIC
            ) as $rfMethod
        ) {
            if (!$rfMethod->isStatic()
                && preg_match(
                    '/^(set|get|isset|unset|with)(.+)/',
                    strtolower($rfMethod->name),
                    $matches
                )
            ) {
                $accessorEndpoints[(string)$matches[2]][(string)$matches[1]] = $rfMethod->name;
            }
        }

        /**
         * Find all class properties.
         *
         * Provide accessor functionality only for private and protected properties.
         *
         * Although accessors for public properties are not provided (because it makes the behaviour unconsistent),
         * we'll need to remember them along with private and protected properties, so in case they are accessed,
         * informative error can be reported.
         */
        foreach (
            $this->rfClass->getProperties(
                ReflectionMethod::IS_PRIVATE | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC
            ) as $rfProperty
        ) {
            $name = $rfProperty->getName();
            $nameLowerCase = strtolower($name);

            $this->properties[$name] = new Property(
                $rfProperty,
                ($docBlockAttributes[$name] ?? $this->attributes),
                ($accessorEndpoints[$nameLowerCase] ?? [])
            );

            $this->propertiesByLcase[$nameLowerCase] = $this->properties[$name];
        }

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

    public function findPropertyConf(string $name, bool $forceCaseInsensitive = false): ?Property
    {
        if ($forceCaseInsensitive) {
            $caseInsensitive = true;
        } else {
            $caseInsensitive = $this->attributes
                ->get(ICase::class)
                ?->enabled();
        }

        if ($caseInsensitive) {
            $propertyConf = $this->propertiesByLcase[strtolower($name)] ?? null;
        } else {
            $propertyConf = $this->properties[$name] ?? null;
        }

        return $propertyConf;
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

    /**
     * @param  ReflectionClass<object>  $rfClass
     *
     * @return array<string, Attributes>
     */
    private function parseDocBlock(ReflectionClass $rfClass): array
    {
        static $docBlockParser = null;
        static $docBlockLexer = null;

        $docComment = $rfClass->getDocComment();

        if (!is_string($docComment)) {
            return [];
        }

        if (null === $docBlockParser) {
            $constExprParser = new ConstExprParser();

            $docBlockParser = new PhpDocParser(
                new TypeParser($constExprParser),
                $constExprParser
            );

            $docBlockLexer = new Lexer();
        }

        $node = $docBlockParser->parse(
            new TokenIterator(
                $docBlockLexer->tokenize($docComment)
            )
        );

        $result = [];

        foreach ($node->children as $childNode) {
            if ($childNode instanceof PhpDocTagNode
                && $childNode->value instanceof PropertyTagValueNode
                && str_starts_with($childNode->value->propertyName, '$')) {

                $attributes = Attributes::fromDocBlock($childNode);

                if (null !== $attributes) {
                    $attributes->mergeWithParent($this->attributes);
                    $result[substr($childNode->value->propertyName, 1)] = $attributes;
                }
            }
        }

        return $result;
    }
}
