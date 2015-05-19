<?php
  /**
   * The Rock - a "framework" built on top of Slim
   *
   * @author    Moe Szyslak <moe.duffdude@gmail.com>
   * @version   0.0.1
   * @package   The Rock
   */

  namespace Config;

  date_default_timezone_set("Africa/Addis_Ababa");

  const TABLE_PREFIX  = "tr001_";
  const HASH = "sha512";
  const SALT = "canYouSmellWhatTheRockIsCooking";

  // Slim
  const DEBUG = false;
  const ROCK_DEBUG = true;
  const COOKIES_ENCRYPT = true;
  const COOKIE_LIFETIME = "1 week";
  const COOKIE_PATH = "/";
  const COOKIE_SECURE = false;
  const COOKIE_HTTPONLY = true;
  const COOKIE_SECRET_KEY = "THE_ROCK";
  const COOKIE_NAME = "THE_ROCK";

  // database connection string
  const DB_HOST = "localhost";
  const DB_USER = "moe";
  const DB_PASSWORD = "\"\"";
  const DB_PORT = 5432;
  const DB_NAME = "the_rock";

  // white-list for CORS
  const CORS_WHITE_LIST = ["*", "rock.io", "foo.com"];
  const CORS_METHODS = ["GET", "POST", "PUT", "DELETE"];
  const CORS_HEADERS = ["accept", "content-type"];
  const CORS_MAX_AGE = "86400";

  // requests that require authenticated session
  const RESTRICTED_REQUESTS = [
    "GET"     => ["users"],
    "POST"    => ["users"],
    "PUT"     => ["about", "background", "contact", "logo", "social", "users"],
    "DELETE"  => ["users"]
  ];

  // request that are NOT allowed --- period
  const FORBIDDEN_REQUESTS = [
    "GET"     => [],
    "POST"    => ["about", "background", "contact", "logo"],
    "PUT"     => [],
    "DELETE"  => ["about", "background", "contact"]
  ];

  // Moedoo will construct queries based on this configurations
  const TABLES = [
    "about"       => [
      "pk"        => "id",
      "columns"   => ["id", "data"],
      "RETURNING" => ["id", "data"],
      "JSON"      => ["data"],
      "int"       => ["id"],
      "float"     => [],
      "double"    => [],
      "bool"      => [],
      "search"    => ["data"]
    ],
    "background"  => [
      "pk"        => "id",
      "columns"   => ["id", "data"],
      "RETURNING" => ["id", "data"],
      "JSON"      => ["data"],
      "int"       => ["id"],
      "float"     => [],
      "double"    => [],
      "bool"      => [],
      "search"    => ["data"]
    ],
    "contact"     => [
      "pk"        => "id",
      "columns"   => ["id", "data"],
      "RETURNING" => ["id", "data"],
      "JSON"      => ["data"],
      "int"       => ["id"],
      "float"     => [],
      "double"    => [],
      "bool"      => [],
      "search"    => ["data"]
    ],
    "logo"        => [
      "pk"        => "id",
      "columns"   => ["id", "data"],
      "RETURNING" => ["id", "data"],
      "JSON"      => ["data"],
      "int"       => ["id"],
      "float"     => [],
      "double"    => [],
      "bool"      => [],
      "search"    => ["data"]
    ],
    // "media"       => [
    //   "pk"        => "id",
    //   "columns"   => ["id", "name", "size", "type", "url", "\"thumbnailUrl\"", "\"deleteUrl\"", "\"deleteType\""],
    //   "RETURNING" => ["id", "name", "size", "type", "url", "\"thumbnailUrl\"", "\"deleteUrl\"", "\"deleteType\""],
    //   "JSON"      => [],
    //   "int"       => ["id", "size"],
    //   "bool"      => []
    // ],
    "social"      => [
      "pk"        => "id",
      "columns"   => ["id", "data"],
      "RETURNING" => ["id", "data"],
      "JSON"      => ["data"],
      "int"       => ["id"],
      "float"     => [],
      "double"    => [],
      "bool"      => [],
      "search"    => ["data"]
    ],
    "users"       => [
      "pk"        => "user_id",
      "columns"   => ["user_id", "user_full_name", "user_username", "user_password", "user_type", "user_status"],
      "RETURNING" => ["user_id", "user_full_name", "user_username", "user_type", "user_status"],
      "JSON"      => [],
      "int"       => ["user_id"],
      "float"     => [],
      "double"    => [],
      "bool"      => ["user_status"],
      "search"    => ["user_full_name", "user_username", "user_type"]
    ]
  ];
?>
