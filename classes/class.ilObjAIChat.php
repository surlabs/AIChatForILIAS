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

use platform\AIChatException;
use objects\AIChat;

/**
 * Class ilObjAIChat
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class ilObjAIChat extends ilObjectPlugin
{
    private AIChat $ai_chat;

    /**
     * Creates a new object
     * @param bool $clone_mode
     * @throws AIChatException
     */
    protected function doCreate(bool $clone_mode = false): void
    {
        $this->ai_chat = new AIChat($this->getId());

        $this->ai_chat->save();
    }

    /**
     * Read the object
     */
    protected function doRead(): void
    {
        $this->ai_chat = new AIChat($this->getId());
    }

    /**
     * Deletes the object
     * @throws AIChatException
     */
    protected function doDelete(): void
    {
        $this->ai_chat->delete();
    }

    /**
     * Updates the object
     * @throws AIChatException
     */
    protected function doUpdate(): void
    {
        $this->ai_chat->save();
    }

    protected function initType(): void
    {
        $this->setType(ilAIChatPlugin::PLUGIN_ID);
    }
}