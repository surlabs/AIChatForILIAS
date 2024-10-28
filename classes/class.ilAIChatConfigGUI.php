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
    protected ilTabsGUI $tabs;
    protected $request;

    public function performCommand($cmd): void
    {
        global $DIC;
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->refinery = $DIC->refinery();
        $this->control = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->request = $DIC->http()->request();
        $this->tabs = $DIC->tabs();

        switch ($cmd) {
            case "configure":
            case "configureGeneral":
            case "configureOpenAI":
            case "configureOllama":
                AIChatConfig::load();
                $this->initTabs();
                $this->control->setParameterByClass('ilAIChatConfigGUI', 'cmd', $cmd);
                $form_action = $this->control->getLinkTargetByClass("ilAIChatConfigGUI", $cmd);
                $rendered = $this->renderForm($form_action, $this->buildForm($cmd));
                break;
            default:
                throw new ilException("command not defined");
        }

        $this->tpl->setContent($rendered);
    }

    protected function initTabs(): void
    {
        $this->tabs->addTab(
            "general",
            $this->plugin_object->txt("config_general"),
            $this->control->getLinkTargetByClass("ilAIChatConfigGUI", "configureGeneral")
        );

        $this->tabs->addTab(
            "openai",
            $this->plugin_object->txt("config_openai"),
            $this->control->getLinkTargetByClass("ilAIChatConfigGUI", "configureOpenAI")
        );

        $this->tabs->addTab(
            "ollama",
            $this->plugin_object->txt("config_ollama"),
            $this->control->getLinkTargetByClass("ilAIChatConfigGUI", "configureOllama")
        );

        switch($this->control->getCmd()) {
            case "configureGeneral":
                $this->tabs->activateTab("general");
                break;
            case "configureOpenAI":
                $this->tabs->activateTab("openai");
                break;
            case "configureOllama":
                $this->tabs->activateTab("ollama");
                break;
            default:
                $this->tabs->activateTab("general");
        }
    }

    private function buildForm(string $cmd): array
    {
        switch($cmd) {
            case "configureGeneral":
                return $this->buildGeneralSection();
            case "configureOpenAI":
                return $this->buildOpenAISection();
            case "configureOllama":
                return $this->buildOllamaSection();
            default:
                return $this->buildGeneralSection();
        }
    }

    private function buildGeneralSection(): array {
        $model_selection = $this->factory->input()->field()->radio(
            $this->plugin_object->txt("config_model_label"),
            $this->plugin_object->txt("config_model_info")
        )
            ->withOption("gpt4-o", "GPT4o")
            ->withOption("gpt-3.5 turbo", "GPT 3.5 Turbo")
            ->withValue("gpt4-o")
            ->withRequired(true);

        $system_prompt = $this->factory->input()->field()->textarea(
            $this->plugin_object->txt("config_prompt_label"),
            $this->plugin_object->txt("config_prompt_info")
        )->withRequired(true);

        $char_limit = $this->factory->input()->field()->numeric(
            $this->plugin_object->txt("config_char_limit_label"),
            $this->plugin_object->txt("config_char_limit_info")
        );

        $prev_messages = $this->factory->input()->field()->numeric(
            $this->plugin_object->txt("config_prev_msg_label"),
            $this->plugin_object->txt("config_prev_msg_info")
        );

        $disclaimer = $this->factory->input()->field()->textarea(
            $this->plugin_object->txt("config_disclaimer_label"),
            $this->plugin_object->txt("config_disclaimer_info")
        )->withRequired(true);

        return [
            "general" => $this->factory->input()->field()->section([
                $model_selection,
                $system_prompt,
                $char_limit,
                $prev_messages,
                $disclaimer
            ], $this->plugin_object->txt("config_general_section"))
        ];
    }

    private function buildOpenAISection(): array {
        $models = $this->factory->input()->field()->select(
            $this->plugin_object->txt("config_openai_models_label"),
            [
                "gpt-3.5 turbo" => "GPT 3.5 Turbo",
                "gpt-4" => "GPT 4",
                "gpt-4.5" => "GPT 4.5"
            ]
        )->withRequired(true);

        $api_key = $this->factory->input()->field()->password(
            $this->plugin_object->txt("config_openai_key_label"),
            $this->plugin_object->txt("config_openai_key_info")
        )->withRequired(true);

        $streaming = $this->factory->input()->field()->checkbox(
            $this->plugin_object->txt("config_openai_stream_label"),
            $this->plugin_object->txt("config_openai_stream_info")
        );

        return [
            "openai" => $this->factory->input()->field()->section([
                $models,
                $api_key,
                $streaming
            ], $this->plugin_object->txt("config_openai_section"))
        ];
    }

    private function buildOllamaSection(): array {
        $endpoint = $this->factory->input()->field()->text(
            $this->plugin_object->txt("config_ollama_endpoint_label"),
            $this->plugin_object->txt("config_ollama_endpoint_info")
        )->withRequired(true);

        $models = $this->factory->input()->field()->multiSelect(
            $this->plugin_object->txt("config_ollama_models_label"),
            [
                "gpt-3.5 turbo" => "GPT 3.5 Turbo",
                "gpt-4" => "GPT 4",
                "gpt-4.5" => "GPT 4.5"
            ]
        )->withRequired(true);

        return [
            "ollama" => $this->factory->input()->field()->section([
                $endpoint,
                $models
            ], $this->plugin_object->txt("config_ollama_section"))
        ];
    }

    private function renderForm(string $form_action, array $sections): string
    {
        $form = $this->factory->input()->container()->form()->standard(
            $form_action,
            $sections
        );

        if ($this->request->getMethod() == "POST") {
            $form = $form->withRequest($this->request);
            $result = $form->getData();
            if ($result) {
                return $this->save();
            }
        }

        return $this->renderer->render($form);
    }

    public function save(): string
    {
        AIChatConfig::save();
        return $this->renderer->render(
            $this->factory->messageBox()->success($this->plugin_object->txt('config_msg_success'))
        );
    }
}