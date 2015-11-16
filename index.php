<?php
  require "lib/fast-route/src/bootstrap.php";
  require "lib/pimple/Container.php";
  require "lib/pimple/ServiceProviderInterface.php";
  require "lib/jwt/Authentication/JWT.php";
  require "lib/jwt/Exceptions/BeforeValidException.php";
  require "lib/jwt/Exceptions/ExpiredException.php";
  require "lib/jwt/Exceptions/SignatureInvalidException.php";
  require "lib/Blueimp/UploadHandler.php";
  require "lib/Blueimp/TheRockUploadHandler.php";
  require "Moedoo.php";
  require "Rock.php";
  require "Util.php";
  require "RestContainer.php";
  require "config.php";

  $dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addRoute("POST", Config::get("ROOT_URL")."/authenticate", "authenticate");
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
      Moedoo::db(Config::get("DB_HOST"), Config::get("DB_PORT"), Config::get("DB_USER"), Config::get("DB_PASSWORD"), Config::get("DB_NAME"));

      if(array_key_exists("table", $routeInfo[2]) === true) {
        Rock::check($_SERVER["REQUEST_METHOD"], $routeInfo[2]["table"]);
      }

      $RestContainer[$routeInfo[1]]($routeInfo);
    break;
  }
?>
