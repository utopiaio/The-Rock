<?php
  class Rock {
    /**
     * checks jwt and authenticates or halts execution
     *
     * @return array - user info from db
     */
    public static function authenticated($method, $table) {
      $requestHeaders = Rock::getHeaders();

      if (array_key_exists(Config::get('JWT_HEADER'), $requestHeaders) === true) {
        try {
          $decoded = (array)Firebase\JWT\JWT::decode($requestHeaders[Config::get('JWT_HEADER')], Config::get('JWT_KEY'), [Config::get('JWT_ALGORITHM')]);
        } catch (Exception $e) {
          Rock::halt(401, 'invalid authorization token');
        }

        $depth = 1;
        $result = Moedoo::select('user', [Config::get('TABLES')['user']['pk'] => $decoded['id']], null, $depth);

        if (count($result) === 1) {
          $user = $result[0];
          $permissionMap = ['GET' => 'read', 'POST' => 'create', 'PATCH' => 'update', 'DELETE' => 'delete'];

          /**
           * authentication pseudo logic steps
           *
           * 1. check user status
           * 2. check group status
           * 3. check tailored permission
           */

          // 1
          if ($user['user_status'] === false) {
            Rock::halt(401, 'account has been suspended');
          }

          // 2
          elseif (is_null($user['user_group']) === true) {
            Rock::halt(401, 'account permission set can not be identified');
          }

          // 2
          elseif ($user['user_group']['user_group_status'] === false) {
            Rock::halt(401, "user group `{$user["user_group"]["user_group_name"]}` has been suspended");
          }

          // 3
          elseif (array_key_exists("user_group_has_permission_{$permissionMap[$method]}_{$table}", $user['user_group']) === true) {
            if ($user['user_group']["user_group_has_permission_{$permissionMap[$method]}_{$table}"] === true) {
              // all permission checks are a go, proceed
              // any post-pre authentication logic go here
            }

            else {
              Rock::halt(401, "account doesn't have `{$permissionMap[$method]}` permission on this table");
            }
          }

          else {
            Rock::halt(401, "account doesn't have `{$permissionMap[$method]}` permission on this table");
          }
        }

        else {
          Rock::halt(401, 'token no longer valid');
        }
      }

      elseif (in_array($table, Config::get('AUTH_REQUESTS')[$method]) === true) {
        Rock::halt(401, 'missing authentication header `'. Config::get('JWT_HEADER') .'`');
      }
    }



    /**
     * checks the request has a valid token + user is active
     * used for non CURD table mapping
     * else returns false
     *
     * @return A.Array | false
     */
    public static function hasValidToken() {
      $requestHeaders = Rock::getHeaders();

      if (array_key_exists(Config::get('JWT_HEADER'), $requestHeaders) === true) {
        try {
          $decoded = (array)Firebase\JWT\JWT::decode($requestHeaders[Config::get('JWT_HEADER')], Config::get('JWT_KEY'), [Config::get('JWT_ALGORITHM')]);
        } catch (Exception $e) {
          return false;
        }

        $depth = 1;
        $result = Moedoo::select('user', [Config::get('TABLES')['user']['pk'] => $decoded['id']], null, $depth);

        if (count($result) === 1) {
          $user = $result[0];

          // user suspended
          if ($user['user_status'] === false) {
            return false;
          }

          // user doesn't belong to a user-group
          elseif (is_null($user['user_group']) === true) {
            return false;
          }

          // user-group has been suspended
          elseif ($user['user_group']['user_group_status'] === false) {
            return false;
          }

          return $user;
        } else {
          return false;
        }
      } else {
        return false;
      }
    }



    /**
     * has permission
     * @param $user A.Array
     * @param $permission String
     * @return Boolean
     */
    public static function hasPermission($user, $permission) {
      if (array_key_exists($permission, $user['user_group']) === true) {
        if ($user['user_group'][$permission] === true) return true;
        else return false;
      }

      else return false;
    }



    /**
     * given username and password info
     * it'll return authenticated user info along with the jwt
     *
     * @param string $username
     * @param string $password - raw password
     */
    public static function authenticate($username, $password) {
      $username = strtolower($username);
      $username = preg_replace('/ /', '_', $username);
      $password = Rock::hash($password);
      $result = Moedoo::select('user', ['user_username' => $username, 'user_password' => $password]);

      if (count($result) === 1) {
        $user = $result[0];

        // user account has been suspended
        if ($user['user_status'] === false) {
          Rock::halt(401, 'account has been suspended');
        }

        elseif (is_null($user['user_group']) === true) {
          Rock::halt(401, 'account permission set can not be identified');
        }

        elseif ($user['user_group']['user_group_status'] === false) {
          Rock::halt(401, "user group `{$user["user_group"]["user_group_name"]}` has been suspended");
        }

        // all good, proceeding with authentication...
        else {
          $token = [
            'iss' => Config::get('JWT_ISS'),
            'iat' => strtotime(Config::get('JWT_IAT')),
            'id' => $user['user_id']
          ];

          // TODO
          // make a fingerprint so that the token stays locked-down
          $jwt = Firebase\JWT\JWT::encode($token, Config::get('JWT_KEY'), Config::get('JWT_ALGORITHM'));
          Rock::JSON(['jwt' => $jwt, 'user' => $user], 202);
        }
      }

      else {
        Rock::halt(401, 'wrong username and/or password');
      }
    }



    /**
     * runs security check on CRUD mapping functions
     *
     * 1: checks weather or not table exists in `config` file
     * 2: checks if $method + $table is restricted calls authentication
     * 3: checks if $method + $table is forbidden execution is stopped
     *
     * @param string $method
     * @param string $table
     * @param string $role
     */
    public static function check($method, $table) {
      if (array_key_exists($table, Config::get('TABLES')) === false) {
        Rock::halt(404, "requested resource `{$table}` does not exist");
      }

      if (in_array($table, Config::get('FORBIDDEN_REQUESTS')[$method]) === true) {
        Rock::halt(403, "`{$method}` method on table `{$table}` is forbidden");
      }

      if (in_array($table, Config::get('AUTH_REQUESTS')[$method]) === true) {
        // this is where the tailored permission check is applied...
        Rock::authenticated($method, $table);
      }
    }



    /**
     * returns body after validating payload
     *
     * @param string $table - table name to check validation against
     * @return associative array representation of the passed body
     */
    public static function getBody($table = null) {
      $streamHandle = fopen('php://input', 'r');
      $body = (string)stream_get_contents($streamHandle);
      fclose($streamHandle);
      $body = Util::toArray($body);

      if ($table !== null) {
        // validating payload...
        foreach ($body as $column => $value) {
          if (in_array($column, Config::get('TABLES')[$table]['columns']) === false) {
            Rock::halt(400, "unknown column `{$column}` for table `{$table}`");
          }
        }
      }

      return $body;
    }



    /**
     * given an array and status code it'll return a JSON response
     *
     * @param array $data
     * @param integer $status
     */
    public static function JSON($data, $status = 200) {
      $requestHeaders = Rock::getHeaders();
      $origin = array_key_exists('Origin', $requestHeaders) === true ? $requestHeaders['Origin'] : '*';
      header("HTTP/1.1 {$status} ". Util::$codes[$status]);
      header("Access-Control-Allow-Origin: {$origin}");
      header('Access-Control-Allow-Methods: '. implode(', ', Config::get('CORS_METHODS')));
      header('Access-Control-Allow-Headers: '. implode(', ', Config::get('CORS_HEADERS')));
      header('Access-Control-Allow-Credentials: true');
      header('Access-Control-Max-Age: '. Config::get('CORS_MAX_AGE'));
      header('Content-Type: application/json;charset=utf-8');
      echo json_encode($data);
    }



    /**
     * "destroys" the app
     * sends one LAST message before halting
     *
     * @param integer $status
     * @param string $message - message to be sent back with `error` property
     */
    public static function halt($status = 401, $message = null) {
      if ($message === null) {
        header("HTTP/1.1 {$status} ". Util::$codes[$status]);
        header('Content-Type: application/json;charset=utf-8');
      }

      else {
        Rock::JSON(['error' => $message], $status);
      }

      exit;
    }



    /**
     * given a string it'll return the hashed form
     *
     * @param string $string
     * @return string
     */
    public static function hash($string) {
      $hash = hash_init(Config::get('HASH'));
      hash_update($hash, $string);
      hash_update($hash, Config::get('SALT'));

      return hash_final($hash);
    }



    /**
     * return request headers as an associative array
     *
     * exception header
     * 4: unable to process request headers
     */
    public static function getHeaders() {
      $headers = getallheaders();

      if ($headers === false) {
        throw new Exception('Error Processing Request', 4);
      }

      return $headers;
    }



    /**
     * given temp file, it'll check if the it's within configuration
     *
     * @param String $tempPath
     * @return Boolean | String
     */
    public static function MIMEIsAllowed($tempPath) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime = finfo_file($finfo, $tempPath);
      finfo_close($finfo);

      if (in_array($mime, Config::get('S3_ALLOWED_MIME')) === true) {
        return $mime;
      } else {
        return false;
      }
    }



    /**
     * returns URL
     *
     * @return string
     */
    public static function getUrl() {
      $https = !empty($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'on') === 0 || !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strcasecmp($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0;
      return ($https ? 'https://' : 'http://').(!empty($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'].'@' : '').(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ($_SERVER['SERVER_NAME'].($https && $_SERVER['SERVER_PORT'] === 443 || $_SERVER['SERVER_PORT'] === 80 ? '' : ':'.$_SERVER['SERVER_PORT']))). substr($_SERVER['SCRIPT_NAME'],0, strrpos($_SERVER['SCRIPT_NAME'], '/'));
    }
  }
