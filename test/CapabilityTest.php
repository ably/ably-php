<?php

require_once dirname(__FILE__) . '/../lib/ably.php';
require_once 'factories/TestOption.php';

class CapabilityTest extends PHPUnit_Framework_TestCase {

    protected static $options;
    protected static $ably0;
    protected $ably;

    public static function setUpBeforeClass() {

        self::$options = TestOption::get_instance()->get_opts();
        self::$ably0 = new AblyRest(array(
            'debug'     => false,
            'encrypted' => self::$options['encrypted'],
            'host'      => self::$options['host'],
            'key'       => self::$options['first_private_api_key'],
            'port'      => self::$options['port'],
        ));

    }

    public static function tearDownAfterClass() {
        TestOption::get_instance()->clear_opts();
    }

    protected function setUp() {
        $this->ably = self::$ably0;
    }

    /**
     * Blanket intersection with specified key
     */
    public function testBlanketIntersectionWithSpecifiedKey() {
        $key = self::$options['keys'][1];
        $auth_options = array(
            'keyId' => $key->key_id,
            'keyValue' => $key->key_value,
        );
        $token_details = $this->ably->request_token($auth_options, null);
        $this->assertNotNull($token_details->id, 'Expected token id');
        $this->assertEquals($key->capability, $token_details->capability, 'Unexpected capability');
    }

    /**
     * Equal intersection with specified key
     */
    public function testEqualIntersectionWithSpecifiedKey() {
        $key = self::$options['keys'][1];
        $auth_options = array(
            'keyId' => $key->key_id,
            'keyValue' => $key->key_value,
        );
        $token_params = array(
            'capability' => json_decode($key->capability),
        );
        $token_details = $this->ably->request_token($auth_options, $token_params);
        $this->assertNotNull($token_details->id, 'Expected token id');
        $this->assertEquals($key->capability, $token_details->capability, 'Unexpected capability');
    }

    /**
     * Empty ops intersection
     */
    public function testEmptyOpsIntersection() {
        echo '==testEmptyOpsIntersection()';
        $key = self::$options['keys'][1];
        $auth_options = array(
            'keyId' => $key->key_id,
            'keyValue' => $key->key_value,
        );
        $capability = array( 'testchannel' => array('subscribe') );
        $token_params = array(
            'capability' => $capability,
        );
        try {
            $token_details = $this->ably->request_token($auth_options, $token_params);
            $this->fail('Invalid capability, expected rejection');
        } catch (Exception $e) {
            $this->assertEquals( 401, (int)substr((string)$e->getCode(),0,3), 'Unexpected error code' );
        }
    }

    /**
     * Empty paths intersection
     */
    public function testEmptyPathIntersection() {
        echo '==testEmptyPathIntersection()';
        $key = self::$options['keys'][1];
        $auth_options = array(
            'keyId' => $key->key_id,
            'keyValue' => $key->key_value,
        );
        $capability = array( 'testchannelx' => array('publish') );
        $token_params = array(
            'capability' => $capability,
        );
        try {
            $token_details = $this->ably->request_token($auth_options, $token_params);
            $this->fail('Invalid capability, expected rejection');
        } catch (Exception $e) {
            $this->assertEquals( 401, (int)substr((string)$e->getCode(),0,3), 'Unexpected error code' );
        }
    }

    /**
     * Non-empty ops intersection
     */
    public function testNonEmptyOpsIntersection() {
        echo '==testNonEmptyOpsIntersection()';
        $key = self::$options['keys'][4];
        $auth_options = array(
            'keyId' => $key->key_id,
            'keyValue' => $key->key_value,
        );
        $requested_capability = array( 'channel2' => array('presence', 'subscribe') );
        $expected_capability = array( 'channel2' => array('subscribe') );
        $token_params = array(
            'capability' => $requested_capability,
        );
        $token_details = $this->ably->request_token($auth_options, $token_params);
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertEquals( $expected_capability, (array)json_decode($token_details->capability), 'Unexpected capability' );
    }

    /**
     * Non-empty paths intersection
     */
    public function testNonEmptyPathsIntersection() {
        echo '==testNonEmptyPathsIntersection()';
        $key = self::$options['keys'][4];
        $auth_options = array(
            'keyId' => $key->key_id,
            'keyValue' => $key->key_value,
        );
        $requested_capability = array(
            'channel2' => array('presence', 'subscribe'),
            'channelx' => array('presence', 'subscribe'),
        );
        $expected_capability = array(
            'channel2' => array('subscribe'),
        );
        $token_params = array(
            'capability' => $requested_capability,
        );
        $token_details = $this->ably->request_token($auth_options, $token_params);
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertEquals( $expected_capability, (array)json_decode($token_details->capability), 'Unexpected capability' );
    }

