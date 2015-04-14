<?php
namespace tests;
use Ably\AblyRest;
use Ably\Channel;
use Ably\Models\CipherParams;
use Ably\Models\Message;

require_once dirname(__FILE__) . '/factories/TestOption.php';

class ChannelMessagesTest extends \PHPUnit_Framework_TestCase {
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

        foreach ($data as $type => $payload) {
            $msg = new Message();
            $msg->name = $type;
            $msg->data = $payload;
            
            $channel->publish($msg);
            
            if ($channel->options['encrypted']) {
                # check if the messages are encrypted
                $msgJSON = json_decode( $msg->toJSON() );
                
                $this->assertTrue(
                    strpos( $msgJSON->encoding, $channel->options['cipherParams']->algorithm ) !== false,
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
        $this->assertEquals( 4, count($messages), 'Expected 4 messages' );

        $actual_message_order = array();

        # verify message contents
        foreach ($messages as $message) {
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
        $unencrypted = $this->ably->channel( 'persisted:unencrypted' );

        $this->executePublishTestOnChannel( $unencrypted );
    }

    /**
     * Publish events with data of various datatypes to an aes-128-cbc encrypted channel
     */
    public function testPublishMessagesVariousTypesAES128() {
        $options = array( 'encrypted' => true, 'cipherParams' => new CipherParams( 'password', 'aes-128-cbc' ));
        $encrypted1 = $this->ably->channel( 'persisted:encrypted1', $options );
        
        $this->assertTrue( $encrypted1->options['encrypted'], 'Expected channel to be encrypted' );

        $this->executePublishTestOnChannel( $encrypted1 );
    }

    /**
     * Publish events with data of various datatypes to an aes-256-cbc encrypted channel
     */
    public function testPublishMessagesVariousTypesAES256() {
        $options = array( 'encrypted' => true, 'cipherParams' => new CipherParams( 'password', 'aes-256-cbc' ));
        $encrypted2 = $this->ably->channel( 'persisted:encrypted2', $options );
        
        $this->assertTrue( $encrypted2->options['encrypted'], 'Expected channel to be encrypted' );

        $this->executePublishTestOnChannel( $encrypted2 );
    }

    /**
     * Encryption mismatch - publish message over encrypted channel, retrieve history over unencrypted channel
     *
     * @expectedException Ably\Exceptions\AblyEncryptionException
     */
    public function testEncryptedMessageUnencryptedHistory() {
        $options = array( 'encrypted' => true, 'cipherParams' => new CipherParams( 'password', 'aes-128-cbc' ));
        $encrypted = $this->ably->channel( 'persisted:mismatch1', $options );
        $unencrypted = $this->ably->channel( 'persisted:mismatch1' );

        $payload = 'This is a test message';
        $encrypted->publish( 'test', $payload );

        $messages = $unencrypted->history();
    }

    /**
     * Publish message over unencrypted channel, retrieve history over encrypted channel, there should be no decryption attempts
     */
    public function testUnencryptedMessageEncryptedHistory() {
        $options = array( 'encrypted' => true, 'cipherParams' => new CipherParams( 'password', 'aes-128-cbc' ));
        $encrypted = $this->ably->channel( 'persisted:mismatch2' );
        $unencrypted = $this->ably->channel( 'persisted:mismatch2', $options );

        $payload = 'This is a test message';
        $encrypted->publish( 'test', $payload );

        $messages = $unencrypted->history();
        $this->assertNotNull( $messages, 'Expected non-null messages' );
        $this->assertEquals( 1, count($messages), 'Expected 1 message' );
        $this->assertEquals( $messages[0]->originalData, $messages[0]->data, 'Expected to have message data untouched' );
    }

    /**
     * Encryption key mismatch - publish message with key1, retrieve history with key2
     *
     * @expectedException Ably\Exceptions\AblyEncryptionException
     */
    public function testEncryptionKeyMismatch() {
        $options = array( 'encrypted' => true, 'cipherParams' => new CipherParams( 'password', 'aes-128-cbc' ));
        $encrypted1 = $this->ably->channel( 'persisted:mismatch3', $options );

        $options2 = array( 'encrypted' => true, 'cipherParams' => new CipherParams( 'DIFFERENT PASSWORD', 'aes-128-cbc' ));
        $encrypted2 = $this->ably->channel( 'persisted:mismatch3', $options2 );

        $payload = 'This is a test message';
        $encrypted1->publish( 'test', $payload );

        $messages = $encrypted2->history();
    }

}