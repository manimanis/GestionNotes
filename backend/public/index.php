<?php
/**
 * Gestion de Notes - API REST Router Principal
 * 
 * Point d'entrée unique de l'API
 * Servi par Apache (mod_php) à l'adresse http://127.0.0.1/GestionNotes/api/*
 * Fonctionne aussi avec le serveur de développement PHP
 */

// ============================================
// AUTOLOADER PSR-4 SIMPLIFIÉ
// ============================================
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';
    
    if (strncmp($prefix, $class, strlen($prefix)) === 0) {
        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    }
});

// ============================================
// HEADERS CORS + CONTENT-TYPE
// ============================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Gérer les requêtes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// INITIALISATION DE LA BASE DE DONNÉES
// ============================================
use App\Helpers\Database;

try {
    $dbInstance = Database::getInstance();
    $dbInstance->initialize();
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur d\'initialisation: ' . $e->getMessage()
    ]);
    exit;
}

// ============================================
// ANALYSE DE L'URI
// ============================================
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Récupérer l'URI complète
// Sous Apache : /GestionNotes/api/login
// Sous PHP built-in : /api/login ou /login
$requestUri = $_SERVER['REQUEST_URI'];
$uri = parse_url($requestUri, PHP_URL_PATH);

// Normaliser le chemin :
// 1. Supprimer le préfixe /GestionNotes (Apache)
$uri = preg_replace('#^/GestionNotes#', '', $uri);
// 2. Supprimer le préfixe /api si présent
$uri = preg_replace('#^/api#', '', $uri);
// 3. Supprimer le préfixe /backend/public si présent (fallback)
$uri = preg_replace('#^/backend/public#', '', $uri);
// 4. Normaliser les slashes
$uri = '/' . trim($uri, '/');

// Si l'URI est vide, c'est la racine
if ($uri === '/' || $uri === '') {
    echo json_encode([
        'status' => 'success',
        'message' => 'API Gestion Notes - OK',
        'version' => '1.0.0',
        'uri' => $uri,
        'request_uri' => $_SERVER['REQUEST_URI']
    ]);
    exit;
}

// ============================================
// ROUTES DE L'API
// ============================================
$routes = [
    ['GET', '/health', function () {
        echo json_encode(['status' => 'success', 'message' => 'API Gestion Notes - OK', 'version' => '1.0.0']);
        exit;
    }],
    
    // Auth
    ['POST', '/register', 'AuthController@register'],
    ['POST', '/login', 'AuthController@login'],
    ['GET', '/user', 'AuthController@user'],
    ['PUT', '/profile', 'AuthController@updateProfile'],
    ['POST', '/profile/photo', 'AuthController@uploadPhoto'],
    
    // Feuilles
    ['GET', '/feuilles', 'FeuilleController@index'],
    ['POST', '/feuilles', 'FeuilleController@store'],
    ['GET', '/feuilles/{id}', 'FeuilleController@show'],
    ['PUT', '/feuilles/{id}', 'FeuilleController@update'],
    ['DELETE', '/feuilles/{id}', 'FeuilleController@destroy'],
    ['POST', '/feuilles/{id}/duplicate', 'FeuilleController@duplicate'],
    
    // Élèves
    ['GET', '/feuilles/{feuilleId}/eleves', 'EleveController@index'],
    ['POST', '/eleves', 'EleveController@store'],
    ['PUT', '/eleves/{id}', 'EleveController@update'],
    ['DELETE', '/eleves/{id}', 'EleveController@destroy'],
    ['POST', '/eleves/import', 'EleveController@importCsv'],
    ['POST', '/eleves/import-paste', 'EleveController@importPaste'],
    
    // Évaluations
    ['GET', '/feuilles/{feuilleId}/evaluations', 'EvaluationController@index'],
    ['POST', '/evaluations', 'EvaluationController@store'],
    ['PUT', '/evaluations/{id}', 'EvaluationController@update'],
    ['DELETE', '/evaluations/{id}', 'EvaluationController@destroy'],
    
    // Épreuves
    ['GET', '/feuilles/{feuilleId}/epreuves', 'EpreuveController@index'],
    ['POST', '/epreuves', 'EpreuveController@store'],
    ['PUT', '/epreuves/{id}', 'EpreuveController@update'],
    ['DELETE', '/epreuves/{id}', 'EpreuveController@destroy'],
    
    // Notes
    ['POST', '/notes-evaluations', 'NoteController@saveEvaluationNote'],
    ['POST', '/notes-evaluations/batch', 'NoteController@saveBatchEvaluationNotes'],
    ['POST', '/notes-epreuves', 'NoteController@saveEpreuveNote'],
    
    // Statistiques
    ['GET', '/feuilles/{feuilleId}/stats', 'StatsController@index'],
    
    // Export
    ['GET', '/feuilles/{feuilleId}/export/csv', 'ExportController@exportCsv'],
    ['GET', '/feuilles/{feuilleId}/export/json', 'ExportController@exportJson'],
];

// ============================================
// DISPATCH
// ============================================
function dispatchRoute(string $method, string $uri, array $routes): void
{
    foreach ($routes as [$routeMethod, $routePattern, $handler]) {
        if ($method !== $routeMethod) {
            continue;
        }
        
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[a-zA-Z0-9\-_\.]+)', $routePattern);
        $pattern = '#^' . $pattern . '$#';
        
        if (preg_match($pattern, $uri, $matches)) {
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            
            if (is_string($handler)) {
                [$controllerName, $methodName] = explode('@', $handler);
                $controllerClass = "App\\Controllers\\{$controllerName}";
                
                if (!class_exists($controllerClass)) {
                    http_response_code(500);
                    echo json_encode(['status' => 'error', 'message' => "Controller {$controllerClass} not found"]);
                    exit;
                }
                
                $controllerClass::$methodName(...array_values($params));
            } elseif (is_callable($handler)) {
                $handler();
            }
            return;
        }
    }
    
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Route non trouvée',
        'uri' => $uri,
        'method' => $method
    ]);
    exit;
}

// Dispatcher
try {
    dispatchRoute($requestMethod, $uri, $routes);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur base de données: ' . $e->getMessage()
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}