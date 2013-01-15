<?php

class Channel extends AblyRest {

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
            return $this->get_resource( '/history', $this->ably->auth_headers(), $params );
        }

        public function presence( $params = array() ) {
            return $this->get_resource( '/presence', $this->ably->auth_headers(), $params );
        }

        public function presence_history( $params = array() ) {
            return $this->get_resource( '/presence/history', $this->ably->auth_headers(), $params );
        }

        public function publish( $name, $data ) {
            $this->ably->log_action( 'Channel.publish()', 'name = '. $name );
            return $this->post_resource( '/publish', array( 'name' => $name, 'data' => $data ) );
        }

        public function stats( $params = array() ) {
            return $this->get_resource( '/stats', $this->ably->auth_headers(), $params );
        }


    /*
     * Private methods
     */
        private function get_resource( $path ) {
            $this->ably->authorise();
            return $this->ably->get( $this->domain, $path, $this->ably->auth_headers() );
        }

        private function post_resource( $path, $params = array() ) {
            $this->ably->authorise();
            return $this->ably->post( $this->domain, $path, $this->ably->auth_headers(), $params );
        }

}