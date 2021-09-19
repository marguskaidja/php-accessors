# GetSet

This library helps to create automatic getter / setter methods for class properties.

It's main usage would be to provide setter method chaining in DTO-s (Data Transfer Objects).

## Requirements

Only requirement is **PHP 8**.

## Examples

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
        return $this->prop2
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
```
This has lot's of boilerplate code just to achieve simple method chaining through setter methods.
The class above can be rewritten with much shorter code:

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
```

