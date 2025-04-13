<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class Controller
{

    public static $baseUrl = 'http://api.eu-pet.com';

    public function proxy(Request $request)
    {
        $destinationUrl = self::$baseUrl . $request->getRequestUri();

        $targetUrl = "http://api.eu-pet.com"; // Bitte anpassen

// Anfrageheader von Client abrufen
        $requestHeaders = getallheaders();
        $headers = [];

// Anfrageheaders für cURL umformatieren
        foreach ($requestHeaders as $key => $value) {
            $headers[] = "$key: $value";
        }

// Eingehende Nutzlast (POST-Daten etc.)
        $body = file_get_contents("php://input");

// Ziel-URL zusammenbauen (bei Query-Strings)
        $target = $targetUrl . $_SERVER['REQUEST_URI'];

// cURL Initialisieren
        $ch = curl_init($target);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']); // Methode setzen (GET, POST, PUT, DELETE...)
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // Header in die Antwort einschließen
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Anfrage-Header übernehmen

// POST-, PUT- oder DELETE-Daten setzen (falls vorhanden)
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE']) && !empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

// Antwort von Target-Server abrufen
        $response = curl_exec($ch);

// Antwortanalyse: Header und Body trennen
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

// Fehler prüfen
        if (curl_errno($ch)) {
            $error = "cURL-Fehler: " . curl_error($ch);
            logData("[ERROR] $error");
            http_response_code(500); // Interner Fehler
            echo $error;
            curl_close($ch);
            exit;
        }

// cURL schließen
        curl_close($ch);
        $headerLines = explode("\r\n", trim($responseHeaders));

        foreach ($headerLines as $header) {
            if (stripos($header, 'Content-Encoding') === false) { // GZIP etc. rausfiltern
                header($header);
            }
        }

        Log::info('Proxy-Data', [
            'url' => $destinationUrl,
            'data' => $responseBody,
            'request' => $body
        ]);
        echo $responseBody;
        die();


    }
}
