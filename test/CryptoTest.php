<?php

require_once dirname(__FILE__) . '/../lib/ably.php';
require_once dirname(__FILE__) . '/factories/TestOption.php';

class CryptoTest extends PHPUnit_Framework_TestCase {

    /**
     * Tests if example messages match actual messages after encryption/decryption and vice versa:
     * decrypt(encrypted_example) == unencrypted_example
     * encrypt(unencrypted_example) == ecrypted_example
     *
     * @dataProvider filenameProvider
     */
    public function testEncryptionDecryptionAgainstFixture( $filename ) {
        $fixture = json_decode( file_get_contents( dirname(__FILE__) . '/fixtures/' . $filename ) );

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

    public function filenameProvider() {
        return array(
            array('crypto-data-128.json'),
            array('crypto-data-256.json'),
        );
    }
}