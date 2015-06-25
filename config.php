<?php
  /**
   * The Rock - a "framework" built on top of Slim
   *
   * @author    Moe Szyslak <moe.duffdude@gmail.com>
   * @version   0.0.1
   * @package   Deez Nuts
   */

  namespace CONFIG;

  date_default_timezone_set("Africa/Addis_Ababa");

  const TABLE_PREFIX  = "tr001_";
  const HASH = "sha512";
  const SALT = "canYouSmellWhatTheRockIsCooking";

  // JWT
  const JWT_HEADER = "X-Access-Token";
  const JWT_KEY = "canYouSmellWhatTheRockIsCooking";
  const JWT_ISS = "The Rock";
  const JWT_IAT = "now";

  // Slim + The Rock
  const DEBUG = false;
  const ROCK_DEBUG = true;

  // S3
  const S3_UPLOAD_DIR = "__S3__";
  const S3_UPLOAD_URL = "@S3";

  // database
  const DB_HOST = "localhost";
  const DB_USER = "moe";
  const DB_PASSWORD = "\"\"";
  const DB_PORT = 5432;
  const DB_NAME = "the_rock";

  // CORS
  const CORS_WHITE_LIST = ["*", "rock.io", "foo.com"];
  const CORS_METHODS = ["GET", "POST", "PUT", "DELETE"];
  const CORS_HEADERS = ["Accept", "Content-Type", "Content-Range", "Content-Disposition", JWT_HEADER];
  const CORS_MAX_AGE = "86400";

  // requests that require authentication
  const AUTH_REQUESTS = [
    "GET"     => [],
    "POST"    => [],
    "PUT"     => ["about", "social"],
    "DELETE"  => []
  ];

  // request that are NOT allowed
  const FORBIDDEN_REQUESTS = [
    "GET"     => [],
    "POST"    => ["about"],
    "PUT"     => [],
    "DELETE"  => ["about"]
  ];

  // Moedoo will construct queries based on this configurations
  const TABLES = [
    "about"       => [
      "pk"        => "id",
      "columns"   => ["id", "data", "creator", "social"],
      "returning" => ["id", "data", "creator", "social"],
      "JSON"      => ["data"],
      "int"       => ["id", "creator", "social"],
      "search"    => ["data"],
      "fk"        => [
        "creator" => ["table" => "users", "references" => "user_id"],
        "social"  => ["table" => "social", "references" => "id"]
      ]
    ],
    "s3"          => [
      "pk"        => "id",
      "columns"   => ["id", "name", "size", "type", "url", "\"thumbnailUrl\"", "\"deleteUrl\"", "\"deleteType\""],
      "returning" => ["id", "name", "size", "type", "url", "\"thumbnailUrl\"", "\"deleteUrl\"", "\"deleteType\""],
      "int"       => ["id", "size"],
    ],
    "social"      => [
      "pk"        => "id",
      "columns"   => ["id", "data", "users"],
      "returning" => ["id", "data", "users"],
      "JSON"      => ["data"],
      "int"       => ["id", "users"],
      "search"    => ["data"],
      "fk"        => [
        "users" => ["table" => "users", "references" => "user_id"]
      ]
    ],
    "story"       => [
      "pk"        => "id",
      "columns"   => ["id", "story", "by", "tags"],
      "returning" => ["id", "story", "by", "tags"],
      "int"       => ["id", "by"],
      "intArray"  => ["tags"],
      "search"    => ["story"],
      "fk"        => [
        "by" => ["table" => "users", "references" => "user_id"],
      ],
      "map" => [
        "tags" => ["table" => "tags", "references" => "id"]
      ]
    ],
    "tags"        => [
      "pk"        => "id",
      "columns"   => ["id", "tag"],
      "returning" => ["id", "tag"],
      "int"       => ["id"],
      "search"    => ["tag"],
      "fk"        => []
    ],
    "users"       => [
      "pk"        => "user_id",
      "columns"   => ["user_id", "user_full_name", "user_username", "user_password", "user_type", "user_status", "user_friends"],
      "returning" => ["user_id", "user_full_name", "user_username", "user_type", "user_status", "user_friends"],
      "int"       => ["user_id"],
      "intArray"  => ["user_friends"],
      "bool"      => ["user_status"],
      "search"    => ["user_full_name", "user_username", "user_type"],
      "map"       => [
        "user_friends" => ["table" => "users", "references" => "user_id"]
      ]
    ]
  ];
?>
