<?php

/**
 * This file is part of the margusk/accessors package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/marguskaidja/php-accessors
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\Accessors\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
class Set extends Base
{
    protected ?array $mutator;

    public function __construct(?bool $enabled = true, string|array $mutator = null)
    {
        parent::__construct($enabled);

        if (is_string($mutator)) {
            if (preg_match('/^\$this->(.+)/', trim($mutator), $matches)) {
                $mutator = ['$this', $matches[1]];
            } else {
                $mutator = preg_split('/::/', $mutator, 2, PREG_SPLIT_NO_EMPTY);
            }
        }

        // Force the mutator array to indexed list
        if (is_array($mutator)) {
            $mutator = array_values($mutator);
        }

        $this->mutator = $mutator;
    }

    public function mutator(): array|null
    {
        return $this->mutator;
    }
}
