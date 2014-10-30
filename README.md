redis-async
===========
* event-based, asynchronous non-blocking
* built-in connection pool
* support concurrent requests

How to use
------

####1. Install swoole extension
```shell
pecl install swoole
```

####2. Run example code
```php
require __DIR__.'/src/Swoole/Async/RedisClient.php';
$redis = new Swoole\Async\RedisClient('127.0.0.1');

$redis->select('2', function () use ($redis) {
    $redis->set('key', 'value-rango', function ($result, $success) use ($redis) {
        for ($i = 0; $i < 3; $i++) {
            $redis->get('key', function ($result, $success) {
                echo "redis ok:\n";
                var_dump($success, $result);
            });
        }
    });
});
```
