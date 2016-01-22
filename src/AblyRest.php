<?php
namespace Ably;

use Ably\Models\ClientOptions;
use Ably\Models\PaginatedResult;
use Ably\Exceptions\AblyException;
use Ably\Exceptions\AblyRequestException;

/**
 * Ably REST client
 */
class AblyRest {

    const API_VERSION = '0.8';

    private $options;

    /**
     * @var \Ably\Http $http object for making HTTP requests
     */
    public $http;
    /**
     * @var \Ably\Auth $auth object providing authorisation functionality
     */
    public $auth;
    /**
     * @var \Ably\Channels $channels object for creating and releasing channels
     */
    public $channels;

    /**
     * Constructor
     * @param \Ably\Models\ClientOptions|string array with options or a string with app key or token
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
        } else {
            Log::setLogCallback( null );
        }

        $httpClass = $this->options->httpClass;
        $this->http = new $httpClass( $this->options );
        $authClass = $this->options->authClass;
        $this->auth = new $authClass( $this, $this->options );
        $this->channels = new Channels( $this );

        return $this;
    }

    /**
     * Shorthand to $this->channels->get()
     * @return \Ably\Channel Channel
     */
    public function channel( $name, $options = array() ) {
        return $this->channels->get( $name, $options );
    }

    /**
     * Gets application-level usage statistics , covering messages sent
     * and received, API requests and connections
     * @return array Statistics
     */
    public function stats( $params = array() ) {
        return new PaginatedResult( $this, 'Ably\Models\Stats', $cipher = false, '/stats', $params );
    }

    /**
     * Retrieves server time
     * @return integer server time in milliseconds
     */
    public function time() {
        $res = $this->get( '/time', $params = array(), $headers = array(), $returnHeaders = false, $authHeaders = false );
        return $res[0];
    }

    /**
     * Returns local time
     * @return integer system time in milliseconds
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
            if ( !empty( $this->options->fallbackHosts ) ) {
                $res = $this->requestWithFallback( $method, $path, $mergedHeaders, $params );
            } else {
                $server = ($this->options->tls ? 'https://' : 'http://') . $this->options->restHost;

                if ( $this->options->tls && !empty( $this->options->tlsPort ) ) {
                    $server .= ':' . $this->options->tlsPort;
                }
                if ( !$this->options->tls && !empty( $this->options->port ) ) {
                    $server .= ':' . $this->options->port;
                }

                $res = $this->http->request( $method, $server . $path, $mergedHeaders, $params );
            }
        } catch (AblyRequestException $e) {
            // check if the exception was caused by an expired token = authorised request + using token auth + specific error message
            $res = $e->getResponse();

            $causedByExpiredToken = $auth
                && !$this->auth->isUsingBasicAuth()
                && ($e->getCode() >= 40140)
                && ($e->getCode() < 40150);

            if ( $causedByExpiredToken ) { // renew the token
                $this->auth->authorise( array(), array( 'force' => true ) );

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
        try {
            if ( $attempt == 0 ) { // using default host
                $server = ($this->options->tls ? 'https://' : 'http://') . $this->options->restHost;
            } else { // using a fallback host
                Log::d( 'Connection failed, attempting with fallback server #' . $attempt );
                // attempt 1 uses fallback host with index 0
                $server = ($this->options->tls ? 'https://' : 'http://') . $this->options->fallbackHosts[$attempt - 1];
            }

            return $this->http->request( $method, $server . $path, $headers, $params );
        }
        catch (AblyRequestException $e) {
            if ( $e->getCode() >= 50000 ) {
                if ( $attempt < min( $this->options->httpMaxRetryCount, count( $this->options->fallbackHosts ) ) ) {
                    return $this->requestWithFallback( $method, $path, $headers, $params, $attempt + 1);
                } else {
                    Log::e( 'Failed to connect to server and all of the fallback servers.' );
                    throw $e;
                }
            }

            throw $e; // other error code than timeout, rethrow exception
        }
    }
}
