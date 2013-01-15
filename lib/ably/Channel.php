<?php

class Channel {

    public $name;
    public $domain;

    private $ably;

    /*
     * Constructor
     */
        public function __construct( AblyRest $ably, $name ) {
            $this->ably = $ably;
            $this->name = $name;
            $this->domain = "/channels/{$name}";
        }

    /*
     * Public methods
     */
        public function history( $params = array() ) {
            return $this->get_resource( '/history', $params );
        }

        public function presence( $params = array() ) {
            return $this->get_resource( '/presence', $params );
        }

        public function presence_history( $params = array() ) {
            return $this->get_resource( '/presence/history', $params );
        }

        public function publish( $name, $data ) {
            $this->ably->log_action( 'Channel.publish()', 'name = '. $name );
            return $this->post_resource( '/publish', array( 'name' => $name, 'data' => $data ) );
        }

        public function stats( $params = array() ) {
            return $this->get_resource( '/stats', $params );
        }


    /*
     * Private methods
     */
        private function get_resource( $path, $params = array() ) {
            $this->ably->authorise();
            return $this->ably->get( $this->domain, $path, $this->ably->auth_headers(), $params );
        }

        private function post_resource( $path, $params = array() ) {
            $this->ably->authorise();
            return $this->ably->post( $this->domain, $path, $this->ably->auth_headers(), $params );
        }

}