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
  const DEBUG = true;
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

  // requests that require authenticated session
  const RESTRICTED_REQUESTS = [
    "GET"     => ["users", "test"],
    "POST"    => ["media", "users"],
    "PUT"     => ["about", "background", "contact", "logo", "media", "social", "users"],
    "DELETE"  => ["media", "users"]
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
      "int"       => ["id"]
    ],
    "background"  => [
      "pk"        => "id",
      "columns"   => ["id", "data"],
      "RETURNING" => ["id", "data"],
      "JSON"      => ["data"],
      "int"       => ["id"]
    ],
    "contact"     => [
      "pk"        => "id",
      "columns"   => ["id", "data"],
      "RETURNING" => ["id", "data"],
      "JSON"      => ["data"],
      "int"       => ["id"]
    ],
    "logo"        => [
      "pk"        => "id",
      "columns"   => ["id", "data"],
      "RETURNING" => ["id", "data"],
      "JSON"      => ["data"],
      "int"       => ["id"]
    ],
    "test"        => [
      "pk"        => "id",
      "columns"   => ["id", "name", "json"],
      "RETURNING" => ["id", "name", "json"],
      "JSON"      => ["json"],
      "int"       => ["id"]
    ],
    "media"       => [
      "pk"        => "id",
      "columns"   => ["id", "name", "size", "type", "url", "\"thumbnailUrl\"", "\"deleteUrl\"", "\"deleteType\""],
      "RETURNING" => ["id", "name", "size", "type", "url", "\"thumbnailUrl\"", "\"deleteUrl\"", "\"deleteType\""],
      "JSON"      => [],
      "int"       => ["id", "size"]
    ],
    "social"      => [
      "pk"        => "id",
      "columns"   => ["id", "data"],
      "RETURNING" => ["id", "data"],
      "JSON"      => ["data"],
      "int"       => ["id"]
    ],
    "users"       => [
      "pk"        => "user_id",
      "columns"   => ["user_id", "user_full_name", "user_username", "user_password", "user_type", "user_status"],
      "RETURNING" => ["user_id", "user_full_name", "user_username", "user_type", "user_status"],
      "JSON"      => [],
      "int"       => ["user_id"]
    ]
  ];
?>
