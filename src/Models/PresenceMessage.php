<?php
namespace Ably\Models;

class PresenceMessage extends BaseMessage {

    /**
     * @var int A unique id for the client associated with the update, to disambiguate in the case
     * that a single clientId is simultaneously present multiple times (eg on different connections).
     * Populated by the server.
     */
    public $memberId;
    /**
     * @var int The timestamp for this message. Populated by the server.
     */
    public $action;

    /**
     * @var int Numeric id of the 'join' action
     */
    const JOIN  = 0;
    /**
     * @var int Numeric id of the 'leave' action
     */
    const LEAVE = 1;
    /**
     * @var int Numeric id of the 'update' action
     */
    const UPDATE = 2;
}