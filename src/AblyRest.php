<?php
namespace Ably;

use Ably\Auth;
use Ably\Log;
use Ably\Http;
use Ably\Models\ClientOptions;
use Ably\Exceptions\AblyException;
use Ably\Exceptions\AblyRequestException;

class AblyRest {

    private $options;

    /**
     * @var Ably\Http $http
     */
    public $http;
    /**
     * @var Ably\Auth $auth
     */
    public $auth;

    /*
     * Constructor
     */
    public function __construct( $options = array() ) {

        # convert to options if a single key is provided
        if (is_string($options)) {
            $options = array('key' => $options );
        }

        $this->options = new ClientOptions( $options );
        $httpClass = $this->options->httpClass;
        $this->http = new $httpClass( $this->options->hostTimeout );
        $this->auth = new Auth( $this, $this->options );

        return $this;
    }

    /**
     * @return \Ably\Channel Channel
     */
    public function channel( $name, $options = array() ) {
        return new Channel( $this, $name, $options );
    }

    /**
     * Gets application-level usage statistics , covering messages sent
     * and received, API requests and connections
     * @return array Statistics
     */
    public function stats( $params = array() ) {
        return $this->get( '/stats', $this->auth_headers(), $params );
    }

    /**
     * @return integer server's time
     */
    public function time() {
        $res = $this->get( '/time', array(), array(), false, true );
        return $res[0];
    }

    /**
     * @return integer system time
     */
    public function systemTime() {
        return round( microtime(true) * 1000 );
    }

    /**
     * Does a GET request, automatically injecting auth headers and handling fallback on server failure
     * @throws AblyRequestException if the request fails
     */
    public function get( $path, $headers = array(), $params = array(), $returnHeaders = false, $noAuth = false ) {
        return $this->request( 'GET', $path, $headers, $params, $returnHeaders, $noAuth );
    }

    /*
     * Does a POST request, automatically injecting auth headers and handling fallback on server failure
     * @throws AblyRequestException if the request fails
     */
    public function post( $path, $headers = array(), $params = array(), $returnHeaders = false, $noAuth = false ) {
        return $this->request( 'POST', $path, $headers, $params, $returnHeaders, $noAuth );
    }

    /**
     * Does a HTTP request, automatically injecting auth headers and handling fallback on server failure
     * @throws AblyRequestException if the request fails
     */
    public function request( $method, $path, $headers = array(), $params = array(), $returnHeaders = false, $noAuth = false ) {
        $server = ($this->options->tls ? 'https://' : 'http://') . (is_array( $this->options->host ) ? $this->options->host[0] : $this->options->host);
        if ( !$noAuth) {
            $headers = array_merge( $this->auth->getAuthHeaders(), $headers );
        }

        $res = $this->http->request( $method, $server . $path, $headers, $params );
        if (!$returnHeaders) {
            $res = $res['body'];
        }
        return $res;
    }
}
