<?php

require_once dirname(__FILE__) . '/../lib/ably.php';
require_once 'factories/TestOption.php';

class AuthTest extends PHPUnit_Framework_TestCase {

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
            'debug' => false,
            'encrypted' => $options['encrypted'],
            'host' => $options['host'],
            'port' => $options['port'],
        );

        $this->defaults = $defaults;
    }

    /**
     * Init library with a key only
     */
    public function testAuthoriseWithKeyOnly() {
        echo '== testAuthoriseWithKeyOnly()';
        $ably = new AblyRest(array_merge($this->defaults, array(
            'key' => self::$options['first_private_api_key'],
        )));
        $this->assertEquals( AuthMethod::BASIC, $ably->auth_method(), 'Unexpected Auth method mismatch.' );
    }


    /**
     * Init library with a token only
     */
    public function testAuthoriseWithTokenOnly() {
        echo '== testAuthoriseWithTokenOnly()';
        $options = self::$options;
        $ably = new AblyRest(array_merge($this->defaults, array(
            'appId'     => $options['appId'],
            'authToken' => "this_is_not_really_a_token",
        )));
        $this->assertEquals( AuthMethod::TOKEN, $ably->auth_method(), 'Unexpected Auth method mismatch.' );
    }

    /**
     * Init library with a token callback
     */
    protected $authinit2_cbCalled = false;
    public function testAuthoriseWithTokenCallback() {
        echo '== testAuthoriseWithTokenCallback()';
        $options = self::$options;
        $ably = new AblyRest(array_merge($this->defaults, array(
            'appId'        => $options['appId'],
            'authCallback' => function( $params ) {
                $this->authinit2_cbCalled = true;
                return "this_is_not_really_a_token_request";
            }
        )));
        // make a call to trigger a token request
        $ably->stats();
        $this->assertTrue( $this->authinit2_cbCalled, 'Token callback not called' );
        $this->assertEquals( AuthMethod::TOKEN, $ably->auth_method(), 'Unexpected Auth method mismatch.' );
    }


    /**
     * Init library with a key and clientId; expect token auth to be chosen
     */
    public function testAuthoriseWithKeyAndClientId() {
        echo '== testAuthoriseWithKeyAndClientId()';
        $options = self::$options;
        $ably = new AblyRest(array_merge($this->defaults, array(
            'key'      => $options['first_private_api_key'],
            'clientId' => 'testClientId',
        )));
        $this->assertEquals( AuthMethod::TOKEN, $ably->auth_method(), 'Unexpected Auth method mismatch.' );
    }

    /**
     * Init library with a token
     */
    public function testAuthoriseWithToken() {
        echo '== testAuthoriseWithToken()';
        $options = self::$options;

        $ably_for_token = new AblyRest(array_merge($this->defaults, array(
            'key' => $options['first_private_api_key'],
        )));
        $token_details = $ably_for_token->request_token();
        $this->assertNotNull($token_details->id, 'Expected token id' );

        $ably = new AblyRest(array_merge($this->defaults, array(
            'appId'     => $options['appId'],
            'authToken' => $token_details->id,
        )));
        $this->assertEquals( AuthMethod::TOKEN, $ably->auth_method(), 'Unexpected Auth method mismatch.' );
    }
}