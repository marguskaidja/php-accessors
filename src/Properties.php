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

use margusk\Accessors\Attr\Format;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

use function is_string;
use function strtolower;
use function substr;

class Properties
{
    /** @var Property[] */
    private array $properties = [];

    /** @var Property[] */
    private array $propertiesByLowerCase = [];

    /**
     * @param  ReflectionClass<object>  $rfClass
     * @param  Attributes               $classAttributes
     * @param  Properties|null          $parentProperties
     *
     * @return self
     */
    public static function fromReflection(
        ReflectionClass $rfClass,
        Attributes $classAttributes,
        ?Properties $parentProperties,
        bool $withPHPDocs
    ): self
    {
        $that = new self();

        /* Learn from DocBlock comments which properties should be exposed and how (read-only,write-only or both) */
        if ($withPHPDocs) {
            $phpDocAttributes = $that->parsePHPDocs($rfClass);
        } else {
            $phpDocAttributes = [];
        }

        /** @var Format $attr */
        $attr = $classAttributes->get(Format::class);
        $format = $attr->instance();

        /**
         * Collect all manually generated accessor endpoints.
         *
         * @var array<string, array<string, string>> $accessorEndpoints
         */
        $accessorEndpoints = [];

        foreach (
            $rfClass->getMethods(
                ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PUBLIC
            ) as $rfMethod
        ) {
            if ($rfMethod->isStatic()) {
                continue;
            }

            if (null !== ($parsedMethod = $format->matchEndpointCandidate($rfMethod->name))) {
                $n = strtolower($parsedMethod->propertyName());
                $t = $parsedMethod->type();
                $accessorEndpoints[$n][$t] = $rfMethod->name;
            }
        }

        /**
         * Find all class properties.
         *
         * Provide accessor functionality only for private and protected properties.
         *
         * Although accessors for public properties are not provided (because it makes the behaviour inconsistent),
         * we'll need to remember them along with private and protected properties, so in case they are accessed,
         * informative error can be reported.
         */
        foreach (
            $rfClass->getProperties(
                ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC
            ) as $rfProperty
        ) {
            $name = $rfProperty->getName();
            $nameLowerCase = strtolower($name);
            $p = null;

            if ($rfClass->name === $rfProperty->getDeclaringClass()->name) {
                if (isset($phpDocAttributes[$name])) {
                    $attributes = $phpDocAttributes[$name]->mergeWithParent($classAttributes);
                } else {
                    $attributes = $classAttributes;
                }

                $p = new Property(
                    $rfProperty,
                    $attributes,
                    ($accessorEndpoints[$nameLowerCase] ?? [])
                );
            } elseif (null !== $parentProperties) {
                $p = $parentProperties->findConf($name);
            }

            if (null !== $p) {
                $that->propertiesByLowerCase[$nameLowerCase] = ($that->properties[$name] = $p);
            }
        }

        return $that;
    }

    /**
     * @param  ReflectionClass<object>  $rfClass
     *
     * @return array<string, Attributes>
     */
    private function parsePHPDocs(ReflectionClass $rfClass): array
    {
        static $phpDocParser = null;
        static $phpDocLexer = null;

        $docComment = $rfClass->getDocComment();

        if (!is_string($docComment)) {
            return [];
        }

        if (null === $phpDocParser) {
            $constExprParser = new ConstExprParser();

            $phpDocParser = new PhpDocParser(
                new TypeParser($constExprParser),
                $constExprParser
            );

            $phpDocLexer = new Lexer();
        }

        $node = $phpDocParser->parse(
            new TokenIterator(
                $phpDocLexer->tokenize($docComment)
            )
        );

        $result = [];

        foreach ($node->children as $childNode) {
            if ($childNode instanceof PhpDocTagNode
                && $childNode->value instanceof PropertyTagValueNode
                && str_starts_with($childNode->value->propertyName, '$')
            ) {
                $attributes = Attributes::fromPHPDocs($childNode);

                if (null !== $attributes) {
                    $result[substr($childNode->value->propertyName, 1)] = $attributes;
                }
            }
        }

        return $result;
    }

    public function findConf(string $name, bool $caseInsensitiveSearch = false): ?Property
    {
        if ($caseInsensitiveSearch) {
            $propertyConf = $this->propertiesByLowerCase[strtolower($name)] ?? null;
        } else {
            $propertyConf = $this->properties[$name] ?? null;
        }

        return $propertyConf;
    }
}
