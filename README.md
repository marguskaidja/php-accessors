[![Tests](https://github.com/marguskaidja/php-accessors/actions/workflows/tests.yml/badge.svg)](https://github.com/marguskaidja/php-accessors/actions/workflows/tests.yml)
# Accessors

This library can provide automatic accessors for object properties. It works by injecting a trait with [magic methods for property overloading](https://www.php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.members) into desired class.

Then, in case `protected`  property (_inaccessible for outside world_) is beeing accessed, property overloader will intervene and handle the situation depending of configuration.

Following features are present:
* Various syntax to choose from:
  * direct assignment syntax:
    * `$value = $foo->property`
    * `$foo->property = 'value'`
  * method syntax:
    * `$value = $foo->get('property')`
    * `$foo->setProperty('value')`
    * `$foo->set('property1', 'value1')`
    * `$foo->set(['property1' => 'value1', 'property2' => 'value2'])`
* All properties in a class can be marked accessible at once or one-by-one.
* Easy and unbloated configuration, implemented purely using [Attributes](https://www.php.net/manual/en/language.attributes.overview.php), which is native, fast and involves no DocBlock parsing. Also, no potentially conflicting data has to be injected into the class nor custom initialization code to be called to make things work.
* _Weak_ immutability support backed by _wither_ methods.
* Mutator support for _setters_.

## Requirements

PHP >= 8.0

## Installation

Install with composer:

```bash
composer require margusk/accessors
```

## Basic Usage

Consider following class with manually generated accessor methods:
```php
class A
{
    protected string $prop1 = "value1";

    protected string $prop2 = "value2";

    protected string $prop3 = "value3";

    public function getProp1(): string
    {
        return $this->prop1;
    }

    public function getProp2(): string
    {
        return $this->prop2;
    }

    public function getProp3(): string
    {
        return $this->prop2;
    }
}

$a = new A();
echo $a->getProp1() . "\n";  // Outputs "value1"
echo $a->getProp2() . "\n";  // Outputs "value2"
echo $a->getProp3() . "\n";  // Outputs "value3"
```
This has boilerplate code just to make 3 properties readable. In case there are tens of properties things could get quite tedious.

By using `Accessible` trait this class can be rewritten:

```php
use margusk\Accessors\Attributes\Get;
use margusk\Accessors\Accessible;

class A
{
    use Accessible;

    #[Get]
    protected string $prop1 = "value1";

    #[Get]
    protected string $prop2 = "value2";

    #[Get]
    protected string $prop3 = "value3";
}

$a = new A();
echo $a->getProp1() . "\n";  // Outputs "value1"
echo $a->getProp2() . "\n";  // Outputs "value2"
echo $a->getProp3() . "\n";  // Outputs "value3"
```

If you have lot's of properties to expose, then it's not reasonable to mark each one of them separately. Mark all properties at once in the class declaration:
```php
use margusk\Accessors\Attributes\Get;
use margusk\Accessors\Accessible;

#[Get]
class A
{
    use Accessible;

    protected string $prop1 = "value1";

    protected string $prop2 = "value2";

    protected string $prop3 = "value3";
}
```
Make all properties readable except `$prop2`:
```php
use margusk\Accessors\Attributes\Get;
use margusk\Accessors\Accessible;

#[Get]
class A
{
    use Accessible;

    protected string $prop1 = "value1";

    #[Get(false)]
    protected string $prop2 = "value2";

    protected string $prop3 = "value3";
}

// Throws InvalidArgumentException
echo (new A())->getProp2();      
```
What about writing to properties? Yes, just add `#[Set]` attribute:
```php
use margusk\Accessors\Attributes\{
    Get, Set
};
use margusk\Accessors\Accessible;

#[Get,Set]
class A
{
    use Accessible;

    protected string $prop1 = "value1";

    #[Get(false),Set(false)]
    protected string $prop2 = "value2";

    protected string $prop3 = "value3";
}

$a = new A();

// "prop1" is readable/writable
echo $a->setProp1("new value1")->getProp1();    // Outputs "new value1"

// "prop2" is only readable and throws exception if written to
$a->setProp2("new value2");                     // Throws InvalidArgumentException
```

**Note:** If `#[Set]` is enabled on property then it should be usually combined with _mutator_ and/or made _immutable_. Although it's technically okay to allow to just modify a property without any other intervention (like in example above), it wouldn't make much sense. If just numb write access is desired, then perhaps using just `public` visibility on property should be considered, because it skips all the overhead caused by current library.

### Immutable properties

Objects which allow their contents to be changed are named as **mutable**. And in contrast the ones who don't are [**immutable**](https://en.wikipedia.org/wiki/Immutable_object).

When talking about immutability, then it usually means combination of restricting the changes inside the original object, but allowing to make a copy of the object with desired changes.

This way original object stays intact and cloned object with changes can be used for new operations. 

Consider following situation:
```php
use margusk\Accessors\Attributes\Get;
use margusk\Accessors\Accessible;

#[Get]
class A
{
    use Accessible;

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

// Configure object $b, which differs from $a only by single value (property A::$f).
// But to achieve this, we have to retrieve all the rest of the values from object $a and
// pass to constructor to create new object.
// 
// This can result in unnecessary complexity.
$b = new B($a->a, $a->b,  $a->c,  $a->d,  $a->e,  7);
```

With `#[Immutable]` flag things can be written more simpler:
```php
use margusk\Accessors\Attributes\{
    Get, Set, Immutable
};
use margusk\Accessors\Accessible;

#[Get,Set,Immutable]
class A
{
    use Accessible;

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
* Immutability here is implemented _weakly_, not to be confused with [strong immutability](https://en.wikipedia.org/wiki/Immutable_object#Weak_vs_strong_immutability). For example:
    * There's no rule how much of the object should be made immutable. It can be only one property or whole object (all properties) if wanted.  
    * Nested immutability is not enforced, thus property can contain another mutable object.
    * Immutable properties can be still changed inside the owner object.
* To prevent ambiguity, immutable properties must be changed using  method `with` instead of `set`. Using `set` results in exception.
* Unsetting immutable properties is not possible and results in exception.

### Mutator

Sometimes it's handy to proxy the setter value through some intermediate method before assigning to property. This method is called _mutator_ and can be specified as second parameter for the `#[Set]` attribute:
```php
use margusk\Accessors\Attributes\{
    Get, Set
};
use margusk\Accessors\Accessible;

#[Get]
class A
{
    use Accessible;

    #[Set(true, "htmlspecialchars")]
    protected string $prop1;

    protected string $prop2;
}

echo (new A())->setProp1('<>')->getProp1();  // Outputs "&lt;&gt;"
```

It can validate and/or tweak the value before beeing assigned to property.

_Mutator_ parameter must be string or array representing a PHP callable. Following callable syntaxes are supported:
1. `<function>` 
1. `<class>::<method>` 
1. `$this-><method>` (`$this` is replaced in runtime with the object instance in which context the accessor is currently executing)

It can contain a special variable named `%property%` which is replaced during parsing phase with the property name it applies. This is useful only when specifying mutator globally in class attribute.

Specified callable must accept assignable value as first parameter and must return a value to be assigned to property.

### Unsetting property

It's also possible to unset property's value by using attribute `#[Delete]`:
```php
use margusk\Accessors\Attributes\{
    Get, Delete
};
use margusk\Accessors\Accessible;

#[Get]
class A
{
    use Accessible;

    #[Delete]
    protected string $prop1;

    protected string $prop2;
}

(new A())->unsetProp1();
```

Why `Delete` in attribute name instead of `Unset`? Because `Unset` is reserved word and can't be used as attribute nor class name.

### Existing getter/setter methods

The library can also work with existing setter/getter methods:
```php
use margusk\Accessors\Attributes\{
    Get, Set
};
use margusk\Accessors\Accessible;

#[Get,Set]
class A
{
    use Accessible;

    public bool $prop1Set = false;

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
  * from existing `with` method is proxied back to original caller only if the result is `object` and derives from current class. Other return values are silently discarded and original caller gets `clone`-d object instance.

   
### Class inheritance

Attribute inheritance works intuitively. Attributes in parent class declaration are inherited by child class and can be overwritten (except `ICase` and `Immutable`):

```php
use margusk\Accessors\Attributes\{
    Get, Set
};
use margusk\Accessors\Accessible;

#[Get,Set]
class A
{
    use Accessible;
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
use margusk\Accessors\Attributes\{
    Get, Set
};
use margusk\Accessors\Accessible;

#[Get,Set]
class A
{
    use Accessible;

    protected string $prop1;
    protected string $PROP1;
}

$obj = new A();
$obj->setProp1('value');        // Case insensitive => A::$PROP1 is modified
$obj->prop1('value');           // Case insensitive => A::$PROP1 is modified
```
2. In all other situations, the property names are treated case-sensitive by default.

```php
$obj->set('prop1', 'value');    // Case sensitive => A::$prop1 is modified
echo $obj->prop1;               // Case sensitive => A::$prop1 is returned
echo $obj->Prop1;               // Throws InvalidArgumentException 
```
  
Case-insensitivity for all situations can be turned on by adding `ICase` attribute to class declaration. Attribute must be added to whole class (thus not to properties) and can't be reverted in child classes to prevent ambiguouty in the class hierarchy.
```php
use margusk\Accessors\Attributes\{
    Get, Set, ICase
};
use margusk\Accessors\Accessible;

#[Get,Set]
class A
{
    use Accessible;

    protected string $prop1;
    protected string $PROP1;
}

$obj = new A();
$obj->Prop1 = 'value';   // Case insensitive => A::$PROP1 is modified
echo $obj->Prop1;        // Case insensitive => A::$PROP1 is returned 
```   
However the recommended way is leave the case-sensitivity on and always access the property by the name it's declared in the class to have the same consistent code throughout whole codebase.


### IDE autocompletion

Using magic methods brings the disadvantages of losing IDE autocompletion and make static code analyzers grope in the dark.

To inform static code parsers about available magic methods and properties, PHPDoc [@method](https://docs.phpdoc.org/3.0/guide/references/phpdoc/tags/method.html) and/or [@property](https://docs.phpdoc.org/3.0/guide/references/phpdoc/tags/property.html) tags can be specified in front of the class:
```php
use margusk\Accessors\Attributes\{
    Get, Set
};
use margusk\Accessors\Accessible;

/**
 * @property        $prop1
 * @property-read   $prop2
 * 
 * @method string   getProp1()
 * @method self     setProp1(string $value)
 * @method string   getProp2()
 */
#[Get,Set]
class A
{
    use Accessible;

    protected string $prop1 = "value1";
    
    #[Set(false)]
    protected string $prop2 = "value1";
}
```   


## Full API

### Exposing properties

1. Use `margusk\Accessors\Accessible` inside the class which properties you want to expose
2. Add attribute `#[Get]`, `#[Set]` and/or `#[Delete]` before the declaration of the property you want to expose. Alternatively if you want to expose all class properties at once, add the attribute before class declaration:
   * `margusk\Accessors\Attributes\Get(?bool $enabled = true)`: allow or disable to read and use `isset()` on the property.
   * `margusk\Accessors\Attributes\Set(?bool $enabled = true, string $mutator = null)`: allow or disable to update the property. Second argument denotes optional _Mutator_ method through which the value is passed through before assigning to property.
   * `margusk\Accessors\Attributes\Delete(?bool $enabled = true)`: allow or disable to `unset()` the property.
3. Attribute `#[ICase]`:
   * `margusk\Accessors\Attributes\ICase()`: make accessing the property names case-insensitive. This can be added only to class declaration and can't be reverted later.
4. Attribute `#[Immutable]`:
   * `margusk\Accessors\Attributes\Immutable()`: turn on immutable flag for single property or whole class. Once the flag is added, it can't be reverted later. 

Note:
   * `null` value can be also used for `$enabled`, if you don't want to change the setting inherited from parent's declaration. This is currently useful only for `#[Set]` attribute where in class declaration there is default _mutator_ method defined and it needs to be changed by inherited class or property.

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
