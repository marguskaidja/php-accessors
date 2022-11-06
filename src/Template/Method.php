<?php

declare(strict_types=1);

namespace margusk\Accessors\Template;

final class Method
{
    public const TYPE_GET   = 'get';
    public const TYPE_SET   = 'set';
    public const TYPE_ISSET = 'isset';
    public const TYPE_UNSET = 'unset';
    public const TYPE_WITH  = 'with';

    /** @var array<string, string> */
    public const TYPES = [
        self::TYPE_GET      => self::TYPE_GET,
        self::TYPE_SET      => self::TYPE_SET,
        self::TYPE_ISSET    => self::TYPE_ISSET,
        self::TYPE_UNSET    => self::TYPE_UNSET,
        self::TYPE_WITH     => self::TYPE_WITH
    ];

    /**
     * @param  value-of<Method::TYPES>  $type
     * @param  string                   $propertyName
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
