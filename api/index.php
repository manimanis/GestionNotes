<?php
/**
 * Gestion de Notes - API REST
 * http://127.0.0.1/GestionNotes/api/login
 * Apache exécute ce fichier automatiquement (dossier api/ + mod_php)
 */

// Autoloader
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../backend/src/';
    if (strncmp($prefix, $class, strlen($prefix)) === 0) {
        $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($file)) require $file;
    }
});

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// DB
use App\Helpers\Database;
try {
    Database::getInstance()->initialize();
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur: ' . $e->getMessage()]);
    exit;
}

// URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^/GestionNotes#', '', $uri);
$path = preg_replace('#^/api#', '', $path);
$path = '/' . trim($path, '/');

// Health
if ($path === '/') {
    echo json_encode(['status' => 'success', 'message' => 'API OK', 'version' => '1.0.0']);
    exit;
}

// Routes
$routes = [
    ['POST', '/login', 'AuthController@login'],
    ['POST', '/register', 'AuthController@register'],
    ['GET', '/user', 'AuthController@user'],
    ['PUT', '/profile', 'AuthController@updateProfile'],
    ['POST', '/profile/photo', 'AuthController@uploadPhoto'],
    ['GET', '/feuilles', 'FeuilleController@index'],
    ['POST', '/feuilles', 'FeuilleController@store'],
    ['GET', '/feuilles/{id}', 'FeuilleController@show'],
    ['PUT', '/feuilles/{id}', 'FeuilleController@update'],
    ['DELETE', '/feuilles/{id}', 'FeuilleController@destroy'],
    ['POST', '/feuilles/{id}/duplicate', 'FeuilleController@duplicate'],
    ['POST', '/feuilles/{id}/import-data', 'FeuilleController@importData'],
    ['GET', '/feuilles/{feuilleId}/eleves', 'EleveController@index'],
    ['POST', '/eleves', 'EleveController@store'],
    ['PUT', '/eleves/{id}', 'EleveController@update'],
    ['DELETE', '/eleves/{id}', 'EleveController@destroy'],
    ['POST', '/eleves/import', 'EleveController@importCsv'],
    ['POST', '/eleves/import-paste', 'EleveController@importPaste'],
    ['GET', '/feuilles/{feuilleId}/evaluations', 'EvaluationController@index'],
    ['POST', '/evaluations', 'EvaluationController@store'],
    ['PUT', '/evaluations/{id}', 'EvaluationController@update'],
    ['DELETE', '/evaluations/{id}', 'EvaluationController@destroy'],
    ['GET', '/feuilles/{feuilleId}/epreuves', 'EpreuveController@index'],
    ['POST', '/epreuves', 'EpreuveController@store'],
    ['PUT', '/epreuves/{id}', 'EpreuveController@update'],
    ['DELETE', '/epreuves/{id}', 'EpreuveController@destroy'],
    ['POST', '/notes-evaluations', 'NoteController@saveEvaluationNote'],
    ['POST', '/notes-evaluations/batch', 'NoteController@saveBatchEvaluationNotes'],
    ['POST', '/notes-epreuves', 'NoteController@saveEpreuveNote'],
    ['GET', '/feuilles/{feuilleId}/stats', 'StatsController@index'],
    ['GET', '/feuilles/{feuilleId}/export/csv', 'ExportController@exportCsv'],
    ['GET', '/feuilles/{feuilleId}/export/json', 'ExportController@exportJson'],
];

foreach ($routes as [$method, $pattern, $handler]) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) continue;
    $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[a-zA-Z0-9\-_\.]+)', $pattern);
    if (!preg_match('#^' . $regex . '$#', $path, $m)) continue;
    $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
    [$ctrl, $action] = explode('@', $handler);
    $class = "App\\Controllers\\{$ctrl}";
    $class::$action(...array_values($params));
    exit;
}

http_response_code(404);
echo json_encode(['status' => 'error', 'message' => 'Route non trouvée', 'uri' => $path]);