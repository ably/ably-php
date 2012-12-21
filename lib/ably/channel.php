<?php

class Channel extends Ably {

    public $name;
    public $domain;

    private $ably;

    /*
     * Constructor
     */
        public function __construct(Ably $ably, $name) {
            $this->ably = $ably;
            $this->name = $name;
            $this->domain = "/channels/{$name}";
        }


    /*
     * Public methods
     */
        public function history($options = array()) {
            return $this->get_resource('/events');
        }

        public function presence($options = array()) {
            return $this->get_resource('/presence');
        }

        public function publish($name, $data) {
            $this->logAction('Channel.publish()', 'name = '. $name);
            return $this->post_resource('/publish', array( 'name' => $name, 'payload' => $data ));
        }

        public function stats($options = array()) {
            return $this->get_resource('/stats');
        }


    /*
     * Private methods
     */
        private function get_resource($path) {
            $this->ably->authorise();
            return $this->ably->get($this->domain, $path, $this->ably->auth_headers());
        }

        private function post_resource($path, $params = array()) {
            $this->ably->authorise();
            return $this->ably->post($this->domain, $path, $this->ably->auth_headers(), $params);
        }

}