<?php
  Class Util {
    /**
     * generates random string
     *
     * @param integer $length - the length of the string to be returned
     * @return string
     */
    public static function randomString($length) {
      $seed = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
      $size = strlen($seed) - 1;
      $str = "";

      for($i = 0; $i < $length; $i++) {
        $str .= $seed[rand(0, $size)];
      }

      return $str;
    }



    /**
     * given a JSON representation of a string, returns an associative array
     * representation of it
     *
     * @param string $body
     * @return array
     */
    public static function toArray($body) {
      $body = json_decode($body);

      if($body === null) {
        Util::halt(400, "unable to parse body");
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
     * given an array and status code it'll return a JSON response
     *
     * @param array $data
     * @param integer $status
     */
    public static function JSON($data, $status = 200) {
      $app = \Slim\Slim::getInstance();
      $response = $app->response;
      $response->setStatus($status);
      $response->headers->set("Content-Type", "application/json;charset=utf-8");
      $response->setBody(json_encode($data));
    }



    /**
     * given a string it'll return the hashed form
     *
     * @param string $string
     * @return string
     */
    public static function hash($string) {
      $hash = hash_init(CONFIG\HASH);
      hash_update($hash, $string);
      hash_update($hash, CONFIG\SALT);

      return hash_final($hash);
    }



    /**
     * "destroys" the app
     * sends one LAST message before halting
     *
     * @param integer $status
     * @param string $message - message to be sent back with `error` property
     */
    public static function halt($status = 401, $message = null) {
      $app = \Slim\Slim::getInstance();
      $response = $app->response;
      $response->headers->set("Content-Type", "application/json;charset=utf-8");

      $message === null ? $app->halt($status) : $app->halt($status, json_encode(["error" => $message]));
    }
  }
?>
