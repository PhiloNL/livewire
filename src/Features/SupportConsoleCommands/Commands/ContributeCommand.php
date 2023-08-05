<?php

namespace Livewire\Features\SupportConsoleCommands\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class ContributeCommand extends Command
{
    protected $signature = 'livewire:contribute {--pr}';

    protected $description = 'Contribute to Livewire\'s development.';
    private const CLONE_DIR = 'livewire';

    public function handle()
    {
        $githubCli = (new ExecutableFinder)->find('gh');
        $composerCli = (new ExecutableFinder)->find('composer');
        $npmCli = (new ExecutableFinder)->find('npm');

        if($this->option('pr')) {
            $prTitle = text(
                label: 'What is the title of your pull request?',
                placeholder: 'My awesome pull request',
                required: true
            );

            $prDescription = text(
                label: 'What is the description of your pull request?',
                placeholder: 'Fixed a bug',
                required: true
            );

            //$result = Process::run("{$githubCli} cd contribute/livewire && gh pr create --title=\"{$prTitle}\" --body=\"{$prDescription}\" --base=master --repo=livewire/livewire");

            //dd($prTitle, $prDescription);

            $this->info('🔥 PR created! http://github.com/livewire/livewire/pull/1');

            return;
        }

        if(! $githubCli) {
            $this->warn('You need to install the GitHub CLI to use this command: https://cli.github.com/');
            return;
        }

        if(! $composerCli) {
            $this->warn('You need to install Composer to use this command: https://getcomposer.org/');
            return;
        }

        intro('LIVEWIRE CONTRIBUTE ASSISTANT');
        note('Hey there! Let\'s get you setup to contribute to Livewire!');
        note('Follow the instructions below to get started.');

        if(confirm(
            label: 'In order to contribute to Livewire, this command will fork the repository. Do you want to continue?',
        ) === false) {
            warning('Okay, see you next time!');
            return;
        }

        $result = spin(function () use ($githubCli) {
            return Process::run("{$githubCli} repo fork livewire/livewire --default-branch-only --clone=true --remote=false -- " . self::CLONE_DIR);
        }, 'Forking & cloning Livewire...');

        if(str($result->errorOutput())->contains('already exists')) {
            note('✅ Looks like you already have a fork of Livewire. Skipping fork...');
        }

        if(str($result->errorOutput())->contains('Created fork')) {
            note('✅ Livewire repository forked...');
        }

        if(!File::exists(base_path(self::CLONE_DIR))) {
            warning('Hmm, something went wrong. Unable to clone Livewire repository. Please try again.');
            return;
        }

        note('✅ Livewire repository cloned...');

        $result = spin(function () use ($composerCli) {
            sleep(2);

            $composerJson = json_decode(File::get(base_path('composer.json')), true);

            if (!in_array(['type' => 'path', 'url' => './' . self::CLONE_DIR], $composerJson['repositories'], true)) {
                $composerJson['repositories'][] = [
                    'type' => 'path',
                    'url' => './' . self::CLONE_DIR,
                ];

                File::put(base_path('composer.json'), json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            //$result = Process::run("{$composerCli} update livewire/livewire");
        }, 'Symlinking Livewire repository using Composer.');

        note('✅ Livewire repository symlinked...');

        $choices = multiselect(
            label: 'Where would you like to make changes?',
            options: [
                'back-end' => 'Livewire PHP codebase',
                'front-end' => 'Livewire JS codebase',
            ],
            default: ['back-end', 'front-end'],
        );

        if(in_array('back-end', $choices, true)) {
            $result = spin(function () use ($composerCli) {
                return Process::path(self::CLONE_DIR)->run(sprintf("{$composerCli} require \"laravel/framework:10.*\" --no-interaction --no-update --dev && {$composerCli} update --prefer-stable --no-interaction && vendor/bin/dusk-updater detect --no-interaction"));
            }, '[Composer] Installing Livewire development dependencies.');

            note('✅ Livewire composer development dependencies installed...');
        }

        if(in_array('front-end', $choices, true)) {
            $result = spin(function () use ($npmCli) {
                return Process::path(self::CLONE_DIR)->run("{$npmCli} ci");
            }, '[NPM] Installing Livewire development dependencies...');

            note('✅ Livewire NPM development dependencies installed...');
        }

        note('You can now make changes to Livewire in ./' . self::CLONE_DIR);
        note('To run tests, use the following commands within the ' . self::CLONE_DIR . ' directory:');

        $this->table(['Description', 'Command'], [
            ['Watch and compile JS changes', 'npm run watch'],
            ['Run tests', './vendor/bin/phpunit'],
            ['Run Unit tests', './vendor/bin/phpunit --testsuite Unit'],
            ['Run Browser tests', './vendor/bin/phpunit --testsuite Browser'],
            ['Run Legacy tests', './vendor/bin/phpunit --testsuite Legacy'],
        ]);


        //dd($result->output(), $result->errorOutput());
    }
}
