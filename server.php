<?php
$http = new swoole_http_server("127.0.0.1", 9501);
$http->set(['worker_num' => 8]);
require __DIR__.'/src/Swoole/Async/RedisClient.php';
$redis = new Swoole\Async\RedisClient('127.0.0.1');

$http->on('request', function ($request, swoole_http_response $response) use ($redis) {
    if (isset($request->get['status'])) {
        $response->end($redis->stats());
    } else {
        $redis->get(
            'hello',
            function ($result, $success) use ($response) {
                if (!$success) {
                    echo "get from redis failed\n";
                }
                $response->end("<h1>Hello Swoole. value=" . $result . "</h1>");
            }
        );
    }
});

$http->start();