<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require 'Cpool.php';
require 'CpException.php';
require 'Redis/RedisCpool.php';

function dataAccess($sql) {
    return $sql;
}

/**
 * @requires extension redis
 * @requires PHP 5.6
 */
class RedisCpoolTest extends \PHPUnit\Framework\TestCase {


    /**
     * 
     */
    public function testOk() {
        $config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'passwd' => '11111111',
            'cacheTimeout' => 2
        ];
        try {
            $rcp = new \Cpool\Redis\RedisCpool($config, 'dataAccess');
        } catch (\Exception $exc) {
            $this->assertFalse(true, $exc->getMessage());
        }
        $rcp->clear();
        $maxNum = 10;
        for ($i = 0; $i < $maxNum; $i++) {
            $rcp->get('test' . $i);
        }
        $this->assertTrue(boolval($rcp->exist($rcp->cacheKey2Key('test1'))));
        $this->assertEquals($rcp->cacheKey2Key('test0'), $rcp->getFirstKey());
        $this->assertEquals($rcp->cacheKey2Key('test' . ($maxNum -1)), $rcp->getLastKey());
        $this->assertEquals($maxNum, $rcp->getCount());
        $this->assertEquals($rcp->get('test2'), 'test2');
        $this->assertEquals($config['cacheTimeout'], $rcp->getCacheTimeoutVal());
        sleep($config['cacheTimeout']);
        $this->assertEquals($rcp->get('test11'), 'test11');
        $this->assertFalse(boolval($rcp->exist($rcp->cacheKey2Key('test0'))));
        $this->assertEquals($rcp->cacheKey2Key('test1'), $rcp->getFirstKey());
        $this->assertEquals($rcp->cacheKey2Key('test11'), $rcp->getLastKey());
        $rcp->clear();
        $this->assertFalse(boolval($rcp->exist('test11')));
        $this->assertEquals(0, $rcp->getCount());
    }

}