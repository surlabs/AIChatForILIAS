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
            case "configureGWDG":
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

        $this->tabs->addTab(
            "gwdg",
            "GWDG",
            $this->control->getLinkTargetByClass("ilAIChatConfigGUI", "configureGWDG")
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
            case "configureGWDG":
                $this->tabs->activateTab("gwdg");
                break;
            default:
                $this->tabs->activateTab("general");
        }
    }

    /**
     * @throws AIChatException
     */
    private function buildForm(string $cmd): array
    {
        switch($cmd) {
            case "configureOpenAI":
                return $this->buildOpenAISection();
            case "configureOllama":
                return $this->buildOllamaSection();
            case "configureGWDG":
                return $this->buildGWDGSection();
            default:
                return $this->buildGeneralSection();
        }
    }

    /**
     * @throws AIChatException
     */
    private function buildGeneralSection(): array {
        $available_services = AIChatConfig::get("available_services");

        $openai_service = $this->factory->input()->field()->checkbox(
            "OpenAI",
        )->withValue(isset($available_services["openai"]) && $available_services["openai"] == "1")->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use (&$available_services) {
                $available_services["openai"] = $v;
                AIChatConfig::set('available_services', $available_services);
            }
        ));

        $ollama_service = $this->factory->input()->field()->checkbox(
            "OLlama",
        )->withValue(isset($available_services["ollama"]) && $available_services["ollama"] == "1")->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use (&$available_services) {
                $available_services["ollama"] = $v;
                AIChatConfig::set('available_services', $available_services);
            }
        ));

        $gwdg = $this->factory->input()->field()->checkbox(
            "GWDG",
        )->withValue(isset($available_services["gwdg"]) && $available_services["gwdg"] == "1")->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use (&$available_services) {
                $available_services["gwdg"] = $v;
                AIChatConfig::set('available_services', $available_services);
            }
        ));

        $prompt = $this->factory->input()->field()->textarea(
            $this->plugin_object->txt("config_prompt_label"),
            $this->plugin_object->txt("config_prompt_info")
        )->withValue(AIChatConfig::get("prompt"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('prompt', $v);
            }
        ))->withRequired(true);

        $characters_limit = $this->factory->input()->field()->numeric(
            $this->plugin_object->txt("config_characters_limit_label"),
            $this->plugin_object->txt("config_characters_limit_info")
        )->withValue(AIChatConfig::get("characters_limit"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('characters_limit', $v);
            }
        ));

        $max_memory_messages = $this->factory->input()->field()->numeric(
            $this->plugin_object->txt("config_max_memory_messages_label"),
            $this->plugin_object->txt("config_max_memory_messages_info")
        )->withValue(AIChatConfig::get("max_memory_messages"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('max_memory_messages', $v);
            }
        ));

        $disclaimer = $this->factory->input()->field()->textarea(
            $this->plugin_object->txt("config_disclaimer_label"),
            $this->plugin_object->txt("config_disclaimer_info")
        )->withValue(AIChatConfig::get("disclaimer"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('disclaimer', $v);
            }
        ))->withRequired(true);

        return [
            "available_services" => $this->factory->input()->field()->section([
                $openai_service,
                $ollama_service,
                $gwdg
            ], $this->plugin_object->txt("config_available_services")),
            "general" => $this->factory->input()->field()->section([
                $prompt,
                $characters_limit,
                $max_memory_messages,
                $disclaimer
            ], $this->plugin_object->txt("config_general"))
        ];
    }

    private function buildOpenAISection(): array {
        $models = $this->factory->input()->field()->select(
            $this->plugin_object->txt("config_openai_models_label"),
            [
                "gpt-4o" => "GPT-4o",
                "gpt-4o-mini" => "GPT-4o mini",
                "gpt-4-turbo" => "GPT-4 Turbo",
                "gpt-4" => "GPT-4",
                "gpt-3.5-turbo" => "GPT-3.5 Turbo"
            ]
        )->withValue(AIChatConfig::get("openai_model"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('openai_model', $v);
            }
        ))->withRequired(true);

        $api_key = $this->factory->input()->field()->text(
            $this->plugin_object->txt("config_openai_key_label"),
            $this->plugin_object->txt("config_openai_key_info")
        )->withValue(AIChatConfig::get("openai_api_key"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('openai_api_key', $v);
            }
        ))->withRequired(true);

        $streaming = $this->factory->input()->field()->checkbox(
            $this->plugin_object->txt("config_openai_stream_label"),
            $this->plugin_object->txt("config_openai_stream_info")
        )->withValue(AIChatConfig::get("openai_streaming") == "1")->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('openai_streaming', $v);
            }

        ));

        return [
            "openai" => $this->factory->input()->field()->section([
                $models,
                $api_key,
                $streaming
            ], $this->plugin_object->txt("config_openai"))
        ];
    }

    /**
     * @throws AIChatException
     */
    private function buildOllamaSection(): array {
        $inputs = [];

        $llama_endpoint = AIChatConfig::get("ollama_endpoint");

        $inputs[] = $this->factory->input()->field()->text(
            $this->plugin_object->txt("config_ollama_endpoint_label"),
            $this->plugin_object->txt("config_ollama_endpoint_info")
        )->withValue($llama_endpoint)->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('ollama_endpoint', $v);
            }
        ))->withRequired(true);

        if (!empty($llama_endpoint)) {
            $models = $this->getOLlamaModels($llama_endpoint);

            $values = AIChatConfig::get("ollama_models");

            if (empty($values)) {
                $values = [];
            } else {
                $values = array_keys($values);
            }

            if (!empty($models)) {
                $inputs[] = $this->factory->input()->field()->multiSelect(
                    $this->plugin_object->txt("config_ollama_models_label"),
                    $models
                )->withValue($values)->withAdditionalTransformation($this->refinery->custom()->transformation(
                    function ($v) use ($models) {
                        $models_to_save = [];

                        foreach ($v as $model) {
                            $models_to_save[$model] = $models[$model];
                        }

                        AIChatConfig::set('ollama_models', $models_to_save);
                    }
                ))->withRequired(true);
            } else {
                $this->tpl->setOnScreenMessage("failure", $this->plugin_object->txt("config_ollama_models_error"));
            }
        }

        return [
            "ollama" => $this->factory->input()->field()->section($inputs, $this->plugin_object->txt("config_ollama"))
        ];
    }

    /**
     * @throws AIChatException
     */
    private function buildGWDGSection(): array {
        $inputs = [];

        if (!empty(AIChatConfig::get("gwdg_api_key"))) {
            $models = $this->getGWDGModels(AIChatConfig::get("gwdg_api_key"));

            $values = AIChatConfig::get("gwdg_models");

            if (empty($values)) {
                $values = [];
            } else {
                $values = array_keys($values);
            }

            if (!empty($models)) {
                $inputs[] = $this->factory->input()->field()->multiSelect(
                    $this->plugin_object->txt("config_gwdg_models_label"),
                    $models
                )->withValue($values)->withAdditionalTransformation($this->refinery->custom()->transformation(
                    function ($v) use ($models) {
                        $models_to_save = [];

                        foreach ($v as $model) {
                            $models_to_save[$model] = $models[$model];
                        }

                        AIChatConfig::set('gwdg_models', $models_to_save);
                    }
                ))->withRequired(true);
            } else {
                $this->tpl->setOnScreenMessage("failure", $this->plugin_object->txt("config_gwdg_models_error"));
            }
        }

        $inputs[] = $this->factory->input()->field()->text(
            $this->plugin_object->txt("config_gwdg_key_label"),
            $this->plugin_object->txt("config_gwdg_key_info")
        )->withValue(AIChatConfig::get("gwdg_api_key"))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('gwdg_api_key', $v);
            }
        ))->withRequired(true);

        $inputs[] = $this->factory->input()->field()->checkbox(
            $this->plugin_object->txt("config_gwdg_stream_label"),
            $this->plugin_object->txt("config_gwdg_stream_info")
        )->withValue(AIChatConfig::get("gwdg_streaming") == "1")->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                AIChatConfig::set('gwdg_streaming', $v);
            }
        ));

        return [
            "gwdg" => $this->factory->input()->field()->section($inputs, "GWDG")
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
                $this->save();
            }
        }

        return $this->renderer->render($form);
    }

    public function save(): void
    {
        AIChatConfig::save();

        $this->tpl->setOnScreenMessage("success", $this->plugin_object->txt('config_msg_success'));
    }

    private function getOLlamaModels(string $llama_endpoint): array
    {
        $llama_endpoint = rtrim($llama_endpoint, '/') . '/api/tags';

        $curlSession = curl_init();
        curl_setopt($curlSession, CURLOPT_URL, $llama_endpoint);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlSession, CURLOPT_TIMEOUT, 10);

        if (\ilProxySettings::_getInstance()->isActive()) {
            $proxyHost = \ilProxySettings::_getInstance()->getHost();
            $proxyPort = \ilProxySettings::_getInstance()->getPort();
            $proxyURL = $proxyHost . ":" . $proxyPort;
            curl_setopt($curlSession, CURLOPT_PROXY, $proxyURL);
        }

        $response = curl_exec($curlSession);

        $models = [];

        if (!curl_errno($curlSession)) {
            $response = json_decode($response, true);


            if (isset($response["models"])) {
                foreach ($response["models"] as $model) {
                    $models[$model['model']] = $model['name'];
                }
            }
        }

        curl_close($curlSession);

        return $models;
    }

    private function getGWDGModels(string $api_key): array
    {
        $curlSession = curl_init();
        curl_setopt($curlSession, CURLOPT_URL, "https://chat-ai.academiccloud.de/v1/models");
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlSession, CURLOPT_TIMEOUT, 10);
        curl_setopt($curlSession, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);

        if (\ilProxySettings::_getInstance()->isActive()) {
            $proxyHost = \ilProxySettings::_getInstance()->getHost();
            $proxyPort = \ilProxySettings::_getInstance()->getPort();
            $proxyURL = $proxyHost . ":" . $proxyPort;
            curl_setopt($curlSession, CURLOPT_PROXY, $proxyURL);
        }

        $response = curl_exec($curlSession);

        $models = [];

        if (!curl_errno($curlSession)) {
            $response = json_decode($response, true);

            if (isset($response["data"])) {
                foreach ($response["data"] as $model) {
                    $models[$model['id']] = $model['name'];
                }
            }
        }

        curl_close($curlSession);

        return $models;
    }
}