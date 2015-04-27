<?php
namespace Ably;

use Ably\Auth;
use Ably\Http;
use Ably\Log;
use Ably\Models\ClientOptions;
use Ably\Exceptions\AblyException;
use Ably\Exceptions\AblyRequestException;

class AblyRest {

    private $options;

    /**
     * @var \Ably\Http $http
     */
    public $http;
    /**
     * @var \Ably\Auth $auth
     */
    public $auth;

    /**
     * Constructor
     * @param \Ably\Models\ClientOptions|string options or a string with app key or token
     */
    public function __construct( $options = array() ) {

        # convert to options if a single key is provided
        if ( is_string( $options ) ) {
            if ( strpos( $options, ':' ) === false ) {
                $options = array( 'token' => $options );
            } else {
                $options = array( 'key' => $options );
            }
        }

        $this->options = new ClientOptions( $options );

        Log::setLogLevel( $this->options->logLevel );
        if ( !empty( $this->options->logHandler ) ) {
            Log::setLogCallback( $this->options->logHandler );
        }

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
        return $this->get( '/stats', $headers = array(), $params );
    }

    /**
     * @return integer server's time
     */
    public function time() {
        $res = $this->get( '/time', $params = array(), $headers = array(), $returnHeaders = false, $authHeaders = false );
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
     * @see AblyRest::request()
     */
    public function get( $path, $headers = array(), $params = array(), $returnHeaders = false, $auth = true ) {
        return $this->request( 'GET', $path, $headers, $params, $returnHeaders, $auth );
    }

    /**
     * Does a POST request, automatically injecting auth headers and handling fallback on server failure
     * @see AblyRest::request()
     */
    public function post( $path, $headers = array(), $params = array(), $returnHeaders = false, $auth = true ) {
        return $this->request( 'POST', $path, $headers, $params, $returnHeaders, $auth );
    }

    /**
     * Does a HTTP request, automatically injecting auth headers and handling fallback on server failure
     * @param string $method HTTP method (GET, POST, PUT, DELETE, ...)
     * @param string $path root-relative path, e.g. /channels/example/messages
     * @param array $headers HTTP headers to send
     * @param array|string $params Array of parameters to submit or a JSON string
     * @param boolean $returnHeaders if true, returns both headers and body as array, otherwise returns just body
     * @param boolean $auth if authentication headers should be automatically injected
     * @return mixed either array with 'headers' and 'body' fields or just body, depending on $returnHeaders, body is automatically decoded
     * @throws AblyRequestException if the request fails
     */
    public function request( $method, $path, $headers = array(), $params = array(), $returnHeaders = false, $auth = true ) {
        if ( $auth ) { // inject auth headers
            $mergedHeaders = array_merge( $this->auth->getAuthHeaders(), $headers );
        } else {
            $mergedHeaders = $headers;
        }

        try {
            if ( is_array( $this->options->host ) ) {
                $res = $this->requestWithFallback( $method, $path, $mergedHeaders, $params );
            } else {
                $server = ($this->options->tls ? 'https://' : 'http://') . $this->options->host;
                $res = $this->http->request( $method, $server . $path, $mergedHeaders, $params );
            }
        } catch (AblyRequestException $e) {
            // check if the exception was caused by an expired token = authorised request + using token auth + specific error message 
            $res = $e->getResponse();
            
            $causedByExpiredToken = $auth
                && !$this->auth->isUsingBasicAuth()
                && $e->getAblyCode() == 40140
                && preg_match( '/Www-authenticate:.*stale *= *"?true"?/i', $res['headers'] );

            if ( $causedByExpiredToken ) { // renew the token
                $this->auth->authorise( array(), true );
                
                // merge headers now and use auth = false to prevent potential endless recursion
                $mergedHeaders = array_merge( $this->auth->getAuthHeaders(), $headers );

                return $this->request( $method, $path, $mergedHeaders, $params, $returnHeaders, $auth = false );
            } else {
                throw $e;
            }
        }

        if (!$returnHeaders) {
            $res = $res['body'];
        }
        return $res;
    }

    /**
     * Does a HTTP request backed up by fallback servers
     */
    protected function requestWithFallback( $method, $path, $headers = array(), $params = array(), $attempt = 0 ) {

        if ( $attempt >= count( $this->options->host ) ) {
            throw new AblyRequestException( 'Could not connect to server or any of the fallback servers', 500, 50003 );
        }

        if ( $attempt > 0 ) {
            Log::d( 'Connection failed, attempting with fallback server #' . $attempt );
        }

        $server = ($this->options->tls ? 'https://' : 'http://') . $this->options->host[$attempt];

        try {
            $res = $this->http->request( $method, $server . $path, $headers, $params );

            // successful reuest

            if ($attempt > 0) { // reorder servers, so that the working one is first and not working one(s) last
                Log::d( 'Switching server to: ' . $this->options->host[$attempt] );
                $this->options->host = $this->rotateArray( $this->options->host, $attempt );
            }

            return $res;
        }
        catch (AblyRequestException $e) {
            if ( $e->getAblyCode() == 50003 ) {
                return $this->requestWithFallback( $method, $path, $headers, $params, $attempt + 1);
            }

            throw $e; // other error code than timeout, rethrow exception
        }
    }

    private function rotateArray( $array, $offset ) {
        return array_merge( array_slice( $array, $offset, NULL, true ), array_slice( $array, 0, $offset, true ) );
    }
}
