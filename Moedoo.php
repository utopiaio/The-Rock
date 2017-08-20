<?php
  class Moedoo {
    // this will hold all the table names that will be be cached for depth references
    private static $MAPPER = [];
    // this will hold table rows with ['tableName' => ['id' => 'row']] structure
    // this will sacrifice the memory in-order to gain much needed performance boost
    // on larger depth requests (especially with queries)
    private static $CACHE_MAP = [];
    private static $db = null;

    /**
     * cache builder
     * [ [table] => [ columnId => row ] ]
     *
     * @param String $table
     * @param String $columnId
     * @param Array $CACHE_MAP
     * @return Array
     */
    public static function CACHE_BUILDER($table, $columnId, &$CACHE_MAP) {
      if (isset($CACHE_MAP[$table]) === false) {
        $CACHE_MAP[$table] = [];

        $columns = Moedoo::buildReturn($table);
        $query = "SELECT {$columns} FROM {$table};";
        $includeRows = Moedoo::executeQuery($table, $query, []);
        $includeRows = Moedoo::cast($table, $includeRows);

        foreach ($includeRows as $index => $includeRow) {
          $CACHE_MAP[$table][$includeRow[$columnId]] = $includeRow;
        }
      }

      return $CACHE_MAP;
    }

    /**
     * MAPPER - recursive builder for `CACHE_BUILDER`
     * goes through the $table's `fk` rules according to depth and builds
     * [tableName => tableId] for `CACHE_BUILDER` to build
     *
     * Mapped FK rule types:
     * [col]
     * {col}
     *
     * @param String $table
     * @param Integer &$depth
     * @param Array &$MAPPER
     */
    public static function MAPPER($table, &$depth, &$MAPPER) {
      if ($depth-- > 0 && isset(Config::get('TABLES')[$table]['fk']) === true) {
        foreach (Config::get('TABLES')[$table]['fk'] as $column => $referenceRule) {
          if (preg_match('/^\[.+\]$/', $column) === 1) {
            $MAPPER[$referenceRule['table']] = $referenceRule['references'];
            $illBeBack = $depth;
            Moedoo::MAPPER($referenceRule['table'], $depth, $MAPPER);
            $depth = $illBeBack; // this makes sure every rules gets the same depth on a go
          } else if (preg_match('/^\{.+\}$/', $column) === 1) {
            $MAPPER[$referenceRule['table']] = Config::get('TABLES')[$referenceRule['table']]['pk'];
            $illBeBack = $depth;
            Moedoo::MAPPER($referenceRule['table'], $depth, $MAPPER);
            $depth = $illBeBack;
          }
        }
      }

      return $MAPPER;
    }

    /**
     * builds fk iterating over CACHE_MAP
     *
     * @param String $tFK
     * @param Array $rFK
     * @param Integer &$dFK
     * @param Array $CACHE_MAP
     * @return Array
     */
    public static function FK($tFK, $rFK, &$dFK, $CACHE_MAP) {
      if ($dFK-- >= 0) {
        if (isset(Config::get('TABLES')[$tFK]['fk']) === true) {
          foreach (Config::get('TABLES')[$tFK]['fk'] as $column => $referenceRule) {
            // [col_name]
            if (preg_match('/^\[.+\]$/', $column) === 1) {
              $column = trim($column, '[]');
              $fkMapped = [];

              foreach ($rFK[$column] as $j => $fkId) {
                if (isset($CACHE_MAP[$referenceRule['table']][$fkId]) === true) {
                  $d = $dFK - 1;
                  array_push($fkMapped, Moedoo::FK($referenceRule['table'], $CACHE_MAP[$referenceRule['table']][$fkId], $d, $CACHE_MAP));
                }
              }

              $rFK[$column] = $fkMapped;
            }

            // {col_name}
            else if (preg_match('/^\{.+\}$/', $column) === 1) {
              $column = trim($column, '{}');
              $rFK[Config::get('REFERENCE_KEY')][$column] = [];

              // reverse fk []
              if (isset(Config::get('TABLES')[$referenceRule['table']]['[int]']) && in_array($referenceRule['referencing_column'], Config::get('TABLES')[$referenceRule['table']]['[int]'])) {
                foreach ($CACHE_MAP[$referenceRule['table']] as $id => $rRow) {
                  if (in_array($rFK[$referenceRule['referenced_by']], $rRow[$referenceRule['referencing_column']]) === true) {
                    $d = $dFK - 1;
                    array_push($rFK[Config::get('REFERENCE_KEY')][$column], Moedoo::FK($referenceRule['table'], $rRow, $d, $CACHE_MAP));
                  }
                }
              }

              // single reverse fk
              else if (isset(Config::get('TABLES')[$referenceRule['table']]['int']) && in_array($referenceRule['referencing_column'], Config::get('TABLES')[$referenceRule['table']]['int'])) {
                foreach ($CACHE_MAP[$referenceRule['table']] as $id => $rRow) {
                  if ($rRow[$referenceRule['referencing_column']] === $rFK[$referenceRule['referenced_by']]) {
                    $d = $dFK - 1;
                    array_push($rFK[Config::get('REFERENCE_KEY')][$column], Moedoo::FK($referenceRule['table'], $rRow, $d, $CACHE_MAP));
                  }
                }
              }
            }

            // col_name
            else if (preg_match('/^[a-z]+/', $column) === 1 && isset($CACHE_MAP[$referenceRule['table']][$rFK[$column]]) === true) {
              $d = $dFK - 1;
              $rFK[$column] = Moedoo::FK($referenceRule['table'], $CACHE_MAP[$referenceRule['table']][$rFK[$column]], $d, $CACHE_MAP);
            }
          }
        }
      }

      return $rFK;
    }

    /**
     * returns a FK reference
     *
     * @param string $table
     * @param array $rows
     * @param array $depth
     * @return array
     */
    public static function referenceFk($table, $rows, &$depth = 1) {
      // fk rules exist for the table
      if (isset(Config::get('TABLES')[$table]['fk']) === true) {
        if ($depth > 0) {
          Moedoo::MAPPER($table, $depth, Moedoo::$MAPPER);

          // passing to cache builder --------------------------------------------------------------
          foreach (Moedoo::$MAPPER as $t => $id) {
            Moedoo::CACHE_BUILDER($t, $id, Moedoo::$CACHE_MAP);
          }
          // ./ passing to cache builder -----------------------------------------------------------

          // mapping -------------------------------------------------------------------------------
          foreach ($rows as $i => &$row) {
            if (isset(Config::get('TABLES')[$table]['fk']) === true) {
              foreach (Config::get('TABLES')[$table]['fk'] as $column => $referenceRule) {
                if (preg_match('/^[a-z]+/', $column) === 1 && isset(Moedoo::$CACHE_MAP[$referenceRule['table']][$row[$column]]) === true) {
                  // we need to *preserve* depth so other
                  // FK rules get a chance to do their thing with depth
                  $d = $depth - 1;
                  $row[$column] = Moedoo::FK($referenceRule['table'], Moedoo::$CACHE_MAP[$referenceRule['table']][$row[$column]], $d, Moedoo::$CACHE_MAP);
                }

                else if (preg_match('/^\[.+\]$/', $column) === 1) {
                  $column = trim($column, '[]');
                  $fkMapped = [];
                  foreach ($row[$column] as $j => $fkId) {
                    if (isset(Moedoo::$CACHE_MAP[$referenceRule['table']][$fkId]) === true) {
                      $d = $depth - 1;
                      array_push($fkMapped, Moedoo::FK($referenceRule['table'], Moedoo::$CACHE_MAP[$referenceRule['table']][$fkId], $d, Moedoo::$CACHE_MAP));
                    }
                  }

                  $row[$column] = $fkMapped;
                }

                else if(preg_match('/^\{.+\}$/', $column) === 1) {
                  $column = trim($column, '{}');
                  $row[Config::get('REFERENCE_KEY')][$column] = [];

                  // reverse fk []
                  if (isset(Config::get('TABLES')[$referenceRule['table']]['[int]']) && in_array($referenceRule['referencing_column'], Config::get('TABLES')[$referenceRule['table']]['[int]'])) {
                    foreach (Moedoo::$CACHE_MAP[$referenceRule['table']] as $id => $rRow) {
                      if (in_array($row[$referenceRule['referenced_by']], $rRow[$referenceRule['referencing_column']]) === true) {
                        $d = $depth - 1;
                        array_push($row[Config::get('REFERENCE_KEY')][$column], Moedoo::FK($referenceRule['table'], $rRow, $d, Moedoo::$CACHE_MAP));
                      }
                    }
                  }

                  // single reverse fk
                  else if (isset(Config::get('TABLES')[$referenceRule['table']]['int']) && in_array($referenceRule['referencing_column'], Config::get('TABLES')[$referenceRule['table']]['int'])) {
                    foreach (Moedoo::$CACHE_MAP[$referenceRule['table']] as $id => $rRow) {
                      if ($rRow[$referenceRule['referencing_column']] === $row[$referenceRule['referenced_by']]) {
                        $d = $depth - 1;
                        array_push($row[Config::get('REFERENCE_KEY')][$column], Moedoo::FK($referenceRule['table'], $rRow, $d, Moedoo::$CACHE_MAP));
                      }
                    }
                  }
                }
              }
            }
          }
          // ./ mapping ----------------------------------------------------------------------------
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
      $build = '';

      foreach (Config::get('TABLES')[$table]['returning'] as $index => $column) {
        $build .= "{$column}, ";
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
    public static function castForSQLite($table, $data) {
      foreach ($data as $column => &$value) {
        if (array_key_exists('JSON', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['JSON']) === true) {
          $value = json_encode($value);
        }

        if (array_key_exists('bool', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['bool']) === true) {
          $value = $value === true ? 'TRUE' : 'FALSE';
        }

        if ((array_key_exists('[int]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[int]']) === true) ||
            (array_key_exists('[float]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[float]']) === true)) {
          $value = '{'. implode(',', $value) .'}';
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
      // we have rows, setting rows...
      // this right here saves gives 200% performance boost on large datasets query
      $CAST_FLAG = [
        'JSON' => [],
        'bool' => [],
        '[int]' => []
      ];

      if (count($rows) > 0 && isset($rows[0][Config::get('TABLES')[$table]['pk']]) === true) {
        foreach (Config::get('TABLES')[$table]['returning'] as $index => $column) {
          $CAST_FLAG['JSON'][$column] = array_key_exists('JSON', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['JSON']) === true;
          $CAST_FLAG['bool'][$column] = array_key_exists('bool', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['bool']) === true;
          $CAST_FLAG['[int]'][$column] = array_key_exists('[int]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[int]']) === true;
        }

        foreach ($rows as $index => &$row) {
          foreach ($row as $column => &$value) {
            if ($CAST_FLAG['JSON'][$column] === true) {
              $value = json_decode($value);
            }

            else if ($CAST_FLAG['bool'][$column] === true) {
              $value = $value === 'TRUE' ? true : false;
            }

            // for now (and probably forever) we can only work with 1D arrays
            // since we'll have PG version 8 we can't use JSON :(
            else if ($CAST_FLAG['[int]'][$column] === true) {
              $value = trim($value, '{}');
              $value = $value === '' ? [] : explode(',', $value);
              foreach ($value as $index => &$v) {
                $v = is_numeric($v) === true ? (int)$v : null;
              }
            }
          }
        }
      }

      return $rows;
    }


    /**
     * query executor
     *
     * EXCEPTION CODES
     * 1: unable to update for unknown reason[s]
     * 2: duplicate constraint
     * 3: foreign key constraint
     *
     * @param  String $table
     * @param  String $query
     * @param  Array $params
     * @return Array
     */
    public static function executeQuery($table, $query, $params = []) {
      $db = Moedoo::db(Config::get('DB_FILE'), Config::get('DB_BUSY_TIMEOUT'));

      $querySelect = preg_match('/^SELECT/', $query);
      $queryInsert = preg_match('/^INSERT/', $query);
      $queryUpdate = preg_match('/^UPDATE/', $query);
      $queryDelete = preg_match('/^DELETE/', $query);

      if (count($params) > 0) {
        $statement = $db -> prepare($query);

        for ($i = 0; $i < count($params); $i++) {
          $iPlus1 = $i + 1;
          $statement -> bindValue("{$iPlus1}", $params[$i]);
        }

        $result = $statement -> execute();
        $result -> finalize();
      } else {
        $result = $db -> query($query);
      }

      if ($result === false) {
        switch ($db -> lastErrorCode()) {
          case 787:
            throw new Exception('request violets foreign key constraint', 3);
            break;

          case 2067:
            throw new Exception('request violets duplicate constraint', 2);
            break;

          default:
            // we won't be giving detailed error in order "protect" the system
            if ($querySelect === 1) {
              throw new Exception("unable to select from table `{$table}`", 1);
            } else if ($queryInsert === 1) {
              throw new Exception("unable to save `{$table}`", 1);
            } else if ($queryUpdate === 1) {
              throw new Exception("unable to update `{$table}`", 1);
            } else if ($queryDelete === 1) {
              throw new Exception("unable to delete record from table `{$table}`", 1);
            } else {
              throw new Exception('error processing query', 1);
            }
            break;
        }
      } else {
        if ($querySelect === 1) {
          $rows = [];

          while ($row = $result -> fetchArray(SQLITE3_ASSOC)) {
            array_push($rows, $row);
          }

          return $rows;
        } else if ($queryInsert === 1) {
          return $db -> lastInsertRowID();
        } else if ($queryUpdate === 1 || $queryDelete === 1) {
          return $db -> changes();
        }
      }
    }


    /**
     * creates SQLite resource
     *
     * @param  string $path file path for the SQLite file
     * @return db resource
     */
    public static function db($path, $busyTimeout) {
      if (Moedoo::$db === null) {
        Moedoo::$db = new SQLite($path, SQLITE3_OPEN_READWRITE);
        Moedoo::$db -> busyTimeout($busyTimeout);
      }

      return Moedoo::$db;
    }


    /**
     * returns matching rows against query
     *
     * @param string $table
     * @param string $query
     * @param string $limit
     * @param integer $depth
     */
    public static function search($table, $q, $limit = -1, $depth = 1) {
      $columns = Moedoo::buildReturn($table);
      $q = preg_replace('/ +/', '|', trim($q));
      $q = preg_replace('/ /', '|', $q);
      $params = [$q];
      $query = "SELECT {$columns} FROM {$table} WHERE ";
      $where = '';
      $order_by = 'ORDER BY';

      // model doesn't have any full-text fields
      if (count(Config::get('TABLES')[$table]['search']) === 0) {
        Rock::halt(400, "table `{$table}` has no searchable fields");
      }

      else {
        // building vector...
        foreach (Config::get('TABLES')[$table]['search'] as $key => $value) {
          $where .= "to_tsvector({$value}) @@ to_tsquery($1) OR ";
        }

        $where = substr($where, 0, -4);

        // building rank...
        foreach (Config::get('TABLES')[$table]['search'] as $key => $value) {
          $order_by .= " ts_rank(to_tsvector($value), to_tsquery($1)) DESC, ";
        }

        $order_by = substr($order_by, 0, -2);
        $limit = "LIMIT {$limit}";

        try {
          $rows = Moedoo::executeQuery($table, "{$query} {$where} {$order_by} {$limit};", $params);

          if (count($rows) === 0) {
            return [];
          }

          else {
            $rows = Moedoo::cast($table, $rows);
            $rows = Moedoo::referenceFk($table, $rows, $depth);
            return $rows;
          }
        } catch (Exception $e) {
          throw new Exception($e->getMessage(), 1);
        }
      }
    }


    /**
     * returns row count on a table
     *
     * @param string $table
     * @return integer
     */
    public static function count($table) {
      $query = 'SELECT count('. Config::get('TABLES')[$table]['pk'] .") as count FROM {$table};";
      $params = [];
      try {
        $rows = Moedoo::executeQuery($table, $query, $params);

        if (count($rows) === 0) {
          $count = 0;
        }

        else {
          $count = (int)$rows[0]['count'];
        }

        return $count;
      } catch (Exception $e) {
        throw new Exception($e->getMessage(), 1);
      }
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
    public static function select($table, $and = null, $or = null, &$depth = 1, $limit = -1, $offset = 0) {
      $columns = Moedoo::buildReturn($table);
      $query = "SELECT {$columns} FROM {$table}";
      $params = [];

      if ($and === null && $or === null) {
        $query .= ' ORDER BY '. Config::get('TABLES')[$table]['pk'] .' DESC ';
      }

      else {
        $query .= ' WHERE';

        if ($and !== null) {
          $query .= ' (';

          foreach ($and as $column => $value) {
            array_push($params, $value);

            if ((array_key_exists('[bool]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[bool]']) === true) ||
               (array_key_exists('[int]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[int]']) === true) ||
               (array_key_exists('[float]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[float]']) === true) ||
               (array_key_exists('[double]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[double]']) === true) ||
               (array_key_exists('[JSON]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[JSON]']) === true) ||
               (array_key_exists('[geometry]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[geometry]']) === true)) {
              $query .= '$'. count($params) ."=ANY({$column}) AND ";
            } else {
              $query .= "{$column}=$". count($params) .' AND ';
            }
          }

          $query = substr($query, 0, -5);
          $query .= ')';

          if ($or !== null) {
            $query .= ' AND';
          }
        }

        if ($or !== null) {
          $query .= ' (';

          foreach ($or as $column => $value) {
            array_push($params, $value);

            if ((array_key_exists('[bool]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[bool]']) === true) ||
               (array_key_exists('[int]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[int]']) === true) ||
               (array_key_exists('[float]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[float]']) === true) ||
               (array_key_exists('[double]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[double]']) === true) ||
               (array_key_exists('[JSON]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[JSON]']) === true) ||
               (array_key_exists('[geometry]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[geometry]']) === true)) {
              $query .= '$'. count($params) ."=ANY({$column}) OR ";
            } else {
              $query .= "{$column}=$".count($params).' OR ';
            }
          }

          $query = substr($query, 0, -4);
          $query .= ')';
        }

        $query .= ' ORDER BY '. Config::get('TABLES')[$table]['pk'] .' DESC ';
      }

      $query .= "LIMIT {$limit} OFFSET {$offset};";

      try {
        $rows = Moedoo::executeQuery($table, $query, $params);

        if (count($rows) === 0) {
          return [];
        }

        else {
          $rows = Moedoo::cast($table, $rows);
          $rows = Moedoo::referenceFk($table, $rows, $depth);
          return $rows;
        }
      } catch (Exception $e) {
        throw new Exception($e->getMessage(), 1);
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
      $data = Moedoo::castForSQLite($table, $data);
      $count = 1;
      $columns = [];
      $holders = []; // ${$index}
      $params = [];

      foreach ($data as $column => $value) {
        array_push($columns, $column);
        array_push($holders, "\${$count}");
        array_push($params, $value);
        $count++;
      }

      $columns = implode(', ', $columns);
      $holders = implode(', ', $holders);
      $returning = Moedoo::buildReturn($table);

      $query = "INSERT INTO {$table} ({$columns}) VALUES ({$holders});";
      try {
        $rowId = Moedoo::executeQuery($table, $query, $params);

        // fetching the last inserted row
        $rows = Moedoo::select($table, [Config::get('TABLES')[$table]['pk'] => $rowId], null, $depth);

        // currently we're only supporting single row insert
        if (count($rows) === 1) {
          $rows = Moedoo::referenceFk($table, $rows, $depth);
          return $rows[0];
        } else {
          throw new Exception('error processing query', 1);
        }
      } catch (Exception $e) {
        throw new Exception($e -> getMessage(), 1);
      }
    }


    /**
     * executes UPDATE on a given tale entry
     *
     * 1 - patch request on non-existent data
     *
     * @param string $table
     * @param array $data - data which to replace on
     * @return array | null
     */
    public static function update($table, $data, $id, $depth = 1) {
      $data = Moedoo::castForSQLite($table, $data);
      $count = 1;
      $set = [];
      $params = [];
      $columns = Moedoo::buildReturn($table);

      foreach ($data as $column => $value) {
        array_push($set, $column."=\${$count}");
        array_push($params, $value);
        $count++;
      }

      $set = implode(', ', $set);
      array_push($params, $id);

      $query = "UPDATE {$table} SET {$set} WHERE ". Config::get('TABLES')[$table]['pk'] ."=\${$count};";
      try {
        $changeCount = Moedoo::executeQuery($table, $query, $params);

        // currently we're only supporting single row update
        if ($changeCount === 1) {
          // RETURNING
          $rows = Moedoo::select($table, [Config::get('TABLES')[$table]['pk'] => $id], null, $depth);
          $rows = Moedoo::referenceFk($table, $rows, $depth);
          return $rows[0];
        }

        // no row was affected, returns the data back...
        else {
          throw new Exception("`{$table}` entry with id `{$id}` does not exist", 1);
        }
      } catch (Exception $e) {
        throw new Exception($e->getMessage(), 1);
      }
    }


    /**
     * executes DELTE command on a given entry
     * i didn't want to do this, but...
     * there comes a time were you want to just delete
     *
     * EXCEPTION CODES:
     * 1: unable to delete for _unknown_ reason[s]
     *
     * @param string $table
     * @param integer $id - id on which to delete on
     * @return array - deleted row
     */
    public static function delete($table, $id) {
      $params = [$id];
      $columns = Moedoo::buildReturn($table);
      // RETURNING before deleting
      $rows = Moedoo::select($table, [Config::get('TABLES')[$table]['pk'] => $id], null, $depth);

      if (count($rows) === 0) {
        throw new Exception("`{$table}` with resource id `{$id}` does not exist", 1);
      } else {
        $query = "DELETE FROM {$table} WHERE ". Config::get('TABLES')[$table]['pk'] ."=$1;";

        try {
          $changeCount = Moedoo::executeQuery($table, $query, $params);

          // currently we're only supporting single row deletion
          if ($changeCount === 1) {
            return $rows[0];
          } else {
            throw new Exception("`{$table}` with resource id `{$id}` does not exist", 1);
          }
        } catch (Exception $e) {
          throw new Exception($e->getMessage(), 1);
        }
      }
    }
  }
