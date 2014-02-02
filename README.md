# [phat](../../)

PHP HTML utility module.

```php
use \phat\Html;

echo Html::tag('strong', ['class' => 'awesome'], 'It works');
```

## Methods

- `Html::tag($tagname, $atts?, $inner?)` Get an html tag (closed only if `$inner` is not `null`). `$atts` is string|array.
- `Html::atts($atts)` - Convert attributes into a properly encoded **string** for use in html. `$atts` is string|array.
- `Html::parseAtts($atts)` - Convert attributes into an **array** for use in PHP.
- `Html::esc($string)` - Escape a string for use in html. Ensure that entities are not double encoded.
- `Html::encode($value)` - Encode a value into a string for use in an html attribute. Uses `esc` or [`json_encode`](http://php.net/manual/en/function.json-encode.php) as needed.
- `Html::decode($value)` - Decode a value that was previously encoded via `encode` or [`json_encode`](http://php.net/manual/en/function.json-encode.php)
- `Html::implode($values, $delimiter?)` - Deep implode. Defaults to SSV.
- `Html::explode($values, $delimiter?)` - Explode by one or more delimiters. Defaults to SSV.
- `Html::respace($text, $replacement?)` Replace or normalize whitespace.
- `Html::rebreak($text, $replacement?)` Replace or normalize line breaks.
- `Html::dom($html)` - Parse markup (or an array of nodes) into a `DOMDocument` object.
- `Html::method($name)` - Get a fully-qualified method name for use like `array_map(Html::method('esc'), $array)`

`_e` methods [echo](http://php.net/manual/en/function.echo.php) the result of the underlying function via overloading. For example: 

- `Html::esc_e($string)`
- `Html::tag_e($tagname, $atts, $inner_html)`

## License

[MIT](http://opensource.org/licenses/MIT)