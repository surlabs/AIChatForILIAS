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

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use platform\AIChatConfig;
use platform\AIChatException;

/**
 * Class ilObjAIChatGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 * @ilCtrl_isCalledBy ilObjAIChatGUI: ilRepositoryGUI, ilObjPluginDispatchGUI, ilAdministrationGUI
 * @ilCtrl_Calls      ilObjAIChatGUI: ilObjectCopyGUI, ilPermissionGUI, ilInfoScreenGUI, ilCommonActionDispatcherGUI
 */
class ilObjAIChatGUI extends ilObjectPluginGUI
{
    private Factory $factory;
    private Renderer $renderer;
    private \ILIAS\Refinery\Factory $refinery;

    public function __construct($a_ref_id = 0, $a_id_type = self::REPOSITORY_NODE_ID, $a_parent_node_id = 0)
    {
        global $DIC;

        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->refinery = $DIC->refinery();

        parent::__construct($a_ref_id, $a_id_type, $a_parent_node_id);
    }

    public function getAfterCreationCmd(): string
    {
        return 'content';
    }

    public function getStandardCmd(): string
    {
        return 'content';
    }

    public function performCommand(string $cmd): void
    {
        $this->{$cmd}();
    }

    public function getType(): string
    {
        return ilAIChatPlugin::PLUGIN_ID;
    }

    protected function setTabs(): void
    {
        $this->tabs->addTab("content", $this->plugin->txt("object_content"), $this->ctrl->getLinkTarget($this, "content"));

        if ($this->checkPermissionBool("write")) {
            $this->tabs->addTab("settings", $this->plugin->txt("object_settings"), $this->ctrl->getLinkTarget($this, "settings"));
        }

        if ($this->checkPermissionBool("edit_permission")) {
            $this->tabs->addTab("perm_settings", $this->lng->txt("perm_settings"), $this->ctrl->getLinkTargetByClass(array(
                get_class($this),
                "ilPermissionGUI",
            ), "perm"));
        }
    }

    private function index()
    {
        $this->content();
    }

    /**
     * @throws ilTemplateException
     */
    private function content()
    {
        global $DIC;
        $this->tabs->activateTab("content");
        $tpl = $DIC['tpl'];
        //$tpl = new ilTemplate("index.html", false, false, $this->plugin->getDirectory());
        $tpl->addCss($this->plugin->getDirectory() . "/templates/default/index.css");
        $tpl->addJavascript($this->plugin->getDirectory() . "/templates/default/index.js");

        $this->tpl->setContent("<div id='root'></div>");
    }

    private function settings()
    {
        $this->tabs->activateTab("settings");

        $form_action = $this->ctrl->getLinkTargetByClass("ilObjAIChatGUI", "settings");
        $this->tpl->setContent($this->renderSettingsForm($form_action, $this->buildSettingsForm()));
    }

    private function renderSettingsForm(string $form_action, array $sections): string
    {
        $form = $this->factory->input()->container()->form()->standard(
            $form_action,
            $sections
        );

        $saving_info = "";

        if ($this->request->getMethod() == "POST") {
            $form = $form->withRequest($this->request);
            $result = $form->getData();
            if ($result) {
                $saving_info = $this->saveSettings();
            }
        }

        return $saving_info . $this->renderer->render($form);
    }

    /**
     * @throws AIChatException
     */
    private function buildSettingsForm(): array
    {
        $inputs_basic = array();
        $inputs_advanced = array();

        $inputs_basic[] = $this->factory->input()->field()->text(
            $this->plugin->txt('object_settings_title')
        )->withValue($this->object->getTitle())->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                $this->object->setTitle($v);
            }
        ))->withRequired(true);

        $inputs_basic[] = $this->factory->input()->field()->textarea(
            $this->plugin->txt('object_settings_description')
        )->withValue($this->object->getDescription())->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                $this->object->setDescription($v);
            }
        ))->withRequired(true);

        $inputs_basic[] = $this->factory->input()->field()->checkbox(
            $this->plugin->txt('object_settings_online'), $this->plugin->txt('object_settings_online_info')
        )->withValue($this->object->getAIChat()->isOnline())->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                $this->object->getAIChat()->setOnline($v);
            }
        ));

        if (AIChatConfig::get("use_global_api_key") != "1") {
            $inputs_advanced[] = $this->factory->input()->field()->text(
                $this->plugin->txt('object_settings_api_key'), $this->plugin->txt('object_settings_api_key_info')
            )->withValue($this->object->getAIChat()->getApiKey())->withAdditionalTransformation($this->refinery->custom()->transformation(
                function ($v) {
                    $this->object->getAIChat()->setApiKey($v);
                }
            ))->withRequired(true);
        }

        $inputs_advanced[] = $this->factory->input()->field()->textarea(
            $this->plugin->txt('object_settings_disclaimer_text'), $this->plugin->txt('object_settings_disclaimer_text_info')
        )->withValue($this->object->getAIChat()->getDisclaimer())->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                $this->object->getAIChat()->setDisclaimer($v);
            }
        ));

        return array(
            $this->factory->input()->field()->section($inputs_basic, $this->plugin->txt("object_settings_basic"), ""),
            $this->factory->input()->field()->section($inputs_advanced, $this->plugin->txt("object_settings_advanced"), "")
        );
    }

    private function saveSettings(): string
    {
        global $DIC;

        $renderer = $DIC->ui()->renderer();

        $this->object->update();

        return $renderer->render($DIC->ui()->factory()->messageBox()->success($this->plugin->txt('object_settings_msg_success')));
    }
}