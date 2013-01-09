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
        'debug'     => false,
        'encrypted' => true,
        'format'    => 'json',
        'host'      => 'rest.ably.io',
        'version'   => 1,
        'ws_port'   => 80,
        'wss_port'  => 443,
    );

    /*
     * Constructor
     */
    public function __construct( $options = array() ) {

        # check dependencies
        $this->check_dependencies( array('curl', 'json') );

        # convert to options if a single key provided
        is_string($options) && $options = array('key' => $options );

        # sanitize options
        $options = $this->sanitize_options( $options );

        # check options
        $this->check_options( $options, 'EMPTY' );

        # merge options with defaults
        $settings = array_merge( self::$defaults, $options );

        if ( !empty( $settings['key'] ) ) {

            # check options
            $this->check_options( $settings, 'INVALID_KEY' );

            # setup keys
            list( $settings['appId'], $settings['keyId'], $settings['keyValue'] ) = explode( ':', $settings['key'] );
        }

        # check options
        $this->check_options( $settings, 'EMPTY_APP_ID' );

        # determine default auth method
        if ( !empty($settings['keyValue']) ) {
            if ( empty($settings['clientId']) ) {
                # we have the and do not need to authenticate the client
                $settings['method']    = 'basic';
                $settings['basicKey']  = base64_encode( $settings['key'] );
            }
        } else {
            $settings['method'] = 'token';
            if ( !empty($options['authToken']) ) {
                $this->token = $this->simple_array_to_object( array('id' => $options['authToken']) );
            }
        }

        # basic common routes
        $settings['scheme']    = 'http' . ($settings['encrypted'] ? 's' : '');
        !isset($settings['port'])
          && $settings['port'] = $settings['encrypted'] ? self::$defaults['wss_port'] : self::$defaults['ws_port'];
        $settings['authority'] = $settings['scheme'] .'://'. $settings['host'] .':'. $settings['port'];
        $settings['baseUri']   = $settings['authority'] . '/apps/' . $settings['appId'];

        $this->settings = $settings;

        return $this;
    }

    /*
     * Public methods
     */

        /*
         * Authorise request
         */
        public function authorise( $options = array() ) {
            if ( !empty($this->token) ) {
                if ( $this->token->expires > $this->timestamp() ) {
                    if ( empty($options['force']) || !$options['force'] ) {
                        # using cached token
                        $this->log_action( 'authorise()', sprintf("\tusing cached token; expires = %s\n\tfor humans token expires on %s", $this->token->expires, gmdate("r",$this->token->expires)) );
                        return $this;
                    }
                } else {
                    # deleting expired token
                    unset($this->token);
                    $this->log_action('authorise()', 'deleting expired token');
                }
            }
            $this->token = $this->request_token($options);

            return $this;
        }

        /*
         * channel
         */
        public function channel( $name ) {
            if ( empty($this->channels[$name]) ) {
                $this->channels[$name] = new Channel($this, $name);
            }
            return $this->channels[$name];
        }

        /*
         * history
         */
        public function history( $options = array() ) {
            $this->authorise();
            $res = $this->get( 'baseUri', '/events', $this->auth_headers() );
            return $res;
        }

        /*
         * Request a New Auth Token
         */
        public function request_token( $options = array() ) {

            $request = array_merge(array(
                'id'         => $this->getopt( 'keyId' ),
                'ttl'        => $this->getopt( 'ttl', '' ),
                'capability' => $this->getopt( 'capability' ),
                'client_id'  => $this->getopt( 'clientId' ),
                'timestamp'  => $this->getopt( 'timestamp', $this->timestamp() ),
                'nonce'      => $this->getopt( 'nonce', $this->random() ),
            ), $this->sanitize_options($options) );

            $signText = implode("\n", array(
                $request['id'],
                $request['ttl'],
                $request['capability'],
                $request['client_id'],
                $request['timestamp'],
                $request['nonce'],
            )) . "\n";

            $this->log_action( 'request_token()', sprintf("--signText Start--\n%s\n--signText End--", $signText) );

            if ( empty($request['mac']) ) {
                $hmac           = hash_hmac( 'sha256',$signText, $this->getopt('keyValue'),true );
                $request['mac'] = $this->getopt( 'mac', $this->safe_base64_encode($hmac) );
                $this->log_action( 'request_token()', sprintf("\tbase64 = %s\n\tmac = %s", base64_encode($hmac), $request['mac']) );
            }

            $res = $this->post( 'baseUri', '/authorise', null, $request );

            if ( !empty($res->access_token) ) {
                return $res->access_token;
            } else {
                trigger_error( 'request_token(): Could not get new access token' );
                return false;
            }
        }

        /*
         * query raw curl responses.
         */
        public function responses( $label = null ) {
            if ( empty($this->raw) ) return false;
            $raw = $this->raw;
            switch( $label ) {
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
            $res = $this->get( 'authority', '/time' );
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
            if ( $this->getopt('method') == 'basic' ) {
                $header = array( "authorization: Basic {$this->getopt('basicKey')}" );
            } else if ( !empty($this->token) ) {
                $header = array( "authorization: Bearer {$this->token->id}" );
            }
            return $header;
        }

        /*
         * Get authentication params
         */
        protected function auth_params() {

            if ( $this->getopt('method') == 'basic' ) {
                $params = array(
                    'key_id'    => $this->getopt('keyId'),
                    'key_value' => $this->getopt('keyValue')
                );
            } else {
                $params = array( "authorisation: Bearer {$this->token->id}" );
            }

            return $params;
        }

        /*
         * curl wrapper to do GET
         */
        protected function get( $domain, $path, $headers = array() ) {
            $fallback = $this->getopt('authority') . $domain;
            return $this->request( $this->getopt( $domain, $fallback ) . $path, $headers );
        }

        /*
         * log action into logfile / syslog (Only in debug mode)
         */
        protected function log_action( $action, $msg ) {

            $debug = $this->getopt('debug');

            if ( !$debug ) return;

            ob_start();

            echo "\n\n---\n{$action}:\n";
            if (is_string($msg)) {
                echo $msg;
            } else {
                var_dump($msg);
            }

            $output = ob_get_contents();

            ob_end_clean();

            if ($debug === 'log') {
                # if ABLY_APP_ROOT is not set then the log is saved inside a sub tmp folder otherwise defaults to root /tmp folder
                $root = defined('ABLY_APP_ROOT') ? ABLY_APP_ROOT : '';
                $handle = fopen( $root . '/tmp/ably.log', 'a' );
                if ($handle) {
                    fwrite( $handle, $output );
                    fclose( $handle );
                } else {
                    trigger_error("log_action(): Could not write to log. Please ensure you have write access to the tmp/ folder.");
                }
            } else {
                echo $output;
            }
        }

        /*
         * curl wrapper to do POST
         */
        protected function post( $domain, $path, $headers = array(), $params = array() ) {
            $fallback = $this->getopt('authority') . $domain;
            return $this->request( $this->getopt($domain, $fallback) . $path, $headers, $params );
        }


    /*
     * Private methods
     */

        private function cb_filter($var) {
            $var = isset($var) && is_string($var) ? trim($var) : $var;
            return ( $var == '' || $var == NULL || $var == array() );
        }

        /*
         * check library dependencies
         */
        private function check_dependencies( $modules ) {
            $loaded = get_loaded_extensions();
            foreach( $modules as $module ) {
                if ( !in_array($module, $loaded) ) {
                    throw new Exception( "{$module} extension required." );
                }
            }
        }

        /*
         * Basic check for the presence of key and it's format
         */
//        private function check_key_format( $options ) {
//            # if no key passed then stop
//            if ( !array_key_exists('key', $options) ) trigger_error( "An API key is required to use the service." );
//            # if key is not in 3 parts then stop
//            if ( count(explode(':', $options['key']) ) != 3) trigger_error( "The API key format is incorrect." );
//        }

        private function check_options( $options, $state ) {

            $msg = '';

            switch ($state) {
                case 'EMPTY':
                    empty($options) && $msg = 'no options provided';
                    break;

                case 'INVALID_KEY':
                    if (!empty($options['key']) && count(explode(':', $options['key']) ) != 3) {
                        $msg = 'invalid key parameter';
                    }
                    break;

                case 'EMPTY_APP_ID':
                    empty($options['appId']) && $msg = 'no appId provided';
                    break;
            }

            if (!empty($msg)) {
                $trace=debug_backtrace();
                $caller=array_shift($trace);
                $action = "{$caller['function']}()";
                $this->log_action($action, $msg );
                trigger_error( $msg );
                return true;
            }

            return false;
        }

        /*
         * Shorthand to get a setting value with an optional fallback value
         */
        private function getopt( $key, $fallback = null ) {
            return empty( $this->settings[$key] ) ? $fallback : $this->settings[$key];
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
        private function request( $url, $header = array(), $params = array() ) {
            $ch = curl_init($url);
            $parts = parse_url($url);

            if (!empty($header)) {
                curl_setopt ( $ch, CURLOPT_HEADER, true );
                curl_setopt ( $ch, CURLOPT_HTTPHEADER, $header );
            }
            if (!empty($params)) {
                curl_setopt ( $ch, CURLOPT_POST, true );
                curl_setopt ( $ch, CURLOPT_POSTFIELDS, $this->safe_params($params) );
            }
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

            $raw = curl_exec($ch);
            $info = curl_getinfo($ch);

            curl_close ($ch);

            $this->raw[$parts['path']] = $raw;
            $this->log_action( '_request()', $info );

            $response = $this->response_format($raw);

            if ( !empty($response->error) ) {
                $msg = is_string( $response->error ) ? $response->error : $response->error->reason;
                trigger_error($msg);
                return;
            }

            $this->log_action( '_response()', $response );

            return $response;
        }

        /*
         * determine the format to return depending on format setting
         */
        private function response_format( $raw ) {
            switch ( $this->getopt('format') ) {
                case 'json': $response = json_decode($raw); break;
                default:     $response = $raw;
            }
            return $response;
        }

        /*
         * URL safe base64 encode
         */
        private function safe_base64_encode( $str ) {
            $b64 = base64_encode( $str );
            //return strtr(trim($b64,'='),'+/','-_');
            return urlencode( $b64 );
        }

        /*
         * URL safe params
         */
        private function safe_params( $params ) {
            return urldecode( http_build_query($params) );
        }

        /*
         * sanitize option hashes that come from external sources.
         * This method takes an option hash and removes all empty/blank values
         * returning a new and clean options hash
         */
        private function sanitize_options( $options ) {
            return array_filter($options, function($var) {
                $var = isset($var) && is_string($var) ? trim($var) : $var;
                return $var !== '' && $var !== NULL && $var !== array();
            });
        }

        /*
         * simple way of converting an associative array to stdObject - does not support multi-dimension arrays
         */
        private function simple_array_to_object( $arr ) {
            return json_decode( json_encode($arr) );
        }

        /*
         * Gets a timestamp
         */
        private function timestamp( $query = false ) {
            return floor( $query ? $this->time()/1000 : time() );
        }
}