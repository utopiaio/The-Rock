<?php
  /**
   * The Rock - a micro "framework" built on top of FastRoute and Pimple
   *
   * @author    Moe Szyslak <moe.duffdude@gmail.com>
   * @version   0.1.3
   * @package   Deez Nuts
   */

  date_default_timezone_set("Africa/Addis_Ababa");

  class Config {
    private static $CONFIG = [
      /**
       * when the API root directory is accessed from a non root directory
       * set `ROOT_URL` to the root directory for `index.php` file
       * if this is accessed at the root, leave empty
       *
       * example:
       * http://app.io/path_to_api/api/
       * `ROOT_URL` will be `/path_to_api`
       */
      "ROOT_URL" => "",

      "TABLE_PREFIX"  => "tr001_",
      "HASH" => "sha512",
      "SALT" => "canYouSmellWhatTheRockIsCooking",

      // JWT
      "JWT_HEADER" => "X-Access-Token",
      "JWT_KEY" => "canYouSmellWhatTheRockIsCooking",
      "JWT_ISS" => "The Rock",
      "JWT_IAT" => "now",

      // S3
      "S3_UPLOAD_DIR" => "__S3__", // relative to the root directory
      "S3_UPLOAD_URL" => "@S3", // appended to the host, http://rock.io/@S3
      "S3_BASE64" => 0,
      "S3_FILE_NAME_SIZE" => 6,
      "S3_ALLOWED_MIME" => ["image/jpeg", "image/png", "image/gif", "application/pdf", "text/rtf", "application/epub+zip", "text/plain", "application/octet-stream", "application/zip"],

      // database
      "DB_HOST" => "localhost",
      "DB_USER" => "moe",
      "DB_PASSWORD" => "\"\"",
      "DB_PORT" => 5432,
      "DB_NAME" => "rock",
      "DEFAULT_DEPTH" => 1,

      // CORS
      "CORS_WHITE_LIST" => ["*", "rock.io", "foo.com"],
      "CORS_METHODS" => ["GET", "POST", "PUT", "DELETE"],
      "CORS_HEADERS" => ["Accept", "Content-Type", "Content-Range", "Content-Disposition", "X-Access-Token"],
      "CORS_MAX_AGE" => "86400",

      // requests that require authentication + tailored permission
      "AUTH_REQUESTS" => [
        "GET"     => ["story"],
        "POST"    => ["story"],
        "PUT"     => ["about", "social", "story"],
        "DELETE"  => ["story"]
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
            "creator" => ["table" => "user", "references" => "user_id"],
            "social"  => ["table" => "social", "references" => "id"]
          ]
        ],
        "s3"          => [
          "pk"        => "id",
          "columns"   => ["id", "name", "size", "type", "url"],
          "returning" => ["id", "name", "size", "type", "url"],
          "int"       => ["id", "size"],
        ],
        "social"      => [
          "pk"        => "id",
          "columns"   => ["id", "data", "\"user\""],
          "returning" => ["id", "data", "\"user\""],
          "JSON"      => ["data"],
          "int"       => ["id", "\"user\""],
          "search"    => ["data"],
          "fk"        => [
            "user" => ["table" => "user", "references" => "user_id"]
          ]
        ],
        "story"       => [
          "pk"        => "id",
          "columns"   => ["id", "story", "by", "tag"],
          "returning" => ["id", "story", "by", "tag"],
          "int"       => ["id", "by"],
          "[int]"     => ["tag"],
          "search"    => ["story"],
          "fk"        => [
            "by" => ["table" => "user", "references" => "user_id"],
            "[tag]" => ["table" => "tag", "references" => "id"]
          ]
        ],
        "tag"        => [
          "pk"        => "id",
          "columns"   => ["id", "tag"],
          "returning" => ["id", "tag"],
          "int"       => ["id"],
          "search"    => ["tag"]
        ],
        "user"       => [
          "pk"        => "user_id",
          "columns"   => ["user_id", "user_full_name", "user_username", "user_password", "user_status", "user_group", "user_friend"],
          "returning" => ["user_id", "user_full_name", "user_username", "user_status", "user_friend", "user_group"],
          "int"       => ["user_id", "user_group"],
          "[int]"     => ["user_friend"],
          "bool"      => ["user_status"],
          "search"    => ["user_full_name", "user_username"],
          "fk"       => [
            "user_group" => ["table" => "user_group", "references" => "user_group_id"],
            "[user_friend]" => ["table" => "user", "references" => "user_id"]
          ]
        ],
        "user_group" => [
          "pk"        => "user_group_id",
          "columns"   => ["user_group_id", "user_group_name", "user_group_has_permission_create_story", "user_group_has_permission_read_story", "user_group_has_permission_update_story", "user_group_has_permission_delete_story", "user_group_status"],
          "returning" => ["user_group_id", "user_group_name", "user_group_has_permission_create_story", "user_group_has_permission_read_story", "user_group_has_permission_update_story", "user_group_has_permission_delete_story", "user_group_status"],
          "int"       => ["user_group_id"],
          "bool"      => ["user_group_has_permission_create_story", "user_group_has_permission_read_story", "user_group_has_permission_update_story", "user_group_has_permission_delete_story", "user_group_status"],
          "search"    => ["user_group_name"]
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
