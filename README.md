# feijaoVermelho
ORM based on RedBeanPHP

## naming
"feij&atilde;o vermelho" simply means "red bean" in portuguese.

## idea
Since i am using RedBeanPHP in about a dozen projects or so, i really love the easypeasy possibilities to put objects into databases, read them, change them... A far as you design your objects to use RedBean from ground up, no problem.

What i wanted to archieve with this little lib is to use RedBeanPHP transparantly in the background for mostly an class/object in your projects... 

## installation

```bash
composer require openwebx/feijaovermelho
```

## usage

using it for easy stuff like saving objects to database, fetching objects from database or "upserting" is totally straight forward:

### saving your object to database

```php
<?php
use openWebX\feijaoVermelho\feijaoVermelho

class Test {
    // use our trait...
    use feijaoVermelho;
    
    // define some public properties
    public int $intValue;
    public string $stringValue;
}

// now simply let feijaoVermelho do its magic:
$myTest = new Test();
$myTest->intValue = 666;
$myTest->stringValue = 'this is a test';
$myTest->save();
```

when database is reachable and configured correctly (see configuration...) then there will be a table named "test" with at least one entry with id, int_value and string_value as fields...

### loading objects from database

now lets get our object from database:
```php
<?php
[...]
$myNewObject = new Test();
$myNewObject->intValue = 666;
$myNewObject->loadByIntValue();

echo $myNewObject->stringValue; // -> this is a test


    
