<?php

require_once '../config.php';
require_once '../lib/ably.php';

class AuthTest extends PHPUnit_Framework_TestCase {

    protected $app;

    protected function setUp() {
        $this->app = Ably::get_instance(array(
            'host' => ABLY_HOST,
            'key'  => ABLY_KEY,
            'debug' => true
        ));
    }

    public function testAuthoriseWithSignedToken() {
        # first authorise
        $this->app->authorise();
        # get token after authorise
        $id1 = $this->app->token->id;
        # re-authorise
        $this->app->authorise();
        $id2 = $this->app->token->id;
        var_dump('testAuthoriseWithSignedToken');
        var_dump($this->app->response());
        $this->assertEquals($id1, $id2);
    }

    public function testAuthoriseWithUnsignedToken() {
        $this->app->token = null;
        $this->app->authorise();
        var_dump('testAuthoriseWithUnSignedToken');
        var_dump($this->app->response());
        $this->assertNotNull($this->app->token);
    }

    public function testAuthoriseWithForceOption() {
        # first authorise
        $this->app->authorise();
        # get token after authorise
        $id1 = $this->app->token->id;
        # re-authorise
        $this->app->authorise(array('force' => true));
        $id2 = $this->app->token->id;
        var_dump('testAuthoriseWithForceOption');
        var_dump($this->app->response());
        $this->assertNotEquals($id1, $id2);
    }

}