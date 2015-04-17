<?php
namespace tests;
use Ably\AblyRest;
use \stdClass;

require_once __DIR__ . '/../../vendor/autoload.php';


/**
 * Generates test application keys in the Ably sandbox
 */
class TestApp {

    private static $fixtureFile = '/../../ably-common/test-resources/test-app-setup.json';
    private $settings = array();
    private $options;
    private $fixture;
    private $appId;
    private $appKeys = array();
    private $server;

    public function __construct() {

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

        if (!isset( $settings['port'] ) ) {
            $settings['port'] = $settings['encrypted'] ?  443 : 80;
        }

        $scheme = 'http' . ($settings['encrypted'] ? 's' : '');

        $this->server = $scheme .'://'. $settings['host'] .':'. $settings['port'];

        $this->init( $settings );

        return $this;
    }

    private function init( $settings ) {

        $this->fixture = json_decode ( file_get_contents( __DIR__ . self::$fixtureFile, 1 ) );

        if (!$this->fixture) {
            echo 'Unable to read fixture file';
            exit(1);
        }

        $raw = $this->request( 'POST', $this->server . '/apps', array(), json_encode( $this->fixture->post_apps ) );
        $response = json_decode( $raw );

        if ($response === null) {
            echo 'Could not connect to API.';
            exit(1);
        }

        $this->appId = $response->appId;

        foreach ($response->keys as $key) {
            $obj = new stdClass();
            $obj->appId = $this->appId;
            $obj->id = $key->id;
            $obj->value = $key->value;
            $obj->string = $this->appId . '.' . $key->id . ':' . $key->value;
            $obj->capability = $key->capability;

            $this->appKeys[] = $obj;
        }

        $this->options = $settings;
    }

    public function release() {
        if (!empty($this->options)) {
            $ably = new AblyRest( $this->getAppKeyDefault()->string );
            $this->request( 'DELETE', $this->server . '/apps/' . $this->appId, $ably->auth_headers() );
            $this->options = null;
        }
    }

    public function getFixture() {
        return $this->fixture;
    }

    public function getOptions() {
        return $this->options;
    }

    public function getAppId() {
        return $this->appId;
    }

    public function getAppKeyDefault() {
        return $this->appKeys[0];
    }

    public function getAppKeyWithCapabilities() {
        return $this->appKeys[1];
    }

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
        $this->options['debug'] && curl_setopt( $ch, CURLOPT_VERBOSE, 1 );

        $raw = curl_exec($ch);
        curl_close ($ch);

        if ($this->options['debug']) {
            var_dump($curl_cmd);
            var_dump($raw);
        }

        return $raw;
    }
}