# GetSet

This library helps to create automatic getter / setter methods for class properties.

It's main usage would be to provide setter method chaining in DTO-s (Data Transfer Objects).

## Requirements

It requires **PHP 8** since the configuration is implemented using [attributes](https://www.php.net/manual/en/language.attributes.overview.php).

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

When you have lot's of properties you want to expose, then it's not reasonable to mark each one of them separately. Mark all properties settable / gettable at once:
```php
#[Get,Set]
class A
{
    use GetSetTrait;

    protected string $prop1;

    protected string $prop2;
}
```

To disable setter method for `$prop2`:
```php
#[Get,Set]
class A
{
    use GetSetTrait;

    protected string $prop1;

    #[Set(false)]
    protected string $prop2;
}
```
### Mutator

Sometimes it's neccessary to pass the setter value through some method before assigning to property. This method is called _mutator_ and can be specified as second parameter for the `Set` attribute:
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

## Full API

### Exposing properties

1. Use `margusk\GetSet\GetSetTrait` inside the class which properties you want to expose
2. Add attribute `Get`, `Set` and/or `Delete` before the declaration of the property you want to expose. Alternatively if you want to expose all class properties at once, add the attribute before class declaration.
   * `margusk\GetSet\Attributes\Get(bool $enabled = true)`: allow or disable to read and use `isset()` on the property.
   * `margusk\GetSet\Attributes\Set(bool $enabled = true, string $mutator = null)`: allow or disable to update the property. Second argument denotes optional _Mutator_ method through which the value is passed through before assigning to property.
   * `margusk\GetSet\Attributes\Delete(bool $enabled = true)`: allow or disable to `unset()` the property.

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
