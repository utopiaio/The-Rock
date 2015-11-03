<?php
  /**
   * The Rock - a micro "framework" built on top of FastRoute and Pimple
   *
   * @author    Moe Szyslak <moe.duffdude@gmail.com>
   * @version   0.1.2
   * @package   Deez Nuts
   */

  date_default_timezone_set("Africa/Addis_Ababa");

  class Config {
    private static $CONFIG = [
      "TABLE_PREFIX"  => "tr001_",
      "HASH" => "sha512",
      "SALT" => "canYouSmellWhatTheRockIsCooking",

      // JWT
      "JWT_HEADER" => "X-Access-Token",
      "JWT_KEY" => "canYouSmellWhatTheRockIsCooking",
      "JWT_ISS" => "The Rock",
      "JWT_IAT" => "now",

      // S3
      "S3_UPLOAD_DIR" => "__S3__",
      "S3_UPLOAD_URL" => "@S3",
      "S3_BASE64" => 0,

      // database
      "DB_HOST" => "localhost",
      "DB_USER" => "moe",
      "DB_PASSWORD" => "\"\"",
      "DB_PORT" => 5432,
      "DB_NAME" => "rock",

      // CORS
      "CORS_WHITE_LIST" => ["*", "rock.io", "foo.com"],
      "CORS_METHODS" => ["GET", "POST", "PUT", "DELETE"],
      "CORS_HEADERS" => ["Accept", "Content-Type", "Content-Range", "Content-Disposition", "X-Access-Token"],
      "CORS_MAX_AGE" => "86400",

      // requests that require authentication
      "AUTH_REQUESTS" => [
        "GET"     => [],
        "POST"    => [],
        "PUT"     => ["about", "social"],
        "DELETE"  => []
      ],

      // request that are NOT allowed
      "FORBIDDEN_REQUESTS" => [
        "GET"     => [],
        "POST"    => ["about"],
        "PUT"     => [],
        "DELETE"  => ["about"]
      ],

      // Moedoo will construct queries based on this configurations
      "TABLES" => [
        "about"       => [
          "pk"        => "id",
          "columns"   => ["id", "data", "creator", "geom", "social"],
          "returning" => ["id", "data", "creator", "geom", "social"],
          "geometry"  => ["geom"],
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
          "columns"   => ["id", "name", "size", "type", "url", "\"thumbnailUrl\"", "\"deleteUrl\"", "\"deleteType\"", "base64"],
          "returning" => ["id", "name", "size", "type", "url", "\"thumbnailUrl\"", "\"deleteUrl\"", "\"deleteType\"", "base64"],
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
          "[int]"     => ["tags"],
          "search"    => ["story"],
          "fk"        => [
            "by" => ["table" => "users", "references" => "user_id"],
            "[tags]" => ["table" => "tags", "references" => "id"]
          ]
        ],
        "tags"        => [
          "pk"        => "id",
          "columns"   => ["id", "tag"],
          "returning" => ["id", "tag"],
          "int"       => ["id"],
          "search"    => ["tag"]
        ],
        "users"       => [
          "pk"        => "user_id",
          "columns"   => ["user_id", "user_full_name", "user_username", "user_password", "user_type", "user_status", "user_friends"],
          "returning" => ["user_id", "user_full_name", "user_username", "user_type", "user_status", "user_friends"],
          "int"       => ["user_id"],
          "[int]"     => ["user_friends"],
          "bool"      => ["user_status"],
          "search"    => ["user_full_name", "user_username", "user_type"],
          "fk"       => [
            "[user_friends]" => ["table" => "users", "references" => "user_id"]
          ]
        ]
      ]
    ];

    /**
     * returns configuration
     *
     * EXCEPTION CODES
     * 1: unknown configuration key requested
     *
     * @param string $key
     */
    public static function get($key = "TheRock") {
      if(array_key_exists($key, Config::$CONFIG) === false) {
        throw new Exception("undefined key `{$key}`", 1);
      }

      return Config::$CONFIG[$key];
    }
  }
?>
