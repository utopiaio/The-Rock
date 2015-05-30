<?php
  Class Util {
    /**
     * generates random string
     *
     * @param integer $length the length of the string to be returned
     * @return string
     */
    public static function generate_token($length) {
      $seed = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
      $size = strlen($seed) - 1;
      $str = "";

      for($i = 0; $i < $length; $i++) {
        $str .= $seed[rand(0, $size)];
      }

      return $str;
    }



    /**
     * given a JSON representation of a JSON string, returns an associative array
     * representation of it
     *
     * @param string $body
     * @return array
     */
    public static function to_array($body) {
      $body = json_decode($body);

      if($body === null) {
        Util::stop("bad request, check the payload and try again", 400);
      }

      else {
        $array = [];

        foreach($body as $key => $value) {
          $array[$key] = $value;
        }

        return $array;
      }
    }



    /**
     * given table and a payload, makes sure the data is in accordance with
     * `config` file --- if anything "suspicious" is detected - execution is stopped
     *
     * @param string $table
     * @param payload
     */
    public static function validate_payload($table, $payload) {
      if(is_null($payload) === true) {
        Util::halt("bad request, check the payload and try again", 400);
      }

      foreach($payload as $key => $value) {
        if(in_array($key, \Config\TABLES[$table]["columns"]) === false) {
          Util::halt("bad request, check the payload and try again", 400);
        }
      }
    }



    /**
     * given an array and status code it'll return a JSON response
     *
     * @param array $data
     * @param integer $status
     */
    public static function JSON($data, $status = 202) {
      $app = \Slim\Slim::getInstance();
      $response = $app->response;
      $response->setStatus($status);
      $response->headers->set("Content-Type", "application/json;charset=utf-8");
      echo json_encode($data);
    }



    /**
     * given a string it'll return the hashed form
     *
     * @param string $string
     * @return string
     */
    public static function hash($string) {
      $hash = hash_init(\Config\HASH);
      hash_update($hash, $string);
      hash_update($hash, \Config\SALT);

      return hash_final($hash);
    }



    /**
     * "destroys" the app
     * sends one LAST message before halting
     *
     * @param string $message - message to be sent back with `error` property
     */
    public static function halt($message, $status = 403) {
      $app = \Slim\Slim::getInstance();
      $response = $app->response;
      $response->headers->set("Content-Type", "application/json;charset=utf-8");
      $app->halt($status, json_encode(["error" => $message]));
    }
  }
?>
