<?php

namespace kburnik\scheduler;

use ReflectionClass;
use ReflectionMethod;

/** A wrapper class for posting tasks in the background. */
class SchedulerContext {
  private $scheduler;
  private $instance;
  private $className;
  private $taskOptions;

  public function __construct(
      Scheduler $scheduler,
      $instance,
      $taskOptions = []) {
    $this->scheduler = $scheduler;
    $this->instance = $instance;
    $this->className = get_class($instance);
    $this->taskOptions = $taskOptions;
  }

  /**
   * Posts a task to run in the background (e.g. via cron or CLI).
   *
   * Method is any public method from the target class with some restrictions:
   *
   * 1) Arguments and return values must be json serializable.
   * 2) WATCH OUT FOR RECURSION! E.g. Posting a task which posts itself.
   *
   * Typicaly you would call a synchronous (i.e. blocking) foreground method as:
   *   $targetInstance->doStuff('foo');
   *
   * In order to run this in a separate process at a later time, just write:
   *   $targetInstance->scheduled()->doStuff('foo');
   *
   * Given that the scheduled() method returns the SchedulerContext and
   * doStuff() is a public instance method of $targetClass.
   */
  public function __call($method, $args) {
    if (!is_callable([$this->instance, $method])) {
      throw new BackgroundTaskError(
            "Cannot schedule call for method: {$method}");
    }
    return $this->scheduler->schedule(
        BackgroundTask::create(
            "{$this->className}/{$method}",
            $args,
            $this->taskOptions));
  }

  /** Registers handlers for all target class public methods. */
  public function __register() {
    $class = new ReflectionClass($this->className);
    $methods = $class->getMethods(
        ReflectionMethod::IS_PUBLIC & ~ReflectionMethod::IS_STATIC);
    foreach ($methods as $method) {
      $this->scheduler->registerHandler(
          "{$this->className}/{$method->name}",
          [$this->instance, $method->name]);
    }
  }
}

