<?php
namespace phat;

/**
 * phatml.php     PHP functions for HTML markup
 * @version       0.x
 * @author        Ryan Van Etten <@ryanve>
 * @link          github.com/ryanve/phatml
 * @license       MIT
 * @uses          PHP 5.3
 */

/**
 * Convert a function name or class name from a namespace into a 
 * fully-qualified name. In other words, prefix it with the namespace.
 * @param   string   A local function name or class name.
 * @param   string=  The namespace. Defaults to the current namespace.
 */
if ( ! \function_exists( __NAMESPACE__ . '\\ns' ) ) {
    function ns ( $name, $ns = null ) {
        $ns or $ns = __NAMESPACE__;
        return $ns . '\\' . \ltrim( $name, '\\' );
    }
}

/**
 * Check if a function name or class name exists in the current namespace.
 * @param   string   $name
 * @param   string=  $what
 * @return  bool
 */
if ( ! \function_exists( __NAMESPACE__ . '\\exists' ) ) {
    function exists ( $name, $what = 'function' ) {
        return \call_user_func( $what . '_exists', ns($name) );
    }
}

/**
 * Call a namespaced function by name. ( Params can be supplied via extra args. )
 * @param   string    $fname
 */
if ( ! exists( 'call' ) ) {
    function call ( $fname ) {
        $params = func_get_args();
        return \call_user_func_array( ns( \array_shift($params) ), $params );
    }
}

/**
 * Call a namespaced function by name. ( Params can be supplied via array. )
 * @param   string    $fname
 * @param   array     $params
 */
if ( ! exists( 'apply' ) ) {
    function apply ( $fname, $params = array() ) {
        return \call_user_func_array( ns( $fname ), $params );
    }
}

/**
 *
 */
if ( ! exists( 'invoke' ) ) {
    function invoke ( $value, $args = array() ) {
        # fire anonymous or __invoke funcs ( not func names )
        # this is useful for accepting "func args" as params
        # php.net/manual/en/functions.anonymous.php
        # php.net/manual/en/language.oop5.magic.php
        if ( ! \is_object($value) || ! \is_callable($value) )
            return $value;
        return \call_user_func_array( $value, $args );
    }
}

/**
 * Escape a string for use in html (such as in html attributes).
 * @param   string|mixed  $value
 * @return  string
 */
if ( ! exists( 'esc' ) ) {
    function esc ( $value ) {
        if ( ! ($value = (string) $value) )
            return $value;
        return \htmlentities( $value, ENT_QUOTES, null, false );
    }
}

 
/**
 * @param    mixed    $tokens
 * @param    string=  $glue     defaults to ssv
 * @return   string
 * @link     dev.w3.org/html5/spec/common-microsyntaxes.html
 */
if ( ! exists( 'token_implode' ) ) {
    function token_implode ( $tokens, $glue = ' ' ) {

        if ( \is_scalar($tokens) )
            return \trim($tokens);

        if ( ! $tokens )
            return '';

        $ret = array();
        foreach ( $tokens as $v ) # flatten
            $ret[] = token_implode($v);

        return \implode( $glue, $ret );
    }
}

/**
 * @param    string|mixed   $tokens
 * @param    string|array=  $glue    Defaults to ssv. If $glue is an array 
 *                                   then $tokens is split at any of $glue's 
 *                                   items. Otherwise $glue splits as a phrase.
 * @return   array
 * @link     dev.w3.org/html5/spec/common-microsyntaxes.html
 */
if ( ! exists( 'token_explode' ) ) {
    function token_explode ( $tokens, $glue = ' ' ) {

        if ( \is_string($tokens) )
            $tokens = \trim( $tokens );
        elseif ( \is_scalar($tokens) )
            return (array) $tokens;
        else $tokens = token_implode( \is_array($glue) ? $glue[0] : $glue, $tokens );

        if ( '' === $tokens ) # could be empty after 1st or 3rd condition above
            return array();

        if ( \is_array($glue) ) # normalize multiple delims into 1
            $tokens = \str_replace( $glue, $glue = $glue[0], $tokens );

        if ( \ctype_space($glue) )
            return \preg_split( '#\s+#', $tokens );

        return \explode( $glue, $tokens );
    }
}

/**
 * An extendable hash for token types. ( See usage in encode()/decode() )
 */
if ( ! exists( 'delimiter' ) ) {

    function delimiter ( $name, $value = null ) {
        static $hash; # php.net/manual/en/language.variables.scope.php
        isset($hash) or $hash = array();
        $name = \mb_strtolower( $name );
        isset( $hash[$name] ) or ( \is_string($value) and $hash[$name] = $value );
        return $hash[$name];
    }
    
    \call_user_func(function () {

        # define delimiters
        # dev.w3.org/html5/spec-author-view/index.html#attributes-1
        # whatwg.org/specs/web-apps/current-work/multipage/microdata.html#names:-the-itemprop-attribute

        $ssv = array('class', 'rel', 'itemprop', 'accesskey', 'dropzone', 'headers', 'sizes', 'sandbox', 'accept-charset');
        $csv = array('accept');
        
        foreach ( $ssv as $name )
            delimiter( $name, ' ' );

        foreach ( $csv as $name )
            delimiter( $name, ',' );

    });
}

