<?php
  $RestContainer["S3"] = function($c) {
    return function($routeInfo) {
      switch($_SERVER["REQUEST_METHOD"]) {
        case "GET":
          $file = $routeInfo[2]["filePath"];
          $filePath = Config::get("S3_UPLOAD_DIR") ."/". $file;

          if(file_exists($filePath) === true) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);

            $requestHeaders = Rock::getHeaders();
            $origin = array_key_exists("Origin", $requestHeaders) === true ? $requestHeaders["Origin"] : "*";
            $origin_stripped = preg_replace("/https?:\/\/|www\./", "", $origin);

            // https://developer.mozilla.org/en-US/docs/Web/HTML/CORS_settings_attributes
            if(in_array("*", Config::get("CORS_WHITE_LIST")) === true || in_array($origin_stripped, Config::get("CORS_WHITE_LIST")) === true) {
              header("Access-Control-Allow-Origin: {$origin}");
              header("Access-Control-Allow-Methods: ". implode(", ", Config::get("CORS_METHODS")));
              header("Access-Control-Allow-Headers: ". implode(", ", Config::get("CORS_HEADERS")));
              header("Access-Control-Allow-Credentials: true");
              header("Access-Control-Max-Age: ". Config::get("CORS_MAX_AGE"));
            }

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
            "upload_dir" => Config::get("S3_UPLOAD_DIR") ."/",
            "upload_url" =>  Rock::getUrl() ."/". Config::get("S3_UPLOAD_URL") ."/"
          ]);
        break;

        case "DELETE":
          $file = $routeInfo[2]["filePath"];

          // deleting via file id...
          if(preg_match("/^\d+$/", $file) === 1) {
            try {
              $fileInfo = Moedoo::delete("s3", (int)$file);
              Rock::JSON($fileInfo, 202);

              if(is_file(Config::get("S3_UPLOAD_DIR") ."/". $fileInfo["name"])) {
                unlink(Config::get("S3_UPLOAD_DIR") ."/". $fileInfo["name"]);
              }

              if(is_file(Config::get("S3_UPLOAD_DIR") ."/thumbnail/". $fileInfo["name"])) {
                unlink(Config::get("S3_UPLOAD_DIR") ."/thumbnail/". $fileInfo["name"]);
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

                if(is_file(Config::get("S3_UPLOAD_DIR") ."/". $fileInfo["name"])) {
                  unlink(Config::get("S3_UPLOAD_DIR") ."/". $fileInfo["name"]);
                }

                if(is_file(Config::get("S3_UPLOAD_DIR") ."/thumbnail/". $fileInfo["name"])) {
                  unlink(Config::get("S3_UPLOAD_DIR") ."/thumbnail/". $fileInfo["name"]);
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
