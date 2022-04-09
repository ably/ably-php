<?php
namespace Ably;

class Host {
    private $clientOptions;
    private $hostCache;

    /**
     * @param $clientOptions
     */
    public function __construct($clientOptions)
    {
        $this->clientOptions = $clientOptions;
        $this->hostCache = new HostCache($clientOptions->fallbackRetryTimeout / 1000);
    }

    public function fallbackHosts($currentHost) {
        $primaryHost = $this->clientOptions->getPrimaryRestHost();
        if ($currentHost != $primaryHost) {
            yield $primaryHost;
        }
        foreach ($this->clientOptions->getFallbackHosts() as $fallbackHost) {
            if ($currentHost != $fallbackHost) {
                yield $fallbackHost;
            }
        }
    }

    // getPreferredHost - Used to retrieve host in the order 1. Cached host 2. primary host
    public function getPreferredHost() {
        $host = $this->hostCache->get();
        if (empty($host)) {
            return $this->clientOptions->getPrimaryRestHost();
        }
        return $host;
    }

    public function setPreferredHost($host) {
        $this->hostCache->put($host);
    }

}

