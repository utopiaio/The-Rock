<?php
  class Moedoo {
    /**
     * returns a FK reference
     *
     * @param string $table
     * @param array $rows
     * @param array $depth
     * @return array
     */
    private static function referenceFk($table, $rows, &$depth = 1) {
      if($depth > 0 || $depth === -1) {
        if($depth > 0) {
          $depth--;
        }

        $referenced = []; // caches referenced values so db hit is minimal

        if(array_key_exists("fk", CONFIG\TABLES[$table]) === true) {
          foreach(CONFIG\TABLES[$table]["fk"] as $column => $referenceRule) {
            foreach($rows as $index => &$row) {
              if($row[$column] !== null) {
                if(array_key_exists($row[$column], $referenced) === true) {
                  $row[$column] = $referenced[$row[$column]];
                }

                else {
                  $referencedRow = Moedoo::select($referenceRule["table"], [$referenceRule["references"] => $row[$column]], null, $depth);

                  if(count($referencedRow) === 1) {
                    $referenced[$row[$column]] = $referencedRow[0];
                    $row[$column] = $referencedRow[0];
                  }

                  else {
                    $referenced[$row[$column]] = null;
                    $row[$column] = null;
                  }
                }
              }
            }
          }
        }

        if(array_key_exists("map", CONFIG\TABLES[$table]) === true) {
          $mapped = []; // caches referenced values so db hit is minimal

          foreach($rows as $index => &$row) {
            $map = [];

            foreach(CONFIG\TABLES[$table]["map"] as $column => $rule) {
              // O^3 --- there's no turning back now
              // -1 is used as a flag to skip mapping
              // i.e. non existing references will simply "vanish" --- now that's
              // what i call INTEGRITY
              foreach($row[$column] as $index => $value) {
                if(array_key_exists($value, $mapped) === true) {
                  if($mapped[$value] !== -1) {
                    array_push($map, $mapped[$value]);
                  }
                }

                else {
                  $referencedRow = Moedoo::select($rule["table"], [$rule["references"] => $value], null, $depth);

                  if(count($referencedRow) === 1) {
                    $mapped[$value] = $referencedRow[0];
                    array_push($map, $referencedRow[0]);
                  }

                  else {
                    $mapped[$value] = -1;
                  }
                }
              }

              $row[$column] = $map;
            }
          }
        }
      }

      return $rows;
    }



    /**
     * given an array it'll cast accordingly so that the db operation can
     * be done without a glitch --- fingers crossed
     *
     * @param string $table - table on which to apply the casting for
     * @param array $data - data to be prepared for db operation
     * @return array - database operation ready data
     */
    private static function castForPg($table, $data) {
      foreach($data as $column => &$value) {
        if(array_key_exists("JSON", CONFIG\TABLES[$table]) === true && in_array($column, CONFIG\TABLES[$table]["JSON"]) === true) {
          $value = json_encode($value);
        }

        if(array_key_exists("bool", CONFIG\TABLES[$table]) === true && in_array($column, CONFIG\TABLES[$table]["bool"]) === true) {
          $value = $value === true ? "TRUE" : "FALSE";
        }

        if( (array_key_exists("intArray", CONFIG\TABLES[$table]) === true && in_array($column, CONFIG\TABLES[$table]["intArray"]) === true) ||
            (array_key_exists("floatArray", CONFIG\TABLES[$table]) === true && in_array($column, CONFIG\TABLES[$table]["floatArray"]) === true) ||
            (array_key_exists("doubleArray", CONFIG\TABLES[$table]) === true && in_array($column, CONFIG\TABLES[$table]["doubleArray"]) === true) ) {
          $value = "{". implode(",", $value) ."}";
        }
      }

      return $data;
    }



    /**
     * given an array of rows straight out of pg it'll cast the appropriate
     * type according to `config`
     *
     * @param string $table - table on which to apply the casting
     * @param array $rows
     * @return array
     */
    public static function cast($table, $rows) {
      foreach($rows as $index => &$row) {
        foreach($row as $column => &$value) {
          if(array_key_exists("JSON", CONFIG\TABLES[$table]) === true && in_array($column, CONFIG\TABLES[$table]["JSON"]) === true) {
            $value = json_decode($value);
          }

          if(array_key_exists("int", CONFIG\TABLES[$table]) === true && in_array($column, CONFIG\TABLES[$table]["int"]) === true) {
            $value = (int)$value;
          }

          if(array_key_exists("float", CONFIG\TABLES[$table]) === true && in_array($column, CONFIG\TABLES[$table]["float"]) === true) {
            $value = (float)$value;
          }

          if(array_key_exists("double", CONFIG\TABLES[$table]) === true && in_array($column, CONFIG\TABLES[$table]["double"]) === true) {
            $value = (double)$value;
          }

          if(array_key_exists("bool", CONFIG\TABLES[$table]) === true && in_array($column, CONFIG\TABLES[$table]["bool"]) === true) {
            $value = $value === "t" ? true : false;
          }

          // for now (and probably forever) we can only work with 1D arrays
          // since we'll have PG version 8 we can't use JSON :(
          if(array_key_exists("intArray", CONFIG\TABLES[$table]) === true && in_array($column, CONFIG\TABLES[$table]["intArray"]) === true) {
            $value = trim($value, "{}");
            $value = $value === "" ? [] : explode(",", $value);
            foreach($value as $index => &$v) {
              $v = (int)$v;
            }
          }

          if(array_key_exists("floatArray", CONFIG\TABLES[$table]) === true && in_array($column, CONFIG\TABLES[$table]["floatArray"]) === true) {
            $value = trim($value, "{}");
            $value = $value === "" ? [] : explode(",", $value);
            foreach($value as $index => &$v) {
              $v = (float)$v;
            }
          }

          if(array_key_exists("doubleArray", CONFIG\TABLES[$table]) === true && in_array($column, CONFIG\TABLES[$table]["doubleArray"]) === true) {
            $value = trim($value, "{}");
            $value = $value === "" ? [] : explode(",", $value);
            foreach($value as $index => &$v) {
              $v = (double)$v;
            }
          }
        }
      }

      return $rows;
    }



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
     * returns matching rows against query
     *
     * @param string $table
     * @param string $query
     */
    public static function search($table, $q, $depth = 1) {
      $columns = implode(", ", CONFIG\TABLES[$table]["returning"]);
      $q = preg_replace("/ +/", "|", trim($q));
      $q = preg_replace("/ /", "|", $q);
      $params = [$q];
      $query = "SELECT {$columns} FROM ". CONFIG\TABLE_PREFIX ."{$table} WHERE ";
      $where = "";
      $order_by = "ORDER BY";

      // building vector...
      foreach(CONFIG\TABLES[$table]["search"] as $key => $value) {
        $where .= "to_tsvector({$value}) @@ to_tsquery($1) OR ";
      }
      $where = substr($where, 0, -4);

      // building rank...
      foreach(CONFIG\TABLES[$table]["search"] as $key => $value) {
        $order_by .= " ts_rank(to_tsvector($value), to_tsquery($1)) DESC, ";
      }
      $order_by = substr($order_by, 0, -2);

      $result = pg_query_params("{$query} {$where} {$order_by};", $params);

      if(pg_affected_rows($result) === 0) {
        return [];
      }

      else {
        $rows = Moedoo::cast($table, pg_fetch_all($result));
        $rows = Moedoo::referenceFk($table, $rows, $depth);
        return $rows;
      }
    }



    /**
     * executes SELECT command on a given table
     *
     * @param string $table - table to operate select command on
     * @param array $and - concatenated with `AND`
     * @param array $or - concatenated with `OR`
     * @param array $depth - -1 implies FULL depth [not recommended]
     * @return affected rows or null if an error occurred
     */
    public static function select($table, $and = null, $or = null, &$depth = 1) {
      $columns = implode(", ", CONFIG\TABLES[$table]["returning"]);
      $query = "SELECT {$columns} FROM ". CONFIG\TABLE_PREFIX ."{$table}";
      $params = [];

      if($and === null && $or === null) {
        $query .= " ORDER BY ". CONFIG\TABLES[$table]["pk"] ." DESC;";
      }

      else {
        $query .= " WHERE";

        if($and !== null) {
          $query .= " (";

          foreach($and as $column => $value) {
            array_push($params, $value);
            $query .= "{$column}=$".count($params)." AND ";
          }

          $query = substr($query, 0, -5);
          $query .= ")";

          if($or !== null) {
            $query .= " AND";
          }
        }

        if($or !== null) {
          $query .= " (";

          foreach($or as $column => $value) {
            array_push($params, $value);
            $query .= "{$column}=$".count($params)." OR ";
          }

          $query = substr($query, 0, -4);
          $query .= ")";
        }

        $query .= " ORDER BY ". CONFIG\TABLES[$table]["pk"] ." DESC;";
      }

      $result = pg_query_params($query, $params);

      if(pg_affected_rows($result) === 0) {
        return [];
      }

      else {
        $rows = Moedoo::cast($table, pg_fetch_all($result));
        $rows = Moedoo::referenceFk($table, $rows, $depth);
        return $rows;
      }
    }



    /**
     * executes INSERT command on a given table - one at a time
     *
     * EXCEPTION CODES
     * 1: unable to save for unknown[s] reason
     * 2: duplicate constraint
     * 3: foreign key constraint
     *
     * @param string $table - table name without prefix
     * @param array $data - data to be inserted
     * @return array - the newly inserted row
     */
    public static function insert($table, $data, $depth = 1) {
      $data = Moedoo::castForPg($table, $data);
      $count = 1;
      $columns = [];
      $holders = []; // ${$index}
      $params = [];

      foreach($data as $column => $value) {
        array_push($columns, $column);
        array_push($holders, "\${$count}");
        array_push($params, $value);
        $count++;
      }

      $columns = implode(", ", $columns);
      $holders = implode(", ", $holders);
      $returning = implode(", ", CONFIG\TABLES[$table]["returning"]);

      $query = "INSERT INTO ". CONFIG\TABLE_PREFIX ."{$table} ({$columns}) VALUES ({$holders}) RETURNING {$returning};";

      try {
        $result = pg_query_params($query, $params);

        if(pg_affected_rows($result) === 1) {
          $rows = Moedoo::cast($table, pg_fetch_all($result));
          $rows = Moedoo::referenceFk($table, $rows, $depth);
          return $rows[0];
        }

        else {
          throw new Exception("unable to save `". $table ."`", 1);
        }
      } catch(Exception $e) {
        $errorMessage = $e->getMessage();

        if(preg_match("/duplicate/", $errorMessage) === 1) {
          throw new Exception("request violets duplicate constant", 2);
        }

        else if(preg_match("/foreign key/", $errorMessage) === 1) {
          throw new Exception("request violets foreign key constraint", 3);
        }

        else {
          throw new Exception($errorMessage, $e->getCode());
        }
      }
    }



    /**
     * executes UPDATE on a given tale entry
     *
     * EXCEPTION CODES
     * 1: unable to update for unknown reason[s]
     * 2: duplicate constraint
     * 3: foreign key constraint
     *
     * @param string $table
     * @param array $data - data which to replace on
     * @return array | null
     */
    public static function update($table, $data, $id, $depth = 1) {
      $data = Moedoo::castForPg($table, $data);
      $count = 1;
      $set = [];
      $params = [];
      $columns = implode(", ", CONFIG\TABLES[$table]["returning"]);

      foreach($data as $column => $value) {
        array_push($set, $column."=\${$count}");
        array_push($params, $value);
        $count++;
      }

      $set = implode(", ", $set);
      array_push($params, $id);

      $query = "UPDATE ". CONFIG\TABLE_PREFIX ."{$table} SET {$set} WHERE ". CONFIG\TABLES[$table]["pk"] ."=\${$count} RETURNING {$columns};";

      try {
        $result = pg_query_params($query, $params);

        // nothing was affected
        if(pg_affected_rows($result) === 0) {
          throw new Exception("unable to update `". $table ."` with resource id `". $id ."`", 1);
        }

        // everything went as expected
        else if(pg_affected_rows($result) === 1) {
          $rows = Moedoo::cast($table, pg_fetch_all($result));
          $rows = Moedoo::referenceFk($table, $rows, $depth);
          return $rows[0];
        }
      } catch(Exception $e) {
        $errorMessage = $e->getMessage();

        if(preg_match("/duplicate/", $errorMessage) === 1) {
          throw new Exception("request violets duplicate constant", 2);
        }

        else if(preg_match("/foreign key/", $errorMessage) === 1) {
          throw new Exception("request violets foreign key constraint", 3);
        }

        else {
          throw new Exception($errorMessage, $e->getCode());
        }
      }
    }



    /**
     * executes DELTE command on a given entry
     * i didn't want to do this, but...
     * there comes a time were you want to just delete
     *
     * EXCEPTION CODES
     * 1: unable to delete because the resource doesn't exist in the first place
     * 3: foreign key constraint
     *
     * @param string $table
     * @param integer $id - id on which to delete on
     * @return array - deleted row
     */
    public static function delete($table, $id) {
      $params = [$id];
      $columns = implode(", ", CONFIG\TABLES[$table]["returning"]);

      $query = "DELETE FROM ". CONFIG\TABLE_PREFIX ."{$table} WHERE ". CONFIG\TABLES[$table]["pk"] ."=$1 RETURNING {$columns};";

      try {
        $result = pg_query_params($query, $params);

        // object requested to be deleted doesn't exist in table
        if(pg_affected_rows($result) === 0) {
          throw new Exception("`". $table ."` with resource id `". $id ."` does not exist", 1);
        }

        // everything went as expected
        else if(pg_affected_rows($result) === 1) {
          $rows = Moedoo::cast($table, pg_fetch_all($result));
          return $rows[0];
        }
      } catch(Exception $e) {
        $errorMessage = $e->getMessage();

        if(preg_match("/foreign key/", $errorMessage) === 1) {
          throw new Exception("request violets foreign key constraint", 3);
        }

        else {
          throw new Exception($errorMessage, $e->getCode());
        }
      }
    }
  }
?>
