<?php

namespace Livewire\Features;

use function Livewire\invade;
use Livewire\Synthesizers\LivewireSynth;
use Livewire\Regexes;
use Livewire\Mechanisms\ComponentDataStore;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Blade;

// This is the current behavior. The problem is that it only
// currently supports child scope. It COULD support parent
// but then it wouldn't support child lol. Both can only
// be supported if the slots are completely static.
//
// @php
//     $foo = 'bar';
// @endphp

// <livewire:counter :slot="[$count]">
//     <div>This will work and live update: {{ $count }}!</div>

//     <h1>Unfortunately, this won't work: {{ $foo }}</h1>
// </livewire>

class SupportSlots
{
    public function __invoke()
    {
        $compiler = function ($string) {
            $pattern = '/'.Regexes::$livewireOpeningTag.'(?<body>.*)'.Regexes::$livewireClosingTag.'/xsm';

            return preg_replace_callback($pattern, function ($matches) use ($string) {
                $body = $matches['body'];

                [$opening, $closing] = str($matches[0])->explode($body);

                // Look for any named slots and extract them:
                $slotPattern = '/'.Regexes::specificBladeDirective('slot').'(?<body>.+?)@endslot/xsm';

                $slots = [];

                $body = preg_replace_callback($slotPattern, function ($matches) use (&$slots) {
                    $arguments = $matches[3];

                    $noop = fn($i) => $i;

                    $scope = (string) str($arguments)->match('/\'scope\' => \\\Illuminate\\\View\\\Compilers\\\BladeCompiler::sanitizeComponentAttribute\((.*?)\)/');
                    $name = (string) str($arguments)->between("('", "', ");

                    $strippedScope = str($scope)->between('[', ']')->explode(',')->map(fn ($i) => trim($i, ' '))->map(fn ($i) => trim($i, '$'))->filter(fn ($i) => $i !== '')->toArray();

                    $hash = Str::random(20);
                    $slotBody = $matches['body'];
                    $path = storage_path('framework/cache/livewire-'.$hash.'.blade.php');
                    file_put_contents($path, $slotBody);

                    $slots[$name === '' ? 'default' : $name] = ['hash' => $hash, 'scope' => $strippedScope];

                    return '';
                }, $body);

                if ((string) str($body)->replaceMatches('/\s/', '') !== '') {
                    // There still more inside the slot, so we'll take care of it...
                    $scope = '';
                    $strippedScope = [];

                    // Look in the opening for a slot scope declaration, extract, and remove it...
                    if (str($opening)->contains(':scope')) {
                        $scope = (string) str($opening)->match('/:scope="\[([^\]]*)\]"/');

                        $strippedScope = str($scope)->explode(',')->map(fn ($i) => trim($i, ' '))->map(fn ($i) => trim($i, '$'))->filter(fn ($i) => $i !== '')->toArray();

                        $opening = (string) str($opening)->replace(':scope="['.$scope.']"', '');
                    }

                    if (! isset($slots['default'])) {
                        $hash = Str::random(20);
                        $slotBody = $body;
                        $path = storage_path('framework/cache/livewire-'.$hash.'.blade.php');
                        file_put_contents($path, $slotBody);
                        $slots['default'] = ['hash' => $hash, 'scope' => $strippedScope];
                    }
                }

                $encodedSlots = json_encode($slots);
                return "<?php \$__slots = json_decode('$encodedSlots', true); ?>\n".$opening;
            }, $string);
        };

        // This ensures we'll be at the top of the precompilers...
        invade(app('blade.compiler'))->precompilers = [$compiler, ...invade(app('blade.compiler'))->precompilers];

        Blade::directive('renderSlot', function ($expression) {
            return <<<HTML
                <?php
                    [\$__name, \$__scope] = (function(\$name = 'default', \$scope = []) {
                        if (is_array(\$name)) {
                            return ['default', \$name];
                        }

                        return [\$name, \$scope];
                    })($expression);


                    \$__slotProps = \Livewire\Mechanisms\ComponentDataStore::get(\$__livewire, 'slotProps', []);

                    \$__slotProps[\$__name] = \$__scope;

                    \Livewire\Mechanisms\ComponentDataStore::set(\$__livewire, 'slotProps', \$__slotProps);

                    echo '|---SLOT:'.\$__name.'---|';

                    unset(\$__name);
                    unset(\$__scope);
                    unset(\$__slotProps);
                ?>
                HTML;
        });

        app('synthetic')->on('mount', function ($name, $params, $parent, $key, $slots) {
            if (! $slots) return;

            return function ($target) use ($slots) {
                ComponentDataStore::set($target, 'slots', $slots);
            };
        });

        app('synthetic')->on('dehydrate', function ($synth, $target, $context) {
            if (! $synth instanceof LivewireSynth) return;
            if (! ComponentDataStore::has($target, 'slots')) return;

            $slots = ComponentDataStore::get($target, 'slots');

            $context->addMeta('slots', $slots);

            return function ($value) use ($target, $context, $slots) {
                $html = $context->effects['html'];

                if (! $html) return $value;

                $slotProps = ComponentDataStore::get($target, 'slotProps', false);

                if ($slotProps === false) return $value;

                foreach ($slotProps as $name => $scope) {
                    ['hash' => $hash, 'scope' => $scopeNames] = $slots[$name];

                    $path = storage_path('framework/cache/livewire-'.$hash.'.blade.php');

                    $passThroughScope = count($scopeNames) === 1 && $scopeNames[0] === '__all__';

                    if ($passThroughScope) {
                        $scopeNames = array_keys($scope);
                    }

                    $slotContents = Blade::render(file_get_contents($path), array_intersect_key($scope, array_flip($scopeNames)));

                    $context->effects['html'] = str($html)->replace('|---SLOT:'.$name.'---|', $slotContents);
                }

                return $value;
            };
        });

        app('synthetic')->on('hydrate', function ($synth, $rawValue, $meta) {
            if (! $synth instanceof LivewireSynth) return;
            if (! isset($meta['slots'])) return;

            $slot = $meta['slots'];

            return function ($target) use ($slot) {
                ComponentDataStore::set($target, 'slots', $slot);

                return $target;
            };
        });
    }
}