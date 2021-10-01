<?php

namespace Bilaliqbalr\LaravelRedis\Commands;

use Illuminate\Console\GeneratorCommand;

class LaravelRedisMakeCommand extends GeneratorCommand
{
    public $signature = 'redis:model {name : The name of the redis model}';

    public $description = 'Create a new Redis model class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Redis model';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/stubs/model.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        if (is_dir(app_path('Models/'))) {
            return $rootNamespace.'\Models\Redis';
        }

        return $rootNamespace.'\Redis';
    }
}
