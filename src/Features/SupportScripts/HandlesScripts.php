<?php

namespace Livewire\Features\SupportScripts;

trait HandlesScripts
{
    protected array $scripts = [];

    public function addScript($path): void
    {
        $this->scripts[] = $path;
    }

    public function getScripts(): array
    {
        return $this->scripts;
    }
}
