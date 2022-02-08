<?php
require 'vendor/autoload.php';
use Nyholm\Dsn\DsnParser;
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
date_default_timezone_set(getenv('TIMEZONE'));

$dsn = DsnParser::parse(getenv('DATABASE_URL'));

$scheme = array(
    "postgresql"=>"pgsql", 
    "mysql"=>"mysql"
    )[$dsn ->getScheme()];

return array(
        'environments' =>
             array(
                'default_migration_table' => 'phinxlog',
                'default_database' => 'production',
                'production' => array(
                    'host' => $dsn ->getHost(),
                    'name' => ltrim($dsn ->getPath(), array('/')),
                    'user' => $dsn ->getUser(),
                    'pass' => $dsn ->getPassword(),
                    'port' => $dsn ->getPort() ?? 3306,
                    'charset' => 'utf8',
                    'adapter' => $scheme
               )
             ),
         'paths' =>
            array(
                'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
                'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
            )
       );