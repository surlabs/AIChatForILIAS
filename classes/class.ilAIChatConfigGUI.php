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
                "services" => $this->buildServices()
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
                $ollama_models = [];
            }

            foreach ($this->getOLlamaModels(AIChatConfig::get("ollama_endpoint")) as $model => $name) {
                $models[$model] = $this->factory->input()->field()->checkbox(
                    $name
                )->withValue((bool)($ollama_models[$model] ?? false));
            }

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
                $gwdg_models = [];
            }

            foreach ($this->getGWDGModels(AIChatConfig::get("gwdg_api_key")) as $model => $name) {
                $models[$model] = $this->factory->input()->field()->checkbox(
                    $name
                )->withValue((bool)($gwdg_models[$model] ?? false));
            }

            $gwdg["models"] = $this->factory->input()->field()->group(
                $models
            );
        }

        return $gwdg;
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
                if ($values) {
                    $this->saveService($service, $values);

                    $available_services[$service] = true;
                } else {
                    $available_services[$service] = false;
                }
            }

            AIChatConfig::set("available_services", $available_services);
        }

        AIChatConfig::save();

        $this->tpl->setOnScreenMessage("success", $this->plugin_object->txt('config_msg_success'), true);
        $this->control->redirect($this, "configure");
    }

    private function saveService(string $service, array $values): void
    {
        foreach ($values as $key => $value) {
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