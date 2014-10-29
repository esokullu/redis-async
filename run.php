<?php
require __DIR__.'/src/Swoole/Async/RedisClient.php';
$redis = new Swoole\Async\RedisClient('127.0.0.1');

function redis_result($result, $success)
{
    echo "redis ok:\n";
    var_dump($success, $result);
}

$redis->select('2', function () use ($redis) {
    $redis->set('key', 'value-rango', function ($result, $success) use ($redis) {
        for ($i = 0; $i < 3; $i++) {
            $redis->get('key', 'redis_result');
        }
    });
});

