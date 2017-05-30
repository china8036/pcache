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
class CpoolTest extends \PHPUnit\Framework\TestCase {


    /**
     * 
     */
    public function testOk() {
        $config = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'passwd' => '11111111'
        ];
        try {
            $rcp = new \Cpool\Redis\RedisCpool($config, 'dataAccess');
        } catch (\Exception $exc) {
            $this->assertFalse(true, $exc->getMessage());
        }
        $rcp->clear();
        for ($i = 0; $i < 10; $i++) {
            $rcp->get('test' . $i);
        }
        $this->assertTrue(boolval($rcp->exist($rcp->cacheKey2Key('test0'))));
        $this->assertEquals(10, $rcp->getCount());
        $this->assertEquals($rcp->get('test1'), 'test1');
        $this->assertEquals(10, $rcp->getCount());
        sleep(\Cpool\Redis\RedisCpool::CACHE_TIMEOUT);
        $this->assertEquals($rcp->get('test11'), 'test11');
        $this->assertFalse(boolval($rcp->exist($rcp->cacheKey2Key('test0'))));
        $rcp->clear();
        $this->assertFalse(boolval($rcp->exist('test11')));
        $this->assertEquals(0, $rcp->getCount());
    }

}