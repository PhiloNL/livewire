<?php

namespace Livewire\Attributes;

use Attribute;
use Livewire\Features\SupportScripts\BaseScript;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
class Script extends BaseScript
{
    //
}
