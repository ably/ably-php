# Ably realtime PHP Client Library SDK: Roadmap

This document outlines our plans for the evolution of this SDK.

## Milestone 1: Realtime Channel Subscription ❌

Once we've completed the scope and objectives detailed in this milestone,
we'll be in a good position to make a release in order to start getting feedback from customers.

That release will allow applications built against it to:

- Create a persistent Realtime connection to the Ably service
- Subscribe to Ably channels in order to receive messages over that connection

### Milestone 1a: Solidify Existing Foundations ❌

Ensure the current source code is in a good enough state to build upon.
This means solving currently known pain points (development environment stabilisation) as well as reassessing our baselines.

**Scope**:

- Resolve issues with dependency pinning.
- Ensure standard linter is available and checks are run on CI.
- Check language and runtime requirements.
- Possibly create a new git repository setup to avoid execution model conflicts with existing rest-library( blocking client). existing rest-library will be mounted as a submodules in the new repository for php realtime lib.
- Since, `ably-php` rest only lib is heavily used at server side, we don't want realtime specific dependencies to be a part of rest-dependencies.
- Some of this scope of work can overlap with milestone 1b.

**Objective**: Achieve confidence that we have foundations we can confidently build upon, knowing what's coming up in future milestones.

### Milestone 1b: Establish Realtime Foundations and Connect ❌

**Scope**:

- pick a WebSocket library
- pick an event model (async/await vs dedicated thread)
- establish connection with basic credentials (Ably API key passed in through Authorization header)
  - triggering on explicit call to `client.connect()` rather than autoConnect

**Objective**: Successfully connect to Ably Realtime.

### Milestone 1c: Realtime Connection Lifecycle ❌

The basic foundations of Realtime connectivity, plus client identification (`Agent`).

**Scope**:

