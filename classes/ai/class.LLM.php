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

use objects\Chat;
use platform\AIChatConfig;
use platform\AIChatException;

/**
 * Class LLM
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
abstract class LLM
{
    public abstract function sendChat(Chat $chat);

    /**
     * @throws AIChatException
     */
    protected function chatToMessagesArray(Chat $chat): array
    {
        $messages = [];

        foreach ($chat->getMessages() as $message) {
            $messages[] = [
                "role" => $message->getRole(),
                "content" => $message->getMessage()
            ];
        }

        $n_memory_messages = AIChatConfig::get("n_memory_messages");

        if (isset($n_memory_messages)) {
            $n_memory_messages = intval($n_memory_messages);
        } else {
            $n_memory_messages = 0;
        }

        if ($n_memory_messages > 0) {
            $messages = array_slice($messages, -$n_memory_messages);
        }

        $prompt = AIChatConfig::get("prompt_selection");

        if (isset($prompt) && !empty(trim($prompt))) {
            array_unshift($messages, [
                "role" => "system",
                "content" => $prompt
            ]);
        }

        return $messages;
    }
}