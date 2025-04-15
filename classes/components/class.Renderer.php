<?php

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

declare(strict_types=1);

namespace Customizing\global\plugins\Services\Repository\RepositoryObject\AIChat\classes\components;

use ILIAS\UI\Component\Component;
use ILIAS\UI\Component\Input\Container\Form\FormInput;
use ILIAS\UI\Implementation\Component\Input\Field\Renderer as RendererILIAS;
use ILIAS\UI\Implementation\Render\Template;
use ilTemplate;
use ilTemplateException;

/**
 * Class Renderer
 */
class Renderer extends RendererILIAS
{
    /**
     * @throws ilTemplateException
     */
    public function render(Component $component, \ILIAS\UI\Renderer $default_renderer): string
    {
        global $DIC;

        $DIC->ui()->mainTemplate()->addCss("Customizing/global/plugins/Services/Repository/RepositoryObject/AIChat/templates/components/style.css");

        return match (true) {
            $component instanceof Hint => $this->renderHint($component),
            default => parent::render($component, $default_renderer),
        };
    }

    protected function maybeDisable(FormInput $component, ilTemplate|Template $tpl): void
    {
        if ($component->isDisabled()) {
            $tpl->setVariable("DISABLED", 'disabled="disabled"');
        }
    }

    protected function applyName(FormInput $component, ilTemplate|Template $tpl): ?string
    {
        $name = $component->getName();
        $tpl->setVariable("NAME", $name);
        return $name;
    }

    protected function bindJSandApplyId(FormInput $component, ilTemplate|Template $tpl): string
    {
        $id = $this->bindJavaScript($component) ?? $this->createId();
        $tpl->setVariable("ID", $id);
        return $id;
    }

    protected function applyValue(FormInput $component, ilTemplate|Template $tpl, callable $escape = null): void
    {
        $value = $component->getValue();
        if (!is_null($escape)) {
            $value = $escape($value);
        }
        if (isset($value) && $value != '') {
            $tpl->setVariable("VALUE", $value);
        }
    }

    private function getTemplateCustom(string $name): ilTemplate
    {
        return new ilTemplate("Customizing/global/plugins/Services/Repository/RepositoryObject/AIChat/templates/components/$name", true, true);
    }

    /**
     * @throws ilTemplateException
     */
    private function renderHint(Hint $component): string
    {
        $tpl = $this->getTemplateCustom("tpl.hint.html");

        $tpl->setVariable("LABEL", $component->getLabel());
        $tpl->setVariable("BYLINE", $component->getByline());

        return $tpl->get();
    }
}