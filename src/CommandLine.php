<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Output;

use Toknot\Output\Printer;
use Toknot\Input\CommandInput;
use Toknot\Misc\{Integer,Boolean,Char,Double};
use Toknot\Digital\Number;

class CommandLine {

    private static $readline = false;
    private static $ins = null;

    public function __construct() {
        if (extension_loaded('readline')) {
            self::$readline = true;
        }
    }

    public static function __callStatic($name, $argvs = []) {
        if (self::$ins === null) {
            self::$ins = new static();
        }
        $ref = new ReflectionMethod(self::$ins, $name);
        if ($ref->isPublic()) {
            return self::$ins->$name($argvs);
        }
        throw new Exception("method $name exists in " . get_called_class());
    }

    public function cli(): bool {
        return PHP_SAPI === 'cli';
    }

    public function readline() {
        return self::$readline;
    }

    /**
     * get terminal number of columns
     * 
     * @param int $defaultCols
     * @return int
     */
    public function getCols($defaultCols = 150) {
        $cols = $this->tput('cols');
        if (empty($cols)) {
            $size = trim(shell_exec('stty size'));
            if (!empty($size)) {
                list(, $cols) = explode(' ', $size);
            }
        }
        if (!$cols || $cols <= 20) {
            $cols = $defaultCols;
        }
        return $cols;
    }

    public function getShell() {
        if (isset($_SERVER['SHELL'])) {
            return $_SERVER['SHELL'];
        }
        return 'unknow';
    }

    public function newLine() {
        if (self::$readline) {
            readline_on_new_line();
        } else {
            echo PHP_EOL;
        }
    }

    public function read($prompt = '', $color = 0) {
        if (self::$readline) {
            $prompt = Printer::addCLIColor($prompt, $color);
            return readline($prompt);
        } else {
            $msg = Printer::addCLIColor($prompt, $color);
            Printer::put($msg);
            return CommandInput::raw();
        }
    }

    public function flushLine($msg, $color) {
        if (is_array($msg)) {
            $linenum = count($msg);
            $this->tput('cuu', $linenum, 0);
            $colorIsArr = is_array($color);
            foreach ($msg as $i => $l) {
                $this->printColorLine($l, $colorIsArr ? $color[$i] : $color);
            }
        } else {
            $this->printColorLine($msg, $color, "\r");
        }
    }

    /**
     * exec interactive shell
     * 
     * @param callable $callable    callable after input
     * @param string $prompt        shell prompt message
     */
    public function interactive(array $prompts, array $optionValue, $color = 0) {
        $colorArr = is_array($color);
        foreach ($prompts as $i => $p) {
            if(is_array($p)) {
                $default = $p[1];
                $prompt = $p[0];
            } else {
                $default = null;
                $prompt = $p;
            }
            $this->checkInput($prompt, $optionValue[$i], $default, $colorArr ? $color[$i] : $color);
        }
    }

    /**
     * 
     * @param string $prompt
     * @param array|string $optionValue
     * @param type $default
     * @param type $color
     * @return type
     */
    public function checkInput($prompt, $optionValue = [], $default = null, $color = 0) {
        if(is_array($optionValue)) {
            $prompt .= '(' . implode('/', $optionValue) . ')';
        }
        do {
            $input = $this->read($prompt, $color);
            if ($input === '' && is_scalar($default)) {
                return $default;
            }
            if ($opArr && in_array($input, $optionValue)) {
                return $input;
            } elseif ($optionValue instanceof Boolean && (CommandInput::rawOnYes($input) || CommandInput::rawOnOff($input))) {
                return $input;
            } elseif ($optionValue instanceof Integer && CommandInput::rawOnInt($input)) {
                return $input;
            } elseif ($optionValue === Number && is_numeric($input)) {
                return $input;
            } elseif($optionValue instanceof Char) {
                return $input;
            } elseif($optionValue === $input) {
                return $input;
            }
        } while (true);
    }

    protected function printColorLine($msg, $color, $last = '') {
        $msg = str_pad($msg, $this->getCols(), ' ');
        $colorMsg = Printer::addCLIColor($msg, $color) . $last;
        Printer::printn($colorMsg);
    }

    protected function tput(...$argv) {
        return trim(shell_exec("tput " . implode(' ', $argv)));
    }

}
