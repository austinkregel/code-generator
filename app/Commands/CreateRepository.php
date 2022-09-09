<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Nette\PhpGenerator\PhpFile;

class CreateRepository extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'make:repository {name}';

    /**
     * Execute the console command.
     
     * @return mixed
     */
    public function handle()
    {
        $newModelFile = new PhpFile();
        $newModelFile->setStrictTypes(true);
        $namespace = $newModelFile->addNamespace(config('generator.default_repository_namespace').$this->argument('name'));
        // Imports
        $namespace->addUse(config('generator.default_repository_extends'));
        array_map(fn ($import) => $namespace->addUse($import), config('generator.default_repository_traits'));
        
        $newClass = $namespace->addClass(class_basename($this->argument('name')));
        $newClass->addExtend(config('generator.default_repository_extends'));

        array_map(fn ($import) => $newClass->addTrait($import), config('generator.default_repository_traits'));

        file_put_contents(getcwd().'/app/Contracts/' . str_replace('\\', '/', $this->argument('name')) .'Contract.php', $this->createInterface());
        file_put_contents(getcwd().'/app/Repositories/'. str_replace('\\', '/', $this->argument('name')) .'.php', $newModelFile);
        echo ($newModelFile);
    }

    protected function createInterface()
    {
        $newModelFile = new PhpFile();
        $newModelFile->setStrictTypes(true);
        $namespace = $newModelFile->addNamespace(config('generator.default_repository_interface_namespace').$this->argument('name'));
        // Imports
        $namespace->addUse(config('generator.default_repository_interface_extends'));
        array_map(fn ($import) => $namespace->addUse($import), config('generator.default_repository_interface_traits'));
        
        $newClass = $namespace->addInterface($this->argument('name'));
        $newClass->addExtend(config('generator.default_repository_interface_extends'));

        array_map(fn ($import) => $newClass->addTrait($import), config('generator.default_repository_interface_traits'));

        echo ($newModelFile);
    }

    protected function getClassBase()
    {
        return class_basename($this->argument('name'));
    }

    protected function getPath()
    {
        $path = str_replace('\\', '/', $this->argument('name'));

        return '/app/Models/' . $path .'.php';
    }
}
