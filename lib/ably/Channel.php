<?php
require_once 'PaginatedResource.php';
require_once 'Presence.php';
require_once 'AblyException.php';

/**
 * Represents a channel
 * @property-read Presence $presence Presence object for this channel
 */
class Channel {

    private $name;
    private $channelPath;
    private $ably;
    private $presence;

    /**
     * Constructor
     * @param AblyRest $ably Ably API instance
     * @param string $name Channel's name
     */
    public function __construct( AblyRest $ably, $name ) {
        $this->ably = $ably;
        $this->name = urlencode($name);
        $this->channelPath = "/channels/{$this->name}";
        $this->presence = new Presence($ably, $name);
    }

    /**
     * Magic getter for the $presence property
     */
    public function __get($name) {
        if ($name == 'presence') {
            return $this->presence;
        }

        throw new AblyException('Undefined property: '.__CLASS__.'::'.$name);
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
        $this->log_action( 'Channel.publish()', 'name = '. urlencode($name) );
        return $this->post( '/messages', json_encode(array( 'name' => urlencode($name), 'data' => $data, 'timestamp' => $this->ably->system_time() )) );
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
        return new PaginatedResource( $this->ably, $this->channelPath . $path, $params );
    }

    private function post( $path, $params = array() ) {
        return $this->ably->post( $this->channelPath . $path, $this->ably->auth_headers(), $params );
    }
}