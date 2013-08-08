<?php
/**
 * This is an in-development WordPress adaptation of phat.php (phat.airve.com)
 * Everything here must be PHP 5.2 compatible as it intends to be in WP core.
 * @link http://core.trac.wordpress.org/ticket/23236
 * @link http://core.trac.wordpress.org/ticket/23237
 */
 
/**
 * Generate an HTML tag
 * @param   string  $tagname
 * @param   mixed   $attr
 * @param   mixed   $inner
 * @return  string
 */
function wp_tag($tagname, $attr = null, $inner = null) {
    $tagname = is_string($tagname) ? wp_tagname($tagname) : '';
    if ( ! strlen($tagname))
        return '';
    $attr and $attr = wp_attr($attr);
    $tag = $attr ? "<$tagname $attr>" : "<$tagname>";
    $inner and $inner = wp_result($inner);
    return null === $inner ? $tag : $tag . $inner . "</$tagname>";
}


/**
 * @param   mixed  $value
 * @return  mixed
 */
function wp_result($value) {
    return $value instanceof Closure ? $value() : $value;
}

function_exists('add_filter') and add_filter('complex_attr_value', 'wp_result', 1);

/**
 * Deep implode.
 * @param    array|mixed  $tokens
 * @param    string       $glue     Defaults to a space.
 * @return   string
 */
function wp_implode($tokens, $glue = ' ') {
    if (is_scalar($tokens))
        return trim($tokens);
    if ( ! $tokens)
        return '';
    $result = array();
    foreach ($tokens as $v) // flatten
        $result[] = wp_implode($v);
    return implode($glue, $result);
}

/**
 * If $glue is an array then $tokens is split at any
 * of $glue's items. Otherwise $glue splits as a phrase.
 * @param    string|mixed   $tokens
 * @param    string|array=  $glue    Defaults to SSV.
 * @return   array
 */
function wp_explode($tokens, $glue = ' ') {
    if (is_string($tokens))
        $tokens = trim($tokens);
    elseif (is_scalar($tokens))
        return (array) $tokens;
    else $tokens = wp_implode(is_array($glue) ? $glue[0] : $glue, $tokens);

    // could be empty after 1st or 3rd condition above
    if ('' === $tokens)
        return array(); 
    // normalize multiple delims into 1
    is_array($glue) and $tokens = str_replace($glue, $glue = $glue[0], $tokens);
    return ctype_space($glue) ? preg_split('#\s+#', $tokens) : explode($glue, $tokens);
}


if ( ! function_exists('wp_tagname')) {
    /**
     * Sanitize an HTML or XML tag name. Or read the tagname of a tag.
     * @param   string|array   $name
     * @return  string|array
     */
    function wp_tagname($name) {
        // Use XML rules: w3.org/TR/REC-xml/#NT-Name
        // allow: alphanumeric|underscore|colon|period|hyphen
        return preg_replace('#\s*<*([\w:.-]*).*#', '$1', $name);
    }
}

/**
 * Sanitize an HTML or XML attribute name.
 * @param   string|array  $name
 * @return  string|array
 */
function wp_attname($name) {
    // stackoverflow.com/q/13283699/770127
    // w3.org/TR/html-markup/syntax.html#syntax-attributes
    // w3.org/TR/REC-xml/#NT-Attribute
    // php.net/manual/en/regexp.reference.unicode.php
    // should start with letter (or underscore|colon in xml) but not enforced here
    // allow: unicode letters|digits|underscore|colon|period|hyphen
    return preg_replace(array('#[=>].*#', '#[^\pL\d_:.-]*#'), '', $name);
}

function wp_attr_encode($value, $name = null) {
    if (is_string($value)) {
        // Encode without double-encoding (unlike esc_attr)
        // Allow double quotes to accomodate JSON (unlike esc_attr)
        $value and $value = htmlentities($value, ENT_NOQUOTES, null, false); 
        return str_replace("'", '&apos;', $value);
    }
    if ( ! is_scalar($value)) {
        if ($name) {
            if (function_exists('apply_filters'))
                $value = apply_filters('complex_attr_value', $value, $name);
            if ( ! is_scalar($value) && ! preg_match('#^data-#i', $name))
                $value = wp_attr_tokens($value, $name);
            return wp_attr_encode($value); 
        } elseif ( ! $value) {
            return null === $value ? 'null' : ''; // null|array()
        }
    }
    return json_encode($value);
}

function wp_attr_decode($value, $name = null) {
    if ( ! $value || ! is_string($value))
        return $value;

    // dev.w3.org/html5/spec-author-view/index.html#attributes-1
    $list = '#^(media|class|rel|itemprop|acce(pt|sskey|pt-charset)|dropzone|headers|sizes|sandbox)$#i';
    if ($name && preg_match($list, $name))
        return wp_attr_tokens(html_entity_decode($value, ENT_QUOTES), $name);

    $result = json_decode($value, true); # null if not json
    return null !== $result || 'null' === $value ? $result : html_entity_decode($value, ENT_QUOTES);
}

