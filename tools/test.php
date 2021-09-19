<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use margusk\GetSet\Attributes\{
    Get, Set, Delete
};
use margusk\GetSet\GetSetTrait;


#[Get,Set(false, 'self::mutator%property%')]
class A
{
    use GetSetTrait;

    #[Get,Set(true)]
    protected string $prop1;

    public static function mutatorProp1($n, $v)
    {
        return htmlspecialchars($v);
    }
}


$a = new A;
$a->prop1 = 'value1<>';

echo 'isset("prop1"): ' . (int)$a->issetProp1() . "\n";
echo 'prop1=' . $a->getProp1() . "\n";

//$a->prop2 = 'value2';