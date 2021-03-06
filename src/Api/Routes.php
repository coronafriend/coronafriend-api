<?php

declare(strict_types=1);

namespace CoronaFriend\Api;

use KammaData\Addressing\PostCode;
use KammaData\ApiScaffold\Interfaces\RoutesInterface;
use KammaData\DatabaseScaffold\PostgreSQL\PostgreSQLClient;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use Slim\App;
use Slim\Routing\RouteCollectorProxy;

class Routes implements RoutesInterface {
    // const SECTOR_REGEX = '/^[a-z]{1,2}\d[a-z\d]?\s*\d$/i';
    const SECTOR_REGEX = '/^[a-z]{1,2}\d[a-z\d]?\s\d$/i';

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

                $sql = sprintf("SELECT json_build_object('type', 'FeatureCollection', 'features', json_agg(features.feature)) FROM (SELECT json_build_object('type', 'Feature', 'id', road_id, 'properties', to_jsonb(inputs) - 'geom', 'geometry', ST_AsGeoJSON(ST_Transform(geom,4326))::jsonb) AS feature FROM (SELECT rl.hash_id AS road_id, rl.name1 AS road_name, rl.roadclassificationnumber AS road_number, rl.geom, rd.claim_id, rd.road_meta, ce.claim AS claim_type FROM roadlink AS rl, road_data AS rd, claim_enum AS ce WHERE rl.roadclassification <> 'Motorway' AND rd.roadlink_id=rl.hash_id AND rd.claim_id=ce.id AND geom && ST_MakeEnvelope(%s)) inputs) features;", $bounds);


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

            $group->map(['PUT', 'POST', 'PATCH', 'DELETE'], '/roads', function (Request $request, Response $response, array $args): Response {
                return self::notAllowed($response);
            });

            $group->get('/roads/{id}', function (Request $request, Response $response, array $args): Response {
                $id = $args['id'];
                $settings = $this->get('settings')['datastore'];
                $table = $settings['roads-table'];
                $sql = sprintf("SELECT json_build_object('type', 'Feature', 'id', road_id, 'properties', to_jsonb(row) - 'geom', 'geometry', ST_AsGeoJSON(ST_Transform(geom,4326))::jsonb) FROM (SELECT rl.hash_id AS road_id, rl.name1 AS road_name, rl.roadclassificationnumber AS road_number, rl.geom, rd.claim_id, rd.road_meta, ce.claim AS claim_type FROM roadlink AS rl, road_data AS rd, claim_enum AS ce WHERE rl.hash_id='%s' AND rd.roadlink_id=rl.hash_id AND rd.claim_id = ce.id) AS row;", $id);

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
                $id = $args['id'];
                $settings = $this->get('settings')['datastore'];
                $table = $settings['roads-table'];
                $sql = sprintf("SELECT json_build_object('type', 'Feature', 'id', road_id, 'properties', to_jsonb(row) - 'geom', 'geometry', ST_AsGeoJSON(ST_Transform(geom,4326))::jsonb) FROM (SELECT rl.hash_id AS road_id, rl.name1 AS road_name, rl.roadclassificationnumber AS road_number, rl.geom, rd.claim_id, rd.road_meta, ce.claim AS claim_type FROM roadlink AS rl, road_data AS rd, claim_enum AS ce WHERE rl.hash_id='%s' AND rd.roadlink_id=rl.hash_id AND rd.claim_id = ce.id) AS row;", $id);
                $sql = sprintf("SELECT * FROM road_data WHERE roadlink_id = '%s';", $id);
                $db = $this->get(PostgreSQLClient::class);

                if ($db()->beginTransaction()) {
                    try {
                        $query = $db()->query($sql);
                        $data = $query->fetchAll(\PDO::FETCH_ASSOC)[0];

                        $road_meta = ($data['road_meta'] == null ? [] : json_decode($data['road_meta'], true));
                        $history = ($data['history'] == null ? [] : json_decode($data['history'], true));
                        $remoteIP = '0.0.0.0';
                        if ($request->hasHeader('X-Forwarded-For')) {
                            $remoteIP = $request->getHeader('X-Forwarded-For');
                        }
                        $update = [
                            'ip' => $remoteIP,
                            'datestamp' => date(DATE_ISO8601)
                        ];
                        if (empty($history)) {
                            $history = [ $update ];
                        }
                        else {
                            $history[] = $update;
                        }

                        $body = $request->getParsedBody();
                        $claim_id = intval($body['claim_id']);

                        if (empty($road_meta)) {
                            $road_meta = [ $body['road_meta'] ];
                        }
                        else {
                            $road_meta[] = $body['road_meta'];
                        }

                        if (empty($history)) {
                            $encoded_history = 'NULL';
                        }
                        else {
                            $encoded_history = $db->encodeNativeJSON($history);
                        }

                        if (empty($road_meta)) {
                            $encoded_road_meta = 'NULL';
                        }
                        else {
                            $encoded_road_meta = $db->encodeNativeJSON($road_meta);
                        }

                        $body['claim_id'] = $claim_id;
                        $body['road_meta'] = $road_meta;
                        $body['history'] = $history;


                        $sql = sprintf("UPDATE road_data SET claim_id=%d, road_meta=%s, history=%s WHERE roadlink_id='%s';", $claim_id, $encoded_road_meta, $encoded_history, $id);
                        $db()->exec($sql);
                        $db()->commit();

                        $body['status'] = [
                            'code' => 200,
                            'message' => 'OK'
                        ];
                        $payload = json_encode($body, JSON_PRETTY_PRINT);
                        $response->getBody()->write($payload);
                        return $response->withStatus($body['status']['code'])
                            ->withHeader('Content-Type', 'application/json;charset=UTF-8');
                    }

                    catch (\Exception $e) {
                        if ($db()->inTransaction()) {
                            $db()->rollback();
                        }
                    }
                }
            });

