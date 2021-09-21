<?php

/**
 * This file is part of the GetSet package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/m4r9u5/GetSet
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\GetSet\Attributes;

class Base
{
    public function __construct(
        protected ?bool $enabled = true
    ) {
    }

    public function enabled(): ?bool
    {
        return $this->enabled;
    }
}