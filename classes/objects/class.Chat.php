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

use DateTime;
use Exception;
use platform\AIChatDatabase;
use platform\AIChatException;

/**
 * Class Chat
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class Chat
{
    /**
     * @var int
     */
    private int $id = 0;

    /**
     * @var int
     */
    private int $obj_id = 0;

    /**
     * @var string
     */
    private string $title;

    /**
     * @var DateTime
     */
    private DateTime $created_at;

    /**
     * @var int
     */
    private int $user_id = 0;

    /**
     * @var DateTime
     */
    private DateTime $last_update;

    /**
     * @var Message[]
     */
    private array $messages = array();
    private ?int $max_messages = null;

    public function __construct(?int $id = null)
    {
        $this->created_at = new DateTime();
        $this->last_update = new DateTime();

        $this->setTitle();

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

    public function getObjId(): int
    {
        return $this->obj_id;
    }

    public function setObjId(int $obj_id): void
    {
        $this->obj_id = $obj_id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(?string $title = null): void
    {
        if ($title === null) {
            global $DIC;
            $title = $DIC->language()->txt("rep_robj_xaic_chat_default_title");
        }
        
        $this->title = $title;
    }

    public function setTitleFromMessage(string $message)
    {
        if (strlen($message) > 100)
            $message = substr($message, 0, 100) . "...";

        $this->setTitle($message);
    }

    public function getCreatedAt(): DateTime
    {
        return $this->created_at;
    }

    public function setCreatedAt(DateTime $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getLastUpdate(): DateTime
    {
        return $this->last_update;
    }

    public function setLastUpdate(DateTime $last_update): void
    {
        $this->last_update = $last_update;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function addMessage(Message $message): void
    {
        $this->messages[] = $message;
    }

    public function setMaxMessages(int $max_messages): void
    {
        $this->max_messages = $max_messages;
    }

    public function getMaxMessages(): ?int
    {
        return $this->max_messages;
    }

    /**
     * @throws AIChatException
     * @throws Exception
     */
    public function loadFromDB(): void
    {
        $database = new AIChatDatabase();

        $result = $database->select("xaic_chats", ["id" => $this->getId()]);

        if (isset($result[0])) {
            $this->setObjId((int)$result[0]["obj_id"]);
            $this->setTitle($result[0]["title"]);
            $this->setCreatedAt(new DateTime($result[0]["created_at"]));
            $this->setUserId((int)$result[0]["user_id"]);
            $this->setLastUpdate(new DateTime($result[0]["last_update"]));
        }

        $messages = $database->select("xaic_messages", ["chat_id" => $this->getId()], ["id"], "ORDER BY date ASC");

        foreach ($messages as $message) {
            $this->addMessage(new Message((int)$message["id"]));
        }
    }

    /**
     * @throws AIChatException
     */
    public function save(): void
    {
        $database = new AIChatDatabase();

        $data = [
            "obj_id" => $this->getObjId(),
            "title" => $this->getTitle(),
            "created_at" => $this->getCreatedAt()->format("Y-m-d H:i:s"),
            "user_id" => $this->getUserId(),
            "last_update" => $this->getLastUpdate()->format("Y-m-d H:i:s")
        ];

        if ($this->getId() > 0) {
            $database->update("xaic_chats", $data, ["id" => $this->getId()]);
        } else {
            $id = $database->nextId("xaic_chats");

            $this->setId($id);

            $data["id"] = $id;

            $database->insert("xaic_chats", $data);
        }
    }

    /**
     * @throws AIChatException
     */
    public function delete(): void
    {
        $database = new AIChatDatabase();

        $database->delete("xaic_chats", ["id" => $this->getId()]);

        foreach ($this->messages as $message) {
            $message->delete();
        }
    }

    /**
     * @throws AIChatException
     */
    public function toArray(): array
    {
        $messages = array();

        foreach ($this->messages as $message) {
            $messages[] = $message->toArray();
        }

        $n_memory_messages = $this->getMaxMessages();

        if (isset($n_memory_messages)) {
            $n_memory_messages = intval($n_memory_messages);
        } else {
            $n_memory_messages = 0;
        }

        if ($n_memory_messages > 0) {
            $messages = array_slice($messages, -$n_memory_messages);
        }

        return [
            "id" => $this->getId(),
            "obj_id" => $this->getObjId(),
            "title" => $this->getTitle(),
            "created_at" => $this->getCreatedAt()->format("Y-m-d H:i:s"),
            "user_id" => $this->getUserId(),
            "messages" => $messages,
            "last_update" => $this->getLastUpdate()->format("Y-m-d H:i:s")
        ];
    }
}