<?php

require_once 'AuthMethod.php';
require_once 'PresenceState.php';
require_once 'PaginatedResource.php';
require_once 'Channel.php';

class AblyRest {

    public $token;

    private $settings = array();
    private $channels = array();
    private $token_options = array();
    private $raw;
    private $client_id;

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

        # check options
        $this->check_options( $options, 'EMPTY' );

        # sanitize options
        $options = $this->sanitize_options( $options );

        # merge options with defaults
        $settings = array_merge( self::$defaults, $options );

        if ( !empty( $settings['key'] ) ) {

            # check options
            $this->check_options( $settings, 'INVALID_KEY' );

            # setup keys
            list( $settings['appId'], $settings['keyId'], $settings['keyValue'] ) = $this->explode_key( $settings['key'] );
        }

        # check options
        $this->check_options( $settings, 'EMPTY_APP_ID' );

        # $token_options contains the parameters that may be used in
        # token requests
        $token_options = array();
        !empty($settings['keyId']) && $token_options['keyId'] = $settings['keyId'];
        !empty($settings['keyValue']) && $token_options['keyValue'] = $settings['keyValue'];

        # pre-set global settings
        $this->settings = $settings;

        # determine default auth method start with token
        $settings['method'] = AuthMethod::TOKEN;
        if ( !empty($settings['keyValue']) ) {
            if ( empty($settings['clientId']) ) {
                # we have the and do not need to authenticate the client
                $this->log_action( 'authorise()', 'anonymous, using basic auth' );
                $settings['method']    = AuthMethod::BASIC;
                $settings['basicKey']  = base64_encode( $settings['key'] );
            }
        }
        if ( $settings['method'] == AuthMethod::TOKEN ) {
            if ( !empty($settings['authToken']) ) {
                $this->token = $this->simple_array_to_object( array('id' => $settings['authToken']) );
            }
            if ( !empty($settings['authCallback']) ) {
                $this->log_action( 'authorise()', 'using token auth with authCallback' );
                $token_options['authCallback'] = $settings['authCallback'];
            } elseif ( !empty($settings['authUrl']) ) {
                $this->log_action( 'authorise()', 'using token auth with authUrl' );
                $token_options['authUrl'] = $settings['authUrl'];
                $token_options['authHeader'] = $settings['authHeader'] || array();
            } elseif ( !empty($options['keyValue']) ) {
                $this->log_action( 'authorise()', 'using token auth with client-side signing' );
            } elseif ( !empty($options['authToken']) ) {
                $this->log_action( 'authorise()', 'using token auth with supplied token only' );
            } else {
                # this is not a hard error - but any operation that requires authentication will fail
                $this->log_action( 'authorise()', 'no authentication parameters supplied' );
            }
        }

        # basic common routes
        !isset($settings['port'])
            && $settings['port'] = self::$defaults[ $settings['encrypted'] ?  'wss_port' : 'ws_port' ];
        $settings['scheme']    = 'http' . ($settings['encrypted'] ? 's' : '');
        $settings['authority'] = $settings['scheme'] .'://'. $settings['host'] .':'. $settings['port'];
        $settings['baseUri']   = $settings['authority'];

        !isset($settings['clientId']) && $settings['clientId'] = null;

        $this->settings = $settings;
        $this->token_options = $token_options;
        $this->client_id = $settings['clientId'];

