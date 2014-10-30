<?php
$http = new swoole_http_server("127.0.0.1", 9501);
require __DIR__.'/src/Swoole/Async/RedisClient.php';
$redis = new Swoole\Async\RedisClient('127.0.0.1');

$http->on('request', function ($request, $response) use ($redis) {
    $redis->get('key1', function($result) use($response) {
        $response->end("<h1>Hello Swoole. value=".$result."</h1>");
    });
});
$http->start();