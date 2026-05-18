<?php
declare(strict_types=1);

namespace AIChat\classes\ai;

use ilObjAIChatGUI;
use AIChat\classes\objects\Chat;
use platform\AIChatException;

class OpenAI extends LLM
{
    private string $model;
    private string $apiKey;
    private bool $streaming = false;
    public const MODEL_TYPES = [
		"gpt-4" => "GPT 4",
        "gpt-4-turbo" => "GPT 4 turbo",
        "gpt-4.1-mini" => "GPT 4.1 mini",
        "gpt-4.1-nano" => "GPT 4.1 nano",
        "gpt-4o" => "GPT 4o",
        "gpt-3.5-turbo" => "GPT 3.5 turbo"
    ];

    public function __construct(string $model)
    {
        $this->model = $model;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function setStreaming(bool $streaming): void
    {
        $this->streaming = $streaming;
    }

    public function isStreaming(): bool
    {
        return $this->streaming;
    }

    /**
     * @throws AIChatException
     */
    public function sendChat(Chat $chat)
    {
        global $DIC;

        $apiUrl = 'https://api.openai.com/v1/chat/completions';

        $payload = json_encode([
            "messages" => $this->chatToMessagesArray($chat),
            "model" => $this->model,
            "temperature" => 0.5,
            "stream" => $this->isStreaming()
        ]);

        $curlSession = curl_init();

        curl_setopt($curlSession, CURLOPT_URL, $apiUrl);
        curl_setopt($curlSession, CURLOPT_POST, true);
        curl_setopt($curlSession, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, !$this->isStreaming());
        curl_setopt($curlSession, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getApiKey()
        ]);

        if (\ilProxySettings::_getInstance()->isActive()) {
            $proxyHost = \ilProxySettings::_getInstance()->getHost();
            $proxyPort = \ilProxySettings::_getInstance()->getPort();
            $proxyURL = $proxyHost . ":" . $proxyPort;
            curl_setopt($curlSession, CURLOPT_PROXY, $proxyURL);
        }

        $responseContent = '';

        if ($this->isStreaming()) {
            if (!headers_sent()) {
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('X-Accel-Buffering: no');
            }
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            curl_setopt($curlSession, CURLOPT_WRITEFUNCTION, function ($curlSession, $chunk) use (&$responseContent) {
                $responseContent .= $chunk;
                echo $chunk;
                flush();
                return strlen($chunk);
            });
        }

        $response = curl_exec($curlSession);
        $httpcode = curl_getinfo($curlSession, CURLINFO_HTTP_CODE);
        $errNo = curl_errno($curlSession);
        $errMsg = curl_error($curlSession);
        curl_close($curlSession);

        if ($errNo) {
            ilObjAIChatGUI::sendApiResponse(array("error" => $DIC->language()->txt("rep_robj_xaic_error_http") . ": " . $errMsg), 500);
        }

        if ($httpcode != 200) {
            if ($httpcode === 401) {
                ilObjAIChatGUI::sendApiResponse(array("error" => $DIC->language()->txt("rep_robj_xaic_error_apikey")), 401);
            } else {
                ilObjAIChatGUI::sendApiResponse(array("error" => $DIC->language()->txt("rep_robj_xaic_error_http")), $httpcode);
            }
        }

        if (!$this->isStreaming()) {
            $decodedResponse = json_decode($response, true);
            return $decodedResponse['choices'][0]['message']['content'] ?? "";
        }

        // Procesar la respuesta completa acumulada en $responseContent
        $messages = explode("\n", $responseContent);
        $completeMessage = '';

        foreach ($messages as $message) {
            if (trim($message) !== '' && strpos($message, 'data: ') === 0) {
                $json = json_decode(substr($message, strlen('data: ')), true);
                if ($json && isset($json['choices'][0]['delta']['content'])) {
                    $completeMessage .= $json['choices'][0]['delta']['content'];
                }
            }
        }

        return $completeMessage;
    }
}
