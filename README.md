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
    
