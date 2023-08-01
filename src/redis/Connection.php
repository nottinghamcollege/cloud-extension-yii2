<?php

namespace craft\cloud\redis;

use craft\cloud\Module;
use League\Uri\UriString;

class Connection extends \yii\redis\Connection
{
    public ?string $url = null;

    public function __construct($config = [])
    {
        $config += [
            'url' => Module::getInstance()->getConfig()->redisUrl,
        ];

        parent::__construct($config);
    }

    public function open(): void
    {
        if ($this->url) {
            $urlComponents = UriString::parse($this->url);
            $this->scheme = $urlComponents['scheme'] ?? $this->scheme;
            $this->username = $urlComponents['user'] ?? $this->username;
            $this->password = $urlComponents['pass'] ?? $this->password;
            $this->hostname = $urlComponents['host'] ?? $this->hostname;
            $this->port = $urlComponents['port'] ?? $this->port;
        }

        parent::open();
    }

    /**
     * Ensure database is always set, even if connection is already open
     */
    public function executeCommand($name, $params = []): bool|array|string|null
    {
        if ($name !== 'SELECT') {
            $this->executeCommand('SELECT', [$this->database]);
        }
        return parent::executeCommand($name, $params);
    }
}
