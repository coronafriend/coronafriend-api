<?php

declare(strict_types=1);

namespace CoronaFriend\Api;

use KammaData\ApiScaffold\Interfaces\RoutesInterface;
use KammaData\DatabaseScaffold\PostgreSQL\PostgreSQLClient;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Slim\App;
use Slim\Routing\RouteCollectorProxy;

class Routes implements RoutesInterface {
    public function __invoke(App $app) {

        // CORS pre-flight enablement ...
        $app->options('/{routes:.+}', function (Request $request, Response $response, array $args): Response {
            return $response;
        });

        $app->map(['GET', 'PUT', 'POST', 'PATCH', 'DELETE'], '/', function (Request $request, Response $response, array $args): Response {
            return self::notAllowed($response);
        });

        $app->group('/v1', function(RouteCollectorProxy $group) {
            $group->map(['GET', 'PUT', 'POST', 'PATCH', 'DELETE'], '/', function (Request $request, Response $response, array $args): Response {
                return self::notAllowed($response);
            });

            $group->get('/roads', function (Request $request, Response $response, array $args): Response {
                $params = $request->getQueryParams();

                if (!isset($params['bounds']) || empty($params['bounds'])) {
                    $data = [
                        'status' => [
                            'code' => 400,
                            'message' => 'Bad Request - missing required "bounds" parameter'
                        ]
                    ];
                    $payload = json_encode($data, JSON_PRETTY_PRINT);
                    $response->getBody()->write($payload);
                    return $response->withStatus($data['status']['code'])
                        ->withHeader('Content-Type', 'application/json;charset=UTF-8');
                }

                $bounds = $params['bounds'];
                $settings = $this->get('settings')['datastore'];
                $table = $settings['roads-table'];
                $sql = sprintf("SELECT jsonb_build_object('type', 'FeatureCollection', 'features', jsonb_agg(features.feature)) FROM (SELECT jsonb_build_object('type', 'Feature', 'id', roadnametoid, 'geometry', ST_AsGeoJSON(ST_Transform(geom,4326))::jsonb, 'properties', to_jsonb(inputs) - 'geom') AS feature FROM (SELECT * FROM %s WHERE roadclassification <> 'Motorway' AND roadnametoid IS NOT null AND geom && ST_MakeEnvelope(%s)) inputs) features;", $table, $bounds);

                $db = $this->get(PostgreSQLClient::class);
                $query = $db()->query($sql);
                $data = $query->fetchAll(\PDO::FETCH_ASSOC);
                $geojson = $data[0]['jsonb_build_object'];

                $data = [
                    'status' => [
                        'code' => 200,
                        'message' => 'OK'
                    ]
                ];
                $payload = json_encode($data, JSON_PRETTY_PRINT);
                $response->getBody()->write($geojson);
                return $response->withStatus($data['status']['code'])
                    ->withHeader('Content-Type', 'application/json;charset=UTF-8');
            });

            $group->map(['PUT', 'POST', 'PATCH', 'DELETE'], '/roads', function (Request $request, Response $response, array $args): Response {
                return self::notAllowed($response);
            });

            $group->get('/roads/{id}', function (Request $request, Response $response, array $args): Response {
                $toid = $args['id'];
                $settings = $this->get('settings')['datastore'];
                $table = $settings['roads-table'];
                $sql = sprintf("SELECT json_build_object('type', 'Feature', 'id', roadnametoid,	'geometry', ST_AsGeoJSON(ST_Transform(geom,4326))::jsonb, 'properties', to_jsonb(row) - 'geom') FROM (SELECT * FROM %s WHERE roadnametoid='%s') row;", $table, $toid);

                $db = $this->get(PostgreSQLClient::class);
                $query = $db()->query($sql);
                $data = $query->fetchAll(\PDO::FETCH_ASSOC);

                $geojson = $data[0]['json_build_object'];
                $data = [
                    'status' => [
                        'code' => 200,
                        'message' => 'OK'
                    ]
                ];
                $payload = json_encode($data, JSON_PRETTY_PRINT);
                $response->getBody()->write($geojson);
                return $response->withStatus($data['status']['code'])
                    ->withHeader('Content-Type', 'application/json;charset=UTF-8');
            });

            $group->put('/roads/{id}', function (Request $request, Response $response, array $args): Response {
                $data = [
                    'status' => [
                        'code' => 200,
                        'message' => 'OK'
                    ]
                ];
                $payload = json_encode($data, JSON_PRETTY_PRINT);
                $response->getBody()->write($payload);
                return $response->withStatus($data['status']['code'])
                    ->withHeader('Content-Type', 'application/json;charset=UTF-8');
            });

            $group->map(['POST', 'PATCH', 'DELETE'], '/roads/{id}', function (Request $request, Response $response, array $args): Response {
                return self::notAllowed($response);
            });
        });
    }

    private static function notAllowed(Response $response): Response {
        $data = [
            'status' => [
                'code' => 405,
                'message' => 'Method Not Allowed'
            ]
        ];
        $payload = json_encode($data, JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response->withStatus($data['status']['code'])
            ->withHeader('Content-Type', 'application/json;charset=UTF-8');
    }
};

?>
