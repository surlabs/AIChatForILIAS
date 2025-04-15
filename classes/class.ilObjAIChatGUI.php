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
use ILIAS\UI\Component\Input\Field\Section;
use ILIAS\UI\Component\Input\Group;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use objects\AIChat;
use objects\Chat;
use objects\Message;
use platform\AIChatConfig;
use platform\AIChatException;
use ai\OpenAI;

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
    protected \ILIAS\Refinery\Factory $refinery;

    public function __construct($a_ref_id = 0, $a_id_type = self::REPOSITORY_NODE_ID, $a_parent_node_id = 0)
    {
        global $DIC;

        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->refinery = $DIC->refinery();
        $this->request = $DIC->http()->request();

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
        $this->checkPermission("read");
        $this->setTitleAndDescription();
        $this->{$cmd}();
    }

    public function getType(): string
    {
        return ilAIChatPlugin::PLUGIN_ID;
    }

    /**
     * @throws ilCtrlException
     */
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

    /**
     * @throws ilTemplateException
     * @throws ilCtrlException
     */
    private function content(): void
    {
        global $DIC;
        $this->tabs->activateTab("content");

        if(ilObjAIChatAccess::_isOffline($this->obj_id)){
            $this->tpl->setContent($DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure($this->plugin->txt("object_offline_info"))));
            return;
        }


        $aichat = $this->object->getAIChat();

        if ($aichat->getLLM() == null) {
            $this->tpl->setContent($DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure($this->plugin->txt("object_no_llm"))));
            return;
        }

        $tpl = $DIC['tpl'];
        $tpl->addCss($this->plugin->getDirectory() . "/templates/default/index.css");
        $tpl->addJavascript($this->plugin->getDirectory() . "/templates/default/index.js");

        $apiUrl = $this->ctrl->getLinkTargetByClass("ilObjAIChatGUI", "apiCall");

        $this->tpl->setContent("<div id='root' apiurl='$apiUrl'></div>");
    }

    /**
     * @throws AIChatException
     * @throws ilCtrlException
     */
    private function settings(): void
    {
        $this->checkPermission("write");

        $this->tabs->activateTab("settings");

        $form_action = $this->ctrl->getLinkTargetByClass("ilObjAIChatGUI", "settings");
        $this->tpl->setContent($this->renderSettingsForm($form_action));
    }

    /**
     * @throws AIChatException
     */
    private function renderSettingsForm(string $form_action): string
    {
        $form = $this->factory->input()->container()->form()->standard(
            $form_action,
            $this->buildSettingsForm()
        );

        $saving_info = "";

        if ($this->request->getMethod() == "POST") {
            $form = $form->withRequest($this->request);
            $result = $form->getData();
            if ($result) {
                $saving_info = $this->saveSettings();

                $form = $this->factory->input()->container()->form()->standard(
                    $form_action,
                    $this->buildSettingsForm()
                );
            }
        }

        return $saving_info . $this->renderer->render($form);
    }

    /**
     * @throws AIChatException
     */
    private function buildSettingsForm(): array
    {
        /**
         * @var $aiChat AIChat
         */
        $aiChat = $this->object->getAIChat();

        $title_input = $this->factory->input()->field()->text(
            $this->plugin->txt('object_settings_title')
        )->withValue($this->object->getTitle())
            ->withAdditionalTransformation(
                $this->refinery->string()->hasMaxLength(255)
            )->withAdditionalTransformation($this->refinery->custom()->transformation(
                function ($v) {
                    $this->object->setTitle($v);
                }
            ));

        $description_input = $this->factory->input()->field()->textarea(
            $this->plugin->txt('object_settings_description')
        )->withValue($this->object->getDescription())
            ->withAdditionalTransformation(
                $this->refinery->string()->hasMaxLength(4000)
            )
            ->withAdditionalTransformation($this->refinery->custom()->transformation(
                function ($v) {
                    $this->object->setDescription($v);
                }
            ));

        $online_input = $this->factory->input()->field()->checkbox(
            $this->plugin->txt('object_settings_online'),
            $this->plugin->txt('object_settings_online_info')
        )->withValue($aiChat->isOnline())->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setOnline($v);
            }
        ));

        $basic_section = $this->factory->input()->field()->section(
            array(
                $title_input,
                $description_input,
                $online_input
            ),
            $this->plugin->txt('object_settings_basic')
        );

        $api_section = $this->buildApiSection();

        $prompt = $this->factory->input()->field()->textarea(
            $this->plugin->txt('config_prompt_selection'),
            $this->plugin->txt('config_prompt_selection_info')
        )->withValue($aiChat->getPrompt(true))
            ->withAdditionalTransformation(
                $this->refinery->string()->hasMaxLength(4000)
            )
            ->withAdditionalTransformation($this->refinery->custom()->transformation(
                function ($v) use ($aiChat) {
                    $aiChat->setPrompt($v);
                }
            ))->withOnloadCode(function ($id) use ($aiChat) {
                return "$('#$id').attr('placeholder', `{$aiChat->getPrompt()}`);";
            });

        $disclaimer = $this->factory->input()->field()->textarea(
            $this->plugin->txt('config_disclaimer_text'),
            $this->plugin->txt('config_disclaimer_text_info')
        )->withValue($aiChat->getDisclaimer(true))
            ->withAdditionalTransformation(
                $this->refinery->string()->hasMaxLength(4000)
            )
            ->withAdditionalTransformation($this->refinery->custom()->transformation(
                function ($v) use ($aiChat) {
                    $aiChat->setDisclaimer($v);
                }
            ))->withOnloadCode(function ($id) use ($aiChat) {
                return "$('#$id').attr('placeholder', `{$aiChat->getDisclaimer()}`);";
            });

        $max_memory_messages = $this->factory->input()->field()->numeric(
            $this->plugin->txt('config_n_memory_messages'), $this->plugin->txt('config_n_memory_messages_info')
        )->withAdditionalTransformation(
            $this->refinery->int()->isGreaterThanOrEqual(0)
        )->withAdditionalTransformation(
            $this->refinery->int()->isLessThanOrEqual(100)
        )->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setMaxMemoryMessages((int) $v);
            }
        ))->withOnloadCode(function ($id) use ($aiChat) {
            return "$('#$id').attr('placeholder', '{$aiChat->getMaxMemoryMessages()}');";
        });

        if ($aiChat->getMaxMemoryMessages(true) > 0) {
            $max_memory_messages = $max_memory_messages->withValue($aiChat->getMaxMemoryMessages(true));
        }

        $characters_limit = $this->factory->input()->field()->numeric(
            $this->plugin->txt('config_characters_limit'), $this->plugin->txt('config_characters_limit_info')
        )->withAdditionalTransformation(
            $this->refinery->int()->isGreaterThanOrEqual(0)
        )->withAdditionalTransformation(
            $this->refinery->int()->isLessThanOrEqual(4000)
        )->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setCharactersLimit((int) $v);
            }
        ))->withOnloadCode(function ($id) use ($aiChat) {
            return "$('#$id').attr('placeholder', '{$aiChat->getCharactersLimit()}');";
        });

        if ($aiChat->getCharactersLimit(true) > 0) {
            $characters_limit = $characters_limit->withValue($aiChat->getCharactersLimit(true));
        }

        $general_section = $this->factory->input()->field()->section(
            array(
                $prompt,
                $disclaimer,
                $max_memory_messages,
                $characters_limit
            ),
            $this->plugin->txt('config_general_section')
        );

        return array(
            $basic_section,
            $api_section,
            $general_section,
        );
    }

    private function saveSettings(): string
    {
        global $DIC;

        $renderer = $DIC->ui()->renderer();

        $this->object->update();

        return $renderer->render($DIC->ui()->factory()->messageBox()->success($this->plugin->txt('object_settings_msg_success')));
    }

    /**
     * @throws AIChatException
     */
    public function apiCall()
    {
        if ($this->request->getMethod() == "GET") {
            self::sendApiResponse($this->processGetApiCall($_GET));
        } else if ($this->request->getMethod() == "POST") {
            $postData = $this->request->getParsedBody();
            self::sendApiResponse($this->processPostApiCall($postData));
        } else {
            self::sendApiResponse(array("error" => "Method not allowed"), 405);
        }
    }

    /**
     * @throws AIChatException
     */
    private function processGetApiCall($data)
    {
        switch ($data["action"]) {
            case "config":
                /**
                 * @var $aiChat AIChat
                 */
                $aiChat = $this->object->getAIChat();

                $openai_streaming = false;

                if ($aiChat->getServiceToUse() == "openai") {
                    $openai_streaming = $aiChat->isOpenaiStreaming() ?? false;
                }

                if ($aiChat->getServiceToUse() == "gwdg") {
                    $openai_streaming = $aiChat->isGWDGStreaming() ?? false;
                }

                return array(
                    "disclaimer" => $aiChat->getDisclaimer() ?? false,
                    "prompt" => $aiChat->getPrompt() ?? false,
                    "characters_limit" => $aiChat->getCharactersLimit() ?? false,
                    "max_memory_messages" => $aiChat->getMaxMemoryMessages() ?? false,
                    "openai_streaming" => $openai_streaming ?? false,
                    "lang" => $this->lng->getUserLanguage(),
                    "translations" => $this->loadFrontLang()
                );
            case "chats":
                global $DIC;

                $user_id = $DIC->user()->getId();

                return $this->object->getAIChat()->getChatsForApi($user_id);
            case "chat":
                if (isset($data["chat_id"])) {
                    $chat = new Chat((int) $data["chat_id"]);

                    $chat->setMaxMessages($this->object->getAIChat()->getMaxMemoryMessages());

                    return $chat->toArray();
                } else {
                    self::sendApiResponse(array("error" => "Chat ID not provided"), 400);
                }
        }

        return false;
    }

    /**
     * @throws AIChatException
     */
    private function processPostApiCall($data)
    {
        switch ($data["action"]) {
            case "new_chat":
                global $DIC;

                $chat = new Chat();

                $user_id = $DIC->user()->getId();

                $chat->setUserId($user_id);
                $chat->setObjId($this->object->getId());
                $chat->setMaxMessages($this->object->getAIChat()->getMaxMemoryMessages());

                $chat->save();

                return $chat->toArray();
            case "add_message":
                if (isset($data["chat_id"]) && isset($data["message"])) {
                    $chat = new Chat((int) $data["chat_id"]);

                    $message = new Message();

                    $message->setChatId((int) $data["chat_id"]);
                    $message->setMessage($data["message"]);
                    $message->setRole("user");

                    if (count($chat->getMessages()) == 0) {
                        $chat->setTitleFromMessage($data["message"]);
                    }

                    $chat->addMessage($message);

                    $chat->setLastUpdate($message->getDate());

                    $chat->setMaxMessages($this->object->getAIChat()->getMaxMemoryMessages());

                    $retval = array(
                        "message" => $message->toArray(),
                        "llmresponse" => $this->object->getAIChat()->getLLMResponse($chat)->toArray()
                    );

                    $message->save();

                    $chat->save();

                    return $retval;
                } else {
                    self::sendApiResponse(array("error" => "Chat ID or message not provided"), 400);
                    break;
                }
            case "delete_chat":
                if (isset($data["chat_id"])) {
                    $chat = new Chat((int) $data["chat_id"]);

                    $chat->delete();

                    return true;
                } else {
                    self::sendApiResponse(array("error" => "Chat ID not provided"), 400);
                }
        }

        return false;
    }

    private function loadFrontLang(): array
    {
        return array(
            "front_new_chat_button" => $this->plugin->txt("front_new_chat_button"),
            "front_input_placeholder" => $this->plugin->txt("front_input_placeholder")
        );
    }

    public static function sendApiResponse($data, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-type: application/json');
        echo json_encode($data);
        exit();
    }

    private function buildApiSection(): Section
    {
        $aiChat = $this->object->getAIChat();

        $available_services = AIChatConfig::get("available_services");

        if (!empty($available_services) && in_array(true, $available_services)) {
            $services = [];

            if (isset($available_services["openai"]) && $available_services["openai"]) {
                $services["openai"] = $this->buildOpenAISection();
            }

            if (isset($available_services["ollama"]) && $available_services["ollama"]) {
                $services["ollama"] = $this->buildOllamaSection();
            }

            if (isset($available_services["gwdg"]) && $available_services["gwdg"]) {
                $services["gwdg"] = $this->buildGWDGSection();
            }

            return $this->factory->input()->field()->section([
                $this->factory->input()->field()->switchableGroup($services, "")->withValue($aiChat->getServiceToUse(true))
                    ->withAdditionalTransformation($this->refinery->custom()->transformation(
                        function ($v) use ($aiChat) {
                            $aiChat->setServiceToUse($v[0]);
                        }
                    ))->withRequired(true),
            ], $this->plugin->txt('config_api_section'));
        } else {
            return $this->factory->input()->field()->section(
                [$this->factory->input()->field()->group([
                    new Hint($this->plugin->txt("no_available_services")),
                ])],
                $this->plugin->txt('config_api_section')
            );
        }
    }

    private function buildOpenAISection(): Group
    {
        $aiChat = $this->object->getAIChat();

        $openai = [];

        $openai[] = $this->factory->input()->field()->select(
            $this->plugin->txt('config_openai_models_label'),
            $aiChat->getOpenAIModelsList()
        )->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setOpenaiModel($v);
            }
        ))->withRequired(true);

        if (isset($aiChat->getOpenAIModelsList()[$aiChat->getOpenaiModel(true)])) {
            $openai[count($openai) - 1] = $openai[count($openai) - 1]->withValue($aiChat->getOpenaiModel(true));
        }

        $openai[] = $this->factory->input()->field()->text(
            $this->plugin->txt('config_openai_key_label'),
            $this->plugin->txt('config_openai_key_info_optional')
        )->withValue($aiChat->getOpenaiApiKey(true))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setOpenaiApiKey($v);
            }
        ));

        $openai[] = $this->factory->input()->field()->checkbox(
            $this->plugin->txt('config_openai_stream_label'),
            $this->plugin->txt('config_openai_stream_info_optional')
        )->withValue($aiChat->isOpenaiStreaming(true))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setOpenaiStreaming($v);
            }
        ));

        return $this->factory->input()->field()->group($openai, "OpenAI");
    }

    private function buildOllamaSection(): Group
    {
        $aiChat = $this->object->getAIChat();

        $ollama = [];

        $ollama[] = $this->factory->input()->field()->select(
            $this->plugin->txt('config_ollama_models_label'),
            $aiChat->getOllamaModelsList()
        )->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setOllamaModel($v);
            }
        ))->withRequired(true);

        if (in_array($aiChat->getOllamaModel(true), $aiChat->getOllamaModelsList())) {
            $ollama[count($ollama) - 1] = $ollama[count($ollama) - 1]->withValue($aiChat->getOllamaModel(true));
        }

        return $this->factory->input()->field()->group($ollama, "Ollama");
    }

    private function buildGWDGSection(): Group
    {
        $aiChat = $this->object->getAIChat();

        $gwdg = [];

        $gwdg[] = $this->factory->input()->field()->select(
            $this->plugin->txt('config_gwdg_models_label'),
            $aiChat->getGWDGModelsList()
        )->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setGWDGModel($v);
            }
        ))->withRequired(true);

        if (isset($aiChat->getGWDGModelsList()[$aiChat->getGWDGModel(true)])) {
            $gwdg[count($gwdg) - 1] = $gwdg[count($gwdg) - 1]->withValue($aiChat->getGWDGModel(true));
        }

        $gwdg[] = $this->factory->input()->field()->checkbox(
            $this->plugin->txt('config_gwdg_stream_label'),
            $this->plugin->txt('config_gwdg_stream_info_optional')
        )->withValue($aiChat->isGWDGStreaming(true))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setGWDGStreaming($v);
            }
        ));

        return $this->factory->input()->field()->group($gwdg, "GWDG");
    }
}