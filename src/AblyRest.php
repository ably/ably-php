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
        $httpClass = $this->options->httpClass;
        $this->http = new $httpClass( $this->options->hostTimeout );
        $this->auth = new Auth( $this, $this->options );
        
        Log::setLogLevel( $this->options->logLevel );
        if ( !empty( $this->options->logHandler ) ) {
            Log::setLogCallback( $this->options->logHandler );
        }

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
        $server = ($this->options->tls ? 'https://' : 'http://') . (is_array( $this->options->host ) ? $this->options->host[0] : $this->options->host);
        
        if ( $auth ) { // inject auth headers
            $headers = array_merge( $this->auth->getAuthHeaders(), $headers );
        }

        // TODO handle fallback
        // TODO handle token expiry:
        /*
        you know if the reason for failure was token expiry if:
        - the `WWW-Authenticate` header contains `"stale=true"`; and
        - the error code is `40140`
        */

        $res = $this->http->request( $method, $server . $path, $headers, $params );
        if (!$returnHeaders) {
            $res = $res['body'];
        }
        return $res;
    }
}
