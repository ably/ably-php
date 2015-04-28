<?php
namespace Ably\Utils;

use Ably\Models\CipherParams;

/**
* Provides static methods for encryption/decryption
*/
class Crypto {
    /**
     * Encrypts data and returns binary payload. Automatically updates $cipherParams with a new IV.
     * @return string|false Binary payload coposed of concatenated IV and encrypted data, false if unsuccessful.
     */
    public static function encrypt( $plaintext, $cipherParams ) {
        $raw = defined( 'OPENSSL_RAW_DATA' ) ? OPENSSL_RAW_DATA : true;

        $ciphertext = openssl_encrypt( $plaintext, $cipherParams->algorithm, $cipherParams->key, $raw, $cipherParams->iv );

        if ($ciphertext === false) {
            return false;
        }

        $iv = $cipherParams->iv;

        self::updateIV( $cipherParams );

        return $iv.$ciphertext;
    }

    /**
     * Decrypts payload and returns original data.
     * @return string|false Original data as string or string containing binary data, false if unsuccessful.
     */
    public static function decrypt( $payload, $cipherParams ) {
        $raw = defined( 'OPENSSL_RAW_DATA' ) ? OPENSSL_RAW_DATA : true;

        $iv = substr( $payload, 0, 16 );
        $ciphertext = substr( $payload, 16 );
        return openssl_decrypt( $ciphertext, $cipherParams->algorithm, $cipherParams->key, $raw, $iv );
    }

    /**
     * Returns default encryption parameters.
     * @param $key string|null Encryption key, if not provided a random key is generated.
     * @return CipherParams Default encryption parameters.
     */
    public static function getDefaultParams( $key = null ) {
        return new CipherParams( $key );
    }

    /**
     * Updates CipherParams' Initialization Vector by encrypting a fixed string with current CipherParams state, thus randomizing it.
     */
    protected static function updateIV( CipherParams $cipherParams ) {
        $raw = defined( 'OPENSSL_RAW_DATA' ) ? OPENSSL_RAW_DATA : true;

        $ivLength = strlen( $cipherParams->iv );

        $cipherParams->iv = openssl_encrypt( str_repeat( ' ', $ivLength ), $cipherParams->algorithm, $cipherParams->key, $raw, $cipherParams->iv );
        $cipherParams->iv = substr( $cipherParams->iv, 0, $ivLength);
    }
}
