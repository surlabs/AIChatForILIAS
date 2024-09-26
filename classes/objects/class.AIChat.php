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
use ai\CustomAI;
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
    private int $id = 0;
    private bool $online = false;
    private string $provider = "";
    private string $model = "";
    private string $api_key = "";
    private ?bool $streaming = null;
    private string $url = "";
    private string $prompt = "";
    private int $char_limit = 0;
    private int $max_memory_messages = 0;
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
    public function getProvider(bool $strict = false): string
    {
        if (empty($this->provider) && !$strict) {
            return AIChatConfig::get("llm_provider") != "" ? AIChatConfig::get("llm_provider") : "openai";
        }

        return $this->provider;
    }

    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
    }

    /**
     * @throws AIChatException
     */
    public function getModel(bool $strict = false): string
    {
        if (empty($this->model) && !$strict) {
            return AIChatConfig::get("llm_model") ?? "davinci";
        }

        return $this->model;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    /**
     * @throws AIChatException
     */
    public function getApiKey(bool $strict = false): string
    {
        if (empty($this->api_key) && !$strict) {
            return AIChatConfig::get("global_api_key");
        }

        return $this->api_key;
    }

    public function setApiKey(string $api_key): void
    {
        $this->api_key = $api_key;
    }

    /**
     * @throws AIChatException
     */
    public function isStreaming(bool $strict = false): bool
    {
        if (!isset($this->streaming) && !$strict) {
            return (bool) AIChatConfig::get("streaming_enabled");
        }

        return $this->streaming ?? false;
    }

    public function setStreaming(bool $streaming): void
    {
        $this->streaming = $streaming;
    }

    /**
     * @throws AIChatException
     */
    public function getUrl(bool $strict = false): string
    {
        if (empty($this->url) && !$strict) {
            return AIChatConfig::get("llm_url");
        }

        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @throws AIChatException
     */
    public function getPrompt(bool $strict = false): string
    {
        if (empty($this->prompt) && !$strict) {
            return AIChatConfig::get("prompt_selection");
        }

        return $this->prompt;
    }

    public function setPrompt(string $prompt): void
    {
        $this->prompt = $prompt;
    }

    /**
     * @throws AIChatException
     */
    public function getCharLimit(bool $strict = false): int
    {
        if ($this->char_limit == 0 && !$strict) {
            return AIChatConfig::get("characters_limit") != "" ? (int) AIChatConfig::get("characters_limit") : 100;
        }

        return $this->char_limit;
    }

    public function setCharLimit(?int $char_limit = null): void
    {
        if ($char_limit == null) {
            $char_limit = 0;
        }

        $this->char_limit = $char_limit;
    }

    /**
     * @throws AIChatException
     */
    public function getMaxMemoryMessages(bool $strict = false): int
    {
        if ($this->max_memory_messages == 0 && !$strict) {
            return AIChatConfig::get("n_memory_messages") != "" ? (int) AIChatConfig::get("n_memory_messages") : 5;
        }

        return $this->max_memory_messages;
    }

    public function setMaxMemoryMessages(?int $max_memory_messages = null): void
    {
        if ($max_memory_messages == null) {
            $max_memory_messages = 0;
        }

        $this->max_memory_messages = $max_memory_messages;
    }

    /**
     * @throws AIChatException
     */
    public function getDisclaimer(bool $strict = false): string
    {
        if (empty($this->disclaimer) && !$strict) {
            return AIChatConfig::get("disclaimer_text");
        }

        return $this->disclaimer;
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

            $chat->setMaxMessages($this->getMaxMemoryMessages());

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
            $this->setProvider((string) $result[0]["provider"]);
            $this->setModel((string) $result[0]["model"]);
            $this->setApiKey((string) $result[0]["api_key"]);
            $this->setStreaming((bool) $result[0]["streaming"]);
            $this->setUrl((string) $result[0]["url"]);
            $this->setPrompt((string) $result[0]["prompt"]);
            $this->setCharLimit((int) $result[0]["char_limit"]);
            $this->setMaxMemoryMessages((int) $result[0]["max_memory_messages"]);
            $this->setDisclaimer((string) $result[0]["disclaimer"]);
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
            "provider" => $this->provider,
            "model" => $this->model,
            "api_key" => $this->api_key,
            "streaming" => $this->streaming,
            "url" => $this->url,
            "prompt" => $this->prompt,
            "char_limit" => $this->char_limit,
            "max_memory_messages" => $this->max_memory_messages,
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
        $provider = $this->getProvider();
        $model = $this->getModel();

        if (!empty($provider) && !empty($model)) {
            switch ($provider) {
                case "openai":
                    $this->llm = new OpenAI($model);
                    $this->llm->setApiKey($this->getApiKey());
                    $this->llm->setMaxMemoryMessages($this->getMaxMemoryMessages());
                    $this->llm->setPrompt($this->getPrompt());
                    break;
                case "custom":
                    $this->llm = new CustomAI($model);
                    $this->llm->setApiKey($this->getApiKey());
                    $this->llm->setUrl($this->getURL());
                    $this->llm->setMaxMemoryMessages($this->getMaxMemoryMessages());
                    $this->llm->setPrompt($this->getPrompt());
                    break;
                default:
                    throw new AIChatException("AIChat::loadLLM() - LLM model provider not found (Provider: " . $provider . ") (Model: " . $model . ")");
            }
        } else {
            throw new AIChatException("AIChat::loadLLM() - LLM provider or model not found in config");
        }
    }
}