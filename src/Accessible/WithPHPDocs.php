<?php /** @noinspection PhpComposerExtensionStubsInspection */

/**
 * This file is part of the margusk/accessors package.
 *
 * @author  Margus Kaidja <margusk@gmail.com>
 * @link    https://github.com/marguskaidja/php-accessors
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

declare(strict_types=1);

namespace margusk\Accessors\Accessible;

use LogicException;
use margusk\Accessors\Accessible;

use function extension_loaded;
use function opcache_get_configuration;

(function() {
    /**
     * If OPCache is enabled then verify that 'opcache.save_comments=1'.
     * Without this setting the code is missing PHPDoc comments and will not work as expected.
     */
    if (extension_loaded('Zend OPcache')) {
        $cnf = opcache_get_configuration();

        if (false === $cnf) {
            throw new LogicException('failed to call opcache_get_configuration()');
        }

        if (true === $cnf['directives']['opcache.enable']) {
            $d = 'opcache.save_comments';

            if (true !== $cnf['directives'][$d]) {
                throw new LogicException(
                    sprintf(
                        'OPCache directive "%s" must be enabled to have support for PHPDoc tags',
                        $d
                    )
                );
            }
        }
    }
})();

/** @api */
trait WithPHPDocs
{
    use Accessible;
}
