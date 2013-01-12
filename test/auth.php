<?php

require_once '../lib/ably.php';
require_once 'factories/TestOption.php';

class AuthTest extends PHPUnit_Framework_TestCase {

    protected static $options;

    public static function setUpBeforeClass() {

        self::$options = TestOption::get_instance()->get_opts();

    }

    public static function tearDownAfterClass() {
        TestOption::get_instance()->clear_opts();
    }


    /**
     * Init library with a key only
     */
    public function testAuthoriseWithKeyOnly() {
        echo '== testAuthoriseWithKeyOnly()';
        $ably = new Ably(self::$options['first_private_api_key']);
        $this->assertEquals( AuthMethod::BASIC, $ably->auth_method(), 'Unexpected Auth method mismatch.' );
    }


    /**
     * Init library with a token only
     */
    public function testAuthoriseWithTokenOnly() {
        echo '== testAuthoriseWithTokenOnly()';
    }

    /**
     * Init library with a token callback
     */
    public function testAuthoriseWithTokenCallback() {
        echo '== testAuthoriseWithTokenCallback()';
    }


    /**
     * Init library with a key and clientId; expect token auth to be chosen
     */
    public function testAuthoriseWithKeyAndClientId() {
        echo '== testAuthoriseWithKeyAndClientId()';
    }

    /**
     * Init library with a token
     */
    public function testAuthoriseWithToken() {
        echo '== testAuthoriseWithKeyOnly()';
    }


    /*
     * OLD TESTS - DEPRECATED
     */

//    public function testAuthoriseWithSignedToken() {
//        echo '== testAuthoriseWithSignedToken()';
//        # first authorise
//        $this->app->authorise();
//        # get token after authorise
//        $id1 = $this->app->token->id;
//        # re-authorise
//        $this->app->authorise();
//        $id2 = $this->app->token->id;
//        $this->assertEquals($id1, $id2);
//    }
//
//    public function testAuthoriseWithUnsignedToken() {
//        echo '== testAuthoriseWithUnsignedToken()';
//        $this->app->token = null;
//        $this->app->authorise();
//        $this->assertNotNull($this->app->token);
//    }
//
//    public function testAuthoriseWithForceOption() {
//        echo '== testAuthoriseWithForceOption()';
//        # first authorise
//        $this->app->authorise();
//        # get token after authorise
//        $id1 = $this->app->token->id;
//        # re-authorise
//        $this->app->authorise(array('force' => true));
//        $id2 = $this->app->token->id;
//        $this->assertNotEquals($id1, $id2);
//    }

}