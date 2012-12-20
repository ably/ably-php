<?php

require_once '../config.php';
require_once '../lib/ably.php';

class AuthTest extends PHPUnit_Framework_TestCase {

    protected $ably;

    protected function setUp() {
        $this->ably = Ably::get_instance(array(
            'host' => ABLY_HOST,
            'key'  => ABLY_KEY
        ));
    }

    public function testAuthoriseWithCachedToken() {
        # first authorise
        $this->ably->authorise();
        # get token after authorise
        $id1 = $this->ably->token->id;
        # re-authorise
        $this->ably->authorise();
        $id2 = $this->ably->token->id;
        $this->assertEquals($id1, $id2);
    }

    public function testAuthoriseWithForceOption() {
        # first authorise
        $this->ably->authorise();
        # get token after authorise
        $id1 = $this->ably->token->id;
        # re-authorise
        $this->ably->authorise(array('force' => true));
        $id2 = $this->ably->token->id;
        $this->assertNotEquals($id1, $id2);
    }

}