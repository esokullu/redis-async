<?php
require __DIR__.'/../src/Swoole/Async/RedisClient.php';
$redis = new Swoole\Async\RedisClient('127.0.0.1');
//$redis->debug = true;
$redis->hmget('hash2', array('key1', 'noexists', 'key2', 'key3'), function ($result, $success) {
    var_dump($result);
});
