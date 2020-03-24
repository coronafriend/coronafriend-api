<?php

declare(strict_types=1);

namespace CoronaFriend\Api;

use KammaData\ApiScaffold\Interfaces\SettingsInterface;

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Monolog\Logger;

class Settings implements SettingsInterface {
    public function __invoke(ContainerBuilder $containerBuilder) {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $containerBuilder->addDefinitions([
            'settings' => [
                'displayErrorDetails' => (getenv('DEPLOYMENT_ENVIRONMENT') !== 'production'),
                'logger' => [
                    'name' => 'api-scaffold-demo',
                    'path' => getenv('LOGFILE_PATH'),
                    'level' => Logger::DEBUG,
                ],
                'api' => [
                    'endpoint' => getenv('API_ENDPOINT')
                ],
                'datastore' => [
                    'host' => getenv('POSTGRES_HOST'),
                    'user' => getenv('POSTGRES_USER'),
                    'password' => getenv('POSTGRES_PASSWORD'),
                    'port' => getenv('POSTGRES_PORT'),
                    'database' => getenv('POSTGRES_DATABASE'),
                    'roads-table' => getenv('POSTGRES_ROADS_TABLE'),
                    'friends-table' => getenv('POSTGRES_FRIENDS_TABLE')
                ]
            ]
        ]);
    }
};

?>
