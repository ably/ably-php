<?php

require_once dirname(__FILE__) . '/../lib/ably.php';
require_once dirname(__FILE__) . '/factories/TestOption.php';

class ChannelPublishTest extends PHPUnit_Framework_TestCase {
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
     * Actual test reused by exposed tests with various settings
     */
    private function executePublishTestOnChannel($channel) {
        # first publish some messages
        $utf = 'This is a UTF-8 string message payload. äôč ビール';
        $binary = hex2bin('00102030405060708090a0b0c0d0e0f0ff');
        $object = (object)array( 'test' => 'This is a JSONObject message payload' );
        $array = (object)array( 'This is a JSONarray message payload', 'Test' );

        $channel->publish('utf', $utf);
        $channel->publish('binary', $binary);
        $channel->publish('jsonobject', $object);
        $channel->publish('jsonarray', $array);

        # get the history for this channel
        $messages = $channel->history();
        $this->assertNotNull( $messages, 'Expected non-null messages' );
        $this->assertEquals( 4, count($messages), 'Expected 4 messages' );

        $actual_message_order = array();

        # verify message contents
        foreach ($messages as $message) {
            $actual_message_order[] = $message->name;

            switch ($message->name) {
                case 'utf'       : $this->assertEquals( $utf,    $message->data, 'Expected a utf-8 string' ); break;
                case 'binary'    : $this->assertEquals( $binary, $message->data, 'Expected binary data' ); break;
                case 'jsonobject': $this->assertEquals( $object, $message->data, 'Expected a stdClass object' ); break;
                case 'jsonarray' : $this->assertEquals( $array,  $message->data, 'Expected an array' ); break;
            }
        }

        # verify message order
        $this->assertEquals( array('jsonarray', 'jsonobject', 'binary', 'utf'), $actual_message_order, 'Expected messages in reverse order' );
    }

    /**
     * Publish events with data of various datatypes to an unencrypted channel
     */
    public function testPublishMessagesVariousTypesUnencrypted() {
        $unencrypted = $this->ably->channel( 'persisted:unencrypted' );

        $this->executePublishTestOnChannel( $unencrypted );
    }

    /**
     * Publish events with data of various datatypes to an aes-128-cbc encrypted channel
     */
    public function testPublishMessagesVariousTypesAES128() {
        $options = array( 'encrypted' => true, 'cipherParams' => new CipherParams( 'password', 'aes-128-cbc' ));
        $encrypted1 = $this->ably->channel( 'persisted:encrypted1' );

        $this->executePublishTestOnChannel( $encrypted1 );
    }

    /**
     * Publish events with data of various datatypes to an aes-256-cbc encrypted channel
     */
    public function testPublishMessagesVariousTypesAES256() {
        $options = array( 'encrypted' => true, 'cipherParams' => new CipherParams( 'password', 'aes-256-cbc' ));
        $encrypted2 = $this->ably->channel( 'persisted:encrypted2' );

        $this->executePublishTestOnChannel( $encrypted2 );
    }

}