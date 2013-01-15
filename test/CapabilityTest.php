<?php

require_once '../lib/ably.php';
require_once 'factories/TestOption.php';

class CapabilityTest extends PHPUnit_Framework_TestCase {

    protected static $options;
    protected $ably;

    public static function setUpBeforeClass() {

        self::$options = TestOption::get_instance()->get_opts();

    }

    public static function tearDownAfterClass() {
        TestOption::get_instance()->clear_opts();
    }

    protected function setUp() {

        $options = self::$options;
        $defaults = array(
            'debug'     => false,
            'encrypted' => $options['encrypted'],
            'host'      => $options['host'],
            'key'       => $options['first_private_api_key'],
            'port'      => $options['port'],
        );

        $this->ably = new Ably( $defaults );
    }

    /**
     * Blanket intersection with specified key
     */
    public function testBlanketIntersectionWithSpecifiedKey() {
        $token_details = $this->ably->request_token();
        $this->assertNotNull($token_details->id, 'Expected token id');
        $this->assertEquals(self::$options['capability'], $token_details->capability, 'Unexpected capability');
    }

    /**
     * Equal intersection with specified key
     */
    public function testEqualIntersectionWithSpecifiedKey() {
        $token_details = $this->ably->request_token();
        $this->assertNotNull($token_details.id, 'Expected token id');
        $this->assertEquals(self::$options['capability'], $token_details->capability, 'Unexpected capability');
    }

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