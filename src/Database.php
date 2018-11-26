<?php

namespace kburnik\scheduler;

use Illuminate\Database\Capsule\Manager as Capsule;

/** Provides a static database configuration method to use eloquent. */
class Database {
  public static function createCapsule(array $options = []) {
    $options = array_merge([
        'driver'    => 'mysql',
        'host'      => 'localhost',
        'database'  => 'mydb',
        'username'  => 'root',
        'password'  => '',
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix'    => '',
    ], $options);

    $capsule = new Capsule;
    $capsule->addConnection($options);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    return $capsule;
  }
}
