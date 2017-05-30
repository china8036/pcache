<?php

namespace Cpool\Redis;

use Cpool\Cpool;
use Cpool\CpException;

/*
 * redis 实现缓存池
 * 1 更新操作
 * 2 
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
     * 最大记录数
     * @var int 
     */
    private $_MaxLen = 10;

    /**
     * 数据回调
     * @var type 
     */
    private $_dataAceess = '';

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
    const CACHE_KEY = 'cache_key';

    /**
     * 
     */
    const HIT_COUNT_KEY = 'hit_count';

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
     * 计数
     */
    const CONUTKEY = 'cpool_count';

    /**
     * 锁超时时间 锁会被手动删除 万一忘记手机删除 也只会在超时时间内被阻塞掉
     */
    const LOCK_TIMEOUT = 10;

    /**
     * 自定义锁
     */
    const LOCKKEY = 'cpool_lock';

    /**
     * 初始化
     * @param array $config
     * @throws CpException
     */
    public function __construct(array $config, callable $dataAccess) {

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
        if (!empty($config['maxLen']) && $config['maxLen'] > 1) {
            $this->_MaxLen = $config['maxLen'];
        }
        $this->_dataAceess = $dataAccess;
        $this->_keyPre = $config['pre'];
        $this->_rc = $redis;
    }

    /**
     * 
     * @param array $config
     */
    private function _initConfig(array &$config) {
        $config['host'] = empty($config['host']) ? '127.0.0.1' : $config['host'];
        $config['port'] = empty($config['port']) ? 6379 : $config['port'];
        $config['pre'] = empty($config['pre']) ? 'cpool_' : $config['pre'];
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
     * @param type $cacheKey
     * @return type
     */
    public function get($cacheKey) {
        $key = $this->cacheKey2Key($cacheKey);
        if (!$this->exist($key)) {
            $value = ($this->_dataAceess)($cacheKey);
            $this->_set($key, $cacheKey, $value);
            return $value;
        }
        $this->_rc->hIncrBy($this->_buildKey($key), self::HIT_COUNT_KEY, 1);
        return $this->getField($key, self::VALUE_KEY);
    }
    
    
    

    /**
     * cacheKey to Key
     * @param type $cacheKey
     * @param type $value
     * @return type
     */
    public function set($cacheKey, $value) {
        return $this->_set($this->cacheKey2Key($cacheKey), $cacheKey, $value);
    }

    /**
     * 
     * @param type $key
     * @return type
     */
    public function cacheKey2Key($key) {
        return md5($key);
    }

    /**
     * 
     * @param type $key
     * @param type $field
     * @return type
     */
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
    private function _set($key, $cacheKey, $value, $updateAt = null, $createAt = null) {
        $bkey = $this->_buildKey($key);
        if (!$updateAt) {
            $updateAt = time();
        }
        if (!$createAt) {
            $createAt = time();
        }

        if (!$this->_rc->exists($bkey)) {//新建
            $countKey = $this->_buildKey(self::CONUTKEY);
            $lockKey = $this->_buildKey(self::LOCKKEY);
            while (true) {
                $nowTime = microtime(true);
                if (!$this->_rc->setnx($lockKey, $nowTime)) {//如果未设置返回 true 已设置返回false  模拟原子操作但不是真正的原子操作可能会出问题
                    //以下是考虑锁定后程序立马崩溃的情况 这里暂时不考虑
                     $oldSetTime  = $this->_rc->get($lockKey);
                     if($oldSetTime - $nowTime > self::LOCK_TIMEOUT){//防止加锁进程 加锁后崩溃 造成死锁
                        return false;//一定时间获取不到就放弃
                    }
                    usleep(100000); //0.1秒
                    continue; //死循环
                }
                $this->_rc->expire($lockKey, self::LOCK_TIMEOUT); //设置超时时间
                $this->_rc->hSetNx($countKey, self::VALUE_KEY, 0); //没有时候才会设置
                $hasNum = $this->_rc->hGet($countKey, self::VALUE_KEY);
                if ($hasNum >= $this->_MaxLen) {//超过设定数目不允许再设置
                    $this->_rc->del($lockKey);
                    return false;
                }
                $this->_rc->hIncrBy($countKey, self::VALUE_KEY, 1); //先占用一个名额
                $this->_rc->del($lockKey);
                break;
            }

            if ($this->exist(self::LASTKEY)) {
                $this->updateNextKey($this->_getLastKey(), $key); //把上一次最后一个的next_value 指向本key
            }
            $this->_rc->hSet($bkey, self::CACHE_KEY, $cacheKey);
            $this->_rc->hSet($bkey, self::HIT_COUNT_KEY, 0);
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
     * 删除一个key 
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
        return $this->_rc->hSet($this->_buildKey(self::FASTKEY), self::VALUE_KEY, $key);
    }

    /**
     * 获取数量
     * @return type
     */
    public function getCount() {
        $key = $this->_buildKey(self::CONUTKEY);
        $this->_rc->multi();
        if (!$this->_rc->exists($key)) {
            $this->_rc->hSet($key, self::VALUE_KEY, 0);
        }
        $this->_rc->exec();
        return $this->_rc->hGet($key, self::VALUE_KEY);
    }

    /**
     * 
     */
    public function scan() {
        while (!isset($i_iterator) || $i_iterator !== 0) {
            $data = $this->_rc->scan($i_iterator, null, null);
            if (empty($data)) {
                break;
            }
            foreach ($data as $dv) {
                echo $dv . ':' . var_export($this->_rc->hGetAll($dv), true) . PHP_EOL;
            }
        }
    }

    /**
     * 
     */
    public function clear() {
        while (!isset($i_iterator) || $i_iterator !== 0) {
            $data = $this->_rc->scan($i_iterator, null, null);
            if (empty($data)) {
                break;
            }
            foreach ($data as $dv) {
                $this->_rc->del($dv);
            }
        }
    }

}
