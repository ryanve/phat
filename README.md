# [phat](http://phat.airve.com) (v 2.3)

PHP HTML utility module.

**@package**: [airve/phat](https://packagist.org/packages/airve/phat)

`Phat` lives in the [`airve`](https://github.com/airve) [namespace](http://php.net/manual/en/language.namespaces.php). You can alias `Phat` locally via [`use`](http://php.net/manual/en/language.namespaces.importing.php):

```php
use \airve\Phat;

echo Phat::tag('strong', array('class' => 'awesome'), 'It works');
```

**or:**

```php
use \airve\Phat as Html;

echo Html::tag('strong', array('class' => 'awesome'), 'It works');
```

## methods

### `Phat::tag($tagname, $attrs, $inner)`

Get an html tag. `$tagname` must be a string. `$attrs` can be a string or array. The tag is closed only if `$inner` is not `null`.

### `Phat::attrs($attrs)`

Convert attributes into a properly encoded **string** for use in html. `$attrs` can be an array or string.

### `Phat::parseAttrs($attrs)`

Convert attributes into an **array** for use in PHP.

### `Phat::esc($string)`

Escape a string for use in html. Ensure that entities are not double encoded.

### `Phat::encode($value)`

Encode a value into a string for use in an html attribute. Uses `esc` or [`json_encode`](http://php.net/manual/en/function.json-encode.php) as needed.

### `Phat::decode($value)`

Decode a value that was previously encoded via `encode` or [`json_encode`](http://php.net/manual/en/function.json-encode.php)

### `Phat::method($name)`

Get a fully-qualified method name. 

## echoes

Method suffixed with `_e` are overloaded to [echo](http://php.net/manual/en/function.echo.php) the result of the underlying function:

### `Phat::esc_e($string)`

### `Phat::encode_e($value)`

### `Phat::tag_e($tagname, $attrs, $inner_html)`

### `Phat::attrs_e($attrs)`