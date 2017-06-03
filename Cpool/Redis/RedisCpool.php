<?php

namespace Cpool\Redis;

use Cpool\Cpool;
use Cpool\CpException;

/*
 * redis 实现缓存池
 * 1 更新操作
 * 2 
 */

class RedisCpool implements Cpool {

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
     * 缓存有效时间
     * @var int
     */
    private $_cacheTimeout = 1200;

    /**
     *
     */
    const NEXT_KEY = 'next_key';

    /**
     * pre
     */
    const PRE_KEY = 'pre_key';

    /**
     * 
     */
    const VALUE_KEY = 'value';

    /**
     * 
     */
    const LAST_HIT_TIME = 'last_hit_time';

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
    const FIRSTKEY = 'cpool_first';

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
     * 每一次loop的sleep时间 微秒 0.01秒
     */
    const LOOP_SLEEP_UTIME = 10000;

    /**
     * 自定义锁
     */
    const LOCKKEY = 'cpool_lock';

    /**
     * 初始化配置信息
     * @param array $config 配置信息
     * @param callable $dataAccess 请保证数据为字符串类型的
     * @throws CpException
     */
    public function __construct(array $config, callable $dataAccess) {

        $this->_initConfig($config);
        //连接本地的 Redis 服务
        $redis = new \Redis();
        try {
            $redis->connect($config['host'], $config['port']);
            if (!empty($config['passwd'])) {
                $redis->auth($config['passwd']);
            }
            $redis->ping();
        } catch (\RedisException $exc) {
            throw new CpException($exc->getMessage());
        }
        if (!empty($config['maxLen']) && $config['maxLen'] > 1) {
            $this->_MaxLen = $config['maxLen'];
        }
        if (!empty($config['cacheTimeout'])) {
            $this->_cacheTimeout = $config['cacheTimeout'];
        }
        $this->_dataAceess = $dataAccess;
        $this->_keyPre = $config['pre'];
        $this->_rc = $redis;
    }

    /**
     * 格式化配置信息
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
    public function get($cacheKey, $cacheTime = 0) {
        $key = $this->cacheKey2Key($cacheKey);
        $bkey = $this->_buildKey($key);
        if (!$this->_lock()) {//如果锁定失败则直接返回原始value
            return $this->getOriginData($cacheKey);
        }
        if ($this->exist($key)) {
            $cacheInfo = $this->_rc->hGetAll($bkey);
            $cacheTime = intval($cacheTime);
            if ($cacheTime > 0) {
                if ( microtime(true) - $cacheInfo[self::UPDATE_AT_KEY] > $cacheTime) {//更新缓存内容
                    $value = $this->getOriginData($cacheKey);
                    $this->_set($key, $cacheKey, $value); //更细缓存内容
                }
            }
            $this->_change2Last($key, $cacheInfo); //命中的排到尾部去 算是增加了权重(因为在表头的会优先被替代)
            $this->_rc->hIncrBy($bkey, self::HIT_COUNT_KEY, 1);
            $this->_rc->hSet($bkey, self::LAST_HIT_TIME, microtime(true)); //最后的命中时间
            $value = isset($value) ? $value : $cacheInfo[self::VALUE_KEY];
        } else {
            $value = $this->getOriginData($cacheKey);
            $this->_set($key, $cacheKey, $value);
        }
        $this->_unlock();
        return $value;
    }

    /**
     * 获取原始数据
     * @param type $cacheKey
     * @return type
     */
    public function getOriginData($cacheKey) {
        return call_user_func($this->_dataAceess, $cacheKey);
    }

    /**
     * 获取缓存超时时间
     * @return type
     */
    public function getCacheTimeoutVal() {
        return $this->_cacheTimeout;
    }

    /**
     * 存储
     * @param type $cacheKey
     * @param type $value
     * @return type
     */
    public function set($cacheKey, $value) {
        return $this->_set($this->cacheKey2Key($cacheKey), $cacheKey, $value);
    }

    /**
     * 转换关键词为 key
     * @param type $key
     * @return type
     */
    public function cacheKey2Key($key) {
        return md5($key);
    }

    /**
     * 获取一个键的某个字段的字
     * @param type $key
     * @param type $field
     * @return type
     */
    public function getField($key, $field) {
        return $this->_rc->hGet($this->_buildKey($key), $field);
    }

