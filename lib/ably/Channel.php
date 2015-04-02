<?php
require_once 'PaginatedResource.php';

class Channel {

    private $name;
    private $channelPath;
    private $ably;

    /**
     * Constructor
     * @param AblyRest $ably Ably API instance
     * @param string $name Channel's name
     */
    public function __construct( AblyRest $ably, $name ) {
        $this->ably = $ably;
        $this->name = urlencode($name);
        $this->channelPath = "/channels/{$this->name}";
    }

    /*
     * Public methods
     */

    /**
     * Retrieves channel's history of messages
     * @param array $params Parameters to be sent with the request
     * @return PaginatedResource
     */
    public function history( $params = array() ) {
        return $this->getPaginated( '/messages', $params );
    }

    /**
     * Retrieves channel's presence data
     * @param array $params Parameters to be sent with the request
     * @return PaginatedResource
     */
    public function presence( $params = array() ) {
        return $this->getPaginated( '/presence', $params );
    }

    /**
     * Retrieves channel's history of presence data
     * @param array $params Parameters to be sent with the request
     * @return PaginatedResource
     */
    public function presence_history( $params = array() ) {
        return $this->getPaginated( '/presence/history', $params );
    }

    /**
     * Posts a message to this channel
     * @param string $name event name
     * @param string $data message data
     */
    public function publish( $name, $data ) {
        $this->log_action( 'Channel.publish()', 'name = '. urlencode($name) );
        return $this->post( '/messages', json_encode(array( 'name' => urlencode($name), 'data' => $data, 'timestamp' => $this->ably->system_time() )) );
    }

    /*
     * Private methods
     */

    private function get( $path, $params = array() ) {
        return $this->ably->get( $this->channelPath . $path, $this->ably->auth_headers(), $params );
    }

    private function getPaginated( $path, $params = array() ) {
        return new PaginatedResource( $this->ably, $this->channelPath . $path, $params );
    }

    private function log_action( $action, $msg ) {
        $this->ably->log_action( $action, $msg );
    }

    private function post( $path, $params = array() ) {
        return $this->ably->post( $this->channelPath . $path, $this->ably->auth_headers(), $params );
    }
}