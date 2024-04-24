<?php
declare(strict_types=1);

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
 * @ilCtrl_IsCalledBy  ilAIChatConfigGUI: ilObjComponentSettingsGUI
 */
class ilAIChatConfigGUI extends ilPluginConfigGUI
{
    private ilAIChatConfig $object;
    private static $factory;
    protected $control;
    protected $tpl;
    protected $request;
    protected $renderer;
    protected array $models = array(
        "gpt-4-1106-preview" => "gpt-4-1106-preview",
        "gpt-4-vision-preview" => "gpt-4-vision-preview",
        "gpt-4" => "gpt-4",
        "gpt-3.5-turbo-1106" => "gpt-3.5-turbo-1106",
        "gpt-3.5-turbo" => "gpt-3.5-turbo",
    );

    /**
     * @throws ilException
     */
    function performCommand($cmd): void
    {
        global $DIC;

        $this->object = new ilAIChatConfig();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->control = $DIC->ctrl();
        $this->request = $DIC->http()->request();
        $this->renderer = $DIC->ui()->renderer();

        switch ($cmd) {
            case "configure":
                $sections = $this->configure();
                $form_action = $this->control->getLinkTargetByClass("ilAIChatConfigGUI", "configure");
                $rendered = $this->renderForm($form_action, $sections);
                break;
            default:
                throw new ilException("command not defined");

        }

        $this->tpl->setContent($rendered);
    }

    private function configure(): array
    {
        global $DIC;

        self::$factory = $DIC->ui()->factory();
        $this->control = $DIC->ctrl();

        try {

            $this->control->setParameterByClass('ilAIChatConfigGUI', 'cmd', 'configure');
            $form_fields = [];

            $object = $this->object;
            $apikey = $object->getValue('apikey') ? ilAIChatUtils::decode($object->getValue('apikey'))->apikey : '';
            //Checkbox
            $field = self::$factory->input()->field()->optionalGroup([
                "global_api_key" => self::$factory->input()->field()->password(
                    $this->plugin_object->txt('apikey'),
                    $this->plugin_object->txt('info_apikey'))
                    ->withValue($apikey)
                    ->withRevelation(true)
                    ->withRequired(true)
                    ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                        function ($v) use ($object) {
                            if ($v) {
                                $reflectionClass = new ReflectionClass('ILIAS\Data\Password');
                                $property = $reflectionClass->getProperty('pass');
                                $property->setAccessible(true);
                                $password = $property->getValue($v);
                                $object->setValue('apikey', ilAIChatUtils::encode(["apikey" => $password]));
                                $object->setValue('global_apikey', true);
                            }

                        }
                    ))
            ],
                $this->plugin_object->txt('global_apikey'),
                $this->plugin_object->txt('info_global_api_key')
            )
                ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                    function ($v) use ($object) {
                        if ($v == null) {
                            $object->setValue('apikey', "");
                            $object->setValue('global_apikey', false);
                        }

                    }
                ));
            if ($object->getValue('global_apikey') == "0") {
                $field = $field->withValue(null);
            }

            //Model
            $modelInput = self::$factory->input()->field()->select(
                $this->plugin_object->txt('model'),
                $this->models, $this->plugin_object->txt('info_model'))
                ->withValue($object->getValue('model'))
                ->withRequired(true)
                ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                    function ($v) use ($object) {
                        $object->setValue('model', $v);
                    }
                ));

                $disclaimer = $object->getValue('disclaimer') ?: '';

            $disclaimerArea = self::$factory->input()->field()->textarea($this->plugin_object->txt("disclaimer"), '')
                ->withValue($disclaimer)
                ->withMaxLimit(4000)
                ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                    function ($v) use ($object) {
                        $object->setValue('disclaimer', $v);
                    }
                ));

            $form_fields["use_global_api_key"] = $field;
            $form_fields["model"] = $modelInput;
            $form_fields['disclaimer'] = $disclaimerArea;

            $section = self::$factory->input()->field()->section($form_fields, $this->plugin_object->txt("settings"), "");

        } catch (Exception $e) {
            $section = self::$factory->messageBox()->failure($e->getMessage());
        }

        return ["config" => $section];

    }

    /**
     * @throws ilCtrlException
     */
    private function renderForm(string $form_action, array $sections): string
    {
        //Create the form
        $form = self::$factory->input()->container()->form()->standard(
            $form_action,
            $sections
        );

        $saving_info = "";

        //Check if the form has been submitted
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
        return $this->renderer->render(self::$factory->messageBox()->success($this->plugin_object->txt('info_config_saved')));
    }
}