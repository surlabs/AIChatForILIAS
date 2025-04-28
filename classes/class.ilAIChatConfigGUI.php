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

use Customizing\global\plugins\Services\Repository\RepositoryObject\AIChat\classes\components\Hint;
use Customizing\global\plugins\Services\Repository\RepositoryObject\AIChat\classes\components\Html;
use ILIAS\UI\Component\Input\Container\Form\Standard;
use ILIAS\UI\Component\Input\Field\Section;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use platform\AIChatConfig;
use platform\AIChatException;
use ai\OpenAI;

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
     * @throws ilCtrlException
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
                $rendered = $this->renderForm();
                break;
            default:
                throw new ilException("command not defined");
        }

        $this->tpl->setContent($rendered);
    }

    /**
     * @throws AIChatException
     */
    private function buildForm(): Standard
    {
        return $this->factory->input()->container()->form()->standard(
            "#",
            [
                "services" => $this->buildServices(),
                "general" => $this->buildGeneral(),
            ]
        );
    }

    /**
     * @throws AIChatException
     */
    private function buildServices(): Section
    {
        $services = [];

        $available_services = AIChatConfig::get("available_services");

        if (empty($available_services) || !in_array(true, $available_services)) {
            $services["hint"] = new Hint($this->plugin_object->txt("step_1"), $this->plugin_object->txt("step_1_info"));
        }

        $services["openai"] = $this->factory->input()->field()->optionalGroup(
            $this->buildOpenAIGroup(),
            "OpenAI"
        );

        if (!isset($available_services["openai"]) || !$available_services["openai"]) {
            $services["openai"] = $services["openai"]->withValue(null);
        }

        $services["ollama"] = $this->factory->input()->field()->optionalGroup(
            $this->buildOllamaGroup(),
            "Ollama"
        );

        if (!isset($available_services["ollama"]) || !$available_services["ollama"]) {
            $services["ollama"] = $services["ollama"]->withValue(null);
        }

        $services["gwdg"] = $this->factory->input()->field()->optionalGroup(
            $this->buildGWDGGroup(),
            "GWDG"
        );

        if (!isset($available_services["gwdg"]) || !$available_services["gwdg"]) {
            $services["gwdg"] = $services["gwdg"]->withValue(null);
        }

        return $this->factory->input()->field()->section(
            $services,
            $this->plugin_object->txt("config_available_services")
        );
    }

    /**
     * @throws AIChatException
     */
    private function buildOpenAIGroup(): array
    {
        $openai = [];

        if (empty(AIChatConfig::get("openai_api_key"))) {
            $openai["hint"] = new Hint($this->plugin_object->txt("step_2"), $this->plugin_object->txt("step_2_openai"));
        }

        $openai["api_key"] =  $this->factory->input()->field()->text(
            $this->plugin_object->txt("config_openai_key_label"),
            $this->plugin_object->txt("config_openai_key_info")
        )->withRequired(true);

        if (!empty(AIChatConfig::get("openai_api_key"))) {
            $openai["api_key"] = $openai["api_key"]->withValue(AIChatConfig::get("openai_api_key"));
        }

        $openai["streaming"] = $this->factory->input()->field()->checkbox(
            $this->plugin_object->txt("config_openai_stream_label"),
            $this->plugin_object->txt("config_openai_stream_info")
        );

        if (!empty(AIChatConfig::get("openai_streaming"))) {
            $openai["streaming"] = $openai["streaming"]->withValue((bool) AIChatConfig::get("openai_streaming"));
        }

        $models = [];
        $openai_models = AIChatConfig::get("openai_models");

        if (empty($openai_models)) {
            $openai_models = [];
        }

        foreach ($this->getOpenAIModels() as $model => $name) {
            $models[$model] = $this->factory->input()->field()->checkbox(
                $name
            )->withValue((bool) ($openai_models[$model] ?? false));
        }



        $openai["no-save"] = new Html('<span style="font-size: 1rem; font-weight: 600; padding-bottom: 5px; padding-top: 5px">' . $this->plugin_object->txt("config_openai_models_label") . '</span>');
        $openai["models"] = $this->factory->input()->field()->group(
            $models
        );

        return $openai;
    }

    /**
     * @throws AIChatException
     */
    private function buildOllamaGroup(): array
    {
        $ollama = [];

        if (empty(AIChatConfig::get("ollama_endpoint"))) {
            $ollama["hint"] = new Hint($this->plugin_object->txt("step_2"), $this->plugin_object->txt("step_2_ollama"));
        }

        $ollama["endpoint"] = $this->factory->input()->field()->text(
            $this->plugin_object->txt("config_ollama_endpoint_label"),
            $this->plugin_object->txt("config_ollama_endpoint_info")
        )->withRequired(true);

        if (!empty(AIChatConfig::get("ollama_endpoint"))) {
            $ollama["endpoint"] = $ollama["endpoint"]->withValue(AIChatConfig::get("ollama_endpoint"));
        }

        if (!empty(AIChatConfig::get("ollama_endpoint"))) {
            $models = [];
            $ollama_models = AIChatConfig::get("ollama_models");

            if (empty($ollama_models)) {
                $ollama["hint"] = new Hint($this->plugin_object->txt("step_3"), $this->plugin_object->txt("step_3_info"));

                $ollama_models = [];
            }

            foreach ($this->getOLlamaModels(AIChatConfig::get("ollama_endpoint")) as $model => $name) {
                $models[$model] = $this->factory->input()->field()->checkbox(
                    $name
                )->withValue((bool)($ollama_models[$model] ?? false));
            }

            $ollama["no-save"] = new Html('<span style="font-size: 1rem; font-weight: 600; padding-bottom: 5px; padding-top: 5px">' . $this->plugin_object->txt("config_openai_models_label") . '</span>');
            $ollama["models"] = $this->factory->input()->field()->group(
                $models
            );
        }

        return $ollama;
    }

    /**
     * @throws AIChatException
     */
    private function buildGWDGGroup(): array
    {
        $gwdg = [];

        if (empty(AIChatConfig::get("gwdg_api_key"))) {
            $gwdg["hint"] = new Hint($this->plugin_object->txt("step_2"), $this->plugin_object->txt("step_2_gwdg"));
        }

        $gwdg["api_key"] =  $this->factory->input()->field()->text(
            $this->plugin_object->txt("config_gwdg_key_label"),
            $this->plugin_object->txt("config_gwdg_key_info")
        )->withRequired(true);

        if (!empty(AIChatConfig::get("gwdg_api_key"))) {
            $gwdg["api_key"] = $gwdg["api_key"]->withValue(AIChatConfig::get("gwdg_api_key"));
        }

        $gwdg["streaming"] = $this->factory->input()->field()->checkbox(
            $this->plugin_object->txt("config_gwdg_stream_label"),
            $this->plugin_object->txt("config_gwdg_stream_info")
        );

        if (!empty(AIChatConfig::get("gwdg_streaming"))) {
            $gwdg["streaming"] = $gwdg["streaming"]->withValue((bool) AIChatConfig::get("gwdg_streaming"));
        }

        if (!empty(AIChatConfig::get("gwdg_api_key"))) {
            $models = [];
            $gwdg_models = AIChatConfig::get("gwdg_models");

            if (empty($gwdg_models)) {
                $gwdg["hint"] = new Hint($this->plugin_object->txt("step_3"), $this->plugin_object->txt("step_3_info"));

                $gwdg_models = [];
            }

            foreach ($this->getGWDGModels(AIChatConfig::get("gwdg_api_key")) as $model => $name) {
                $models[$model] = $this->factory->input()->field()->checkbox(
                    $name
                )->withValue((bool)($gwdg_models[$model] ?? false));
            }

            $gwdg["no-save"] = new Html('<span style="font-size: 1rem; font-weight: 600; padding-bottom: 5px; padding-top: 5px">' . $this->plugin_object->txt("config_openai_models_label") . '</span>');
            $gwdg["models"] = $this->factory->input()->field()->group(
                $models
            );
        }

        return $gwdg;
    }

    /**
     * @throws AIChatException
     */
    private function buildGeneral(): Section
    {
        $general = [];

        $general["prompt"] = $this->factory->input()->field()->textarea(
            $this->plugin_object->txt("config_prompt_label"),
            $this->plugin_object->txt("config_prompt_info")
        )->withValue(AIChatConfig::get("prompt"))->withRequired(true);


        $general["characters_limit"] = $this->factory->input()->field()->numeric(
            $this->plugin_object->txt("config_characters_limit_label"),
            $this->plugin_object->txt("config_characters_limit_info")
        )->withValue(AIChatConfig::get("characters_limit"));

        $general["max_memory_messages"] = $this->factory->input()->field()->numeric(
            $this->plugin_object->txt("config_max_memory_messages_label"),
            $this->plugin_object->txt("config_max_memory_messages_info")
        )->withValue(AIChatConfig::get("max_memory_messages"));

        $general["disclaimer"] = $this->factory->input()->field()->textarea(
            $this->plugin_object->txt("config_disclaimer_label"),
            $this->plugin_object->txt("config_disclaimer_info")
        )->withValue(AIChatConfig::get("disclaimer"))->withRequired(true);

        return $this->factory->input()->field()->section(
            $general,
            $this->plugin_object->txt("config_general")
        );
    }

    /**
     * @throws AIChatException
     * @throws ilCtrlException
     */
    private function renderForm(): string
    {
        $form = $this->buildForm();

        if ($this->request->getMethod() == "POST") {
            $form = $form->withRequest($this->request);
            $result = $form->getData();
            if ($result) {
                $this->save($result);
                $form = $this->buildForm();
            }
        }

        return $this->renderer->render($form);
    }

    /**
     * @throws ilCtrlException
     */
    public function save(array $data): void
    {
        if (!empty($data["services"])) {
            $available_services = [];

            foreach ($data["services"] as $service => $values) {
                if ($service == "hint" || $service == "no-save") {
                    continue;
                }

                if ($values) {
                    $this->saveService($service, $values);

                    $available_services[$service] = true;
                } else {
                    $available_services[$service] = false;
                }
            }

            AIChatConfig::set("available_services", $available_services);
        }

        if (!empty($data["general"])) {
            foreach ($data["general"] as $key => $value) {
                if ($key == "hint" || $key == "no-save") {
                    continue;
                }

                AIChatConfig::set($key, $value);
            }
        }

        AIChatConfig::save();

        $this->tpl->setOnScreenMessage("success", $this->plugin_object->txt('config_msg_success'), true);
        $this->control->redirect($this, "configure");
    }

    private function saveService(string $service, array $values): void
    {
        foreach ($values as $key => $value) {
            if ($key == "hint" || $key == "no-save") {
                continue;
            }

            if ($key == "models") {
                $models_tags = [];

                switch ($service) {
                    case "openai":
                        $models_tags = $this->getOpenAIModels();
                        break;
                    case "ollama":
                        $models_tags = $this->getOLlamaModels($values["endpoint"]);
                        break;
                    case "gwdg":
                        $models_tags = $this->getGWDGModels($values["api_key"]);
                        break;
                }

                $models = [];

                foreach ($value as $model => $selected) {
                    if ($selected) {
                        $models[$model] = $models_tags[$model];
                    }
                }

                $value = $models;
            }

            AIChatConfig::set($service . "_" . $key, $value);
        }
    }

    private function getOpenAIModels(): array
    {
        return OpenAI::MODEL_TYPES;
    }

    private function getOLlamaModels(string $llama_endpoint): array
    {
        $llama_endpoint = rtrim($llama_endpoint, '/') . '/api/tags';

        $curlSession = curl_init();
        curl_setopt($curlSession, CURLOPT_URL, $llama_endpoint);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlSession, CURLOPT_TIMEOUT, 10);

        if (ilProxySettings::_getInstance()->isActive()) {
            $proxyHost = ilProxySettings::_getInstance()->getHost();
            $proxyPort = ilProxySettings::_getInstance()->getPort();
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

        if (ilProxySettings::_getInstance()->isActive()) {
            $proxyHost = ilProxySettings::_getInstance()->getHost();
            $proxyPort = ilProxySettings::_getInstance()->getPort();
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