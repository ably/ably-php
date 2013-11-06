<?php

require_once '../lib/ably.php';
require_once 'factories/TestOption.php';

class TokenTest extends PHPUnit_Framework_TestCase {

    protected static $options;
    protected $ably;
    protected $token_params;
    protected $error_margin = 10;

    public static function setUpBeforeClass() {

        self::$options = TestOption::get_instance()->get_opts();

    }

    public static function tearDownAfterClass() {
        TestOption::get_instance()->clear_opts();
    }

    protected function setUp() {

        $options = self::$options;
        $defaults = array(
            'debug'     => true,
            'encrypted' => $options['encrypted'],
            'host'      => $options['host'],
            'key'       => $options['first_private_api_key'],
            'port'      => $options['port'],
        );

        $this->ably = new AblyRest( $defaults );
        $this->permit_all = json_decode(json_encode(array('*' => array('*'))));
        #$this->token_params = array('id' => '', 'ttl' =>  null, 'capability' => '', 'client_id' => '', 'timestamp' => null, 'nonce' => '', 'mac' => '');
        $this->token_params = array();
    }

    /**
     * Base requestToken case with null params
     */
    public function testBaseRequestTokenWithNullParams() {
        echo '==testBaseRequestTokenWithNullParams()';
        $request_time = $this->ably->time_in_seconds();
        $token_details = $this->ably->request_token(null, null);
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertTrue( ($token_details->issued_at >= $request_time - $this->error_margin) && ($token_details->issued_at <= $request_time + $this->error_margin), 'Unexpected issued_at time' );
        $this->assertEquals( $token_details->issued_at + 60*60, $token_details->expires, 'Unexpected expires time' );
        $this->assertEquals( $this->permit_all, $token_details->capability, 'Unexpected capability' );
    }

    /**
     * Base requestToken case with non-null but empty params
     */
    public function testBaseRequestTokenWithNonNullButEmptyParams() {
        echo '==testBaseRequestTokenWithNonNullButEmptyParams()';
        $request_time = $this->ably->time_in_seconds();
        $params = $this->token_params;
        $token_details = $this->ably->request_token(null, $params);
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertTrue( ($token_details->issued_at >= $request_time - $this->error_margin) && ($token_details->issued_at <= $request_time + $this->error_margin), 'Unexpected issued_at time' );
        $this->assertEquals( $token_details->issued_at + 60*60, $token_details->expires, 'Unexpected expires time' );
        $this->assertEquals( $this->permit_all, $token_details->capability, 'Unexpected capability' );
    }

    /**
     * requestToken with explicit timestamp
     */
    public function testRequestTokenWithExplicitTimestamp() {
        echo '==testRequestTokenWithExplicitTimestamp()';
        $request_time = $this->ably->time_in_seconds();
        $params = array_merge($this->token_params, array(
            'timestamp' => $request_time
        ));
        $token_details = $this->ably->request_token(null, $params);
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertTrue( ($token_details->issued_at >= $request_time - $this->error_margin) && ($token_details->issued_at <= $request_time + $this->error_margin), 'Unexpected issued_at time' );
        $this->assertEquals( $token_details->issued_at + 60*60, $token_details->expires, 'Unexpected expires time' );
        $this->assertEquals( $this->permit_all, $token_details->capability, 'Unexpected capability' );
    }

    /**
     * requestToken with explicit, invalid timestamp
     */
    public function testRequestTokenWithExplicitInvalidTimestamp() {
        echo '==testRequestTokenWithExplicitInvalidTimestamp()';
        $request_time = $this->ably->time_in_seconds();
        $params = array_merge($this->token_params, array(
            'timestamp' => $request_time - 30 * 60
        ));
        try {
            $this->ably->request_token(null, $params);
            $this->fail('Expected token request rejection');
        } catch (Exception $e) {
            $this->assertEquals( 40101, $e->getCode(), 'Unexpected error code' );
        }
    }

    /**
     * requestToken with system timestamp
     */
    public function testRequestWithSystemTimestamp() {
        echo '==testRequestWithSystemTimestamp()';
        $request_time = time();
        $auth_options = array('query' => true);
        $token_details = $this->ably->request_token($auth_options, null);
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertTrue( ($token_details->issued_at >= $request_time - $this->error_margin) && ($token_details->issued_at <= $request_time + $this->error_margin), 'Unexpected issued_at time' );
        $this->assertEquals( $token_details->issued_at + 60*60, $token_details->expires, 'Unexpected expires time' );
        $this->assertEquals( $this->permit_all, $token_details->capability, 'Unexpected capability' );
    }

