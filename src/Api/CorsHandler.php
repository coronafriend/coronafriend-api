<?php

declare(strict_types=1);

namespace CoronaFriend\Api;

use KammaData\ApiScaffold\Interfaces\MiddlewareHandlerInterface;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class CorsHandler implements MiddlewareHandlerInterface {
    public function __invoke(Request $request, RequestHandler $handler): Response {
        $response = $handler->handle($request);

        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            // ->withHeader('Access-Control-Allow-Headers', ['Content-Type', 'Origin', 'Accept'])
            ->withHeader('Access-Control-Allow-Methods', ['OPTIONS', 'GET', 'POST']);
    }
};

?>
