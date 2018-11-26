<?php

/** Reads value from environment or throws exception if not set. */
function check_getenv($varname) {
  if (($value = getenv($varname)) === false) {
    throw new Exception("Missing ENV var: $varname");
  }
  return $value;
}

