<?php
namespace Ably\Models;

use Ably\Log;

/**
 * Client library options
 */
class AuthOptions extends BaseOptions {

    /**
     * @var string|null The application id. This option is only required if the application id cannot be inferred either from a key or token option.
     * If given, it is the application id as indicated on the application dashboard.
     */
    public $appId;
    
    /**
     * @var string|null A client id, used for identifying this client for presence purposes.
     * The clientId can be any string. This option is primarily intended to be used in situations where the library is instanced with a key;
     * note that a clientId may also be implicit in a token used to instance the library; an error will be raised if a clientId specified here conflicts with the clientId implicit in the token.
     */
    public $clientId;

    /**
     * @var string|null The full key string, as obtained from the application dashboard.
     * Use this option if you wish to use Basic authentication, or wish to be able to issue tokens without needing to defer to a separate entity to sign token requests.
     */
    public $key;
    
    /**
     * @var \Ably\Models\TokenDetails|null Token that should be used for authentificating all requests
     */
    public $tokenDetails;

    /**
     * @var function|null A function to call when a new token is required.
     * The role of the callback is to generate a signed token request which may then be submitted by the library to the requestToken API.
     * See authentication for details of the token request format and associated API calls.
     */
    public $authCallback;

    /**
     * @var string|null A URL that the library may use to obtain a signed token request.
     * For example, this can be used by a client to obtain signed token requests from an application server.
     */
    public $authUrl;

    /**
     * @var array|null A set of headers to be added to any request made to the authUrl.
     * Useful when an application requires these to be added to validate the request or implement the response.
     */
    public $authHeaders;

    /**
     * @var array|null A set of parameters to be submitted to any request made to the authUrl.
     */
    public $authParams;

    /**
     * @var array|null HTTP method to use with authUrl, defaults to GET
     */
    public $authMethod = 'GET';
}