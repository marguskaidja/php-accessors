<?php

/**
 * This file is part of the margusk/accessors package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/marguskaidja/php-accessors
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\Accessors\Tests;

use margusk\Accessors\Accessible;
use margusk\Accessors\Attr\Delete;
use margusk\Accessors\Attr\Get;
use margusk\Accessors\Attr\Set;

use function htmlspecialchars;

/**
 * @property string $parentProperty
 *
 * @method string getParentProperty()
 * @method self setParentProperty(string $value)
 */
class ParentTestClassWithPropertyAttributes
{
    use Accessible;

    protected string $parentProperty;
}
