#The Rock
a set of static helper functions added on top of [Slim](https://github.com/slimphp/Slim) that make my 9-5 life easy.

Most of the REST API is controlled via `config.php` --- I'll try to make a wiki page.

###Requirements
as it's SPECIFICALLY designed to work on shared hosting all you need is
- PHP5+
- PG database 8+

###the kitchen sink
three musketeers `Util`, `Moedoo` and `Rock` form `The Rock`
- Util: utility functions
- Moedoo: basic PG DB "engine"
- Rock: ...can you smell what the rock is cooking

####Util
```php
Util::randomString($length) // returns a random string with length of `$length`
Util::toArray($body) // returns an associative array of a VALID body string
Util::JSON($data, $status) // return a JSON encoded response
Util::hash($string) // returns the hash (set via `config.php`) of the string
Util::halt($status, $message) // halts execution
```

####Moedoo
```php
Moedoo::cast($table, $rows) // returns appropriately casted values
Moedoo::db($host, $port, $user, $password, $dbname) // returns db resource
Moedoo::search($table, $q) // perform full-text search on table
Moedoo::select($table, $and, $or) // performs `select` operation
Moedoo::insert($table, $data) // performs `insert` operation
Moedoo::update($table, $data, $id) // performs `update` operation
Moedoo::delete($table, $id) // performs `delete` operation
```

####Rock
```php
Rock::authenticated($role) // authenticates the JWT
Rock::authenticate($username, $password) // authenticates and returns JWT
Rock::check($method, $table, $role) // runs security checks via `config`
Rock::getBody($table) // validates and returns request body
```

###why PHP
i don't have a choice, PHP is the only thing i can afford to host on a "production" scale

###Contribution
any contribution is welcome!
