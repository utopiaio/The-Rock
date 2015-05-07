<?php
  require 'Slim/Slim.php';

  date_default_timezone_set('Africa/Addis_Ababa');
  \Slim\Slim::registerAutoloader();

  $app = new \Slim\Slim([
    'debug' => true,
    'cookies.encrypt' => true,
    'cookies.lifetime' => '7 days',
    'cookies.domain' => 'slim.io',
    'cookies.secure' => false,
    'cookies.httponly' => true,
    'cookies.secret_key' => 'THE_ROCK'
  ]);

  $app->add(new \Slim\Middleware\SessionCookie([
    'expires' => '1 hour',
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'name' => 'THE_ROCK'
  ]));

  $app->get('/', function() use($app) {
    echo "i am root";
  });

  $app->run();
?>
