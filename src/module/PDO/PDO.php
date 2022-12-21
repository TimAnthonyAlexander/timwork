<?php

namespace src\module\PDO;

use src\module\ProjectConfig\ProjectConfig;

class PDO
{
    public static function getDriver(): \PDO
    {
        $projectConfig = new ProjectConfig();

        $projectConfig->getConfigItem('db', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '8339',
            'database' => 'teamup',
            'username' => 'dev',
            'password' => 'development33!',
        ]);

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s',
            $projectConfig->getConfigItem('db')['driver'],
            $projectConfig->getConfigItem('db')['host'],
            $projectConfig->getConfigItem('db')['port'],
            $projectConfig->getConfigItem('db')['database']
        );

        return new \PDO(
            $dsn,
            $projectConfig->getConfigItem('db')['username'],
            $projectConfig->getConfigItem('db')['password']
        );
    }
}