/**
 *
 */
if ( ! exists( 'encode' ) ) {
    function encode ( $value, $name = null ) {
    
        if ( \is_string($value) ) {
            $value and $value = \htmlentities( $value, ENT_NOQUOTES, null, false );
            return \str_replace( "'", '&apos;', $value );
        }
            
        if ( ! ( $safe = \is_scalar($value) ) ) {

            if ( ! $value ) # null|array()
                return $value === null ? 'null' : '';
                
            if ( $name && ($d = delimiter($name)) !== null )
                return encode( token_implode($value, $d) );
        }
        
        $value = \json_encode($value); # array|object|number|boolean
        return $safe ? $value : encode($value);
    }
}

/**
 *
 */
if ( ! exists( 'decode' ) ) {
    function decode ( $value, $name = null ) {

        if ( ! $value || ! \is_string($value) )
            return $value;

        if ( $name && ($d = delimiter($name)) !== null )
            return token_explode( \html_entity_decode( $value, ENT_QUOTES ), $d );
            
        $result = \json_decode( $value, true ); # null if not json
        if ( null !== $result || 'null' === $value )
            return $result;

        return \html_entity_decode( $value, ENT_QUOTES );
    }
}

/**
 * Sanitize an html or xml tagname
 * @param   string|*  $name
 * @return  string
 */
if ( ! exists( 'tagname' ) ) {
    function tagname ( $name = null ) {
        # allow: alphanumeric|underscore|colon
        return \is_string($name) ? \preg_replace( '/[^\w\:]/', '', $name ) : '';
    }
}

/**
 * Sanitize an html attribute name
 * @param   string|*  $name
 * @return  string
 */
if ( ! exists( 'attname' ) ) {
    function attname ( $name ) {
        # sanitize attr name (allow: alphanumeric, underscore, hyphen, period)
        # must start with letter or underscore
        # stackoverflow.com/q/13283699/770127
        # w3.org/TR/html-markup/syntax.html#syntax-attributes
        if ( ! \is_string($name) )
            return '';
        $name = \preg_replace( '/[^0-9\pL_.-]/', '', $name );
        return \preg_match( '/^[\pL_]/', $name ) ? $name : '';
    }
}

/**
 * Convert an array into an attributes string. Null values are skipped. Boolean values
 * convert into boolean attrs. Other values encode via encode().
 * 
 * @param  array|string $name    An array of name/value pairs, or an ssv string of attr 
                                 names, or an indexed array of attr names.
 * @param  mixed=       $value   Attr value for context when $name is an attribute name.
 */
if ( ! exists( 'attrs' ) ) {
    function attrs ( $name, $value = '' ) {
    
        $array = array();
        
        # handle false boolean attrs | null names/values
        # dev.w3.org/html5/spec/common-microsyntaxes.html#boolean-attributes
        if ( false === $value || null === $name || null === $value )
            return '';

        # key/value map a.k.a. "array to attr"
        if ( ! \is_scalar($name) ) {
            foreach ( $name as $k => $v )
                ( $pair = attrs($k, $v) ) and $array[] = $pair;
            return \implode( ' ', $array );
        }
        
        # looped indexed arrays:
        if ( \is_int($name) )
            return attrs($value);

        # $name may need recursion and/or sanitization:
        if ( $name && ! \ctype_alnum($name) ) {
            if ( \strpos( $name, '=' ) !== false )
                # looks already stringified like `title=""`
                # dirty syntax but make it work (ignoring $value)
                return attrs( parse_attrs($name) );

            if ( \count( $names = token_explode($name) ) > 1 )
                # ssv names like `async defer`
                # set all to the same value (defaults to '')
                return attrs( \array_fill_keys( $names, $value ) );

            # sanitize attr name (allow: alphanumeric, underscore, hyphen, period)
            # stackoverflow.com/q/13283699/770127
            $name = attname( $name );
        }
        
        # <p contenteditable> is the same as <p contenteditable="">
        # skip attrs that had invalid names ($name sanitized to '')
        # dev.w3.org/html5/spec/common-microsyntaxes.html#boolean-attributes
        if ( '' === $value || '' === $name || true === $value )
            return $name;
            
        # use single quotes for compatibility with JSON
        return $name . "='" . encode($value) . "'";
    }
}

/**
 * Parse a string of attributes into an array. If the string starts with a tag,
 * then the attrs on the first tag are parsed. This function uses a manual
 * loop to parse the attrs and is designed to be safer than using DOMDocument.
 *
 * @param    string|*   $attrs
 * @return   array
 * @link     dev.airve.com/demo/speed_tests/php/parse_attrs.php
 *
 * @example  parse_attrs( 'src="example.jpg" alt="example"' )
 * @example  parse_attrs( '<img src="example.jpg" alt="example">' )
 * @example  parse_attrs( '<a href="example"></a>' )
 * @example  parse_attrs( '<a href="example">' )
 */
