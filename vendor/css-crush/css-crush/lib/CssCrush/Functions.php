<?php
/**
 *
 * Custom CSS functions
 *
 */
namespace CssCrush;

class Functions
{
    protected static $builtins = array(

        // These functions must come first in this order.
        'query' => 'CssCrush\fn__query',

        // These functions can be any order.
        'math' => 'CssCrush\fn__math',
        'percent' => 'CssCrush\fn__percent',
        'pc' => 'CssCrush\fn__percent',
        'hsla-adjust' => 'CssCrush\fn__hsla_adjust',
        'hsl-adjust' => 'CssCrush\fn__hsl_adjust',
        'h-adjust' => 'CssCrush\fn__h_adjust',
        's-adjust' => 'CssCrush\fn__s_adjust',
        'l-adjust' => 'CssCrush\fn__l_adjust',
        'a-adjust' => 'CssCrush\fn__a_adjust',
    );

    public $register = array();

    protected $pattern;

    protected $patternOptions;

    public function __construct($register = array(), $pattern = null, $pattern_options = array())
    {
        $this->register = $register;
        $this->pattern = $pattern;
        $this->patternOptions = $pattern_options;
    }

    public function add($name, $callback)
    {
        $this->register[$name] = $callback;
    }

    public function remove($name)
    {
        unset($this->register[$name]);
    }

    public function setPattern($use_builtin = false)
    {
        $options = $this->patternOptions;
        if ($use_builtin) {
            $this->register = self::$builtins + $this->register;
            $options += array('bare_paren' => true);
        }
        $this->pattern = Regex::makeFunctionPatt(array_keys($this->register), $options);
    }

    public function apply($str, $callbacks = null, \stdClass $context = null)
    {
        if (strpos($str, '(') === false) {
            return $str;
        }

        if (! $this->pattern) {
            $this->setPattern();
        }

        if (! preg_match($this->pattern, $str)) {
            return $str;
        }

        if (! $context) {
            $context = new \stdClass();
        }

        $matches = Regex::matchAll($this->pattern, $str);

        while ($match = array_pop($matches)) {

            $offset = $match[0][1];

            if (! preg_match(Regex::$patt->parens, $str, $parens, PREG_OFFSET_CAPTURE, $offset)) {
                continue;
            }

            // No function name default to math expression.
            // Store the raw function name match.
            $raw_fn_name = isset($match[1]) ? strtolower($match[1][0]) : '';
            $fn_name = $raw_fn_name ? $raw_fn_name : 'math';
            if ('-' === $fn_name) {
                $fn_name = 'math';
            }

            $opening_paren = $parens[0][1];
            $closing_paren = $opening_paren + strlen($parens[0][0]);

            // Get the function arguments.
            $raw_args = trim($parens['parens_content'][0]);

            // Workaround the signs.
            $before_operator = '-' === $raw_fn_name ? '-' : '';

            $func_returns = '';
            $context->function = $fn_name;

            // Use override callback if one is specified.
            if (isset($callbacks[$fn_name])) {
                $func_returns = $callbacks[$fn_name]($raw_args, $context);
            }
            elseif (isset($this->register[$fn_name])) {
                $func = $this->register[$fn_name];
                $func_returns = $func($raw_args, $context);
            }

            // Splice in the function result.
            $str = substr_replace($str, "$before_operator$func_returns", $offset, $closing_paren - $offset);
        }

        return $str;
    }


    #############################
    #  API and helpers.

    public static function parseArgs($input, $allowSpaceDelim = false)
    {
        return Util::splitDelimList(
            $input, ($allowSpaceDelim ? '\s*[,\s]\s*' : ','));
    }

    // Intended as a quick arg-list parse for function that take up-to 2 arguments
    // with the proviso the first argument is an ident.
    public static function parseArgsSimple($input)
    {
        return preg_split(Regex::$patt->argListSplit, $input, 2);
    }
}


#############################
#  Stock CSS functions.

function fn__math($input) {

    list($expression, $unit) = array_pad(Functions::parseArgs($input), 2, '');

    // Swap in math constants.
    $expression = preg_replace(
        array('~\bpi\b~i'),
        array(M_PI),
        $expression);

    // Strip blacklisted characters.
    $expression = preg_replace('~[^\.0-9\*\/\+\-\(\)]~S', '', $expression);

    $result = @eval("return $expression;");

    return ($result === false ? 0 : round($result, 5)) . $unit;
}