        return $this;
    }

    /*
     * Public methods
     */

    /*
     * Authorise request
     */
    public function authorise( $options = array(), $params = array(), $force = false ) {
        if ( !empty($this->token) ) {
            if ( $this->token->expires > $this->timestamp() ) {
                if ( !$force ) {
                    # using cached token
                    $this->log_action('authorise()', sprintf("\tusing cached token; expires = %s\n\tfor humans token expires on %s",
                        $this->token->expires, gmdate("r",$this->token->expires)) );
                    return $this;
                }
            } else {
                # deleting expired token
                unset($this->token);
                $this->log_action( 'authorise()', 'deleting expired token' );
            }
        }
        $this->token = $this->request_token( $options, $params );

        return $this;
    }

    /*
     * Get authentication headers
     */
    public function auth_headers() {

        $header = array();
        if ( $this->getopt('method') == AuthMethod::BASIC ) {
            $header = array( "authorization: Basic {$this->getopt('basicKey')}" );
        } else if ( !empty($this->token) ) {
            $header = array( "authorization: Bearer {$this->token->id}" );
        }
        return $header;
    }

    public function auth_method() {
        return $this->getopt('method');
    }

    /*
     * Get authentication params
     */
    public function auth_params() {

        if ( $this->getopt('method') == AuthMethod::BASIC ) {
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
     * channel
     */
    public function channel( $name ) {
        if ( empty($this->channels[$name]) ) {
            $this->channels[$name] = new Channel($this, $name);
        }
        return $this->channels[$name];
    }

    /*
     * get the current rest host
     */
    public function get_setting($key) {
        return $this->getopt($key);
    }

    /*
     * Request a New Auth Token
     */
    public function request_token( $options = array(), $params = array() ) {

        if ($options == null) $options = array();

        # merge supplied options with already-known options
        $options = array_merge( $this->token_options, $this->sanitize_options($options) );

        # setup the request params
        if ( $params == null ) $params = array();
        if ( empty($params['client_id']) ) {
            $params['client_id'] = isset($options['clientId']) ? $options['clientId'] : $this->client_id;
        }
        if ( !empty($params['capability']) ) {
            $params['capability'] = $this->c14n($params['capability']);
        } else {
            $params['capability'] = '';
        }

        # get the signed token request
        $signed_token_request = null;
        if ( !empty($options['authCallback']) ) {
            $this->log_action( 'request_token()', 'using token auth with auth_callback' );
            $signed_token_request = $options['authCallback']($params);
        } elseif ( !empty($options['authUrl']) ) {
            $this->log_action( 'request_token()', 'using token auth with auth_url' );
            $signed_token_request = $this->request( $options['authUrl'], $options['authHeaders'], array_merge( $this->auth_params(), $params ) );
        } elseif ( !empty($options['keyValue']) ) {
            $this->log_action( 'request_token()', 'using token auth with client-side signing' );
            $signed_token_request = $this->create_token( $options, $params );
        } else {
            trigger_error( 'request_token(): options must include valid authentication parameters' );
        }

        # finally check if signed token request is in correct format
        if (is_string($signed_token_request)) {
            $signed_token_request = $this->simple_array_to_object( array('id' => $signed_token_request) );
        }

        return $signed_token_request;
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

    /**
     * Gets application-level usage statistics , covering messages sent
     * and received, API requests and connections
     * @return PaginatedResource Statistics
     */
    public function stats( $params = array() ) {
        return new PaginatedResource ( $this, '/stats', $params );
    }

    # service time in milliseconds
    public function time() {
        $res = $this->get( '/time' );
        return $res[0];
    }

    # service time in seconds
    public function time_in_seconds() {
        return intval($this->time()/1000);
    }

    # system time in milliseconds
    public function system_time() {
        return round(microtime(true)*1000);
    }

    /*
     * curl wrapper to do GET
     * @throws AblyRequestException if the request fails
     */
    public function get( $path, $headers = array(), $params = array(), $returnHeaders = false ) {
        $server = $this->getopt('authority');
        return $this->request( $server . $path . ( !empty($params) ? '?' . http_build_query($params) : '' ), $headers, null, $returnHeaders );
    }

    /*
     * log action into logfile / syslog (Only in debug mode)
     */
    public function log_action( $action, $msg ) {

        $debug = $this->getopt('debug');

        if ( !$debug ) return false;

        ob_start();

        $micro = microtime(true)*1000;
        echo "\n\n---\n_tick: {$micro} ms\n{$action}:\n";
        if (is_string($msg)) {
            echo $msg;
        } else {
            echo print_r($msg, true);
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
        } elseif ( is_callable($debug) ) {
            $debug($output);
        } else {
            echo $output;
        }

        return true;
    }

    /*
     * curl wrapper to do POST
     * @throws AblyRequestException if the request fails
     */
    public function post( $path, $headers = array(), $params = array(), $returnHeaders = false ) {
        $server = $this->getopt('authority');
        return $this->request( $server . $path, $headers, $params, $returnHeaders );
    }


    /*
     * Private methods
     */

    /*
     * get canonicalised string
     */
    private function c14n( $str ) {
        return json_encode($str);
    }

    /*
     * check library dependencies
     */
    private function check_dependencies( $modules ) {
        $loaded = get_loaded_extensions();
        foreach( $modules as $module ) {
            if ( !in_array($module, $loaded) ) {
                throw new AblyException( "{$module} extension required." );
            }
        }
    }

    private function check_options( $options, $state ) {

        $msg = '';

        switch ($state) {
            case 'EMPTY':
                empty($options) && $msg = 'no options provided';
                break;

            case 'INVALID_KEY':
                if (!empty($options['key']) && count($this->explode_key($options['key']) ) != 3) {
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
            $this->log_action("{$caller['function']}()", $msg );
            trigger_error( $msg );
            return true;
        }

        return false;
    }

    private function create_token( $options = array(), $params = array() ) {
        $query_time = isset($options['query']) && $options['query'];


        # app_id setting
        $app_id = $this->getopt('appId');

        # key_id option
        $key_id = $options['keyId'];
        if (empty($params['id'])) {
            $params['id'] = "$app_id.$key_id";
        } else if ( $params['id'] != $key_id ) {
            trigger_error( 'Incompatible keys specified' );
        }

        # key_value options
        $key_value = $options['keyValue'];
        if (empty($key_id) || empty($key_value)) {
            trigger_error('No key specified');
        }

        $allowed_params = array_intersect_key($params, array_flip(explode(' ', 'id ttl capability client_id timestamp nonce mac')));
        
        if (isset($allowed_params['client_id'])) {
            $allowed_params['clientId'] = $allowed_params['client_id'];
            unset($allowed_params['client_id']);
        }

        $request = array_merge(array(
            'id'         => "$app_id.$key_id",
            'ttl'        => $this->getopt( 'ttl', '' ),
            'capability' => $this->getopt( 'capability' ),
            'clientId'   => $this->getopt( 'clientId' ),
            'timestamp'  => $this->getopt( 'timestamp', $this->timestamp( $query_time ) ),
            'nonce'      => $this->getopt( 'nonce', $this->random() ),
        ), $allowed_params);

        $signText = implode("\n", array(
            $request['id'],
            $request['ttl'],
            $request['capability'],
            $request['clientId'],
            $request['timestamp'],
            $request['nonce'],
        )) . "\n";

        $this->log_action( 'create_token()', sprintf("--signText Start--\n%s\n--signText End--", $signText) );

        if ( empty($request['mac']) ) {
            $hmac           = hash_hmac( 'sha256',$signText, $key_value,true );
            $request['mac'] = $this->getopt( 'mac', $this->safe_base64_encode($hmac) );
            $this->log_action( 'create_token()', sprintf("\tbase64 = %s\n\tmac = %s", $this->safe_base64_encode($hmac), $request['mac']) );
        }

        try {
            $res = $this->post( "/keys/$app_id.$key_id/requestToken", null, $request );
        } catch (AblyRequestException $e) {
            $res = $e->getResponse();
            if (is_array($res) && isset($res['headers']) && isset($res['body'])) {
                $res = $res['body'];
            }
        }

        if ( empty($res->access_token) ) {
            $error = $res->error;
            if (is_string($error)) {
                $msg = $error;
                $code = 50000;
            } else {
                $msg = $error->message;
                $code = $error->code;
            }
            throw new AblyException( 'create_token(): Could not get new access token. '. $msg, $code );
        }

        return $res->access_token;
    }

    /*
     * Shorthand to explode the combined private key
     */
    private function explode_key( $key ) {
        list( $username, $keyValue ) = explode( ':', $key );
        list($appId, $keyId ) = explode( '.', $username );
        return array( $appId, $keyId, $keyValue );  
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
     * @throws AblyRequestException if the request fails
     */
    private function request( $url, $headers = array(), $params = array(), $returnHeaders = false ) {

        $ch = curl_init($url);
        $parts = parse_url($url);
        ($headers === NULL) && $headers = array();
        $curl_cmd = 'curl ';

        if (!empty($params)) {
            curl_setopt( $ch, CURLOPT_POST, true );
            # if an array is passed in we will convert it to parameters
            curl_setopt( $ch, CURLOPT_POSTFIELDS, is_array($params) ? $this->safe_params($params) : $params );
            # if not a array then the data will be assume JSON and passed through the body
            (!is_array($params)) && array_push( $headers, 'Accept: application/json', 'Content-Type: application/json' );
            if (is_array($params)) {
                $curl_cmd .= '--data "'. $this->safe_params($params) .'" ';
            } else {
                $curl_cmd .= "--data '{$params}' ";
            }
            $curl_cmd .= '-X POST ';
        }

        if (!empty($headers)) {
            curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
        }

        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_VERBOSE, $this->getopt('debug') );
        curl_setopt( $ch, CURLOPT_HEADER, $returnHeaders ); // return response headers

        if (!empty($headers)) {
            foreach($headers as $header) {
                $curl_cmd .= "-H '{$header}' ";
            }
        }

        $curl_cmd .= $url;

        $this->log_action( '_request_build()', $curl_cmd );

        $raw = curl_exec($ch);
        $info = curl_getinfo($ch);

        curl_close ($ch);

        $this->log_action( '_request_info()', $info );

        $this->raw[$parts['path']] = $raw; // TODO: is this necessary? may increase memory usage significantly

        $response = null;

        if ($returnHeaders) {
            $headers = substr($raw, 0, $info['header_size']);
            $body = substr($raw, $info['header_size']);

            $response = array('headers' => $headers, 'body' => $this->response_format($body));
        } else {
            $response = $this->response_format($raw);
        }

        $this->log_action( '_request_result()', $response );

        if ( !in_array( $info['http_code'], array(200,201) ) ) {
            throw new AblyRequestException( 'API request failed', $info['http_code'], $response );
        }

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