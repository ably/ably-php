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

    public function __construct() {
        $this->algorithm = 'aes';
        $this->keyLength = '256';
        $this->mode = 'cbc';
    }

    /**
     * @return string Algorithm string as required by openssl - for instance `aes-128-cbc`
     */
    public function getAlgorithmString() {
        return $this->algorithm . '-' . $this->keyLength . '-' . $this->mode;
    }

    public function generateIV() {
        $this->iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( $this->getAlgorithmString() ) );
    }
}