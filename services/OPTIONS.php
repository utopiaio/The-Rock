<?php
  $RestContainer["OPTIONS"] = function($c) {
    return function($routeInfo) {
      $requestHeaders = Rock::getHeaders();
      $origin = array_key_exists("Origin", $requestHeaders) === true ? $requestHeaders["Origin"] : "*";
      $origin_stripped = preg_replace("/https?:\/\/|www\./", "", $origin);

      if(in_array("*", Config::get("CORS_WHITE_LIST")) === true || in_array($origin_stripped, Config::get("CORS_WHITE_LIST")) === true) {
        header("HTTP/1.1 202 Accepted");
        header("Access-Control-Allow-Origin: {$origin}");
        header("Access-Control-Allow-Methods: ". implode(", ", Config::get("CORS_METHODS")));
        header("Access-Control-Allow-Headers: ". implode(", ", Config::get("CORS_HEADERS")));
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: ". Config::get("CORS_MAX_AGE"));
      }

      else {
        Rock::halt(403, "CORS forbidden, please contact system administrator");
      }
    };
  };
