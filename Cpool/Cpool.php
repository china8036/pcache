<?php

namespace Cpool;
/*
 * Cpool接口定义
 */

interface Cpool {

    
    /**
     * 根据key获取
     * @param string $key
     */
    function get($key, $cacheTime);
    
    
    /**
     * 清空
     */
    function clear();    
    
}
