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

use Exception;
use PHPUnit\Framework\Constraint\LogicalNot;
use PHPUnit\Framework\TestCase as TestCaseBase;

use function md5;

class TestCase extends TestCaseBase
{
    /** @var array<string, string> */
    private static array $createdClasses = [];

    public static function assertNotObjectEquals(
        object $expected,
        object $actual,
        string $method = 'equals',
        string $message = ''
    ): void {
        static::assertThat(
            $actual,
            new LogicalNot(static::objectEquals($expected, $method)),
            $message
        );
    }

    /**
     * @param  string  $code
     *
     * @return object
     */
    protected function createObjFromClassCode(string $code): object
    {
        $name = $this->createClass($code);
        return new $name();
    }

    /**
     * @param  string  $code
     *
     * @return string
     */
    protected function createClass(string $code): string
    {
        $name = 'testClass_'.md5(uniqid());

        $code = str_replace(
            '%name%',
            $name,
            $code
        );

        $hash = md5($code);

        if (isset(self::$createdClasses[$hash])) {
            return self::$createdClasses[$hash];
        }

        eval($code);

        self::$createdClasses[$hash] = $name;

        return $name;
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function randomString(): string
    {
        return md5(random_bytes(32));
    }
}
