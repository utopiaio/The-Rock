#The Rock
a set of static helper functions added on top of [FastRoute](https://github.com/nikic/FastRoute) and [Pimple](https://github.com/silexphp/Pimple) that make my 9-5 life easy.

Most of the REST API is controlled via `config.php` --- I'll try to make a wiki page.

###Requirements
as it's SPECIFICALLY designed to work on shared hosting all you need is
- PHP5.4+
- PG database 8+

###The kitchen sink
three musketeers `Util`, `Moedoo` and `Rock` form `The Rock`
- Util: utility functions
- Moedoo: basic PG DB "engine"
- Rock: ...can you smell what the rock is cooking

####Util
```php
Util::randomString($length) // returns a random string with length of `$length`
Util::toArray($body) // returns an associative array of a VALID body string
```

####Moedoo
```php
Moedoo::cast($table, $rows) // returns appropriately casted values
Moedoo::db($host, $port, $user, $password, $dbname) // returns db resource
Moedoo::search($table, $q, $depth) // perform full-text search on table
Moedoo::select($table, $and, $or, $depth, $limit, $offset) // performs `select`
Moedoo::insert($table, $data, $depth) // performs `insert`
Moedoo::update($table, $data, $id, $depth) // performs `update`
Moedoo::delete($table, $id) // performs `delete`
Moedoo::count($table) // returns row count on table
```

####Rock
```php
Rock::authenticated($role) // authenticates the JWT
Rock::authenticate($username, $password) // authenticates and returns JWT
Rock::check($method, $table, $role) // runs security checks via `config`
Rock::getBody($table) // validates and returns request body
Rock::JSON($data, $status) // returns JSON
Rock::halt($status, $message) // halts execution
Rock::hash($string) // returns the hash (set via `config.php`) of the string
Rock::getHeaders() // returns request headers
Rock::getUrl() // returns URL
```

###Why PHP
i don't have a choice, PHP is the only thing i can afford to host on a "production" scale

###License
MIT

###Contribution
any contribution is welcome!