- send `Ably-Agent` header when establishing WebSocket connection ([`RSC7d2`](https://docs.ably.io/client-lib-development-guide/features/#RSC7d2))
- loop to read protocol messages from the WebSocket
- handle basic connectivity messages: `CONNECTED`, `DISCONNECTED`, `CLOSED`, `ERROR`
- handle `HEARTBEAT` messages
- Connection state machine
- queryable connection state
  - consider whether there is a php-idiomatic alternative to blindly implementing `EventEmitter`

**Objective**: Track connection state and offer API to query it.

### Milestone 1d: Basic Realtime-Client-initiated Messages ❌

Give our users some control.

**Scope**:

- client to service `CLOSE` ([`RTC16`](https://docs.ably.io/client-lib-development-guide/features/#RTC16))
- ping ([`RTN13`](https://docs.ably.io/client-lib-development-guide/features/#RTN13))
  - loop to read messages from user
  - send a ping (`HEARTBEAT`)
  - wait for a response (`HEARTBEAT`)
  - callback to user with timing info

**Objective**: Provide APIs for sending basic messages to the service,
resulting in proof-of-life / smoke-test proving interactions with the event model chosen in [1b](#milestone-1b-establish-realtime-foundations-and-connect).

### Milestone 1e: Attach and Subscribe ❌

Start receiving messages from the Ably service.

**Scope**:

- channels, including:
  - Channels.get ([`RTS3c`](https://docs.ably.io/client-lib-development-guide/features/#RTS3c))
  - Channels.release ([`RTS34`](https://docs.ably.io/client-lib-development-guide/features/RTS34))
  - RealtimeChannel state machine
  - attach ([`RTL4`](https://docs.ably.io/client-lib-development-guide/features/#RTL4))
  - detach ([`RTL5`](https://docs.ably.io/client-lib-development-guide/features/#RTL5))
  - subscribe ([`RTL7`](https://docs.ably.io/client-lib-development-guide/features/#RTL7)) / unsubscribe ([`RTL8`](https://docs.ably.io/client-lib-development-guide/features/#RTL8))
    - consider whether there is a php-idiomatic alternative to blindly implementing `EventEmitter`

**Objective**: Receive application level messages from the network.

## Milestone 2: Realtime Connectivity Hardening ❌

This milestone will add connection error handling to the realtime client,
allowing it to continue operating in the event of a recoverable connection error.
It will also improve the visibility of what went wrong in the event of a fatal connection error.

### Milestone 2a: Handle connection opening errors ❌

Implement the correct behaviour for all potential errors that may occur when establishing a new realtime connection.

**Scope**:

- Implement configurable `realtimeRequestTimeout` and transition to `DISCONNECTED` if the initial `CONNECTED` message is not received in time ([`RTN14c`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN14c))
- Populate the `Connection.errorReason` field when a connection error is encountered ([`RTN14a`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN14a))
- Transition to `DISCONNECTED` upon recoverable errors as defined by [`RTN14d`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN14d) (network failure, disconnected response)

**Objective**: Achieve confidence that the library has defined behaviour for all errors it may encounter upon establishing a realtime connection.

### Milestone 2b: Retry failed connection attempts ❌

Attempt to re-establish connection upon a recoverable connection attempt failure and give users visibility of the connection state when the library is doing so.

**Scope**:

- Implement configurable `disconnectedRetryTimeout` and retry connection periodically while the connection state is `DISCONNECTED` ([`RTN14d`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN14d))
- Implement configurable `connectionStateTtl` and transition connection to `SUSPENDED` when `connectionStateTtl` is exceeded ([`RTN14e`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN14e))
- Fallback hosts are outside of the scope of this milestone: each retry should be against the primary realtime endpoint
- Incremental backoff and jitter is outside of the scope of this milestone

**Objective**: Allow the library to re-establish connection in the event of a recoverable connection opening failure.

### Milestone 2c: Use fallback hosts ❌

Use fallback hosts in the case of a connection error, allowing the library to still connect to Ably when connection to the primary host is unavailable.

**Scope**:

- Implement the `fallbackHosts` client option ([`RTN17b2`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN17b2))
- Use a new fallback host when encountering an appropriate error ([`RTN17d`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN17d))
- Implement connectivity check and check connectivity before using a new fallback host ([`RTN17c`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN17c))

**Objective**: Make the realtime client resilient when one or more realtime endpoints are unavailable.

### Milestone 2d: Handle connection errors once connected ❌

Handle errors which the realtime client may encounter once already in the `CONNECTED` state, resuming the connection and reattaching to channels when appropriate.

**Scope**:

- Implement `maxIdleInterval` and handle `HEARTBEAT` messages and disconnect transport once `maxIdleInterval` is exceeded ([`RTN23`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN23))
- Handle `CONNECTED` messages once connected ([`RTN24`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN24))
- Resend protocol messages for pending channels upon resume ([`RTN19b`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN19b))
- When `connectionStateTtl` elapsed, clear connection state ([`RTN15g`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN15g))
- Immediately reattempt connection when unexpectedly disconnected ([`RTN15a`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN15a))
- Connection resume:
    - Send resume query param when reconnecting within `connectionStateTtl` ([`RTN15b`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN15b))
    - Handle clean resume response ([`RTN15c6`](https://sdk.ably.com/builds/ably/specification/pull/108/features/#RTN15c6), [`RTL4c`](https://sdk.ably.com/builds/ably/specification/main/features/#RTL4c), [`RTN15e`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN15e))
    - Handle invalid resume response ([`RTN15c7`](https://sdk.ably.com/builds/ably/specification/pull/108/features/#RTN15c7))
    - Handle fatal resume error ([`RTN15c4`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN15c4))
- Set the `ATTACH_RESUME` flag on unclean attach ([`RTL4j`](https://sdk.ably.com/builds/ably/specification/main/features/#RTL4j))
- Emit `update` event on additional `ATTACHED` message ([`RTL12`](https://sdk.ably.com/builds/ably/specification/main/features/#RTL12))

**Objective**: Detect connection errors while connected and handle them appropriately.

## Milestone 3: Token Authentication ❌

This milestone will add token-based authentication to the realtime client.

### Milestone 3a: Enable token-based authentication and re-authentication ❌

Implement the expected behavior for successful token-based authentication and re-authentication.

**Scope**:

- Allow token auth methods for realtime constructor ([`RTC4`](https://sdk.ably.com/builds/ably/specification/main/features/#RTC4), [`RTC8`](https://sdk.ably.com/builds/ably/specification/main/features/#RTC8))
- Send `AUTH` protocol message when `Auth.authorize` called on realtime client ([`RTC8`](https://sdk.ably.com/builds/ably/specification/main/features/#RTC8), [`RSA3c`](https://sdk.ably.com/builds/ably/specification/main/features/#RSA3c), [`RSA3d`](https://sdk.ably.com/builds/ably/specification/main/features/#RSA3d))
- Reauth upon inbound `AUTH` protocol message ([`RTN22`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN22), [`RTC8a`](https://sdk.ably.com/builds/ably/specification/main/features/#RTC8a), [`RTC8a1`](https://sdk.ably.com/builds/ably/specification/main/features/#RTC8a1))

**Objective**: Create functionality that will allow the client to authenticate with Ably via tokens.

### Milestone 3b: Error scenarios ❌

Implement the correct handling of edge cases when there are connectivity issues or authentication errors during token-based authentication.

**Scope**:

- Handle connection request failure due to token error ([`RTN14b`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN14b), [`RSA4a`](https://sdk.ably.com/builds/ably/specification/main/features/#RSA4a))
- Handle `DISCONNECTED` messages containing token errors ([`RTN15h`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN15h), [`RTN15h1`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN15h1), [`RTN15h2`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN15h2), [`RTN22a`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN22a))
- Handle token `ERROR` response to a resume request ([`RTN15c5`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN15c5), [`RTN15h`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN15h))

**Objective**: Display the correct errors and place client in expected state during error scenarios that may arise during authentication process.

### Milestone 3c: Client ID ❌

Properly handle and set `clientId` attribute during token-based authentication.

**Scope**:

- Apply `Auth#clientId` only after a realtime connection has been established ([`RTC4a`](https://sdk.ably.com/builds/ably/specification/main/features/#RTC4a), [`RSA7b3`](https://sdk.ably.com/builds/ably/specification/main/features/#RSA7b3), [`RSA7b4`](https://sdk.ably.com/builds/ably/specification/main/features/#RSA7b4))
- Validate `clientId` in `ClientOptions` ([`RSA15`](https://sdk.ably.com/builds/ably/specification/main/features/#RSA15))
- Pass `clientId` as query string param when opening a new connection ([`RTN2d`](https://sdk.ably.com/builds/ably/specification/main/features/#RTN2d))

**Objective**: Ensure `clientId` is set after authentication so that it can be used for follow-on development of realtime functionality.

## Milestone 4: Realtime Channel Publish

_T.B.D._

## Milestone 5: Realtime Channel Presence

_T.B.D._
