<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Output;

use Toknot\Digital\Number;
use Toknot\Input\CommandInput;
use Toknot\Misc\Boolean;
use Toknot\Misc\Char;
use Toknot\Misc\Integer;
use Toknot\Output\Printer;

class CommandLine
{

    private static $readline         = false;
    private static $ins              = null;
    public static $progMask          = '=';
    public static $tobeMask          = '-';
    public static $helpColumnSpacing = 8;

    public function __construct()
    {
        if (!self::$readline && extension_loaded('readline')) {
            self::$readline = true;
        }
    }

    public static function __callStatic($name, $argvs = [])
    {
        if (self::$ins === null) {
            self::$ins = new static();
        }
        $ref = new ReflectionMethod(self::$ins, $name);
        if ($ref->isPublic()) {
            return self::$ins->$name($argvs);
        }
        throw new Exception("method $name exists in " . get_called_class());
    }

    public function cli(): bool
    {
        return PHP_SAPI === 'cli';
    }

    public function readlineState()
    {
        return self::$readline;
    }

    /**
     * get terminal number of columns
     *
     * @param int $defaultCols
     * @return int
     */
    public function getCols($defaultCols = 150)
    {
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

    public function getShell()
    {
        if (isset($_SERVER['SHELL'])) {
            return $_SERVER['SHELL'];
        }
        return 'unknow';
    }

    public function newLine()
    {
        if (self::$readline) {
            readline_on_new_line();
        } else {
            echo PHP_EOL;
        }
    }

    public function read($prompt = '', $color = 0)
    {
        if (self::$readline) {
            $prompt = Printer::addCLIColor($prompt, $color);
            return readline($prompt);
        } else {
            $msg = Printer::addCLIColor($prompt, $color);
            Printer::put($msg);
            return CommandInput::raw();
        }
    }

    public function flushLine($msg, $color)
    {
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
    public function interactive(array $prompts, array $optionValue, $color = 0)
    {
        $colorArr = is_array($color);
        foreach ($prompts as $i => $p) {
            if (is_array($p)) {
                $default = $p[1];
                $prompt  = $p[0];
            } else {
                $default = null;
                $prompt  = $p;
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
    public function checkInput($prompt, $optionValue = [], $default = null, $color = 0)
    {
        if (is_array($optionValue)) {
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
            } elseif ($optionValue instanceof Char) {
                return $input;
            } elseif ($optionValue === $input) {
                return $input;
            }
        } while (true);
    }

    public function setBgProgMask()
    {
        self::$progMask = Printer::addCLIColor(' ', Printer::COLOR_B_WHITE);
    }

    public function progress($percent, string $prompt, string $lastMsg)
    {
        if (\is_numeric($percent)) {
            throw new Exception("progress percent must between 0 and 100 number each");
        }
        $percent     = \round($percent, 2);
        $prolen      = $this->getCols() - strlen($prompt . $lastMsg . $percent) - 5;
        $progCharLen = round($prolen / 100, 2);
        $cur         = ceil($progCharLen * $percent);

        $will     = $prolen - $cur;
        $progMask = self::$progMask;
        $tobeMask = self::$tobeMask;
        $msg      = $prompt . sprintf(" [%'{$progMask}{$cur}s%'{$tobeMask}{$will}s] {$percent}%", $progMask, '') . $lastMsg;
        $this->flushLine($msg);
    }

    public function showHelp(array $options, $color = null)
    {
        $max = [];
        $i   = 0;
        foreach ($options as $i => $row) {
            if ($i !== 0 && \is_array($row)) {
                $i++;
                continue;
            }
            $len     = strlen($row[0]);
            $max[$i] = $len > $max ? $len : $max;
        }
        $k = 0;
        foreach ($options as $row) {
            if (\is_scalar($row)) {
                Printer::printn($row);
                $k++;
            } elseif (count($row) > 2) {
                $desc = $row[1];
                unset($row[1]);
                $msg = join(', ', $row);
                Printer::printn($msg);
                $spaceLen   = $max[$k] + self::$helpColumnSpacing;
                $this->printOptionDesc($desc, $spaceLen);
            } elseif(count($row) == 1) {
                Printer::printn($row[0]);
            } else {
                $space = $max[$k] - strlen($row[0]) + self::$helpColumnSpacing;
                $msg   = sprintf("{$row[0]}%-{$space}s", '');
                Printer::put($msg);
                $optLen  = strlen($msg);
                $this->printOptionDesc($row[1], $optLen, $pad);
            }
        }
    }

    protected function printOptionDesc($optDesc, $optLen)
    {
        $descLen = $this->getCols() - $optLen;
        $pad     = sprintf("%{$optLen}s", '');
        $descOffset = 0;
        while (true) {
            $desc = substr($optDesc, $descOffset, $descLen);
            if (!$desc) {
                break;
            }
            $descOffset += $deslen;
            Printer::printn($pad . $desc);
        }
    }

    protected function printColorLine($msg, $color, $last = '')
    {
        $msg      = str_pad($msg, $this->getCols(), ' ');
        $colorMsg = Printer::addCLIColor($msg, $color) . $last;
        Printer::printn($colorMsg);
    }

    protected function tput(...$argv)
    {
        return trim(shell_exec("tput " . implode(' ', $argv)));
    }

}
