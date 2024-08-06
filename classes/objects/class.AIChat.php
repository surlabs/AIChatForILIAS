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

    /**
     * @var Chat[]
     */
    private array $chats = array();

    public function __construct(?int $id = null)
    {
        if ($id !== null && $id > 0) {
            $this->id = $id;

            $this->loadFromDB();
        }
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
    public function getApiKey(): string
    {
        $use_global_api_key = (bool) AIChatConfig::get("use_global_api_key");

        if ($use_global_api_key) {
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

    public function getDisclaimer(): string
    {
        return $this->disclaimer;
    }

    public function setDisclaimer(string $disclaimer): void
    {
        $this->disclaimer = $disclaimer;
    }

    public function getChats(): array
    {
        return $this->chats;
    }

    public function setChats(array $chats): void
    {
        $this->chats = $chats;
    }

    public function getChat(int $id): ?Chat
    {
        foreach ($this->chats as $chat) {
            if ($chat->getId() === $id) {
                return $chat;
            }
        }

        return null;
    }

    public function addChat(Chat $chat): void
    {
        $this->chats[] = $chat;
    }

    /**
     * @throws AIChatException
     */
    public function deleteChat(int $id): void
    {
        foreach ($this->chats as $key => $chat) {
            if ($chat->getId() === $id) {
                $chat->delete();

                unset($this->chats[$key]);
            }
        }
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

        $chats = $database->select("xaic_chats", ["obj_id" => $this->getId()], ["id"]);

        foreach ($chats as $chat) {
            $this->addChat(new Chat($chat["id"]));
        }
    }

    /**
     * @throws AIChatException
     */
    public function save(): void
    {
        $database = new AIChatDatabase();

        $data = [
            "online" => $this->isOnline(),
            "api_key" => $this->getApiKey(),
            "disclaimer" => $this->getDisclaimer()
        ];

        if ($this->getId() > 0) {
            $database->update("xaic_objects", $data, ["id" => $this->getId()]);
        } else {
            $id = $database->nextId("xaic_objects");

            $this->setId($id);

            $data["id"] = $id;

            $database->insert("xaic_objects", $data);
        }

        foreach ($this->getChats() as $chat) {
            $chat->setObjId($this->getId());

            $chat->save();
        }
    }

    /**
     * @throws AIChatException
     */
    public function delete(): void
    {
        $database = new AIChatDatabase();

        $database->delete("xaic_objects", ["id" => $this->id]);

        foreach ($this->getChats() as $chat) {
            $chat->delete();
        }
    }
}