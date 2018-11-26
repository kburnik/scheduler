<?php

namespace kburnik\scheduler;

use Illuminate\Database\Capsule\Manager as Capsule;
use DateInterval;
use DateTime;
use Exception;

class Scheduler {
  private static $instance;
  private $handlers = [];
  private $task_scheduled_handler;
  private $task_started_handler;
  private $task_completed_handler;
  private $task_failed_handler;
  private $max_tasks_per_run;
  private $keep_completed_task_days;
  private $keep_other_task_days;

  public function __construct(array $options = []) {
    $options = array_merge([
      'task_scheduled_handler' => function(BackgroundTask $task) {
      },
      'task_started_handler' => function(BackgroundTask $task) {
      },
      'task_completed_handler' => function(BackgroundTask $task) {
      },
      'task_failed_handler' => function(BackgroundTask $task, $exception) {
        $date = date('Y-m-d H:i:s');
        echo "[{$date}] Failed task: {$task->getExcerpt()}\n";
        echo "Exception: {$exception->getMessage()}\n";
      },
      'table_name' => 'background_task',
      'max_tasks_per_run' => 100,
      'keep_completed_task_days' => 7,
      'keep_other_task_days' => 30
    ], $options);
    foreach ($options as $key => $value) {
      $this->$key = $value;
    }
  }

  public static function getInstance() {
    if (self::$instance === null) {
      self::$instance = new Scheduler();
    }
    return self::$instance;
  }

  public function registerHandler($namespace, $handler) {
    list($category, $name) = explode('/', $namespace);
    $this->handlers[$category][$name] = $handler;
    return $this;
  }

  public function schedule(BackgroundTask $task) {
    $result = DTOBackgroundTask::insert($task->toArray());
    call_user_func($this->task_scheduled_handler, $task);
    return $result;
  }

  public function run() {
    $tasks = $this->fetchDueTasks($this->max_tasks_per_run);
    foreach ($tasks as $task) {
      try {
        $this->runTask($task);
      } catch (Exception $ex) {
        call_user_func($this->task_failed_handler, $task, $ex);
      }
    }
  }

  /** Cleans the queue from old and completed tasks. */
  public function clean() {
    $completed = BackgroundTaskStatus::COMPLETED;
    $keep_completed_task_days = intval($this->keep_completed_task_days);
    $keep_other_task_days = intval($this->keep_other_task_days);
    Capsule::statement("
        DELETE FROM `{$this->table_name}` WHERE
        (
          status = '{$completed}'
            and due_at < date_add(
                now(), INTERVAL -{$keep_completed_task_days} DAY)
        )
        or due_at < date_add(now(), INTERVAL -{$keep_other_task_days} DAY)
        ;");
    Capsule::statement("OPTIMIZE TABLE `{$this->table_name}`;");
  }

  public function runTask(BackgroundTask $task) {
    call_user_func($this->task_started_handler, $task);
    $task->updateSlice([
      'started_at' => self::now(),
      'attempts' => $task->attempts + 1,
      'status' => BackgroundTaskStatus::RUNNING,
      'due_at' => $this->getNextDueDate(
          $task->offset_seconds *
          max(1.0, pow($task->backoff_factor, $task->attempts)))
    ]);
    $this->updateTask($task);

    if (!array_key_exists($task->category, $this->handlers)) {
      throw new BackgroundTaskError(
          "No handler was registered for task category: {$task->category}");
    }

    if (!array_key_exists($task->name, $this->handlers[$task->category])) {
      throw new BackgroundTaskError(
          "No handler was registered for task: " .
          "{$task->category}/{$task->name}");
    }

    $handler = $this->handlers[$task->category][$task->name];

    $arguments = json_decode($task->arguments, true);
    if (json_last_error() != JSON_ERROR_NONE) {
      throw new BackgroundTaskError(
          "Invalid task arguments: {$task->arguments}");
    }

    try {
      $result = call_user_func_array($handler, $arguments);
      $this->finishTaskAttempt($task, [
        'output' => json_encode($result),
        'status' => BackgroundTaskStatus::COMPLETED,
      ]);
      call_user_func($this->task_completed_handler, $task);
    } catch (Exception $ex) {
      $this->finishTaskAttempt($task, [
        'output' => $ex->getMessage(),
        'status' => BackgroundTaskStatus::FAILED,
      ]);
      throw $ex;
    }
  }

  private function finishTaskAttempt(BackgroundTask $task, array $updates) {
    $task->updateSlice(array_merge($updates, [
      'completed_at' => self::now()
    ]));
    $this->updateTask($task);
  }

  private function getNextDueDate($minutes) {
    $minutes = round($minutes);
    $time = new DateTime(self::now());
    $time->add(new DateInterval("PT{$minutes}S"));
    return $time->format('Y-m-d H:i:s');
  }

  private function updateTask(BackgroundTask $task) {
    return DTOBackgroundTask::where(['id' => $task->id])
        ->update($task->toArray());
  }

  private function fetchDueTasks($limit) {
    $limit = intval($limit);

    $tasks = Capsule::select("
        SELECT * from `{$this->table_name}`
        where due_at <= now()
        and `status` in ('PENDING', 'FAILED')
        and attempts < max_attempts
        ORDER BY due_at asc
        LIMIT {$limit}
        ;");
    return BackgroundTask::mapEach($tasks);
  }

  private static function now() {
    return date('Y-m-d H:i:s');
  }
}

