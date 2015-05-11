<?php
  class Moedoo {
    /**
     * instantiates db connection
     *
     * @param string $host
     * @param string $port
     * @param string $user
     * @param string $password
     * @param string $dbname
     * @param string $host
     * @return connection resource
     */
    public static function db($host, $port, $user, $password, $dbname) {
      return pg_pconnect("host={$host} port={$port} user={$user} password={$password} dbname={$dbname}");
    }



    /**
     * executes SELECT command on a given table
     * if given an $id it'll return that object, if not it'll return all entries
     *
     * @param string $table
     * @param integer $id - id on which to select on
     */
    public static function select($table, $id = -1) {
      $columns = implode(", ", \Config\TABLES[$table]["RETURNING"]);
      $params = $id === -1 ? [] : [$id];

      $query = "SELECT {$columns} FROM ". \Config\TABLE_PREFIX ."{$table}";
      $query .= $id === -1 ? "" : " WHERE ". \Config\TABLES[$table]["pk"] ."=$1";
      $query .= " ORDER BY ". \Config\TABLES[$table]["pk"] ." DESC;";
      $result = pg_query_params($query, $params);

      // table is empty, empty array will be returned
      if(pg_affected_rows($result) === 0 && $id === -1) {
        Util::JSON([], 200);
      }

      // requested object is not found
      else if(pg_affected_rows($result) === 0 && $id !== -1) {
        Util::JSON(["error" => "object not found"], 404);
      }

      // single row requested found
      else if(pg_affected_rows($result) === 1 && $id !== -1) {
        $row = pg_fetch_all($result)[0];

        foreach($row as $column => $value) {
          // JSON string -> array...
          if(in_array($column, \Config\TABLES[$table]["JSON"]) === true) {
            $row[$column] = json_decode($value);
          }

          // integer string -> integer...
          if(in_array($column, \Config\TABLES[$table]["int"]) === true) {
            $row[$column] = (int)$value;
          }
        }

        Util::JSON($row, 200);
      }

      // all is good
      else {
        $rows = pg_fetch_all($result);

        foreach($rows as $index => $row) {
          foreach($row as $column => $value) {
            // JSON string -> array...
            if(in_array($column, \Config\TABLES[$table]["JSON"]) === true) {
              $rows[$index][$column] = json_decode($value);
            }

            // integer string -> integer...
            if(in_array($column, \Config\TABLES[$table]["int"]) === true) {
              $rows[$index][$column] = (int)$value;
            }
          }
        }

        Util::JSON($rows, 200);
      }
    }



    /**
     * executes INSERT command on a given table - one at a time
     *
     * @param string $table - table name without prefix
     * @param array $data - data to be inserted
     */
    public static function insert($table, $data) {
      Util::validatePayload($table, $data);

      $count = 1;
      $columns = [];
      $holders = []; // ${$index}
      $params = [];

      foreach($data as $column => $value) {
        // array -> string...
        if(in_array($column, \Config\TABLES[$table]["JSON"]) === true) {
          $value = json_encode($value);
        }

        array_push($columns, $column);
        array_push($holders, "\${$count}");
        array_push($params, $value);
        $count++;
      }

      $columns = implode(", ", $columns);
      $holders = implode(", ", $holders);
      $returning = implode(", ", \Config\TABLES[$table]["RETURNING"]);

      $query = "INSERT INTO ". \Config\TABLE_PREFIX ."{$table} ({$columns}) VALUES ({$holders}) RETURNING {$returning};";
      $result = pg_query_params($query, $params);
      $row = pg_fetch_all($result)[0];

      foreach($row as $column => $value) {
        // JSON string -> array...
        if(in_array($column, \Config\TABLES[$table]["JSON"]) === true) {
          $row[$column] = json_decode($value);
        }

        // integer string -> integer...
        if(in_array($column, \Config\TABLES[$table]["int"]) === true) {
          $row[$column] = (int)$value;
        }
      }

      Util::JSON($row, 202);
    }



    /**
     * executes UPDATE on a given tale entry
     *
     * @param string $table
     * @param array $newData - data which to replace on
     */
    public static function update($table, $newData, $id) {
      Util::validatePayload($table, $newData);

      $count = 1;
      $set = [];
      $params = [];
      $columns = implode(", ", \Config\TABLES[$table]["RETURNING"]);

      foreach($newData as $column => $value) {
        // array -> string...
        if(in_array($column, \Config\TABLES[$table]["JSON"]) === true) {
          $value = json_encode($value);
        }

        array_push($set, $column."=\${$count}");
        array_push($params, $value);
        $count++;
      }

      $set = implode(", ", $set);
      array_push($params, $id);

      $query = "UPDATE ". \Config\TABLE_PREFIX ."{$table} SET {$set} WHERE ". \Config\TABLES[$table]["pk"] ."=\${$count} RETURNING {$columns};";
      $result = pg_query_params($query, $params);

      // nothing was affected
      if(pg_affected_rows($result) === 0) {
        Util::JSON(["error" => "object not found"], 404);
      }

      // everything went as expected
      else if(pg_affected_rows($result) === 1) {
        $row = pg_fetch_all($result)[0];

        foreach($row as $column => $value) {
          // JSON string -> array...
          if(in_array($column, \Config\TABLES[$table]["JSON"]) === true) {
            $row[$column] = json_decode($value);
          }

          // integer string -> integer...
          if(in_array($column, \Config\TABLES[$table]["int"]) === true) {
            $row[$column] = (int)$value;
          }
        }

        Util::JSON($row, 202);
      }

      // something horrible has happened
      else {
        Util::JSON(["error" => "ouch, that hurt"], 500);
      }
    }



    /**
     * executes DELTE command on a given entry
     * i didn't want to do this, but...
     * there comes a time were you want to just delete
     *
     * @param string $table
     * @param integer $id - id on which to delete on
     */
    public static function delete($table, $id) {
      $params = [$id];
      $columns = implode(", ", \Config\TABLES[$table]["RETURNING"]);

      $query = "DELETE FROM ". \Config\TABLE_PREFIX ."{$table} WHERE ". \Config\TABLES[$table]["pk"] ."=$1 RETURNING {$columns};";
      $result = pg_query_params($query, $params);

      // object requested to be deleted doesn't exist in table
      if(pg_affected_rows($result) === 0) {
        Util::JSON(["error" => "object not found"], 404);
      }

      // everything went as expected
      else if(pg_affected_rows($result) === 1) {
        $row = pg_fetch_all($result)[0];

        foreach($row as $column => $value) {
          // JSON string -> array...
          if(in_array($column, \Config\TABLES[$table]["JSON"]) === true) {
            $row[$column] = json_decode($value);
          }

          // integer string -> integer...
          if(in_array($column, \Config\TABLES[$table]["int"]) === true) {
            $row[$column] = json_decode($value);
          }
        }

        Util::JSON($row, 200);
      }

      // something horrible has happened
      else {
        Util::JSON(["error" => "ouch, that hurt"], 500);
      }
    }
  }
?>
