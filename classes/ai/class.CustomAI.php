<?php
declare(strict_types=1);

namespace ai;

use ilObjAIChatGUI;
use objects\Chat;
use platform\AIChatException;

class CustomAI extends LLM
{
    private string $url;
    private string $model;
    private string $apiKey;

    public function __construct(string $model)
    {
        $this->model = $model;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
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
            "temperature" => 0.5
        ]);

        $curlSession = curl_init();

        curl_setopt($curlSession, CURLOPT_URL, $this->url);
        curl_setopt($curlSession, CURLOPT_POST, true);
        curl_setopt($curlSession, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlSession, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
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
        return $decodedResponse['choices'][0]['message']['content'] ?? "";
    }
}