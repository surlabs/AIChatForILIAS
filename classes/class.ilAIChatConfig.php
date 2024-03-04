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

class ilAIChatConfig
{
    protected bool $use_global_apikey = false;
    protected string $api_key = '';
    protected string $model = 'gpt-4';

    public function setValue($key, $value): void
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        if (!is_string($this->getValue($key))) {
            $ilDB->insert(
                "rep_robj_xaic_config", array(
                'key_setting' => array("text", $key),
                'value_setting' => array("text", $value)
            ));
        } else {
            $ilDB->update(
                "rep_robj_xaic_config", array(
                'value_setting' => array("text", $value)
            ), array(
                    'key_setting' => array("text", $key)
                )
            );
        }

        switch ($key):
            case 'global_apikey':
                $this->setUseGlobalApikey($value);
                break;
            case 'apikey':
                $this->setApikey($value);
                break;
            case 'model':
                $this->setModel($value);
                break;
        endswitch;
    }

    /**
     *
     * @return bool|string
     */
    public function getValue($key)
    {
        global $DIC;
        $ilDB = $DIC['ilDB'];

        $result = $ilDB->query("SELECT value_setting FROM rep_robj_xaic_config where key_setting='$key'");
        if ($result->numRows() == 0) {
            return false;
        }

        $row = $result->fetchAssoc();
        $record = $row['value_setting'];

        return (string)$record;
    }

    public function getUseGlobalApikey(): bool
    {
        return $this->use_global_apikey;
    }

    public function setUseGlobalApikey(bool $use_global_apikey): void
    {
        $this->use_global_apikey = $use_global_apikey;
    }

    public function getApikey(): string
    {
        return $this->api_key;
    }

    public function setApikey(string $apikey): void
    {
        $this->api_key = $apikey;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }
}