    /**
     * Wildcard ops intersection
     */
    public function testWildcardOpsIntersection0() {
        echo '==testWildcardOpsIntersection0()';
        $key = self::$options['keys'][4];
        $auth_options = array(
            'keyId' => $key->key_id,
            'keyValue' => $key->key_value,
        );
        $requested_capability = array( 'channel2' => array('*') );
        $expected_capability = array( 'channel2' => array('publish', 'subscribe') );
        $token_params = array(
            'capability' => $requested_capability,
        );
        $token_details = $this->ably->request_token($auth_options, $token_params);
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertEquals( $expected_capability, (array)json_decode($token_details->capability), 'Unexpected capability' );
    }

    public function testWildcardOpsIntersection1() {
        echo '==testWildcardOpsIntersection1()';
        $key = self::$options['keys'][4];
        $auth_options = array(
            'keyId' => $key->key_id,
            'keyValue' => $key->key_value,
        );
        $requested_capability = array( 'channel6' => array('publish', 'subscribe') );
        $expected_capability = array( 'channel6' => array('publish', 'subscribe') );
        $token_params = array(
            'capability' => $requested_capability,
        );
        $token_details = $this->ably->request_token($auth_options, $token_params);
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertEquals( $expected_capability, (array)json_decode($token_details->capability), 'Unexpected capability' );
    }

    /**
     * Wildcard resources intersection
     */
    public function testWildcardResourcesIntersection0() {
        echo '==testWildcardResourcesIntersection0()';
        $key = self::$options['keys'][2];
        $auth_options = array(
            'keyId' => $key->key_id,
            'keyValue' => $key->key_value,
        );
        $requested_capability = array( 'cansubscribe' => array('subscribe') );
        $token_params = array(
            'capability' => $requested_capability,
        );
        $token_details = $this->ably->request_token($auth_options, $token_params);
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertEquals( $requested_capability, (array)json_decode($token_details->capability), 'Unexpected capability' );
    }

    public function testWildcardResourcesIntersection1() {
        echo '==testWildcardResourcesIntersection1()';
        $key = self::$options['keys'][2];
        $auth_options = array(
            'keyId' => $key->key_id,
            'keyValue' => $key->key_value,
        );
        $requested_capability = array( 'canpublish:check' => array('publish') );
        $token_params = array(
            'capability' => $requested_capability,
        );
        $token_details = $this->ably->request_token($auth_options, $token_params);
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertEquals( $requested_capability, (array)json_decode($token_details->capability), 'Unexpected capability' );
    }

    public function testWildcardResourcesIntersection2() {
        echo '==testWildcardResourcesIntersection2()';
        $key = self::$options['keys'][2];
        $auth_options = array(
            'keyId' => $key->key_id,
            'keyValue' => $key->key_value,
        );
        $requested_capability = array( 'cansubscribe:*' => array('subscribe') );
        $token_params = array(
            'capability' => $requested_capability,
        );
        $token_details = $this->ably->request_token($auth_options, $token_params);
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertEquals( $requested_capability, (array)json_decode($token_details->capability), 'Unexpected capability' );
    }

    /**
     * Invalid capabilities
     */
    public function testInvalidCapabilities0() {
        echo '==testInvalidCapabilities0()';
        $invalid_capability = array( 'channel0' => array('publish_') );
        $token_params = array(
            'capability' => $invalid_capability,
        );
        try {
            $this->ably->request_token(null, $token_params);
            $this->fail('Invalid capability, expected rejection');
        } catch (Exception $e) {
            $this->assertEquals( 40000, $e->getCode(), 'Unexpected error code' );
        }
    }

    public function testInvalidCapabilities1() {
        echo '==testInvalidCapabilities1()';
        $invalid_capability = array( 'channel0' => array('*', 'publish') );
        $token_params = array(
            'capability' => $invalid_capability,
        );
        try {
            $this->ably->request_token(null, $token_params);
            $this->fail('Invalid capability, expected rejection');
        } catch (Exception $e) {
            $this->assertEquals( 40000, $e->getCode(), 'Unexpected error code' );
        }
    }

    public function testInvalidCapabilities2() {
        echo '==testInvalidCapabilities2()';
        $invalid_capability = array( 'channel0' => array(0) );
        $token_params = array(
            'capability' => $invalid_capability,
        );
        try {
            $this->ably->request_token(null, $token_params);
            $this->fail('Invalid capability, expected rejection');
        } catch (Exception $e) {
            $this->assertEquals( 40000, $e->getCode(), 'Unexpected error code' );
        }
    }

}