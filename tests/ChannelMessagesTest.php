<?php
namespace tests;
use Ably\AblyRest;
use Ably\Channel;
use Ably\Http;
use Ably\Log;
use Ably\Exceptions\AblyException;
use Ably\Models\Message;
use Ably\Utils\Crypto;

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
       
        // first publish some messages
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
                // check if the messages are encrypted
                $msgJSON = json_decode( $msg->toJSON() );
                
                $this->assertTrue(
                    strpos( $msgJSON->encoding, $channel->getCipherParams()->getAlgorithmString() ) !== false,
                    'Expected message encoding to contain a cipher algorithm'
                );
                $this->assertFalse( $msgJSON->data === $payload, 'Expected encrypted message payload not to match original data' );
            } else {
                // check if the messages are unencrypted
                $msgJSON = json_decode( $msg->toJSON() );
                
                $this->assertTrue(
                    strpos( $msgJSON->encoding, 'cipher' ) === false,
                    'Expected message encoding not to contain a cipher algorithm'
                );
            }
        }

        // get the history for this channel
        $messages = $channel->history();
        $this->assertNotNull( $messages, 'Expected non-null messages' );
        $this->assertEquals( 4, count($messages->items), 'Expected 4 messages' );

        $actual_message_order = array();

        // verify message contents
        foreach ($messages->items as $message) {
            $actual_message_order[] = $message->name;
            
            // payload must exactly match the one that was sent and must be decrypted automatically
            $originalPayload = $data[$message->name];
            $this->assertEquals( $originalPayload, $message->data, 'Expected retrieved message\'s data to match the original data (' . $message->name . ')' );
        }

        // verify message order
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
        $options = array( 'cipher' => array(
            'key' => Crypto::generateRandomKey( 128 ),
        ) );
        $encrypted1 = self::$ably->channels->get( 'persisted:encrypted1', $options );
        
        $this->assertNotNull( $encrypted1->getCipherParams(), 'Expected channel to be encrypted' );

        $this->executePublishTestOnChannel( $encrypted1 );
    }

    /**
     * Publish events with data of various datatypes to an aes-256-cbc encrypted channel
     */
    public function testPublishMessagesVariousTypesAES256() {
        $options = array( 'cipher' => array(
            'key' => Crypto::generateRandomKey( 256 ),
        ) );
        $encrypted2 = self::$ably->channels->get( 'persisted:encrypted2', $options );
        
        $this->assertNotNull( $encrypted2->getCipherParams(), 'Expected channel to be encrypted' );

        $this->executePublishTestOnChannel( $encrypted2 );
    }

    /**
     * Publish a single Message
     */
    public function testPublishSingleMessageUnencrypted() {
        $channel = self::$ably->channel( 'persisted:unencryptedSingle' );
        $data = (object)array( 'test' => 'This is a JSONObject message payload' );

        $msg = new Message();
        $msg->name = 'single';
        $msg->data = $data;

        $channel->publish( $msg );

        // get the history for this channel
        $messages = $channel->history();
        $this->assertNotNull( $messages, 'Expected non-null messages' );
        $this->assertEquals( 1, count($messages->items), 'Expected 1 message' );
        $this->assertEquals( $data, $messages->items[0]->data, 'Expected message contents to match' );
    }

    /**
     * Publish a single message by passing data to Channel::publish() directly
     */
    public function testPublishSingleMessageUnencryptedParams() {
        $channel = self::$ably->channel( 'persisted:unencryptedSingleParams' );

        $channel->publish( 'testEvent', 'testPayload', 'testClientId' );

        $messages = $channel->history();
        $this->assertNotNull( $messages, 'Expected non-null messages' );
        $this->assertEquals( 1, count($messages->items), 'Expected 1 message' );
        $this->assertEquals( 'testEvent',    $messages->items[0]->name,     'Expected message event name to match' );
        $this->assertEquals( 'testPayload',  $messages->items[0]->data,     'Expected message payload to match' );
        $this->assertEquals( 'testClientId', $messages->items[0]->clientId, 'Expected message clientId to match' );
    }

    /**
     * Verify that batch sending messages actually makes just one request
     */
    public function testMessageArraySingleRequest() {
        $messages = array();
        
        for ( $i = 0; $i < 10; $i++ ) {
            $msg = new Message();
            $msg->name = 'msg'.$i;
            $msg->data = 'test string'.$i;
            $messages[] = $msg;
        }

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
            'httpClass' => 'tests\HttpMockMsgCounter',
        ) ) );

        $channel = $ably->channel( 'singleReq' );
        $channel->publish( $messages );

        $this->assertEquals( 1, $ably->http->requestCount, 'Expected 1 request to be made' );
    }

    /**
     * Verify that publishing invalid types fails
     */
    public function testInvalidTypes() {
        $channel = self::$ably->channel( 'invalidTypes' );
        
        $msg = new Message();
        $msg->name = 'int';
        $msg->data = 81403;

        try {
            $channel->publish( $msg );
            $this->fail( 'Expected an exception' );
        } catch (AblyException $e) {
            if ( $e->getCode() != 40003 ) $this->fail('Expected exception error code 40003');
        }

        $msg = new Message();
        $msg->name = 'bool';
        $msg->data = true;

        try {
            $channel->publish( $msg );
            $this->fail( 'Expected an exception' );
        } catch (AblyException $e) {
            if ( $e->getCode() != 40003 ) $this->fail('Expected exception error code 40003');
        }

        $msg = new Message();
        $msg->name = 'float';
        $msg->data = 42.23;

        try {
            $channel->publish( $msg );
            $this->fail( 'Expected an exception' );
        } catch (AblyException $e) {
            if ( $e->getCode() != 40003 ) $this->fail('Expected exception error code 40003');
        }

        $msg = new Message();
        $msg->name = 'function';
        $msg->data = function($param) {
            return "mock function";
        };

        try {
            $channel->publish( $msg );
            $this->fail( 'Expected an exception' );
        } catch (AblyException $e) {
            if ( $e->getCode() != 40003 ) $this->fail('Expected exception error code 40003');
        }
    }

    /**
     * Verify that publishing too large message (>128KB) fails
     */
    public function testTooLargeMessage() {
        $channel = self::$ably->channel( 'huge' );
        
        $msg = new Message();
        $msg->name = 'huge';
        $msg->data = str_repeat("~", 128 * 1024); // 128 kilobytes + message JSON

        $this->setExpectedException( 'Ably\Exceptions\AblyException', '', 40009 );
        $channel->publish( $msg );
    }

    /**
     * Encryption mismatch - publish message over encrypted channel, retrieve history over unencrypted channel
     */
    public function testEncryptedMessageUnencryptedHistory() {
        $errorLogged = false;

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
            'logHandler' => function( $level, $args ) use ( &$errorLogged ) {
                if ( $level == Log::ERROR ) {
                    $errorLogged = true;
                }
            },
        ) ) );

        $payload = 'This is a test message';

        $options = array( 'cipher' => array( 'key' => Crypto::generateRandomKey( 128 ) ) );
        $encrypted1 = $ably->channel( 'persisted:mismatch1', $options );
        $encrypted1->publish( 'test', $payload );

        $options2 = array();
        $encrypted2 = $ably->channel( 'persisted:mismatch1', $options2 );
        $messages = $encrypted2->history();
        $msg = $messages->items[0];

        $this->assertTrue( $errorLogged, 'Expected an error to be logged' );
        $this->assertEquals( 'utf-8/cipher+aes-128-cbc/base64', $msg->originalEncoding, 'Expected the original message to be encrypted + base64 encoded' );
        $this->assertEquals( 'utf-8/cipher+aes-128-cbc', $msg->encoding, 'Expected to receive the message still encrypted, but base64 decoded' );
    }

    /**
     * Publish message over unencrypted channel, retrieve history over encrypted channel, there should be no decryption attempts
     */
    public function testUnencryptedMessageEncryptedHistory() {
        $payload = 'This is a test message';

        $encrypted = self::$ably->channel( 'persisted:mismatch2' );
        $encrypted->publish( 'test', $payload );

        $options = array( 'cipher' => array( 'key' => Crypto::generateRandomKey( 128 ) ) );
        $unencrypted = self::$ably->channel( 'persisted:mismatch2', $options );
        $messages = $unencrypted->history();
        $this->assertNotNull( $messages, 'Expected non-null messages' );
        $this->assertEquals( 1, count($messages->items), 'Expected 1 message' );
        $this->assertEquals( $messages->items[0]->originalData, $messages->items[0]->data, 'Expected to have message data untouched' );
    }

    /**
     * Encryption key mismatch - publish message with key1, retrieve history with key2
     */
    public function testEncryptionKeyMismatch() {
        $errorLogged = false;

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
            'logHandler' => function( $level, $args ) use ( &$errorLogged ) {
                if ( $level == Log::ERROR ) {
                    $errorLogged = true;
                }
            },
        ) ) );

        $payload = 'This is a test message';

        $options = array( 'cipher' => array( 'key' => 'fake key 1xxxxxx' ) );
        $encrypted1 = $ably->channel( 'persisted:mismatch3', $options );
        $encrypted1->publish( 'test', $payload );

        $options2 = array( 'cipher' => array( 'key' => 'fake key 2xxxxxx' ) );
        $encrypted2 = $ably->channel( 'persisted:mismatch3', $options2 );
        $messages = $encrypted2->history();
        $msg = $messages->items[0];

        $this->assertTrue( $errorLogged, 'Expected an error to be logged' );
        $this->assertEquals( 'utf-8/cipher+aes-128-cbc/base64', $msg->originalEncoding, 'Expected the original message to be encrypted + base64 encoded' );
        $this->assertEquals( 'utf-8/cipher+aes-128-cbc', $msg->encoding, 'Expected to receive the message still encrypted, but base64 decoded' );
    }

    /**
     * Verify if channel caching and releasing works
     */
    public function testChannelCaching() {
        $channel1 = self::$ably->channel( 'cache_test' );
        $channel2 = self::$ably->channels->get( 'cache_test' );

        $this->assertTrue( $channel1 === $channel2, 'Expected to get the same instance of the channel' );

        self::$ably->channels->release( 'cache_test' );

        $channel3 = self::$ably->channels->get( 'cache_test' );
        $this->assertTrue( $channel1 !== $channel3, 'Expected to get a new instance of the channel' );

        $this->assertNull( $channel3->getCipherParams(), 'Expected the channel to not have CipherParams' );

        self::$ably->channel( 'cache_test', array( 'cipher' => array( 'key' => Crypto::generateRandomKey( 128 ) ) ) );

        $this->assertNotNull( $channel3->getCipherParams(), 'Expected the channel to have CipherParams even when specified for a new instance' );
    }

    private function getMessageEncoding( $msg ) {
        $msgEnc = json_decode( $msg->toJSON() );
        return $msgEnc->encoding;
    }

    /**
     * Check if message encodings are correct, including the default encryption
     */
    public function testMessageEncodings() {
        $msg = new Message();

        $msg->data = 'This is a UTF-8 string message payload. äôč ビール';
        $this->assertEquals( '', $this->getMessageEncoding( $msg ), 'Expected empty message encoding' );

        $msg->data = (object)array( 'test' => 'This is a JSONObject message payload' );
        $this->assertEquals( 'json', $this->getMessageEncoding( $msg ), 'Expected empty message encoding' );

        $msg->data = hex2bin( '00102030405060708090a0b0c0d0e0f0ff' );
        $this->assertEquals( 'base64', $this->getMessageEncoding( $msg ), 'Expected empty message encoding' );

        $msg->setCipherParams( Crypto::getDefaultParams( array( 'key' => Crypto::generateRandomKey( 128 ) ) ) );

        $msg->data = 'This is a UTF-8 string message payload. äôč ビール';
        $this->assertEquals( 'utf-8/cipher+aes-128-cbc/base64', $this->getMessageEncoding( $msg ), 'Expected empty message encoding' );

        $msg->data = (object)array( 'test' => 'This is a JSONObject message payload' );
        $this->assertEquals( 'json/utf-8/cipher+aes-128-cbc/base64', $this->getMessageEncoding( $msg ), 'Expected empty message encoding' );

        $msg->data = hex2bin( '00102030405060708090a0b0c0d0e0f0ff' );
        $this->assertEquals( 'cipher+aes-128-cbc/base64', $this->getMessageEncoding( $msg ), 'Expected empty message encoding' );
    }

    /**
     * Test if null name and data elements are allowed when publishing messages
     */
    public function testNullData() {
        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
            'httpClass' => 'tests\HttpSaveWrapper',
        ) ) );

        $channel = $ably->channels->get( 'testChannel' );

        $msg = new Message();
        $msg->name = 'onlyName';
        $msg->data = null;

        $channel->publish( $msg );

        $publishedMsg = json_decode( $ably->http->lastParams );

        $this->assertEquals( $msg->name, $publishedMsg->name );
        $this->assertFalse( isset( $publishedMsg->data ) );

        $msg = new Message();
        $msg->name = null;
        $msg->data = 'onlyData';

        $channel->publish( $msg );

        $publishedMsg = json_decode( $ably->http->lastParams );

        $this->assertEquals( $msg->data, $publishedMsg->data );
        $this->assertFalse( isset( $publishedMsg->name ) );
    }
}


class HttpMockMsgCounter extends Http {
    public $requestCount = 0;
    
    public function request( $method, $url, $headers = array(), $params = array() ) {

        $this->requestCount++;

        return array(
            'headers' => 'HTTP/1.1 200 OK'."\n",
            'body' => array(),
        );
    }
}


class HttpSaveWrapper extends Http {
    public $lastResponse;
    public $lastHeaders;
    public $lastParams;
    
    public function request( $method, $url, $headers = array(), $params = array() ) {
        $this->lastHeaders = $headers;
        $this->lastParams = $params;
        $this->lastResponse = parent::request( $method, $url, $headers, $params );
        return $this->lastResponse;
    }
}
