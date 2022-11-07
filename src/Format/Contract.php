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

/** @api */
interface Contract
{
    /**
     * This handler must split given $method into accessor type and property name.
     *
     * Received information is used by caller to decide if the declared method in
     * a class should be used as endpoint for specific property or not.
     *
     * If Method::$propertyName is empty or null is returned, then the method will not be
     * used as endpoint for properties.
     *
     * @param  string  $method
     *
     * @return Method|null
     */
    public function matchEndpointCandidate(string $method): ?Method;

    /**
     * This handler must split given $method into accessor type and property name.
     *
     * The information is used during a __call in Accessible trait, when it must decide,
     * if the $method is made to access an property or properties.
     *
     * If Method::$propertyName is filled, then the method is specific to named property, e.g.:
     * ```php
     *  $foo->setBar('this is new value for bar');
     * ```
     *
     * If Method::$propertyName is empty, then it indicates "general" accessor method, e.g.:
     * ```php
     *  $foo->set('bar', 'new value for bar');
     *
     *  $foo->set([
     *      'bar' => 'new value for bar',
     *      'baz' => 'new value for baz'
     *  ]);
     * ```
     *
     * If $method isn't accessor, then null must be returned.
     *
     * @param  string  $method
     *
     * @return Method|null
     */
    public function matchCalled(string $method): ?Method;

    /**
     * Returns true if method calls without specifying accessor types are allowed, e.g.:
     * ```php
     *  $valueOfBar = $foo->bar();
     *
     *  $foo->bar('this is new value for bar'));
     * ```
     *
     * @return bool
     */
    public function allowPropertyNameOnly(): bool;
}
