<?php
  /**
   * The Rock - a micro "framework" with a budget
   *
   * @author    Utopia፲ <utopiaio@yahoo.com>
   * @version   1.0.0
   */

  date_default_timezone_set('Africa/Addis_Ababa');

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
      'ROOT_URL' => '',

      // encryption
      'HASH' => 'sha512',
      'SALT' => 'canYouSmellWhatTheRockIsCooking',

      // JWT
      'JWT_HEADER' => 'Authorization',
      'JWT_KEY' => 'canYouSmellWhatTheRockIsCooking',
      'JWT_ISS' => 'The Rock',
      'JWT_IAT' => 'now',
      'JWT_ALGORITHM' => 'HS256',

      // S3
      'S3_UPLOAD_DIR' => __DIR__ . '/__S3__', // relative to the root directory
      'S3_UPLOAD_URL' => '@S3', // appended to the host, http://rock.io/@S3
      'S3_MAX_UPLOAD_SIZE' => 102400, // in bytes (that's ~100KB)
      'S3_FILE_NAME_SIZE' => 12,
      'S3_ALLOWED_MIME' => ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/rtf', 'application/epub+zip', 'text/plain', 'application/json', 'application/octet-stream', 'application/zip'],

      // SQLite
      'DB_FILE' => __DIR__ . '/db/rock.sqlite',
      'DB_ID_LENGTH' => 8, // 62^8, which is aboot 210 Trillion
      'DB_BUSY_TIMEOUT' => 30000, // 30 seconds
      'DEFAULT_DEPTH' => 1,
      'RELATIONSHIP_KEY' => 'relationships',

      // CORS
      'CORS_WHITE_LIST' => ['*', 'rock.io', 'foo.com'],
      'CORS_METHODS' => ['GET', 'POST', 'PATCH', 'DELETE'],
      'CORS_HEADERS' => ['Accept', 'Content-Type', 'Content-Range', 'Content-Disposition', 'Authorization'],
      'CORS_MAX_AGE' => '86400',

      // requests that require authentication + tailored permission
      'AUTH_REQUESTS' => [
        'GET'     => ['user'],
        'POST'    => [],
        'PATCH'   => [],
        'DELETE'  => []
      ],

      // request that are NOT allowed
      'FORBIDDEN_REQUESTS' => [
        'GET'     => [],
        'POST'    => [],
        'PATCH'   => [],
        'DELETE'  => []
      ],

      // Moedoo will construct queries based on this configurations
      'TABLES' => [
        'rock'        => [
          'pk'        => 'id',
          'columns'   => ['id', 'col_integer', 'col_float', 'col_json', 'col_bool', 'col_string', 'col_fk', 'col_fk_m'],
          'returning' => ['id', 'col_integer', 'col_float', 'col_json', 'col_bool', 'col_string', 'col_fk', 'col_fk_m'],
          'bool'      => ['col_bool'],
          '[string]'  => ['col_fk_m'],
          'JSON'      => ['col_json'],
          'search'    => ['col_string'],
          'fk'        => [
            'col_fk'      => ['table' => 's3', 'references' => 'id'],
            '[col_fk_m]'  => ['table' => 'tag', 'references' => 'id']
          ]
        ],
        's3'          => [
          'pk'        => 'id',
          'columns'   => ['id', 'name', 'size', 'type'],
          'returning' => ['id', 'name', 'size', 'type'],
        ],
        'tag'         => [
          'pk'        => 'id',
          'columns'   => ['id', 'tag'],
          'returning' => ['id', 'tag'],
          'search'    => ['tag'],
          'fk'        => [
            '{rock}'  => ['table' => 'rock', 'referenced_by' => 'id', 'referencing_column' => 'col_fk_m']
          ]
        ],
        'user'        => [
          'pk'        => 'user_id',
          'columns'   => ['user_id', 'user_full_name', 'user_username', 'user_password', 'user_status', 'user_group'],
          'returning' => ['user_id', 'user_full_name', 'user_username', 'user_status', 'user_group'],
          'bool'      => ['user_status'],
          'search'    => ['user_full_name', 'user_username'],
          'fk'        => [
            'user_group' => ['table' => 'user_group', 'references' => 'user_group_id']
          ]
        ],
        'user_group' => [
          'pk'        => 'user_group_id',
          'columns'   => [
            'user_group_id',
            'user_group_name',
            'user_group_create_rock', 'user_group_read_rock', 'user_group_update_rock', 'user_group_delete_rock',
            'user_group_create_tag', 'user_group_read_tag', 'user_group_update_tag', 'user_group_delete_tag',
            'user_group_create_s3', 'user_group_read_s3', 'user_group_update_s3', 'user_group_delete_s3',
            'user_group_create_user', 'user_group_read_user', 'user_group_update_user', 'user_group_delete_user',
            'user_group_create_user_group', 'user_group_read_user_group', 'user_group_update_user_group', 'user_group_delete_user_group',
            'user_group_status'
          ],
          'returning' => [
            'user_group_id',
            'user_group_name',
            'user_group_create_rock', 'user_group_read_rock', 'user_group_update_rock', 'user_group_delete_rock',
            'user_group_create_tag', 'user_group_read_tag', 'user_group_update_tag', 'user_group_delete_tag',
            'user_group_create_s3', 'user_group_read_s3', 'user_group_update_s3', 'user_group_delete_s3',
            'user_group_create_user', 'user_group_read_user', 'user_group_update_user', 'user_group_delete_user',
            'user_group_create_user_group', 'user_group_read_user_group', 'user_group_update_user_group', 'user_group_delete_user_group',
            'user_group_status'
          ],
          'bool'      => [
            'user_group_create_rock', 'user_group_read_rock', 'user_group_update_rock', 'user_group_delete_rock',
            'user_group_create_tag', 'user_group_read_tag', 'user_group_update_tag', 'user_group_delete_tag',
            'user_group_create_s3', 'user_group_read_s3', 'user_group_update_s3', 'user_group_delete_s3',
            'user_group_create_user', 'user_group_read_user', 'user_group_update_user', 'user_group_delete_user',
            'user_group_create_user_group', 'user_group_read_user_group', 'user_group_update_user_group', 'user_group_delete_user_group',
            'user_group_status'],
          'search'    => ['user_group_name'],
          'fk'        => [
            '{user}'  => ['table' => 'user', 'referenced_by' => 'user_group_id', 'referencing_column' => 'user_group']
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
    public static function get($key = 'TheRock') {
      if (array_key_exists($key, Config::$CONFIG) === false) {
        throw new Exception("undefined key `{$key}`", 1);
      }

      return Config::$CONFIG[$key];
    }
  }
