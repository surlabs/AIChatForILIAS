<?php
declare(strict_types=1);
/**
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

namespace ai;

use ilObjAIChatGUI;
use objects\Chat;
use platform\AIChatException;

/**
 * Class OpenAI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class OpenAI extends LLM
{
    private string $model;
    private string $apiKey;

    public function __construct(string $model)
    {
        $this->model = $model;
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }


    /** @noinspection PhpComposerExtensionStubsInspection */
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
            "temperature" => 0.5
        ]);

        $curlSession = curl_init();

        curl_setopt($curlSession, CURLOPT_URL, $apiUrl);
        curl_setopt($curlSession, CURLOPT_POST, true);
        curl_setopt($curlSession, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlSession, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);

        $response = curl_exec($curlSession);
        $httpcode = curl_getinfo($curlSession, CURLINFO_HTTP_CODE);
        $errNo = curl_errno($curlSession);
        $errMsg = curl_error($curlSession);
        curl_close($curlSession);

        if ($errNo) {
            ilObjAIChatGUI::sendApiResponse(array("error" => $DIC->language()->txt("rep_robj_xaic_error_http_openai") . ": " . $errMsg), 500);
        }

        if ($httpcode != 200) {
            if ($httpcode === 401) {
                ilObjAIChatGUI::sendApiResponse(array("error" => $DIC->language()->txt("rep_robj_xaic_error_apikey")), 401);
            } else {
                ilObjAIChatGUI::sendApiResponse(array("error" => $DIC->language()->txt("rep_robj_xaic_error_http_openai")), $httpcode);
            }
        }

        $decodedResponse = json_decode($response, true);

        return $decodedResponse['choices'][0]['message']['content'] ?? "";
    }
}