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
 * Class Message
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class Message
{
    /**
     * @var int
     */
    private int $id = 0;

    /**
     * @var int
     */
    private int $chat_id;

    /**
     * @var DateTime
     */
    private DateTime $date;

    /**
     * @var string
     */
    private string $role;

    /**
     * @var string
     */
    private string $message;

    public function __construct(?int $id = null)
    {
        $this->date = new DateTime();

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

    public function getChatId(): int
    {
        return $this->chat_id;
    }

    public function setChatId(int $chat_id): void
    {
        $this->chat_id = $chat_id;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function setDate(DateTime $date): void
    {
        $this->date = $date;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    /**
     * @throws AIChatException
     * @throws Exception
     */
    public function loadFromDB(): void
    {
        $database = new AIChatDatabase();

        $result = $database->select("xaic_messages", ["id" => $this->getId()]);

        if (isset($result[0])) {
            $this->setChatId((int)$result[0]["chat_id"]);
            $this->setDate(new DateTime($result[0]["date"]));
            $this->setRole($result[0]["role"]);
            $this->setMessage($result[0]["message"]);
        }
    }

    /**
     * @throws AIChatException
     */
    public function save(): void
    {
        $database = new AIChatDatabase();

        $data = [
            "chat_id" => $this->getChatId(),
            "date" => $this->getDate()->format("Y-m-d H:i:s"),
            "role" => $this->getRole(),
            "message" => $this->getMessage()
        ];

        if ($this->getId() > 0) {
            $database->update("xaic_messages", $data, ["id" => $this->getId()]);
        } else {
            $id = $database->nextId("xaic_messages");

            $this->setId($id);

            $data["id"] = $id;

            $database->insert("xaic_messages", $data);
        }
    }

    /**
     * @throws AIChatException
     */
    public function delete(): void
    {
        $database = new AIChatDatabase();

        $database->delete("xaic_messages", ["id" => $this->getId()]);
    }
}