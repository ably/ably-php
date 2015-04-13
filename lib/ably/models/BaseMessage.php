<?php
require_once dirname(__FILE__) . '/../AblyExceptions.php';
require_once dirname(__FILE__) . '/../utils/Crypto.php';
require_once dirname(__FILE__) . '/CipherParams.php';

/**
 * Base class for messages sent over channels.
 * Provides automatic encoding and decoding.
 */
abstract class BaseMessage {

    /**
     * @var mixed The message payload.
     */
    public $data;
    /**
     * @var string|null The clientId of the client that published the message.
     * This property is populated by the system, where the clientId is known.
     */
    public $clientId;
    /**
     * @var int The timestamp of this message. Populated by the system.
     */
    public $timestamp;
    /**
     * @var string|null Transformations to be applied to this message.
     * If specified for new messages, it is assumed that $data is already encoded
     * in the specified format, including any encryption.
     * Otherwise the encoding is automatically inferred from $data.
     */
    public $encoding;
    /**
     * @var mixed Original received data, without any transformations, ignored when sending.
     */
    public $originalData;
    /**
     * @var mixed Original received encoding, ignored when sending.
     */
    public $originalEncoding;
    
    /**
     * @var CipherParams|null Cipher parameters for encrypted messages.
     */
    protected $cipherParams;

    /**
     * Creates a JSON representation of this message, ready to be sent to the API.
     *
     * If there is an $encoding specified, the data will be left untouched.
     * If not specified the encoding is inferred from the type of $data and any transformations
     * such as base64 encoding for binary data and encryption are applied.
     */
    public function toJSON() {
        return json_encode( $this->encode() );
    }

    /**
     * Populates the message from JSON and automatically decodes data.
     * @param string|stdClass $json JSON string or an already decoded object.
     * @param bool $keepOriginal When set to true, the message won't be decoded or decrypted
     * @throws AblyException
     */
    public function fromJSON( $json, $keepOriginal = false ) {
        $this->clearFields();

        if (is_object( $json )) {
            $obj = $json;
        } else {
            $obj = @json_decode($json);
            if (!$obj) {
                throw new AblyException( 'Invalid object or JSON encoded object', 400, 40000 );
            }
        }

        foreach ($obj as $key => $value) {
            if (property_exists( 'Message', $key )) {
                $this->$key = $value;
            }
        }

        if ($keepOriginal) return;

        $this->decode();
    }

    /**
     * Returns an encoded message as a stdClass ready for stringifying
     */
    protected function encode() {
        $msg = new stdClass();

        if ($this->encoding) {
            $msg->encoding = $this->encoding;
            $msg->data = $this->data;

            return $msg;
        }

        if (is_array( $this->data ) || is_object( $this->data )) {

            $type = 'json/utf-8';
            $msg->data = json_encode($this->data);
        } else if (!mb_check_encoding( $this->data, 'UTF-8' )) { // non-UTF-8, most likely a binary string

            if (!$this->cipherParams) {
                $type = 'base64';
                $msg->data = base64_encode( $this->data );
            } else {
                $type = '';
                $msg->data = $this->data;
            }
        } else { // it's a UTF-8 string

            $type = 'utf-8';
            $msg->data = $this->data;
        }

        if ($this->cipherParams) {
            $msg->data = base64_encode( Crypto::encrypt( $msg->data, $this->cipherParams ) );
            $msg->encoding = $type . '/cipher+' . $this->cipherParams->algorithm . '/base64';
        } else {
            $msg->encoding = $type;
        }

        return $msg;
    }

    /**
     * Decodes message's data field according to encoding
     * @throws AblyException
     * @throws AblyEncryptionException
     */
    protected function decode() {
        $this->originalData = $this->data;
        $this->originalEncoding = $this->encoding;

        if (!empty( $this->encoding )) {
            $encodings = explode( '/', $this->encoding );

            foreach (array_reverse( $encodings ) as $encoding) {
                if ($encoding == 'base64') {
                    $this->data = base64_decode( $this->data );

                    if ($this->data === false) {
                        throw new AblyException( 'Could not base64-decode message data', 400, 40000 );
                    }
                } else if ($encoding == 'json') {
                    $this->data = json_decode( $this->data );

                    if ($this->data === null) {
                        throw new AblyException( 'Could not JSON-decode message data', 400, 40000 );
                    }
                } else if (strpos( $encoding, 'cipher+' ) === 0) {
                    if (!$this->cipherParams) {
                        throw new AblyEncryptionException( 'Could not decrypt message data, no cipherParams provided', 400, 40000 );
                    }

                    $this->data = Crypto::decrypt( $this->data, $this->cipherParams );
                    
                    if ($this->data === false) {
                        throw new AblyEncryptionException( 'Could not decrypt message data', 400, 40000 );
                    }
                }
            }

            $this->encoding = null;
        }
    }

    /**
     * Sets all the public fields to null
     */
    protected function clearFields() {
        $fields = get_object_vars( $this );
        unset( $fields['cipherParams'] );

        foreach ($fields as $key => $value) {
            $this->$key = null;
        }
    }

    /**
     * Sets cipher parameters for this message for automatic encryption and decryption.
     * @param CipherParams $cipherParams
     */
    public function setCipherParams( CipherParams $cipherParams ) {
        $this->cipherParams = $cipherParams;
    }
}