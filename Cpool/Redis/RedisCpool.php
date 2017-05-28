<?php

namespace Cpool\Redis;

use Cpool\Cpool;
use Cpool\CpException;

/*
 * redis 实现缓存池
 */

class RedisCpool implements Cpool {

    /**
     *
     * @var RedisCpool 
     */
    private $_rc;

    public function __construct($config) {

        //连接本地的 Redis 服务
        $redis = new \Redis();
        try {

            $redis->connect($config['host'], $config['port']);
            $redis->ping();
        } catch (\RedisException $exc) {
            throw new CpException($exc->getMessage());
        }

        $this->$_rc = $redis;
    }

    public function get($key) {
        ;
    }

    public function set($key, $value) {
        ;
    }

    public function del($key) {
        ;
    }

    public function update($key, $val) {
        ;
    }

}
