<?php
namespace Ably\PubSub;

use Ably\PubSub\Exceptions\AblyException;
use Ably\PubSub\Exceptions\AblyRequestException;
use Ably\PubSub\Models\ClientOptions;
use Ably\PubSub\Models\HttpPaginatedResponse;
use Ably\PubSub\Models\PaginatedResult;
use Ably\PubSub\Utils\Miscellaneous;
use MessagePack\MessagePack;
use MessagePack\PackOptions;

/**
 * Ably REST client
 */
class AblyRest {

    public $options;

    /**
     * The versioned identifier of this SDK family, sent as the first entry of
     * every `Ably-Agent` header.
     */
    const SDK_AGENT_IDENTIFIER = 'ably-pubsub-php';

    private function getAcceptHeader()
    {
        if($this->options->useBinaryProtocol)
            return 'application/x-msgpack';

        return 'application/json';
    }

    /**
     * Renders the value of the `Ably-Agent` request header for this client
     * (RSC7d): this SDK, the PHP runtime, then the client's `agents` entries
     * in the order they were given.
     *
     * An entry whose version is `null` or `''` renders as a bare identifier
     * with no `/`. That is not a fallback for a missing version, it is how the
     * ably-common registry declares flags — `ably-pubsub-server` among them —
     * and emitting `ably-pubsub-server/` instead would fail to classify.
     *
     * @return string
     */
    public function ablyAgentHeader()
    {
        $sdkIdentifier = self::SDK_AGENT_IDENTIFIER.'/'.Defaults::LIB_VERSION;
        $runtimeIdentifier = 'php/'.Miscellaneous::getNumeric(phpversion());
        $agentHeader = $sdkIdentifier.' '.$runtimeIdentifier;
        foreach($this->options->agents as $agentIdentifier => $agentVersion) {
            $agentHeader.= ' '.$agentIdentifier;
            if (!empty($agentVersion)) {
                $agentHeader.= '/'.$agentVersion;
            }
        }
        return $agentHeader;
    }
    /**
     * @var \Ably\PubSub\Http $http object for making HTTP requests
     */
    public $http;
    /**
     * @var \Ably\PubSub\Auth $auth object providing authorisation functionality
     */
    public $auth;
    /**
     * @var \Ably\PubSub\Channels $channels object for creating and releasing channels
     */
    public $channels;

    public $host;

    public $push;

    /**
     * Constructor.
     *
     * @internal Construct clients through the factory door,
     *   {@see \Ably\PubSub\Server::createHttpClient()}, which is the only
     *   documented entry point of this package. A client built by calling this
     *   constructor directly declares no side in its `Ably-Agent` header, and
     *   so does not qualify for the server exemption from monthly-active-user
     *   counting.
     *
     * @param \Ably\PubSub\Models\ClientOptions|array|string $options array with
     *   options, a ClientOptions instance, or a string with an app key or token
     */
    public function __construct( $options = [] ) {

        # convert to options if a single key or token string is provided
        $options = ClientOptions::normalizeConstructorArgument( $options );

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
     * @return \Ably\PubSub\Channel Channel
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
        return new PaginatedResult( $this, 'Ably\PubSub\Models\Stats', $cipher = false, 'GET', '/stats', $params );
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

    /**
     * Returns hosts in the order 1. Cached Host or Primary Host 2. Randomized Fallback Hosts
     * @return \Generator hosts
     */
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
            'Accept: ' . $this->getAcceptHeader(),
            'X-Ably-Version: ' .Defaults::API_VERSION,
            'Ably-Agent: ' .$this->ablyAgentHeader(),
        ], $headers );
        if ( $auth ) { // inject auth headers
            $mergedHeaders = array_merge( $this->auth->getAuthHeaders(), $mergedHeaders );
        }
        $attempt = 0;
        if(!in_array($method, ['GET', 'DELETE'], true) && !is_string($params)) {
            if($this->options->useBinaryProtocol) {
                if(is_object($params)){
                    Miscellaneous::deepConvertObjectToArray($params);
                }
                $params = MessagePack::pack($params, PackOptions::FORCE_STR);
            }
            else {
                $params = json_encode($params);
            }
        }

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

                $isServerError = $e->getStatusCode() >= 500 && $e->getStatusCode() <= 504; // RSC15d
                if ( $isServerError && $attempt < $maxPossibleRetries) {
                    $attempt += 1;
                } else {
                    $causedByExpiredToken = $auth && !$this->auth->isUsingBasicAuth()
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
     * RSC19 - This function is provided as a convenience for customers who wish to use REST API functionality that is
     * either not documented or is not included in the API for our client libraries.
     * The REST client library provides a function to issue HTTP requests to the Ably endpoints with all the built in
     * functionality of the library such as authentication, paging, fallback hosts, MsgPack and JSON support
     * @param string $method HTTP method (GET, POST, PUT, DELETE, PATCH, ...)
     * @param string $path root-relative path, e.g. /channels/example/messages
     * @param array $params GET parameters to append to $path
     * @param array|object $body JSON-encodable structure to send in the body - leave empty for GET requests
     * @param array $headers HTTP headers to send
     * @return \Ably\PubSub\Models\HttpPaginatedResponse
     * @throws AblyRequestException This exception is only thrown for status codes >= 500
     */
    public function request( $method, $path, $params = [], $body = '', $headers = []) {
        if ( count( $params ) ) {
            $path .= '?' . http_build_query( $params );
        }

        if ( $method == 'GET' && $body ) {
            throw new AblyException( 'GET requests cannot have a JSON body', 400, 40000 );
        }

        return new HttpPaginatedResponse( $this, 'Ably\PubSub\Models\Untyped', null, $method, $path, $body, $headers ); // RSC19d
    }

    // RTN17c
    function hasActiveInternetConnection() {
        $response = $this->http->get(Defaults::$internetCheckUrl);
        return $response["body"] == Defaults::$internetCheckOk;
    }
}
