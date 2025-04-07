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

use objects\AIChat;
use platform\AIChatException;

/**
 * Class ilObjAIChatAccess
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class ilObjAIChatAccess extends ilObjectPluginAccess
{

    public function _checkAccess(string $cmd, string $permission, int $ref_id, int $obj_id, ?int $user_id = null): bool
    {
        global $ilUser, $ilAccess;

        if ($user_id === 0) {
            $user_id = $ilUser->getId();
        }

        switch ($permission) {
            case "read":
                if (!self::_isOffline($obj_id) &&
                    !$ilAccess->checkAccessOfUser($user_id, "read", "", $ref_id)) {
                    return false;
                }
                break;
        }

        return true;
    }

    /**
     * Check if the object is offline
     *
     * @param int $a_obj_id
     * @return bool
     */
    public static function _isOffline($a_obj_id): bool
    {
        $aichat = new AIChat((int) $a_obj_id);
        return !$aichat->isOnline();
    }

    public static function getConditionOperators() : array
    {
        include_once './Services/Conditions/classes/class.ilConditionHandler.php';
        return array(
            ilConditionHandler::OPERATOR_FAILED,
            ilConditionHandler::OPERATOR_PASSED
        );
    }

}