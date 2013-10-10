<?php
/**
 * @link https://github.com/ryanve/phat
 * @license MIT
 */
namespace phat;

class Html {
    # Alias via mixins.
    protected static $mixins = array('attrs' => array(__CLASS__, 'atts'), 'parseAttrs' => array(__CLASS__, 'parseAtts'));

    public static function mixin($name, $fn = null) {
        if (\is_scalar($name)) $fn and static::$mixins[$name] = $fn;
        else foreach ($name as $k => $v) self::mixin($k, $v);
    }

    /**
     * Get the fully-qualified name of a method
     * @param   string  $name
     * @return  string
     */
    public static function method($name) {
        return \get_called_class() . "::$name";
    }

    # Overload methods suffixed with "_e" as echoers
    public static function __callStatic($name, $params) {
        if (isset(static::$mixins[$name]))
            return \call_user_func_array(static::$mixins[$name], $params);
        if ('_e' === \substr($name, -2))
            echo \call_user_func_array(static::method(\substr($name, 0, -2)), $params);
        else \trigger_error(\get_called_class() . "::$name is not callable.");
    }

    /**
     * Invoke anonymous funcs.
     * @param   mixed  $value
     * @return  mixed
     */
    protected static function result($value) {
        return $value instanceof \Closure ? $value() : $value;
    }
    
    /**
     * Generate an HTML tag.
     * @param   Closure|string             $tagname
     * @param   Closure|array|string|null  $atts
     * @param   Closure|string|null        $inner
     * @return  string
     */
    public static function tag($tagname, $atts = null, $inner = null) {
        $tagname = static::tagname(static::result($tagname));
        if ( ! $tagname) return '';
        $atts and $atts = static::atts($atts);
        $tag = $atts ? "<$tagname $atts>" : "<$tagname>";
        $inner and $inner = static::result($inner);
        return null === $inner ? $tag : $tag . $inner . "</$tagname>";
    }
    
    /**
     * Escape a string for use in HTML or HTML attributes.
     * @param   string  $value
     * @return  string
     */
    public static function esc($value, $flag = ENT_QUOTES) {
        # Prevent double-encoding entities.
        return ($value = (string) $value) ? \htmlentities($value, $flag, null, false) : $value;
    }
    
    /**
     * @param int $timestamp unix timestamp (defaults to now)
     * @return string
     */
    public static function datetime($timestamp = null) {
        return null === $timestamp ? \date(DATE_W3C) : \date(DATE_W3C, $timestamp);
    }

    /**
     * Deep implode.
     * @param   array|mixed  $tokens
     * @param   string       $glue    Defaults to a space.
     * @return  string
     */
    public static function implode($tokens, $glue = ' ') {
        if (\is_scalar($tokens)) return \trim($tokens);
        if ( ! $tokens) return '';
        $ret = array();
        foreach ($tokens as $v)
            $ret[] = self::implode($v, $glue); # flatten
        return \implode($glue, $ret);
    }
    
    /**
     * @param   string|mixed  $tokens
     * @param   string|array  $glue    One or more delimiters.
     * @return  array
     */
    public static function explode($tokens, $glue = ' ') {
        if (\is_string($tokens)) $tokens = \trim($tokens);
        elseif ( ! $tokens || \is_scalar($tokens)) return (array) $tokens;
        else $tokens = self::implode(\is_array($glue) ? $glue[0] : $glue, (array) $tokens);
        if ('' === $tokens) return array(); # Applies to first or last condition above.
        \is_array($glue) and $tokens = \str_replace($glue, $glue = $glue[0], $tokens); # Normalize glue.
        return \ctype_space($glue) ? \preg_split('#\s+#', $tokens) : \explode($glue, $tokens);
    }
    
    /**
     * @param   mixed   $html
     * @return  string
     */
    public static function express($html) {
        if ( ! $html) return \is_numeric($html) ? (string) $html : '';
        return \is_scalar($html) ? (string) $html : static::dom($html)->saveHTML();
    }
    
    /**
     * A hash for token types (csv, etc.) Get or set $delimiter for the specified $name.
     * @param   string       $name 
     * @param   string=      $delimiter
     * @return  string|null
     */
    protected static function delimiter($name, $delimiter = null) {
        # dev.w3.org/html5/spec-author-view/index.html#attributes-1
        # whatwg.org/specs/web-apps/current-work/multipage/microdata.html#names:-the-itemprop-attribute
        static $hash;
        $hash or $hash = \array_merge(
            \array_fill_keys(\explode('|', 'accept|media'), ','),
            \array_fill_keys(
                \explode('|', 'class|rel|itemprop|accesskey|dropzone|headers|sizes|sandbox|accept-charset')
            , ' ')
        );
        $name = \mb_strtolower($name);
        isset($hash[$name]) or (\is_string($delimiter) and $hash[$name] = $delimiter);
        return $hash[$name];
    }
    
