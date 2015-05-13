```php
Util::generate_token($length)
Util::to_array($array)
Util::validatePayload($table, $payload) - checks weather or not the payload corresponds to the config file
Util::JSON($data, $statusCode)
Util::hash($string)
Util::halt($message, $status) - halts execution (destroys session too)
Util::stop($message, $status) - halts execution (session still intact)
Util::clear_session();

Moedoo::db($host, $port, $user, $password, $dbname)
Moedoo::insert($table, $data)
Moedoo::select($table, $id)
Moedoo::delete($table, $id)
Moedoo::update($table, $data, $update)
Moedoo::search($table, $query) - /table?q=query

Rock::authenticated()
Rock::login($username, $password)
Rock::logout()
Rock::check() - run restricted and forbidden checks
```
