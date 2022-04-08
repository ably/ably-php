<?php
namespace Ably;

class Host {

}

// TODO - Add SyncMutex support to the class to avoid data corruption due to concurrent READ/WRITE (e.g. Apache multithreading environment)
class HostCache {
    private $timeoutDuration;
    private $expireTimeInSec;
    private $host = "";

    /**
     * @param $timeoutDuration
     * @param $expireTimeInMs
     * @param string $host
     */
    public function __construct($timeoutDuration, $expireTimeInMs)
    {
        $this->timeoutDuration = $timeoutDuration;
        $this->expireTimeInSec = $expireTimeInMs / 1000;
    }

    public function put($host) {
        $this->host = $host;
        $this->expireTimeInSec = time() + $this->timeoutDuration;
    }

    public function get() {
        if (empty($this->host) || time() > $this->expireTimeInSec) {
            return "";
        }
        return $this->host;
    }
}
