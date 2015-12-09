<?php
  $RestContainer["authenticate"] = function($c) {
    return function($routeInfo) {
      $body = Rock::getBody();

      if(!array_key_exists("username", $body) || !array_key_exists("password", $body)) {
        Rock::halt(400);
      }

      Rock::authenticate($body["username"], $body["password"]);
    };
  };
