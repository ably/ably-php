<?php

require_once '../lib/ably.php';
require_once 'factories/TestOption.php';

class TokenTest extends PHPUnit_Framework_TestCase {

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

        $this->ably = new AblyRest( $defaults );
        $this->permit_all = json_decode(json_encode(array('*' => array('*'))));
    }

    /**
     * Base requestToken case with null params
     */
    public function testBaseRequestTokenWithNullParams() {
        echo '==testBaseRequestTokenWithNullParams()';
        $request_time = time();
        $token_details = $this->ably->request_token(null);
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertTrue( ($token_details->issued_at >= $request_time - 1) && ($token_details->issued_at <= $request_time + 1), 'Unexpected issued_at time' );
        $this->assertEquals( $token_details->issued_at + 60*60, $token_details->expires, 'Unexpected expires time' );
        $this->assertEquals( $this->permit_all, $token_details->capability, 'Unexpected capability' );
    }

    /**
     * Base requestToken case with non-null but empty params
     */
    public function testBaseRequestTokenWithNonNullButEmptyParams() {
        echo '==testBaseRequestTokenWithNonNullButEmptyParams()';
        $request_time = time();
        $token_details = $this->ably->request_token(array());
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertTrue( ($token_details->issued_at >= $request_time - 1) && ($token_details->issued_at <= $request_time + 1), 'Unexpected issued_at time' );
        $this->assertEquals( $token_details->issued_at + 60*60, $token_details->expires, 'Unexpected expires time' );
        $this->assertEquals( $this->permit_all, $token_details->capability, 'Unexpected capability' );
    }

    /**
     * requestToken with explicit timestamp
     */
    public function testRequestTokenWithExplicitTimestamp() {
        echo '==testRequestTokenWithExplicitTimestamp()';
        $request_time = time();
        $token_details = $this->ably->request_token(array('timestamp' => $request_time));
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertTrue( ($token_details->issued_at >= $request_time - 1) && ($token_details->issued_at <= $request_time + 1), 'Unexpected issued_at time' );
        $this->assertEquals( $token_details->issued_at + 60*60, $token_details->expires, 'Unexpected expires time' );
        $this->assertEquals( $this->permit_all, $token_details->capability, 'Unexpected capability' );
    }

    /**
     * requestToken with explicit, invalid timestamp
     */
    public function testRequestTokenWithExplicitInvalidTimestamp() {
        echo '==testRequestTokenWithExplicitInvalidTimestamp()';
        $request_time = time();
        try {
            $this->ably->request_token(array('timestamp' => $request_time - 30 * 60));
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
        $token_details = $this->ably->request_token(array('query' => true));
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        $this->assertTrue( ($token_details->issued_at >= $request_time - 1) && ($token_details->issued_at <= $request_time + 1), 'Unexpected issued_at time' );
        $this->assertEquals( $token_details->issued_at + 60*60, $token_details->expires, 'Unexpected expires time' );
        $this->assertEquals( $this->permit_all, $token_details->capability, 'Unexpected capability' );
    }

    /**
     * requestToken with duplicate nonce
     */
    public function testRequestTokenWithDuplicateNonce() {
        echo '==testRequestTokenWithDuplicateNonce()';
        $request_time = time();
        $token_params = array(
            'timestamp' => $request_time,
            'nonce' => "1234567890123456",
        );
        $token_details = $this->ably->request_token( $token_params );
        $this->assertNotNull( $token_details->id, 'Expected token id' );
        try {
            $this->ably->request_token( $token_params );
        } catch (Exception $e) {
            $this->assertEquals( 40101, $e->getCode(), 'Unexpected error code' );
        }
    }

    /**
     * Base requestToken case with non-null but empty params
     */

    /**
     * Token generation with capability that subsets key capability
     */

    /**
     * Token generation with specified key
     */

    /**
     * requestToken with invalid mac
     */

    /**
     * Token generation with specified ttl
     */

    /**
     * Token generation with excessive ttl
     */

    /**
     * Token generation with invalid ttl
     */
}