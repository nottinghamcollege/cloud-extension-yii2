<?php

namespace craft\cloud\redis;

class Connection extends \yii\redis\Connection
{
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
