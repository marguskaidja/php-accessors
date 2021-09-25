# GetSet

This library helps to create automatic getter / setter methods for class properties.

It's base usage would be to provide setter-method-chaining in DTO-s (Data Transfer Objects).

Due the fact that it's configuration is built upon [PHP attributes](https://www.php.net/manual/en/language.attributes.overview.php), it's faster and more native than the implementations which use DocBlocks to parse out information about which property and how should be made accessible.

## Requirements

Only requirement is **PHP 8+**. No external library is needed for normal usage.

For running the tests:
* [PHPUnit](https://phpunit.de/) is required

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

    public function getProp1()
    {
        return $this->prop1;
    }

    public function getProp2()
    {
        return $this->prop2;
    }

    public function setProp1($value)
    {
        $this->prop1 = $value;
        return $this;
    }

    public function setProp2($value)
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

When you have lot's of properties you want to expose, then it's not reasonable to mark each one of them separately. Mark all properties at once in the class declaration:
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

Sometimes it's handy to pass the setter value through some method before assigning to property. This method is called _mutator_ and can be specified as second parameter for the `Set` attribute:
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
* `<class>::<method>`
* `self::<method>`
* `parent::<method>`
* `static::<method>`

_Mutator_ parameter can contain a special variable named `%property%` which is replaced by the property name it applies. This is useful only when specifying mutator globally in class attribute.

_Mutator_ callback receives the settable value as first parameter and the value it returns is then assigned to property.

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

Why `Delete` and not `Unset`? Because `Unset` is reserved word and can't be used as attribute nor class name.

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
$obj->prop1 = 1024;
echo $obj->prop1; // Outputs "255"
var_dump($opj->prop1Set); // Outputs "bool(true)"
```

Notes:
* To be able to use existing method, it must start with `set`, `get`, `isset` or `unset` prefix and follow with property name.
* Existing method's visibility must be `public` and non-static. `private` and `protected` methods are ignored.
* In case existing `set` method is used, the _mutator_ method is not called. Mutating should be done inside existing `set<property>` method.

### Class inheritance

Attribute inheritance works like it's expected. All attributes in parent class declaration are inherited by child class and can be overwritten:

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

$obj->prop2 = 'value'; // Throws InvalidArgumentException
```

### Case sensitivity in property names

Currently following rules apply when dealing with case sensitivity in property names:
1. When accessed through method, then property name is treated as case-insensitive. Thus if for whatever reason you have names which only differ in case, then the last defined property is used:
```php
#[Get,Set]
class A
{
    use GetSetTrait;

    protected string $prop1;
    protected string $PROP1;
}

$obj = new A();
$obj->setProp1('value');   // A::$PROP1 is modified
```
2. When accessed using direct-access, then property names are treated case-sensitively by default.
   
   This can be changed by adding `ICase` attribute to class declaration. Attribute must be added to whole class (thus not to properties) and can't be reverted in child classes to prevent ambiguouty in the class hierarchy.
```php
#[Get,Set,ICase]
class A
{
    use GetSetTrait;

    protected string $prop1;
    protected string $PROP1;
}

$obj = new A();
$obj->Prop1 = 'value';   // A::$PROP1 is modified
```   
   However the recommended way is leave the case-sensitivity on and always access the property by the name it's declared in the class to have the same consistent code throughout whole codebase.

## Full API

### Exposing properties

1. Use `margusk\GetSet\GetSetTrait` inside the class which properties you want to expose
2. Add attribute `Get`, `Set` and/or `Delete` before the declaration of the property you want to expose. Alternatively if you want to expose all class properties at once, add the attribute before class declaration:
   * `margusk\GetSet\Attributes\Get(?bool $enabled = true)`: allow or disable to read and use `isset()` on the property.
   * `margusk\GetSet\Attributes\Set(?bool $enabled = true, string $mutator = null)`: allow or disable to update the property. Second argument denotes optional _Mutator_ method through which the value is passed through before assigning to property.
   * `margusk\GetSet\Attributes\Delete(?bool $enabled = true)`: allow or disable to `unset()` the property.
   * `margusk\GetSet\Attributes\ICase()`: make accessing the property names case-insensitive. This cane be added only to class declaration and can't be reverted later.

Note:
   * `null` value can be also used for `$enabled`, if you don't want to change the setting inherited from parent's declaration. This is currently useful only for `Set` attribute where in class declaration there is default _mutator_ method defined and it needs to be changed by inherited class or property.

### Properties can be accessed as following

To read the value of property `$prop1`:
* `echo $obj->prop1;`
* `echo $obj->getProp1();`
* `echo $obj->prop1();`

To update the value of property `$prop1`:
* `$obj->prop1 = 'some value';`
* `$obj->setProp1('some value');`
* `$obj->prop1('some value');`

To unset the value of property `$prop1`:
* `unset($obj->prop1);`
* `$obj->unsetProp1();`

To test if `$prop1` property is set. This is allowed/disabled with `Get` attribute:
* `echo isset($obj->prop1);`
* `echo $obj->issetProp1();`
