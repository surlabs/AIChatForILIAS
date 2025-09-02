<?php
declare(strict_types=1);

namespace AIChat\classes\ai;

use ilObjAIChatGUI;
use AIChat\classes\objects\Chat;
use platform\AIChatException;

class Ollama extends LLM
{
    private string $endpoint;
    private string $model;

    public function __construct(string $model)
    {
        $this->model = $model;
    }

    public function setEndpoint(string $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    /**
     * @throws AIChatException
     */
    public function sendChat(Chat $chat)
    {
        global $DIC;

        $payload = json_encode([
            "messages" => $this->chatToMessagesArray($chat),
            "model" => $this->model,
            "stream" => false
        ]);

        $curlSession = curl_init();

        curl_setopt($curlSession, CURLOPT_URL, rtrim($this->endpoint, '/') . '/api/chat');
        curl_setopt($curlSession, CURLOPT_POST, true);
        curl_setopt($curlSession, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlSession, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);

        if (\ilProxySettings::_getInstance()->isActive()) {
            $proxyHost = \ilProxySettings::_getInstance()->getHost();
            $proxyPort = \ilProxySettings::_getInstance()->getPort();
            $proxyURL = $proxyHost . ":" . $proxyPort;
            curl_setopt($curlSession, CURLOPT_PROXY, $proxyURL);
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

        $decodedResponse = json_decode($response, true);
        return $decodedResponse['message']['content'] ?? "";
    }
}