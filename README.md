```
          .___________. __    __   _______
          |           ||  |  |  | |   ____|
          `---|  |----`|  |__|  | |  |__
              |  |     |   __   | |   __|
              |  |     |  |  |  | |  |____
              |__|     |__|  |__| |_______|

      .______        ______     ______  __  ___
      |   _  \      /  __  \   /      ||  |/  /
      |  |_)  |    |  |  |  | |  ,----'|  '  /
      |      /     |  |  |  | |  |     |    <
      |  |\  \----.|  `--'  | |  `----.|  .  \
      | _| `._____| \______/   \______||__|\__\
```

a set of static helper functions added on top of [Slim](https://github.com/slimphp/Slim) that make my 9-5 life easy

###why PHP
i don't have a choice, PHP is the only thing i can afford to host on a "production" scale

###note from Moe Szyslak
don't expect anything fancy --- it's PHP

```php
Util::random_string($length)
Util::to_array($body)
Util::JSON($data, $statusCode)
Util::hash($string)
Util::halt($status, $message) - halts execution (destroys session too)

Moedoo::db($host, $port, $user, $password, $dbname)
Moedoo::insert($table, $data)
Moedoo::select($table, $id)
Moedoo::delete($table, $id)
Moedoo::update($table, $data, $update)
Moedoo::search($table, $query) - /table?q=query

Rock::authenticated()
Rock::authenticate($username, $password)
Rock::validate_payload($table, $payload) - checks weather or not the payload corresponds to the config file
Rock::login($username, $password)
Rock::check($method, $table) - return simple security checks on CRUD mapping
```
