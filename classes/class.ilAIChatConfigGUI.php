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
 * Class ilAIChatConfigGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 * @ilCtrl_IsCalledBy  ilAIChatConfigGUI: ilObjComponentSettingsGUI
 */
class ilAIChatConfigGUI extends ilPluginConfigGUI
{
    protected Factory $factory;
    protected Renderer $renderer;
    protected \ILIAS\Refinery\Factory $refinery;
    protected ilCtrl $control;
    protected ilGlobalTemplateInterface $tpl;
    protected $request;

    /**
     * @throws AIChatException
     * @throws ilException
     */
    public function performCommand($cmd): void
    {
        global $DIC;
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->refinery = $DIC->refinery();
        $this->control = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->request = $DIC->http()->request();

        switch ($cmd) {
            case "configure":
                AIChatConfig::load();
                $this->control->setParameterByClass('ilAIChatConfigGUI', 'cmd', 'configure');
                $form_action = $this->control->getLinkTargetByClass("ilAIChatConfigGUI", "configure");
                $rendered = $this->renderForm($form_action, $this->buildForm());
                break;
            default:
                throw new ilException("command not defined");

        }

        $this->tpl->setContent($rendered);
    }

    /**
     * @throws AIChatException
     */
    private function buildForm(): array
    {
        $prompt_selection = $this->factory->input()->field()->textarea(
            $this->plugin_object->txt('config_prompt_selection'),
            $this->plugin_object->txt('config_prompt_selection_info')
        )->withValue((string) AIChatConfig::get("prompt_selection"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('prompt_selection', $v);
            }
        ))->withRequired(true);

        $model = $this->factory->input()->field()->select(
            $this->plugin_object->txt('config_model'),
            array(
                "openai_gpt-4o" => "GPT-4o",
                "openai_gpt-4o-mini" => "GPT-4o mini",
                "openai_gpt-4-turbo" => "GPT-4 Turbo",
                "openai_gpt-4" => "GPT-4",
                "openai_gpt-3.5-turbo" => "GPT-3.5 Turbo"
            ),
            $this->plugin_object->txt('config_model_info')
        )->withValue((string) AIChatConfig::get("llm_model"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('llm_model', $v);
            }
        ))->withRequired(true);

        $characters_limit = $this->factory->input()->field()->numeric(
            $this->plugin_object->txt('config_characters_limit'), $this->plugin_object->txt('config_characters_limit_info')
        )->withValue((int) AIChatConfig::get("characters_limit") ?? 0)->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('characters_limit', $v);
            }
        ))->withRequired(true);

        $n_memory_messages = $this->factory->input()->field()->numeric(
            $this->plugin_object->txt('config_n_memory_messages'), $this->plugin_object->txt('config_n_memory_messages_info')
        )->withValue((int) AIChatConfig::get("n_memory_messages") ?? 0)->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('n_memory_messages', $v);
            }
        ))->withRequired(true);

        $disclaimer_text = $this->factory->input()->field()->textarea(
            $this->plugin_object->txt('config_disclaimer_text'),
            $this->plugin_object->txt('config_disclaimer_text_info')
        )->withValue((string) AIChatConfig::get("disclaimer_text"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('disclaimer_text', $v);
            }
        ))->withRequired(true);

        $global_api_key = $this->factory->input()->field()->text(
            $this->plugin_object->txt('config_global_api_key')
        )->withValue((string) AIChatConfig::get("global_api_key"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('global_api_key', $v);

                if (isset($v) && $v != null && $v != "") {
                    AIChatConfig::set('use_global_api_key', "1");
                }
            }
        ))->withRequired(true);

        $use_global_api_key = $this->factory->input()->field()->optionalGroup(array(
            "global_api_key" => $global_api_key
        ), $this->plugin_object->txt('config_use_global_api_key'), $this->plugin_object->txt('config_use_global_api_key_info')
        )->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                if ($v == null) {
                    AIChatConfig::set('use_global_api_key', "0");

                    AIChatConfig::set('global_api_key', "");
                }
            }
        ));

        if (AIChatConfig::get("use_global_api_key") != "1") {
            $use_global_api_key = $use_global_api_key->withValue(null);
        }

        return array(
            $prompt_selection,
            $model,
            $characters_limit,
            $n_memory_messages,
            $disclaimer_text,
            $use_global_api_key
        );
    }

    private function renderForm(string $form_action, array $sections): string
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
                $saving_info = $this->save();
            }
        }

        return $saving_info . $this->renderer->render($form);
    }

    public function save(): string
    {
        AIChatConfig::save();
        return $this->renderer->render($this->factory->messageBox()->success($this->plugin_object->txt('config_msg_success')));
    }
}