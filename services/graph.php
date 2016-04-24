<?php
  $__REST__['graph'] = function($routeInfo) {
    $authRequests = Config::get('AUTH_REQUESTS')['GET'];
    $authForbiddenRequests = Config::get('FORBIDDEN_REQUESTS')['GET'];
    $user = Rock::hasValidToken();
    $ql = trim($routeInfo[2]['ql'], '[]');
    $ql = preg_split('/\,/', $ql);
    $return = [];

    foreach ($ql as $key => $table) {
      $depth = array_key_exists('depth', Config::get('TABLES')[$table]) === true ? Config::get('TABLES')[$table]['depth'] : Config::get('DEFAULT_DEPTH');

      // checking table exits...
      if (array_key_exists($table, Config::get('TABLES')) === false) {
        $return[$table] = null;
      }

      // checking table is not forbidden...
      elseif (in_array($table, $authForbiddenRequests) === true) {
        $return[$table] = [];
      }

      // "normal", proceeding with permission check...
      // 1: resource requires token
      // 2: resource does not require token
      else {
        // resource requires auth...
        if (in_array($table, $authRequests) === true) {
          // user doesn't have a valid token
          if ($user === false) {
            $return[$table] = [];
          }

          // user has valid permission
          elseif(Rock::hasPermission($user, "user_group_has_permission_read_{$table}") === true) {
            $return[$table] = Moedoo::select($table, null, null, $depth);
          }

          // user does not have a permission mapping
          else {
            $return[$table] = [];
          }
        }

        // resource is public
        else {
          $return[$table] = Moedoo::select($table, null, null, $depth);
        }
      }
    }

    Rock::JSON($return, 200);
  };
