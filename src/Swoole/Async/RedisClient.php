<?php
namespace Swoole\Async;

/**
 * Class Redis
 *
 * @method set
 * @method get
 * @method select
 * @method hexists
 * @method sadd
 * @method sMembers
 * @package Swoole\Async
 */
class RedisClient
{
    public $host;
    public $port;
    public $debug = false;

    /**
     * 空闲连接池
     * @var array
     */
    public $pool = array();

    public function __construct($host = 'localhost', $port = 6379, $timeout = 0.1)
    {
        $this->host = $host;
        $this->port = $port;
    }

    function trace($msg)
    {
        if ($this->debug)
        {
            echo "-----------------------------------------\n".trim($msg)."\n-----------------------------------------\n";
        }
    }

    function stats()
    {
        $stats = "Idle connection: ".count($this->pool)."<br />\n";
        return $stats;
    }

    function hmset($key, array $value, $callback)
    {
        $lines[] = "hmset";
        $lines[] = $key;
        foreach($value as $k => $v)
        {
            $lines[] = $k;
            $lines[] = $v;
        }
        $connection = $this->getConnection();
        $cmd = $this->parseRequest($lines);
        $connection->command($cmd, $callback);
    }

    function hmget($key, array $value, $callback)
    {
        $connection = $this->getConnection();
        $connection->fields = $value;

        array_unshift($value, "hmget", $key);
        $cmd = $this->parseRequest($value);
        $connection->command($cmd, $callback);
    }

    function parseRequest($array)
    {
        $cmd = '*' . count($array) . "\r\n";
        foreach ($array as $item)
        {
            $cmd .= '$' . strlen($item) . "\r\n" . $item . "\r\n";
        }
        return $cmd;
    }

    public function __call($method, array $args)
    {
        $callback = array_pop($args);
        array_unshift($args, $method);
        $cmd = $this->parseRequest($args);
        $connection = $this->getConnection();
        $connection->command($cmd, $callback);
    }

    /**
     * 从连接池中取出一个连接资源
     * @return RedisConnection
     */
    protected function getConnection()
    {
        if (count($this->pool) > 0)
        {
            foreach($this->pool as $k => $connection)
            {
                unset($this->pool[$k]);
                return $connection;
            }
        }
        else
        {
            return new RedisConnection($this);
        }
    }

    function lockConnection($id)
    {
        unset($this->pool[$id]);
    }

    function freeConnection($id, $connection)
    {
        $this->pool[$id] = $connection;
    }
}

class RedisConnection
{
    /**
     * @var RedisClient
     */
    protected $redis;
    protected $buffer = '';
    /**
     * @var \swoole_client
     */
    protected $client;
    protected $callback;

    /**
     * 等待发送的数据
     */
    protected $wait_send;
    protected $wait_recv;
    protected $multi_line = false;
    protected $require_line_n = 0;
    public $fields;

    function __construct(RedisClient $redis)
    {
        $client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $client->on('connect', array($this, 'onConnect'));
        $client->on('error', array($this, 'onError'));
        $client->on('receive', array($this, 'onReceive'));
        $client->on('close', array($this, 'onClose'));
        $client->connect($redis->host, $redis->port);
        $this->client = $client;
        $redis->pool[$client->sock] = $this;
        $this->redis = $redis;
    }

    /**
     * 执行redis指令
     * @param $cmd
     * @param $callback
     */
    function command($cmd, $callback)
    {
        /**
         * 如果已经连接，直接发送数据
         */
        if ($this->client->isConnected())
        {
            $this->client->send($cmd);
        }
        /**
         * 未连接，等待连接成功后发送数据
         */
        else
        {
            $this->wait_send = $cmd;
        }
        $this->callback = $callback;
        //从空闲连接池中移除，避免被其他任务使用
        $this->redis->lockConnection($this->client->sock);
    }

    function onConnect(\swoole_client $client)
    {
        if ($this->wait_send)
        {
            $client->send($this->wait_send);
            $this->wait_send = '';
        }
    }

    function onError()
    {
        echo "连接redis服务器失败\n";
    }

    function onReceive($cli, $data)
    {
        $this->redis->trace($data);
        if ($this->wait_recv)
        {
            $this->buffer .= $data;
            if ($this->multi_line)
            {
                $rn_count = substr_count($this->buffer, "\r\n");
                if ($this->require_line_n == $rn_count)
                {
                    goto parse_multi_line;
                }
                else
                {
                    return;
                }
            }
            else
            {
                //就绪
                if (strlen($this->buffer) == $this->wait_recv)
                {
                    goto ready;
                }
                else
                {
                    return;
                }
            }
        }
        $success = true;
        $lines = explode("\r\n", $data);
        $type = $lines[0][0];
        if ($type == '-')
        {
            $success = false;
            $result = substr($lines[0], 1);
        }
        elseif ($type == '+')
        {
            $result = substr($lines[0], 1);;
        }
        //只有一行数据
        elseif ($type == '$')
        {
            $len = intval(substr($lines[0], 1));
            if ($len > strlen($lines[1]))
            {
                $this->wait_recv = $len;
                $this->buffer = $lines[1];
                $this->multi_line = false;
            }
            $result = $lines[1];
        }
        //多行数据
        elseif ($type == '*')
        {
            parse_multi_line:
            $data_line_num = intval(substr($lines[0], 1));
            //echo "data_line_num=$data_line_num\n";
            //ready
            //TODO: 这里解析是不对的，有不存在key会导致失败
            if ($data_line_num == (count($lines) / 2) - 1)
            {
                $result = array();
                for ($i = 1; $i <= $data_line_num; $i++)
                {
                    //echo $i."\n";
                    if ($this->fields)
                    {
                        $result[$this->fields[$i - 1]] = $lines[$i * 2];
                    }
                    else
                    {
                        $result[] = $lines[$i * 2];
                    }
                }
                if ($this->fields)
                {
                    $this->fields = false;
                }
                $this->multi_line = false;
                $this->require_line_n = 0;
            }
            else
            {
                $this->multi_line = true;
                $this->require_line_n = $data_line_num * 2 + 2;
                $this->buffer = $data;
            }
        }
        elseif ($type == ':')
        {
            $result = intval(substr($lines[0], 1));
        }
        else
        {
            echo "Response is not a redis result. String:\n$data\n";
            return;
        }

        ready:
        $this->redis->freeConnection($cli->sock, $this);
        call_user_func($this->callback, $result, $success);
    }

    function onClose(\swoole_client $cli)
    {
        if ($this->wait_send)
        {
            $this->redis->freeConnection($cli->sock, $this);
            call_user_func($this->callback, "timeout", false);
        }
    }
}
