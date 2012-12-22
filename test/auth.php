<?php

require_once '../config.php';
require_once '../lib/ably.php';

class AuthTest extends PHPUnit_Framework_TestCase {

    protected $app;

    protected function setUp() {
        $this->app = Ably::rest(array(
            'host' => ABLY_HOST,
            'key'  => ABLY_KEY,
            'debug' => true
        ));
    }

    public function testAuthoriseWithSignedToken() {
        echo '== testAuthoriseWithSignedToken()';
        # first authorise
        $this->app->authorise();
        # get token after authorise
        $id1 = $this->app->token->id;
        # re-authorise
        $this->app->authorise();
        $id2 = $this->app->token->id;
        $this->assertEquals($id1, $id2);
    }

    public function testAuthoriseWithUnsignedToken() {
        echo '== testAuthoriseWithUnsignedToken()';
        $this->app->token = null;
        $this->app->authorise();
        $this->assertNotNull($this->app->token);
    }

    public function testAuthoriseWithForceOption() {
        echo '== testAuthoriseWithForceOption()';
        # first authorise
        $this->app->authorise();
        # get token after authorise
        $id1 = $this->app->token->id;
        # re-authorise
        $this->app->authorise(array('force' => true));
        $id2 = $this->app->token->id;
        $this->assertNotEquals($id1, $id2);
    }

}