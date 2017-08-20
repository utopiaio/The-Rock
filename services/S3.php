<?php
  $__REST__['S3'] = function ($routeInfo) {
    switch ($_SERVER['REQUEST_METHOD']) {
      case 'GET':
        $file = $routeInfo[2]['filePath'];
        $filePath = Config::get('S3_UPLOAD_DIR') .'/'. $file;

        if (file_exists($filePath) === true) {
          $mime = Rock::MIMEIsAllowed($filePath);
          $requestHeaders = Rock::getHeaders();
          $origin = array_key_exists('Origin', $requestHeaders) === true ? $requestHeaders['Origin'] : '*';
          $originStripped = preg_replace('/https?:\/\/|www\./', '', $origin);

          // https://developer.mozilla.org/en-US/docs/Web/HTML/CORS_settings_attributes
          if (in_array('*', Config::get('CORS_WHITE_LIST')) === true || in_array($originStripped, Config::get('CORS_WHITE_LIST')) === true) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Methods: '. implode(', ', Config::get('CORS_METHODS')));
            header('Access-Control-Allow-Headers: '. implode(', ', Config::get('CORS_HEADERS')));
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: '. Config::get('CORS_MAX_AGE'));
          }

          header('HTTP/1.1 200 OK');
          // JSON files are read as text files --- we're making sure json are read accordingly
          strtolower(substr($file, strrpos($file, '.') + 1)) === 'json' ?
            header('Content-Type: application/json;charset=utf-8') :
            header("Content-Type: {$mime}");

          if ($mime === false) {
            header('Content-Disposition: attachment;');
          }

          readfile($filePath);
        }

        else {
          Rock::halt(404, "file `{$file}` does not exist");
        }
      break;

      case 'POST':
        $savedFiles = [];

        foreach ($_FILES as $key => $files) {
          foreach ($files['name'] as $index => $file) {
            $mime = Rock::MIMEIsAllowed($files['tmp_name'][$index]);

            if ($mime === false || $files['error'][$index] > 0 || (int)$files['size'][$index] > Config::get('S3_MAX_UPLOAD_SIZE')) {
              unlink($files['tmp_name'][$index]);
            } else {
              $name = Util::randomString(Config::get('S3_FILE_NAME_SIZE')) .'.'. substr($files['name'][$index], strrpos($files['name'][$index], '.') + 1);
              $size = (int)$files['size'][$index];
              move_uploaded_file($files['tmp_name'][$index], Config::get('S3_UPLOAD_DIR') .'/'. $name);
              array_push($savedFiles, Moedoo::insert('s3', ['name' => $name, 'size' => $size, 'type' => $mime]));
            }
          }
        }

        Rock::JSON($savedFiles, 202);
      break;

      case 'DELETE':
        $file = $routeInfo[2]['filePath'];

        // deleting via file id...
        if (preg_match('/^\d+$/', $file) === 1) {
          try {
            $fileInfo = Moedoo::delete('s3', (int)$file);
            Rock::JSON($fileInfo, 202);

            if (is_file(Config::get('S3_UPLOAD_DIR') .'/'. $fileInfo['name'])) {
              unlink(Config::get('S3_UPLOAD_DIR') .'/'. $fileInfo['name']);
            }
          } catch (Exception $e) {
            Rock::halt($e -> getCode() === 1 ? 404 : 400, $e -> getMessage());
          }
        }

        // deleting via file name...
        else {
          $fileInfo = Moedoo::select('s3', ['name' => $file]);

          if (count($fileInfo) === 0) {
            Rock::halt(404, "requested file `{$file}` does not exist");
          }

          else {
            try {
              $fileInfo = Moedoo::delete('s3', $fileInfo[0]['id']);
              Rock::JSON($fileInfo, 202);

              if (is_file(Config::get('S3_UPLOAD_DIR') .'/'. $fileInfo['name'])) {
                unlink(Config::get('S3_UPLOAD_DIR') .'/'. $fileInfo['name']);
              }
            } catch (Exception $e) {
              Rock::halt(400, $e -> getMessage());
            }
          }
        }
      break;
    }
  };
