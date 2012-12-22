<?php

require_once '../config.php';
require_once '../lib/ably.php';

class BrowserTest extends PHPUnit_Extensions_Selenium2TestCase {
    protected $app;

    protected function setUp() {
        $this->setBrowser('firefox');
        $this->setBrowserUrl('http://localhost:8888/');
    }

    public function testTitle() {
        echo '== testChannelPubSub()';
        $this->url('http://localhost:8888/demo/chat.php?channel=chat&event=guest');
        $this->assertEquals('Simple Chat', $this->title());
    }

}