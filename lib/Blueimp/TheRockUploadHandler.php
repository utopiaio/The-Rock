<?php
  class TheRockUploadHandler extends UploadHandler {
    protected function handle_file_upload($uploaded_file, $name, $size, $type, $error, $index = null, $content_range = null) {
      $file = parent::handle_file_upload($uploaded_file, $name, $size, $type, $error, $index, $content_range);

      if(empty($file->error)) {
        $file->deleteUrl = preg_replace("/\/index\.php\?file\=/", "/". CONFIG\S3_UPLOAD_URL ."/", $file->deleteUrl);

        if(empty($file->thumbnailUrl)) {
          $fileData = [
            "\"deleteType\"" => $file->deleteType,
            "\"deleteUrl\"" => $file->deleteUrl,
            "name" => $file->name,
            "size" => $file->size,
            "type" => $file->type,
            "url" => $file->url
          ];
        }

        else {
          $fileData = [
            "\"deleteType\"" => $file->deleteType,
            "\"deleteUrl\"" => $file->deleteUrl,
            "name" => $file->name,
            "size" => $file->size,
            "\"thumbnailUrl\"" => $file->thumbnailUrl,
            "type" => $file->type,
            "url" => $file->url
          ];
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
