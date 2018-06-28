<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Output;

use Exception;
use ReflectionMethod;
use Toknot\Output\CommandLine;

class Printer {

    private static $ins = null;
    private $cli = false;
    private $color = false;

    const COLOR_BLACK = 188;
    const COLOR_RED = 190;
    const COLOR_GREEN = 192;
    const COLOR_YELLOW = 194;
    const COLOR_BLUE = 196;
    const COLOR_PURPLE = 198;
    const COLOR_WHITE = 202;
    const COLOR_B_BLACK = 10240;
    const COLOR_B_RED = 10496;
    const COLOR_B_GREEN = 10752;
    const COLOR_B_YELLOW = 11008;
    const COLOR_B_BLUE = 11264;
    const COLOR_B_PURPLE = 11520;
    const COLOR_B_WHITE = 12032;
    const SET_BOLD = 1;
    const COLOR_MAPS = [188 => 'black', 190 => 'red', 192 => 'green', 194 => 'yellow',
        196 => 'blue', 198 => 'purple', 202 => 'white', 10240 => 'black', 10496 => 'red',
        10752 => 'green', 11008 => 'yellow', 11264 => 'blue', 11520 => 'purple', 12032 => 'white'];

    public function __construct() {
        self::$cli = CommandLine::cli();
        self::$ins = $this;
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

    public static function colorException($color) {
        throw new Exception("unknown,error or unsupport color value $color");
    }

    public function enableColor() {
        $this->color = true;
    }

    public function disableColor() {
        $this->color = false;
    }

    public function dump(...$mix) {
        $this->pre();
        if ($this->color) {
            foreach ($mix as $v) {
                $this->colorDump($v);
            }
        } else {
            call_user_func_array('var_dump', $mix);
        }
        $this->pre(true);
    }

    public function put(...$mix) {
        foreach ($mix as $v) {
            if (is_object($v) || is_resource($v)) {
                var_dump($v);
            } elseif (is_array($v)) {
                print_r($v);
            } else {
                echo $v;
            }
        }
    }

    public function printn(...$mix) {
        $this->pre();
        foreach ($mix as $v) {
            if (is_object($v) || is_resource($v)) {
                var_dump($v);
                $this->nl();
            } elseif (is_array($v)) {
                print_r($v);
                $this->nl();
            } else {
                echo $v;
                $this->nl();
            }
        }
        $this->pre(true);
    }

    public function error($mix) {
        ob_start();
        $this->printn($mix);
        $b = ob_get_contents();
        ob_end_clean();
        if ($this->cli) {
            fwrite(STDERR, $b);
        } else {
            trigger_error($b);
        }
    }

    public function addColor($str, $color) {
        if ($this->cli) {
            return $this->addCLIColor($str, $color);
        }
        return $this->addWebColor($str, $color);
    }

    /**
     * add color for string,only support font color,bg color, whether set blod
     * 
     * @param string $str
     * @param int $color
     * @return string
     */
    public function addCLIColor($str, $color) {
        $mask2 = 1 << 7;

        if (!is_numeric($color) && is_string($color)) {
            $color = $this->strToColor($color);
        } elseif (!is_numeric($color)) {
            return $str;
        }
        $colorCode = '';
        if ($color & self::SET_BOLD) {
            $colorCode .= '1;';
        }
        $bg = ($color >> 8);
        if ($bg && $bg >= 40 && $bg <= 49) {
            $colorCode .= "$bg;";
        }
        $fcolor = $bg ? ($color ^ ($bg << 8)) : $color;
        $fcolor && $fcolor = (($fcolor ^ $mask2) >> 1);
        if ($fcolor && $fcolor >= 30 && $fcolor <= 39) {
            $colorCode .= "$fcolor;";
        }

        if ($colorCode) {
            $colorCode = trim($colorCode, ';');
            return "\033[{$colorCode}m{$str}\033[0m";
        }
        return $str;
    }

    public function addWebColor($str, $color) {
        if (is_numeric($color)) {
            $style = $this->webMaskColor($color);
        } else {
            $style = $this->webStrColor($color);
        }
        return "<span style=\"$style\">$str</span>";
    }

    protected function colorDump($v) {
        ob_start();
        var_dump($v);
        $p = ob_get_contents();
        ob_end_clean();
        if ((is_scalar($v) && !is_string($v)) || is_null($v)) {
            echo $this->addCLIColor($p, self::COLOR_GREEN);
        } elseif (is_string($v)) {
            $p = trim($p, '"');
            $this->printPad('"');
            echo $this->addColor($p, self::SET_BOLD);
            $this->printPad('"');
        } elseif (is_array($v)) {
            $this->colorDumpArrayObject($p, 5);
        } elseif (is_object($v)) {
            $this->colorDumpArrayObject($p, 6);
        } elseif (is_resource($v)) {
            echo $this->addColor($p, self::COLOR_BLUE);
        } else {
            echo $p;
        }
    }

    protected function colorDumpArrayObject($p, $typeLen) {
        $data = explode("\n", trim($p), 2);
        $typeName = substr($data[0], 0, $typeLen);
        $type = substr($data[0], $typeLen, -1);
        echo $this->addColor($typeName, self::COLOR_GREEN | self::SET_BOLD);
        echo $this->addColor($type, self::SET_BOLD);
        $this->printPad('{');
        $this->nl();
        echo substr($data[1], 0, -1);
        $this->nl();
        $this->printPad('}');
        $this->nl();
    }

    protected function printPad($char) {
        echo $this->addColor($char, self::COLOR_RED | self::SET_BOLD);
    }

    protected function webMaskColor($color) {
        $style = '';
        if ($color & self::SET_BOLD) {
            $style .= $this->setWebBold();
        }
        $bg = ($color >> 8 << 8);
        if (isset(self::COLOR_MAPS[$bg])) {
            $style .= $this->setBgColor(self::COLOR_MAPS[$bg]);
        } elseif ($bg) {
            self::colorException($color);
        }
        $fcolor = $bg ? ($color ^ ($bg << 8)) : $color;

        if (isset(self::COLOR_MAPS[$fcolor])) {
            $style .= $this->setWebColor(self::COLOR_MAPS[$fcolor]);
        } elseif ($fcolor) {
            self::colorException($color);
        }
        return $style;
    }

    protected function nl() {
        if ($this->cli) {
            CommandLine::newLine();
        } else {
            echo '<br/>';
        }
    }

    protected function setWebBold() {
        return 'font-weight: bold;';
    }

    protected function setWebColor($color) {
        return "color: $color;";
    }

    protected function setBgColor($color) {
        return "background-color: $color;";
    }

    protected function webStrColor($color) {
        $colors = explode('|', $color);
        $style = '';
        if ($colors[0]) {
            $style .= $this->setWebColor($this->webColor($colors[0]));
        }
        if (isset($colors[2])) {
            $style .= $this->setWebBold();
        }
        if (isset($colors[1])) {
            $style .= $this->setBgColor($this->webColor($colors[1]));
        }
        return $style;
    }

    protected function pre($end = false) {
        if (!$this->cli) {
            $endSlash = $end ? '/' : '';
            echo "<{$endSlash}pre>";
        }
    }

    protected function webColor($color) {
        if (preg_match('/^#[0-9A-F]+$/i', $color, 1)) {
            return $color;
        }
        $rgbColor = $this->rgb($color);
        if ($rgbColor) {
            return $rgbColor;
        }
        $hslColor = $this->hsl($color);
        if ($hslColor) {
            return $hslColor;
        }
        $str = strtoupper($color);
        $fcv = "static::COLOR_{$str}";
        if (defined($fcv)) {
            return $fcv;
        }
        self::colorException($color);
    }

    protected function rgb($color) {
        $matches = [];
        if (preg_match('/^rgb\((\d{1,3}\.\d{1,3}\.\d{1,3})\)$/i', $color, $matches)) {
            $rgb = explode('.', $matches[1]);
            foreach ($rgb as $n) {
                if ($n < 0 && $n > 255) {
                    self::colorException($color);
                }
            }
            return $color;
        }
        return false;
    }

    protected function hsl($color) {
        $matches = [];
        if (preg_match('/^hsl\((\d{1,3},\d{1,3}%,\d{1,3}%)\)$/i', $color, $matches)) {
            $hsl = explode(',', $matches[1]);
            if ($hsl[0] > 360 || $hsl[0] < 0) {
                throw new Exception("hsl color value: '$color' error ");
            }
            $hsl[1] = trim($hsl[1], '%');
            $hsl[2] = trim($hsl[2], '%');
            if ($hsl[1] < 0 || $hsl[1] > 100 || $hsl[2] < 0 || $hsl[2] > 100) {
                self::colorException($color);
            }
            return $color;
        }
        return false;
    }

    protected function strToColor($color) {
        $colors = explode('|', $color);
        $v = 0;
        foreach ($colors as $cs) {
            $str = strtoupper($cs);
            $fcv = "static::COLOR_{$str}";
            if (defined($fcv)) {
                $v = $v | constant($fcv);
                continue;
            }
            $bcv = "static::SET_$str";
            if (defined($bcv)) {
                $v = $v | constant($bcv);
                continue;
            }
            self::colorException($color);
        }
        return $v;
    }

}
