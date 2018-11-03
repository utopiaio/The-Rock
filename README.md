![The Rock](https://raw.githubusercontent.com/utopiaio/The-Rock/master/__S3__/TheRock.png "The Rock")

# The Rock
REST on a budget.

### Requirements
As it's **specifically** designed to work on shared hosting all you need is
- PHP5.4+

### The kitchen sink
Three musketeers `Util`, `Moedoo` and `Rock` form `The Rock`
- Util: utility functions
- Moedoo: basic SQLite _engine_ (for PostgreSQL version checkout the [pg](https://github.com/utopiaio/The-Rock/tree/pg) branch)
- Rock: ...can you smell what the rock is cooking

#### Util
```php
Util::randomString($length) // returns a random string with length of `$length`
Util::toArray($body) // returns an associative array of a valid body string
```

#### Moedoo
```php
Moedoo::cast($table, $rows) // returns appropriately casted values
Moedoo::db($path, $busyTimeout) // returns db resource
Moedoo::search($table, $q, $depth) // performs wild-card search on table
Moedoo::select($table, $and, $or, $depth, $limit, $offset) // performs `select`
Moedoo::insert($table, $data, $depth) // performs `insert`
Moedoo::update($table, $data, $id, $depth) // performs `update`
Moedoo::delete($table, $id) // performs `delete`
Moedoo::count($table) // returns row count on table
```

#### Rock
```php
Rock::authenticated($method, $table) // authenticates the JWT
Rock::authenticate($username, $password) // authenticates and returns JWT
Rock::check($method, $table) // runs security checks via `config`
Rock::getBody($table) // validates and returns request body
Rock::JSON($data, $status) // returns JSON
Rock::halt($status, $message) // halts execution
Rock::hash($string) // returns the hash (set via `config.php`) of the string
Rock::getHeaders() // returns request headers
Rock::MIMEIsAllowed() // MEME checking on S3
Rock::getUrl() // returns URL
```

### Setup
- Clone the app
- Run `$ composer install`
- Inside `config.php`, configure `ROOT_URL` (line 22), if the app lives in the root directory of from where it's going be served leave it empty. If the app lives inside another folder, example: `localhost:8080/rock/` then `ROOT_URL` becomes `/rock`
- Open `config.php`, configure the database, line 42-43
- Fire up your browser and hit `PathToRock/s3` for list of files

There you have it, `The Rock` should be running. You can get a pretty good understanding of what `The Rock` is trying to achieve by looking at the `config.php` file

### REST URL Mapping
- `GET /{tableName}` returns entire list
- `GET /{tableName}/id` returns entry of id
- `POST /{tableName}` saves data
- `PATCH /{tableName}/id` updates data
- `DELETE /{tableName}/id` deletes data
- `GET /{tableName}/count` return number of records
- `GET /@S3/{fileName}` returns file
- `DELETE /@S3/{fileName}` delete file
- `POST /@S3` save file

### Authentication
The Rock provides stateless, tailored authentication (see `user_group` table for more details). It uses JWT for authentication.

Using [Httpie](https://github.com/jkbrzt/httpie) `$ http POST http://PathToRock/auth username=utopiaio password=rada`

### License
MIT

### Contribution
contributions are welcome!
