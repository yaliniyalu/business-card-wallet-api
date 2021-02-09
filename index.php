<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

const TOKEN_FILE = __DIR__ . '/token';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: token, Content-Type');
    header('Access-Control-Max-Age: 1728000');
    header('Content-Length: 0');
    header('Content-Type: text/plain');
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$text = json_decode(file_get_contents("php://input"), true)['text'];

$token = file_get_contents(TOKEN_FILE);
if (!$token) {
    $token = getToken();
}

$nlp = null;
try {
    $nlp = getNLP($text, $token);
} catch (\GuzzleHttp\Exception\ClientException $e) {
    $status = $e->getResponse()->getStatusCode();

    echo $e->getMessage();

    $str = $e->getRequest()->getHeaders();

    if ($status == 401) {
        try {
            $token = getToken();
            $nlp = getNLP($text, $token);
        }
        catch (Exception $e) {

        }
    }
}

if ($nlp === null) {
    echo json_encode(["error" => true, "message" => "Unknown Error"]);
    exit;
}

define('NLP', $nlp);
$result = require_once __DIR__ . '/process.php';

echo json_encode($result);
exit;


function getToken() {
    $client = new \GuzzleHttp\Client();
    $response = $client->post("https://developer.expert.ai/oauth2/token", [
        'json' => [
            "username" => $_ENV['EXPERT_AI_USERNAME'],
            "password" => $_ENV['EXPERT_AI_PASSWORD']
        ]
    ]);

    $token = (string) $response->getBody();
    file_put_contents(TOKEN_FILE, $token);
    return $token;
}

function getNLP($text, $token) {
    $client = new \GuzzleHttp\Client();
    $response = $client->post("https://nlapi.expert.ai/v2/analyze/standard/en", [
        'json' => [
            "document" => [
                "text" =>  $text
            ]
        ],
       'headers' => [
            'Authorization' => "Bearer $token"
        ]
    ]);

    return json_decode((string) $response->getBody(), true);
}