            $group->map(['POST', 'PATCH', 'DELETE'], '/roads/{id}', function (Request $request, Response $response, array $args): Response {
                return self::notAllowed($response);
            });

            $group->get('/postcode/{postcode}', function (Request $request, Response $response, array $args): Response {
                $candidate = trim(urldecode($args['postcode']));
                if (PostCode::isValid($candidate)) {
                    $postcode = PostCode::toNormalised($candidate);
                    $settings = $this->get('settings')['datastore'];
                    $table = $settings['postcodes-table'];
                    $sql = sprintf("SELECT jsonb_build_object('type', 'FeatureCollection', 'features', jsonb_agg(features.feature)) FROM (SELECT jsonb_build_object('type', 'Feature', 'geometry', ST_AsGeoJSON(ST_Transform(geometry, 4326))::jsonb, 'properties', to_jsonb(inputs) - 'geometry') AS feature FROM (select pcds AS postcode, wkb_geometry AS geometry FROM %s WHERE pcds = '%s') inputs) features;", $table, $postcode);
                    $db = $this->get(PostgreSQLClient::class);
                    $query = $db()->query($sql);
                    $data = $query->fetchAll(\PDO::FETCH_ASSOC);
                    $payload = $data[0]['jsonb_build_object'];
                    $geojson = json_decode($payload, true);

                    if (!empty($geojson['features'])) {
                        $data = [
                            'status' => [
                                'code' => 200,
                                'message' => 'OK'
                            ]
                        ];
                        $response->getBody()->write($payload);
                        return $response->withStatus($data['status']['code'])
                            ->withHeader('Content-Type', 'application/json;charset=UTF-8');
                    }
                }

                else if (preg_match(self::SECTOR_REGEX, $candidate) === 1) {
                    $sector = strtoupper($candidate);
                    $settings = $this->get('settings')['datastore'];
                    $table = $settings['postcodes-table'];
                    $sql = sprintf("SELECT jsonb_build_object('type', 'FeatureCollection', 'features', jsonb_agg(features.feature)) FROM (SELECT jsonb_build_object('type', 'Feature', 'geometry', ST_AsGeoJSON(ST_Transform(geometry, 4326))::jsonb, 'properties', to_jsonb(inputs) - 'geometry') AS feature FROM (select pcd2 AS postcode, wkb_geometry AS geometry FROM %s WHERE pcd2 LIKE '%s%%') inputs) features;", $table, $sector);

                    $db = $this->get(PostgreSQLClient::class);
                    $query = $db()->query($sql);
                    $data = $query->fetchAll(\PDO::FETCH_ASSOC);
                    $payload = $data[0]['jsonb_build_object'];
                    $geojson = json_decode($payload, true);

                    if (!empty($geojson['features'])) {
                        $data = [
                            'status' => [
                                'code' => 200,
                                'message' => 'OK'
                            ]
                        ];
                        $response->getBody()->write($payload);
                        return $response->withStatus($data['status']['code'])
                            ->withHeader('Content-Type', 'application/json;charset=UTF-8');
                    }
                }

                $data = [
                    'status' => [
                        'code' => 404,
                        'message' => 'Not Found'
                    ]
                ];
                $payload = json_encode($data, JSON_PRETTY_PRINT);
                $response->getBody()->write($payload);
                return $response->withStatus($data['status']['code'])
                    ->withHeader('Content-Type', 'application/json;charset=UTF-8');
            });

            $group->get('/stats', function (Request $request, Response $response, array $args): Response {
                $settings = $this->get('settings')['datastore'];
                $sql = sprintf('SELECT count(*) AS total, count(*) FILTER (WHERE claim_id=1) AS full, count(*) FILTER (WHERE claim_id=2) AS partial, count(*) FILTER (WHERE claim_id=3) AS empty FROM road_data;');
                $db = $this->get(PostgreSQLClient::class);
                $query = $db()->query($sql);
                $data = $query->fetchAll(\PDO::FETCH_ASSOC);
                $payload = [
                    'stats' => $data[0],
                    'status' => [
                        'code' => 200,
                        'message' => 'OK'
                    ]
                ];
                $response->getBody()->write(json_encode($payload));
                return $response->withStatus($payload['status']['code'])
                    ->withHeader('Content-Type', 'application/json;charset=UTF-8');
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
