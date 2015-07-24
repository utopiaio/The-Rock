<?php
  class TheRockUploadHandler extends UploadHandler {
    protected function handle_file_upload($uploaded_file, $name, $size, $type, $error, $index = null, $content_range = null) {
      $file = parent::handle_file_upload($uploaded_file, $name, $size, $type, $error, $index, $content_range);

      if(empty($file->error)) {
        $file->deleteUrl = preg_replace("/\/index\.php\?file\=/", "/".  Config::get("S3_UPLOAD_URL") ."/", $file->deleteUrl);
        // $file->type is empty
        // so we're going to be to resolving URL so we can find the appropriate MIME
        $filePath = urldecode(Config::get("S3_UPLOAD_DIR") ."/". substr($file->url, strpos($file->url, "@S3/") + 4));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        if(empty($file->thumbnailUrl)) {
          $fileData = [
            "\"deleteType\"" => $file->deleteType,
            "\"deleteUrl\"" => $file->deleteUrl,
            "name" => $file->name,
            "size" => $file->size,
            "type" => $mime,
            "url" => $file->url
          ];
        }

        else {
          if($file->size < Config::get("S3_BASE64")) {
            $fileData = [
              "\"deleteType\"" => $file->deleteType,
              "\"deleteUrl\"" => $file->deleteUrl,
              "name" => $file->name,
              "size" => $file->size,
              "\"thumbnailUrl\"" => $file->thumbnailUrl,
              "type" => $mime,
              "url" => $file->url,
              "base64" => "data:". $mime .";base64,". base64_encode(file_get_contents($filePath))
            ];
          }

          else {
            $fileData = [
              "\"deleteType\"" => $file->deleteType,
              "\"deleteUrl\"" => $file->deleteUrl,
              "name" => $file->name,
              "size" => $file->size,
              "\"thumbnailUrl\"" => $file->thumbnailUrl,
              "type" => $mime,
              "url" => $file->url
            ];
          }
        }

        try {
          $file = Moedoo::insert("s3", $fileData);
        } catch(Exception $e) {
          Rock::halt($e->getCode() === 1 ? 500 : 400, $e->getMessage());
        }
      }

      return $file;
    }

    protected function body($str) {
      Rock::JSON(json_decode($str), 202);
    }
  }
?>
