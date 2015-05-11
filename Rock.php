<?php
  class Rock {
    /**
     * checks session and authenticates or halts execution
     */
    public static function authenticated() {
      if(isset($_SESSION[\Config\TABLES["users"]["pk"]]) && isset($_SESSION["user_type"])) {
        $params = [$_SESSION[\Config\TABLES["users"]["pk"]], $_SESSION["user_type"]];
        $query = "SELECT ". implode(", ", \Config\TABLES["users"]["RETURNING"]) ." FROM ". \Config\TABLE_PREFIX ."users WHERE ". \Config\TABLES["users"]["pk"] ."=$1 AND user_type=$2;";
        $result = pg_query_params($query, $params);

        // user doesn't exist
        if(pg_affected_rows($result) === 0) {
          Util::halt("unauthorized, please login", 401);
        }

        // user has been suspended
        else if(pg_affected_rows($result) === 1 && (pg_fetch_all($result)[0]["user_status"] === "f" || pg_fetch_all($result)[0]["user_type"] !== "ADMINISTRATOR")) {
          Util::halt("unauthorized, account has been suspended", 401);
        }
      }

      // straight up unauthenticated
      else {
        Util::halt("unauthorized, please login", 401);
      }
    }



    /**
     * given username and password info, it'll create session for administrator
     *
     * @param string $username
     * @param string $password - raw password
     */
    public static function login($username, $password) {
      $password = Util::hash($password);
      $params = [$username, $password, 'ADMINISTRATOR'];
      $query = "SELECT ". implode(", ", \Config\TABLES["users"]["RETURNING"]) ." FROM ". \Config\TABLE_PREFIX ."users WHERE user_username=$1 AND user_password=$2 AND user_type=$3;";
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
      else if(pg_affected_rows($result) === 1 && pg_fetch_all($result)[0]["user_status"] === "t") {
        $user = pg_fetch_all($result)[0];
        $user["user_status"] = true;

        Util::JSON($user, 202);

        foreach($user as $key => $value) {
          $_SESSION[$key] = $value;
        }
      }

      // something horrible has happened
      else {
        Util::JSON(["error" => "ouch, that hurt"], 500);
      }
    }



    /**
     * clears a session (if there's one)
     * no mater the condition - execution is halted
     */
    public static function logout() {
      $app = \Slim\Slim::getInstance();

      // one property is enough to check for session
      if(isset($_SESSION[\Config\TABLES["users"]["pk"]])) {
        Util::clear_session();
        $app = \Slim\Slim::getInstance();
        $response = $app->response;
        $response->headers->set('Content-Type', 'application/json');
        $app->halt(202, json_encode(["success" => "thank you for spending quality time with the site today"]));
      }

      // there's no session to clear
      else {
        Util::halt("you need to be logged in to logout", 412);
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
        Util::stop("requested URL was not found");
      }

      if(in_array($table, \Config\RESTRICTED_REQUESTS[$method]) === true) {
        Rock::authenticated();
      }

      if(in_array($table, \Config\FORBIDDEN_REQUESTS[$method]) === true) {
        Util::stop("request is forbidden", 403);
      }
    }
  }
?>