function fn__percent($input) {

    // Strip non-numeric and non delimiter characters
    $input = preg_replace('~[^\d\.\s,]~S', '', $input);

    $args = preg_split(Regex::$patt->argListSplit, $input, -1, PREG_SPLIT_NO_EMPTY);

    // Use precision argument if it exists, use default otherwise
    $precision = isset($args[2]) ? $args[2] : 5;

    // Output zero on failure
    $result = 0;

    // Need to check arguments or we may see divide by zero errors
    if (count($args) > 1 && ! empty($args[0]) && ! empty($args[1])) {

        // Use bcmath if it's available for higher precision

        // Arbitary high precision division
        if (function_exists('bcdiv')) {
            $div = bcdiv($args[0], $args[1], 25);
        }
        else {
            $div = $args[0] / $args[1];
        }

        // Set precision percentage value
        if (function_exists('bcmul')) {
            $result = bcmul((string) $div, '100', $precision);
        }
        else {
            $result = round($div * 100, $precision);
        }

        // Trim unnecessary zeros and decimals
        $result = trim((string) $result, '0');
        $result = rtrim($result, '.');
    }

    return $result . '%';
}

function fn__hsla_adjust($input) {
    list($color, $h, $s, $l, $a) = array_pad(Functions::parseArgs($input, true), 5, 0);
    return Color::colorAdjust($color, array($h, $s, $l, $a));
}

function fn__hsl_adjust($input) {
    list($color, $h, $s, $l) = array_pad(Functions::parseArgs($input, true), 4, 0);
    return Color::colorAdjust($color, array($h, $s, $l, 0));
}

function fn__h_adjust($input) {
    list($color, $h) = array_pad(Functions::parseArgs($input, true), 2, 0);
    return Color::colorAdjust($color, array($h, 0, 0, 0));
}

function fn__s_adjust($input) {
    list($color, $s) = array_pad(Functions::parseArgs($input, true), 2, 0);
    return Color::colorAdjust($color, array(0, $s, 0, 0));
}

function fn__l_adjust($input) {
    list($color, $l) = array_pad(Functions::parseArgs($input, true), 2, 0);
    return Color::colorAdjust($color, array(0, 0, $l, 0));
}

function fn__a_adjust($input) {
    list($color, $a) = array_pad(Functions::parseArgs($input, true), 2, 0);
    return Color::colorAdjust($color, array(0, 0, 0, $a));
}

function fn__this($input, $context) {

    $args = Functions::parseArgsSimple($input);
    $property = $args[0];

    // Function relies on a context rule, bail if none.
    if (! isset($context->rule)) {
        return '';
    }
    $rule = $context->rule;

    $rule->declarations->expandData('data', $property);

    if (isset($rule->declarations->data[$property])) {

        return $rule->declarations->data[$property];
    }

    // Fallback value.
    elseif (isset($args[1])) {

        return $args[1];
    }

    return '';
}

function fn__query($input, $context) {

    $args = Functions::parseArgs($input);

    // Function relies on a context property, bail if none.
    if (count($args) < 1 || ! isset($context->property)) {
        return '';
    }

    $call_property = $context->property;
    $references =& Crush::$process->references;

    // Resolve arguments.
    $name = array_shift($args);
    $property = $call_property;

    if (isset($args[0])) {
        $args[0] = strtolower($args[0]);
        if ($args[0] !== 'default') {
            $property = array_shift($args);
        }
        else {
            array_shift($args);
        }
    }
    $default = isset($args[0]) ? $args[0] : null;

    if (! preg_match(Regex::$patt->rooted_ident, $name)) {
        $name = Selector::makeReadable($name);
    }

    // If a rule reference is found, query its data.
    $result = '';
    if (isset($references[$name])) {
        $query_rule = $references[$name];
        $query_rule->declarations->process($query_rule);
        $query_rule->declarations->expandData('queryData', $property);

        if (isset($query_rule->declarations->queryData[$property])) {
            $result = $query_rule->declarations->queryData[$property];
        }
    }

    if ($result === '' && isset($default)) {
        $result = $default;
    }

    return $result;
}
