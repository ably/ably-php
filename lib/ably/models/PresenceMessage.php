<?php
require_once dirname(__FILE__) . '/../AblyExceptions.php';

class PresenceMessage {

    /**
     * @var int The id of the client associated with the update.
     */
    public $clientId;
    /**
     * @var int A unique id for the client associated with the update, to disambiguate in the case
     * that a single clientId is simultaneously present multiple times (eg on different connections).
     */
    public $memberId;
    /**
     * @var mixed An optional message payload for a status line or other data associated with the presence update. The data may be one of the supported payload datatypes.
     */
    public $data;
    /**
     * @var int|null The timestamp for this message. Populated by the server.
     */
    public $timestamp;
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


    /**
     * Creates a JSON representation of this object, ready to be sent to the API.
     */
    public function toJSON() {
        $data = new stdClass();

        $fields = get_object_vars( $this );

        foreach ($fields as $key => $value) {
            $data->$key = $value;
        }

        return json_encode( $data );
    }

    /**
     * Populates the data from JSON
     * @param string|stdClass $json JSON string or an already decoded object.
     * @throws AblyException
     */
    public function fromJSON( $json ) {
        $this->clearFields();

        if (is_object( $json )) {
            $obj = $json;
        } else {
            $obj = @json_decode($json);
            if (!$obj) {
                throw new AblyException( 'Invalid object or JSON encoded object', 400, 40000 );
            }
        }

        foreach ($obj as $key => $value) {
            if (property_exists( __CLASS__, $key )) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Sets all the public fields to null
     */
    protected function clearFields() {
        $fields = get_object_vars( $this );

        foreach ($fields as $key => $value) {
            $this->$key = null;
        }
    }
}