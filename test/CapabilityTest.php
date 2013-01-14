<?php

require_once '../lib/ably.php';
require_once 'factories/TestOption.php';

class CapabilityTest extends PHPUnit_Framework_TestCase {

    protected static $options;
    protected $defaults;

    public static function setUpBeforeClass() {

        self::$options = TestOption::get_instance()->get_opts();

    }

    public static function tearDownAfterClass() {
        TestOption::get_instance()->clear_opts();
    }

    protected function setUp() {

        $options = self::$options;
        $defaults = array(
            'debug' => true,
            'encrypted' => $options['encrypted'],
            'host' => $options['host'],
            'port' => $options['port'],
        );

        $this->defaults = $defaults;
    }

    /**
     * Blanket intersection with specified key
     */

    /**
     * Equal intersection with specified key
     */

    /**
     * Empty ops intersection
     */

    /**
     * Empty paths intersection
     */

    /**
     * Non-empty ops intersection
     */

    /**
     * Non-empty paths intersection
     */

    /**
     * Wildcard ops intersection
     */

    /**
     * Wildcard resources intersection
     */

}