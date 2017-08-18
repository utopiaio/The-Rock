<?php
  class SQLite extends SQLite3 {
    function __construct($path) {
      $this -> open($path);
    }
  }
?>
