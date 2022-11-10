<?php

/**
 * This file is part of the margusk/accessors package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/marguskaidja/php-accessors
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\Accessors\Format;

/** @api */
final class Method
{
    public const TYPE_GET = 'get';
    public const TYPE_SET = 'set';
    public const TYPE_ISSET = 'isset';
    public const TYPE_UNSET = 'unset';
    public const TYPE_WITH = 'with';

    /** @var array<string, string> */
    public const TYPES
        = [
            self::TYPE_GET   => self::TYPE_GET,
            self::TYPE_SET   => self::TYPE_SET,
            self::TYPE_ISSET => self::TYPE_ISSET,
            self::TYPE_UNSET => self::TYPE_UNSET,
            self::TYPE_WITH  => self::TYPE_WITH
        ];

    /**
     * @noinspection PhpUndefinedClassInspection  Make PHPStorm ignore the value-of annotation
     * @phpstan-param  value-of<Method::TYPES> $type
     *
     * @param  string                          $type
     * @param  string                          $propertyName
     */
    public function __construct(
        private string $type,
        private string $propertyName
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function propertyName(): string
    {
        return $this->propertyName;
    }
}
