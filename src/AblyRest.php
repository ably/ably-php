<?php
namespace Ably;

use Ably\Models\ClientOptions;
use Ably\Models\PaginatedResult;
use Ably\Models\HttpPaginatedResponse;
use Ably\Exceptions\AblyException;
use Ably\Exceptions\AblyRequestException;
use Ably\Utils\Miscellaneous;
/**
 * Ably REST client
 */
class AblyRest {

    const API_VERSION = '1.1';
    const LIB_VERSION = '1.1.5';

    public $options;
    protected static $libFlavour = '';

    /**
     * Map of agents that will be appended to the agent header.
     *
     * This should only be used by Ably-authored SDKs.
     * If you need to use this then you have to add the agent to the agents.json file:
     * https://github.com/ably/ably-common/blob/main/protocol/agents.json
     * The keys represent agent names and its corresponding values represent agent versions.
     */
    protected static $agents = array();

    static function ablyAgentHeader()
    {
        $sdk_identifier = 'ably-php/'.self::LIB_VERSION;
        $runtime_identifier = 'php/'.Miscellaneous::getNumeric(phpversion());
        $agent_header = $sdk_identifier.' '.$runtime_identifier;
        // TODO -  remove $libFlavour check once deprecated method setLibraryFlavourString is deleted
        if (self::$libFlavour == 'laravel') {
            $agent_header.= ' laravel';
        }
        foreach(self::$agents as $agent_identifier => $agent_version) {
            $agent_header.= ' '.$agent_identifier;
            if (!empty($agent_version)) {
                $agent_header.= '/'.$agent_version;
            }
        }
        return $agent_header;
    }
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

    // RSC15f Cached fallback host
    private $cachedHost = null;
    private $cachedHostExpires = null;

