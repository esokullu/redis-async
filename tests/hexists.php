<?php
require __DIR__.'/../src/Swoole/Async/RedisClient.php';
$redis = new Swoole\Async\RedisClient('127.0.0.1');

$redis->hexists('hash2', 'key222', function ($result, $success) {
    var_dump($result);
});

