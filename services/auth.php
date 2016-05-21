<?php
  $__REST__['auth'] = function ($routeInfo) {
    switch ($_SERVER['REQUEST_METHOD']) {
      case 'GET':
        $requestHeaders = Rock::getHeaders();

        if(array_key_exists(Config::get('JWT_HEADER'), $requestHeaders) === true) {
          try {
            $decoded = (array)Firebase\JWT\JWT::decode($requestHeaders[Config::get('JWT_HEADER')], Config::get('JWT_KEY'), ['HS256']);
          } catch(Exception $e) {
            Rock::halt(401, 'invalid authorization token');
          }

          $depth = 1;
          $result = Moedoo::select('user', [Config::get('TABLES')['user']['pk'] => $decoded['id']], null, $depth);

          if(count($result) === 1) {
            $user = $result[0];
            $token = [
              'iss' => Config::get('JWT_ISS'),
              'iat' => strtotime(Config::get('JWT_IAT')),
              'id' => $user['user_id']
            ];

            // TODO
            // make a fingerprint so that the token stays locked-down
            $jwt = Firebase\JWT\JWT::encode($token, Config::get('JWT_KEY'));
            Rock::JSON(['jwt' => $jwt, 'user' => $user], 202);
          }

          else {
            Rock::halt(401, 'token no longer valid');
          }
        }

        else {
          Rock::halt(401, 'missing authentication header `'. Config::get('JWT_HEADER') .'`');
        }
      break;

      case 'POST':
        $body = Rock::getBody();

        if(!array_key_exists('username', $body) || !array_key_exists('password', $body)) {
          Rock::halt(400, 'body must have `username` and `password`');
        }

        Rock::authenticate($body['username'], $body['password']);
      break;
    }
  };
