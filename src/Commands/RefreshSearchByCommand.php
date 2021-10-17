<?php

namespace Bilaliqbalr\LaravelRedis\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class RefreshSearchByCommand extends Command
{
    public $signature = 'refresh:search_by {model : Redis model}';

    public $description = 'This command will maintain all search by fields in specified model';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $modelName = $this->argument('model');

        if (!class_exists($modelName)) {
            $this->error("Invalid model provided {$modelName}");
            return 0;
        }

        // Getting all search by fields
        $model = app($modelName);
        $searchByFields = $model->getSearchByFields();

        // Getting all records of model
        $allRecordsKeys = $model->getAllKeys(true);

        foreach ($allRecordsKeys as $key) {
            $rec = $model->get($key);

            foreach ($searchByFields as $field) {
                // Field must have data in the record, otherwise it will be ignored
                if ($rec instanceof $modelName) {
                    if (isset($rec->{$field}) && !is_null($rec->{$field})) {
                        $newKey = $model->getSearchColumnKey($field, $rec->{$field});

                        if ( ! $model->getConnection()->exists($newKey)) {
                            $id = $rec->{$model->getKeyName()};
                            // Adding fields to make them searchable
                            $model->getConnection()->set(
                                $newKey,
                                $id
                            );

                            $this->info("Make record {$id} searchable by {$field}");
                        }
                    }
                }
            }
        }

        return 1;
    }
}
