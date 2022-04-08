<?php
namespace Ably;

class Host {

}

// TODO - Add SyncMutex support to the class to avoid data corruption due to concurrent READ/WRITE (e.g. Apache multithreading environment)
class HostCache {
    private $timeoutDurationInSec;
    private $expireTimeInSec;
    private $host = "";

    /**
     * @param $timeoutDurationInMs - $fallbackRetryTimeout in milliseconds
     */
    public function __construct($timeoutDurationInMs)
    {
        $this->timeoutDurationInSec = $timeoutDurationInMs / 1000;
    }

    public function put($host) {
        $this->host = $host;
        $this->expireTimeInSec = time() + $this->timeoutDurationInSec;
    }

    public function get() {
        if (empty($this->host) || time() > $this->expireTimeInSec) {
            return "";
        }
        return $this->host;
    }
}
