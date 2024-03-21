<?php
declare(strict_types=1);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use ILIAS\UI\Renderer;
use ILIAS\UI\Factory;

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

/**
 * @ilCtrl_isCalledBy ilObjAIChatGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls      ilObjAIChatGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI, ilExportGUI
 */
class ilObjAIChatGUI extends ilObjectPluginGUI
{
    protected $ctrl;
    protected $tabs;
    public $tpl;
    protected ilAIChatConfig $config;
    private static $factory;
    protected $control;
    protected $renderer;

    protected function afterConstructor() : void
    {
        global $ilCtrl, $ilTabs, $tpl, $DIC;
        $this->ctrl = $ilCtrl;
        $this->tabs = $ilTabs;
        $this->tpl = $tpl;
        $this->renderer = $DIC->ui()->renderer();

    }

    final public function getType() : string
    {
        return ilAIChatPlugin::ID;
    }

    public function performCommand(string $cmd) : void
    {
        switch ($cmd) {
            case "editProperties":   // list all commands that need write permission here
            case "updateProperties":
            case "saveProperties":
            case "showContent":   // list all commands that need read permission here
            case "setStatusToCompleted":
            case "setStatusToFailed":
            case "setStatusToInProgress":
            case "setStatusToNotAttempted":
            case "receiveMessages":
            case "getChatMessages":
            case "removeChatMessages":
            default:
                $this->checkPermission("read");
                $this->$cmd();
                break;
        }
    }

    function getAfterCreationCmd() : string
    {
        return "editProperties";
    }

    function getStandardCmd() : string
    {
        return "showContent";
    }

    /**
     * @throws ilCtrlException
     */
    protected function setTabs() : void
    {
        global $ilCtrl, $ilAccess;

        // tab for the "show content" command
        if ($ilAccess->checkAccess("read", "", $this->object->getRefId())) {
            $this->tabs->addTab("content", $this->txt("content"), $ilCtrl->getLinkTarget($this, "showContent"));
        }

        // standard info screen tab
        $this->addInfoTab();

        // a "properties" tab
        if ($ilAccess->checkAccess("write", "", $this->object->getRefId())) {
            $this->tabs->addTab(
                "properties",
                $this->txt("properties"),
                $ilCtrl->getLinkTarget($this, "editProperties")
            );
        }

        // standard permission tab
        $this->addPermissionTab();
        $this->activateTab();
    }

    /**
     * @throws ilCtrlException
     */
    protected function editProperties() : void
    {
        $this->tabs->activateTab("properties");

        $sections = $this->initPropertiesForm();
        $form_action = $this->control->getLinkTargetByClass("ilObjAIChatGUI", "editProperties");
        $rendered = $this->renderForm($form_action, $sections);

        $this->tpl->setContent($rendered);
    }

    /**
     * @throws ilCtrlException
     */
    protected function initPropertiesForm() : array
    {
        global $DIC;

        self::$factory = $DIC->ui()->factory();
        $this->control = $DIC->ctrl();

        $sections = [];

        try {
            $this->control->setParameterByClass('ilObjAIChatGUI', 'cmd', 'editProperties');
            $object = $this->object;
            $titleInput = self::$factory->input()->field()->text($this->plugin->txt("title"), '')
                ->withValue($object->getTitle())
                ->withRequired(true)
                ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                    function ($v) use ($object) {
                        $object->setTitle($v);
                    }
                ));

