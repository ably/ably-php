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
    const TOKEN_EXPIRY_MARGIN = 15000; // a token is considered expired a bit earlier to prevent race conditions

    public function __construct( AblyRest $ably, ClientOptions $options ) {
        $this->authOptions = new AuthOptions($options);
        $this->ably = $ably;

        if ( empty( $this->authOptions->useTokenAuth ) && $this->authOptions->key && empty( $this->authOptions->clientId ) ) {
            $this->basicAuth = true;
            Log::d( 'Auth: anonymous, using basic auth' );

            if ( !$options->tls ) {
                log::e( 'Auth: trying to use basic key auth over insecure connection' );
                throw new AblyException ( 'Trying to use basic key auth over insecure connection', 40103, 401 );
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
            Log::e( 'Auth: no authentication parameters supplied' );
            throw new AblyException ( 'No authentication parameters supplied', 40103, 401 );
        }

        $this->tokenDetails = $this->authOptions->tokenDetails;

        if ( $this->authOptions->clientId == '*' ) {
            throw new AblyException ( 'Reserved name `*` used as a clientId. If you wish to use anonymous auth, please leave the clientId unset.', 40003, 400 );
        }
    }

    public function isUsingBasicAuth() {
        return $this->basicAuth;
    }

    /**
     * Ensures that a valid token is present for the library instance. This may rely on an already-known and valid token,
     * and will obtain a new token if necessary.
     * In the event that a new token request is made, the specified options are used.
     * If not already using token based auth, this will enable it.
     * @param array|null $tokenParams Requested token parameters
     * @param array|null $authOptions Overridable auth options, if you don't wish to use the default ones
     * @param boolean|null $force Forces generation of a fresh token
     * @return \Ably\Models\TokenDetails The new token
     */
    public function authorise( $tokenParams = array(), $authOptions = array(), $force = false ) {
        if ( !$force && !empty( $this->tokenDetails ) ) {
            if ( empty( $this->tokenDetails->expires ) ) {
                // using cached token
                Log::d( 'Auth::authorise: using cached token, unknown expiration time' );
                return $this->tokenDetails;
            } else if ( $this->tokenDetails->expires - self::TOKEN_EXPIRY_MARGIN > $this->ably->systemTime() ) {
                // using cached token
                Log::d( 'Auth::authorise: using cached token, expires on ' . date( 'Y-m-d H:i:s', $this->tokenDetails->expires / 1000 ) );
                return $this->tokenDetails;
            }
        }

        Log::d( 'Auth::authorise: requesting new token' );
        $this->tokenDetails = $this->requestToken( $tokenParams, $authOptions );
        $this->authOptions->tokenDetails = $this->tokenDetails;
        $this->basicAuth = false;

        return $this->tokenDetails;
    }

    /**
     * Get HTTP headers with authentication data
     * Automatically attempts to authorise token requests
     * @return Array Array of HTTP headers containing an `Authorization` header
     */
    public function getAuthHeaders() {
        $header = array();
        if ( $this->isUsingBasicAuth() ) {
            $header = array( 'Authorization: Basic ' . base64_encode( $this->authOptions->key ) );
        } else {
            $this->authorise();
            $header = array( 'Authorization: Bearer '. base64_encode( $this->tokenDetails->token ) );
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
     * @return string|null Library instance's clientId, if instanced with a clientId
    */
    public function getClientId() {
        if ( empty( $this->tokenDetails ) ) {
            if ( !empty( $this->authOptions->clientId ) ) {
                return $this->authOptions->clientId;
            }
        } else {
            if ( !empty( $this->tokenDetails->clientId ) && $this->tokenDetails->clientId != '*' ) {
                return $this->tokenDetails->clientId;
            }
        }

        return null;
    }

    /**
     * Request a new token.
     * @param array|null $tokenParams Requested token parameters
     * @param array|null $authOptions Overridable auth options, if you don't wish to use the default ones
     * @param \Ably\Models\ClientOptions|array $options
     * @throws \Ably\Exceptions\AblyException
     * @return \Ably\Models\TokenDetails The new token
     */
    public function requestToken( $tokenParams = array(), $authOptions = array() ) {
        // infer the clientId from default authOptions - if null, use '*'
        $tokenClientId = empty( $this->authOptions->clientId ) ? '*' : $this->authOptions->clientId;
        // provided authOptions may override inferred clientId, even with a null value
        if ( array_key_exists( 'clientId', $authOptions ) ) $tokenClientId = $authOptions['clientId'];
        // provided tokenParams may override inferred as well as authOptions clientId, even with a null value
        if ( array_key_exists( 'clientId', $tokenParams ) ) $tokenClientId = $tokenParams['clientId'];

        // merge provided auth options with defaults
        $authOptions = new AuthOptions( array_merge( $this->authOptions->toArray(), $authOptions ) );
        $tokenParams = new TokenParams( $tokenParams );
        
        $tokenParams->clientId = $tokenClientId;

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
                throw new AblyException( 'Invalid response from authCallback' );
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

            if ( is_string( $data ) ) {
                return new TokenDetails( $data ); // assuming it's a token string
            } else if ( is_object( $data ) ) {
                if ( !empty( $data->issued ) ) { // assuming it's a token
                    return new TokenDetails( $data );
                } else if ( !empty( $data->mac ) ) { // assuming it's a signed token request
                    $signedTokenRequest = new TokenRequest( $data );
                } else {
                    Log::e( 'Auth::requestToken:', 'Invalid response from authURL, expecting JSON representation of signed TokenRequest or TokenDetails' );
                    throw new AblyException( 'Invalid response from authURL' );
                }
            } else {
                Log::e( 'Auth::requestToken:', 'Invalid response from authURL, expecting token string or JSON representation of signed TokenRequest or TokenDetails' );
                throw new AblyException( 'Invalid response from authURL' );
            }
        } elseif ( !empty( $authOptions->key ) ) {
            Log::d( 'Auth::requestToken:', 'using token auth with client-side signing' );
            $signedTokenRequest = $this->createTokenRequest( $tokenParams->toArray(), $authOptions->toArray() );
        } else {
            Log::e( 'Auth::requestToken:', 'Unable to request a Token, auth options don\'t provide means to do so' );
            throw new AblyException( 'Unable to request a Token, auth options don\'t provide means to do so', 40101, 401 );
        }

        // do the request

        $keyName = $signedTokenRequest->keyName;

        if ( empty( $keyName ) ) {
            throw new AblyException( 'No keyName specified in the TokenRequest' );
        }
        
        $res = $this->ably->post(
            "/keys/{$keyName}/requestToken",
            $headers = array(),
            $params = json_encode( $signedTokenRequest->toArray() ),
            $returnHeaders = false,
            $authHeaders = false
        );

        if ( empty( $res->token ) ) { // just in case.. an AblyRequestException should be thrown on the previous step with a 4XX error code on failure
            throw new AblyException( 'Failed to get a token', 40100, 401 );
        }

        return new TokenDetails( $res );
    }

    /**
     * Create a signed token request based on known credentials
     * and the given token params. This would typically be used if creating
     * signed requests for submission by another client.
     * @param \Ably\Models\TokenParams $tokenParams
     * @param \Ably\Models\AuthOptions $authOptions
     * @return \Ably\Models\TokenRequest A signed token request
     */
    public function createTokenRequest( $tokenParams = array(), $authOptions = array() ) {
        $authOptions = new AuthOptions( array_merge( $this->authOptions->toArray(), $authOptions ) );
        $tokenParams = new TokenParams( $tokenParams );
        $keyParts = explode( ':', $authOptions->key );
        
        if ( count( $keyParts ) != 2 ) {
            Log::e( 'Auth::createTokenRequest', "Can't create signed token request, invalid key specified" );
            throw new AblyException( 'Invalid key specified', 40101, 401 );
        }
        
        $keyName   = $keyParts[0];
        $keySecret = $keyParts[1];
        
        $tokenRequest = new TokenRequest( $tokenParams );
        
        if ( !empty( $tokenRequest->keyName ) && $tokenRequest->keyName != $keyName ) {
            throw new AblyException( 'Incompatible keys specified', 40102, 401 );
        } else {
            $tokenRequest->keyName = $keyName;
        }
        
        if ( $authOptions->queryTime ) {
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