if ( ! exists( 'parse_attrs' ) ) {
    function parse_attrs ($attrs) {

        if ( ! \is_scalar($attrs) )
            return (array) $attrs;

        $attrs = \str_split( \trim($attrs) );
        
        if ( '<' === $attrs[0] ) # looks like a tag so strip the tagname
            while ( $attrs && ! \ctype_space($attrs[0]) && $attrs[0] !== '>' )
                \array_shift($attrs);

        $arr = array(); # output
        $name = '';     # for the current attr being parsed
        $value = '';    # for the current attr being parsed
        $mode = 0;      # whether current char is part of the name (-), the value (+), or neither (0)
        $stop = false;  # delimiter for the current $value being parsed
        $space = ' ';   # a single space

        foreach ( $attrs as $j => $curr ) {
            
            if ( $mode < 0 ) {# name
                if ( '=' === $curr ) {
                    $mode = 1;
                    $stop = false;
                } elseif ( '>' === $curr ) {
                    '' === $name or $arr[ $name ] = $value;
                    break; 
                } elseif ( ! \ctype_space($curr) ) {
                    if ( \ctype_space( $attrs[ $j - 1 ] ) ) { # previous char
                        '' === $name or $arr[ $name ] = '';   # previous name
                        $name = $curr;                        # initiate new
                    } else {
                        $name .= $curr;
                    }
                }
            } elseif ( $mode > 0 ) {# value
                if ( $stop === false ) {
                    if ( ! \ctype_space($curr) ) {
                        if ( '"' === $curr || "'" === $curr ) {
                            $value = '';
                            $stop = $curr;
                        } else {
                            $value = $curr;
                            $stop = $space;
                        }
                    }
                } elseif ( $stop === $space ? \ctype_space($curr) : $curr === $stop ) {
                    $arr[ $name ] = $value;
                    $mode = 0;
                    $name = $value = '';
                } else {
                    $value .= $curr;
                }
            } else {# neither

                if ( '>' === $curr )
                    break;
                if ( ! \ctype_space( $curr ) ) {
                    # initiate 
                    $name = $curr; 
                    $mode = -1;
                }
            }
        }

        # incl the final pair if it was quoteless
        '' === $name or $arr[ $name ] = $value;

        return $arr;
    }
}

/**
 * Generate an html tag.
 * @param   string        $tagname
 * @param   array|string  $attrs
 * @param   string        $inner_html
 * @return  string
 */
if ( ! exists( 'tag' ) ) {
    function tag ( $tagname = null, $attrs = null, $inner_html = null ) {

        # allows args to be set at runtime
        $tagname = tagname( invoke($tagname) );
        if ( ! $tagname )
            return '';
        $attrs = invoke($attrs);
        $inner_html = invoke($inner_html);

        # build the markup - close the tag only if it had inner html
        $tag = '<' . $tagname;
        $attrs = attrs($attrs);
        $attrs and $tag .= ' ' . $attrs;
        $tag .= '>';
        null === $inner_html or $tag .= $inner_html . '</' . $tagname . '>';
        return $tag;
    }
}

/**
 * experimental function for parsing markup into a DOMDocument
 * @param   DOMDocument|string|array   $html
 * @return  DOMDocument
 */
if ( ! exists( 'dom' ) ) {
    function dom ( $html ) {

        $source = null;

        if ( \is_object($html) )
            if ( \method_exists( $html, 'saveHtml' ) )
                $html = $html->saveHtml();
            else $source = $html;
        elseif ( \is_array($html) ) # array of nodes
            $source = $html; 
        elseif ( ! \is_string($html) )
            return new \DOMDocument;

        if ( null === $source ) {

            $typed = \preg_match( '/^\<\!doctype\s/i', $html );
            if ( ! $typed && \preg_match( '/^\<html\s/i', $html ) )
                $typed = !!($html = '<!DOCTYPE html>' . $html);

            $source = new \DOMDocument;
            $source->loadHtml($html);
            
            if ( $typed || $source->saveHtml() === $html )
                return $source;

            $source = $source->getElementsByTagName('*')->item(0)->childNodes;
            if ( ! \preg_match( '/^(\<body|\<head)\s/i', $html ) )
                $source = $source->item(0)->childNodes;
        }
        
        $output = new \DOMDocument;
        foreach ( $source as $i => $node )
            $output->appendChild( $output->importNode($node, true) );
    
        return $output;
    }
}

# echoing functions are suffixed with _e
# use apply() for these for easier maintainability

if ( ! exists( 'esc_e' ) ) {
    function esc_e ( $value ) {
        echo apply( 'esc', \func_get_args() );
    }
}

if ( ! exists( 'encode_e' ) ) {
    function encode_e () {
        echo apply( 'encode', \func_get_args() );
    }
}

if ( ! exists( 'attrs_e' ) ) {
    function attrs_e () {
        echo apply( 'attrs', \func_get_args() );
    }
}

if ( ! exists( 'tag_e' ) ) {
    function tag_e () {
        echo apply( 'tag', \func_get_args() );
    }
}

#end