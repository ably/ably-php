<?php
namespace tests;
use Ably\AblyRest;
use Ably\Channel;
use Ably\Http;
use Ably\Models\CipherParams;
use Ably\Models\Message;

require_once __DIR__ . '/factories/TestApp.php';

class ChannelMessagesTest extends \PHPUnit_Framework_TestCase {
    protected static $testApp;
    protected static $defaultOptions;
    protected static $ably;

    public static function setUpBeforeClass() {
        self::$testApp = new TestApp();
        self::$defaultOptions = self::$testApp->getOptions();
        self::$ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
        ) ) );
    }

    public static function tearDownAfterClass() {
        self::$testApp->release();
    }

    /**
     * Partial test reused by actual exposed tests.
     * Publishes messages to a provided channel, checks for encryption, retrieves history,
     * compares decoded payloads with original messages
     */
    private function executePublishTestOnChannel(Channel $channel) {
       
        # first publish some messages
        $data = array(
            'utf' => 'This is a UTF-8 string message payload. äôč ビール',
            'binary' => hex2bin('00102030405060708090a0b0c0d0e0f0ff'),
            'object' => (object)array( 'test' => 'This is a JSONObject message payload' ),
            'array' => array( 'This is a JSONarray message payload', 'Test' ),
        );

        $messages = array();

        foreach ($data as $type => $payload) {
            $msg = new Message();
            $msg->name = $type;
            $msg->data = $payload;
            
            $messages[] = $msg;
        }

        $channel->publish( $messages ); // publish all messages at once

        foreach ($messages as $msg) {
            if ( $channel->getCipherParams() ) {
                # check if the messages are encrypted
                $msgJSON = json_decode( $msg->toJSON() );
                
                $this->assertTrue(
                    strpos( $msgJSON->encoding, $channel->getCipherParams()->algorithm ) !== false,
                    'Expected message encoding to contain a cipher algorithm'
                );
                $this->assertFalse( $msgJSON->data === $payload, 'Expected encrypted message payload not to match original data' );
            } else {
                # check if the messages are unencrypted
                $msgJSON = json_decode( $msg->toJSON() );
                
                $this->assertTrue(
                    strpos( $msgJSON->encoding, 'cipher' ) === false,
                    'Expected message encoding not to contain a cipher algorithm'
                );
            }
        }

        # get the history for this channel
        $messages = $channel->history();
        $this->assertNotNull( $messages, 'Expected non-null messages' );
        $this->assertEquals( 4, count($messages->items), 'Expected 4 messages' );

        $actual_message_order = array();

        # verify message contents
        foreach ($messages->items as $message) {
            $actual_message_order[] = $message->name;
            
            # payload must exactly match the one that was sent and must be decrypted automatically
            $originalPayload = $data[$message->name];
            $this->assertEquals( $originalPayload, $message->data, 'Expected retrieved message\'s data to match the original data (' . $message->name . ')' );
        }

        # verify message order
        $this->assertEquals( array_reverse( array_keys( $data ) ), $actual_message_order, 'Expected messages in reverse order' );
    }

    /**
     * Publish events with data of various datatypes to an unencrypted channel
     */
    public function testPublishMessagesVariousTypesUnencrypted() {
        $unencrypted = self::$ably->channels->get( 'persisted:unencrypted' );

        $this->executePublishTestOnChannel( $unencrypted );
    }

    /**
     * Publish events with data of various datatypes to an aes-128-cbc encrypted channel
     */
    public function testPublishMessagesVariousTypesAES128() {
        $options = array( 'encrypted' => true, 'cipherParams' => new CipherParams( 'password', 'aes-128-cbc' ));
        $encrypted1 = self::$ably->channels->get( 'persisted:encrypted1', $options );
        
        $this->assertNotNull( $encrypted1->getCipherParams(), 'Expected channel to be encrypted' );

        $this->executePublishTestOnChannel( $encrypted1 );
    }

    /**
     * Publish events with data of various datatypes to an aes-256-cbc encrypted channel
     */
    public function testPublishMessagesVariousTypesAES256() {
        $options = array( 'encrypted' => true, 'cipherParams' => new CipherParams( 'password', 'aes-256-cbc' ));
        $encrypted2 = self::$ably->channels->get( 'persisted:encrypted2', $options );
        
        $this->assertNotNull( $encrypted2->getCipherParams(), 'Expected channel to be encrypted' );

        $this->executePublishTestOnChannel( $encrypted2 );
    }

    /**
     * Publish single Message
     */
    public function testPublishSingleMessageUnencrypted() {
        $channel = self::$ably->channel( 'persisted:unencryptedSingle' );
        $data = (object)array( 'test' => 'This is a JSONObject message payload' );

        $msg = new Message();
        $msg->name = 'single';
        $msg->data = $data;

        $channel->publish( $msg );

        # get the history for this channel
        $messages = $channel->history();
        $this->assertNotNull( $messages, 'Expected non-null messages' );
        $this->assertEquals( 1, count($messages->items), 'Expected 1 message' );
        $this->assertEquals( $data, $messages->items[0]->data, 'Expected message contents to match' );
    }

    /**
     * Verify that publishing invalid types fails
     */
    public function testInvalidTypes() {
        $channel = self::$ably->channel( 'persisted:unencryptedSingle' );
        
        $msg = new Message();
        $msg->name = 'int';
        $msg->data = 81403;

        $this->setExpectedException( 'Ably\Exceptions\AblyException', '', 40003 );
        $channel->publish( $msg );

        $msg = new Message();
        $msg->name = 'bool';
        $msg->data = true;

        $this->setExpectedException( 'Ably\Exceptions\AblyException', '', 40003 );
        $channel->publish( $msg );

        $msg = new Message();
        $msg->name = 'float';
        $msg->data = 42.23;

        $this->setExpectedException( 'Ably\Exceptions\AblyException', '', 40003 );
        $channel->publish( $msg );

        $msg = new Message();
        $msg->name = 'function';
        $msg->data = function($param) {
            return "mock function";
        };

        $this->setExpectedException( 'Ably\Exceptions\AblyException', '', 40003 );
        $channel->publish( $msg );
    }

    /**
     * Encryption mismatch - publish message over encrypted channel, retrieve history over unencrypted channel
     *
     * @expectedException Ably\Exceptions\AblyEncryptionException
     */
    public function testEncryptedMessageUnencryptedHistory() {
        $payload = 'This is a test message';

        $options = array( 'encrypted' => true, 'cipherParams' => new CipherParams( 'password', 'aes-128-cbc' ));
        $encrypted = self::$ably->channel( 'persisted:mismatch1', $options );
        $encrypted->publish( 'test', $payload );

        $unencrypted = self::$ably->channel( 'persisted:mismatch1', array() );
        $messages = $unencrypted->history();
    }

    /**
     * Publish message over unencrypted channel, retrieve history over encrypted channel, there should be no decryption attempts
     */
    public function testUnencryptedMessageEncryptedHistory() {
        $payload = 'This is a test message';

        $options = array( 'encrypted' => true, 'cipherParams' => new CipherParams( 'password', 'aes-128-cbc' ));
        $encrypted = self::$ably->channel( 'persisted:mismatch2' );
        $encrypted->publish( 'test', $payload );

        $unencrypted = self::$ably->channel( 'persisted:mismatch2', $options );
        $messages = $unencrypted->history();
        $this->assertNotNull( $messages, 'Expected non-null messages' );
        $this->assertEquals( 1, count($messages->items), 'Expected 1 message' );
        $this->assertEquals( $messages->items[0]->originalData, $messages->items[0]->data, 'Expected to have message data untouched' );
    }

    /**
     * Encryption key mismatch - publish message with key1, retrieve history with key2
     *
     * @expectedException Ably\Exceptions\AblyEncryptionException
     */
    public function testEncryptionKeyMismatch() {
        $payload = 'This is a test message';

        $options = array( 'encrypted' => true, 'cipherParams' => new CipherParams( 'password', 'aes-128-cbc' ));
        $encrypted1 = self::$ably->channel( 'persisted:mismatch3', $options );
        $encrypted1->publish( 'test', $payload );

        $options2 = array( 'encrypted' => true, 'cipherParams' => new CipherParams( 'DIFFERENT PASSWORD', 'aes-128-cbc' ));
        $encrypted2 = self::$ably->channel( 'persisted:mismatch3', $options2 );
        $messages = $encrypted2->history();
    }
}


class HttpMockMsgCounter extends Http {
    public $requests = 0;
    
    public function request($method, $url, $headers = array(), $params = array()) {

        $this->requests++;

        return array(
            'headers' => 'HTTP/1.1 200 OK'."\n",
            'body' => array(),
        );
    }
}
