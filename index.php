<?php
  require "lib/Slim/Slim.php";
  require "config.php";
  require "Moedoo.php";
  require "Util.php";
  require "Rock.php";

  \Slim\Slim::registerAutoloader();

  $db = Moedoo::db(\Config\DB_HOST, \Config\DB_PORT, \Config\DB_USER, \Config\DB_PASSWORD, \Config\DB_NAME);

  $app = new \Slim\Slim([
    "debug" => \Config\DEBUG,
    "rock.debug" => \Config\ROCK_DEBUG,
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
    $body = Util::to_array($request->getBody());

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

      // search query requested on a table
      if(isset($_GET["q"]) === true && $id === -1) {
        Moedoo::search($table, $_GET["q"]);
      }

      // select query requested
      else {
        $id === -1 ? Moedoo::select($table) : Moedoo::select($table, $id);
      }
    })->conditions(["id" => "\d+"]);

    $app->post("", function($table) use($app) {
      Rock::check("POST", $table);

      $request = $app->request;
      $body = Util::to_array($request->getBody());
      Moedoo::insert($table, $body);
    });

    $app->put("/:id", function($table, $id) use($app) {
      Rock::check("PUT", $table);

      $request = $app->request;
      $body = Util::to_array($request->getBody());
      Moedoo::update($table, $body, $id);
    })->conditions(["id" => "\d+"]);

    $app->delete("/:id", function($table, $id) use($app) {
      Rock::check("DELETE", $table);

      Moedoo::delete($table, $id);
    })->conditions(["id" => "\d+"]);
  });

  // this maps ANY OPTIONS request sent and checks the origin against
  // `config` and returns the appropriate header
  $app->options("/(:path+)", function() use($app) {
    $response = $app->response;
    $origin = $app->request->headers["Origin"];
    $origin_stripped = preg_replace("/https?:\/\/|www\./", "", $origin);

    if(in_array("*", \Config\CORS_WHITE_LIST)  === true) {
      $response->headers->set("Access-Control-Allow-Origin", "*");
      $response->headers->set("Access-Control-Allow-Methods", implode(", ", \Config\CORS_METHODS));
      $response->headers->set("Access-Control-Allow-Headers", implode(", ", \Config\CORS_HEADERS));
      $response->headers->set("Access-Control-Allow-Credentials", "true");
      $response->headers->set("Access-Control-Max-Age", \Config\CORS_MAX_AGE);
      $response->setStatus(202);
    }

    else if(in_array($origin_stripped, \Config\CORS_WHITE_LIST) === true) {
      $response->headers->set("Access-Control-Allow-Origin", "{$origin}");
      $response->headers->set("Access-Control-Allow-Methods", implode(", ", \Config\CORS_METHODS));
      $response->headers->set("Access-Control-Allow-Headers", implode(", ", \Config\CORS_HEADERS));
      $response->headers->set("Access-Control-Allow-Credentials", "true");
      $response->headers->set("Access-Control-Max-Age", \Config\CORS_MAX_AGE);
      $response->setStatus(202);
    }

    else {
      Util::JSON(["error" => "CORS forbidden, please contact system administrator"], 403);
    }
  });

  $app->notFound(function() {
    Util::JSON(["error" => "requested URL was not found"], 404);
  });

  // executed whenever an error is thrown
  // PS: called when `debug` is set to false
  $app->error(function(Exception $e) use($app) {
    Util::JSON(["error" => $app->config('rock.debug') === true ? $e->getMessage() : "Application Error, if problem persists please contact system administrator."], 500);
  });

  $app->run();
?>