    /**
     * Constructor
     * @param \Ably\Models\ClientOptions|string array with options or a string with app key or token
     */
    public function __construct( $options = [] ) {

        # convert to options if a single key is provided
        if ( is_string( $options ) ) {
            if ( strpos( $options, ':' ) === false ) {
                $options = [ 'token' => $options ];
            } else {
                $options = [ 'key' => $options ];
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
        $this->push = new Push( $this );

        return $this;
    }

    /**
     * Shorthand to $this->channels->get()
     * @return \Ably\Channel Channel
     */
    public function channel( $name, $options = [] ) {
        return $this->channels->get( $name, $options );
    }

    /**
     * Gets application-level usage statistics , covering messages sent
     * and received, API requests and connections
     * @return array Statistics
     */
    public function stats( $params = [] ) {
        return new PaginatedResult( $this, 'Ably\Models\Stats', $cipher = false, 'GET', '/stats', $params );
    }

    /**
     * Retrieves server time
     * @return integer server time in milliseconds
     */
    public function time() {
        $res = $this->get( '/time', $params = [], $headers = [], $returnHeaders = false, $authHeaders = false );
        return $res[0];
    }

    /**
     * Returns local time
     * @return integer system time in milliseconds
     */
    public function systemTime() {
        return intval( round( microtime(true) * 1000 ) );
    }

    /**
     * Does a GET request, automatically injecting auth headers and handling fallback on server failure
     * @see AblyRest::request()
     */
    public function get( $path, $headers = [], $params = [], $returnHeaders = false, $auth = true ) {
        return $this->requestInternal( 'GET', $path, $headers, $params, $returnHeaders, $auth );
    }

    /**
     * Does a POST request, automatically injecting auth headers and handling fallback on server failure
     * @see AblyRest::request()
     */
    public function post( $path, $headers = [], $params = [], $returnHeaders = false, $auth = true ) {
        return $this->requestInternal( 'POST', $path, $headers, $params, $returnHeaders, $auth );
    }

    /**
     * Does a PUT request, automatically injecting auth headers and handling fallback on server failure
     * @see AblyRest::request()
     */
    public function put( $path, $headers = [], $params = [], $returnHeaders = false, $auth = true ) {
        return $this->requestInternal( 'PUT', $path, $headers, $params, $returnHeaders, $auth );
    }

    /**
     * Does a DELETE request, automatically injecting auth headers and handling fallback on server failure
     * @see AblyRest::request()
     */
    public function delete( $path, $headers = [], $params = [], $returnHeaders = false, $auth = true ) {
        return $this->requestInternal( 'DELETE', $path, $headers, $params, $returnHeaders, $auth );
    }
    /**
     * Does a HTTP request, automatically injecting auth headers and handling fallback on server failure.
     * This method is used internally and `request` is the preferable method to use.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, PATCH, ...)
     * @param string $path root-relative path, e.g. /channels/example/messages
     * @param array $headers HTTP headers to send
     * @param array|string $params Array of parameters to submit or a JSON string
     * @param boolean $returnHeaders if true, returns both headers and body as array, otherwise returns just body
     * @param boolean $auth if authentication headers should be automatically injected
     * @return mixed either array with 'headers' and 'body' fields or just
     *         body, depending on $returnHeaders, body is automatically decoded
     * @throws AblyRequestException if the request fails
     */
    public function requestInternal( $method, $path, $headers = [], $params = [], $returnHeaders = false,
                                     $auth = true ) {

        $mergedHeaders = array_merge( [
            'Accept: application/json',
            'X-Ably-Version: ' .self::API_VERSION,
            'Ably-Agent: ' .self::ablyAgentHeader(),
        ], $headers );

        if ( $auth ) { // inject auth headers
            $mergedHeaders = array_merge( $this->auth->getAuthHeaders(), $mergedHeaders );
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
            // check if the exception was caused by an expired
            // token = authorised request + using token auth + specific error message
            $res = $e->getResponse();

            $causedByExpiredToken = $auth
                && !$this->auth->isUsingBasicAuth()
                && ($e->getCode() >= 40140)
                && ($e->getCode() < 40150);

            if ( $causedByExpiredToken ) { // renew the token
                $this->auth->authorize();

                // merge headers now and use auth = false to prevent potential endless recursion
                $mergedHeaders = array_merge( $this->auth->getAuthHeaders(), $headers );

                return $this->requestInternal($method, $path, $mergedHeaders, $params, $returnHeaders, $auth = false);
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
     * Does an HTTP request with automatic pagination, automatically injected
     * auth headers and automatic server failure handling using fallbackHosts.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, PATCH, ...)
     * @param string $path root-relative path, e.g. /channels/example/messages
     * @param array $params GET parameters to append to $path
     * @param array|object $body JSON-encodable structure to send in the body - leave empty for GET requests
     * @param array $headers HTTP headers to send
     * @return \Ably\Models\HttpPaginatedResponse
     * @throws AblyRequestException This exception is only thrown for status codes >= 500
     */
    public function request( $method, $path, $params = [], $body = '', $headers = []) {
        if ( count( $params ) ) {
            $path .= '?' . http_build_query( $params );
        }

        if ( $method == 'GET' && $body ) {
            throw new AblyException( 'GET requests cannot have a JSON body', 400, 40000 );
        }

        if ( !is_string( $body ) ) {
            $body = json_encode( $body );
        }

        return new HttpPaginatedResponse( $this, 'Ably\Models\Untyped', null, $method, $path, $body, $headers );
    }

    /**
     * Does a HTTP request backed up by fallback servers
     */
    private function getHosts() {
        // The cached fallback host
        if ( $this->cachedHost != null ) {
            if ( $this->systemTime() > $this->cachedHostExpires ) {
                $this->cachedHost = null;
                $this->cachedHostExpires = null;
            } else {
                yield $this->cachedHost;
            }
        }

        // Default host
        yield $this->options->restHost;

        // Fallback hosts
        foreach ($this->options->fallbackHosts as $host) {
            if ( $host != $this->cachedHost ) { // Don't try twice the same host
                yield $host;
            }
        }
    }

    protected function requestWithFallback( $method, $path, $headers = [], $params = [] ) {
        $protocol = ($this->options->tls ? 'https://' : 'http://');
        $maxAttempts = min( $this->options->httpMaxRetryCount, count( $this->options->fallbackHosts ) );
        $attempt = 0;
        foreach ($this->getHosts() as $host) {
            $url = $protocol . $host . $path;
            try {
                $response = $this->http->request( $method, $url, $headers, $params );

                // Keep fallback host for later (RSC15f)
                if ( $attempt > 0 && $host != $this->options->restHost ) {
                    $this->cachedHost = $host;
                    $this->cachedHostExpires = $this->systemTime() + $this->options->fallbackRetryTimeout;
                }

                return $response;
            } catch (AblyRequestException $e) {
                // Clear cached host if it failed (RSC15f)
                if ( $host == $this->cachedHost ) {
                    $this->cachedHost = null;
                    $this->cachedHostExpires = null;
                }

                // other error code than timeout, rethrow exception
                if ( $e->getCode() < 50000 ) {
                    throw $e;
                }

                if ( $attempt >= $maxAttempts ) {
                    Log::e( 'Failed to connect to server and all of the fallback servers.' );
                    throw $e;
                }

                $attempt += 1;
            }
        }
    }

    /**
     * @deprecated
     * Sets a "flavour string", that is sent in the `Ably-Agent` request header.
     * Used for internal statistics.
     * For instance setting 'laravel' results in: `Ably-Agent: laravel`
     */
    public static function setLibraryFlavourString( $flavour = '' ) {
        self::$libFlavour = $flavour;
    }

    /**
     * @param string $agentName represents agent_identifier
     * @param string $agentVersion represents agent_identifier_version (optional)
     * @return void
     * @throws AblyException
     */
    public static function setAblyAgentHeader($agentName, $agentVersion = '' ) {
        if (empty($agentName)) {
            throw new AblyException("agentName cannot be empty");
        }
        self::$agents[$agentName] = $agentVersion;
    }
}
