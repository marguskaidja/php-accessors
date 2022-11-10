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

use margusk\Accessors\Format\Contract as FormatContract;

use function implode;
use function preg_match;
use function strtolower;

/**
 * Default camel-case format for accessors method names.
 *
 * 1. Detects accessor calls and endpoints with following format:
 *      - Setters: `set[<property>]()` and `with[<property>]()`
 *      - Getters: `get[<property>]()` and `isset[<property>]()`
 *      - Unsetter: `unset[<property>]()`
 *
 * 2. Allows accessor methods without property names, e.g. following calls are valid:
 * ```php
 *  $value = $foo->bar();       // Same as $value = $foo->bar;
 *  $foo->bar('new value');    // Same as $foo->bar = `new value`;
 * ```
 * @api
 */
class Standard implements FormatContract
{
    public function matchEndpointCandidate(string $method): ?Method
    {
        return $this->matchCalled($method);
    }

    public function matchCalled(string $method): ?Method
    {
        if (
            preg_match(
                '/^('.implode('|', Method::TYPES).')(.*)/i',
                strtolower($method),
                $matches
            )
        ) {
            $methodName = $matches[1];
            $propertyName = $matches[2];

            return new Method(
                Method::TYPES[$methodName],
                $propertyName
            );
        }

        return null;
    }

    public function allowPropertyNameOnly(): bool
    {
        return true;
    }
}