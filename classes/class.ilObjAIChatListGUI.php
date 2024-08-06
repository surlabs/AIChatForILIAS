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

/**
 * Class ilObjAIChatListGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class ilObjAIChatListGUI extends ilObjectPluginListGUI
{
    public function getGuiClass(): string
    {
        return 'ilObjAIChatGUI';
    }

    public function initCommands(): array
    {
        return [
            [
                "permission" => "read",
                "cmd" => "content",
                "default" => true,
            ],
            [
                "permission" => "write",
                "cmd" => "settings",
                "txt" => $this->txt("object_settings")
            ]
        ];
    }

    public function initType()
    {
        $this->setType(ilAIChatPlugin::PLUGIN_ID);
    }

    public function getCustomProperties($a_prop): array
    {
        if (!isset($this->obj_id)) {
            return [];
        }

        $props = parent::getCustomProperties($a_prop);

        if (ilObjAIChatAccess::_isOffline($this->obj_id)) {
            $props[] = array(
                'alert' => true,
                'newline' => true,
                'property' => 'Status',
                'value' => 'Offline'
            );
        }

        return $props;
    }
}