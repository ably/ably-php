<?php
namespace tests;
use Ably\AblyRest;
use \stdClass;

require_once __DIR__ . '/../../vendor/autoload.php';


class TestOption {

    private static $spec_file = __DIR__ . '/../../ably-common/test-resources/test-app-setup.json';
    private $settings = array();
    private $options;
    private $spec;

    /**
     * singleton pattern
     */
    private static $instance;
    public static function get_instance() {
        if (!self::$instance) self::$instance = new TestOption();
        return self::$instance;
    }

    private function __construct() {

        $settings = array( 'host' => getenv("WEBSOCKET_ADDRESS"), 'debug' => false );

        if (empty($settings['host'])) {
            //$settings['host'] = "staging-rest.ably.io";
            $settings['host'] = "sandbox-rest.ably.io";
            //$settings['host'] = "rest.ably.io";
            $settings['encrypted'] = true;
        } else {
            $settings['encrypted'] = $settings['host'] != "localhost";
            $settings['port'] = $settings['encrypted'] ? 8081 : 8080;
        }

        # basic common routes
        !isset($settings['port'])
            && $settings['port'] = $settings['encrypted'] ?  443 : 80;
        $settings['scheme']    = 'http' . ($settings['encrypted'] ? 's' : '');
        $settings['authority'] = $settings['scheme'] .'://'. $settings['host'] .':'. $settings['port'];

        $this->settings = $settings;

        return $this;
    }

    public function get_opts() {
        if (empty($this->options)) {

            $this->spec = json_decode ( file_get_contents( self::$spec_file, 1 ) );

            if (!$this->spec) {
                trigger_error( 'unable to read spec file' );
            }

            $raw = $this->request( 'POST', join('/', array($this->settings['authority'], 'apps') ) , array(), json_encode( $this->spec->post_apps ) );
            $response = json_decode( $raw );

            if ($response === null) {
                echo 'Could not connect to API.';
                exit(1);
            }

            $keys = $response->keys;
            $app_id = $response->appId;
            $key_objs = array();
            $first_private_api_key = null;

            foreach ($keys as $key) {
                $obj = new stdClass();
                $obj->key_id = $key->id;
                $obj->key_value = $key->value;
                $obj->key_str = implode('.', array($app_id, implode(':', array($obj->key_id, $obj->key_value))));
                $obj->capability = $key->capability;
                array_push($key_objs, $obj);

                empty($first_private_api_key) && $first_private_api_key = $obj->key_str;

                unset($obj);
            }

            $this->options = array_merge( $this->settings, array(
                'appId' => $app_id,
                'keys'  => $key_objs,
                'first_private_api_key' => $first_private_api_key,
            ));
        }
        return $this->options;
    }

    public function clear_opts() {
        if (!empty($this->options)) {
            $ably = new AblyRest($this->options['first_private_api_key']);
            $this->request( 'DELETE', join( '/', array($this->settings['authority'],'apps', $this->options['appId']) ), $ably->auth_headers() );
            $this->options = null;
        }
    }

    public function getSpec() {
        return $this->spec;
    }

    /*
     * Build the curl request
     */
    private function request( $mode, $url, $headers = array(), $params = array() ) {
        $ch = curl_init($url);
        $curl_cmd = 'curl ';

        if ( $mode == 'DELETE') curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'DELETE' );
        if ( $mode == 'POST' )  curl_setopt ( $ch, CURLOPT_POST, 1 );

        if (!empty($params)) {
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $params );
            array_push( $headers, 'Accept: application/json', 'Content-Type: application/json' );
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

        if (!empty($headers)) {
            foreach($headers as $header) {
                $curl_cmd .= "-H '{$header}' ";
            }
        }

        $curl_cmd .= $url;

        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        $this->settings['debug'] && curl_setopt( $ch, CURLOPT_VERBOSE, 1 );

        $raw = curl_exec($ch);
        curl_close ($ch);

        if ($this->settings['debug']) {
            var_dump($curl_cmd);
            var_dump($raw);
        }

        return $raw;
    }
}