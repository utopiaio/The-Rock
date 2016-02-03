<?php
  require __DIR__ ."/vendor/autoload.php";
  require __DIR__ ."/config.php";
  require __DIR__ ."/Moedoo.php";
  require __DIR__ ."/Rock.php";
  require __DIR__ ."/Util.php";
  require __DIR__ ."/services/REST.php";
  require __DIR__ ."/services/OPTIONS.php";
  require __DIR__ ."/services/all.php";
  require __DIR__ ."/services/auth.php";
  require __DIR__ ."/services/S3.php";

  $dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addRoute("GET", Config::get("ROOT_URL")."/auth", "auth");
    $r->addRoute("POST", Config::get("ROOT_URL")."/auth", "auth");

    $r->addRoute("GET", Config::get("ROOT_URL")."/all", "all");

    $r->addRoute("GET", Config::get("ROOT_URL")."/@S3/{filePath:.+}", "S3");
    $r->addRoute("DELETE", Config::get("ROOT_URL")."/@S3/{filePath:.+}", "S3");
    $r->addRoute("POST", Config::get("ROOT_URL")."/S3", "S3");

    $r->addRoute("OPTIONS", Config::get("ROOT_URL")."/[{path:.+}]", "OPTIONS");

    $r->addRoute("GET", Config::get("ROOT_URL")."/{table}[/{id:\d+}]", "REST");
    $r->addRoute("GET", Config::get("ROOT_URL")."/{table}/{count:count}", "REST");
    $r->addRoute("POST", Config::get("ROOT_URL")."/{table}", "REST");
    $r->addRoute("PUT", Config::get("ROOT_URL")."/{table}/{id:\d+}", "REST");
    $r->addRoute("DELETE", Config::get("ROOT_URL")."/{table}/{id:\d+}", "REST");
  });

  $routeInfo = $dispatcher->dispatch($_SERVER["REQUEST_METHOD"], array_key_exists("REDIRECT_URL", $_SERVER) === true ? $_SERVER["REDIRECT_URL"] : "/");

  if(array_key_exists("REDIRECT_URL", $_SERVER) === false) {
    $_SERVER["REDIRECT_URL"] = "/";
  }

  switch($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
      Rock::halt(404, "`".  $_SERVER["REQUEST_METHOD"] ."` method with URL `". $_SERVER["REDIRECT_URL"] ."` not found");
    break;

    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
      Rock::halt(405, "`".  $_SERVER["REQUEST_METHOD"] ."` method with URL `". $_SERVER["REDIRECT_URL"] ."` not allowed");
    break;

    case FastRoute\Dispatcher::FOUND:
      $db = Moedoo::db(Config::get("DB_HOST"), Config::get("DB_PORT"), Config::get("DB_USER"), Config::get("DB_PASSWORD"), Config::get("DB_NAME"));

      if(array_key_exists("table", $routeInfo[2]) === true) {
        Rock::check($_SERVER["REQUEST_METHOD"], $routeInfo[2]["table"]);
      } else if($routeInfo[1] === "S3") {
        Rock::check($_SERVER["REQUEST_METHOD"], "s3");
      }

      try {
        $RestContainer[$routeInfo[1]]($routeInfo);
        pg_close($db);
      } catch(Exception $e) {
        Rock::halt(400, $e->getMessage());
      }
    break;
  }
