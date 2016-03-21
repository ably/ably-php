<?php
namespace Ably;

use Ably\Exceptions\AblyException;
use Ably\Models\ChannelOptions;
use Ably\Models\Message;
use Ably\Models\PaginatedResult;

/**
 * Represents a channel
 * @property-read Presence $presence Presence object for this channel
 * @method publish(Message $message)
 * @method publish(string $name, string $data)
 */
class Channel {

    private $name;
    private $channelPath;
    private $ably;
    private $presence;
    /**
     * @var Ably\Models\ChannelOptions
     */
    public $options;

    /**
     * Constructor
     * @param AblyRest $ably Ably API instance
     * @param string $name Channel's name
     * @param ChannelOptions|array|null $options Channel options (for encrypted channels)
     * @throws AblyException
     */
    public function __construct( AblyRest $ably, $name, $options = array() ) {
        $this->ably = $ably;
        $this->name = $name;
        $this->channelPath = "/channels/" . urlencode( $name );
        $this->presence = new Presence( $ably, $this );

        $this->setOptions( $options );
    }

    /**
     * Magic getter for the $presence property
     */
    public function __get( $name ) {
        if ($name == 'presence') {
            return $this->presence;
        }

        throw new AblyException( 'Undefined property: '.__CLASS__.'::'.$name );
    }

    /**
     * Posts a message to this channel
     * @param mixed ... Either a Message, array of Message-s, or (string eventName, string data, [string clientId])
     * @throws \Ably\Exceptions\AblyException
     */
    public function publish() {

        $args = func_get_args();
        $json = '';
        
        if ( count($args) == 1 && is_a( $args[0], 'Ably\Models\Message' ) ) { // single Message
            $msg = $args[0];

            if ( $this->options->cipher ) {
                $msg->setCipherParams( $this->options->cipher );
            }

            $json = $msg->toJSON();
        } else if ( count($args) == 1 && is_array( $args[0] ) ) { // array of Messages
            $jsonArray = array();

            foreach ( $args[0] as $msg ) {
                if ( $this->options->cipher ) {
                    $msg->setCipherParams( $this->options->cipher );
                }

                $jsonArray[] = $msg->toJSON();
            }
            
            $json = '[' . implode( ',', $jsonArray ) . ']';
        } else if ( count($args) >= 2 && count($args) <= 3 ) { // eventName, data[, clientId]
            $msg = new Message();
            $msg->name = $args[0];
            $msg->data = $args[1];
            if ( count($args) == 3 ) $msg->clientId = $args[2];

            if ( $this->options->cipher ) {
                $msg->setCipherParams( $this->options->cipher );
            }

            $json = $msg->toJSON();
        } else {
            throw new AblyException( 'Wrong parameters provided, use either Message, array of Messages, or name and data', 40003, 400 );
        }
        
        $authClientId = $this->ably->auth->clientId;
        // if the message has a clientId set and we're using token based auth, the clientIds must match unless we're a wildcard client
        if ( !empty( $msg->clientId ) && !$this->ably->auth->isUsingBasicAuth() && $authClientId != '*' && $msg->clientId != $authClientId) {
            throw new AblyException( 'Message\'s clientId does not match the clientId of the authorisation token.', 40102, 401 );
        }

        $this->ably->post( $this->channelPath . '/messages', $headers = array(), $json );
        return true;
    }

    /**
     * Retrieves channel's history of messages
     * @param array $params Parameters to be sent with the request
     * @return PaginatedResult
     */
    public function history( $params = array() ) {
        return new PaginatedResult( $this->ably, 'Ably\Models\Message', $this->getCipherParams(), $this->getPath() . '/messages', $params );
    }

    /**
     * @return string Channel's name
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return string Channel portion of the request URI
     */
    public function getPath() {
        return $this->channelPath;
    }

    /**
     * @return CipherParams|null Cipher params if the channel is encrypted
     */
    public function getCipherParams() {
        return $this->options->cipher;
    }

    /**
     * @return \Ably\Models\ChannelOptions
     */
    public function getOptions() {
        return $this->options;
    }

    /**
     * Sets channel options
     * @param array|null $options channel options
     * @throws AblyException
     */
    public function setOptions( $options = array() ) {
        $this->options = new ChannelOptions( $options );
    }
}