<?php

namespace kburnik\scheduler;

class BackgroundTask {
  public $id;
  public $category;
  public $name;
  public $arguments;
  public $created_at;
  public $due_at;
  public $started_at;
  public $completed_at;
  public $attempts;
  public $max_attempts;
  public $offset_seconds;
  public $backoff_factor;
  public $status;
  public $output;

  public function __construct(array $data) {
    foreach ($this as $key => $_) {
      $this->$key = $data[$key];
    }
  }

  public static function create(
      $namespace,
      array $arguments = [],
      array $options = []) {
    list($category, $name) = explode('/', $namespace);
    return new BackgroundTask(array_merge(
        self::defaults(),
        [
          'category' => $category,
          'name' => $name,
          'arguments' => json_encode($arguments)
        ],
        $options));
  }

  public static function mapEach($tasks) {
    return array_map(function($task) {
      return new BackgroundTask((array)$task);
    }, $tasks);
  }

  public function updateSlice($values) {
    foreach ($values as $key => $value) {
      $this->$key = $value;
    }
    $this->updated_at = date('Y-m-d H:i:s');
  }

  public function toArray() {
    return (array)$this;
  }

  public function getExcerpt($maxLength = 100) {
    $args = $this->arguments;
    if (strlen($args) > $maxLength) {
      $args = substr($args, 0, $maxLength - 3) . '...';
    }
    return "{$this->category}/{$this->name} {$args}";
  }

  public static function defaults() {
    return [
      'category' => 'generic',
      'name' => 'task',
      'arguments' => '[]',
      'attempts' => 0,
      'max_attempts' => 10,
      'started_at' => null,
      'completed_at' => null,
      'created_at' => date('Y-m-d H:i:s'),
      'updated_at' => date('Y-m-d H:i:s'),
      'due_at' => date('Y-m-d H:i:s'),
      'backoff_factor' => 1.2,
      'offset_seconds' => 30,
      'status' => 'PENDING',
      'output' => ''
    ];
  }
}
