<?php

namespace Livewire\Features\SupportTeleporting;

use Illuminate\Support\Facades\Route;
use Laravel\Dusk\Browser;
use Livewire\Component;
use Livewire\Features\SupportPageComponents\ComponentWithTeleport;
use Livewire\Livewire;
use Tests\BrowserTestCase;

class BrowserTest extends BrowserTestCase
{
    /** @test */
    public function can_teleport_dom_via_blade_directive()
    {
        Livewire::visit(new class extends Component {
            public function render() { return <<<'HTML'
            <div dusk="component">
                @teleport('body')
                    <span>teleportedbar</span>
                @endteleport
            </div>
            HTML; }
        })
            ->assertDontSeeIn('@component', 'teleportedbar')
            ->assertSee('teleportedbar');
    }

    /** @test */
    public function can_teleport_dom_via_blade_directive_then_change_it()
    {
        Livewire::visit(new class extends Component {
            public $foo = 'bar';

            public function setFoo()
            {
                $this->foo = 'baz';
            }

            public function render() { return <<<'HTML'
            <div dusk="component">
                <button dusk="setFoo" type="button" wire:click="setFoo">
                    Set foo
                </button>

                @teleport('body')
                    <span>teleported{{ $foo }}</span>
                @endteleport
            </div>
            HTML; }
        })
            ->assertDontSeeIn('@component', 'teleportedbar')
            ->assertSee('teleportedbar')
            ->waitForLivewire()->click('@setFoo')
            ->assertDontSeeIn('@component', 'teleportedbaz')
            ->assertSee('teleportedbaz');
    }

    /** @test */
    public function can_teleport_full_when_using_full_page_components()
    {
        $this->tweakApplication(function() {
            Livewire::component('full-page-component', FullPageComponent::class);
            Livewire::component('child-component', ChildComponent::class);

            Route::get('/full-page-component', FullPageComponent::class)->middleware('web');
        });

        $this->browse(function (Browser $browser) {
            $browser->visit('/full-page-component')
                ->assertSee('I am a form')
                ->type('@input', 'hello world')
                ->waitForLivewire()
                ->assertSee('I am a form');
        });
    }
}

class FullPageComponent extends Component {

    public function render() { return <<<'HTML'
            <div dusk="component">
                <button dusk="something" type="button" wire:click="something">
                    Do Something
                </button>

                @teleport('body')
                    <livewire:child-component/>
                @endteleport
            </div>
        HTML; }
}

class ChildComponent extends Component {
    public $value;

    public function render() { return <<<'HTML'
            <form>
                I am a form
                <input type="text" dusk="input" wire:model.live="value">
                <button type="submit">Save</button>
            </form>
        HTML; }
}
