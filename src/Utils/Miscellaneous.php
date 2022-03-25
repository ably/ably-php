<?php
namespace Ably\Utils;

class Miscellaneous
{
    public static function getNumeric($text)
    {
        preg_match("#^\d+(\.\d+)*#", $text, $match);
        if (isset($match[0])) {
            return $match[0];
        }
        return "";
    }
}