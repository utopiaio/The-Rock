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

        if(array_key_exists("fk", Config::get("TABLES")[$table]) === true) {
          $cache = [];
          $tempDepth = $depth;

          foreach(Config::get("TABLES")[$table]["fk"] as $column => $referenceRule) {
            if(preg_match("/^\[.+\]$/", $column) === 1) {
              $column = trim($column, "[]");

              foreach($rows as $index => &$row) {
                $map = [];

                foreach($row[$column] as $index => $value) {
                  if(array_key_exists("{$referenceRule["table"]}_{$referenceRule["references"]}_{$value}", $cache) === true) {
                    if(is_null($cache["{$referenceRule["table"]}_{$referenceRule["references"]}_{$value}"]) === false) {
                      array_push($map, $cache["{$referenceRule["table"]}_{$referenceRule["references"]}_{$value}"]);
                    }
                  }

                  else {
                    $illBeBack = $depth;
                    $referencedRow = Moedoo::select($referenceRule["table"], [$referenceRule["references"] => $value], null, $depth);
                    $depth = $illBeBack;

                    if(count($referencedRow) === 1) {
                      $cache["{$referenceRule["table"]}_{$referenceRule["references"]}_{$value}"] = $referencedRow[0];
                      array_push($map, $referencedRow[0]);
                    }

                    else {
                      $cache["{$referenceRule["table"]}_{$referenceRule["references"]}_{$value}"] = null;
                    }
                  }
                }

                $row[$column] = $map;
              }
            }

            else {
              foreach($rows as $index => &$row) {
                if(array_key_exists("{$referenceRule["table"]}_{$referenceRule["references"]}_{$row[$column]}", $cache) === true) {
                  if(is_null($cache["{$referenceRule["table"]}_{$referenceRule["references"]}_{$row[$column]}"]) === false) {
                    $row[$column] = $cache["{$referenceRule["table"]}_{$referenceRule["references"]}_{$row[$column]}"];
                  }
                }

                else {
                  $illBeBack = $depth;
                  $referencedRow = Moedoo::select($referenceRule["table"], [$referenceRule["references"] => $row[$column]], null, $depth);
                  $depth = $illBeBack;

                  if(count($referencedRow) === 1) {
                    $cache["{$referenceRule["table"]}_{$referenceRule["references"]}_{$row[$column]}"] = $referencedRow[0];
                    $row[$column] = $referencedRow[0];
                  }

                  else {
                    $cache["{$referenceRule["table"]}_{$referenceRule["references"]}_{$row[$column]}"] = null;
                  }
                }
              }
            }
          }
        }
      }

      return $rows;
    }



    /**
     * builds a returning table syntax
     *
     * this is to be used for casting special columns like geometry
     *
     * @param string $table
     * @return array
     */
    public static function buildReturn($table) {
      $build = "";

      foreach(Config::get("TABLES")[$table]["returning"] as $index => $column) {
        if(array_key_exists("geometry", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["geometry"]) === true) {
          $build .= "ST_AsGeoJSON({$column}) as {$column}, ";
        } else {
          $build .= "{$column}, ";
        }
      }

      return substr($build, 0, -2);
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
        if(array_key_exists("JSON", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["JSON"]) === true) {
          $value = json_encode($value);
        }

        if(array_key_exists("bool", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["bool"]) === true) {
          $value = $value === true ? "TRUE" : "FALSE";
        }

        if(array_key_exists("geometry", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["geometry"]) === true) {
          $value = json_encode($value);
        }

        if( (array_key_exists("[int]", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["[int]"]) === true) ||
            (array_key_exists("[float]", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["[float]"]) === true) ||
            (array_key_exists("[double]", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["[double]"]) === true) ) {
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
          if(array_key_exists("JSON", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["JSON"]) === true) {
            $value = json_decode($value);
          }

          if(array_key_exists("geometry", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["geometry"]) === true) {
            $value = json_decode($value);
          }

          if(array_key_exists("int", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["int"]) === true) {
            $value = (int)$value;
          }

          if(array_key_exists("float", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["float"]) === true) {
            $value = (float)$value;
          }

          if(array_key_exists("double", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["double"]) === true) {
            $value = (double)$value;
          }

          if(array_key_exists("bool", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["bool"]) === true) {
            $value = $value === "t" ? true : false;
          }

          // for now (and probably forever) we can only work with 1D arrays
          // since we'll have PG version 8 we can't use JSON :(
          if(array_key_exists("[int]", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["[int]"]) === true) {
            $value = trim($value, "{}");
            $value = $value === "" ? [] : explode(",", $value);
            foreach($value as $index => &$v) {
              $v = (int)$v;
            }
          }

          if(array_key_exists("[float]", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["[float]"]) === true) {
            $value = trim($value, "{}");
            $value = $value === "" ? [] : explode(",", $value);
            foreach($value as $index => &$v) {
              $v = (float)$v;
            }
          }

          if(array_key_exists("[double]", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["[double]"]) === true) {
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
     * query executor. will catch all exceptions and return a fancy error
     *
     * EXCEPTION CODES
     * 1: unable to update for unknown reason[s]
     * 2: duplicate constraint
     * 3: foreign key constraint
     *
     * @param  string $table  table on which the query is to be executed on
     * @param  string $query  query to be executed
     * @param  array  $params placeholders for query
     * @return array result
     */
    public static function executeQuery($table, $query, $params, $depth = 1) {
      $dbConnection = Moedoo::db(Config::get("DB_HOST"), Config::get("DB_PORT"), Config::get("DB_USER"), Config::get("DB_PASSWORD"), Config::get("DB_NAME"));

      if(pg_send_query_params($dbConnection, $query, $params)) {
        $resource = pg_get_result($dbConnection);
        $state = pg_result_error_field($resource, PGSQL_DIAG_SQLSTATE);

        if($state == 0 && pg_fetch_all($resource) !== false) {
          $rows = Moedoo::cast($table, pg_fetch_all($resource));
          $rows = Moedoo::referenceFk($table, $rows, $depth);
          return $rows;
        } else {
          switch($state) {
            // duplicate
            case "23505":
              throw new Exception("request violets duplicate constraint", 2);
            break;

            // foreign key
            case "23503":
              throw new Exception("request violets foreign key constraint", 3);
            break;

            default:
              $queryInsert = preg_match("/^INSERT INTO/", $query);
              $queryUpdate = preg_match("/^UPDATE/", $query);

              // we won't be giving detailed error in order "protect" the system
              if($queryInsert === 1) {
                throw new Exception("unable to save `". $table ."`", 1);
              } else if ($queryUpdate === 1) {
                throw new Exception("unable to update `". $table ."`", 1);
              } else {
                throw new Exception("error processing query", 1);
              }
            break;
          }
        }
      } else {
        throw new Exception("unable to save `". $table ."`", 1);
      }
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
      $dbConnection = pg_pconnect("host={$host} port={$port} user={$user} password={$password} dbname={$dbname}");
      if($dbConnection === false) {
        Rock::halt(500, "unable to connect to database");
      }

      return $dbConnection;
    }



    /**
     * returns matching rows against query
     *
     * @param string $table
     * @param string $query
     * @param string $limit
     * @param integer $depth
     */
    public static function search($table, $q, $limit = "ALL", $depth = 1) {
      $columns = Moedoo::buildReturn($table);
      $q = preg_replace("/ +/", "|", trim($q));
      $q = preg_replace("/ /", "|", $q);
      $params = [$q];
      $query = "SELECT {$columns} FROM ". Config::get("TABLE_PREFIX") ."{$table} WHERE ";
      $where = "";
      $order_by = "ORDER BY";

      // building vector...
      foreach(Config::get("TABLES")[$table]["search"] as $key => $value) {
        $where .= "to_tsvector({$value}) @@ to_tsquery($1) OR ";
      }

      $where = substr($where, 0, -4);

      // building rank...
      foreach(Config::get("TABLES")[$table]["search"] as $key => $value) {
        $order_by .= " ts_rank(to_tsvector($value), to_tsquery($1)) DESC, ";
      }

      $order_by = substr($order_by, 0, -2);
      $limit = "LIMIT {$limit}";
      $result = pg_query_params("{$query} {$where} {$order_by} {$limit};", $params);

      if(pg_fetch_all($result) === false) {
        return [];
      }

      else {
        $rows = Moedoo::cast($table, pg_fetch_all($result));
        $rows = Moedoo::referenceFk($table, $rows, $depth);
        return $rows;
      }
    }



    /**
     * returns row count on a table
     *
     * @param string $table
     * @return integer
     */
    public static function count($table) {
      $query = "SELECT count(". Config::get("TABLES")[$table]["pk"] .") as count FROM ". Config::get("TABLE_PREFIX") ."{$table};";
      $params = [];
      $result = pg_query_params($query, $params);

      if(pg_fetch_all($result) === false) {
        $count = 0;
      } else {
        $count = (int)pg_fetch_all($result)[0]["count"];
      }

      return $count;
    }



    /**
     * executes SELECT command on a given table
     *
     * @param string $table - table to operate select command on
     * @param array $and - concatenated with `AND`
     * @param array $or - concatenated with `OR`
     * @param array $depth - -1 implies FULL depth [not recommended]
     * @param integer $limit - limit on row limit
     * @param integer $offset - offset on query
     * @return affected rows or null if an error occurred
     */
    public static function select($table, $and = null, $or = null, &$depth = 1, $limit = "ALL", $offset = 0) {
      $columns = Moedoo::buildReturn($table);
      $query = "SELECT {$columns} FROM ". Config::get("TABLE_PREFIX") ."{$table}";
      $params = [];

      if($and === null && $or === null) {
        $query .= " ORDER BY ". Config::get("TABLES")[$table]["pk"] ." DESC ";
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

        $query .= " ORDER BY ". Config::get("TABLES")[$table]["pk"] ." DESC ";
      }

      $query .= "LIMIT {$limit} OFFSET {$offset};";
      $result = pg_query_params($query, $params);

      if(pg_fetch_all($result) === false) {
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

        if(array_key_exists("geometry", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["geometry"]) === true) {
          array_push($holders, "ST_GeomFromGeoJSON(\${$count})");
        }

        else {
          array_push($holders, "\${$count}");
        }

        array_push($params, $value);
        $count++;
      }

      $columns = implode(", ", $columns);
      $holders = implode(", ", $holders);
      $returning = Moedoo::buildReturn($table);

      $query = "INSERT INTO ". Config::get("TABLE_PREFIX") ."{$table} ({$columns}) VALUES ({$holders}) RETURNING {$returning};";
      return Moedoo::executeQuery($table, $query, $params, $depth)[0];
    }



    /**
     * executes UPDATE on a given tale entry
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
      $columns = Moedoo::buildReturn($table);

      foreach($data as $column => $value) {
        if(array_key_exists("geometry", Config::get("TABLES")[$table]) === true && in_array($column, Config::get("TABLES")[$table]["geometry"]) === true) {
          array_push($set, $column."=ST_GeomFromGeoJSON(\${$count})");
        }

        else {
          array_push($set, $column."=\${$count}");
        }

        array_push($params, $value);
        $count++;
      }

      $set = implode(", ", $set);
      array_push($params, $id);

      $query = "UPDATE ". Config::get("TABLE_PREFIX") ."{$table} SET {$set} WHERE ". Config::get("TABLES")[$table]["pk"] ."=\${$count} RETURNING {$columns};";
      return Moedoo::executeQuery($table, $query, $params, $depth)[0];
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
      $columns = Moedoo::buildReturn($table);

      $query = "DELETE FROM ". Config::get("TABLE_PREFIX") ."{$table} WHERE ". Config::get("TABLES")[$table]["pk"] ."=$1 RETURNING {$columns};";
      return Moedoo::executeQuery($table, $query, $params)[0];
    }
  }
?>
