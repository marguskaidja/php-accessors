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

#[Attribute(Attribute::TARGET_CLASS)]
class ICase extends Attr
{
    public function __construct()
    {
        parent::__construct();
    }
}