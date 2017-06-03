<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Cpool;

class Engin {

    /**
     *
     * @var \Cpool\Cpool 
     */
    private $_engin;

    /**
     * 
     * @param array $config
     * @param \Cpool\callable $dataAccess
     */
    public function __construct(array $config, callable $dataAccess) {
        require_once 'Cpool.php';
        require_once 'CpException.php';
        require_once 'Redis/RedisCpool.php';
        $this->_engin = new \Cpool\Redis\RedisCpool($config, $dataAccess);
    }

    /**
     * 
     * @param type $sql
     * @param type $cacheTime
     */
    public function pget($sql, $cacheTime = 10) {
        $this->_engin->scan();
        return $this->_engin->get($sql, $cacheTime);
    }

    public static function dataAccess($sql) {
        return md5($sql);
    }

}

