<?php

namespace Cpool;
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

interface Cpool {

    
    /**
     * 根据key获取
     * @param string $key
     */
    function get($key);
    
    
    /**
     * 设置
     * @param type $key
     * @param type $value
     */
    function set($key, $value);

    
    /**
     * 删除
     * @param type $key
     */
    function del($key);
    
    
    /**
     * 更新
     * @param type $key
     * @param type $val
     */
    function update($key, $val);
    
    
}
