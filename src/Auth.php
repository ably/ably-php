<?php
namespace Ably;

use Ably\AblyRest;
use Ably\Log;
use Ably\Models\AuthOptions;
use Ably\Models\ClientOptions;
use Ably\Models\TokenDetails;
use Ably\Models\TokenParams;
use Ably\Models\TokenRequest;
use Ably\Exceptions\AblyException;

/**
 * Provides authentification methods for AblyRest instances
 */
class Auth {
    private $authOptions;
    private $basicAuth;
    private $tokenDetails;
    private $ably;

    public function __construct( AblyRest $ably, ClientOptions $options ) {
        $this->authOptions = new AuthOptions($options);
        $this->ably = $ably;

        if ( $this->authOptions->key && empty( $this->authOptions->clientId ) ) {
            $this->basicAuth = true;
            Log::d( 'Auth: anonymous, using basic auth' );

            if ( !$options->tls ) {
                log::e( 'Trying to use basic key auth over unsecure connection' );
                throw new AblyException ( 'Trying to use basic key auth over unsecure connection', 401, 40103 );
            }
            return;
        }

        $this->basicAuth = false;

        if(!empty( $this->authOptions->authCallback )) {
            Log::d( 'Auth: using token auth with authCallback' );
        } else if(!empty( $this->authOptions->authUrl )) {
            Log::d( 'Auth: using token auth with authUrl' );
        } else if(!empty( $this->authOptions->key )) {
            Log::d( 'Auth: using token auth with client-side signing' );
        } else if(!empty( $this->authOptions->tokenDetails )) {
            Log::d( 'Auth: using token auth with supplied token only' );
        } else {
            /* this is not a hard error - but any operation that requires
             * authentication will fail */
            Log::w( 'Auth: no authentication parameters supplied' );
        }
    }

    public function isUsingBasicAuth() {
        return $this->basicAuth;
    }

    /**
     * Ensures valid auth credentials are present for the library instance. This may rely on an already-known and valid token, and will obtain a new token if necessary.
     * In the event that a new token request is made, the specified options are used.
     */
    public function authorise( $options = array(), $force = false ) {
        if ( !$force && !empty( $this->tokenDetails ) ) {
            if ( empty( $this->tokenDetails->expires ) ) {
                // using cached token
                Log::d( 'Auth::authorise: using cached token, unknown expiration time' );
                return $this;
            } else if ( $this->tokenDetails->expires > $this->ably->time() ) {
                // using cached token
                Log::d( 'Auth::authorise: using cached token, expires on ' . date( 'Y-m-d H:i:s', $this->tokenDetails->expires / 1000 ) );
                return $this;
            }
        }
        Log::d( 'Auth::authorise: requesting new token' );
        $this->tokenDetails = $this->requestToken( $options );
        $this->authOptions->tokenDetails = $this->tokenDetails;

        return $this;
    }

    /**
     * Get HTTP headers with authentication data
     * Automatically attempts to authorise token requests
     */
    public function getAuthHeaders() {
        $header = array();
        if ( $this->isUsingBasicAuth() ) {
            $header = array( 'authorization: Basic ' . base64_encode( $this->authOptions->key ) );
        } else if ( !empty( $this->tokenDetails ) ) {
            $this->authorise();
            $header = array( 'authorization: Bearer '. base64_encode( $this->tokenDetails->token ) );
        } else {
            throw new AblyException( 'Unable to provide auth headers. No auth parameters defined.' );
        }
        return $header;
    }

    /**
     * @return \Ably\Models\TokenDetails Token currently in use
    */
    public function getTokenDetails() {
        return $this->tokenDetails;
    }

