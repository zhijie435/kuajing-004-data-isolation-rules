<?php

require_once __DIR__ . '/../autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Tenant-Id, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

use App\Core\Http\Router;
use App\Core\Middleware\TenantMiddleware;

$router = new Router();

$router->post('/api/auth/login', function ($req) {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $req['body'] = $body;
    return (new App\Module\Auth\Controller\AuthController())->login($req);
});

$router->group('/api', [new TenantMiddleware()], function (Router $r) {

    $r->get('/auth/me', function ($req) {
        return (new App\Module\Auth\Controller\AuthController())->getCurrentUser();
    });

    $r->get('/auth/tenants', function ($req) {
        return (new App\Module\Auth\Controller\AuthController())->getAvailableTenants();
    });

    $r->get('/data-scope/info', function ($req) {
        return (new App\Module\Core\Controller\DataScopeController())->getScopeInfo();
    });

    $r->get('/data-scope/available', function ($req) {
        return (new App\Module\Core\Controller\DataScopeController())->getAvailableScopes();
    });

    $r->post('/data-scope/switch', function ($req) {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $req['body'] = $body;
        return (new App\Module\Core\Controller\DataScopeController())->switchScope($req);
    });

    $r->post('/data-scope/check-access', function ($req) {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $req['body'] = $body;
        return (new App\Module\Core\Controller\DataScopeController())->checkResourceAccess($req);
    });

    $r->post('/data-scope/cross-role-filter', function ($req) {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $req['body'] = $body;
        return (new App\Module\Core\Controller\DataScopeController())->crossRoleFilter($req);
    });

    $r->post('/data-scope/cross-role-audit', function ($req) {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $req['body'] = $body;
        return (new App\Module\Core\Controller\DataScopeController())->crossRoleAudit($req);
    });

    $r->get('/courses', function ($req) {
        $req['query'] = $_GET ?? [];
        return (new App\Module\Course\Controller\CourseController())->index($req);
    });

    $r->get('/courses/debug', function ($req) {
        return (new App\Module\Course\Controller\CourseController())->debug($req);
    });

    $r->post('/courses/cross-role-report', function ($req) {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $req['body'] = $body;
        return (new App\Module\Course\Controller\CourseController())->crossRoleReport($req);
    });

    $r->get('/courses/{id}', function ($req) {
        return (new App\Module\Course\Controller\CourseController())->show($req);
    });

    $r->post('/courses', function ($req) {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $req['body'] = $body;
        return (new App\Module\Course\Controller\CourseController())->store($req);
    });

    $r->put('/courses/{id}', function ($req) {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $req['body'] = $body;
        return (new App\Module\Course\Controller\CourseController())->update($req);
    });

    $r->delete('/courses/{id}', function ($req) {
        return (new App\Module\Course\Controller\CourseController())->destroy($req);
    });
});

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/backend/public';
$path = substr($uri, strlen($basePath) === 0 ? 0 : strlen($basePath));
$path = $uri;

$headers = function_exists('getallheaders') ? getallheaders() : [];
$normalizedHeaders = [];
foreach ($headers as $name => $value) {
    $normalizedHeaders[strtolower($name)] = $value;
}

$request = [
    'method' => $method,
    'uri' => $uri,
    'headers' => $normalizedHeaders,
    'query' => $_GET ?? [],
    'body' => json_decode(file_get_contents('php://input'), true) ?: [],
];

$response = $router->dispatch($method, $uri, $request);

http_response_code($response['status'] ?? 200);
echo $response['body'];
