<?php

namespace kburnik\scheduler;

class BackgroundTaskStatus {
  const PENDING = 'PENDING';
  const RUNNING = 'RUNNING';
  const COMPLETED = 'COMPLETED';
  const FAILED = 'FAILED';
}
