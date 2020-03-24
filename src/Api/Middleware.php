<?php

declare(strict_types=1);

namespace CoronaFriend\Api;

use KammaData\ApiScaffold\Interfaces\MiddlewareInterface;

use Slim\App;

class Middleware implements MiddlewareInterface {
    public function __invoke(App $app) {
        $app->add(new CorsHandler());
    }
};

?>