    /**
     * Request a new Token
     * @param array|null $authOptions Overridable auth options, if you don't wish to use the default ones
     * @param array|null $tokenParams Requested token parameters
     * @param \Ably\Models\ClientOptions|array $options
     * @throws \Ably\Exceptions\AblyException
     */
    public function requestToken( $authOptions = array(), $tokenParams = array() ) {

        // merge provided auth options with defaults
        $authOptions = new AuthOptions( array_merge( $this->authOptions->toArray(), $authOptions ) );
        $tokenParams = new TokenParams( $tokenParams );

        if ( empty( $tokenParams->clientId ) ) {
            $tokenParams->clientId = $authOptions->clientId;
        }

        // get a signed token request
        $signedTokenRequest = null;
        if ( !empty( $authOptions->authCallback ) ) {
            Log::d( 'Auth::requestToken:', 'using token auth with auth_callback' );
            
            $callback = $authOptions->authCallback;
            $data = $callback($tokenParams);

            // returned data can be either a signed TokenRequest or TokenDetails or just a token string
            if ( is_a( $data, '\Ably\Models\TokenRequest' ) ) {
                $signedTokenRequest = $data;
            } else if ( is_a( $data, '\Ably\Models\TokenDetails' ) ) {
                return $data;
            } else if ( is_string( $data ) ) {
                return new TokenDetails( $data );
            } else {
                Log::e( 'Auth::requestToken:', 'Invalid response from authCallback, expecting signed TokenRequest or TokenDetails or a token string' );
                throw new AblyException( 'Invalid response from authCallback', 400, 40000 );
            }
        } elseif ( !empty( $authOptions->authUrl ) ) {
            Log::d( 'Auth::requestToken:', 'using token auth with auth_url' );

            $data = $this->ably->http->request(
                $authOptions->authMethod,
                $authOptions->authUrl,
                $authOptions->authHeaders ? : array(),
                array_merge( $authOptions->authParams ? : array(), $tokenParams->toArray() )
            );
            
            $data = $data['body'];

            if ( !is_object( $data ) ) {
                Log::e( 'Auth::requestToken:', 'Invalid response from authURL, expecting JSON' );
                throw new AblyException( 'Invalid response from authURL', 400, 40000 );
            }

            if ( !empty( $data->issued ) ) { // assuming it's a token
                return new TokenDetails( $data );
            } else if ( !empty( $data->mac ) ) { // assuming it's a signed token request
                $signedTokenRequest = new TokenRequest( $data );
            } else {
                Log::e( 'Auth::requestToken:', 'Invalid response from authURL, expecting JSON representation of signed TokenRequest or a Token' );
                throw new AblyException( 'Invalid response from authURL', 400, 40000 );
            }
        } elseif ( !empty( $authOptions->key ) ) {
            Log::d( 'Auth::requestToken:', 'using token auth with client-side signing' );
            $signedTokenRequest = $this->createTokenRequest( $authOptions, $tokenParams );
        } else {
            Log::e( 'Auth::requestToken:', 'Unable to request a Token, options must include valid authentication parameters' );
            throw new AblyException( 'Invalid auth parameters', 400, 40000 );
        }

        // do the request

        $keyName = $signedTokenRequest->keyName;

        if ( empty( $keyName ) ) {
            throw new AblyException( 'No keyName specified in the TokenRequest', 400, 40000 );
        }
        
        $res = $this->ably->post(
            "/keys/{$keyName}/requestToken",
            $headers = array(),
            $params = json_encode( $signedTokenRequest->toArray() ),
            $returnHeaders = false,
            $authHeaders = false
        );

        if ( empty( $res->token ) ) { // just in case.. an AblyRequestException should be thrown on the previous step with a 4XX error code on failure
            throw new AblyException( 'Failed to get a token', 401, 40100 );
        }

        return new TokenDetails( $res );
    }

    /**
     * Create a signed token request based on known credentials
     * and the given token params. This would typically be used if creating
     * signed requests for submission by another client.
     * @param \Ably\Models\AuthOptions $authOptions
     * @param \Ably\Models\TokenParams $tokenParams
     */
    public function createTokenRequest( $authOptions = array(), $tokenParams = array() ) {
        $keyParts = explode( ':', $authOptions->key );
        
        if ( count( $keyParts ) != 2 ) {
            Log::e( 'Auth::createTokenRequest', "Can't create signed token request, invalid key specified" );
            throw new AblyException( 'Invalid key specified', 401, 40101 );
        }
        
        $keyName   = $keyParts[0];
        $keySecret = $keyParts[1];
        
        $tokenRequest = new TokenRequest( $tokenParams );
        
        if ( !empty( $tokenRequest->keyName ) && $tokenRequest->keyName != $keyName ) {
            throw new AblyException( 'Incompatible keys specified', 401, 40102 );
        } else {
            $tokenRequest->keyName = $keyName;
        }
        
        if ( $tokenRequest->queryTime ) {
            $tokenRequest->timestamp = $this->ably->time();
        } else if ( empty( $tokenRequest->timestamp ) ) {
            $tokenRequest->timestamp = $this->ably->systemTime();
        }
        
        if ( empty( $tokenRequest->clientId ) ) {
            $tokenRequest->clientId = $authOptions->clientId;
        }

        if ( empty( $tokenRequest->nonce ) ) {
            $tokenRequest->nonce = md5( microtime( true ) . mt_rand() );
        }

        $signText = implode("\n", array(
            empty( $tokenRequest->keyName )    ? '' : $tokenRequest->keyName,
            empty( $tokenRequest->ttl )        ? '' : $tokenRequest->ttl,
            empty( $tokenRequest->capability ) ? '' : $tokenRequest->capability,
            empty( $tokenRequest->clientId )   ? '' : $tokenRequest->clientId,
            empty( $tokenRequest->timestamp )  ? '' : $tokenRequest->timestamp,
            empty( $tokenRequest->nonce )      ? '' : $tokenRequest->nonce,
        )) . "\n";


        if ( empty( $tokenRequest->mac ) ) {
            $tokenRequest->mac = base64_encode( hash_hmac( 'sha256', $signText, $keySecret, true ) );
        }

        return $tokenRequest;
    }
}
