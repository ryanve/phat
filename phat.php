<?php
/**
 * phat           PHP HTML utils
 * @link          phat.airve.com
 * @author        Ryan Van Etten
 * @package       airve/phat
 * @version       2.2.0
 * @license       MIT
 */

namespace airve;

abstract class Phat {

    protected static $mixins = array();

    public static function mixin($name, $fn = null) {
        if ( \is_scalar($name))
            $fn and static::$mixins[$name] = $fn;
        else foreach ($name as $k => $v)
            self::mixin($k, $v);
    }
    
    /**
     * Get the fully-qualified name of a method
     * @param   string  $name
     * @return  string
     * @example array_map(Phat::method('esc'), $array)
     */
    public static function method($name) {
        return \get_called_class() . "::$name";
    }

    # php.net/manual/en/language.oop5.overloading.php#object.callstatic
    # methods suffixed with "_e" overload as echoers
    public static function __callStatic($name, $params) {
        if (isset(static::$mixins[$name]))
            return \call_user_func_array(static::$mixins[$name], $params);
        if ('_e' === \substr($name, -2))
            echo \call_user_func_array(static::method(\substr($name, 0, -2)), $params);
        else \trigger_error(\get_called_class() . "::$name is not callable.");
    }
    
    /**
     * Invoke anonymous funcs (not names or other objects)
     * This is useful for accepting "func args" as params
     * @link    php.net/manual/en/functions.anonymous.php
     * @param   mixed  $value
     * @return  mixed
     */
    protected static function result($value) {
        return $value instanceof \Closure ? $value() : $value;
    }
    
    /**
     * Generate an HTML tag.
     * @param   Closure|string             $tagname
     * @param   Closure|array|string|null  $attrs
     * @param   Closure|string|null        $inner
     * @return  string
     */
    public static function tag($tagname, $attrs = null, $inner = null) {
        $tagname = self::tagname(self::result($tagname));
        if ( ! $tagname)
            return '';
        $attrs and $attrs = self::attrs($attrs);
        $tag = $attrs ? "<$tagname $attrs>" : "<$tagname>";
        $inner and $inner = self::result($inner);
        return null === $inner ? $tag : $tag . $inner . "</$tagname>";
    }
    
    /**
     * Escape a string for use in HTML or HTML attributes.
     * @param   string  $value
     * @return  string
     */
    public static function esc($value) {
        if ( !($value = (string) $value))
            return $value;
        # prevent double-encoding entities:
        return \htmlentities($value, ENT_QUOTES, null, false);
    }

    /**
     * Join $tokens into a string. Deep implode.
     * @param    array|mixed  $tokens
     * @param    string       $glue     Defaults to SSV.
     * @return   string
     */
    public static function implode($tokens, $glue = ' ') {
        if (\is_scalar($tokens))
            return \trim($tokens);
        if ( ! $tokens)
            return '';
        $ret = array();
        foreach ($tokens as $v) # flatten
            $ret[] = self::implode($v);
        return \implode($glue, $ret);
    }
    
    /**
     * If $glue is an array then $tokens is split at any
     * of $glue's items. Otherwise $glue splits as a phrase.
     * @param    string|mixed   $tokens
     * @param    string|array=  $glue    Defaults to SSV.
     * @return   array
     */
    public static function explode($tokens, $glue = ' ') {

        if (\is_string($tokens))
            $tokens = \trim($tokens);
        elseif (\is_scalar($tokens))
            return (array) $tokens;
        else $tokens = self::implode(\is_array($glue) ? $glue[0] : $glue, $tokens);

        # could be empty after 1st or 3rd condition above
        if ('' === $tokens)
            return array(); 
        # normalize multiple delims into 1
        \is_array($glue) and $tokens = \str_replace($glue, $glue = $glue[0], $tokens);
        return \ctype_space($glue) ? \preg_split('#\s+#', $tokens) : \explode($glue, $tokens);
    }
    
