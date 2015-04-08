<?php
require_once dirname(__FILE__) . '/models/Message.php';
require_once dirname(__FILE__) . '/models/PaginatedResource.php';
require_once dirname(__FILE__) . '/Presence.php';
require_once dirname(__FILE__) . '/AblyExceptions.php';
require_once dirname(__FILE__) . '/utils/Crypto.php';

/**
 * Represents a channel
 * @property-read Presence $presence Presence object for this channel
 */
class Channel {

    private $name;
    private $channelPath;
    private $ably;
    private $presence;
    private $options;

    private static $defaultOptions = array(
        'encrypted' => false,
        'cipherParams' => null,
    );

    /**
     * Constructor
     * @param AblyRest $ably Ably API instance
     * @param string $name Channel's name
     * @param array $options Channel options
     * @throws AblyException
     */
    public function __construct( AblyRest $ably, $name, $options = array() ) {
        $this->ably = $ably;
        $this->name = $name;
        $this->channelPath = "/channels/" . urlencode( $name );
        $this->presence = new Presence( $ably, $name );

        $this->options = array_merge( self::$defaultOptions, $options );

        if ($this->options['encrypted'] && !$this->options['cipherParams']) {
            throw new AblyException( 'Channel created as encrypted, but no cipherParams provided' );
        }
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

    /*
     * Public methods
     */

    /**
     * Posts a message to this channel
     * @param string $name Event name
     * @param string $data Message data
     */
    public function publish( $name, $data ) {

        $msg = new Message();
        $msg->name = $name;
        $msg->data = $data;

        if ($this->options['encrypted']) {
            $msg->setCipherParams( $this->options['cipherParams'] );
        }

        return $this->post( '/messages', $msg->toJSON() );
    }

    /**
     * Retrieves channel's history of messages
     * @param array $params Parameters to be sent with the request
     * @return PaginatedResource
     */
    public function history( $params = array() ) {
        return $this->getPaginated( '/messages', $params );
    }

    /*
     * Private methods
     */

    private function getPaginated( $path, $params = array() ) {
        return new PaginatedResource( $this->ably, 'Message', $this->channelPath . $path, $params );
    }

    private function post( $path, $params = array() ) {
        return $this->ably->post( $this->channelPath . $path, $this->ably->auth_headers(), $params );
    }
}