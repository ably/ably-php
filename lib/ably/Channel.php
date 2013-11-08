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
            return $this->get( '/history', $params );
        }

        public function presence( $params = array() ) {
            return $this->get( '/presence', $params );
        }

        public function presence_history( $params = array() ) {
            return $this->get( '/presence/history', $params );
        }

        public function publish( $name, $data ) {
            $this->log_action( 'Channel.publish()', 'name = '. $name );
            return $this->post( '/publish', json_encode(array( 'name' => $name, 'data' => $data, 'timestamp' => $this->ably->system_time() )) );
        }

        public function stats( $params = array() ) {
            return $this->get( '/stats', $params );
        }


    /*
     * Private methods
     */
        private function get( $path, $params = array() ) {
//            $this->ably->authorise();
            return $this->ably->get( $this->domain, $path, $this->ably->auth_headers(), $params );
        }

        private function log_action( $action, $msg ) {
            $this->ably->log_action( $action, $msg );
        }

        private function post( $path, $params = array() ) {
//            $this->ably->authorise();
            return $this->ably->post( $this->domain, $path, $this->ably->auth_headers(), $params );
        }
}