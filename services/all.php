<?php
  /**
   * single request that returns ALL non-Auth / forbidden tables
   * api.io/all
   */
  $RestContainer["all"] = function($c) {
    return function($routeInfo) {
      $tables = Config::get("TABLES");
      unset($tables["users"]);
      unset($tables["s3"]);
      $AuthGETRequests = Config::get("AUTH_REQUESTS")["GET"];
      $AuthGETForbiddenRequests = Config::get("FORBIDDEN_REQUESTS")["GET"];

      foreach($tables as $tableName => $property) {
        if(!in_array($tableName, $AuthGETRequests) && !in_array($tableName, $AuthGETForbiddenRequests)) {
          $tables[$tableName] = Moedoo::select($tableName);
        }
      }

      Rock::JSON(["tables" => $tables], 200);
    };
  };
