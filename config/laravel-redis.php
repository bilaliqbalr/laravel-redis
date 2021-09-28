<?php

return [

    /**
     * Redis database connection in config/database.php
     */
    'connection' => 'default',

    /**
     * Name of redis custom guard
     */
    'api-guard' => 'redis-api',

    /**
     * Name of redis custom user provider
     */
    'provider' => 'redis'

];
