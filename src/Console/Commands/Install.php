<?php

namespace Statamic\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Console\Composer\Json;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\File;
use Statamic\Statamic;
use Statamic\UpdateScripts\UpdateScript;

class Install extends Command
{
    use RunsInPlease;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statamic:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Statamic';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->addons()
             ->createFiles()
             ->publish()
             ->runCallbacks()
             ->clearViews()
             ->clearCache()
             ->runUpdateScripts();
    }

    protected function addons()
    {
        $this->call('statamic:addons:discover');

        return $this;
    }

    protected function createFiles()
    {
        $gitkeeps = [
            config('statamic.stache.stores.asset-containers.directory'),
            config('statamic.stache.stores.collections.directory'),
            config('statamic.stache.stores.globals.directory'),
            config('statamic.stache.stores.taxonomies.directory'),
            config('statamic.stache.stores.navigation.directory'),
            config('statamic.users.repositories.file.paths.users'),
        ];

        $gitignores = [
            storage_path('statamic'),
        ];

        foreach (array_filter($gitkeeps) as $dir) {
            if (! File::exists($gitkeep = $dir.'/.gitkeep')) {
                File::put($gitkeep, '');
                $this->info("Created the <comment>[$dir]</comment> directory.");
            }
        }

        foreach (array_filter($gitignores) as $dir) {
            if (! File::exists($gitignore = $dir.'/.gitignore')) {
                File::put($gitignore, "*\n!.gitignore");
                $this->info("Created the <comment>[$dir]</comment> directory.");
            }
        }

        return $this;
    }

    protected function publish()
    {
        $this->call('vendor:publish', ['--tag' => 'statamic']);
        $this->call('vendor:publish', ['--tag' => 'statamic-cp', '--force' => true]);

        return $this;
    }

    protected function clearViews()
    {
        $this->call('view:clear');

        return $this;
    }

    protected function clearCache()
    {
        $this->call('cache:clear');

        return $this;
    }

    protected function runCallbacks()
    {
        Statamic::runAfterInstalledCallbacks($this);

        return $this;
    }

    protected function runUpdateScripts()
    {
        if (Json::isMissingPreUpdateCmd()) {
            return $this->outputMissingPreUpdateCmd();
        }

        UpdateScript::runAll($this);

        return $this;
    }

    protected function outputMissingPreUpdateCmd()
    {
        $this->error('We notice you are missing a composer hook!');
        $this->error('Please ensure the following is registered in the `scripts` section of your composer.json file,');
        $this->error('And re-run [php artisan statamic:install] when complete.');

        $this->line(<<<'EOT'
"scripts": {
    "pre-update-cmd": [
        "Statamic\\Console\\Composer\\Scripts::preUpdateCmd"
    ],
    ...
}
EOT
        );

        return $this;
    }
}