/**
 * @param   string  $tagname
 * @param   mixed   $attr
 * @param   mixed   $inner
 * @return  string
 */
function wp_attr_tokens($value, $name = null, $explode = false) {
    // dev.w3.org/html5/spec-author-view/index.html#attributes-1
    $name = strtolower($name);
    $name = 'media' === $name || 'accept' === $name ? ',' : ' '; # convert to delimiter
    return call_user_func($explode ? 'wp_explode' : 'wp_implode', $value, $name);
}

/**
 * Generate an attributes string.
 * @return string
 * Syntax:
 * - wp_attr(assocMap)      # associative array|object containing name/value pairs
 * - wp_attr(name, value)   # basic singular syntax
 * - wp_attr(mixedMap)      # delegates on per-key (integer or not) basis
 * - wp_attr(names)         # SSV or indexed array
 * - wp_attr(attrString)    # existing attribute(s)
 * - wp_attr(closure)       # uses wp_result
 */
function wp_attr($name, $value = '') {
    // non-associative recursion
    is_int($name) and ($name = $value) === ($value = '');

    // func args
    $name and $name = wp_result($name); 
    
    // false boolean attrs | null names/values
    // dev.w3.org/html5/spec/common-microsyntaxes.html#boolean-attributes
    if (false === $value || null === $value || null === $name || is_bool($name))
        return '';

    // Name may need parsing or sanitizing:
    if (is_scalar($name) && ($name = trim($name)) && ctype_alpha($name))
        // parse if it looks already stringified like `title=""` or `async defer`
        $name = preg_match('#(\=|\s)#', $name) ? wp_attr_parse($name) : wp_attname($name);
    
    // key/value map a.k.a. "array to attr"
    if ( ! is_scalar($name)) {
        $value = array();
        foreach ($name as $k => $v)
            strlen($pair = wp_attr($k, $v)) and $value[] = $pair;
        return implode(' ', $value);
    }
    
    // <p contenteditable> === <p contenteditable="">
    // Use single quotes for compatibility with JSON
    return '' === $value || true === $value || '' === $name || (
        true === ($value = wp_attr_encode($value, $name)) || '' === $value
    ) ? $name : (false === $value || null === $value ? '' : "$name='$value'");
}


/**
 * Convert an attributes string into an array.
 * @param  string|mixed  $attr
 * @return array
 */
function wp_attr_parse($attr) {
    if ( ! is_scalar($attr))
        return (array) $attr;

    // trim, then strip tagname (if present), then split into array
    $attr = str_split(preg_replace('#^<+\S*#', '', trim($attr)));

    $arr = array(); // output
    $name = '';     // for the current attr being parsed
    $value = '';    // for the current attr being parsed
    $mode = 0;      // whether current char is part of the name (-), value (+), or neither (0)
    $stop = false;  // delimiter for the current $value being parsed
    $space = ' ';   // a single space

    foreach ($attr as $j => $curr) {
        if ($mode < 0) {// name
            if ('=' === $curr) {
                $mode = 1;
                $stop = false;
            } elseif ('>' === $curr) {
                '' === $name or $arr[$name] = $value;
                break; 
            } elseif ( ! ctype_space($curr)) {
                if (ctype_space($attr[$j-1])) {       // previous char
                    '' === $name or $arr[$name] = ''; // previous name
                    $name = $curr;                    // initiate new
                } else {
                    $name .= $curr;
                }
            }
        } elseif ($mode > 0) {// value
            if ($stop === false) {
                if ( ! ctype_space($curr)) {
                    if ('"' === $curr || "'" === $curr) {
                        $value = '';
                        $stop = $curr;
                    } else {
                        $value = $curr;
                        $stop = $space;
                    }
                }
            } elseif ($stop === $space ? ctype_space($curr) : $curr === $stop) {
                $arr[$name] = $value;
                $mode = 0;
                $name = $value = '';
            } else {
                $value .= $curr;
            }
        } else {// neither
            if ('>' === $curr)
                break;
            if ( ! ctype_space($curr)) {
                // initiate 
                $name = $curr; 
                $mode = -1;
            }
        }
    }

    // incl the final pair if it was quoteless
    '' === $name or $arr[$name] = $value;
    return $arr;
}

# Echoers
function wp_tag_e() {
    echo call_user_func_array(substr(__FUNCTION__,0 , -2), func_get_args());
}

function wp_attr_e() {
    echo call_user_func_array(substr(__FUNCTION__,0 , -2), func_get_args());
}

#EOF