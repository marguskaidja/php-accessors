[![Tests](https://github.com/marguskaidja/php-getset/actions/workflows/tests.yml/badge.svg)](https://github.com/marguskaidja/php-getset/actions/workflows/tests.yml)
# GetSet

Current library helps to create automatic accessor (getters/setters) methods for object properties and keep the code simple and unbloated.
Accessors are useful mainly when dealing with DTO-s (Data Transfer Objects).

It uses simple technique with [trait](https://www.php.net/manual/en/language.oop5.traits.php) to inject it's own implementations of magic ___get()_, ___set()_, ___isset()_, __unset()_ and ___call()_ methods into the desired class. The configuration is built upon [PHP attributes](https://www.php.net/manual/en/language.attributes.overview.php), which makes it faster and more native than implementations which use _DocBlocks_ to parse out information about which property and how should be made accessible.

## Requirements

Only requirement is **PHP 8.0** or later.

No external library is needed except for testing purposes, where [PHPUnit](https://phpunit.de/) is required

## Installation

Install with composer:

```bash
composer require margusk/getset
```

## Basic Usage

Consider the following class:
```php
class A
{
    protected string $prop1;

    protected string $prop2;

    public function getProp1(): mixed
    {
        return $this->prop1;
    }

    public function getProp2(): mixed
    {
        return $this->prop2;
    }

    public function setProp1($value): static
    {
        $this->prop1 = $value;
        return $this;
    }

    public function setProp2($value): static
    {
        $this->prop2 = $value;
        return $this;
    }
}

$a = (new A())->
    setProp1('value1')->
    setProp2('value2');
```
The code above has lot's of boilerplate and boring duplicate code just to achieve smooth method chaining. In case there are tens of properties things could get quite tedious.

The class `A` above can be rewritten using **GetSet** trait:

```php
use margusk\GetSet\Attributes\{
    Get, Set
};
use margusk\GetSet\GetSetTrait;

class A
{
    use GetSetTrait;

    #[Get,Set]
    protected string $prop1;

    #[Get,Set]
    protected string $prop2;
}

$a = (new A())->
    setProp1('value1')->
    setProp2('value2');
```

If you have lot's of properties to expose, then it's not reasonable to mark each one of them separately. Mark all properties at once in the class declaration:
```php
#[Get,Set]
class A
{
    use GetSetTrait;

    protected string $prop1;

    protected string $prop2;
}
```

To allow access to all properties except for `$prop2`, which is made read-only:
```php
#[Get,Set]
class A
{
    use GetSetTrait;

    protected string $prop1;

    #[Set(false)]
    protected string $prop2;
    
    protected string $prop3;
}
```

### Mutator

Sometimes it's handy to proxy the setter value through some intermediate method before assigning to property. This method is called _mutator_ and can be specified as second parameter for the `Set` attribute:
```php
#[Get,Set]
class A
{
    use GetSetTrait;

    #[Set(true, "htmlspecialchars")]
    protected string $prop1;

    protected string $prop2;
}

echo (new A())->setProp1('<>')->getProp1();  // Outputs "&lt;&gt;"
```

It can validate and/or tweak the value before it's assigned to property.

_Mutator_ parameter must be string and can contain function/method name in format:
* `<function>`
* `$this-><method>`
* `<class>::<method>`
* `self::<method>` (NB! can be used only with static class methods)
* `parent::<method>` (NB! can be used only with static class methods)
* `static::<method>`

_Mutator_ parameter can contain a special variable named `%property%` which is replaced by the property name it applies. This is useful only when specifying mutator globally in class attribute.

The callback must accept the settable value as first parameter and it's return value is then assigned to property.

### Unsetting property

It's also possible to unset property's value by using attribute `Delete`:
```php
#[Get,Set]
class A
{
    use GetSetTrait;

    #[Delete]
    protected string $prop1;

    protected string $prop2;
}

(new A())->unsetProp1();
```

Why use `Delete` in attribute name instead of `Unset`? Because `Unset` is reserved word and can't be used as attribute nor class name.

### Existing getter/setter methods

The library can also work with existing setter/getter methods:
```php
#[Get,Set]
class A
{
    use GetSetTrait;

    #[Set(false)]
    protected bool $prop1Set = false;

    protected int $prop1;

    public function setProp1($prop1)
    {
        $this->prop1 = $prop1 & 0xFF;
        $this->prop1Set = true;
    }
}

$obj = new A();
$obj->prop1 = 1023;
echo $obj->prop1; // Outputs "255"
var_dump($opj->prop1Set); // Outputs "bool(true)"
```

Notes:
* To be able to use existing method, it must start with `set`, `get`, `isset`, `unset` or `with` prefix and follow with property name.
* Method's visibility must be `public` and non-static. `private` and `protected` methods are ignored.
* In case existing `set` method is used, the _mutator_ method is not called. Mutating should be done inside existing `set<property>` method.
* Return values:
  * from existing `get` and `isset` methods are proxied back to original caller.
  * from existing `set` and `unset` methods are discarded.
  * from existing `with` method is proxied back to original caller only if the result is object and derives from current class. Other return values are silently discarded and original caller gets `clone`-d object instance.

   
### Class inheritance

Attribute inheritance works intuitively. Attributes in parent class declaration are inherited by child class and can be overwritten (except `ICase` and `Immutable`):

```php
#[Get,Set]
class A
{
    use GetSetTrait;
}

class B extends A
{
    protected string $prop1;
    
    #[Set(false)]
    protected string $prop2;
}

$obj = new B();
$obj->prop1 = 'value';
echo $obj->prop1; // Outputs "value"

$obj->prop2 = 'value'; // Throws BadMethodCallException
```

### Case sensitivity in property names

Following rules apply when dealing with case sensitivity in property names:
1. When accessed through method and property name is part of method name, then it's treated case-insensitive. Thus if for whatever reason you have names which only differ in case, then the last defined property is used:
```php
#[Get,Set]
class A
{
    use GetSetTrait;

    protected string $prop1;
    protected string $PROP1;
}

$obj = new A();
$obj->setProp1('value');        // Case INsensitive: A::$PROP1 is modified
$obj->prop1('value');           // Case INsensitive: A::$PROP1 is modified
```
2. In all other situations, the property names are treated case-sensitive by default.

```php
$obj->set('prop1', 'value');    // Case sensitive: A::$prop1 is modified
echo $obj->prop1;               // Case sensitive: A::$prop1 is returned
echo $obj->Prop1;               // Throws BadMethodCallException: "tried to read unknown property..." 
```
  
Case-insensitivity for all situations can be turned on by adding `ICase` attribute to class declaration. Attribute must be added to whole class (thus not to properties) and can't be reverted in child classes to prevent ambiguouty in the class hierarchy.
```php
#[Get,Set,ICase]
class A
{
    use GetSetTrait;

    protected string $prop1;
    protected string $PROP1;
}

$obj = new A();
$obj->Prop1 = 'value';   // Case INsensitive: A::$PROP1 is modified
echo $obj->Prop1;        // Case INsensitive: A::$PROP1 is returned 
```   
However the recommended way is leave the case-sensitivity on and always access the property by the name it's declared in the class to have the same consistent code throughout whole codebase.

### Immutable properties

By default the objects which allow their properties to be changed are mutable. Sometimes it's neccessary to prevent mutability but somehow still allow to change some or all the properties. 
Consider following situtaion:
```php
#[Get]
class A 
{
    use GetSetTrait;

    public function __construct(
        protected $a,
        protected $b,
        protected $c,
        protected $d,
        protected $e,
        protected $f
    )
    {
    }
}

// Configure object $a
$a = new A(1, 2, 3, 4, 5, 6);

// Configure object $b, which differs from $a only by single value (property $f).
// But to achieve this, we had to read the rest of the values from object $a and pass to constructor to create new object. 
// This can result in bloated and complex code.
$b = new B($a->a, $a->b,  $a->c,  $a->d,  $a->e,  7);
```

This is where immutable object/properties help to simplify things: 
```php
#[Get,Set,Immutable]
class A 
{
    use GetSetTrait;

    public function __construct(
        protected $a,
        protected $b,
        protected $c,
        protected $d,
        protected $e,
        protected $f
    )
    {
    }
}

// Configure object $a
$a = new A(1, 2, 3, 4, 5, 6);

// Clone object $a and change only 1 property in cloned object.
// Very clean and simple.
$b = $a->with('f', 7);

// Clone object $a and change 3 properties in cloned object.
$b = $a->with([
    'a' => 11,
    'b' => 12,
    'f' => 7
]);

// The original object $a still stays intact
echo (int)($a === $b); // Outputs "0"
echo $a->f; // Outputs "6"
echo $b->f; // Outputs "7"
```

Notes:
* To prevent ambiguity, immutable properties must be changed using  method `with` instead of `set`. Using `set` results in exception.
* Unsetting immutable properties is not possible and results in exception.


## Full API

### Exposing properties

1. Use `margusk\GetSet\GetSetTrait` inside the class which properties you want to expose
2. Add attribute `Get`, `Set` and/or `Delete` before the declaration of the property you want to expose. Alternatively if you want to expose all class properties at once, add the attribute before class declaration:
   * `margusk\GetSet\Attributes\Get(?bool $enabled = true)`: allow or disable to read and use `isset()` on the property.
   * `margusk\GetSet\Attributes\Set(?bool $enabled = true, string $mutator = null)`: allow or disable to update the property. Second argument denotes optional _Mutator_ method through which the value is passed through before assigning to property.
   * `margusk\GetSet\Attributes\Delete(?bool $enabled = true)`: allow or disable to `unset()` the property.
   * `margusk\GetSet\Attributes\ICase()`: make accessing the property names case-insensitive. This cane be added only to class declaration and can't be reverted later.
   * `margusk\GetSet\Attributes\Immutable()`: turn on immutable flag for single property or whole class. Once the flag is enabled, it can't be disabled later. 

Note:
   * `null` value can be also used for `$enabled`, if you don't want to change the setting inherited from parent's declaration. This is currently useful only for `Set` attribute where in class declaration there is default _mutator_ method defined and it needs to be changed by inherited class or property.

### Properties can be accessed as following

To read the value of property `$prop1`:
* `echo $obj->prop1;`
* `echo $obj->getProp1();`
* `echo $obj->get('prop1);`
* `echo $obj->prop1();`

To update the value of property `$prop1`:
* `$obj->prop1 = 'some value';`
* `$obj->setProp1('some value');`
* `$obj->set('prop1', 'some value');`
* `$obj->set(['prop1' => 'value1', 'prop2' => 'value2', ..., 'propN' => 'valueN');`
* `$obj->prop1('some value');`

To update the value of immutable property `$prop1`:
* `$cloned = $obj->withProp1('some value');`
* `$cloned = $obj->with('prop1', 'some value');`
* `$cloned = $obj->with(['prop1' => 'value1', 'prop2' => 'value2', ..., 'propN' => 'valueN');`

To unset the value of property `$prop1`:
* `unset($obj->prop1);`
* `$obj->unsetProp1();`
* `$obj->unset('prop1);`
* `$obj->unset(['prop1', 'prop2', ..., 'propN');`

To test if `$prop1` property is set. This is allowed/disabled with `Get` attribute:
* `echo isset($obj->prop1);`
* `echo $obj->issetProp1();`
* `echo $obj->isset('prop1);`