    /**
     * 设置hash值 应该放在锁定状态里防止冲突
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
            $this->_rc->hSetNx($countKey, self::VALUE_KEY, 0); //没有时候才会设置 初始化conut key
            $hasNum = $this->_rc->hGet($countKey, self::VALUE_KEY);
            if ($hasNum >= $this->_MaxLen) {//超过设定数目 判断第一个是否已失效
                $firstKey = $this->getFirstKey();
                $firstLastHitTime = $this->getField($firstKey, self::LAST_HIT_TIME);
                if (microtime(true) - $firstLastHitTime > $this->_cacheTimeout) {
                    $secondKey = $this->getField($firstKey, self::NEXT_KEY);
                    if ($secondKey) {//没有发现 标示最后一个
                        $this->_setFirstKey($secondKey); //设置第二个为链接头
                    } else {
                        $this->del(self::FIRSTKEY); //删除第一个 多为链表长度最大为一的情况
                    }
                    $this->_rc->hDel($this->_buildKey($secondKey), self::PRE_KEY); //删除前一元素记录
                    $this->del($firstKey); //删除第一个过期的缓存
                    $this->_rc->hIncrBy($countKey, self::VALUE_KEY, -1); //减去一个名额
                } else {//否则直接失败
                    return false; //直接退出
                }
            }
            //走到此处证明可以加入到缓存中
            $this->_rc->hIncrBy($countKey, self::VALUE_KEY, 1); //先占用一个名额
            if (!$this->exist(self::FIRSTKEY)) {//是否设置了链表头部
                $this->_setFirstKey($key);
            }
            if ($this->exist(self::LASTKEY)) {//如果有链表尾部 则把尾部的key的next key 设为本key
                $lastKey = $this->getLastKey();
                $this->updateNextKey($lastKey, $key); //把上一次最后一个的next_key 指向本key
                $this->_rc->hSet($bkey, self::PRE_KEY, $lastKey); //本key的上一个key
            }
            $this->_setLastKey($key); //设置本key为链表的尾部
            $this->_rc->hSet($bkey, self::CACHE_KEY, $cacheKey);
            $this->_rc->hSet($bkey, self::HIT_COUNT_KEY, 0);
            $this->_rc->hSet($bkey, self::CREATE_AT_KEY, $createAt);
            $this->_rc->hSet($bkey, self::LAST_HIT_TIME, $createAt);
        } else {//更新操作
        }
        $this->_rc->hSet($bkey, self::VALUE_KEY, $value);
        $this->_rc->hSet($bkey, self::UPDATE_AT_KEY, $updateAt); //缓存更新时间
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
     * @return int 1 success 0 fail
     */
    public function updateNextKey($key, $nextKey) {
        $key = $this->_buildKey($key);
        return $this->_rc->hSet($key, self::NEXT_KEY, $nextKey);
    }

    /**
     * 更新prekey
     * @param string $key
     * @param type $preKey
     * @return type
     */
    public function updatePreKey($key, $preKey) {
        $key = $this->_buildKey($key);
        return $this->_rc->hSet($key, self::PRE_KEY, $preKey);
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
     * 获取最后一个
     * @return type
     */
    public function getLastKey() {
        return $this->getField(self::LASTKEY, self::VALUE_KEY);
    }

    /**
     * 不用集成的set 因为这是辅助的主体逻辑如果走主题逻辑设置 逻辑判断复杂而且不清楚
     */
    private function _setLastKey($key) {
        return $this->_rc->hSet($this->_buildKey(self::LASTKEY), self::VALUE_KEY, $key);
    }

    /**
     * 获取第一个key
     * @return type
     */
    public function getFirstKey() {
        return $this->getField(self::FIRSTKEY, self::VALUE_KEY);
    }

    /**
     * 设置链表开头
     * 不用集成的set 因为这是辅助的主体逻辑如果走主题逻辑设置 逻辑判断复杂而且不清楚
     */
    private function _setFirstKey($key) {
        return $this->_rc->hSet($this->_buildKey(self::FIRSTKEY), self::VALUE_KEY, $key);
    }

    /**
     * 把链表中的一个元素移动到尾部
     * @param string $key
     * @param array $keyInfo
     * @return boolean
     */
    private function _change2Last($key, $keyInfo) {
        $lastKey = $this->getLastKey();
        if ($lastKey == $key) {//本来就是最后一个 直接返回成功
            return true;
        }
        $firstKey = $this->getFirstKey();
        if ($firstKey == $key) {
            $nextKey = $keyInfo[self::NEXT_KEY];
            $this->_setFirstKey($nextKey); //设置第二个为头部链表
            $this->_rc->hDel($this->_buildKey($nextKey), self::PRE_KEY); //删除它pre_key记录
        } else {
            $this->updateNextKey($keyInfo[self::PRE_KEY], $keyInfo[self::NEXT_KEY]); //设置上一个元素的下一个元素为自己的下一个元素
        }
        $this->updateNextKey($lastKey, $key); //更新链表尾部的next key为本key
        $this->updatePreKey($key, $lastKey); //更新本key的 pre key为上一次的链表尾部
        $this->_rc->hDel($this->_buildKey($key), self::NEXT_KEY); //删除next key 指向
        $this->_setLastKey($key); //设置本key为链表尾部
        return true;
    }

    /**
     * 获取数量
     * @return type
     */
    public function getCount() {
        $key = $this->_buildKey(self::CONUTKEY);
        $this->_rc->hSetNx($key, self::VALUE_KEY, 0); //没有的话设置为0 有的话跳过
        return $this->_rc->hGet($key, self::VALUE_KEY);
    }

    /**
     * 尝试在一定时间内锁定redis
     * @return boolean
     */
    private function _lock() {
        $lockKey = $this->_buildKey(self::LOCKKEY);
        while (true) {//尝试获取锁
            $nowTime = microtime(true);
            if (!$this->_rc->setnx($lockKey, $nowTime)) {//如果未设置返回 true 已设置返回false  模拟原子操作但不是真正的原子操作可能会出问题
                //以下是考虑锁定后程序立马崩溃的情况 这里暂时不考虑
                $oldSetTime = $this->_rc->get($lockKey);
                if ($nowTime - $oldSetTime > self::LOCK_TIMEOUT) {//防止加锁进程 加锁后崩溃 造成死锁
                    return false; //一定时间获取不到就放弃
                }
                usleep(self::LOOP_SLEEP_UTIME);
                continue; //死循环知道获取锁
            }
            $this->_rc->expire($lockKey, self::LOCK_TIMEOUT); //设置超时时间 防止防止程序异常崩溃时无人解锁 这样redis会自动删除lockkey
            break; //此处标识以获取到锁所以退出循环
        }
        return true;
    }

    /**
     * 解锁
     * @return type
     */
    private function _unlock() {
        $this->_rc->del($this->_buildKey(self::LOCKKEY));
    }

    /**
     * 扫描
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
     * 清空
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
