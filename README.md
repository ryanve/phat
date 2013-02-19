# [phat](https://github.com/ryanve/phat)

PHP functions for HTML markup. 

All functions live under the "phat" [namespace](http://php.net/manual/en/language.namespaces.php).

## getters

### `esc( $string )`

Escape a string for use in html. Ensure that entities are not double encoded.

### `tag( $tagname, $attrs, $inner_html )`

Get an html tag. `$tagname` must be a string. `$attrs` can be a string or array. The tag is closed only if `$inner_html` is not `null`.

### `attrs( $attrs )`

Convert attributes into a properly encoded **string** for use in html. `$attrs` can be an array or string.

### `parse_attrs( $attrs )`

Convert attributes into an **array** for use in PHP.

### `encode( $value )`

Encode a value into a string for use in an html attribute. Uses `esc` or [`json_encode`](http://php.net/manual/en/function.json-encode.php) as needed.

### `decode( $value )`

Decode a value that was previously encoded via `encode` or [`json_encode`](http://php.net/manual/en/function.json-encode.php)

## echoes

Functions suffixed with `_e` [echo](http://php.net/manual/en/function.echo.php) the result of the underlying function:

### `esc_e( $string )`

### `encode_e( $value )`

### `tag_e( $tagname, $attrs, $inner_html )`

### `attrs_e( $attrs )`