    /**
     * @param   mixed        $value   value to encode
     * @param   string|null  $name    optional attribute name
     * @param   bool         $retain  whether to keep null|bool values as is
     * @return  string
     */
    public static function encode($value, $name = null, $retain = null) {
        if (\is_string($value))
            return \str_replace("'", '&apos;', $value ? static::esc($value, ENT_NOQUOTES) : $value);

        $retain = true === $retain;
        if ( ! \is_scalar($value)) {
            if ( ! $value)
                return null === $value ? $retain ? null : 'null' : '';
            if ($value instanceof \Closure)
                return self::encode($value(), $name, $retain);
            if ($name && \is_string($d = static::delimiter($name)))
                return self::encode(self::implode($value, $d));
        }

        if ($retain && \in_array($value, array(false, true, null))) return $value;
        return \str_replace("'", '&apos;', \json_encode($value)); # bool|number|array|object
    }

    /**
     * @param   string       $value
     * @param   string|null  $name
     * @return  mixed
     */    
    public static function decode($value, $name = null) {
        if ( ! $value || ! \is_string($value))
            return $value;
        if ($name && \is_string($d = static::delimiter($name)))
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
     * @param   mixed   $text
     * @param   string  $replacement
     * @return  string|array
     */
    public static function respace($text, $replacement = ' ') {
        return \preg_replace('#\s+#', $replacement, $text);
    }

    /**
     * Replace or normalize linebreaks.
     * @param   mixed   $text
     * @param   string  $replacement
     * @return  string|array
     */
    public static function rebreak($text, $replacement = "\n\n") {
        return \preg_replace('#\n+\s*\n+#', $replacement, $text);
    }
    
    /**
     * Produce an attributes string. Null values are skipped. Booleans
     * convert properly to boolean atts. Other values encode via ::encode().
     * @param  mixed    $name   An array of name/value pairs, or an ssv string of attr 
                                names, or an indexed array of attr names.
     * @param  mixed    $value  Attr value for context when $name is an attribute name.
     */
    public static function atts($name, $value = '') {
        # non-assoc recursion
        \is_int($name) and ($name = $value) === ($value = '');

        # func args
        $name and $name = static::result($name); 
        
        # false boolean atts | null names/values
        # dev.w3.org/html5/spec/common-microsyntaxes.html#boolean-attributes
        if (false === $value || null === $value || null === $name || \is_bool($name))
            return '';

        # Name may need parsing or sanitizing:
        if (\is_scalar($name) && ($name = \trim($name)) && \ctype_alpha($name))
            # parse if it looks already stringified like `title=""` or `async defer`
            $name = \preg_match('#(\=|\s)#', $name) ? static::parseAtts($name) : static::attname($name);
        
        # key/value map a.k.a. "array to attr"
        if ( ! \is_scalar($name)) {
            $value = array();
            foreach ($name as $k => $v)
                \strlen($pair = self::atts($k, $v)) and $value[] = $pair;
            return \implode(' ', $value);
        }
        
        # <p contenteditable> === <p contenteditable="">
        # Use single quotes for compatibility with JSON
        return '' === $value || true === $value || '' === $name || (
            true === ($value = static::encode($value, $name)) || '' === $value
        ) ? $name : (false === $value || null === $value ? '' : "$name='$value'");
    }
    
    /**
     * Parse a string of attributes into an array. If the string 
     * starts with a tag, then atts on the first tag are parsed.
     * @param   string|mixed  $atts
     * @return  array
     * @example parseAtts('src="example.jpg" alt="example"')
     * @example parseAtts('<img src="example.jpg" alt="example">')
     * @example parseAtts('<a href="example"></a>')
     * @example parseAtts('<a href="example">')
     */
    public static function parseAtts($atts) {
        if ( ! \is_scalar($atts))
            return (array) $atts;

        # trim, then strip tagname (if present), then split into array
        $atts = \str_split(\preg_replace('#^<+\S*#', '', \trim($atts)));

        $arr = array(); # output
        $name = '';     # for the current attr being parsed
        $value = '';    # for the current attr being parsed
        $mode = 0;      # whether current char is part of the name (-), value (+), or neither (0)
        $stop = false;  # delimiter for the current $value being parsed
        $space = ' ';   # a single space

        foreach ($atts as $j => $curr) {
            if ($mode < 0) {# name
                if ('=' === $curr) {
                    $mode = 1;
                    $stop = false;
                } elseif ('>' === $curr) {
                    '' === $name or $arr[$name] = $value;
                    break; 
                } elseif ( ! \ctype_space($curr)) {
                    if (\ctype_space($atts[$j-1])) { # previous char
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
        $html and $html = static::result($html);
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
    
    /**
     * @param  mixed         $html
     * @param  string|array  $tags  whitelist
     * @return string
     */
    public static function keep($html, $tags = null) {
        return \strip_tags(static::express($html), \array_reduce(self::explode($tags), function($kept, $tag) {
            return \strlen($tag = \trim($tag, '</>')) ? "$kept<$tag>" : $kept;
        }, ''));
    }
    
    /**
     * @param  mixed         $html
     * @param  string|array  $tags  blacklist
     * @return string
     */
    public static function ban($html, $tags = null) {
        $tags = self::explode($tags);
        $html = static::dom($html);
        foreach ($tags as $tag)
            foreach ($html->getElementsByTagName(\trim($tag, '</>')) as $node)
                $node->parentNode->removeChild($node);
        return $html->saveHTML();
    }
    
    /**
     * @param  string $html
     * @return string
     */
    public static function cdata($html) {
        return "<![CDATA[$html]]>";
    }
}