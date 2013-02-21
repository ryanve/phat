<?php
/**
 * phat           PHP functions for HTML
 * @link          phat.airve.com
 * @author        Ryan Van Etten
 * @package       ryanve/phat
 * @version       1.2.0
 * @license       MIT
 */

namespace phat; # PHP 5.3+

/**
 * @param  mixed  $value
 */
if ( ! \function_exists( __NAMESPACE__ . '\\result' ) ) {
    function result ( $value ) {
        # fire anonymous funcs (not names or other objects)
        # this is useful for accepting "func args" as params
        # php.net/manual/en/functions.anonymous.php
        # php.net/manual/en/language.oop5.magic.php
        return $value instanceof \Closure ? $value() : $value;
    }
}

/**
 * Escape a string for use in html (such as in html attributes).
 * @param   string|mixed  $value
 * @return  string
 */
if ( ! \function_exists( __NAMESPACE__ . '\\esc' ) ) {
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
if ( ! \function_exists( __NAMESPACE__ . '\\token_implode' ) ) {
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
if ( ! \function_exists( __NAMESPACE__ . '\\token_explode' ) ) {
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
 * An extendable hash for token types (ssv, csv, etc.)
 * Get or set the $delimiter for the specified $name.
 * @param   string       $name 
 * @param   string=      $delimiter
 * @return  string|null
 */
if ( ! \function_exists( __NAMESPACE__ . '\\delimiter' ) ) {
    function delimiter ( $name, $delimiter = null ) {
        static $hash;
        isset( $hash ) or $hash = \array_merge(
            # dev.w3.org/html5/spec-author-view/index.html#attributes-1
            # whatwg.org/specs/web-apps/current-work/multipage/microdata.html#names:-the-itemprop-attribute
            \array_fill_keys( \explode( '|', 'accept|media'), ',' ), #csv
            \array_fill_keys( \explode( '|', 'class|rel|itemprop|accesskey|dropzone|headers|sizes|sandbox|accept-charset'), ' ' ) #ssv
        );
        $name = \mb_strtolower( $name );
        isset( $hash[$name] ) or ( \is_string($delimiter) and $hash[$name] = $delimiter );
        return $hash[$name];
    }
}

/**
 *
 */
if ( ! \function_exists( __NAMESPACE__ . '\\encode' ) ) {
    function encode ( $value, $name = null ) {
    
        if ( \is_string($value) ) {
            $value and $value = \htmlentities( $value, ENT_NOQUOTES, null, false );
            return \str_replace( "'", '&apos;', $value );
        }
        
        $scalar = \is_scalar($value);

        if ( ! $scalar ) {
            if ( ! $value ) # null|array()
                return $value === null ? 'null' : '';
            if ( $value instanceof \Closure )
                return encode( $value(), $name );
            if ( $name && \is_string($d = delimiter($name)) )
                return encode( token_implode($value, $d) );
        }
        
        $value = \json_encode($value); # array|object|number|boolean
        return $scalar ? $value : encode($value);
    }
}

/**
 *
 */
if ( ! \function_exists( __NAMESPACE__ . '\\decode' ) ) {
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
 * @param   string|mixed  $name
 * @return  string
 */
if ( ! \function_exists( __NAMESPACE__ . '\\tagname' ) ) {
    function tagname ( $name ) {
        # allow: alphanumeric|underscore|colon
        return \is_string($name) ? \preg_replace( '/[^\w\:]/', '', $name ) : '';
    }
}

/**
 * Sanitize an html attribute name
 * @param   string|mixed  $name
 * @return  string
 */
if ( ! \function_exists( __NAMESPACE__ . '\\attname' ) ) {
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
 * Produce an attributes string. Null values are skipped. Boolean
 * values convert to boolean attrs. Other values encode via encode().
 * @param  mixed    $name   An array of name/value pairs, or an ssv string of attr 
                            names, or an indexed array of attr names.
 * @param  mixed    $value  Attr value for context when $name is an attribute name.
 */
if ( ! \function_exists( __NAMESPACE__ . '\\attrs' ) ) {
    function attrs ( $name, $value = '' ) {
    
        # "func args"
        $name  and $name  = result($name);
        $value and $value = result($value);
        
        # handle false boolean attrs | null names/values
        # dev.w3.org/html5/spec/common-microsyntaxes.html#boolean-attributes
        if ( null === $name || null === $value || false === $value )
            return '';

        # key/value map a.k.a. "array to attr"
        if ( ! \is_scalar($name) ) {
            $arr = array();
            foreach ( $name as $k => $v )
                ( $pair = attrs($k, $v) ) and $arr[] = $pair;
            return \implode( ' ', $arr );
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
        
        # <p contenteditable> === <p contenteditable="">
        # Skip attrs whose $name sanitized to ''
        # dev.w3.org/html5/spec/common-microsyntaxes.html#boolean-attributes
        if ( '' === $value || '' === $name || true === $value )
            return $name;
            
        # Use single quotes for compatibility with JSON
        return $name . "='" . encode($value, $name) . "'";
    }
}

/**
 * Parse a string of attributes into an array. If the string starts with a tag,
 * then the attrs on the first tag are parsed. This function uses a manual
 * loop to parse the attrs and is designed to be safer than using DOMDocument.
 *
 * @param    string|mixed   $attrs
 * @return   array
 * @link     dev.airve.com/demo/speed_tests/php/parse_attrs.php
 *
 * @example  parse_attrs( 'src="example.jpg" alt="example"' )
 * @example  parse_attrs( '<img src="example.jpg" alt="example">' )
 * @example  parse_attrs( '<a href="example"></a>' )
 * @example  parse_attrs( '<a href="example">' )
 */
if ( ! \function_exists( __NAMESPACE__ . '\\parse_attrs' ) ) {
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
 * @param   Closure|string             $tagname
 * @param   Closure|array|string|null  $attrs
 * @param   Closure|string|null        $inner
 * @return  string
 */
if ( ! \function_exists( __NAMESPACE__ . '\\tag' ) ) {
    function tag ( $tagname, $attrs = null, $inner = null ) {
        $tagname = tagname( result($tagname) );
        if ( ! $tagname )
            return '';
        $attrs and $attrs = attrs($attrs);
        $tag = $attrs ? "<$tagname $attrs>" : "<$tagname>";
        $inner and $inner = result($inner);
        return null === $inner ? $tag : $tag . $inner . "</$tagname>";
    }
}

/**
 * experimental function for parsing markup into a DOMDocument
 * @param   DOMDocument|string|array   $html
 * @return  DOMDocument
 */
if ( ! \function_exists( __NAMESPACE__ . '\\dom' ) ) {
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
# apply func_get_args to ensure proper args

if ( ! \function_exists( __NAMESPACE__ . '\\esc_e' ) ) {
    function esc_e ( $value ) {
        echo \call_user_func_array( __NAMESPACE__ . '\\esc', \func_get_args() );
    }
}

if ( ! \function_exists( __NAMESPACE__ . '\\encode_e' ) ) {
    function encode_e () {
        echo \call_user_func_array( __NAMESPACE__ . '\\encode', \func_get_args() );
    }
}

if ( ! \function_exists( __NAMESPACE__ . '\\attrs_e' ) ) {
    function attrs_e () {
        echo \call_user_func_array( __NAMESPACE__ . '\\attrs', \func_get_args() );
    }
}

if ( ! \function_exists( __NAMESPACE__ . '\\tag_e' ) ) {
    function tag_e () {
        echo \call_user_func_array( __NAMESPACE__ . '\\tag', \func_get_args() );
    }
}

#end