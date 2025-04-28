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

use Closure;
use ILIAS\Data\Factory;
use ILIAS\Refinery\Constraint;
use ILIAS\UI\Component\Input\Container\Form\FormInput;
use ILIAS\UI\Component\Signal;
use ILIAS\UI\Implementation\Component\Input\Input;
use ILIAS\UI\Implementation\Component\JavaScriptBindable;
use ILIAS\UI\Implementation\Component\Triggerer;


/**
 * Class Html
 */
class Html extends Input implements FormInput {
    use JavaScriptBindable;
    use Triggerer;

    protected string $label;
    protected ?string $byline;
    protected string $html = "";
    protected bool $is_required = false;
    protected bool $is_disabled = false;
    protected ?Constraint $requirement_constraint = null;


    public function __construct(string $html)
    {
        global $DIC;

        $this->html = $html;

        parent::__construct(new Factory(), $DIC->refinery());
    }

    protected function isClientSideValueOk($value): bool
    {
        return true;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function withLabel(string $label): self
    {
        $clone = clone $this;
        $clone->label = $label;
        return $clone;
    }

    public function getByline(): ?string
    {
        return $this->byline;
    }

    public function withByline(string $byline): self
    {
        $clone = clone $this;
        $clone->byline = $byline;
        return $clone;
    }

    public function isRequired(): bool
    {
        return $this->is_required;
    }

    public function withRequired(bool $is_required, ?Constraint $requirement_constraint = null): self
    {
        $clone = clone $this;
        $clone->is_required = $is_required;
        $clone->requirement_constraint = ($is_required) ? $requirement_constraint : null;
        return $clone;
    }

    public function isDisabled(): bool
    {
        return $this->is_disabled;
    }

    public function withDisabled(bool $is_disabled): self
    {
        $clone = clone $this;
        $clone->is_disabled = $is_disabled;
        return $clone;
    }

    public function getUpdateOnLoadCode(): Closure
    {
        return function () {};
    }

    public function withOnUpdate(Signal $signal): self
    {
        return $this->withTriggeredSignal($signal, 'update');
    }

    public function appendOnUpdate(Signal $signal): self
    {
        return $this->appendTriggeredSignal($signal, 'update');
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function withHtml(string $html): self
    {
        $clone = clone $this;
        $clone->html = $html;
        return $clone;
    }
}