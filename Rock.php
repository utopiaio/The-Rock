<?php
  class Rock {
    /**
     * checks jwt and authenticates or halts execution
     */
    public static function authenticated() {
      $app = \Slim\Slim::getInstance();
      $request = $app->request;

      if(isset($request->headers[\Config\JWT_REQ_HEADER])) {
        try {
          $decoded = (array)JWT::decode($request->headers["X-Access-Token"], \Config\JWT_KEY, ["HS256"]);
        } catch(Exception $e) {
          Util::halt("unauthorized, please login", 401);
        }

        $params = [$decoded["id"]];
        $query = "SELECT ". implode(", ", \Config\TABLES["users"]["returning"]) ." FROM ". \Config\TABLE_PREFIX ."users WHERE ". \Config\TABLES["users"]["pk"] ."=$1;";
        $result = pg_query_params($query, $params);

        // user doesn't exist
        if(pg_affected_rows($result) === 0) {
          Util::halt("unauthorized, please login", 401);
        }

        // user has been suspended
        else if(pg_affected_rows($result) === 1 && pg_fetch_all($result)[0]["user_status"] === "f") {
          Util::halt("unauthorized, account has been suspended", 401);
        }
      }

      else {
        Util::halt("unauthorized, please login", 401);
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
      $password = Util::hash($password);
      $params = [$username, $password, "ADMINISTRATOR"];
      $query = "SELECT ". implode(", ", \Config\TABLES["users"]["returning"]) ." FROM ". \Config\TABLE_PREFIX ."users WHERE user_username=$1 AND user_password=$2 AND user_type=$3;";
      $result = pg_query_params($query, $params);

      // straight up, unauthorized
      if(pg_affected_rows($result) === 0) {
        Util::JSON(["error" => "unauthorized"], 401);
      }

      // user account exists but it's suspended
      else if(pg_affected_rows($result) === 1 && pg_fetch_all($result)[0]["user_status"] === "f") {
        Util::JSON(["error" => "account has been suspended, contact system administrator"], 403);
      }

      // proceed with authentication
      // building JWT...
      else if(pg_affected_rows($result) === 1 && pg_fetch_all($result)[0]["user_status"] === "t") {
        $user = pg_fetch_all($result)[0];
        $user["user_status"] = true;

        $token = [
          "iss" => \Config\JWT_ISS,
          "iat" => strtotime(\Config\JWT_IAT),
          "id" => $user["user_id"]
        ];

        $jwt = JWT::encode($token, \Config\JWT_KEY);
        Util::JSON(["jwt" => $jwt, "info" => $user]);
      }

      // something horrible has happened
      else {
        Util::JSON(["error" => "ouch, that hurt"], 500);
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
     */
    public static function check($method, $table) {
      if(array_key_exists($table, \Config\TABLES) === false) {
        Util::halt("requested URL was not found");
      }

      if(in_array($table, \Config\RESTRICTED_REQUESTS[$method]) === true) {
        Rock::authenticated();
      }

      if(in_array($table, \Config\FORBIDDEN_REQUESTS[$method]) === true) {
        Util::halt("request is forbidden", 403);
      }
    }
  }
?>
