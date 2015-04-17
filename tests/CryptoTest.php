<?php
namespace tests;
use Ably\Models\CipherParams;
use Ably\Models\Message;
use Ably\Models\PresenceMessage;

require_once __DIR__ . '/factories/TestApp.php';

class CryptoTest extends \PHPUnit_Framework_TestCase {

    /**
     * Tests if example messages match actual messages after encryption/decryption and vice versa:
     * decrypt(encrypted_example) == unencrypted_example
     * encrypt(unencrypted_example) == encrypted_example
     *
     * @dataProvider filenameProvider
     */
    public function testMessageEncryptionAgainstFixture( $filename ) {
        $fixture = json_decode( file_get_contents( $filename ) );

        foreach ($fixture->items as $example) {
            $key = base64_decode( $fixture->key );
            $algorithm = $fixture->algorithm . '-' . $fixture->keylength . '-' . $fixture->mode;
            $iv = base64_decode( $fixture->iv );
            $cipherParams = new CipherParams( $key, $algorithm, $iv );

            $decodedExample = new Message();
            $decodedExample->fromJSON( $example->encoded );
            $decryptedExample = new Message();
            $decryptedExample->setCipherParams( $cipherParams );
            $decryptedExample->fromJSON( $example->encrypted );

            $this->assertEquals( $decodedExample->data, $decryptedExample->data, 'Expected unencrypted and decrypted message\'s contents to match' );

            $decodedExample->setCipherParams( $cipherParams );
            $encryptedJSON = json_decode( $decodedExample->toJSON() );
            $this->assertEquals( $example->encrypted->data, $encryptedJSON->data, 'Expected encrypted and example encrypted message\'s contents to match' );
        }
    }

    /**
     * Tests if example presence messages match actual presence messages after encryption/decryption and vice versa:
     * decrypt(encrypted_example) == unencrypted_example
     * encrypt(unencrypted_example) == encrypted_example
     *
     * @dataProvider filenameProvider
     */
    public function testPrenenceMessageEncryptionAgainstFixture( $filename ) {
        $fixture = json_decode( file_get_contents( $filename ) );

        foreach ($fixture->items as $example) {
            unset ($example->encoded->name); // we're reusing fixtures for standard messages, but presence messages do not have a name
            unset ($example->encrypted->name);

            $key = base64_decode( $fixture->key );
            $algorithm = $fixture->algorithm . '-' . $fixture->keylength . '-' . $fixture->mode;
            $iv = base64_decode( $fixture->iv );
            $cipherParams = new CipherParams( $key, $algorithm, $iv );

            $decodedExample = new PresenceMessage();
            $decodedExample->fromJSON( $example->encoded );
            $decryptedExample = new PresenceMessage();
            $decryptedExample->setCipherParams( $cipherParams );
            $decryptedExample->fromJSON( $example->encrypted );

            $this->assertEquals( $decodedExample->data, $decryptedExample->data, 'Expected unencrypted and decrypted message\'s contents to match' );

            $decodedExample->setCipherParams( $cipherParams );
            $encryptedJSON = json_decode( $decodedExample->toJSON() );
            $this->assertEquals( $example->encrypted->data, $encryptedJSON->data, 'Expected encrypted and example encrypted message\'s contents to match' );
        }
    }

    public function filenameProvider() {
        return array(
            array( __DIR__ . '/../ably-common/test-resources/crypto-data-128.json'),
            array( __DIR__ . '/../ably-common/test-resources/crypto-data-256.json'),
        );
    }
}