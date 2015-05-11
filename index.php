<?php
  require "config.php";
  require "lib/Slim/Slim.php";
  require "lib/Moedoo/Moedoo.php";
  require "Util.php";
  require "Rock.php";

  \Slim\Slim::registerAutoloader();

  $db = Moedoo::db(\Config\DB_HOST, \Config\DB_PORT, \Config\DB_USER, \Config\DB_PASSWORD, \Config\DB_NAME);

  $app = new \Slim\Slim([
    "debug" => \Config\DEBUG,
    "cookies.encrypt" => \Config\COOKIES_ENCRYPT
  ]);

  $app->add(new \Slim\Middleware\SessionCookie([
    "expires" => \Config\COOKIE_LIFETIME,
    "path" => \Config\COOKIE_PATH,
    "secure" => \Config\COOKIE_SECURE,
    "httponly" => \Config\COOKIE_HTTPONLY,
    "name" => \Config\COOKIE_NAME,
    "secret" => \Config\COOKIE_SECRET_KEY
  ]));

  // login
  $app->post("/login", function() use($app) {
    $request = $app->request;
    $body = Util::to_array(json_decode($request->getBody()));

    if(!array_key_exists("username", $body) || !array_key_exists("password", $body)) {
      Util::stop("bad request, check the payload and try again", 400);
    }

    Rock::login($body["username"], $body["password"]);
  });

  // logout
  $app->post("/logout", function() use($app) {
    Rock::logout();
  });

  // generic CRUD mapper
  $app->group("/:table", function() use($app) {
    $app->get("(/:id)", function($table, $id = -1) use($app) {
      Rock::check("GET", $table);

      $id === -1 ? Moedoo::select($table) : Moedoo::select($table, $id);
    })->conditions(["id" => "\d+"]);

    $app->post("", function($table) use($app) {
      Rock::check("POST", $table);

      $request = $app->request;
      $body = Util::to_array(json_decode($request->getBody()));
      Moedoo::insert($table, $body);
    });

    $app->put("/:id", function($table, $id) use($app) {
      Rock::check("PUT", $table);

      $request = $app->request;
      $body = Util::to_array(json_decode($request->getBody()));
      Moedoo::update($table, $body, $id);
    })->conditions(["id" => "\d+"]);

    $app->delete("/:id", function($table, $id) use($app) {
      Rock::check("DELETE", $table);

      Moedoo::delete($table, $id);
    })->conditions(["id" => "\d+"]);
  });

  $app->options("/(:path+)", function() use($app) {
    $response = $app->response;
    $origin = $app->request->headers["Origin"];
    $origin_stripped = preg_replace("/http:\/\/|www\./", "", $origin);

    if(in_array("*", \Config\CORS_WHITE_LIST)  === true) {
      $response->headers->set("Access-Control-Allow-Origin", "*");
      $response->headers->set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE");
      $response->headers->set("Access-Control-Allow-Headers", "accept, content-type");
      $response->headers->set("Access-Control-Allow-Credentials", "true");
      $response->headers->set("Access-Control-Max-Age", "86400");
      $response->setStatus(202);
    }

    else if(in_array($origin_stripped, \Config\CORS_WHITE_LIST) === true) {
      $response->headers->set("Access-Control-Allow-Origin", "{$origin}");
      $response->headers->set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE");
      $response->headers->set("Access-Control-Allow-Headers", "accept, content-type");
      $response->headers->set("Access-Control-Allow-Credentials", "true");
      $response->headers->set("Access-Control-Max-Age", "86400");
      $response->setStatus(202);
    }

    else{
      Util::JSON(["error" => "CORS forbidden, please contact system administrator"], 403);
    }
  });

  $app->notFound(function() {
    Util::JSON(["error" => "requested URL was not found"], 404);
  });

  // executed whenever an error is thrown
  // PS: called when `debug` is set to false
  $app->error(function(Exception $e) use($app) {
    Util::JSON(["error" => "Application Error, if problem persists please contact system administrator"], 500);
  });

  $app->run();
?>
