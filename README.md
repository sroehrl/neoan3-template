# neoan3-apps/template
PHP template engine

[![Test Coverage](https://api.codeclimate.com/v1/badges/76b09924300375c4d79a/test_coverage)](https://codeclimate.com/github/sroehrl/neoan3-template/test_coverage)
[![Maintainability](https://api.codeclimate.com/v1/badges/76b09924300375c4d79a/maintainability)](https://codeclimate.com/github/sroehrl/neoan3-template/maintainability)
[![Build Status](https://travis-ci.org/sroehrl/neoan3-template.svg?branch=master)](https://travis-ci.org/sroehrl/neoan3-template)

> As of version 2, we dropped PHP 7.4 support. 
> You require at least PHP 8.0 or use v1.2.0 of this package.

## Installation / Quick start

`composer require neoan3-apps/template`

```php
use Neoan3\Apps\Template\Constants;
use Neoan3\Apps\Template\Template;

require_once 'vendor/autoload.php';

// optional, if set, path defines the relative starting point to templates
Constants::setPath(__DIR__ . '/templates');

echo Template::embrace('<h1>{{test}}</h1>',['test'=>'Hello World']);
```

## Contents

- [Templating](#templating)
- [Iterations](#iterations-n-for)
- [Conditions](#conditions-n-if)
- [Custom Functions](#custom-functions)
- [Custom Delimiter](#custom-delimiter)
- [Custom Attributes](#custom-attributes)
- [OOP](#oop)
- [Tips](#tips)


## Templating
**neoan3-template** is not a full blown template engine, but rather what a template engine should be: 
With modern JavaScript solutions creating a dynamic approach, neoan3-template focuses on the necessities of static rendering. 


_profile.html_
```HTML
<h1>{{user}}</h1>
<p>{{profile.name}}</p>
<p n-for="items as key => item" n-if="key > 0">{{item}}-{{key}}</p>

```
_profile.php_
```PHP
$dynamicContent = [
    'user' => 'Test',
    'items' => ['one','two'],
    'profile' => [
        'name' => 'John Doe',
        ...
    ]
];
echo \Neoan3\Apps\Template\Template::embraceFromFile('profile.html',$dynamicContent);
```
_output_
```HTML
<h1>Test</h1>
<p>John Doe</p>
<p>two-1</p>
```

### Main templating methods
#### embrace($string, $substitutionArray)
Replaces array-keys indicated by double curly braces with the appropriate value
#### embraceFromFile($fileLocation, $substitutionArray)
Reads content of a file and executes the embrace function.


## Iterations (n-for)

The n-for loop evaluates as PHP's foreach and uses the same syntax (excluding the $-sign).
In order to access keys, use the PHP markup (items as key => value) to emulate $items as $key => $value.
Without the necessity to access keys, use the simple markup items => item.
```PHP
$parameters = [
    'items' => ['one', 'two']
];
$html = '<div n-for="items as item">{{item}}</div>';
echo \Neoan3\Apps\Template::embrace($html, $parameters);
```
Output:
```html
<div>one</div>
<div>two</div>

```


## Conditions (n-if)

You can attach the n-if attribute to any tag in order to render conditionally. 
Curly braces are not required and nesting follows the same rules as general rendering 
(meaning that you will write `outer.inner` to access `$parameters['outer']['inner']`)

n-if is type conscious and therefore evaluates strict.

| true | false |
| --- | --- |
| false == 'false' | false === 'false' |

Conditions work in nested context and inherit naming. 

Example within n-for:
```php
$parameters = [
    'items' => ['one', 'two']
];
$html = '<div n-for="items as item"><span n-if="item != \'one\'">{{item}}</span></div>';
echo \Neoan3\Apps\Template::embrace($html, $parameters);

```
Output:

```html
<div></div>
<div><span>two<span></div>

```

## Custom functions

Unlike other template engines, **neoan3-apps/template** does not come with expensive additional functionality.
It is our believe that most of what other template engines offer should not be included in a template engine.
However, you can pass in custom closures to achieve custom transformation or similar.

Example:

```PHP
$html = '<h3>{{headline(items.length)}}</h3><p n-for="items as item">{{toUpper(item)}}</p>';

$passIn = [
    'items'=>['chair', 'table']
];
// pluralize
\Neoan3\Apps\Template\Constants::addCustomFunction('headline', function ($input){
    return $input . ' item' . ($input>1 ? 's' : '');
});
// transform uppercase
\Neoan3\Apps\Template\Constants::addCustomFunction('toUpper', function ($input){
    return strtoupper($input);
});
echo \Neoan3\Apps\Template::embrace($html, $passIn);
```

Output:

```html
<h3>2 items</h3>
<p>CHAIR</p>
<p>TABLE</p>

```

## Custom delimiter

There is a reason why curly braces are used: You can leverage the fact that some values are only potentially filled by the backend and 
addressed in the front-end if the value does not exist in your data (yet).
However, there are also cases where you want to specifically avoid having your front-end 
framework picking up unfilled variables or you have special parsing needs to work with various files.
Therefore, you can use custom identifiers by providing your desired markup to **embrace** or **embraceFromFile**.

Example:

```php
$html = '
<p>[[name]]</p>
<p>Here is content</p>
';

$substitutions = [
    'name' => 'neoan3'
];
// characters are escaped automatically
\Neoan3\Apps\Template\Constants::setDelimiter('[[',']]');

echo \Neoan3\Apps\Template\Template::embrace($html, $substitutions);
```

Output:

```html
<p>neoan3</p>
<p>Here is content</p>
```

**NOTE:** If your delimiter is a tag, the engine will **NOT** remove the delimiter:

```php 
use \Neoan3\Apps\Template\Constants;
use \Neoan3\Apps\Template\Template;

Constants::setDelimiter('<translation>','</translation>');

$esperanto = [
    'hello' => 'saluton'
];

$user => ['userName' => 'Sammy', ...];

$html = "<h1><translation>hello</translation> {{userName}}</h1>";

$translated = Template::embrace($html, $esperanto);

Constants::setDelimiter('{{','}}');

echo Template::embrace($translated, $user);
```

Output:

```html
<h1><translation>saluton</translation> Sammy</h1>
```

## Custom Attributes

Under the hood, n-if and n-for are just custom attributes.
You can add your own attributes and extend the engine to your needs using any callable:

```php 
use \Neoan3\Apps\Template\Constants;

class TranslateMe
{
    private string $language;
    
    // you will receive the native DOMAttr from DOMDocument
    // and the user-provided array
    function __invoke(\DOMAttr &$attr, $contextData = []): void
    {
        // here we are going to use the "flat" version of the context data
        // it translates something like 
        // ['en' => ['hallo'=>'hello']] to ['en.hallo' => 'hello]
        $flatValues = Constants::flattenArray($contextData[$this->language]);
    
        // if we find the content of the actual element in our translations:
        if(isset($flatValues[$attr->parentNode->nodeValue])){
            $attr->parentNode->nodeValue = $flatValues[$attr->parentNode->nodeValue];
        }
    }
    function __construct(string $lang)
    {
        $this->language = $lang;
    }
}

```
```html
<!-- main.html -->
<p translate>hallo</p>
```
```php 
use \Neoan3\Apps\Template\Constants;
use \Neoan3\Apps\Template\Template;
...
$translations = [
    'en' => [
        'hallo' => 'hello',
        ...
    ],
    'es' => [
        'hallo' => 'hola',
        ...
    ]
];

$userLang = 'en';

Constants::addCustomAttribute('translate', new TranslateMe($userLang));
echo Template::embraceFromFile('/main.html', $translations)

```

## OOP

So far, we have used a purely static approach. However, the "Template" methods are merely facades resulting in the initialization 
of the Interpreter. If you need more control over what is happening, or it better fits your situation, you are welcome to use it directly:

```php 
$html = file_get_contents(__DIR__ . '/test.html);

$contextData = [
    'my' => 'value'
];
$templating = new \Neoan3\Apps\Template\Interpreter($html, $contextData);


// at this point, nothing is parsed or set if we wanted to use the attributes n-if or n-for, we would have to set it
// note how we are free to change the naming now

\Neoan3\Apps\Template\Constants::addCustomAttribute('only-if', new \Neoan3\Apps\Attributes\NIf());

// Let's parse in one step:
$templating->parse();

// And output

echo $templating->asHtml();

```

## Tips

There are a few things that are logical, but not obvious:

### Parents on loops

Due to processing in hierarchy, many internal parsing operations can cause race-conditions.
Imagine the following HTML:
```html
// ['items' => ['one','two'], 'name' => 'John']
<div>
    <p n-for="items as item">{{item}} {{name}}</p>
    <p>{{name}}</p>
</div>
```
In this scenario, the parser would first hit the attribute `n-for`
and add p-tags to the parent node (here div). `n-for` now takes control of this parent and
interprets the children. As the context gets reevaluated in every loop, but the second p-tag is not iterated,
the resulting output would be:
```html
<div>
    <p>{{name}}</p>
    <p>one John</p>
    <p>two John</p>
</div>
```
It is therefore recommended to use **one** distinct parent when using attribute methods:
```html
// ['items' => ['one','two'], 'name' => 'John']
<div>
    <p n-for="items as item">{{item}} {{name}}</p>
</div>
<div>
    <p>{{name}}</p>
</div>
```

### Flat array properties

The interpreter "flattens" the given context-array
in order to allow for the dot-notation. In this process generic values are added:
```html
// ['items' => ['one','two'], 'deepAssoc' => ['name' => 'Tim']]
<p>{{items}}</p>
<p>{{items.0}}</p>
<p>{{items.length}}</p>
<p>{{deepAssoc.name}}</p>
```
output:
```html
<p>Array</p>
<p>one</p>
<p>2</p>
<p>Tim</p>
```
If needed, you can use this in logic:
```html
<div n-if="items == 'Array'">
    <ul>
        <li n-for="items as item">{{item}}</li>
    </ul>
</div>
```