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
            $this->name = urlencode($name);
            $this->domain = "/channels/{$this->name}";
        }

    /*
     * Public methods
     */
        public function history( $params = array() ) {
            return $this->get( '/messages', $params );
        }

        public function presence( $params = array() ) {
            return $this->get( '/presence', $params );
        }

        public function presence_history( $params = array() ) {
            return $this->get( '/presence/messages', $params );
        }

        public function publish( $name, $data ) {
            $this->log_action( 'Channel.publish()', 'name = '. urlencode($name) );
            return $this->post( '/messages', json_encode(array( 'name' => urlencode($name), 'data' => $data, 'timestamp' => $this->ably->system_time() )) );
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