<?php
/*
 * Ably Client API (REST) Library
 */
require_once 'ably/channel.php';

class Ably {

    public $token;

    private $settings = array();
    private $channels = array();
    private $raw;

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
        if (!self::$instance) {
            if (is_string($options)) {
                $options = array('key' => $options);
            }
            self::$instance = new Ably($options);
        }
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
                        $this->logAction('authorise()', 'using cached token; expires = ' + $this->token->expires);
                        return;
                    }
                } else {
                    # deleting expired token
                    unset($this->token);
                    $this->logAction('authorise()', 'deleting expired token');
                }
            }
            $this->token = $this->request_token($options);
        }

        /*
         * channel
         */
        public function channel($name) {
            if (empty($this->channels[$name])) {
                $channel = new Channel(self::$instance, $name);
                $this->channels[$name] = $channel;
            }
            return $this->channels[$name];
        }

        /*
         * history
         */
        public function history($options=array()) {
            $this->authorise();
            $res = $this->get('baseUri', '/events', $this->auth_headers());
            return $res;
        }

        /*
         * Request a New Auth Token
         */
        public function request_token( $options = array() ) {

            $request = array_merge(array(
                'id'         => $this->getopt( 'keyId' ),
                'expires'    => $this->getopt( 'expires', 3600 ),
                'capability' => $this->getopt( 'capability' ),
                'client_id'  => $this->getopt( 'clientId' ),
                'timestamp'  => $this->getopt( 'timestamp', $this->timestamp() ),
                'nonce'      => $this->getopt( 'nonce', $this->random() ),
            ), $options);

            $signText = implode("\n", array(
                $request['id'],
                $request['expires'],
                $request['capability'],
                $request['client_id'],
                $request['timestamp'],
                $request['nonce'],
            )) . "\n";

            if (empty($request['mac'])) {
                $request['mac'] = $this->getopt('mac', $this->base64_encode_safe(hash_hmac('sha1',$signText, $this->getopt('keyValue'),true)));
                $this->logAction('request_token()', 'mac = '. $request['mac']);
            }

            $params = urldecode(http_build_query($request));

            $res = $this->post('baseUri', '/authorise', $params);

            if (!empty($res->access_token)) {
                return $res->access_token;
            } else {
                trigger_error('request_token(): Could not get new access token');
                return;
            }
        }

        /*
         * query raw curl responses.
         */
        public function responses($label = null) {
            if (empty($this->raw)) return;
            $raw = $this->raw;
            switch($label) {
                case null:
                    $res = $raw; break;
                case 'first':
                    $res = $raw[0]; break;
                case 'last':
                    $res = $raw[ count($raw)-1 ]; break;
                default:
                    $res = $raw[$label];
            }
            return $res;
        }

        public function stats() {
            $this->authorise();
            $res = $this->get( 'baseUri', '/stats', $this->auth_headers() );
            return $res;
        }

        public function time() {
            $res = $this->get('authority', '/time');
            return $res[0];
        }

    /*
     * Protected methods
     */

        /*
         * Get authentication headers
         */
        protected function auth_headers() {
            $header = array();
            if ($this->getopt('method') == 'basic') {
                $header = array("authorisation: Basic {$this->getopt('basicKey')}");
            } else if (!empty($this->token)) {
                $header = array("authorisation: Bearer {$this->token->id}");
            }
            return $header;
        }

        /*
         * Get authentication params
         */
        protected function auth_params() {

            if ($this->getopt('method') == 'basic') {
                $params = array('key_id' => $this->getopt('keyId'), 'key_value' => $this->getopt('keyValue'));
            } else {
                $params = array("authorisation: Bearer {$this->token->id}");
            }

            return $params;
        }

        /*
         * curl wrapper to do GET
         */
        protected function get($domain, $path, $headers=array()) {
            return $this->request($this->getopt($domain, $domain) . $path, $headers);
        }

        /*
         * log action into logfile / syslog (Only in debug mode)
         */
        protected function logAction($action, $msg) {
            var_dump($this->getopt('key'));
            if (!$this->getopt('debug')) return;

            # TODO : use logfile or syslog
            # var_dump for now!
            var_dump("{$action}:");
            var_dump($msg);
        }

        /*
         * curl wrapper to do POST
         */
        protected function post($domain, $path, $params=array(), $header=array()) {
            return $this->request($this->getopt($domain, $domain) . $path, $header, $params);
        }


    /*
     * Private methods
     */

        /*
         * URL safe base64 encode
         */
        private function base64_encode_safe($str) {
            $b64 = base64_encode($str);
            $this->logAction('base64_encode_safe()', ' str = '. $b64);
            //return strtr(trim($b64,'='),'+/','-_');
            return urlencode($b64);
        }

        /*
         * check library dependencies
         */
        private function check_dependencies($modules) {
            $loaded = get_loaded_extensions();
            foreach($modules as $module) {
                if (!in_array($module, $loaded)) {
                    throw new Exception("{$module} extension required.");
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
            $parts = parse_url($url);

            if ($header != null) {
                curl_setopt ($ch, CURLOPT_HEADER, true);
                curl_setopt ($ch, CURLOPT_HTTPHEADER, $header);
            }
            if ($params != null) {
                curl_setopt ($ch, CURLOPT_POST, true);
                curl_setopt ($ch, CURLOPT_POSTFIELDS, $params);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $raw = curl_exec($ch);
            $info = curl_getinfo($ch);

            curl_close ($ch);

            $this->raw[$parts['path']] = $raw;
            $this->logAction('_request()', $info );

            $response = $this->response_format($raw);

            if (!empty($response->error)) {
                $msg = is_string($response->error) ? $response->error : $response->error->reason;
                trigger_error($msg);
                return;
            }

            $this->logAction('_response()', $response );
            return $response;
        }

        /*
         * determine the format to return depending on format setting
         */
        private function response_format($raw) {
            switch ($this->getopt('format')) {
                case 'json': $response = json_decode($raw); break;
                default:     $response = $raw;
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