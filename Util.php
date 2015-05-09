<?php
  Class Util {
    /**
     * generates random string
     *
     * @param integer $length the length of the string to be returned
     * @return string
     */
    public static function generate_token($length) {
      $seed = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
      $size = strlen($seed) - 1;
      $str = '';

      for($i = 0; $i < $length; $i++) {
        $str .= $seed[rand(0, $size)];
      }

      return $str;
    }



    /**
     * given an array and status code it'll return a JSON response
     *
     * @param array $data
     * @param integer $status
     */
    public static function JSON($data, $status) {
      $app = \Slim\Slim::getInstance();
      $response = $app->response;
      $response->setStatus($status);
      $response->headers->set('Content-Type', 'application/json');
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
     * given a message it'll return a JSON response
     * then halt the app
     *
     * @param string $message - message to be sent back with `error` property
     */
    public static function halt($message, $status = 403) {
      $app = \Slim\Slim::getInstance();
      $response = $app->response;
      $response->headers->set('Content-Type', 'application/json');
      $app->halt($status, json_encode(["error" => $message]));
    }
  }
?>
