<?php
declare(strict_types=1);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/*
 *  This file is part of the AI Chat Repository Object plugin for ILIAS, which allows your platform's users
 *  To connect with an external LLM service
 *  This plugin is created and maintained by SURLABS.
 *
 *  The AI Chat Repository Object plugin for ILIAS is open-source and licensed under GPL-3.0.
 *  For license details, visit https://www.gnu.org/licenses/gpl-3.0.en.html.
 *
 *  To report bugs or participate in discussions, visit the Mantis system and filter by
 *  the category "AI Chat" at https://mantis.ilias.de.
 *
 *  More information and source code are available at:
 *  https://github.com/surlabs/AIChat
 *
 *  If you need support, please contact the maintainer of this software at:
 *  info@surlabs.es
 *
 */

class ilAIChatUtils
{
    private const JWT_ALGORITHM = 'HS256';
    private const encryptionKey = 'iliasgpt_keysurlabs';

    public static function decode(string $value): object
    {
        return JWT::decode($value, new Key(self::encryptionKey, self::JWT_ALGORITHM));
    }

    public static function encode(array $value): string
    {
        return JWT::encode($value, self::encryptionKey, self::JWT_ALGORITHM);
    }

    public static function sendToApi(object $request, ilObjAIChat $object, $userId, $plugin)
    {

        $messages = $request->messages;
        $apiKey = $object->getApiKey() ? self::decode($object->getApiKey())->apikey : '';

        $openaiUrl = 'https://api.openai.com/v1/chat/completions'; // OpenAI API URL
        $model = $object->getModel();

        if (!is_array($messages)) {
            self::sendResponseJson(["error" => "Invalid data, array expected"], 400);
            return;
        }

        // Create the request body
        $payload = json_encode([
            "messages" => $messages,
            "model" => $model,
            "temperature" => 0.5
        ]);

        // Initialize cURL session
        $curlSession = curl_init();

        // Set cURL options
        curl_setopt($curlSession, CURLOPT_URL, $openaiUrl);
        curl_setopt($curlSession, CURLOPT_POST, true);
        curl_setopt($curlSession, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlSession, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);

        // Execute cURL and get the response
        $response = curl_exec($curlSession);
        $httpcode = curl_getinfo($curlSession, CURLINFO_HTTP_CODE);
        $errNo = curl_errno($curlSession);
        $errMsg = curl_error($curlSession);
        curl_close($curlSession);

        // Handle OpenAI API errors
        if ($errNo) {
            self::sendResponseJson([
                "error" => $plugin->txt("error_http_openai") . ": " . $errMsg
            ], $httpcode);
            return;
        }

        if ($httpcode != 200) {
            if ($httpcode === 401) {
                self::sendResponseJson([
                    "error" => $plugin->txt("error_apikey")
                ], $httpcode);
            } else {
                self::sendResponseJson([
                    "error" => $plugin->txt("error_http_openai")
                ], $httpcode);
            }
            return;
        }

        // Convert JSON response to a PHP array
        $decodedResponse = json_decode($response, true);

        $messages[] = [
            "role" => "assistant",
            "content" => $decodedResponse['choices'][0]['message']['content'] ?? ""
        ];

        if ($userId != "13") {
            $object->saveMessagesJson(json_encode($messages), $object->getId(), $userId);
        }


        self::sendResponseJson([
            "messages" => $messages
        ]);

    }

    /**
     * Send a JSON response
     */
    public static function sendResponseJson($data, $statusCode = 200)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code($statusCode);
            echo json_encode($data);
        }
        exit();
    }

}