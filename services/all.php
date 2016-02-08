<?php
  /**
   * single request that returns ALL non-Auth / forbidden tables
   * api.io/all
   */
  $__REST__["all"] = function($routeInfo) {
    $tables = Config::get("TABLES");
    unset($tables["user"]);
    unset($tables["user_group"]);
    unset($tables["s3"]);
    $authGETRequests = Config::get("AUTH_REQUESTS")["GET"];
    $authGETForbiddenRequests = Config::get("FORBIDDEN_REQUESTS")["GET"];
    $public = [];

    foreach($tables as $tableName => $property) {
      if(!in_array($tableName, $authGETRequests) && !in_array($tableName, $authGETForbiddenRequests)) {
        $public[$tableName] = Moedoo::select($tableName);
      }
    }

    Rock::JSON(["tables" => $public], 200);
  };
