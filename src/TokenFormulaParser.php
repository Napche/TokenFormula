<?php

namespace TokenFormula;

/**
 * Defines TokenFormulaParser
 *
 */
class TokenFormulaParser
{
    /**
     * Formula to be parsed.
     *
     * @var string
     */
    private $formula;

    /**
     * List of available calculation callbacks.
     *
     * @var array
     */
    private $callbacks;

    /**
     * @var array
     */
    private $tokens;

    /**
     * @return string
     */
    public function getFormula()
    {
        return $this->formula;
    }

    /**
     * @param string $formula
     */
    public function setFormula($formula)
    {
        $this->formula = $formula;
    }

    /**
     * @return array
     */
    public function getCallbacks()
    {
        return $this->callbacks;
    }

    /**
     * @param array $callbacks
     */
    public function setCallbacks($callbacks)
    {
        $this->callbacks = $callbacks;
    }

    /**
     * @return array
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * @param array $tokens
     */
    public function setTokens(array $tokens)
    {
        $this->tokens = $tokens;
    }

    /**
     * TokenFormulaParser constructor.
     * @param array $tokens
     */
    public function __construct(array $tokens = [])
    {
        $this->buildCallbacks();
        $this->tokens = $tokens;
    }

    /**
     * Validate formula correctness.
     * @param string $formula
     * @throws \Exception
     */
    protected function validateFormula($formula) {
        if( substr_count($formula, '(') !== substr_count($formula, ')') ) {
            throw new \Exception("Invalid formula");
        }
    }

    /**
     * Parse a token formula string.
     *
     * @var string $formula
     * @var array $data
     *
     * @return int
     * @throws \Exception
     */
    public function replaceTokens($formula)
    {
        $tokens = [];

        // remove whitespaces.
        $this->formula = preg_replace('/\s+/', '', $formula);
        preg_match_all('/(?<=\[)([^\]]+)(?=\])/', $this->formula, $tokens);

        foreach ($tokens[0] as $token) {
            $token_value = $this->getTokenValue($token);
            $regex = '/\[' . $token . '\]/i';
            $formula = preg_replace($regex, $token_value, $this->formula);
        }
        return $formula;
    }

    /**
     * Parse Formula.
     *
     * @param string $string
     *
     * @return float|string
     * @throws \Exception
     */
    public function parseFormula($string)
    {
        $this->validateFormula($string);
        $string = $this->replaceTokens($string);

        while (strpos($string, '(') !== FALSE) {

            // Get the last occurence of a function.
            $last_opening_bracket = strrpos($string, '(');
            $next_closing_bracket = strpos($string, ')', $last_opening_bracket);
            $values_string = substr($string, $last_opening_bracket + 1, $next_closing_bracket - $last_opening_bracket - 1);
            $operator_string = substr($string, 0, $last_opening_bracket);

            $last_comma = strrpos($operator_string, ',');
            $last_bracket = strrpos($operator_string, '(');
            $last_point = strrpos($operator_string, ';');

            $operator_pos = max(array($last_comma, $last_bracket, $last_point));
            if ($operator_pos) {
                $callback = substr($operator_string, $operator_pos + 1, $last_opening_bracket - $operator_pos);
                $function_string = substr($string, $operator_pos + 1, $next_closing_bracket - $operator_pos);
            }
            else {
                $callback = $operator_string;
                $function_string = $string;
            }

            // Convert to lower to avoid trouble.
            $callback = strtolower($callback);

            // Are these grouped values?
            if (strpos($values_string,';') === FALSE) {
                $value = $this->calculateFormula($callback, $values_string);
            }
            else {
                $value = $this->processGroupedValues($callback, $values_string);

                $function_string = substr($string, $operator_pos + 1, $next_closing_bracket - $operator_pos);
            }
            $string = str_replace($function_string, $value, $string);
        }

        return $string;
    }

    /**
     * Process a grouped values string.
     *
     * @param string $callback
     * @param string $values_string
     *
     * @return string
     * @throws \Exception
     */
    protected function processGroupedValues($callback, $values_string) {
        $grouped = $multiple = $singular = array();

        $combo_values = explode(';', $values_string);
        // Loop and seperate singular & multiple value fields.
        foreach ($combo_values as $key => $substring) {
            $values = explode(',', $substring);
            if (count($values) == 1) {
                $singular[] = array_shift($values);
                unset($combo_values[$key]);
            }
            else {
                // Group multiple values by delta.
                foreach ($values as $i => $value) {
                    $multiple[$i][] = $value;
                }
            }
        }

        if (!empty($multiple)) {
            // Add singular fields to each multiple field range & convert to string.
            foreach ($multiple as $i => $multiple_values) {
                $value_string = implode(',', array_merge($multiple_values, $singular));
                $grouped[] = $this->calculateFormula($callback, $value_string);
            }

            return implode(',', $grouped);
        }
        else {
            return $this->calculateFormula($callback, implode(',', $singular));
        }
    }


    /**
     * Calculation using plugins.
     *
     * @param string $callback
     * @param string $values_string
     *
     * @return float
     * @throws \Exception
     */
    protected function calculateFormula($callback, $values_string)
    {
        if ($this->validateCallback($callback)) {

            $values = explode(',', $values_string);

            $callbacks = $this->getCallbacks();

            if (is_array($values) && isset($callbacks[$callback])) {
                $plugin = $callbacks[$callback];
                $return = $plugin->calculate($values);

                return $return;
            }
        }
        elseif (is_numeric($values_string)) {
            return floatval($values_string);
        }
        throw new \Exception("Unable to calculate formula");
    }

    /**
     * Code to function.
     *
     * @param string $caller
     *
     * @return bool
     */
    protected function validateCallback($caller)
    {
        $functions = $this->getCallbacks();

        return isset($functions[strtolower($caller)]);
    }

    /**
     * Available functions.
     *
     * @return array
     * @throws \Exception
     */
    protected function buildCallbacks() {
        $dir = __DIR__ . '/Formula';
        foreach ( scandir( $dir ) as $file ) {
            if ( substr( $file, 0, 2 ) !== '._' && preg_match( "/.php$/i" , $file ) ) {
                // TODO: remove when creating package.
                include $dir .'/'. $file;
                $class = "TokenFormula\Formula\\" . str_replace('.php', '', $file);
                $formula = new $class();
                if ($formula instanceof FormulaInterface) {
                    if (isset($this->callbacks[strtolower($formula->getCaller())])) {
                        throw new \Exception("Multiple formulas with identical callers detected");
                    }
                    $this->callbacks[strtolower($formula->getCaller())] = $formula;
                }
            }
        }

        return $this->callbacks;
    }

    /**
     * Replace tokens.
     *
     * @param string $token
     *
     * @return string
     * @throws \Exception
     */
    protected function getTokenValue($token)
    {
        if (isset($this->tokens[$token]) && is_numeric($this->tokens[$token])) {
            return $this->tokens[$token];
        }
        throw new \Exception("Unknown token");
    }

}