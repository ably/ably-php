<?php

class Channel {
    public $name;

    public function __construct($name) {
        $this->name = $name;
        $this->domain = "/channels/{$name}";
    }
}