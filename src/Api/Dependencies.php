<?php

declare(strict_types=1);

namespace CoronaFriend\Api;

use KammaData\ApiScaffold\Interfaces\DependenciesInterface;
use KammaData\DatabaseScaffold\PostgreSQL\PostgreSQLClient;

use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class Dependencies implements DependenciesInterface {
    public function __invoke(ContainerBuilder $containerBuilder) {
        $containerBuilder->addDefinitions([
            LoggerInterface::class => function (ContainerInterface $c) {
                $settings = $c->get('settings');

                $loggerSettings = $settings['logger'];
                $logger = new Logger($loggerSettings['name']);

                $processor = new UidProcessor();
                $logger->pushProcessor($processor);

                $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
                $logger->pushHandler($handler);

                return $logger;
            },
            PostgreSQLClient::class => function(ContainerInterface $c) {
                $settings = $c->get('settings')['datastore'];

                $options = [
                    PostgreSQLClient::HOST => $settings['host'],
                    PostgreSQLClient::USER => $settings['user'],
                    PostgreSQLClient::PORT => $settings['port'],
                    PostgreSQLClient::DATABASE => $settings['database'],
                    PostgreSQLClient::CONNECT => true
                ];
                if (isset($settings['password']) && !empty($settings['password'])) {
                    $options[PostgreSQLClient::PASSWD] = $settings['password'];
                }

                $client = new PostgreSQLClient($options);
                return $client;
            }
        ]);
    }
};

?>
