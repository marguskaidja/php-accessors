[![Tests](https://github.com/marguskaidja/php-accessors/actions/workflows/tests.yml/badge.svg)](https://github.com/marguskaidja/php-accessors/actions/workflows/tests.yml)
# Accessors

Current library can create automatic accessors (e.g. _getters_ and _setters_) for object properties. It works by injecting a trait with [magic methods for property overloading](https://www.php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.members) into desired class, which will then handle situations, where _inaccessible_ (`private`/`protected`) property is accessed.

#### Features
* Multiple accessor **syntaxes**:
    * direct assignment syntax:
        * `$value = $foo->property`
        * `$foo->property = 'value'`
    * method syntax:
        * `$value = $foo->get('property')`
        * `$value = $foo->property()`
        * `$foo->property('value')`
        * `$foo->setProperty('value')`
        * `$foo->set('property1', 'value1')`
        * `$foo->set(['property1' => 'value1', 'property2' => 'value2', ..., 'propertyN' => 'valueN'])`
* Easy and straightforward **configuration** using [Attributes](https://www.php.net/manual/en/language.attributes.overview.php):
    * No custom initialization code has to be called from class constructors to make things work.
    * Accessors can be configured _per_ property or for all class at once.
    * Inheritance and override support. E.g. set default behaviour for whole class and make exceptions based on specific properties.
    * No variables, functions or methods (except `__get()`/`__set()`/`__isset()`/`__unset()`/`__call()`) will be polluted into user classes or global namespace.
    * [_DocBlock_](https://docs.phpdoc.org/3.0/guide/references/phpdoc/tags/property.html) tags `@property`, `@property-read` and `@property-write` are also supported and can be used instead of Attributes on basic cases.
* _Weak_ **immutability** support backed by _wither_ methods.
* **Mutator** support for _setters_.

## Requirements

PHP >= 8.0

## Installation

Install with composer:

```bash
composer require margusk/accessors
```

## Usage

Consider following class with manually generated accessor methods:
```php
class A
{
    protected string $foo = "foo";

    protected string $bar = "bar";

    protected string $baz = "baz";

    public function getFoo(): string
    {
        return $this->foo;
    }

    public function getBar(): string
    {
        return $this->bar;
    }

    public function getBaz(): string
    {
        return $this->baz;
    }
}

$a = new A();
echo $a->getFoo();  // Outputs "foo"
echo $a->getBar();  // Outputs "bar"
echo $a->getBaz();  // Outputs "baz"
```
This has boilerplate code just to make 3 properties readable. In case there are tens of properties things could get quite tedious.

By using `Accessible` trait this class can be rewritten:

```php
use margusk\Accessors\Attr\Get;
use margusk\Accessors\Accessible;

class A
{
    use Accessible;

    #[Get]
    protected string $foo = "foo";

    #[Get]
    protected string $bar = "bar";

    #[Get]
    protected string $baz = "baz";
}

$a = new A();
echo $a->getFoo();  // Outputs "foo"
echo $a->getBar();  // Outputs "bar"
echo $a->getBaz();  // Outputs "baz"
```
Besides the fact that boilerplate code for _getters_ has been avoided, there's also  _direct assignment_ syntax available, which wasn't even possible with initial object:
```php
echo $a->foo;  // Outputs "foo"
echo $a->bar;  // Outputs "bar"
echo $a->baz;  // Outputs "baz"
```

### Further examples
If there's  lot's of properties to expose, then it's not reasonable to mark each one of them separately. Mark all properties at once in the class declaration:

```php
use margusk\Accessors\Attr\Get;
use margusk\Accessors\Accessible;

#[Get]
class A
{
    use Accessible;

    protected string $foo = "foo";

    protected string $bar = "bar";

    protected string $baz = "baz";
}
```
Make all properties readable except `$bar`:

```php
use margusk\Accessors\Attr\Get;
use margusk\Accessors\Accessible;

#[Get]
class A
{
    use Accessible;

    protected string $foo = "foo";

    #[Get(false)]
    protected string $bar = "bar";

    protected string $baz = "baz";
}
$a = new A();
echo $a->getFoo();   // Outputs "foo"
echo $a->getBar();   // Results in Exception
```
What about writing to properties? Yes, just add `#[Set]` attribute:

```php
use margusk\Accessors\Attr\{
    Get, Set
};
use margusk\Accessors\Accessible;

#[Get,Set]
class A
{
    use Accessible;

    protected string $foo = "foo";

    #[Get(false),Set(false)]
    protected string $bar = "bar";
}

$a = new A();
echo $a->setFoo("new foo")->getFoo();  // Outputs "new foo"
$a->setBar("new bar");                 // Results in Exception
```

What about _DocBlock_? Yes! Same class from above, but now configured using _DocBlock_ tags:
```php
use margusk\Accessors\Accessible;

/**
 * @property        string $foo 
 * @property-read   string $bar
 */
class A
{
    use Accessible;

    protected string $foo = "foo";

    protected string $bar = "bar";
}

$a = new A();
echo $a->setFoo("new foo")->getFoo();   // Outputs "new foo"
echo $a->bar;                           // Outputs "bar"
$a->setBar("new bar");                  // Results in Exception
```

### Immutable properties

Objects which allow their contents to be changed are named as **mutable**. And vice versa, the ones who don't are [**immutable**](https://en.wikipedia.org/wiki/Immutable_object).

When talking about immutability, then it usually means combination of restricting the changes inside the original object, but providing functionality to copy/clone object with desired changes.  This way original object stays intact and cloned object with changes can be used for new operations.

Consider the following example:
```php
use margusk\Accessors\Attr\Get;
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
// This results in unnecessary complexity and unreadability.
$b = new B($a->a, $a->b,  $a->c,  $a->d,  $a->e,  7);
```

With `#[Immutable]` attribute things get simpler:

```php
use margusk\Accessors\Attr\{
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

// Original object still stays intact
echo (int)($a === $b); // Outputs "0"
echo $a->f; // Outputs "6"
echo $b->f; // Outputs "7"
```

Notes:
* Immutability here is _weak_ and should not to be confused with [strong immutability](https://en.wikipedia.org/wiki/Immutable_object#Weak_vs_strong_immutability):
    * There's no rule how much of the object should be made immutable. It can be only one property or whole object (all properties) if wanted.
    * Nested immutability is not enforced, thus property can contain another mutable object.
    * Immutable properties can be still changed inside the owner object.
* To prevent ambiguity, immutable properties must be changed using  method `with` instead of `set`. Using `set` for immutable properties results in exception and vice versa.
* Unsetting immutable properties is not possible and results in exception.

### Mutator

With _setters_, it's sometimes necessary to have the assignable value passed through some intermediate function, before assigning to property. This function is called _mutator_ and can be specified using `#[Mutator]` attribute:

```php
use margusk\Accessors\Attr\{
    Get, Set, Mutator
};
use margusk\Accessors\Accessible;

#[Get]
class A
{
    use Accessible;

    #[Set,Mutator("htmlspecialchars")]
    protected string $foo;
}

$a = (new A());
$a->setFoo('<>');
echo $a->getFoo();      // Outputs "&lt;&gt;"
```

It can validate or otherwise manipulate the value before beeing assigned to property.

_Mutator_ parameter must be string or array representing a PHP `callable`. When string is passed then it must have one of following syntaxes:
1. `<function>`
1. `<class>::<method>`
1. `$this-><method>` (`$this` is replaced during runtime with the object instance where the accessor belongs)

It may contain special variable named `%property%` which is replaced with the property name it applies. This is useful when using separate mutator for each property but declaring it only once within class attributes.

The callable function/method must accept assignable value as first parameter and must return a value to be assigned to property.

### Accessor endpoints

Current library can make use of any manually created accessor methods with prefixes `set`, `get`, `isset`, `unset` or `with` and followed by property name. Those are here referred as accessor endpoints.

For example, this allows seamless integration where current library provides _direct assignment syntax_ on top of existing _method syntax_:
```php
use margusk\Accessors\Attr\{
    Get, Set
};
use margusk\Accessors\Accessible;

#[Get,Set]
class A
{
    use Accessible;

    protected int $foo = 0;

    public function getFoo(): int
    {
        return $this->foo + 1;
    }

    public function setFoo(int $value): void
    {
        $this->foo = $value & 0xFF;
    }
}

$a = new A();
$a->foo = 1023;
echo $a->foo;         // Outputs "256" instead of "1023"
echo $a->getFoo();    // Outputs "256" instead of "1023"
```

The 2 endpoints (`getFoo`/`setFoo`) will be called in every situation:
* either when property is accessed with direct assignment syntax (e.g. `$a->foo`)
* or when property is accessed with method syntax (e.g. `$a->getFoo()`)
    * if the visibility of endpoint is `public`, then it's naturally called by PHP engine.
    * if it's `private`/`protected`, then it goes through `__call` magic method provided by `Accessible` trait.

Notes:
* To have endpoint detected, it's name must start with string `set`, `get`, `isset`, `unset` or `with` and followed with property name.
* Only instance methods are detected (`static` methods wont work).
* _mutator_ is bypassed and should be done inside the setter endpoint itself.
* Return values from endpoints are handled as following. Values from:
    * `get` and `isset` are handed over to caller.
    * `set` and `unset` are discarded and current object instance is always returned.
    * `with` endpoint are handed over to caller **only if** value is `object` and derived from current class. Other values are discarded and original caller gets `clone`-d object instance.

### Unsetting properties

If there's ever necessity, then it's also possible to unset properties with using `#[Delete]`:

```php
use margusk\Accessors\Attr\{
    Get, Delete
};
use margusk\Accessors\Accessible;

#[Get]
class A
{
    use Accessible;

    #[Delete]
    protected string $foo;

    protected string $bar;
}

$a = new A();
$a->unsetFoo();     // Ok.
unset($a->foo);     // Ok.
unset($a->bar);     // Results in Exception
```

Notes:
* since `Unset` is reserved word, `Delete` was chosen for attribute name instead.

### Configuration inheritance

Inheritance is quite straightforward. Attributes in parent class are inherited by children and can be overwritten (except for `Immutable`):

```php
use margusk\Accessors\Attr\{
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
    protected string $foo;

    #[Set(false)]
    protected string $bar;
}

$b = new B();
$b->foo = 'new foo';
echo $b->foo;           // Outputs "new foo"
$b->bar = 'new bar';    // Results in Exception
```

### Case sensitivity in property names

Following rules apply when dealing with case-sensitivity in property names:
1. When property is accessed with method syntax, where property name is part of method name, then it's treated as case-insensitive. Thus if for whatever reason you have properties which differ only in casing, then the last defined property is used:

```php
use margusk\Accessors\Attr\{
    Get, Set
};
use margusk\Accessors\Accessible;

#[Get,Set]
class A
{
    use Accessible;

    protected string $foo = 'foo';
    protected string $FOO = 'FOO';
}

$a = new A();
$a->setFoo('value');        // Case insensitive => A::$FOO is modified
$a->foo('value');           // Case insensitive => A::$FOO is modified
$a->Foo('value');           // Case insensitive => A::$FOO is modified
```
2. For all other situations, the property names are always treated as _case-sensitive_:

```php
$a->set('foo', 'value');    // A::$foo is modified
echo $a->foo;               // Outputs "foo"
echo $a->FOO;               // Outputs "FOO"
echo $a->Foo;               // Results in Exception because property "Foo" doesn't exist
```

### IDE autocompletion

Having accessors with _magic methods_ can bring the disadvantages of losing somewhat of IDE autocompletion and make static code analyzers grope in the dark.

To inform static code parsers about available magic methods and properties, PHPDoc [@method](https://docs.phpdoc.org/3.0/guide/references/phpdoc/tags/method.html) and/or [@property](https://docs.phpdoc.org/3.0/guide/references/phpdoc/tags/property.html) tags can be specified in front of the class:

```php
use margusk\Accessors\Accessible;

/**
 * @property        string $foo
 * @property-read   string $bar
 * 
 * @method string   getFoo()
 * @method self     setFoo(string $value)
 * @method string   getBar()
 */
class A
{
    use Accessible;

    protected string $foo = "foo";
    
    protected string $bar = "bar";
}
$a = new A();
echo $a->setFoo('foo is updated')->foo; // Outputs "foo is updated"
echo $a->bar; // Outputs "bar"
```   

Since `@property[<-read>|<-write>]` tags act also for exposing properties (instead of Attributes), you get automatically the documented behaviour.

## API

### Exposing properties

1. Use `margusk\Accessors\Accessible` trait inside the class which properties you want to expose.
2. Configure with following attributes:
   *  Use `#[Get]`, `#[Set]` and/or `#[Delete]` (from namespace `margusk\Accessors\Attr`) before the declaration of the property or whole you want to expose:
       * All them take optional `bool` parameter which can be set to `false` to deny specific accessor for the property or whole class. This is useful in situtations where override from previous setting is needed.
       * `#[Get(bool $enabled = true)]`: allow or disable read access to property. Works in conjunction with allowing/denying `isset` on property.
       * `#[Set(bool $enabled = true)]`: allow or disable to write access the property.
       * `#[Delete(bool $enabled = true)]`: allow or disable `unset()` of the property.
   * `#[Mutator(string|array|null $callback)]`:
       * the `$callback` parameter works almost like `callable` but with a tweak in `string` type:
       * if `string` type is used then it must contain regular function name or syntax `$this->someMutatorMethod` implies instance method.
       * use `array` type for specifying static class method.
       * and use `null` to discard any previously set mutator.
   * `#[Immutable]`: turns an property or whole class immutable. Once the attribute is added, it can't be disabled later.

### Properties can be accessed with following syntaxes:

#### Reading properties:
* `$value = $obj->foo;`
* `$value = $obj->getFoo();`
* `$value = $obj->get('foo');`
* `$value = $obj->foo();`

#### Updating mutable properties (allows method chaining):
* `$a->foo = 'new foo';`
* `$a = $a->setFoo('new foo')->setBar('new bar');`
* `$a = $a->set('foo', 'new foo')->set('bar', 'new bar');`
* `$a = $a->set(['foo' => 'new foo', 'bar' => 'new bar', ..., 'baz' => 'new baz');`
* `$a = $a->foo('new foo')->bar('new bar');`

#### Updating immutable properties (allows method chaining):
* `$b = $a->withFoo('new foo')->withBar('new bar');`
* `$b = $a->with('foo', 'new foo')->with('bar', 'new bar');`
* `$b = $a->with(['foo' => 'new foo', 'bar' => 'new bar', ..., 'baz' => 'new baz');`

#### Unsetting properties (allows method chaining):
* `unset($a->foo);`
* `$a = $a->unsetFoo()->unsetBar();`
* `$a = $a->unset('foo')->unset('bar');`
* `$a = $a->unset(['foo', 'bar', ..., 'baz');`

#### Checking if property is initialized (returns `bool`):
* `$isFooSet = isset($a->foo);`
* `$isFooSet = $a->issetFoo();`
* `$isFooSet = $a->isset('foo');`
