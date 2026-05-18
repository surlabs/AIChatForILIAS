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

namespace AIChat\classes\objects;

use AIChat\classes\ai\GWDG;
use AIChat\classes\ai\LLM;
use AIChat\classes\ai\OpenAI;
use AIChat\classes\ai\Ollama;
use DateTime;
use ilSession;
use platform\AIChatConfig;
use platform\AIChatDatabase;
use platform\AIChatException;
use platform\SurContextException;
use AIChat\classes\objects\Chat;

/**
 * Class AIChat
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class AIChat
{
    private int $id = 0;
    private bool $online = false;
    private string $prompt = "";
    private string $disclaimer = "";
    private int $max_memory_messages = 0;
    private int $characters_limit = 0;
    private string $openai_model = "";
    private string $openai_api_key = "";
    private bool $openai_streaming = false;
    private string $ollama_model = "";
    private string $service_to_use = "";
    private string $gwdg_model = "";
    private bool $gwdg_streaming = false;
    private ?LLM $llm = null;

    /**
     * @throws AIChatException
     */
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
    public function getPrompt(bool $strict = false): string
    {
        if ($this->prompt != "" || $strict) {
            return $this->prompt;
        }

        return AIChatConfig::get("prompt");
    }

    public function setPrompt(string $prompt): void
    {
        $this->prompt = $prompt;
    }

    /**
     * @throws AIChatException
     */
    public function getDisclaimer(bool $strict = false): string
    {
        if ($this->disclaimer != "" || $strict) {
            return $this->disclaimer;
        }

        return AIChatConfig::get("disclaimer");
    }

    public function setDisclaimer(string $disclaimer): void
    {
        $this->disclaimer = $disclaimer;
    }

    /**
     * @throws AIChatException
     */
    public function getMaxMemoryMessages(bool $strict = false): int
    {
        if ($this->max_memory_messages != 0 || $strict) {
            return $this->max_memory_messages;
        }

        if (!empty(AIChatConfig::get("max_memory_messages"))) {
            return AIChatConfig::get("max_memory_messages");
        }

        return 100;
    }

    public function setMaxMemoryMessages(int $max_memory_messages): void
    {
        $this->max_memory_messages = $max_memory_messages;
    }

    /**
     * @throws AIChatException
     */
    public function getCharactersLimit(bool $strict = false): int
    {
        if ($this->characters_limit != 0 || $strict) {
            return $this->characters_limit;
        }

        if (!empty(AIChatConfig::get("characters_limit"))) {
            return AIChatConfig::get("characters_limit");
        }

        return 2000;
    }

    public function setCharactersLimit(int $characters_limit): void
    {
        $this->characters_limit = $characters_limit;
    }

    /**
     * @throws AIChatException
     */
    public function getOpenaiModel(bool $strict = false): string
    {
        if ($this->openai_model != "" || $strict) {
            $openaiModel = $this->openai_model;
            if (!array_key_exists($openaiModel, OpenAI::MODEL_TYPES)) {
                $openaiModel = array_key_first(OpenAI::MODEL_TYPES);
            }
            return $openaiModel;
        }

        return AIChatConfig::get("openai_model");
    }

    public function setOpenaiModel(string $openai_model): void
    {
        $this->openai_model = $openai_model;
    }

    /**
     * @throws AIChatException
     */
    public function getOpenaiApiKey(bool $strict = false): string
    {
        if ($this->openai_api_key != "" || $strict) {
            return $this->openai_api_key;
        }

        return AIChatConfig::get("openai_api_key");
    }

    public function setOpenaiApiKey(string $openai_api_key): void
    {
        $this->openai_api_key = $openai_api_key;
    }

    /**
     * @throws AIChatException
     */
    public function isOpenaiStreaming(bool $strict = false): bool
    {
        if ($this->getServiceToUse() != "openai") {
            return false;
        }

        if ($this->openai_streaming || $strict) {
            return $this->openai_streaming;
        }

        return AIChatConfig::get("openai_streaming") == "1";
    }

    public function setOpenaiStreaming(bool $openai_streaming): void
    {
        $this->openai_streaming = $openai_streaming;
    }

    /**
     * @throws AIChatException
     */
    public function getOllamaModel(bool $strict = false): string
    {
        if ($this->ollama_model != "" || $strict) {
            return $this->ollama_model;
        }

        return AIChatConfig::get("ollama_model");
    }

    public function setOllamaModel(string $ollama_model): void
    {
        $this->ollama_model = $ollama_model;
    }

    /**
     * @throws AIChatException
     */
    public function getOllamaModelsList(): array
    {
        if (!empty(AIChatConfig::get("ollama_models"))) {
            return AIChatConfig::get("ollama_models");
        }

        return [];
    }

    public function getServiceToUse(bool $strict = false): string
    {
        $available_services = AIChatConfig::get("available_services");

        if (($this->service_to_use != "" && isset($available_services[$this->service_to_use]) && $available_services[$this->service_to_use]) || $strict) {
            return $this->service_to_use;
        }

        foreach ($available_services as $service => $available) {
            if ($available) {
                return $service;
            }
        }

        return "";
    }

    public function setServiceToUse(string $service_to_use): void
    {
        $this->service_to_use = $service_to_use;
    }

    public function getGwdgModel(bool $strict = false): string
    {
        if ($this->gwdg_model != "" || $strict) {
            return $this->gwdg_model;
        }

        return AIChatConfig::get("gwdg_model");
    }

    public function setGwdgModel(string $gwdg_model): void
    {
        $this->gwdg_model = $gwdg_model;
    }

    public function isGwdgStreaming(bool $strict = false): bool
    {
        if ($this->getServiceToUse() != "gwdg") {
            return false;
        }

        if ($this->gwdg_streaming || $strict) {
            return $this->gwdg_streaming;
        }

        return AIChatConfig::get("gwdg_streaming") == "1";
    }

    public function setGwdgStreaming(bool $gwdg_streaming): void
    {
        $this->gwdg_streaming = $gwdg_streaming;
    }

    public function getGwdgModelsList(): array
    {
        if (!empty(AIChatConfig::get("gwdg_models"))) {
            return AIChatConfig::get("gwdg_models");
        }

        return [];
    }

    public function getLlm(): ?LLM
    {
        return $this->llm;
    }

    public function setLlm(?LLM $llm = null): void
    {
        $this->llm = $llm;
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
            $this->setPrompt((string) $result[0]["prompt"]);
            $this->setDisclaimer((string) $result[0]["disclaimer"]);
            $this->setMaxMemoryMessages((int) $result[0]["max_memory_messages"]);
            $this->setCharactersLimit((int) $result[0]["characters_limit"]);
            $this->setOpenaiModel((string) $result[0]["openai_model"]);
            $this->setOpenaiApiKey((string) $result[0]["openai_api_key"]);
            $this->setOpenaiStreaming((bool) $result[0]["openai_streaming"]);
            $this->setOllamaModel((string) $result[0]["ollama_model"]);
            $this->setServiceToUse($result[0]["service_to_use"]);
            $this->setGwdgModel((string) $result[0]["gwdg_model"]);
            $this->setGwdgStreaming((bool) $result[0]["gwdg_streaming"]);
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
            "prompt" => $this->prompt,
            "disclaimer" => $this->disclaimer,
            "max_memory_messages" => $this->max_memory_messages,
            "characters_limit" => $this->characters_limit,
            "openai_model" => $this->openai_model,
            "openai_api_key" => $this->openai_api_key,
            "openai_streaming" => (int) $this->openai_streaming,
            "ollama_model" => $this->ollama_model,
            "service_to_use" => $this->service_to_use,
            "gwdg_model" => $this->gwdg_model,
            "gwdg_streaming" => (int) $this->gwdg_streaming,
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
    public function getChatsForApi(?int $user_id = null): array
    {
        $chats = [];

        if ($user_id != ANONYMOUS_USER_ID) {
            $database = new AIChatDatabase();

            $where = [
                "obj_id" => $this->getId(),
            ];

            if (isset($user_id) && $user_id > 0) {
                $where["user_id"] = $user_id;
            }
            // dump($this->getId(), "hey", $database->select("xaic_chats", $where, null, "ORDER BY last_update DESC"));
            $chats = $database->select("xaic_chats", $where, null, "ORDER BY last_update DESC");
        } else {
            $chats_array = ilSession::get("xaic_chats") ?? [];

            if (!empty($chats_array)) {
                foreach ($chats_array as $chat) {
                    if ($chat["obj_id"] == $this->getId()) {
                        $chats[] = $chat;
                    }
                }
            }
        }

        if (empty($chats) && isset($user_id) && $user_id > 0) {

            $chat = new Chat();

            $chat->setMaxMessages($this->getMaxMemoryMessages());

            $chat->setObjId($this->getId());
            $chat->setUserId($user_id);

            if ($user_id != ANONYMOUS_USER_ID) {
                // dump("hay", $chat);exit();
                try {
                    $chat->save();
                } catch (AIChatException $e) {
                    // dump("AIChatException", $e);exit();
                } catch (SurContextException $e) {
                    // dump("SurContextException", $e);exit();
                }
            } else {
                $chat->saveToSession();
            }


            return $this->getChatsForApi($user_id);
        }

        return $chats;
    }

    /**
     * @throws AIChatException
     */
    private function loadLLM()
    {
        $service_to_use = $this->getServiceToUse();

        if (!empty($service_to_use)) {
            switch ($service_to_use) {
                case "openai":
                    $this->llm = new OpenAI($this->getOpenaiModel());
                    $this->llm->setApiKey($this->getOpenaiApiKey());
                    $this->llm->setMaxMemoryMessages($this->getMaxMemoryMessages());
                    $this->llm->setPrompt($this->getPrompt());
                    $this->llm->setStreaming($this->isOpenaiStreaming());
                    break;
                case "ollama":
                    $models = $this->getOllamaModelsList();
                    $model = $this->getOllamaModel();

                    if (in_array($model, $models)) {
                        $this->llm = new Ollama($model);
                        $this->llm->setEndpoint(AIChatConfig::get("ollama_endpoint"));
                        $this->llm->setMaxMemoryMessages($this->getMaxMemoryMessages());
                        $this->llm->setPrompt($this->getPrompt());
                    }
                    break;
                case "gwdg":
                    $models = $this->getGwdgModelsList();
                    $model = $this->getGwdgModel();

                    if (in_array($model, $models) || array_key_exists($model, $models)) {
                        $this->llm = new GWDG($model);
                        $this->llm->setApiKey(AIChatConfig::get("gwdg_api_key"));
                        $this->llm->setMaxMemoryMessages($this->getMaxMemoryMessages());
                        $this->llm->setPrompt($this->getPrompt());
                        $this->llm->setStreaming($this->isGwdgStreaming());
                    }
                    break;
                default:
                    throw new AIChatException("AIChat::loadLLM() - LLM service to use not valid (Service: " . $service_to_use . ")");
            }
        }
    }

    public function isStreamingEnabled(): bool
    {
        switch ($this->getServiceToUse()) {
            case "openai":
                return (bool) $this->isOpenaiStreaming();
            case "gwdg":
                return (bool) $this->isGwdgStreaming();
            default:
                return false;
        }
    }

    /**
     * @throws AIChatException
     */
    public function getLLMResponse(Chat $chat): Message
    {
        global $DIC;

        $llm_response = $this->llm->sendChat($chat);

        $response = new Message();

        $response->setChatId($chat->getId());
        $response->setDate(new DateTime());
        $response->setRole("assistant");
        $response->setMessage($llm_response);

        if ($DIC->user()->getId() != ANONYMOUS_USER_ID) {
            $response->save();
        } else {
            $response->saveToSession();
        }

        return $response;
    }
}