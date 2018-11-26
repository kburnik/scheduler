<?php

namespace kburnik\scheduler;

use Illuminate\Database\Eloquent\Model;

class DTOBackgroundTask extends Model {
  protected $table = "background_task";
  protected $fillable = [
    "id",
    "category",
    "name",
    "arguments",
    "due_at",
    "started_at",
    "completed_at",
    "attempts",
    "max_attempts",
    "offset_seconds",
    "backoff_factor",
    "status",
    "output"
  ];
  protected $primaryKey = "id";
  public $incrementing = true;
}
