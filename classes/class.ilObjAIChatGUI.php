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

use ILIAS\UI\Component\Input\Group;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use objects\AIChat;
use objects\Chat;
use objects\Message;
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
    private function content()
    {
        global $DIC;
        $this->tabs->activateTab("content");
        $tpl = $DIC['tpl'];
        //$tpl = new ilTemplate("index.html", false, false, $this->plugin->getDirectory());
        $tpl->addCss($this->plugin->getDirectory() . "/templates/default/index.css");
        $tpl->addJavascript($this->plugin->getDirectory() . "/templates/default/index.js");

        $apiUrl = $this->ctrl->getLinkTargetByClass("ilObjAIChatGUI", "apiCall");

        $this->tpl->setContent("<div id='root' apiurl='$apiUrl'></div>");
    }

    /**
     * @throws AIChatException
     * @throws ilCtrlException
     */
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
        /**
         * @var $aiChat AIChat
         */
        $aiChat = $this->object->getAIChat();

        $title_input = $this->factory->input()->field()->text(
            $this->plugin->txt('object_settings_title')
        )->withValue($this->object->getTitle())->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) {
                $this->object->setTitle($v);
            }
        ));

        $description_input = $this->factory->input()->field()->textarea(
            $this->plugin->txt('object_settings_description')
        )->withValue($this->object->getDescription())->withAdditionalTransformation($this->refinery->custom()->transformation(
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

        $provider = $this->factory->input()->field()->switchableGroup(
            array(
                "default" => $this->factory->input()->field()->group(array(), $this->plugin->txt('config_default')),
                "openai" => $this->buildOpenAIGroup(),
                "custom" => $this->buildCustomGroup()
            ),
            $this->plugin->txt('config_provider')
        )->withValue($aiChat->getProvider(true) != "" ? $aiChat->getProvider(true) : "default")->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setProvider($v[0]);
            }
        ));

        $api_section = $this->factory->input()->field()->section(
            array(
                $provider
            ),
            $this->plugin->txt('config_api_section')
        );

        $prompt_selection = $this->factory->input()->field()->textarea(
            $this->plugin->txt('config_prompt_selection'),
            $this->plugin->txt('config_prompt_selection_info')
        )->withValue($aiChat->getPrompt(true))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setPrompt($v);
            }
        ))->withOnloadCode(function ($id) use ($aiChat) {
            return "$('#$id').attr('placeholder', `{$aiChat->getPrompt()}`);";
        });

        $characters_limit = $this->factory->input()->field()->numeric(
            $this->plugin->txt('config_characters_limit'), $this->plugin->txt('config_characters_limit_info')
        )->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setCharLimit($v);
            }
        ))->withOnloadCode(function ($id) use ($aiChat) {
            return "$('#$id').attr('placeholder', '{$aiChat->getCharLimit()}');";
        });

        if ($aiChat->getCharLimit(true) > 0) {
            $characters_limit = $characters_limit->withValue($aiChat->getCharLimit(true));
        }

        $n_memory_messages = $this->factory->input()->field()->numeric(
            $this->plugin->txt('config_n_memory_messages'), $this->plugin->txt('config_n_memory_messages_info')
        )->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setMaxMemoryMessages($v);
            }
        ))->withOnloadCode(function ($id) use ($aiChat) {
            return "$('#$id').attr('placeholder', '{$aiChat->getMaxMemoryMessages()}');";
        });

        if ($aiChat->getMaxMemoryMessages(true) > 0) {
            $n_memory_messages = $n_memory_messages->withValue($aiChat->getMaxMemoryMessages(true));
        }

        $disclaimer_text = $this->factory->input()->field()->textarea(
            $this->plugin->txt('config_disclaimer_text'),
            $this->plugin->txt('config_disclaimer_text_info')
        )->withValue($aiChat->getDisclaimer(true))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setDisclaimer($v);
            }
        ))->withOnloadCode(function ($id) use ($aiChat) {
            return "$('#$id').attr('placeholder', `{$aiChat->getDisclaimer()}`);";
        });

        $general_section = $this->factory->input()->field()->section(
            array(
                $prompt_selection,
                $characters_limit,
                $n_memory_messages,
                $disclaimer_text,
            ),
            $this->plugin->txt('config_general_section')
        );

        return array(
            $basic_section,
            $api_section,
            $general_section,
        );
    }

    /**
     * @throws AIChatException
     */
    private function buildOpenAIGroup(): Group
    {
        /**
         * @var $aiChat AIChat
         */
        $aiChat = $this->object->getAIChat();

        $models = array(
            "gpt-4o" => "GPT-4o",
            "gpt-4o-mini" => "GPT-4o mini",
            "gpt-4-turbo" => "GPT-4 Turbo",
            "gpt-4" => "GPT-4",
            "gpt-3.5-turbo" => "GPT-3.5 Turbo"
        );

        $model = $this->factory->input()->field()->select(
            $this->plugin->txt('config_model'),
            $models,
            $this->plugin->txt('config_model_info')
        )->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setModel($v);
            }
        ))->withRequired(true);

        if ($aiChat->getModel(true) != "") {
            if (array_key_exists($aiChat->getModel(true), $models)) {
                $model = $model->withValue($aiChat->getModel(true));
            }
        }

        $global_api_key = $this->factory->input()->field()->text(
            $this->plugin->txt('config_global_api_key')
        )->withValue($aiChat->getApiKey(true))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setApiKey($v);
            }
        ))->withRequired(true);

        $streaming_enabled = $this->factory->input()->field()->checkbox(
            $this->plugin->txt('config_streaming_enabled'),
            $this->plugin->txt('config_streaming_enabled_info')
        )->withValue($aiChat->isStreaming(true))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setStreaming($v);
            }
        ));

        return $this->factory->input()->field()->group(
            array(
                $model,
                $global_api_key,
                $streaming_enabled
            ),
            $this->plugin->txt('config_openai')
        );
    }

    /**
     * @throws AIChatException
     */
    private function buildCustomGroup(): Group
    {
        /**
         * @var $aiChat AIChat
         */
        $aiChat = $this->object->getAIChat();

        $url = $this->factory->input()->field()->text(
            $this->plugin->txt('config_url'),
            $this->plugin->txt('config_url_info')
        )->withValue($aiChat->getUrl(true))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setUrl($v);
            }
        ))->withRequired(true);

        $model = $this->factory->input()->field()->text(
            $this->plugin->txt('config_model'),
            $this->plugin->txt('config_model_info')
        )->withValue($aiChat->getModel(true))->withAdditionalTransformation($this->refinery->custom()->transformation(
            function ($v) use ($aiChat) {
                $aiChat->setModel($v);
            }
        ))->withRequired(true);

//        $global_api_key = $this->factory->input()->field()->text(
//            $this->plugin->txt('config_global_api_key')
//        )->withValue($aiChat->getApiKey(true))->withAdditionalTransformation($this->refinery->custom()->transformation(
//            function ($v) use ($aiChat) {
//                $aiChat->setApiKey($v);
//            }
//        ))->withRequired(true);

        return $this->factory->input()->field()->group(
            array(
                $url,
                $model,
//                $global_api_key
            ),
            $this->plugin->txt('config_custom')
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

                return array(
                    "disclaimer" => $aiChat->getDisclaimer() ?? false,
                    "prompt_selection" => $aiChat->getPrompt() ?? false,
                    "characters_limit" => $aiChat->getCharLimit() ?? false,
                    "n_memory_messages" => $aiChat->getMaxMemoryMessages() ?? false,
                    "streaming_enabled" => $aiChat->isStreaming() ?? false,
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
}