    /**
     * requestToken with duplicate nonce
     */
    public function testRequestTokenWithDuplicateNonce() {
        echo '==testRequestTokenWithDuplicateNonce()';
        $request_time = $this->ably->time_in_seconds();
        $token_params = array(
            'timestamp' => $request_time,
            'nonce' => "1234567890123456",
        );
        $token_details = $this->ably->request_token( null, $token_params );
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        try {
            $this->ably->request_token( null, $token_params );
        } catch (Exception $e) {
            $this->assertEquals( 40101, $e->getCode(), 'Unexpected error code' );
        }
    }

    /**
     * Base requestToken case with non-null but empty params
     */
    public function testBaseRequestTokenCaseWithNonNullButEmptyParams() {
        echo '==testBaseRequestTokenCaseWithNonNullButEmptyParams()';
        $request_time = $this->ably->time_in_seconds();
        $token_params = array(
            'client_id' => 'test client id',
        );
        $token_details = $this->ably->request_token( null, $token_params );
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertTrue( ($token_details->issued_at >= $request_time - $this->error_margin) && ($token_details->issued_at <= $request_time + $this->error_margin), 'Unexpected issued_at time' );
        $this->assertEquals( $token_details->issued_at + 60*60, $token_details->expires, 'Unexpected expires time' );
        $this->assertEquals( $this->permit_all, $token_details->capability, 'Unexpected capability' );
        $this->assertEquals( $token_params['client_id'], $token_details->client_id, 'Unexpected clientId' );
    }

    /**
     * Token generation with capability that subsets key capability
     */
    public function testTokenGenerationWithCapabilityKey() {
        echo '==testTokenGenerationWithCapabilityKey()';
        $capability = array( 'onlythischannel' => array('subscribe') );
        $capability_obj = json_decode(json_encode($capability), false);
        $token_params = array('capability' => $capability );
        $token_details = $this->ably->request_token( null, $token_params );
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertEquals( $token_details->capability, $capability_obj, 'Unexpected capability' );
    }

    /**
     * Token generation with specified key
     */
    public function testTokenGenerationWithSpecifiedKey() {
        echo '==testTokenGenerationWithSpecifiedKey()';
        $key = self::$options['keys'][1];
        $auth_options = array(
            'keyId' => $key->key_id,
            'keyValue' => $key->key_value,
        );
        $token_details = $this->ably->request_token($auth_options, null);
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertEquals( $token_details->capability, $key->capability, 'Unexpected capability' );
    }


    /**
     * requestToken with invalid mac
     */
    public function testRequestTokenWithInvalidMac() {
        echo '==testRequestTokenWithInvalidMac()';
        $token_params = array( 'mac' => 'thisisnotavalidmac' );
        try {
            $this->ably->request_token( null, $token_params );
            $this->fail('Expected token request rejection');
        } catch (Exception $e) {
            $this->assertEquals( 40101, $e->getCode(), 'Unexpected error code' );
        }
    }

    /**
     * Token generation with specified ttl
     */
    public function testTokenGenerationWithSpecifiedTTL() {
        echo '==testTokenGenerationWithSpecifiedTTL()';
        $token_params = array( 'ttl' => 100 );
        $token_details = $this->ably->request_token(null, $token_params);
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertEquals( $token_details->issued_at + 100, $token_details->expires, 'Unexpected expires time' );
    }

    /**
     * Token generation with excessive ttl
     */
    public function testTokenGenerationWithExcessiveTTL() {
        echo '==testTokenGenerationWithExcessiveTTL()';
        $token_params = array( 'ttl' => 365*24*60*60 );
        try {
            $this->ably->request_token(null, $token_params);
            $this->fail('Expected token request rejection');
        } catch (Exception $e) {
            $this->assertEquals( 40003, $e->getCode(), 'Unexpected error code' );
        }
    }

    /**
     * Token generation with invalid ttl
     */
    public function testTokenGenerationWithInvalidTTL() {
        echo '==testTokenGenerationWithInvalidTTL()';
        $token_params = array( 'ttl' => -1 );
        try {
            $this->ably->request_token(null, $token_params);
            $this->fail('Expected token request rejection');
        } catch (Exception $e) {
            $this->assertEquals( 40003, $e->getCode(), 'Unexpected error code' );
        }
    }
}