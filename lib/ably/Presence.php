<?php
require_once dirname(__FILE__) . '/models/PaginatedResource.php';

class Presence {

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
        $this->name = $name;
        $this->channelPath = "/channels/" . urlencode( $name );
    }

    /*
     * Public methods
     */

    /**
     * Retrieves channel's presence data
     * @param array $params Parameters to be sent with the request
     * @return PaginatedResource
     */
    public function get( $params = array() ) {
        return $this->getPaginated( '/presence', $params );
    }

    /**
     * Retrieves channel's history of presence data
     * @param array $params Parameters to be sent with the request
     * @return PaginatedResource
     */
    public function history( $params = array() ) {
        return $this->getPaginated( '/presence/history', $params );
    }

    /*
     * Private methods
     */

    private function getPaginated( $path, $params = array() ) {
        return new PaginatedResource( $this->ably, $this->channelPath . $path, $params );
    }
}