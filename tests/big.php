<?php
require __DIR__.'/../src/Swoole/Async/RedisClient.php';
$redis = new Swoole\Async\RedisClient('127.0.0.1');

$redis->get("big", function ($result, $success) {
    echo strlen($result);
});
