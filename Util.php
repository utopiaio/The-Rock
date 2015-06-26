<?php
  Class Util {
    public static $codes = [
      200 => 'OK',
      201 => 'Created',
      202 => 'Accepted',
      203 => 'Non-Authoritative Information',
      204 => 'No Content',
      205 => 'Reset Content',
      206 => 'Partial Content',

      300 => 'Multiple Choices',
      301 => 'Moved Permanently',
      302 => 'Found',
      303 => 'See Other',
      304 => 'Not Modified',
      305 => 'Use Proxy',
      307 => 'Temporary Redirect',

      400 => 'Bad Request',
      401 => 'Unauthorized',
      403 => 'Forbidden',
      404 => 'Not Found',
      405 => 'Method Not Allowed',
      406 => 'Not Acceptable',
      407 => 'Proxy Authentication Required',
      408 => 'Request Timeout',
      409 => 'Conflict',
      410 => 'Gone',
      411 => 'Length Required',
      412 => 'Precondition Failed',
      413 => 'Request Entity Too Large',
      414 => 'Request-URI Too Long',
      415 => 'Unsupported Media Type',
      416 => 'Requested Range Not Satisfiable',
      417 => 'Expectation Failed',

      500 => 'Internal Server Error',
      501 => 'Not Implemented',
      502 => 'Bad Gateway',
      503 => 'Service Unavailable',
      504 => 'Gateway Timeout',
      505 => 'HTTP Version Not Supported'
    ];



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
  }
?>
