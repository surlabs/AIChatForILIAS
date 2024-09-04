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

namespace objects;

use ai\LLM;
use ai\OpenAI;
use DateTime;
use platform\AIChatConfig;
use platform\AIChatDatabase;
use platform\AIChatException;

/**
 * Class AIChat
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class AIChat
{
    /**
     * @var int
     */
    private int $id = 0;

    /**
     * @var bool
     */
    private bool $online = false;

    /**
     * @var string
     */
    private string $api_key = "";

    /**
     * @var string
     */
    private string $disclaimer = "";
    private LLM $llm;

    public function __construct(?int $id = null)
    {
        if ($id !== null && $id > 0) {
            $this->id = $id;

            $this->loadFromDB();
        }

        $this->loadLLM();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function isOnline(): bool
    {
        return $this->online;
    }

    public function setOnline(bool $online): void
    {
        $this->online = $online;
    }

    /**
     * @throws AIChatException
     */
    public function getApiKey(bool $strict = false): string
    {
        $use_global_api_key = (bool) AIChatConfig::get("use_global_api_key");

        if ($use_global_api_key && !$strict) {
            return AIChatConfig::get("global_api_key");
        }

        return $this->api_key;
    }

    /**
     * @throws AIChatException
     */
    public function setApiKey(string $api_key): void
    {
        $use_global_api_key = (bool) AIChatConfig::get("use_global_api_key");

        if ($use_global_api_key) {
            return;
        }

        $this->api_key = $api_key;
    }

    /**
     * @throws AIChatException
     */
    public function getDisclaimer(bool $strict = false): string
    {
        if ($this->disclaimer != "" || $strict) {
            return $this->disclaimer;
        }

        return AIChatConfig::get("disclaimer_text");
    }

    public function setDisclaimer(string $disclaimer): void
    {
        $this->disclaimer = $disclaimer;
    }

    /**
     * @throws AIChatException
     */
    public function getChatsForApi(?int $user_id = null): array
    {
        $database = new AIChatDatabase();

        $where = [
            "obj_id" => $this->getId(),
        ];

        if (isset($user_id) && $user_id > 0) {
            $where["user_id"] = $user_id;
        }

        $chats = $database->select("xaic_chats", $where, null, "ORDER BY last_update DESC");

        if (empty($chats) && isset($user_id) && $user_id > 0) {
            $chat = new Chat();

            $chat->setObjId($this->getId());
            $chat->setUserId($user_id);

            $chat->save();

            return $this->getChatsForApi($user_id);
        }

        return $chats;
    }

    /**
     * @throws AIChatException
     */
    public function loadFromDB(): void
    {
        $database = new AIChatDatabase();

        $result = $database->select("xaic_objects", ["id" => $this->getId()]);

        if (isset($result[0])) {
            $this->setOnline((bool) $result[0]["online"]);
            $this->setApiKey($result[0]["api_key"]);
            $this->setDisclaimer($result[0]["disclaimer"]);
        }
    }

    /**
     * @throws AIChatException
     */
    public function save(): void
    {
        if (!isset($this->id) || $this->id == 0) {
            throw new AIChatException("AIChat::save() - AIChat ID is 0");
        }

        $database = new AIChatDatabase();

        $database->insertOnDuplicatedKey("xaic_objects", array(
            "id" => $this->id,
            "online" => (int) $this->online,
            "api_key" => $this->api_key,
            "disclaimer" => $this->disclaimer
        ));
    }

    /**
     * @throws AIChatException
     */
    public function delete(): void
    {
        $database = new AIChatDatabase();

        $database->delete("xaic_objects", ["id" => $this->id]);

        $chats = $database->select("xaic_chats", ["obj_id" => $this->id]);

        foreach ($chats as $chat) {
            $chat_obj = new Chat($chat["id"]);

            $chat_obj->delete();
        }
    }

    /**
     * @throws AIChatException
     */
    public function getLLMResponse(Chat $chat): Message
    {
        $llm_response = $this->llm->sendChat($chat);

        $response = new Message();

        $response->setChatId($chat->getId());
        $response->setDate(new DateTime());
        $response->setRole("assistant");
        $response->setMessage($llm_response);

        $response->save();

        return $response;
    }

    /**
     * @throws AIChatException
     */
    private function loadLLM()
    {
        $llm_model_from_config = AIChatConfig::get("llm_model");

        if (!empty($llm_model_from_config)) {
            $llm_model_parts = explode("_", $llm_model_from_config);

            $model_provider = $llm_model_parts[0];
            $model = $llm_model_parts[1];

            switch ($model_provider) {
                case "openai":
                    $this->llm = new OpenAI($model);
                    $this->llm->setApiKey($this->getApiKey());
                    break;
                default:
                    throw new AIChatException("AIChat::loadLLM() - LLM model provider not found (Config: " . $llm_model_from_config . ") (Provider: " . $model_provider . ") (Model: " . $model . ")");
            }
        } else {
            throw new AIChatException("AIChat::loadLLM() - LLM model not found in config");
        }
    }
}