    /**
     * An extendable hash for token types (ssv, csv, etc.)
     * Get or set the $delimiter for the specified $name.
     * @param   string       $name 
     * @param   string=      $delimiter
     * @return  string|null
     */
    protected static function delimiter($name, $delimiter = null) {
        static $hash;
        $hash = $hash ?: \array_merge(
            # dev.w3.org/html5/spec-author-view/index.html#attributes-1
            # whatwg.org/specs/web-apps/current-work/multipage/microdata.html#names:-the-itemprop-attribute
            \array_fill_keys(\explode( '|', 'accept|media'), ','), #csv
            \array_fill_keys(\explode( '|', 'class|rel|itemprop|accesskey|dropzone|headers|sizes|sandbox|accept-charset'), ' ') #ssv
        );
        $name = \mb_strtolower($name);
        isset($hash[$name]) or (\is_string($delimiter) and $hash[$name] = $delimiter);
        return $hash[$name];
    }
    
    /**
     * @param   mixed        $value
     * @param   string|null  $name
     * @return  string
     */
    public static function encode($value, $name = null) {

        if (\is_string($value)) {
            $value and $value = \htmlentities($value, ENT_NOQUOTES, null, false);
            return \str_replace("'", '&apos;', $value);
        }
        if ( !($scalar = \is_scalar($value))) {
            if ( ! $value) # null|array()
                return $value === null ? 'null' : '';
            if ($value instanceof \Closure)
                return self::encode($value(), $name);
            if ($name && \is_string($d = self::delimiter($name)))
                return self::encode(self::implode($value, $d));
        }

        $value = \json_encode($value); # array|object|number|boolean
        return $scalar ? $value : encode($value);
    }

    /**
     * @param   string       $value
     * @param   string|null  $name
     * @return  mixed
     */    
    public static function decode($value, $name = null) {
        if ( ! $value || ! \is_string($value))
            return $value;
        if ($name && \is_string($d = self::delimiter($name)))
            return self::explode(\html_entity_decode($value, ENT_QUOTES), $d);
        $result = \json_decode($value, true); # null if not json
        if (null !== $result || 'null' === $value)
            return $result;
        return \html_entity_decode($value, ENT_QUOTES);
    }
    
    /**
     * Sanitize an HTML or XML tagName
     * @param   string|mixed  $name
     * @return  string
     */
    protected static function tagname($name) {
        # allow: alphanumeric|underscore|colon
        return \is_string($name) ? \preg_replace('/[^\w\:]/', '', $name) : '';
    }
    
    /**
     * Sanitize an HTML attribute name
     * @param   string|mixed  $name
     * @return  string
     */
    protected static function attname($name) {
        # allow: alphanumeric, underscore, hyphen, period
        # must start with letter or underscore
        # stackoverflow.com/q/13283699/770127
        # w3.org/TR/html-markup/syntax.html#syntax-attributes
        if ( ! \is_string($name))
            return '';
        $name = \preg_replace('/[^0-9\pL_.-]/', '', $name);
        return \preg_match('/^[\pL_]/', $name) ? $name : '';
    }
    
    /**
     * Replace or normalize whitespace.
     * @param   string  $text
     * @param   string  $replacement
     * @return  string
     */    
    public static function respace($text, $replacement = ' ') {
        return \preg_replace('#\s+#', $replacement, $text);
    }
    
    /**
     * Produce an attributes string. Null values are skipped. Boolean
     * values convert to boolean attrs. Other values encode via encode().
     * @param  mixed    $name   An array of name/value pairs, or an ssv string of attr 
                                names, or an indexed array of attr names.
     * @param  mixed    $value  Attr value for context when $name is an attribute name.
     */
    public static function attrs($name, $value = '') {
        # "func args"
        $name  and $name  = self::result($name);
        $value and $value = self::result($value);
        
        # handle false boolean attrs | null names/values
        # dev.w3.org/html5/spec/common-microsyntaxes.html#boolean-attributes
        if (null === $name || null === $value || false === $value)
            return '';

        # key/value map a.k.a. "array to attr"
        if ( ! \is_scalar($name)) {
            $arr = array();
            foreach ($name as $k => $v)
                ($pair = self::attrs($k, $v)) and $arr[] = $pair;
            return \implode(' ', $arr);
        }

        # looped indexed arrays:
        if (\is_int($name))
            return self::attrs($value);

        # Name may need parsing or sanitizing:
        if ($name && ! \ctype_alnum($name)) {
            if (\preg_match('#(\=|\s)#', $name))
                # looks already stringified like `title=""` or `async defer`
                return self::attrs(self::parseAttrs($name));
            $name = self::attname($name); # sanitize
        }
        
        # <p contenteditable> === <p contenteditable="">
        # Skip attrs whose name sanitized to ''
        if ('' === $value || '' === $name || true === $value)
            return $name;
        # Use single quotes for compatibility with JSON
        return $name . "='" . self::encode($value, $name) . "'";
    }
    
