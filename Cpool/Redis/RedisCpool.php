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
     * 
     */
    const NEXT_KEY = 'next_key';

    /**
     * 
     */
    const VALUE_KEY = 'value';

    /**
     * 
     */
    const CREATE_AT_KEY = 'create_at';

    /**
     * 
     */
    const UPDATE_AT_KEY = 'update_at';

    /**
     * php web 不能常驻内存 因此用redis的键存储相关信息
     * @var string 
     */
    const FASTKEY = 'cpool_fast';

    /**
     * php web 不能常驻内存 因此用redis的键存储相关信息
     * @var string 
     */
    const LASTKEY = 'cpool_last';

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
        if (!$this->exist($key)) {
            return false;
        }
        $key = $this->_buildKey($key);
        return $this->_rc->hGetAll($key);
        ;
    }

    public function getField($key, $field) {
        return $this->_rc->hGet($this->_buildKey($key), $field);
    }

    /**
     * 设置hash值
     * @param string $key
     * @param string $value
     * @param string $updateAt
     * @param string $createAt
     */
    public function set($key, $value, $updateAt = null, $createAt = null) {
        $bkey = $this->_buildKey($key);
        if (!$updateAt) {
            $updateAt = time();
        }
        if (!$createAt) {
            $createAt = time();
        }
        if (!$this->_rc->exists($bkey)) {//新建
            if ($this->exist(self::LASTKEY)) {
                $this->updateNextKey($this->_getLastKey(), $key); //把上一次最后一个的next_value 指向本key
            }
            $this->_rc->hSet($bkey, self::CREATE_AT_KEY, $createAt);
            $this->_setLastKey($key);
            if (!$this->exist(self::FASTKEY)) {
                $this->_setFastKey($key);
            }
        }
        $this->_rc->hSet($bkey, self::VALUE_KEY, $value);
        $this->_rc->hSet($bkey, self::UPDATE_AT_KEY, $updateAt);
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
    public function updateNextKey($key, $nextKey) {
        $key = $this->_buildKey($key);
        return $this->_rc->hSet($key, self::NEXT_KEY, $nextKey);
    }

    /**
     * 查询是否存在
     * @param type $key
     * @return type
     */
    public function exist($key) {
        return $this->_rc->exists($this->_buildKey($key));
    }

    /**
     * 
     * @return type
     */
    private function _getLastKey() {
        return $this->getField(self::LASTKEY, self::VALUE_KEY);
    }

    /**
     * 不用集成的set 因为这是辅助的主体逻辑如果走主题逻辑设置 逻辑判断复杂而且不清楚
     */
    private function _setLastKey($key) {
        return $this->_rc->hSet($this->_buildKey(self::LASTKEY), self::VALUE_KEY, $key);
    }

    /**
     * 
     * @return type
     */
    private function _getFastKey() {
        return $this->getField(self::FASTKEY, self::VALUE_KEY);
    }

    /**
     * 设置链表开头
     * 不用集成的set 因为这是辅助的主体逻辑如果走主题逻辑设置 逻辑判断复杂而且不清楚
     */
    private function _setFastKey($key) {
        return $this->_rc->hSet($this->_buildKey(self::FASTKEY),self::VALUE_KEY, $key);
    }

    /**
     * 
     */
    public function scan() {
        while ($i_iterator !== 0) {
            $data = $this->_rc->scan($i_iterator, null, null);
            if (empty($data)) {
                break;
            }
            foreach ($data as $dv) {
                //$this->_rc->del($dv);
                echo $dv . PHP_EOL . var_dump($this->_rc->hGetAll($dv));
            }
        }
    }

}
