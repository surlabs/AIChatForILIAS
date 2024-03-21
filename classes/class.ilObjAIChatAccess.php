<?php
declare(strict_types=1);

/*
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
class ilObjAIChatAccess extends ilObjectPluginAccess
{

    /**
     * Checks whether a user may invoke a command or not
     * (this method is called by ilAccessHandler::checkAccess)
     * Please do not check any preconditions handled by
     * ilConditionHandler here. Also don't do usual RBAC checks.
     * @param string $cmd        command (not permission!)
     * @param string $permission permission
     * @param int    $ref_id     reference id
     * @param int    $obj_id     object id
     * @param int    $user_id    user id (default is current user)
     * @return bool true, if everything is ok
     */
    public function _checkAccess($cmd, $permission, $ref_id, $obj_id, $user_id = null)
    {
        global $ilUser, $ilAccess;

        if ($user_id === 0) {
            $user_id = $ilUser->getId();
        }

        switch ($permission) {
            case "read":
                if (!self::checkOnline($obj_id) &&
                    !$ilAccess->checkAccessOfUser($user_id, "write", "", $ref_id)) {
                    return false;
                }
                break;
        }

        return true;
    }

    public static function checkOnline($a_id)
    {
        global $ilDB;

        $set = $ilDB->query(
            "SELECT is_online FROM rep_robj_xaic_data " .
            " WHERE id = " . $ilDB->quote($a_id, "integer")
        );
        $rec = $ilDB->fetchAssoc($set);
        return (boolean) $rec["is_online"];
    }

    /**
     * Returns an array with valid operators for the specific object type
     */
    public static function getConditionOperators() : array
    {
        include_once './Services/Conditions/classes/class.ilConditionHandler.php'; //bugfix mantis 24891
        return array(
            ilConditionHandler::OPERATOR_FAILED,
            ilConditionHandler::OPERATOR_PASSED
        );
    }

    /**
     * check condition for a specific user and object
     */
    public static function checkCondition(
        int $a_trigger_obj_id,
        string $a_operator,
        string $a_value,
        int $a_usr_id
    ) : bool {
        $ref_ids = ilObject::_getAllReferences($a_trigger_obj_id);
        $ref_id = array_shift($ref_ids);
        $object = new ilObjAIChat($ref_id);
        switch ($a_operator) {
            case ilConditionHandler::OPERATOR_PASSED:
                return true;
            case ilConditionHandler::OPERATOR_FAILED:
                return false;
        }
        return false;
    }

}