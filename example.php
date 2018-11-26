<?php

require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/common.php";

use kburnik\scheduler\Scheduler;
use kburnik\scheduler\Database;
use kburnik\scheduler\SchedulerContext;

function init() {
  Database::createCapsule([
    'host' => check_getenv('DB_HOST'),
    'database' => check_getenv('DB_DATABASE'),
    'username' => check_getenv('DB_USERNAME'),
    'password' => check_getenv('DB_PASSWORD'),
  ]);
}

class Schedulable {
  private $context;

  public function __construct() {
    $this->scheduler = new Scheduler();
    $this->context = new SchedulerContext($this->scheduler, $this, []);
  }

  public function produce() {
    $this->scheduled()->task(rand(10000, 99999));
  }

  public function task($value) {
    echo "Running task with $value\n";
  }

  public function scheduled() {
    return $this->context;
  }

  public function run() {
    $this->context->__register();
    $this->scheduler->run();
    $this->scheduler->clean();
  }
}


function main($args) {
  init();
  $schedulable = new Schedulable();
  if ($args[1] == 'produce') {
    $schedulable->produce();
  } else if ($args[1] == 'consume') {
    $schedulable->run();
  } else {
    echo "Usage: {$args[0]} produce|consume\n";
    exit(1);
  }
}

main($argv);
