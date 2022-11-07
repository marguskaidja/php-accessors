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

use function array_values;
use function count;
use function explode;
use function is_array;
use function is_string;
use function preg_match;
use function trim;

/** @api */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
class Mutator extends Attr
{
    /** @var string|string[]|null */
    private string|array|null $mutator;

    /**
     * @param  string|string[]|null  $mutator
     */
    public function __construct(
        string|array|null $mutator
    ) {
        if (is_string($mutator)) {
            if (preg_match('/^\$this->(.+)/', trim($mutator), $matches)) {
                $mutator = ['', $matches[1]];
            } else {
                $mutator = explode('::', $mutator, 2);

                if (1 === count($mutator)) {
                    $mutator = $mutator[0];
                }
            }
        }

        // Force the mutator array into indexed list
        if (is_array($mutator)) {
            $mutator = array_values($mutator);
        }

        $this->mutator = $mutator;

        parent::__construct(null !== $this->mutator);
    }

    /**
     * @return string|string[]|null
     */
    public function mutator(): string|array|null
    {
        return $this->mutator;
    }
}
