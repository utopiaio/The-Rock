<?php
  class SQLite extends SQLite3 {
    // this is redundant but "extension" proof
    function __construct($path, $flags) {
      $this->open($path, $flags);
    }
  }