            $descriptionInput = self::$factory->input()->field()->text($this->plugin->txt("description"), '')
                ->withValue($object->getDescription())
                ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                    function ($v) use ($object) {
                        $object->setDescription($v);
                    }
                ));

            $formFields = [
                'title' => $titleInput,
                'description' => $descriptionInput,
            ];

            $onlineCheckbox = self::$factory->input()->field()->checkbox($this->plugin->txt("online"), '')
                ->withValue($object->isOnline())
                ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                    function ($v) use ($object) {
                        $object->setOnline($v);
                    }
                ));
            $formFields['online'] = $onlineCheckbox;

            $sectionObject = self::$factory->input()->field()->section($formFields, $this->plugin->txt("obj_xaic"), "");

            $sections["object"] = $sectionObject;

            if ($object instanceof ilObjAIChat && !$object->getUseGlobalApikey()) {
                $sectionObject = self::$factory->input()->field()->section([
                    "user_api_key" => self::$factory->input()->field()->text($this->plugin->txt("obj_apikey_input"), '')
                        ->withValue($object->getApiKey() ? ilAIChatUtils::decode($object->getApiKey())->apikey : '')
                        ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                            function ($v) use ($object) {
                                $object->setApiKey(ilAIChatUtils::encode(["apikey" => $v]));
                            }
                        ))
                ],$this->plugin->txt("api_key"), "");

                $sections["api_key"] = $sectionObject;

            }

        } catch(Exception $e){
            $section = self::$factory->messageBox()->failure($e->getMessage());
            $sections["object"] = $section;
        }

        return $sections;
    }

    /**
     * @throws ilCtrlException
     */
    private function renderForm(string $form_action, array $sections): string
    {
        GLOBAL $DIC;
        //Create the form
        $form = self::$factory->input()->container()->form()->standard(
            $form_action,
            $sections
        );

        $saving_info = "";

        $request = $DIC->http()->request();

        //Check if the form has been submitted
        if ($request->getMethod() == "POST") {
            $form = $form->withRequest($request);
            $result = $form->getData();
            if($result){
                $saving_info = $this->saveProperties($result);
            }
        }

        return $saving_info . $this->renderer->render($form);
    }

    protected function saveProperties(array $data) : string
    {
        GLOBAL $DIC;
        $renderer = $DIC->ui()->renderer();

        $this->object->update();

        return $renderer->render(self::$factory->messageBox()->success($this->plugin->txt('info_config_saved')));

    }

    /**
     * @throws ilTemplateException
     */
    protected function showContent() : void
    {

        $this->tabs->activateTab("content");
        $this->startChat();

    }

    private function activateTab() : void
    {
        $next_class = $this->ctrl->getCmdClass();
    }

    /**
     * Start the chat
     * @throws ilTemplateException
     */
    protected function startChat() : void
    {

        global $DIC;

        $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
		


        $host = $_SERVER['HTTP_HOST'];

        $urlCompleta = $protocolo . "://" . $host;
		
		$tpl = new ilTemplate('index.html', true, true, "Customizing/global/plugins/Services/Repository/RepositoryObject/AIChat/");

        $DIC->globalScreen()->layout()->meta()->addJs('Customizing/global/plugins/Services/Repository/RepositoryObject/AIChat/templates/default/index.js');
        
        $cmdNode = $_GET['cmdNode'];
        
        $tpl->setVariable("CLEAR_TEXT", $this->plugin->txt("clear_chat"));
        $tpl->setVariable("ID", $this->object->getRefId());
        $tpl->setVariable("URL", $urlCompleta);
        $tpl->setVariable("CMD_NODE", $cmdNode);
        $this->tpl->setContent($tpl->get());


    }

    /**
     * @throws ilException
     * @throws ilObjectNotFoundException
     * @throws ilDatabaseException
     */
    protected function receiveMessages()
    {
        global $ilUser;

        $userId = $ilUser->getId();
        $this->initObject((int)$_POST["id"]);
        $request= json_decode((string)$_POST["messages"]);



        if(!$this->object instanceof ilObjAIChat){
            return;
        }

        ilAIChatUtils::sendToApi($request, $this->object, $userId, $this->plugin);

    }

    /**
     * @throws ilObjectNotFoundException
     * @throws ilException
     * @throws ilDatabaseException
     */
    protected function getChatMessages()
    {
        global $ilUser;
        $userId = $ilUser->getId();
        $id = json_decode($_POST["id"]);
        $this->initObject($id);
        if(!$this->object instanceof ilObjAIChat){
            return;
        }

        $messages = $this->object->getMessagesJson($this->object->getId(), $userId);
        ilAIChatUtils::sendResponseJson([
            "messages" => $messages
        ]);

    }

    /**
     * @throws ilException
     */
    protected function removeChatMessages()
    {
        global $ilUser;

        $userId = $ilUser->getId();
        $id = json_decode((string)$_POST["id"]);
        $this->initObject((int)$id);
        if(!$this->object instanceof ilObjAIChat){
            return;
        }
        $this->object->saveMessagesJson("", $this->object->getId(), $userId);
        $messages = $this->object->getMessagesJson($this->object->getId(), $userId);
        ilAIChatUtils::sendResponseJson([
            "messages" => $messages
        ]);

    }


    /**
     * @throws ilException
     * @throws ilObjectNotFoundException
     * @throws ilDatabaseException
     */
    protected function initObject($id) : void
    {
        if (!$this->object) {
            if ($id) {
                $object = ilObjectFactory::getInstanceByRefId($id, false);
                if ($object instanceof ilObjAIChat) {
                    $this->object = $object;
                } else {
                    throw new ilException("Failed to instantiate the object. Expected ilObjAIChat, got " . get_class($object));
                }
            } else {
                throw new ilException("Reference ID is missing.");
            }
        }
    }
}