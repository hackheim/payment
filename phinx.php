<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
date_default_timezone_set(getenv('TIMEZONE'));

return array(
        'environments' =>
             array(
                'default_migration_table' => 'phinxlog',
                'default_database' => 'production',
                'production' => array(
                    'dsn:' => getenv('DATABASE_URL')
               )
             ),
         'paths' =>
            array(
                'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
                'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
            )
       );