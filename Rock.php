<?php
  class Rock {
    /**
     * checks jwt and authenticates or halts execution
     *
     * @param string $role - user role to match against
     * @return array - user info from db
     */
    public static function authenticated($role = null) {
      $app = \Slim\Slim::getInstance();
      $request = $app->request;

      if(isset($request->headers[CONFIG\JWT_HEADER])) {
        try {
          $decoded = (array)JWT::decode($request->headers[CONFIG\JWT_HEADER], CONFIG\JWT_KEY, ["HS256"]);
        } catch(Exception $e) {
          Util::halt(401, "invalid authorization token");
        }

        $params = [$decoded["id"]];
        $query = "SELECT ". implode(", ", CONFIG\TABLES["users"]["returning"]) ." FROM ". CONFIG\TABLE_PREFIX ."users WHERE ". CONFIG\TABLES["users"]["pk"] ."=$1;";
        $result = pg_query_params($query, $params);

        if(pg_affected_rows($result) === 0) {
          Util::halt(401, "token no longer valid");
        }

        else if(pg_affected_rows($result) === 1) {
          $user = Moedoo::cast("users", pg_fetch_all($result))[0];

          if($user["user_status"] === false) {
            Util::halt(401, "account has been suspended");
          }

          else {
            if($role === null || $role === $user["user_type"]) {
              return $user;
            }

            else {
              Util::halt(401, "role mismatch");
            }
          }
        }
      }

      else {
        Util::halt(401, "missing authentication header `". CONFIG\JWT_HEADER ."`");
      }
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
      $username = preg_replace("/ /", "_", $username);
      $password = Util::hash($password);
      $params = [$username, $password];
      $query = "SELECT ". implode(", ", CONFIG\TABLES["users"]["returning"]) ." FROM ". CONFIG\TABLE_PREFIX ."users WHERE user_username=$1 AND user_password=$2;";
      $result = pg_query_params($query, $params);

      // straight up, unauthorized
      if(pg_affected_rows($result) === 0) {
        Util::halt(401, "wrong username and/or password");
      }

      else if(pg_affected_rows($result) === 1) {
        $user = Moedoo::cast("users", pg_fetch_all($result))[0];

        // user account has been suspended
        if($user["user_status"] === false) {
          Util::halt(401, "account has been suspended");
        }

        // all good, proceeding with authentication...
        else {
          $token = [
            "iss" => CONFIG\JWT_ISS,
            "iat" => strtotime(CONFIG\JWT_IAT),
            "id" => $user["user_id"]
          ];

          // TODO
          // make a fingerprint so that the token stays locked-down
          $jwt = JWT::encode($token, CONFIG\JWT_KEY);
          Util::JSON(["jwt" => $jwt, "user" => $user]);
        }
      }
    }



    /**
     * runs security check on CRUD mapping functions
     * 1: checks weather or not table exists in `config` file
     * 2: checks if $method + $table is restricted calls authentication
     * 3: checks if $method + $table is forbidden execution is stopped
     *
     * @param string $method
     * @param string $table
     * @param string $role
     */
    public static function check($method, $table, $role = "ANY") {
      if(array_key_exists($table, CONFIG\TABLES) === false) {
        Util::halt(404, "requested resource `". $table ."` does not exist");
      }

      if(in_array($table, CONFIG\FORBIDDEN_REQUESTS[$method]) === true) {
        Util::halt(403, "`". $method ."` method on table `". $table ."` is forbidden");
      }

      if(in_array($table, CONFIG\AUTH_REQUESTS[$method]) === true) {
        $role === "ANY" ? Rock::authenticated() : Rock::authenticated($role);
      }
    }



    /**
     * returns body after validating payload
     *
     * @param string $table - request body as a string
     * @return associative array representation of the passed body
     */
    public static function getBody($table) {
      $app = \Slim\Slim::getInstance();
      $request = $app->request;
      $body = Util::toArray($request->getBody());

      // validating payload...
      foreach($body as $column => $value) {
        if(in_array($column, CONFIG\TABLES[$table]["columns"]) === false) {
          Util::halt(400, "unknown column `". $column ."`");
        }
      }

      return $body;
    }
  }
?>
