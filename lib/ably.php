<?php
/*
 * Ably Client API (REST) Library
 */

class Ably {

    public $token;

    private $settings = array();

    private static $defaults = array(
        'debug'   => false,
        'format'  => 'json',
        'host'    => 'rest.ably.io',
        'scheme'  => 'https',
        'version' => 1,
    );

    /*
     * Singleton Pattern
     */
    private static $instance;
    public static function get_instance($options = array()) {
        if (!self::$instance) self::$instance = new Ably($options);
        return self::$instance;
    }

    /*
     * Constructor
     */
    private function __construct($options = array()) {

        # check dependencies
        $this->check_dependencies(['curl', 'json']);

        # merge options into defaults
        $settings = array_merge(self::$defaults, $options);

        # check key format is correct
        $this->check_key_format($settings);

        # setup keys
        list($settings['appId'], $settings['keyId'], $settings['keyValue']) = explode(':', $settings['key']);

        # determine default auth method
        if (!empty($settings['keyValue'])) {
            if (empty($settings['clientId'])) {
                # we have the and do not need to authenticate the client
                $settings['method'] = 'basic';
                $settings['basicKey']  = base64_encode($settings['key']);
            }
        } else {
            $settings['method'] = 'token';
            if (!empty($options['authToken'])) {
                $this->token = json_decode(json_encode(array('id' => $options['authToken'])));
            }
        }

        # basic common routes
        $settings['authority'] = $settings['scheme'] .'://'. $settings['host'];
        $settings['baseUri']   = $settings['authority'] . '/apps/' . $settings['appId'];

        $this->settings = $settings;
    }

    /*
     * Public methods
     */

        /*
         * Authorise request
         */
        public function authorise($options = array()) {
            if ( !empty($this->token) ) {
                if ($this->token->expires > $this->timestamp()) {
                    if (empty($options['force']) || !$options['force']) {
                        # using cached token
                        return;
                    }
                } else {
                    # deleting expired token
                    unset($this->token);
                }
            }
            $this->token = $this->request_token($options);
        }

        /*
         * channel
         */
        public function channel($channelId) {
            #$res = $this->request("{$this->settings['auhority']}/channels/{$channelId}");
            # return $channelId;
        }

        /*
         * history
         */
        public function history($options=array()) {
            $this->authorise();
            $res = $this->get('baseUri', '/history');
            return $res;
        }

        /*
         * Request a New Auth Token
         */
        public function request_token($options = array()) {

            $request = array(
                'id'         => $this->getopt( 'keyId' ),
                'expires'    => $this->getopt( 'expires', 3600 ),
                'capability' => $this->getopt( 'capability' ),
                'client_id'  => $this->getopt( 'clientId' ),
                'timestamp'  => $this->getopt( 'timestamp', $this->timestamp() ),
                'nonce'      => $this->getopt( 'nonce', $this->random() ),
            );

            $signText = implode("\n", $request)."\n";

            $request['mac'] = $this->getopt('mac', base64_encode(hash_hmac('sha1',$signText, $this->getopt('keyValue'),true)));

            $params = urldecode(http_build_query($request));

            $res = $this->post('baseUri', '/authorise', $params);

            if (!empty($res->access_token)) {
                return $res->access_token;
            } else {
                trigger_error('request_token(): Could not get new access token');
                return;
            }
        }

        public function stats($options=array()) {
            $this->authorise();
            $res = $this->get('baseUri', '/stats');
            return $res;
        }

        public function time() {
            $res = $this->get('authority', '/time');
            return $res[0];
        }

    /*
     * Private methods
     */

        /*
         * Get authentication headers
         */
        private function auth_headers() {
            $header = array();
            if ($this->getopt('method') == 'basic') {
                $header = array("authorisation: Basic {$this->basicKey}");
            } else {
                $header = array("authorisation: Bearer {$this->token->id}");
            }

            return $header;
        }

        /*
         * check library dependencies
         */
        private function check_dependencies($modules) {
            $loaded = get_loaded_extensions();
            foreach($modules as $module) {
                if (!in_array($module, $loaded)) {
                    die("{$module} extension required.");
                }
            }
        }

        /*
         * Basic check for the presence of key and it's format
         */
        private function check_key_format($options) {
            # if no key passed then stop
            if (!array_key_exists('key', $options)) trigger_error("An API key is required to use the service.");
            # if key is not in 3 parts then stop
            if (count(explode(':', $options['key'])) != 3) trigger_error("The API key format is incorrect.");
        }

        /*
         * Shorthand to get a setting value with an optional fallback value
         */
        private function getopt($key, $fallback=null) {
            return empty($this->settings[$key]) ? $fallback : $this->settings[$key];
        }

        /*
         * curl wrapper to do GET
         */
        private function get($key, $path, $header=array()) {
            return $this->request($this->settings[$key] . $path, $header);
        }

        /*
         * curl wrapper to do POST
         */
        private function post($label, $path, $params=array(), $header=array()) {
            return $this->request($this->settings[$label] . $path, $header, $params);
        }

        /*
         * Get a random 16 digit number
         */
        private function random() {
            $multiplier = pow(10,16);
            return rand($multiplier,9*$multiplier);
        }

        /*
         * Build the curl request
         */
        private function request($url, $header = array(), $params = array()) {
            $ch = curl_init($url);
            if ($header != null) {
                curl_setopt ($ch, CURLOPT_HEADER, true);
                curl_setopt ($ch, CURLOPT_HTTPHEADER, $header);
            }
            if ($params != null) {
                curl_setopt ($ch, CURLOPT_POST, true);
                curl_setopt ($ch, CURLOPT_POSTFIELDS, $params);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec ($ch);
            curl_close ($ch);

            if ($this->settings['format'] === 'json') {
                $response = json_decode($response);
            }

            if (!empty($response->error)) {
                $msg = is_string($response->error) ? $response->error : $response->error->reason;
                trigger_error($msg);
                return;
            }

            return $response;
        }

        /*
         * Gets a timestamp
         */
        private function timestamp($query=false) {
            return floor( $query ? $this->time()/1000 : time() );
        }
}