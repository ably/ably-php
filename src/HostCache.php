<?php

namespace Ably;

// TODO - Add SyncMutex support to the class to avoid data corruption due to concurrent READ/WRITE (e.g. Apache multithreading environment)
class HostCache
{
    private $timeoutDurationInSec;
    private $expireTimeInSec;
    private $host = "";

    /**
     * @param $timeoutDurationInSec - $fallbackRetryTimeout in seconds
     */
    public function __construct($timeoutDurationInSec)
    {
        $this->timeoutDurationInSec = $timeoutDurationInSec;
    }

    public function put($host)
    {
        $this->host = $host;
        $this->expireTimeInSec = time() + $this->timeoutDurationInSec;
    }

    public function get()
    {
        if (empty($this->host) || time() > $this->expireTimeInSec) {
            return "";
        }
        return $this->host;
    }
}
