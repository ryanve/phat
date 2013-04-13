<?php
/**
 * phat           PHP HTML utils
 * @link          phat.airve.com
 * @author        Ryan Van Etten
 * @package       airve/phat
 * @version       2.4.0
 * @license       MIT
 */

namespace airve;

class Phat {

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
            \array_fill_keys(
                \explode('|', 'accept|media')
            , ','), #csv
            \array_fill_keys(
                \explode('|', 'class|rel|itemprop|accesskey|dropzone|headers|sizes|sandbox|accept-charset')
            , ' ') #ssv
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
        return $scalar ? $value : self::encode($value);
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
     * Sanitize an HTML or XML tag name. Or read the tagname of a tag.
     * @param   mixed         $name
     * @return  string|array
     */
    public static function tagname($name) {
        # w3.org/TR/html-markup/syntax.html#tag-name
        # w3.org/TR/REC-xml/#NT-Name
        # allow: alphanumeric|underscore|colon|period|hyphen
        return \preg_replace('#\s*<*([\w:.-]*).*#', '$1', $name);
    }
    
    /**
     * Sanitize an HTML or XML attribute name.
     * @param   mixed          $name
     * @return  string|array
     */
    public static function attname($name) {
        # stackoverflow.com/q/13283699/770127
        # w3.org/TR/html-markup/syntax.html#syntax-attributes
        # w3.org/TR/REC-xml/#NT-Attribute
        # php.net/manual/en/regexp.reference.unicode.php
        # should start with letter (or underscore|colon in xml) but not enforced here
        # allow: unicode letters|digits|underscore|colon|period|hyphen
        return \preg_replace(array('#[=>].*#', '#[^\pL\d_:.-]*#'), '', $name);
    }
    
    /**
     * Replace or normalize whitespace.
     * @since   2.1.0
     * @param   mixed   $text
     * @param   string  $replacement
     * @return  string|array
     */
    public static function respace($text, $replacement = ' ') {
        return \preg_replace('#\s+#', $replacement, $text);
    }

    /**
     * Replace or normalize linebreaks.
     * @since   2.3.0
     * @param   mixed   $text
     * @param   string  $replacement
     * @return  string|array
     */
    public static function rebreak($text, $replacement = "\n\n") {
        return \preg_replace('#\n+\s*\n+#', $replacement, $text);
    }
    
    /**
     * Produce an attributes string. Null values are skipped. Booleans
     * convert properly to boolean attrs. Other values encode via ::encode().
     * @param  mixed    $name   An array of name/value pairs, or an ssv string of attr 
                                names, or an indexed array of attr names.
     * @param  mixed    $value  Attr value for context when $name is an attribute name.
     */
    public static function attrs($name, $value = '') {
        # "func args"
        $name  and $name  = self::result($name);
        $value and $value = self::result($value);
        
        # false boolean attrs | null names/values
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
        if ($name && ! \ctype_alpha($name)) {
            if (\preg_match('#(\=|\s)#', $name))
                # looks already stringified like `title=""` or `async defer`
                return self::attrs(self::parseAttrs($name));
            $name = self::attname($name); # sanitize
        }
        
        # <p contenteditable> === <p contenteditable="">
        # Skip attrs whose name sanitized to ''
        # Use single quotes for compatibility with JSON
        return '' === $value || '' === $name || true === $value || (
            '' === ($value = self::encode($value, $name))
        ) ? $name : $name . "='" . $value . "'";
    }
    
    /**
     * Parse a string of attributes into an array. If the string starts with a tag,
     * then the attrs on the first tag are parsed. It uses a safe reliable loop
     * rather than risking errors via DOMDocument.
     * @param    string|mixed   $attrs
     * @return   array
     * @example  parseAttrs('src="example.jpg" alt="example"')
     * @example  parseAttrs('<img src="example.jpg" alt="example">')
     * @example  parseAttrs('<a href="example"></a>')
     * @example  parseAttrs('<a href="example">')
     */
    public static function parseAttrs($attrs) {
        if ( ! \is_scalar($attrs))
            return (array) $attrs;

        # trim, then strip tagname (if present), then split into array
        $attrs = \str_split(\preg_replace('#^<+\S*#', '', \trim($attrs)));

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
                        '' === $name or $arr[$name] = ''; # previous name
                        $name = $curr;                    # initiate new
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
     * Parse markup (or an array of nodes) into a DOMDocument object
     * @param   DOMDocument|Closure|string|array   $html
     * @return  DOMDocument
     */
    public static function dom($html = false) {
        $source = null;
        $html and $html = self::result($html);
        if ( ! \is_scalar($html))
            \is_callable(array($html, 'saveHtml')) ? $html = $html->saveHtml() : $source = $html;
        elseif ( ! \is_string($html))
            return new \DOMDocument;

        if (null === $source) {
            $source = new \DOMDocument;
            $html = \trim($html);
            if ('' === $html)
                return $source;
            $type = \strtolower(\substr($html, 0, 5));
            if ('<html' === $type)
                $html = '<!DOCTYPE html>' . "\n" . $html;
            \libxml_use_internal_errors(true);
            $source->loadHtml($html);
            \libxml_clear_errors();
            if ('<!doc' === $type || '<html' === $type)
                return $source;
            $save = $source->saveHtml();
            if ($save === $html)
                return $source;
            $save = \strtolower(\substr($save, 0, 5));
            $source = $source->getElementsByTagName('*')->item(0)->childNodes;
            if ($save !== $type && '<body' !== $type && '<head' !== $type)
                $source = $source->item(0)->childNodes;
        }

        $html = new \DOMDocument; # repurpose
        foreach ($source as $i => $node)
            $html->appendChild($html->importNode($node, true));
        return $html;
    }

}#class