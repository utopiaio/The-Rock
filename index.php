<?php
  require "lib/Slim/Slim.php";
  require "lib/JWT/Authentication/JWT.php";
  require "lib/JWT/Exceptions/BeforeValidException.php";
  require "lib/JWT/Exceptions/ExpiredException.php";
  require "lib/JWT/Exceptions/SignatureInvalidException.php";
  require "lib/Blueimp/UploadHandler.php";
  require "lib/Blueimp/TheRockUploadHandler.php";
  require "config.php";
  require "Moedoo.php";
  require "Util.php";
  require "Rock.php";

  \Slim\Slim::registerAutoloader();

  $db = Moedoo::db(CONFIG\DB_HOST, CONFIG\DB_PORT, CONFIG\DB_USER, CONFIG\DB_PASSWORD, CONFIG\DB_NAME);

  $app = new \Slim\Slim([
    "debug" => CONFIG\DEBUG,
    "rock.debug" => CONFIG\ROCK_DEBUG
  ]);



  // authenticate +=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=
  $app->post("/authenticate", function() use($app) {
    $request = $app->request;
    $body = Util::toArray($request->getBody());

    if(!array_key_exists("username", $body) || !array_key_exists("password", $body)) {
      Util::halt(400);
    }

    Rock::authenticate($body["username"], $body["password"]);
  });
  // authenticate: end +=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+



  // S3 +=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=
  $app->post("/S3", function() use($app) {
    $uploadHandler = new TheRockUploadHandler([
      "upload_dir" => CONFIG\S3_UPLOAD_DIR ."/",
      "upload_url" => $app->request->getUrl() ."/". CONFIG\S3_UPLOAD_URL ."/"
    ]);
  });

  $app->map("/". CONFIG\S3_UPLOAD_URL ."/:file+", function($file) use($app) {
    switch($app->request->getMethod()) {
      case "GET":
        $file = implode("/", $file);
        $filePath = CONFIG\S3_UPLOAD_DIR ."/". $file;

        if(file_exists($filePath) === true) {
          $finfo = finfo_open(FILEINFO_MIME_TYPE);
          $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $filePath);
          finfo_close($finfo);

          $response = $app->response;
          $response->headers->set("Content-Type", $mime);

          if(preg_match("/(?i)\.(gif|jpe?g|png)$/", $file) === 0) {
            $response->headers->set("Content-Disposition", "attachment;");
          }

          readfile($filePath);
        }

        else {
          Util::halt(404, "file `{$file}` does not exist");
        }
      break;

      // in keeping with our REST API we'll handle deletion ourselves
      //
      // two ways to request deletion of a file
      // 1: filename - @S3/fileName
      // 2: id - @S3/id
      case "DELETE":
        if(count($file) === 1) {
          // deleting via file id...
          if(preg_match("/^\d+$/", $file[0]) === 1) {
            try {
              $fileInfo = Moedoo::delete("s3", (int)$file[0]);
              Util::JSON($fileInfo, 202);

              if(is_file(CONFIG\S3_UPLOAD_DIR ."/". $fileInfo["name"])) {
                unlink(CONFIG\S3_UPLOAD_DIR ."/". $fileInfo["name"]);
              }

              if(is_file(CONFIG\S3_UPLOAD_DIR ."/thumbnail/". $fileInfo["name"])) {
                unlink(CONFIG\S3_UPLOAD_DIR ."/thumbnail/". $fileInfo["name"]);
              }
            } catch(Exception $e) {
              Util::halt($e->getCode() === 1 ? 404 : 400, $e->getMessage());
            }
          }

          // deleting via file name...
          else {
            $fileInfo = Moedoo::select("s3", ["name" => $file[0]]);

            if(count($fileInfo) === 0) {
              Util::halt(404, "requested file `". $file[0] ."` does not exist");
            }

            else {
              try {
                $fileInfo = Moedoo::delete("s3", $fileInfo[0]["id"]);
                Util::JSON($fileInfo, 202);

                if(is_file(CONFIG\S3_UPLOAD_DIR ."/". $fileInfo["name"])) {
                  unlink(CONFIG\S3_UPLOAD_DIR ."/". $fileInfo["name"]);
                }

                if(is_file(CONFIG\S3_UPLOAD_DIR ."/thumbnail/". $fileInfo["name"])) {
                  unlink(CONFIG\S3_UPLOAD_DIR ."/thumbnail/". $fileInfo["name"]);
                }
              } catch(Exception $e) {
                Util::halt(400, $e->getMessage());
              }
            }
          }
        }

        else {
          Util::halt(400, "`". $app->request->getPath() ."` is not a valid request");
        }
      break;
    }
  })->via("GET", "DELETE");
  // S3: end +=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+



  // generic CRUD mapper +=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+
  $app->group("/:table", function() use($app) {
    $app->get("(/:id)", function($table, $id = -1) use($app) {
      switch($table) {
        case "users":
          Rock::authenticated("ADMINISTRATOR");
        break;

        default:
          Rock::check("GET", $table);
        break;
      }

      if(isset($_GET["q"]) === true && $id === -1) {
        Util::JSON(Moedoo::search($table, $_GET["q"]), 200);
      }

      else {
        $result = $id === -1 ? Moedoo::select($table) : Moedoo::select($table, [CONFIG\TABLES[$table]["pk"] => $id]);

        if($id === -1) {
          Util::JSON($result, 200);
        }

        else if(count($result) === 1) {
          Util::JSON($result[0], 200);
        }

        else {
          Util::halt(404, "`". $table ."` with  id `". $id ."` does not exist");
        }
      }
    })->conditions(["id" => "\d+"]);

    $app->post("", function($table) use($app) {
      $body = Rock::getBody($table);

      switch($table) {
        case "users":
          Rock::authenticated("ADMINISTRATOR");

          if(array_key_exists("user_username", $body) === true) {
            $body["user_username"] = strtolower($body["user_username"]);
            $body["user_username"] = preg_replace("/ /", "_", $body["user_username"]);
          }

          if(array_key_exists("user_password", $body) === true) {
            $body["user_password"] = Util::hash($body["user_password"]);
          }
        break;

        default:
          Rock::check("POST", $table);
        break;
      }

      try {
        $result = Moedoo::insert($table, $body);
      } catch(Exception $e) {
        Util::halt(400, $e->getMessage());
      }

      Util::JSON($result, 201);
    });

    $app->put("/:id", function($table, $id) use($app) {
      $body = Rock::getBody($table);

      switch($table) {
        case "users":
          Rock::authenticated("ADMINISTRATOR");

          if(array_key_exists("user_username", $body) === true) {
            $body["user_username"] = strtolower($body["user_username"]);
            $body["user_username"] = preg_replace("/ /", "_", $body["user_username"]);
          }

          if(array_key_exists("user_password", $body) === true) {
            $body["user_password"] = Util::hash($body["user_password"]);
          }
        break;

        default:
          Rock::check("PUT", $table);
        break;
      }

      try {
        $result = Moedoo::update($table, $body, $id);
      } catch(Exception $e) {
        Util::halt(400, $e->getMessage());
      }

      Util::JSON($result, 202);
    })->conditions(["id" => "\d+"]);

    $app->delete("/:id", function($table, $id) use($app) {
      switch($table) {
        case "users":
          Rock::authenticated("ADMINISTRATOR");
        break;

        default:
          Rock::check("DELETE", $table);
        break;
      }

      try {
        $result = Moedoo::delete($table, $id);
      } catch(Exception $e) {
        Util::halt(400, $e->getMessage());
      }

      Util::JSON($result, 202);
    })->conditions(["id" => "\d+"]);
  });
  // generic CRUD mapper: end +=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=



  // this maps ANY OPTIONS request sent and checks the origin against
  // `config` and returns the appropriate header
  $app->options("/(:path+)", function() use($app) {
    $response = $app->response;
    $origin = $app->request->headers["Origin"];
    $origin_stripped = preg_replace("/https?:\/\/|www\./", "", $origin);

    if(in_array("*", CONFIG\CORS_WHITE_LIST) === true || in_array($origin_stripped, CONFIG\CORS_WHITE_LIST) === true) {
      $response->headers->set("Access-Control-Allow-Origin", "{$origin}");
      $response->headers->set("Access-Control-Allow-Methods", implode(", ", CONFIG\CORS_METHODS));
      $response->headers->set("Access-Control-Allow-Headers", implode(", ", CONFIG\CORS_HEADERS));
      $response->headers->set("Access-Control-Allow-Credentials", "true");
      $response->headers->set("Access-Control-Max-Age", CONFIG\CORS_MAX_AGE);
      $response->setStatus(202);
    }

    else {
      Util::halt(403, "CORS forbidden, please contact system administrator");
    }
  });

  // refereed
  $app->notFound(function() use($app) {
    Util::halt(404, "`". $app->request->getMethod() ."` method with URL `". $app->request->getPath() ."` not found");
  });

  // executed whenever an error is thrown
  // PS: called when `debug` is set to false
  $app->error(function(Exception $e) use($app) {
    Util::halt(500, $app->config("rock.debug") === true ? $e->getMessage() : "Application Error, if problem persists please contact system administrator.");
  });

  $app->run();
?>
