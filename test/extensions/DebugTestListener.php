<?php
class DebugTestListener extends PHPUnit_Framework_BaseTestListener {

    public function startTestSuite(PHPUnit_Framework_TestSuite $suite)
    {
        echo "Suite \"" . $suite->getName() . "\"\n";
    }

    public function endTestSuite(PHPUnit_Framework_TestSuite $suite)
    {
        echo "\n\n";
    }
    
    public function startTest(PHPUnit_Framework_Test $test) {
        echo "\n" . $test->getName().'... ';
    }

    public function endTest(PHPUnit_Framework_Test $test, $time) {
        echo "(" . round($time * 1000) . " ms)";
    }
}