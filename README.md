# neoan3-apps/template
neoan3 minimal template engine

[![Test Coverage](https://api.codeclimate.com/v1/badges/76b09924300375c4d79a/test_coverage)](https://codeclimate.com/github/sroehrl/neoan3-template/test_coverage)
[![Maintainability](https://api.codeclimate.com/v1/badges/76b09924300375c4d79a/maintainability)](https://codeclimate.com/github/sroehrl/neoan3-template/maintainability)
[![Build Status](https://travis-ci.org/sroehrl/neoan3-template.svg?branch=master)](https://travis-ci.org/sroehrl/neoan3-template)

## Installation / Quick start

`composer require neoan3-apps/template`

```php
use Neoan3\Apps\TemplateFunctions;
use Neoan3\Apps\Template;

require_once 'vendor/autoload.php';

// optional, if set, path defines the relative starting point to templates
define('path',__DIR__);

echo Template::embrace('<h1>{{test}}</h1>',['test'=>'Hello World']);
```

## Templating
**neoan3-template** is not a full blown template engine, but rather what a template engine should be: 
With modern JavaScript solutions creating a dynamic approach, neoan3-template focuses on the necessities of static rendering. 

_NOTE:_ For historical reasons, neoan3-apps/ops inherits all neoan3-template functions.
Refactoring is not necessary, but we suggest using the class **Template** instead of **Ops**
going forward to account for eventual future deprecation.

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
echo \Neoan3\Apps\Template::embraceFromFile('profile.html',$dynamicContent);
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
>When using Neoan3, the location starts at the root of your application. As a stand-alone, either define "path" accordingly (as a global constant) or use the fallback to the root of the server.

#### hardEmbrace($string, $substitutionArray)
When working with front-end technology, the similarity of markup can either be wanted (e.g. Vue fills content PHP could not), or ambiguous.
You can choose "hardEmbrace" to use double hard brackets instead.

`<h1>[[key]]</h1>`
#### tEmbrace($string, $substitutionArray)
Used for i18n (internationalization) in Neoan3, this method replaces based on t-tags in your markup.
```PHP
$german = ['hello'=>'hallo'];
$html = '
    <h1><t>hello</t></h1>
';
\Neoan3\Apps\Template::tEmbrace($html, $german);
```
Output:
`<h1>hallo</h1>`

_NOTE:_ tEmbrace assumes that dynamically calling translations is cost intensive. 
Unlike "embrace", "hardEmbrace" and "embraceFromFile" it does not 
support any functionality other than substitution.  

## loop (n-for)

The n-for loop evaluates as PHP's foreach and uses the same syntax (excluding the $-sign).
In order to access keys, use the PHP markup (items as key => value) to emulate $items as $key => $value.
Without the necessity to access keys, use the simple markup items => item.
```PHP
$parameters = [
    'items' => ['one', 'two']
];
$html = '<div n-for="items in item">{{item}}</div>';
echo \Neoan3\Apps\Template::embrace($html, $parameters);
```
Output:
```html
<div>one</div>
<div>two</div>

```


## Condition (n-if)

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
$html = '<div n-for="items in item"><span n-if="item != \'one\'">{{item}}</span></div>';
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
$html = '<p n-for="items as item">{{toUpper(item)}}</p>';

$passIn = [
    'items'=>['chair', 'table']
];
\Neoan3\Apps\TemplateFunctions::registerClosure('toUpper', function ($input){
    return strtoupper($input);
});
echo \Neoan3\Apps\Template::embrace($html, $passIn);
```

Output:

```html
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
<!-- name -->
<p>Here is content</p>
';

$substitutions = [
    'name' => 'neoan3'
];
// characters are escaped automatically
\Neoan3\Apps\TemplateFunctions::setDelimiter('<!--','-->');

echo \Neoan3\Apps\Template::embrace($html, $substitutions);
```

Output:

```html
<!-- neoan3 -->
<p>Here is content</p>
```
