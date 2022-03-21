<?php
namespace tests;

use Ably\Utils\Miscellaneous;

class UtilsTest extends \PHPUnit\Framework\TestCase {
    public function testPhpVersion() {
        $numericVersion = Miscellaneous::getNumeric('1.2.9-ubuntu-tef');
        $this->assertEquals('1.2.9', $numericVersion);

        $numericVersion = Miscellaneous::getNumeric('4.6-macos-t');
        $this->assertEquals('4.6', $numericVersion);
    }
}