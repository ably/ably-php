<?php
namespace Ably\Models;

/**
 * Cipher parameters.
 * @see Crypto
 */
class CipherParams {
    /** @var string Key used for encryption, may be a binary string. */
    public $key;
    /** @var string Algorithm to be used for encryption. Valid values are: 'aes-128-cbc' (default) and 'aes-256-cbc'. */
    public $algorithm;
    /** @var string Initialization vector for encryption, may be a binary string. */
    public $iv;

    /**
     * Constructor
     * @param string|null $key Encryption key, if not provided a random key is generated.
     * @param string|null $algorithm Algorithm to be used for encryption. Valid values are: 'aes-128-cbc' (default) and 'aes-256-cbc'.
     * @param string|null $iv Initialization vector for encryption, if not provided, random IV is generated.
     */
    public function __construct( $key = null, $algorithm = null, $iv = null ) {
        $this->key = $key ? $key : openssl_random_pseudo_bytes( 16 );
        $this->algorithm = $algorithm ? $algorithm : 'aes-128-cbc';
        $this->iv = $iv ? $iv : openssl_random_pseudo_bytes( openssl_cipher_iv_length( $this->algorithm ) );
    }
}