<?php
require __DIR__.'/../src/Swoole/Async/RedisClient.php';
$redis = new Swoole\Async\RedisClient('127.0.0.1');

$redis->hmget('hash2', array('key1', 'key2'), function ($result, $success) {
    var_dump($result);
});
