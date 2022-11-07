<?php

/**
 * This file is part of the margusk/accessors package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/marguskaidja/php-accessors
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\Accessors\Attr;

use Attribute;
use margusk\Accessors\Attr;
use margusk\Accessors\Exception\InvalidArgumentException;
use margusk\Accessors\Format\Contract as FormatContract;

use function is_subclass_of;

/** @api */
#[Attribute(Attribute::TARGET_CLASS)]
class Format extends Attr
{
    /** @var mixed[] */
    private array $ctorArgs;

    /** @var FormatContract|null */
    private ?FormatContract $instance = null;

    /**
     * @param  class-string<FormatContract> $format
     * @param  mixed[]                      ...$ctorArgs
     */
    public function __construct(
        private string $format,
        mixed ...$ctorArgs
    ) {
        if (!is_subclass_of($format, FormatContract::class)) {
            throw InvalidArgumentException::dueFormatClassMustImplementValidContract($format);
        }

        parent::__construct();

        $this->ctorArgs = $ctorArgs;
    }

    /**
     * @return FormatContract
     */
    public function instance(): FormatContract
    {
        if (null === $this->instance) {
            $this->instance = new $this->format(...$this->ctorArgs);
        }

        return $this->instance;
    }
}
