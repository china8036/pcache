<?php

namespace Cpool\Redis;

use Cpool\Cpool;
use Cpool\CpException;

/*
 * redis 实现缓存池
 */

class RedisCpool {

    /**
     *
     * @var \Redis 
     */
    private $_rc;

    /**
     * 键的前缀
     * @var string
     */
    private $_keyPre = '';

    /**
     * 初始化
     * @param array $config
     * @throws CpException
     */
    public function __construct(array $config) {

        $this->_initConfig($config);
        //连接本地的 Redis 服务
        $redis = new \Redis();
        try {
            $redis->connect($config['host'], $config['port']);
            $redis->auth($config['passwd']);
            $redis->ping();
        } catch (\RedisException $exc) {
            throw new CpException($exc->getMessage());
        }
        $this->_keyPre = $config['pre'];
        $this->_rc = $redis;
    }

    /**
     * 
     * @param array $config
     */
    private function _initConfig(array &$config) {
        $config['host'] = $config['host'] ?: '127.0.0.1';
        $config['port'] = $config['port'] ?: 6379;
        $config['pre'] = $config['pre'] ?: 'cpool_';
    }

    
    /**
     * 加下健值前缀
     * @param string $key
     * @return string
     */
    private function _buildKey($key) {
        return $this->_keyPre . $key;
    }

    /**
     * 获取值
     * @param type $key
     * @return type
     */
    public function get($key) {
        $key = $this->_buildKey($key);
        if (!$this->_rc->exists($key)) {
            return false;
        }
        return $this->_rc->hGetAll($key);
        ;
    }

    /**
     * 设置hash值
     * @param string $key
     * @param string $value
     * @param string $updateAt
     * @param string $createAt
     */
    public function set($key, $value, $updateAt = null, $createAt = null) {
        $key = $this->_buildKey($key);
        if (!$updateAt) {
            $updateAt = time();
        }
        if (!$createAt) {
            $createAt = time();
        }
        $this->_rc->hSet($key, 'value', $value);
        $this->_rc->hSet($key, 'update_at', $updateAt);
        $this->_rc->hSet($key, 'create_at', $createAt);
    }

    /**
     * xxxx
     * @param type $key
     * @return int 1 success 0 fail
     */
    public function del($key) {
        $key = $this->_buildKey($key);
        return $this->_rc->del($key);
    }

    /**
     * 更新健值
     * @param string $key
     * @param string $val
     * @param int $updateTime
     * @return int 1 success 0 fail
     */
    public function update($key, $val, $updateTime) {
        $key = $this->_buildKey($key);
        $this->_rc->hSet('updateAt', $updateTime);
        return $this->_rc->hSet($key, 'value', $val);
    }

    /**
     * 
     */
    public function scan() {
        while ($i_iterator !== 0) {
            var_dump($this->_rc->hscan('test', $i_iterator, null, null));
        }
    }

}
