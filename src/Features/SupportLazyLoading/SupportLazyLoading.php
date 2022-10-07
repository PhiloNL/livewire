<?php

namespace Livewire\Features\SupportLazyLoading;

class SupportLazyLoading
{
    public function boot()
    {
        // return;
        app('synthetic')->on('mount', function ($name, $params, $parent, $key, $slots, $hijack) {
            if ($name === 'lazy') return;
            if (! array_key_exists('lazy', $params)) return;
            unset($params['lazy']);

            [$html] = app('livewire')->mount('lazy', ['componentName' => $name, 'forwards' => $params], $key, $slots);

            $hijack($html);
        });

        app('livewire')->component('lazy', Lazy::class);
    }
}