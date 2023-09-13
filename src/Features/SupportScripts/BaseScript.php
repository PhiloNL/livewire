<?php

namespace Livewire\Features\SupportScripts;

use Livewire\Features\SupportAttributes\Attribute as LivewireAttribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class BaseScript extends LivewireAttribute
{
    function __construct(
        public $path = null,
        public $js = null,
    ) {}

    function boot()
    {
        if($this->path) {
            $this->component->addScript($this->path);
        }

        if($this->js) {
            $this->component->addScript($this->js);
        }
    }
}
