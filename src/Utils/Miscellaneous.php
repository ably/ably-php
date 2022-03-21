<?php
namespace Ably\Utils;

class Miscellaneous
{
    public static function getNumeric($text)
    {
        preg_match("#^\d+(\.\d+)*#", $text, $match);
        return $match[0];
    }
}