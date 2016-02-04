<?php
  $__REST__["REST"] = function ($routeInfo) {
    $defaultDepth = Config::get("DEFAULT_DEPTH");
    $table = $routeInfo[2]["table"];
    $id = array_key_exists("id", $routeInfo[2]) === true ? $routeInfo[2]["id"] : -1;
    $count = array_key_exists("count", $routeInfo[2]);

    switch($_SERVER["REQUEST_METHOD"]) {
      case "GET":
        if(isset($_GET["q"]) === true && $id === -1) {
          $limit = (isset($_GET["limit"]) === true && preg_match("/^\d+$/", $_GET["limit"])) ? $_GET["limit"] : "ALL";
          Rock::JSON(Moedoo::search($table, $_GET["q"], $limit, $defaultDepth), 200);
        }

        else if($count === true) {
          Rock::JSON([
            "count" => Moedoo::count($table)
          ]);
        }

        else if(isset($_GET["limit"]) === true && preg_match("/^\d+$/", $_GET["limit"]) === 1) {
          $limit = $_GET["limit"];
          $count = 0;
          $depth = $defaultDepth;

          if(isset($_GET["offset"]) === true && preg_match("/^\d+$/", $_GET["offset"]) === 1) {
            $count = $_GET["offset"];
          }

          Rock::JSON(Moedoo::select($table, null, null, $depth, $limit, $count), 200);
        }

        else {
          // selects takes depth by reference, so we'll be passing a copy
          $depth = $defaultDepth;
          $result = $id === -1 ? Moedoo::select($table, null, null, $depth) : Moedoo::select($table, [Config::get("TABLES")[$table]["pk"] => $id], null, $depth);

          if($id === -1) {
            Rock::JSON($result, 200);
          }

          else if(count($result) === 1) {
            Rock::JSON($result[0], 200);
          }

          else {
            Rock::halt(404, "`{$table}` with id `{$id}` does not exist");
          }
        }
      break;

      case "POST":
        $body = Rock::getBody($table);

        switch($table) {
          case "users":
            if(array_key_exists("user_username", $body) === true) {
              $body["user_username"] = strtolower($body["user_username"]);
              $body["user_username"] = preg_replace("/ /", "_", $body["user_username"]);
            }

            if(array_key_exists("user_password", $body) === true && strlen($body["user_password"]) > 3) {
              $body["user_password"] = Rock::hash($body["user_password"]);
            } else {
              Rock::halt(400, "invalid password provided");
            }
          break;
        }

        try {
          $result = Moedoo::insert($table, $body, $defaultDepth);
        } catch(Exception $e) {
          Rock::halt(400, $e->getMessage());
        }

        Rock::JSON($result, 201);
      break;

      case "PUT":
        $body = Rock::getBody($table);

        switch($table) {
          case "users":
            if(array_key_exists("user_username", $body) === true) {
              $body["user_username"] = strtolower($body["user_username"]);
              $body["user_username"] = preg_replace("/ /", "_", $body["user_username"]);
            }

            if(array_key_exists("user_password", $body) === true && strlen($body["user_password"]) > 3) {
              $body["user_password"] = Rock::hash($body["user_password"]);
            } else {
              unset($body["user_password"]);
            }
          break;
        }

        try {
          $result = Moedoo::update($table, $body, $id, $defaultDepth);
        } catch(Exception $e) {
          Rock::halt(400, $e->getMessage());
        }

        Rock::JSON($result, 202);
      break;

      case "DELETE":
        try {
          $result = Moedoo::delete($table, $id);
        } catch(Exception $e) {
          Rock::halt(400, $e->getMessage());
        }

        Rock::JSON($result, 202);
      break;
    }
  };
