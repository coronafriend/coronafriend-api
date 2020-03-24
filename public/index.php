<?php

require_once '../vendor/autoload.php';

$settings = new CoronaFriend\Api\Settings();
$dependencies = new CoronaFriend\Api\Dependencies();
$middleware = new CoronaFriend\Api\Middleware();
$routes = new CoronaFriend\Api\Routes();

$api = new KammaData\ApiScaffold\Api($settings, $dependencies, $middleware, $routes);
$api->run();

?>
