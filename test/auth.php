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

    public function testTime() {
        $this->assertGreaterThanOrEqual(time(), $this->ably->time());
    }

    public function testAuthoriseWithCachedToken() {
        sleep(2);
        # first authorise
        $this->ably->authorise();
        # get token after authorise
        $id1 = $this->ably->token->id;
        sleep(2);
        # re-authorise
        $this->ably->authorise();
        $id2 = $this->ably->token->id;
        $this->assertEquals($id1, $id2);
    }

    public function testAuthoriseWithForceOption() {
        sleep(2);
        # first authorise
        $this->ably->authorise();
        # get token after authorise
        $id1 = $this->ably->token->id;
        var_dump($id1);
        sleep(2);
        # re-authorise
        $this->ably->authorise(array('force' => true));
        $id2 = $this->ably->token->id;
        var_dump($id2);
        $this->assertNotEquals($id1, $id2);
    }

}