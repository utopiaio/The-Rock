<?php
  $__REST__['REST'] = function($routeInfo) {
    $user = Rock::hasValidToken();
    $table = strtolower($routeInfo[2]['table']);
    $depth = array_key_exists('depth', Config::get('TABLES')[$table]) === true ? Config::get('TABLES')[$table]['depth'] : Config::get('DEFAULT_DEPTH');
    $id = array_key_exists('id', $routeInfo[2]) === true ? $routeInfo[2]['id'] : -1;
    $count = array_key_exists('count', $routeInfo[2]);

    switch ($_SERVER['REQUEST_METHOD']) {
      case 'GET':
        if (isset($_GET['q']) === true && $id === -1) {
          //-> not using `is_numeric` to avoid decimals as $limit
          $limit = (isset($_GET['limit']) === true && preg_match('/^\d+$/', $_GET['limit'])) ? $_GET['limit'] : -1;
          Rock::JSON([
            'data' => Moedoo::search($table, $_GET['q'], $limit, $depth),
            'included' => Moedoo::included(),
          ], 200);
        } elseif ($count === true) {
          Rock::JSON([
            'count' => Moedoo::count($table),
          ]);
        } elseif (isset($_GET['limit']) === true && preg_match('/^\d+$/', $_GET['limit']) === 1) {
          $limit = $_GET['limit'];
          $count = 0;

          if (isset($_GET['offset']) === true && preg_match('/^\d+$/', $_GET['offset']) === 1) {
            $count = $_GET['offset'];
          }

          Rock::JSON([
            'data' => Moedoo::select($table, null, null, $depth, $limit, $count),
            'included' => Moedoo::included(),
          ], 200);
        }

        else {
          // selects takes depth by reference, so we'll be passing a copy
          $result = $id === -1 ? Moedoo::select($table, null, null, $depth) : Moedoo::select($table, [Config::get('TABLES')[$table]['pk'] => $id], null, $depth);

          if ($id === -1) {
            Rock::JSON([
              'data' => $result,
              'included' => Moedoo::included(),
            ], 200);
          } elseif (count($result) === 1) {
            Rock::JSON([
              'data' => $result[0],
              'included' => Moedoo::included(),
            ], 200);
          } else {
            Rock::halt(404, "`$table` with id `$id` does not exist");
          }
        }
      break;

      case 'POST':
        $body = Rock::getBody($table);

        if (isset($body[0]) === false) {
          //-> processing single entry...
          switch ($table) {
            case 'user':
              if (array_key_exists('user_username', $body) === true) {
                $body['user_username'] = strtolower($body['user_username']);
                $body['user_username'] = preg_replace('/ /', '_', $body['user_username']);
              }

              if (array_key_exists('user_password', $body) === true && strlen($body['user_password']) > 3) {
                $body['user_password'] = Rock::hash($body['user_password']);
              } else {
                Rock::halt(400, 'invalid password provided');
              }
            break;
          }

          try {
            $result = Moedoo::insert($table, $body, $depth);
          } catch (Exception $e) {
            Rock::halt(400, $e->getMessage());
          }

          Rock::JSON([
            'data' => $result,
            'included' => Moedoo::included(),
          ], 201);
        } else {
          //-> processing multiple entry...
          $result = [];

          foreach ($body as $index => $entry) {
            switch ($table) {
              case 'user':
                if (array_key_exists('user_username', $entry) === true) {
                  $entry['user_username'] = strtolower($entry['user_username']);
                  $entry['user_username'] = preg_replace('/ /', '_', $entry['user_username']);
                }

                if (array_key_exists('user_password', $entry) === true && strlen($entry['user_password']) > 3) {
                  $entry['user_password'] = Rock::hash($entry['user_password']);
                } else {
                  Rock::halt(400, 'invalid password provided');
                }
              break;
            }

            try {
              $entryDepth = $depth;
              array_push($result, Moedoo::insert($table, $entry, $entryDepth));
            } catch (Exception $e) {
              Rock::halt(400, $e->getMessage());
            }
          }

          Rock::JSON([
            'data' => $result,
            'included' => Moedoo::included(),
          ], 201);
        }
      break;

      case 'PATCH':
        $body = Rock::getBody($table);

        switch ($table) {
          case 'user':
            if (array_key_exists('user_username', $body) === true) {
              $body['user_username'] = strtolower($body['user_username']);
              $body['user_username'] = preg_replace('/ /', '_', $body['user_username']);
            }

            if (array_key_exists('user_password', $body) === true && strlen($body['user_password']) > 3) {
              $body['user_password'] = Rock::hash($body['user_password']);
            } else {
              unset($body['user_password']);
            }
          break;
        }

        try {
          $result = Moedoo::update($table, $body, $id, $depth);
        } catch (Exception $e) {
          Rock::halt(400, $e->getMessage());
        }

        Rock::JSON([
          'data' => $result,
          'included' => Moedoo::included(),
        ], 202);
      break;

      case 'DELETE':
        try {
          $result = Moedoo::delete($table, $id);
        } catch (Exception $e) {
          Rock::halt(400, $e->getMessage());
        }

        Rock::JSON([
          'data' => $result,
          'included' => Moedoo::included(),
        ], 202);
      break;
    }
  };
