<?php
  class Moedoo {
    // this will hold all the table names that will be be cached for depth references
    private static $MAPPER = [];
    // this will hold table rows with ['tableName' => ['id' => 'row']] structure
    // this will sacrifice the memory in-order to gain much needed performance boost
    // on larger depth requests (especially with queries)
    private static $CACHE_MAP = [];
    private static $DEPTH = []; // ['tableName' => 'depth']
    private static $included = []; // ['tableName' => ['id' => 'row']]
    private static $db = null;



    /**
     * goes through tables 'fk' rules and builds ['tableName' => 'depth']
     * which will be used for consistent model mapping
     * @param String $table
     * @param Integer &$depth
     * @return Array
     */
    public static function DEPTH_BUILDER($table, &$depth) {
      if ($depth-- > 0 && isset(Config::get('TABLES')[$table]['fk']) === true) {
        foreach (Config::get('TABLES')[$table]['fk'] as $column => $referenceRule) {
          if (isset(Moedoo::$DEPTH[$referenceRule['table']]) === false) {
            Moedoo::$DEPTH[$referenceRule['table']] = $depth;
          } elseif (isset(Moedoo::$DEPTH[$referenceRule['table']]) === true &&  Moedoo::$DEPTH[$referenceRule['table']] < $depth) {
            Moedoo::$DEPTH[$referenceRule['table']] = $depth;
          }

          $d = $depth;
          Moedoo::DEPTH_BUILDER($referenceRule['table'], $d);
        }
      }

      return Moedoo::$DEPTH;
    }



    /**
     * MAPPER - recursive builder for `CACHE_BUILDER`
     * goes through the $table's `fk` rules according to depth and builds
     * [tableName => tableIdColumn] for `CACHE_BUILDER` to build
     *
     * Mapped FK rule types:
     * [col]
     * {col}
     *
     * @param String $table
     * @param Integer &$depth
     * @return Array $MAPPER
     */
    public static function MAPPER($table, &$depth) {
      if ($depth-- > 0 && isset(Config::get('TABLES')[$table]['fk']) === true) {
        foreach (Config::get('TABLES')[$table]['fk'] as $column => $referenceRule) {
          if (preg_match('/^\[.+\]$/', $column) === 1) {
            Moedoo::$MAPPER[$referenceRule['table']] = $referenceRule['references'];
            $d = $depth;
            Moedoo::MAPPER($referenceRule['table'], $d);
          } elseif (preg_match('/^\{.+\}$/', $column) === 1) {
            Moedoo::$MAPPER[$referenceRule['table']] = Config::get('TABLES')[$referenceRule['table']]['pk'];
            $d = $depth;
            Moedoo::MAPPER($referenceRule['table'], $d);
          }
        }
      }

      return Moedoo::$MAPPER;
    }



    /**
     * cache builder [with depth zero]
     * [ [table] => [ columnId => row ] ]
     *
     * @param String $table
     * @param String $columnId
     * @return Array $CACHE_MAP
     */
    public static function CACHE_BUILDER($table, $columnId) {
      if (isset(Moedoo::$CACHE_MAP[$table]) === false) {
        Moedoo::$CACHE_MAP[$table] = [];

        $columns = Moedoo::buildReturn($table);
        $query = "SELECT $columns FROM $table;";
        $includeRows = Moedoo::executeQuery($table, $query, []);
        $includeRows = Moedoo::cast($table, $includeRows);

        foreach ($includeRows as $index => $includeRow) {
          Moedoo::$CACHE_MAP[$table][$includeRow[$columnId]] = $includeRow;
        }
      }

      return Moedoo::$CACHE_MAP;
    }

    /**
     * @param Boolean $keepAuth
     * @return Array
     */
    public static function included($keepAuth = false) {
      if ($keepAuth === false) {
        $included = Moedoo::$included;
        unset($included['user']);
        unset($included['user_group']);
        return $included;
      }

      return Moedoo::$included;
    }



    /**
     * returns $rFK's {column} references
     *
     * @param String $tFK
     * @param Array $rFK
     * @return Array Enhanced $rFK
     */
    private static function FKRelationship($tFK, $rFK) {
      if (isset(Config::get('TABLES')[$tFK]['fk']) === true) {
        foreach (Config::get('TABLES')[$tFK]['fk'] as $column => $referenceRule) {
          if (isset(Moedoo::$CACHE_MAP[$referenceRule['table']]) === false) {
            Moedoo::$CACHE_MAP[$referenceRule['table']] = [];
          }

          if (preg_match('/^\{.+\}$/', $column) === 1) {
            //-> {column}
            $column = trim($column, '{}');
            $rFK[Config::get('RELATIONSHIP_KEY')][$column] = [];
            $pk = Config::get('TABLES')[$referenceRule['table']]['pk'];

            if ((isset(Config::get('TABLES')[$referenceRule['table']]['[int]']) && in_array($referenceRule['referencing_column'], Config::get('TABLES')[$referenceRule['table']]['[int]'])) || (isset(Config::get('TABLES')[$referenceRule['table']]['[string]']) && in_array($referenceRule['referencing_column'], Config::get('TABLES')[$referenceRule['table']]['[string]']))) {
              //-> reverse fk []
              foreach (Moedoo::$CACHE_MAP[$referenceRule['table']] as $id => $rRow) {
                if (in_array($rFK[$referenceRule['referenced_by']], $rRow[$referenceRule['referencing_column']]) === true) {
                  array_push($rFK[Config::get('RELATIONSHIP_KEY')][$column], $rRow[$pk]);
                  Moedoo::$included[$referenceRule['table']][$rRow[$pk]] = Moedoo::FKRelationship($referenceRule['table'], $rRow);
                }
              }
            } else {
              //-> single reverse fk
              foreach (Moedoo::$CACHE_MAP[$referenceRule['table']] as $id => $rRow) {
                if ($rRow[$referenceRule['referencing_column']] === $rFK[$referenceRule['referenced_by']]) {
                  array_push($rFK[Config::get('RELATIONSHIP_KEY')][$column], $rRow[$pk]);
                  Moedoo::$included[$referenceRule['table']][$rRow[$pk]] = Moedoo::FKRelationship($referenceRule['table'], $rRow);
                }
              }
            }
          }
        }
      }

      return $rFK;
    }



    /**
     * builds fk iterating over CACHE_MAP
     *
     * @param String $tFK
     * @param Array $rFK
     * @param Integer &$dFK
     * @return Array
     */
    public static function FK($tFK, $rFK, &$dFK) {
      if ($dFK-- >= 0) {
        if (isset(Config::get('TABLES')[$tFK]['fk']) === true) {
          foreach (Config::get('TABLES')[$tFK]['fk'] as $column => $referenceRule) {
            if (isset(Moedoo::$CACHE_MAP[$referenceRule['table']]) === false) {
              //-> the column table to be checked in cache is not of [table] or {table}
              //-> `$CACHE_MAP` will be built per request
              // booting `$CACHE_MAP` table...
              Moedoo::$CACHE_MAP[$referenceRule['table']] = [];
            }

            if (preg_match('/^[a-z].+/', $column) === 1 && isset(Moedoo::$CACHE_MAP[$referenceRule['table']][$rFK[$column]]) === false) {
              //-> `$column` not found in `$CACHE_MAP`, adding...
              $columns = Moedoo::buildReturn($referenceRule['table']);
              $query = "SELECT $columns FROM {$referenceRule['table']} WHERE {$referenceRule['references']} = :1;";

              try {
                $includeRows = Moedoo::executeQuery($referenceRule['table'], $query, [$rFK[$column]]);

                if (count($includeRows) === 0) {
                  //-> reference no longer exits
                  // setting cache to null...
                  Moedoo::$CACHE_MAP[$referenceRule['table']][$rFK[$column]] = null;
                } else {
                  $includeRows = Moedoo::cast($referenceRule['table'], $includeRows);
                  // setting cache to the first row...
                  Moedoo::$CACHE_MAP[$referenceRule['table']][$rFK[$column]] = $includeRows[0];

                  if ($dFK === -1 && Moedoo::$DEPTH[$referenceRule['table']] > 0) {
                    Moedoo::$included[$referenceRule['table']][$rFK[$column]] = Moedoo::FKRelationship($referenceRule['table'], $includeRows[0]);
                  } else {
                    $d = $dFK;
                    Moedoo::$included[$referenceRule['table']][$rFK[$column]] = Moedoo::FK($referenceRule['table'], $includeRows[0], $d);
                  }
                }
              } catch (Exception $e) {
                throw new Exception($e->getMessage(), 1);
              }
            } elseif (preg_match('/^[a-z].+/', $column) === 1 && isset(Moedoo::$CACHE_MAP[$referenceRule['table']][$rFK[$column]]) === true && Moedoo::$CACHE_MAP[$referenceRule['table']][$rFK[$column]] !== null) {
              //-> column - cached
              if ($dFK === -1 && Moedoo::$DEPTH[$referenceRule['table']] > 0) {
                Moedoo::$included[$referenceRule['table']][$rFK[$column]] = Moedoo::FKRelationship($referenceRule['table'], Moedoo::$CACHE_MAP[$referenceRule['table']][$rFK[$column]]);
              } else {
                $d = $dFK;
                Moedoo::$included[$referenceRule['table']][$rFK[$column]] = Moedoo::FK($referenceRule['table'], Moedoo::$CACHE_MAP[$referenceRule['table']][$rFK[$column]], $d);
              }
            } elseif (preg_match('/^\[.+\]$/', $column) === 1) {
              //-> [column]
              $column = trim($column, '[]');

              foreach ($rFK[$column] as $j => $fkId) {
                if (isset(Moedoo::$CACHE_MAP[$referenceRule['table']][$fkId]) === true && Moedoo::$CACHE_MAP[$referenceRule['table']][$fkId] !== null) {
                  if ($dFK === -1 && Moedoo::$DEPTH[$referenceRule['table']] > 0) {
                    Moedoo::$included[$referenceRule['table']][$fkId] = Moedoo::FKRelationship($referenceRule['table'], Moedoo::$CACHE_MAP[$referenceRule['table']][$fkId]);
                  } else {
                    $d = $dFK;
                    Moedoo::$included[$referenceRule['table']][$fkId] = Moedoo::FK($referenceRule['table'], Moedoo::$CACHE_MAP[$referenceRule['table']][$fkId], $d);
                  }
                }
              }
            } elseif (preg_match('/^\{.+\}$/', $column) === 1) {
              //-> {column}
              $column = trim($column, '{}');
              $rFK[Config::get('RELATIONSHIP_KEY')][$column] = [];
              $pk = Config::get('TABLES')[$referenceRule['table']]['pk'];

              if ((isset(Config::get('TABLES')[$referenceRule['table']]['[int]']) && in_array($referenceRule['referencing_column'], Config::get('TABLES')[$referenceRule['table']]['[int]'])) || (isset(Config::get('TABLES')[$referenceRule['table']]['[string]']) && in_array($referenceRule['referencing_column'], Config::get('TABLES')[$referenceRule['table']]['[string]']))) {
                //-> reverse fk []
                foreach (Moedoo::$CACHE_MAP[$referenceRule['table']] as $id => $rRow) {
                  if (in_array($rFK[$referenceRule['referenced_by']], $rRow[$referenceRule['referencing_column']]) === true) {
                    array_push($rFK[Config::get('RELATIONSHIP_KEY')][$column], $rRow[$pk]);

                    if ($dFK === -1 && Moedoo::$DEPTH[$referenceRule['table']] > 0) {
                      Moedoo::$included[$referenceRule['table']][$rRow[$pk]] = Moedoo::FKRelationship($referenceRule['table'], $rRow);
                    } else {
                      $d = $dFK;
                      Moedoo::$included[$referenceRule['table']][$rRow[$pk]] = Moedoo::FK($referenceRule['table'], $rRow, $d);
                    }
                  }
                }
              } else {
                //-> single reverse fk
                foreach (Moedoo::$CACHE_MAP[$referenceRule['table']] as $id => $rRow) {
                  if ($rRow[$referenceRule['referencing_column']] === $rFK[$referenceRule['referenced_by']]) {
                    array_push($rFK[Config::get('RELATIONSHIP_KEY')][$column], $rRow[$pk]);

                    if ($dFK === -1 && Moedoo::$DEPTH[$referenceRule['table']] > 0) {
                      Moedoo::$included[$referenceRule['table']][$rRow[$pk]] = Moedoo::FKRelationship($referenceRule['table'], $rRow);
                    } else {
                      $d = $dFK;
                      Moedoo::$included[$referenceRule['table']][$rRow[$pk]] = Moedoo::FK($referenceRule['table'], $rRow, $d);
                    }
                  }
                }
              }
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
    public static function referenceFK($table, $rows, &$depth = 1) {
      if (isset(Config::get('TABLES')[$table]['fk']) === true) {
        //-> fk rules exist for the table
        if ($depth > 0) {
          $d = $depth;
          Moedoo::MAPPER($table, $d);
          $d = $depth;
          Moedoo::DEPTH_BUILDER($table, $d);

          // passing to cache builder --------------------------------------------------------------
          foreach (Moedoo::$MAPPER as $t => $id) {
            Moedoo::CACHE_BUILDER($t, $id);
          }
          // ./ passing to cache builder -----------------------------------------------------------

          // mapping -------------------------------------------------------------------------------
          foreach ($rows as $i => &$row) {
            foreach (Config::get('TABLES')[$table]['fk'] as $column => $referenceRule) {
              if (isset(Moedoo::$included[$referenceRule['table']]) === false) {
                //-> initiating $included...
                Moedoo::$included[$referenceRule['table']] = [];
              }

              if (preg_match('/^[a-z].+$/', $column) === 1) {
                //-> column
                // we need to *preserve* depth so other
                // FK rules get a chance to do their thing with depth
                $d = Moedoo::$DEPTH[$referenceRule['table']] - 1;

                if (isset(Moedoo::$CACHE_MAP[$referenceRule['table']][$row[$column]]) === true) {
                  //-> reference has been cached
                  Moedoo::$included[$referenceRule['table']][$row[$column]] = Moedoo::FK($referenceRule['table'], Moedoo::$CACHE_MAP[$referenceRule['table']][$row[$column]], $d);
                } else {
                  //-> column has not been cached
                  $columns = Moedoo::buildReturn($referenceRule['table']);
                  $query = "SELECT $columns FROM {$referenceRule['table']} WHERE {$referenceRule['references']} = :1;";

                  try {
                    $includeRows = Moedoo::executeQuery($referenceRule['table'], $query, [$row[$column]]);

                    if (count($includeRows) === 0) {
                      //-> reference no longer exits
                      // setting cache to null...
                      Moedoo::$CACHE_MAP[$referenceRule['table']][$row[$column]] = null;
                    } else {
                      $includeRows = Moedoo::cast($referenceRule['table'], $includeRows);
                      // setting cache to the first row...
                      Moedoo::$CACHE_MAP[$referenceRule['table']][$row[$column]] = $includeRows[0];
                      Moedoo::$included[$referenceRule['table']][$row[$column]] = Moedoo::FK($referenceRule['table'], $includeRows[0], $d);
                    }
                  } catch (Exception $e) {
                    throw new Exception($e->getMessage(), 1);
                  }
                }
              } elseif (preg_match('/^\[.+\]$/', $column) === 1) {
                //-> [column]
                $column = trim($column, '[]');

                foreach ($row[$column] as $j => $fkId) {
                  if (isset(Moedoo::$CACHE_MAP[$referenceRule['table']][$fkId]) === true) {
                    $d = Moedoo::$DEPTH[$referenceRule['table']] - 1;
                    Moedoo::$included[$referenceRule['table']][$fkId] = Moedoo::FK($referenceRule['table'], Moedoo::$CACHE_MAP[$referenceRule['table']][$fkId], $d);
                  }
                }
              } elseif (preg_match('/^\{.+\}$/', $column) === 1) {
                //-> {column}
                $column = trim($column, '{}');
                $row[Config::get('RELATIONSHIP_KEY')][$column] = [];
                $pk = Config::get('TABLES')[$referenceRule['table']]['pk'];

                if ((isset(Config::get('TABLES')[$referenceRule['table']]['[int]']) && in_array($referenceRule['referencing_column'], Config::get('TABLES')[$referenceRule['table']]['[int]'])) || (isset(Config::get('TABLES')[$referenceRule['table']]['[string]']) && in_array($referenceRule['referencing_column'], Config::get('TABLES')[$referenceRule['table']]['[string]']))) {
                  //-> reverse fk []
                  foreach (Moedoo::$CACHE_MAP[$referenceRule['table']] as $id => $rRow) {
                    if (in_array($row[$referenceRule['referenced_by']], $rRow[$referenceRule['referencing_column']]) === true) {
                      array_push($row[Config::get('RELATIONSHIP_KEY')][$column], $rRow[$pk]);
                      $d = Moedoo::$DEPTH[$referenceRule['table']] - 1;
                      Moedoo::$included[$referenceRule['table']][$rRow[$pk]] = Moedoo::FK($referenceRule['table'], $rRow, $d);
                    }
                  }
                } else {
                  //-> single reverse fk
                  foreach (Moedoo::$CACHE_MAP[$referenceRule['table']] as $id => $rRow) {
                    if ($rRow[$referenceRule['referencing_column']] === $row[$referenceRule['referenced_by']]) {
                      array_push($row[Config::get('RELATIONSHIP_KEY')][$column], $rRow[$pk]);
                      //-> include check
                      $d = Moedoo::$DEPTH[$referenceRule['table']] - 1;
                      Moedoo::$included[$referenceRule['table']][$rRow[$pk]] = Moedoo::FK($referenceRule['table'], $rRow, $d);
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
        $build .= "$column, ";
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
            (array_key_exists('[string]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[string]']) === true)) {
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
        '[int]' => [],
        '[string]' => [],
      ];

      if (count($rows) > 0 && isset($rows[0][Config::get('TABLES')[$table]['pk']]) === true) {
        foreach (Config::get('TABLES')[$table]['returning'] as $index => $column) {
          $CAST_FLAG['JSON'][$column] = array_key_exists('JSON', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['JSON']) === true;
          $CAST_FLAG['bool'][$column] = array_key_exists('bool', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['bool']) === true;
          $CAST_FLAG['[int]'][$column] = array_key_exists('[int]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[int]']) === true;
          $CAST_FLAG['[string]'][$column] = array_key_exists('[string]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[string]']) === true;
        }

        foreach ($rows as $index => &$row) {
          foreach ($row as $column => &$value) {
            if ($CAST_FLAG['JSON'][$column] === true) {
              $value = json_decode($value, true);
            } elseif ($CAST_FLAG['bool'][$column] === true) {
              $value = $value === 'TRUE' ? true : false;
            } elseif ($CAST_FLAG['[int]'][$column] === true) {
              //-> for now (and probably forever) we can only work with 1D arrays
              //-> since we'll have PG version 8 we can't use JSON :(
              $value = trim($value, '{}');
              $value = $value === '' ? [] : explode(',', $value);
              foreach ($value as $index => &$v) {
                $v = is_numeric($v) === true ? (int)$v : null;
              }
            } elseif ($CAST_FLAG['[string]'][$column] === true) {
              //-> for now (and probably forever) we can only work with 1D arrays
              //-> since we'll have PG version 8 we can't use JSON :(
              $value = trim($value, '{}');
              $value = $value === '' ? [] : explode(',', $value);
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
        $statement = $db->prepare($query);

        for ($i = 0; $i < count($params); $i++) {
          $iPlus1 = $i + 1;
          $statement->bindValue(":$iPlus1", $params[$i]);
        }

        $result = $statement->execute();

        if (is_bool($result) === false) {
          $result->finalize();
        }
      } else {
        $result = $db->query($query);
      }

      if ($querySelect === 1) {
        $rows = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
          array_push($rows, $row);
        }

        return $rows;
      } elseif ($queryInsert === 1) {
        return $db->lastInsertRowID();
      } elseif ($queryUpdate === 1 || $queryDelete === 1) {
        return $db->changes();
      }
    }



    /**
     * creates SQLite resource
     *
     * @param  string $path file path for the SQLite file
     * @param  number $busyTimeout
     * @return db resource
     */
    public static function db($path, $busyTimeout) {
      if (Moedoo::$db === null) {
        Moedoo::$db = new SQLite($path, SQLITE3_OPEN_READWRITE);
        Moedoo::$db->busyTimeout($busyTimeout);
        Moedoo::$db->enableExceptions(true);

        // big shout out to:
        // https://stackoverflow.com/users/2148494/cara
        // this has to be executed per connection
        $statement = Moedoo::$db->prepare('PRAGMA foreign_keys = ON;');
        $statement->execute();
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
      $q = preg_replace('/ +/', ' ', trim($q));
      $query = "SELECT $columns FROM $table WHERE";
      $where = '';

      if (isset(Config::get('TABLES')[$table]['search']) === false || count(Config::get('TABLES')[$table]['search']) === 0) {
        //-> model doesn't have any "search" fields
        Rock::halt(400, "table `$table` has no searchable fields");
      } else {
        $db = Moedoo::db(Config::get('DB_FILE'), Config::get('DB_BUSY_TIMEOUT'));

        foreach (Config::get('TABLES')[$table]['search'] as $key => $value) {
          $where .= "$value LIKE '%". $db->escapeString($q) ."%' OR ";
        }

        $where = substr($where, 0, -4);

        $limit = "LIMIT $limit";

        try {
          $rows = Moedoo::executeQuery($table, "$query $where $limit;", []);

          if (count($rows) === 0) {
            return [];
          } else {
            $rows = Moedoo::cast($table, $rows);
            $rows = Moedoo::referenceFK($table, $rows, $depth);
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
      $query = 'SELECT count('. Config::get('TABLES')[$table]['pk'] .") as count FROM $table;";

      try {
        $rows = Moedoo::executeQuery($table, $query, []);

        if (count($rows) === 0) {
          $count = 0;
        } else {
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
      $query = "SELECT $columns FROM $table";
      $params = [];

      if ($and === null && $or === null) {
        $query .= ' ';
      } else {
        $query .= ' WHERE';

        if ($and !== null) {
          $query .= ' (';

          foreach ($and as $column => $value) {
            if (array_key_exists('bool', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['bool']) === true) {
              array_push($params, $value === true ? 'TRUE' : 'FALSE');
              $query .= "$column = :". count($params) .' AND ';
            } elseif (array_key_exists('[int]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[int]']) === true) {
              foreach ($value as $i1 => $v2) {
                array_push($params, $v2);
                $query .= "$column = :". count($params) .' AND ';
              }
            } elseif (array_key_exists('[string]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[string]']) === true) {
              foreach ($value as $i1 => $v2) {
                array_push($params, $v2);
                $query .= "$column = :". count($params) .' AND ';
              }
            } else {
              array_push($params, $value);
              $query .= "$column = :". count($params) .' AND ';
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
            if (array_key_exists('bool', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['bool']) === true) {
              array_push($params, $value === true ? 'TRUE' : 'FALSE');
              $query .= "$column = :". count($params) .' OR ';
            } elseif (array_key_exists('[int]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[int]']) === true) {
              foreach ($value as $i1 => $v2) {
                array_push($params, $v2);
                $query .= "$column = :". count($params) .' OR ';
              }
            } elseif (array_key_exists('[string]', Config::get('TABLES')[$table]) === true && in_array($column, Config::get('TABLES')[$table]['[string]']) === true) {
              foreach ($value as $i1 => $v2) {
                array_push($params, $v2);
                $query .= "$column = :". count($params) .' OR ';
              }
            } else {
              array_push($params, $value);
              $query .= "$column = :".count($params).' OR ';
            }
          }

          $query = substr($query, 0, -4);
          $query .= ')';
        }

        $query .= ' ';
      }

      $query .= "ORDER BY _rowid_ DESC LIMIT $limit OFFSET $offset;";

      try {
        $rows = Moedoo::executeQuery($table, $query, $params);

        if (count($rows) === 0) {
          return [];
        } else {
          $rows = Moedoo::cast($table, $rows);
          $rows = Moedoo::referenceFK($table, $rows, $depth);
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
      $count = 2; // PK will be initialized via `Util::randomString`
      $columns = [Config::get('TABLES')[$table]['pk']];
      $holders = [':1']; // :$index
      $params = [Util::randomString(Config::get('DB_ID_LENGTH'))];

      foreach ($data as $column => $value) {
        if (in_array($column, $columns) === false) {
          //-> prevent PK double via `$data` containing PK column name
          array_push($columns, $column);
          array_push($holders, ":$count");
          array_push($params, $value);
          $count++;
        }
      }

      $columns = implode(', ', $columns);
      $holders = implode(', ', $holders);
      $returning = Moedoo::buildReturn($table);

      $query = "INSERT INTO $table ($columns) VALUES ($holders);";

      try {
        // `Moedoo::executeQuery` return last inserted ID, internally referred as `RowID` or `OID`
        $rowId = Moedoo::executeQuery($table, $query, $params);

        // fetching the last inserted row
        $rows = Moedoo::select($table, ['RowID' => $rowId], null, $depth);

        // currently we're only supporting single row insert
        if (count($rows) === 1) {
          $rows = Moedoo::referenceFK($table, $rows, $depth);
          return $rows[0];
        } else {
          throw new Exception('error processing query', 1);
        }
      } catch (Exception $e) {
        throw new Exception($e->getMessage(), 1);
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
        array_push($set, $column." = :$count");
        array_push($params, $value);
        $count++;
      }

      $set = implode(', ', $set);
      array_push($params, $id);

      $query = "UPDATE $table SET $set WHERE ". Config::get('TABLES')[$table]['pk'] ." = :$count;";
      try {
        $changeCount = Moedoo::executeQuery($table, $query, $params);

        if ($changeCount === 1) {
          //-> currently we're only supporting single row update
          // RETURNING
          $rows = Moedoo::select($table, [Config::get('TABLES')[$table]['pk'] => $id], null, $depth);
          $rows = Moedoo::referenceFK($table, $rows, $depth);
          return $rows[0];
        } else {
          //-> no row was affected, returns the data back...
          throw new Exception("`$table` entry with id `$id` does not exist", 1);
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
        throw new Exception("`$table` with resource id `$id` does not exist", 1);
      } else {
        $query = "DELETE FROM $table WHERE ". Config::get('TABLES')[$table]['pk'] .'= :1;';

        try {
          $changeCount = Moedoo::executeQuery($table, $query, $params);

          if ($changeCount === 1) {
            //-> currently we're only supporting single row deletion
            return $rows[0];
          } else {
            throw new Exception("`$table` with resource id `$id` does not exist", 1);
          }
        } catch (Exception $e) {
          throw new Exception($e->getMessage(), 1);
        }
      }
    }
  }