    /**
     * Parse a string of attributes into an array. If the string starts with a tag,
     * then the attrs on the first tag are parsed. This function uses a manual
     * loop to parse the attrs and is designed to be safer than using DOMDocument.
     * @param    string|mixed   $attrs
     * @return   array
     * @example  parseAttrs( 'src="example.jpg" alt="example"' )
     * @example  parseAttrs( '<img src="example.jpg" alt="example">' )
     * @example  parseAttrs( '<a href="example"></a>' )
     * @example  parseAttrs( '<a href="example">' )
     */
    public static function parseAttrs($attrs) {
        if ( ! \is_scalar($attrs))
            return (array) $attrs;

        $attrs = \str_split(\trim($attrs));
        if ('<' === $attrs[0]) # looks like a tag so strip the tagname
            while ( $attrs && ! \ctype_space($attrs[0]) && $attrs[0] !== '>' )
                \array_shift($attrs);

        $arr = array(); # output
        $name = '';     # for the current attr being parsed
        $value = '';    # for the current attr being parsed
        $mode = 0;      # whether current char is part of the name (-), value (+), or neither (0)
        $stop = false;  # delimiter for the current $value being parsed
        $space = ' ';   # a single space

        foreach ($attrs as $j => $curr) {
            if ($mode < 0) {# name
                if ('=' === $curr) {
                    $mode = 1;
                    $stop = false;
                } elseif ('>' === $curr) {
                    '' === $name or $arr[$name] = $value;
                    break; 
                } elseif ( ! \ctype_space($curr)) {
                    if (\ctype_space($attrs[$j-1])) { # previous char
                        '' === $name or $arr[$name] = '';   # previous name
                        $name = $curr;                        # initiate new
                    } else {
                        $name .= $curr;
                    }
                }
            } elseif ($mode > 0) {# value
                if ($stop === false) {
                    if ( ! \ctype_space($curr)) {
                        if ('"' === $curr || "'" === $curr) {
                            $value = '';
                            $stop = $curr;
                        } else {
                            $value = $curr;
                            $stop = $space;
                        }
                    }
                } elseif ($stop === $space ? \ctype_space($curr) : $curr === $stop) {
                    $arr[$name] = $value;
                    $mode = 0;
                    $name = $value = '';
                } else {
                    $value .= $curr;
                }
            } else {# neither
                if ('>' === $curr)
                    break;
                if ( ! \ctype_space($curr)) {
                    # initiate 
                    $name = $curr; 
                    $mode = -1;
                }
            }
        }

        # incl the final pair if it was quoteless
        '' === $name or $arr[$name] = $value;
        return $arr;
    }
    
    /**
     * experimental function for parsing markup into a DOMDocument object
     * @param   DOMDocument|string|array   $html
     * @return  DOMDocument
     */
    public static function dom($html) {
        $source = null;
        if (\is_object($html)) {
            \method_exists($html, 'saveHtml') 
                ? ($html = $html->saveHtml())
                : ($source = $html);
        } elseif (\is_array($html)) {
            $source = $html; # array of nodes
        } elseif ( ! \is_string($html)) {
            return new \DOMDocument;
        }

        if (null === $source) {
            $typed = \preg_match('/^\<\!doctype\s/i', $html);
            if ( ! $typed && \preg_match('/^\<html\s/i', $html))
                $typed = !!($html = '<!DOCTYPE html>' . $html);
            $source = new \DOMDocument;
            $source->loadHtml($html);
            if ($typed || $source->saveHtml() === $html)
                return $source;
            $source = $source->getElementsByTagName('*')->item(0)->childNodes;
            if ( ! \preg_match('/^(\<body|\<head)\s/i', $html))
                $source = $source->item(0)->childNodes;
        }

        $output = new \DOMDocument;
        foreach ($source as $i => $node)
            $output->appendChild($output->importNode($node, true));
        return $output;
    }

}#class

#end