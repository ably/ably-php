<?php
namespace Ably\PubSub;

use Ably\PubSub\Models\ClientOptions;

/**
 * The Ably Pub/Sub SDK for servers.
 *
 * Servers are trusted environments which typically authenticate with an API
 * key, and whose connections are exempt from monthly-active-user counting.
 * This package names that side, so the client an application should reach for
 * is the one whose package matches where it runs.
 *
 * `createHttpClient()` is the entry point of the `ably/pubsub-server`
 * distribution and the only documented way to construct a client. PHP has no
 * realtime client, so there is no realtime door here and there is no stub for
 * one. The client it returns is the same REST client as before: channels,
 * history, presence, auth and push admin are unchanged.
 */
final class Server {

    /**
     * The agent identifier declaring the server side.
     *
     * The `-server` suffix is load-bearing, not cosmetic. On API-key auth the
     * realtime system grants the MAU server exemption by matching an agent
     * entry ending in `-server`, and an identifier that is not yet in the
     * ably-common registry is classified by that suffix alone. Renaming it
     * without preserving the suffix silently reclassifies every client this
     * package constructs.
     *
     * The entry is stamped WITHOUT a version, matching its registration in
     * the ably-common agents registry (a pure flag, like `browser`): a version
     * here would only duplicate the `ably-pubsub-php` entry beside it, which
     * already carries identity, version and support status. Wire shape:
     *   ably-pubsub-php/2.0.0 php/8.3.4 ably-pubsub-server
     */
    const SERVER_AGENT_IDENTIFIER = 'ably-pubsub-server';

    /**
     * Not instantiable: this class is a door, not a room.
     */
    private function __construct() {}

    /**
     * Creates a stateless HTTP (REST) client declaring the server side.
     *
     * Accepts everything the client constructor accepts: an options array, a
     * ClientOptions instance, or a string holding an API key or a token.
     *
     * @param \Ably\PubSub\Models\ClientOptions|array|string $options
     * @return \Ably\PubSub\AblyRest
     */
    public static function createHttpClient( $options = [] ) {
        return new AblyRest( self::withSideAgent( $options ) );
    }

    /**
     * Returns the caller's options carrying the agent entry that declares this
     * package's side.
     *
     * The caller's own `agents` entries are preserved in order, so an SDK
     * layered on top of this package keeps its attribution. The side entry is
     * merged last and so wins a collision on its own identifier: which side
     * the package declares is the package's to state, not the caller's to
     * redefine.
     *
     * A ClientOptions instance is copied rather than mutated, so passing the
     * same instance to the door twice does not accumulate state on the
     * caller's object. Anything that is neither a string, an array nor a
     * ClientOptions passes through untouched, so the caller gets the
     * constructor's own error rather than a vaguer failure here.
     *
     * @param \Ably\PubSub\Models\ClientOptions|array|string $options
     * @return \Ably\PubSub\Models\ClientOptions|array|mixed
     */
    private static function withSideAgent( $options ) {
        $options = ClientOptions::normalizeConstructorArgument( $options );

        $sideAgent = [ self::SERVER_AGENT_IDENTIFIER => null ];

        if ( $options instanceof ClientOptions ) {
            $options = clone $options;
            $options->agents = array_merge( $options->agents ?: [], $sideAgent );

            return $options;
        }

        if ( is_array( $options ) ) {
            $callerAgents = isset( $options['agents'] ) ? $options['agents'] : [];
            $options['agents'] = array_merge( $callerAgents, $sideAgent );

            return $options;
        }

        return $options;
    }
}
