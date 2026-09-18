<?php

namespace Wilkques\Console;

use Wilkques\Console\Contracts\Commandable;
use Wilkques\Console\Exceptions\InvalidSignatureException;

abstract class Command implements Commandable
{
    /**
     * command flag
     *
     * @var array
     */
    protected $origins = array();

    /**
     * command flag merge Explanation
     *
     * @var array
     */
    protected $options = array();

    /**
     * command arguments merge Explanation
     *
     * @var array
     */
    protected $arguments = array();

    /**
     * Command Explanation
     *
     * @var string
     */
    public $signature;

    /**
     * Command Description
     *
     * @var string
     */
    public $description;

    /**
     * @param array $options
     *
     * @return static
     */
    public function setOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array
     */
    public function options()
    {
        return $this->options;
    }

    /**
     * @param string|int $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function option($key, $default = null)
    {
        return array_get($this->options(), $key, $default);
    }

    /**
     * @param array $arguments
     *
     * @return static
     */
    public function setArguments($arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * @return array
     */
    public function arguments()
    {
        return $this->arguments;
    }

    /**
     * @param string|int $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function argument($key, $default = null)
    {
        return array_get($this->arguments(), $key, $default);
    }

    /**
     * @return string
     */
    public function getSignaturet()
    {
        return $this->signature;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param array $origins
     *
     * @return static
     */
    public function setOrigins($origins)
    {
        $this->origins = $origins;

        return $this;
    }

    /**
     * @return array
     */
    public function origins()
    {
        return $this->origins;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function origin($key)
    {
        return array_get($this->origins(), "options.{$key}");
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasOrigin($key)
    {
        return array_has($this->origins(), "options.{$key}");
    }

    /**
     * @return array
     */
    public function getHelper()
    {
        $parsed = $this->parseSignature();

        $command = $parsed['command'];

        $description = $this->getDescription();

        $signature = $this->getSignaturet();

        return compact('command', 'description', 'signature');
    }

    /**
     * Turn the raw $signature string into a structured array of the
     * command name plus raw argument/option tokens.
     *
     * Signature parsing is intentionally NOT routed through
     * Wilkques\Console\Parser: Parser is for CLI tokens, where a bare
     * "--force" means "the user turned this on" (default true). In a
     * *signature*, "{--force}" only declares that the option exists, and
     * must default to false until a caller actually passes it. Reusing
     * one parser for both would make either the CLI or the signature
     * side wrong.
     *
     * @return array array('command' => string, 'arguments' => string[], 'options' => array)
     *
     * @throws \Wilkques\Console\Exceptions\InvalidSignatureException
     */
    public function toArray()
    {
        $parsed = $this->parseSignature();

        return array(
            'command' => $parsed['command'],
            'arguments' => $parsed['arguments'],
            'options' => $parsed['options'],
        );
    }

    /**
     * @return array
     *
     * @throws \Wilkques\Console\Exceptions\InvalidSignatureException
     */
    protected function parseSignature()
    {
        $signature = (string) $this->getSignaturet();

        $trimmed = ltrim($signature);

        // C7 — an empty (or all-whitespace) signature cannot be parsed
        // into a command name and must fail loudly rather than silently
        // producing a null/empty command name.
        if ($trimmed === '' || !preg_match('/^\S+/', $trimmed, $nameMatch)) {
            throw new InvalidSignatureException(
                'Unable to determine the command name: the signature is empty or malformed.'
            );
        }

        $command = $nameMatch[0];

        // Non-greedy: stop at the FIRST closing "}", so tokens are never
        // accidentally merged across braces.
        preg_match_all('/\{\s*(.*?)\s*\}/', $signature, $tokenMatches);

        $arguments = array();

        $options = array();

        foreach ($tokenMatches[1] as $rawToken) {
            // Command source files in this project are CRLF; a multi-line
            // signature puts a stray "\r" inside a token unless it is
            // trimmed here.
            $rawToken = trim($rawToken);

            if ($rawToken === '') {
                continue;
            }

            // Split at the FIRST colon that is followed by whitespace (or
            // that ends the token), so both "name: desc" and "name : desc"
            // are recognised as the name/description separator, while a
            // default value that itself contains a colon (a DSN, a
            // timestamp, a URL) is left untouched because the colon there
            // is immediately followed by a non-whitespace character (N7).
            // Anything after the separator is a human-readable description
            // we don't need to keep.
            $parts = preg_split('/:(?=\s|$)/', $rawToken, 2);

            $namePart = trim($parts[0]);

            if ($namePart === '') {
                continue;
            }

            if (strpos($namePart, '--') === 0) {
                $option = $this->parseOptionToken(substr($namePart, 2));

                $options[$option['name']] = $option['default'];
            } else {
                $arguments[] = $namePart;
            }
        }

        return array(
            'command' => $command,
            'arguments' => $arguments,
            'options' => $options,
        );
    }

    /**
     * Parse the raw argument tokens from the signature (e.g. "username",
     * "username?", "username=guest", "tags*") into structured definitions.
     *
     * N10 — an array ("*") argument greedily consumes every remaining
     * positional value, so it is only valid as the LAST declared
     * argument; declaring one anywhere else must fail fast instead of
     * silently starving a later required argument.
     *
     * @return array Each item: array('name', 'optional', 'default', 'array')
     *
     * @throws \Wilkques\Console\Exceptions\InvalidSignatureException
     */
    public function getArgumentDefinitions()
    {
        $tokens = $this->toArray()['arguments'];

        $count = count($tokens);

        $definitions = array();

        foreach ($tokens as $index => $token) {
            $definition = $this->parseArgumentToken($token);

            if ($definition['array'] && $index !== $count - 1) {
                throw new InvalidSignatureException(sprintf(
                    'The array argument "%s" must be the last defined argument.',
                    $definition['name']
                ));
            }

            $definitions[] = $definition;
        }

        return $definitions;
    }

    /**
     * @param string $token
     *
     * @return array
     */
    protected function parseArgumentToken($token)
    {
        $token = trim($token);

        $optional = false;

        $default = null;

        $isArray = false;

        if (substr($token, -1) === '*') {
            $isArray = true;

            $optional = true;

            $token = substr($token, 0, -1);
        }

        if (strpos($token, '=') !== false) {
            list($token, $default) = explode('=', $token, 2);

            $optional = true;
        } elseif (substr($token, -1) === '?') {
            $optional = true;

            $token = substr($token, 0, -1);
        }

        return array(
            'name' => $token,
            'optional' => $optional,
            'default' => $default,
            'array' => $isArray,
        );
    }

    /**
     * Parse a signature option token (with its leading "--" already
     * stripped) into its declared name and default value.
     *
     * - "force"        -> default false  (a bare declaration is OFF by default)
     * - "force=false"  -> default false
     * - "force=true"   -> default true
     * - "name=guest"   -> default 'guest'
     * - "name="        -> default null   (N26 — accepts a value, none given)
     *
     * @param string $token
     *
     * @return array array('name' => string, 'default' => mixed)
     */
    protected function parseOptionToken($token)
    {
        if (strpos($token, '=') === false) {
            return array('name' => $token, 'default' => false);
        }

        list($name, $value) = explode('=', $token, 2);

        $default = $value === '' ? null : is_a_to($value);

        return array('name' => $name, 'default' => $default);
    }

    /**
     * Parse the raw option tokens from the signature into structured
     * definitions, parallel to getArgumentDefinitions().
     *
     * @return array Each item: array('name', 'default')
     */
    public function getOptionDefinitions()
    {
        $definitions = array();

        foreach ($this->toArray()['options'] as $name => $default) {
            $definitions[] = array('name' => $name, 'default' => $default);
        }

        return $definitions;
    }
}
