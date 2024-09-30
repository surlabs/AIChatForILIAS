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
use ILIAS\UI\Component\Input\Field\Group;
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
        $provider = $this->factory->input()->field()->switchableGroup(
            array(
                "openai" => $this->buildOpenAIGroup(),
                "custom" => $this->buildCustomGroup()
            ),
            $this->plugin_object->txt('config_provider')
        )->withValue(AIChatConfig::get("llm_provider") != "" ? AIChatConfig::get("llm_provider") : "openai")->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('llm_provider', $v[0]);
            }
        ))->withRequired(true);

        $api_section = $this->factory->input()->field()->section(
            array(
                $provider
            ),
            $this->plugin_object->txt('config_api_section')
        );

        $prompt_selection = $this->factory->input()->field()->textarea(
            $this->plugin_object->txt('config_prompt_selection'),
            $this->plugin_object->txt('config_prompt_selection_info')
        )->withValue((string) AIChatConfig::get("prompt_selection"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('prompt_selection', $v);
            }
        ))->withRequired(true);

        $characters_limit = $this->factory->input()->field()->numeric(
            $this->plugin_object->txt('config_characters_limit'), $this->plugin_object->txt('config_characters_limit_info')
        )->withValue(AIChatConfig::get("characters_limit") != "" ? (int) AIChatConfig::get("characters_limit") : 100)->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('characters_limit', $v);
            }
        ))->withRequired(true);

        $n_memory_messages = $this->factory->input()->field()->numeric(
            $this->plugin_object->txt('config_n_memory_messages'), $this->plugin_object->txt('config_n_memory_messages_info')
        )->withValue(AIChatConfig::get("config_n_memory_messages") != "" ? (int) AIChatConfig::get("config_n_memory_messages") : 100)->withAdditionalTransformation($this->refinery->custom()->transformation(
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

        $general_section = $this->factory->input()->field()->section(
            array(
                $prompt_selection,
                $characters_limit,
                $n_memory_messages,
                $disclaimer_text,
            ),
            $this->plugin_object->txt('config_general_section')
        );

        return array(
            $api_section,
            $general_section
        );
    }

    /**
     * @throws AIChatException
     */
    private function buildOpenAIGroup(): Group
    {
        $models = array(
            "gpt-4o" => "GPT-4o",
            "gpt-4o-mini" => "GPT-4o mini",
            "gpt-4-turbo" => "GPT-4 Turbo",
            "gpt-4" => "GPT-4",
            "gpt-3.5-turbo" => "GPT-3.5 Turbo"
        );

        $model = $this->factory->input()->field()->select(
            $this->plugin_object->txt('config_model'),
            $models,
            $this->plugin_object->txt('config_model_info')
        )->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('llm_model', $v);
            }
        ))->withRequired(true);

        if (AIChatConfig::get("llm_model") != "") {
            if (array_key_exists(AIChatConfig::get("llm_model"), $models)) {
                $model = $model->withValue(AIChatConfig::get("llm_model"));
            }
        }

        $global_api_key = $this->factory->input()->field()->text(
            $this->plugin_object->txt('config_global_api_key')
        )->withValue((string) AIChatConfig::get("global_api_key"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('global_api_key', $v);
            }
        ))->withRequired(true);

        $streaming_enabled = $this->factory->input()->field()->checkbox(
            $this->plugin_object->txt('config_streaming_enabled'),
            $this->plugin_object->txt('config_streaming_enabled_info')
        )->withValue((bool) AIChatConfig::get("streaming_enabled"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('streaming_enabled', $v);
            }
        ));

        return $this->factory->input()->field()->group(
            array(
                $model,
                $global_api_key,
                $streaming_enabled
            ),
            $this->plugin_object->txt('config_openai')
        );
    }

    /**
     * @throws AIChatException
     */
    private function buildCustomGroup(): Group
    {
        $url = $this->factory->input()->field()->text(
            $this->plugin_object->txt('config_url'),
            $this->plugin_object->txt('config_url_info')
        )->withValue((string) AIChatConfig::get("llm_url"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('llm_url', $v);
            }
        ))->withRequired(true);

        $model = $this->factory->input()->field()->text(
            $this->plugin_object->txt('config_model'),
            $this->plugin_object->txt('config_model_info')
        )->withValue((string) AIChatConfig::get("llm_model"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('llm_model', $v);
            }
        ))->withRequired(true);

//        $global_api_key = $this->factory->input()->field()->text(
//            $this->plugin_object->txt('config_global_api_key')
//        )->withValue((string) AIChatConfig::get("global_api_key"))->withAdditionalTransformation($this->refinery->custom()->transformation(
//            function ($v) {
//                AIChatConfig::set('global_api_key', $v);
//            }
//        ));

        return $this->factory->input()->field()->group(
            array(
                $url,
                $model,
//                $global_api_key
            ),
            $this->plugin_object->txt('config_custom')
        );
    }

    /**
     * @throws AIChatException
     */
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

                $form = $this->factory->input()->container()->form()->standard(
                    $form_action,
                    $this->buildForm()
                );
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