<?php
require_once dirname(__FILE__) . '/../utils/Crypto.php';
require_once dirname(__FILE__) . '/CipherParams.php';

class Message {

    /**
     * @var string|null The event name of the message.
     */
    public $name;
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
     * @var int|null The timestamp for this message. Populated by the server.
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
     * @var mixed Original data, as received, without any transformations, ignored when sending.
     */
    public $originalData;
    
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
     * @param string|stdClass $json JSON string or an already decoded object
     */
    public function toJSON() {
        $msg = new stdClass();

        if ($this->name) $msg->name = $this->name;

        if ($this->encoding) {
            $msg->encoding = $this->encoding;
            $msg->data = $this->data;

            return json_encode( $msg );
        }

        if (is_array( $this->data ) || is_object( $this->data )) {

            $type = 'json/utf-8';
            $msg->data = json_encode($this->data);
        } else if (!mb_check_encoding( $this->data, 'UTF-8' )) { // non-UTF-8, most likely a binary string

            if (!$this->cipherParams) {
                $type = 'base64';
                $msg->data = base64_encode( $this->data );
            } else {
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

        return json_encode( $msg );
    }

    /**
     * Creates a new message from JSON and automatically decodes data.
     * @param string|stdClass $json JSON string or an already decoded object.
     * @throws AblyException
     * @return Message
     */
    public static function fromJSON( $json ) {
        $msg = new Message();

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
                $msg->$key = $value;
            }
        }

        $msg->originalData = $msg->data;

        if (!empty( $msg->encoding )) {
            $encodings = explode( '/', $msg->encoding );

            foreach (array_reverse( $encodings ) as $encoding) {
                if ($encoding == 'base64') {
                    $msg->data = base64_decode( $msg->data );

                    if ($msg->data === false) {
                        throw new AblyException( 'Could not base64-decode message data', 400, 40000 );
                    }
                } else if ($encoding == 'json') {
                    $msg->data = json_decode( $msg->data );

                    if ($msg->data === null) {
                        throw new AblyException( 'Could not JSON-decode message data', 400, 40000 );
                    }
                } else if (strpos( $encoding, 'cipher+' ) === 0) {
                    $msg->data = Crypto::decrypt( $msg->data, $this->cipherParams );
                    
                    if ($msg->data === false) {
                        throw new AblyException( 'Could not decrypt message data', 400, 40000 );
                    }
                }
            }
        }
        
        return $msg;
    }

    /**
     * Sets cipher parameters for this message for automatic encryption and decryption.
     * @param CipherParams $cipherParams
     */
    public function setCipherParams( CipherParams $cipherParams ) {
        $this->cipherParams = $cipherParams;
    }
}