<?php

namespace Bilaliqbalr\LaravelRedis\Commands;

use Illuminate\Console\Command;

class LaravelRedisCommand extends Command
{
    public $signature = 'laravel-redis';

    public $description = 'My command';

    public function handle()
    {
        $this->comment('All done');
    }
}
