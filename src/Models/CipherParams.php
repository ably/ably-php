<?php
namespace Ably\Models;

/**
 * Cipher parameters.
 * @see Crypto
 */
class CipherParams {
    /** @var string Key used for encryption, may be a binary string. */
    public $key;
    /** @var string Algorithm to be used for encryption. The only supported algorithm is currently 'aes'. */
    public $algorithm;
    /** @var string Key length of the algorithm. Valid values for 'aes' are 128 or 256. */
    public $keyLength;
    /** @var string Algorithm mode. The only supported mode is currenty 'cbc'. */
    public $mode;
    /** @var string Initialization vector for encryption, may be a binary string. */
    public $iv;

    /**
     * Constructor. The encryption algorithm defaults to the only supported algorithm - AES CBC with
     * a default key length of 128. A random IV is generated.
     * @param string|null  $key Encryption key, if not provided a random key is generated.
     * @param string|null  $algorithm Encryption algorithm, defaults to 'aes'.
     * @param Integer|null $keyLength Cipher key length, defaults to 128.
     * @param string|null  $mode Algorithm mode, defaults to 'cbc'.
     */
    public function __construct( $key = null, $algorithm = 'aes', $keyLength = 128, $mode = 'cbc' ) {
        $this->key = $key ? $key : openssl_random_pseudo_bytes( 16 );
        $this->algorithm = $algorithm;
        $this->keyLength = $keyLength;
        $this->mode = $mode;
        $this->iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( $this->getAlgorithmString() ) );
    }

    /**
     * @return string Algorithm string as required by openssl - for instance `aes-128-cbc`
     */
    public function getAlgorithmString() {
        return $this->algorithm . '-' . $this->keyLength . '-' . $this->mode;
    }
}