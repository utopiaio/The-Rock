<?php
  use Pimple\Container;
  $RestContainer = new Container();

  $RestContainer["authenticate"] = function($c) {
    return function($routeInfo) {
      $body = Rock::getBody();

      if(!array_key_exists("username", $body) || !array_key_exists("password", $body)) {
        Rock::halt(400);
      }

      Rock::authenticate($body["username"], $body["password"]);
    };
  };

  $RestContainer["S3"] = function($c) {
    return function($routeInfo) {
      switch($_SERVER["REQUEST_METHOD"]) {
        case "GET":
          $file = $routeInfo[2]["filePath"];
          $filePath = CONFIG\S3_UPLOAD_DIR ."/". $file;

          if(file_exists($filePath) === true) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $filePath);
            finfo_close($finfo);

            header("HTTP/1.1 200 OK");
            header("Content-Type: {$mime}");

            if(preg_match("/(?i)\.(gif|jpe?g|png)$/", $file) === 0) {
              header("Content-Disposition: attachment;");
            }

            readfile($filePath);
          }

          else {
            Rock::halt(404, "file `{$file}` does not exist");
          }
        break;

        case "POST":
          $uploadHandler = new TheRockUploadHandler([
            "upload_dir" => CONFIG\S3_UPLOAD_DIR ."/",
            "upload_url" =>  Rock::getUrl() ."/". CONFIG\S3_UPLOAD_URL ."/"
          ]);
        break;

        case "DELETE":
          $file = $routeInfo[2]["filePath"];

          // deleting via file id...
          if(preg_match("/^\d+$/", $file) === 1) {
            try {
              $fileInfo = Moedoo::delete("s3", (int)$file);
              Rock::JSON($fileInfo, 202);

              if(is_file(CONFIG\S3_UPLOAD_DIR ."/". $fileInfo["name"])) {
                unlink(CONFIG\S3_UPLOAD_DIR ."/". $fileInfo["name"]);
              }

              if(is_file(CONFIG\S3_UPLOAD_DIR ."/thumbnail/". $fileInfo["name"])) {
                unlink(CONFIG\S3_UPLOAD_DIR ."/thumbnail/". $fileInfo["name"]);
              }
            } catch(Exception $e) {
              Rock::halt($e->getCode() === 1 ? 404 : 400, $e->getMessage());
            }
          }

          // deleting via file name...
          else {
            $fileInfo = Moedoo::select("s3", ["name" => $file]);

            if(count($fileInfo) === 0) {
              Rock::halt(404, "requested file `". $file ."` does not exist");
            }

            else {
              try {
                $fileInfo = Moedoo::delete("s3", $fileInfo[0]["id"]);
                Rock::JSON($fileInfo, 202);

                if(is_file(CONFIG\S3_UPLOAD_DIR ."/". $fileInfo["name"])) {
                  unlink(CONFIG\S3_UPLOAD_DIR ."/". $fileInfo["name"]);
                }

                if(is_file(CONFIG\S3_UPLOAD_DIR ."/thumbnail/". $fileInfo["name"])) {
                  unlink(CONFIG\S3_UPLOAD_DIR ."/thumbnail/". $fileInfo["name"]);
                }
              } catch(Exception $e) {
                Rock::halt(400, $e->getMessage());
              }
            }
          }
        break;
      }
    };
  };

  $RestContainer["REST"] = function($c) {
    return function($routeInfo) {
      $table = $routeInfo[2]["table"];
      $id = array_key_exists("id", $routeInfo[2]) === true ? $routeInfo[2]["id"] : -1;
      $count = array_key_exists("count", $routeInfo[2]);

      switch($_SERVER["REQUEST_METHOD"]) {
        case "GET":
          switch($table) {
            case "users":
              Rock::authenticated("ADMINISTRATOR");
            break;
          }

          if(isset($_GET["q"]) === true && $id === -1) {
            Rock::JSON(Moedoo::search($table, $_GET["q"]), 200);
          }

          else if($count === true) {
            Rock::JSON([
              "count" => Moedoo::count($routeInfo[2]["table"])
            ]);
          }

          else if(isset($_GET["limit"]) === true && preg_match("/^\d+$/", $_GET["limit"]) === 1) {
            $limit = $_GET["limit"];
            $count = 0;
            $depth = 1;

            if(isset($_GET["offset"]) === true && preg_match("/^\d+$/", $_GET["offset"]) === 1) {
              $count = $_GET["offset"];
            }

            Rock::JSON(Moedoo::select($table, null, null, $depth, $limit, $count), 200);
          }

          else {
            $result = $id === -1 ? Moedoo::select($table) : Moedoo::select($table, [CONFIG\TABLES[$table]["pk"] => $id]);

            if($id === -1) {
              Rock::JSON($result, 200);
            }

            else if(count($result) === 1) {
              Rock::JSON($result[0], 200);
            }

            else {
              Rock::halt(404, "`". $table ."` with  id `". $id ."` does not exist");
            }
          }
        break;

        case "POST":
          $body = Rock::getBody($table);

          switch($table) {
            case "users":
              Rock::authenticated("ADMINISTRATOR");

              if(array_key_exists("user_username", $body) === true) {
                $body["user_username"] = strtolower($body["user_username"]);
                $body["user_username"] = preg_replace("/ /", "_", $body["user_username"]);
              }

              if(array_key_exists("user_password", $body) === true) {
                $body["user_password"] = Rock::hash($body["user_password"]);
              }
            break;
          }

          try {
            $result = Moedoo::insert($table, $body);
          } catch(Exception $e) {
            Rock::halt(400, $e->getMessage());
          }

          Rock::JSON($result, 201);
        break;

        case "PUT":
          $body = Rock::getBody($table);

          switch($table) {
            case "users":
              Rock::authenticated("ADMINISTRATOR");

              if(array_key_exists("user_username", $body) === true) {
                $body["user_username"] = strtolower($body["user_username"]);
                $body["user_username"] = preg_replace("/ /", "_", $body["user_username"]);
              }

              if(array_key_exists("user_password", $body) === true) {
                $body["user_password"] = Rock::hash($body["user_password"]);
              }
            break;
          }

          try {
            $result = Moedoo::update($table, $body, $id);
          } catch(Exception $e) {
            Rock::halt(400, $e->getMessage());
          }

          Rock::JSON($result, 202);
        break;

        case "DELETE":
          switch($table) {
            case "users":
              Rock::authenticated("ADMINISTRATOR");
            break;
          }

          try {
            $result = Moedoo::delete($table, $id);
          } catch(Exception $e) {
            Rock::halt(400, $e->getMessage());
          }

          Rock::JSON($result, 202);
        break;
      }
    };
  };

  $RestContainer["OPTIONS"] = function($c) {
    return function($routeInfo) {
      $requestHeaders = Rock::getHeaders();
      $origin = array_key_exists("Origin", $requestHeaders) === true ? $requestHeaders["Origin"] : "*";
      $origin_stripped = preg_replace("/https?:\/\/|www\./", "", $origin);

      if(in_array("*", CONFIG\CORS_WHITE_LIST) === true || in_array($origin_stripped, CONFIG\CORS_WHITE_LIST) === true) {
        header("HTTP/1.1 202 Accepted");
        header("Access-Control-Allow-Origin: {$origin}");
        header("Access-Control-Allow-Methods: ". implode(", ", CONFIG\CORS_METHODS));
        header("Access-Control-Allow-Headers: ". implode(", ", CONFIG\CORS_HEADERS));
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: ". CONFIG\CORS_MAX_AGE);
      }

      else {
        Rock::halt(403, "CORS forbidden, please contact system administrator");
      }
    };
  };
?>
