<?php
namespace Ably;

use Ably\Exceptions\AblyException;
use Ably\Exceptions\AblyRequestException;
use Ably\Models\ClientOptions;
use Ably\Models\HttpPaginatedResponse;
use Ably\Models\PaginatedResult;
use Ably\Utils\Miscellaneous;

/**
 * Ably REST client
 */
class AblyRest {

    public $options;
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
        $sdk_identifier = 'ably-php/'.Defaults::LIB_VERSION;
        $runtime_identifier = 'php/'.Miscellaneous::getNumeric(phpversion());
        $agent_header = $sdk_identifier.' '.$runtime_identifier;
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

    public $host;

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
        $this->host = new Host($this->options);
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

    public function getHosts() {
        $prefHost = $this->host->getPreferredHost();
        yield $prefHost;
        yield from $this->host->fallbackHosts($prefHost);
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
    public function requestInternal( $method, $path, $headers = [], $params = [], $returnHeaders = false, $auth = true ) {
        $mergedHeaders = array_merge( [
            'Accept: application/json',
            'X-Ably-Version: ' .Defaults::API_VERSION,
            'Ably-Agent: ' .self::ablyAgentHeader(),
        ], $headers );
        if ( $auth ) { // inject auth headers
            $mergedHeaders = array_merge( $this->auth->getAuthHeaders(), $mergedHeaders );
        }
        $attempt = 0;
        $maxPossibleRetries = min(count($this->options->getFallbackHosts()), $this->options->httpMaxRetryCount);
        foreach ($this->getHosts() as $host) {
            $hostUrl = $this->options->getHostUrl($host). $path;
            try {
                $updatedHeaders = $mergedHeaders;
                if ($host != $this->options->getPrimaryRestHost()) { // set hostHeader for fallback host (RSC15j)
                    $updatedHeaders[] = "Host: " . $host;
                }
                $response = $this->http->request( $method, $hostUrl, $updatedHeaders, $params );
                $this->host->setPreferredHost($host);
                break;
            } catch (AblyRequestException $e) {
                $response = $e->getResponse();
                // Clear cached host if it failed (RSC15f)
                $this->host->setPreferredHost("");

                // check if error is timeout
                if ( $e->getCode() >= 50000 && $attempt < $maxPossibleRetries) {
                    $attempt += 1;
                } else {
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
            }
        }
        if (!$returnHeaders) {
            $response = $response['body'];
        }
        return $response;
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

    // RTN17c
    function hasActiveInternetConnection() {
        $response = $this->http->get(Defaults::$internetCheckUrl);
        return $response["body"] == Defaults::$internetCheckOk;
    }

    /**
     * @deprecated
     * Sets a "flavour string", that is sent in the `Ably-Agent` request header.
     * Used for internal statistics.
     * For instance setting 'laravel' results in: `Ably-Agent: laravel`
     */
    public static function setLibraryFlavourString( $flavour = '' ) {
        if (!empty($flavour)) {
            self::setAblyAgentHeader($flavour);
        }
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
