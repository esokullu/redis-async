<?php
require __DIR__.'/../src/Swoole/Async/RedisClient.php';
$redis = new Swoole\Async\RedisClient('127.0.0.1');

$redis->hmset('hash2', array('key1' => 102, 'key2' => 'hello'), function ($result, $success) {
        echo $result;
});
