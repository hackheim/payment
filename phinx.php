<?php
require 'vendor/autoload.php';
$dotenv = new Dotenv\Dotenv('./');
$dotenv->load();
date_default_timezone_set(getenv('TIMEZONE'));

return array(
        'environments' =>
             array(
                'default_migration_table' => 'phinxlog',
                'default_database' => 'production',
                'production' => array(
                    'host' => getenv('PHINX_DBHOST'),
                    'name' => getenv('PHINX_DBNAME'),
                    'user' => getenv('PHINX_DBUSER'),
                    'pass' => getenv('PHINX_DBPASS'),
                    'port' => 3306,
                    'charset' => 'utf8',
                    'adapter' => 'mysql'
               )
             ),
         'paths' =>
            array(
                'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
                'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
            )
       );