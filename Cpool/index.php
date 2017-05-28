<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require 'Cpool.php';
require 'CpException.php';
require 'Redis/RedisCpool.php';



$config = [
    'host' => '127.0.0.1',
    'port' => 6379
];
try {
    $rcp = new \Cpool\Redis\RedisCpool($config);
} catch (\Exception $exc) {
    echo $exc->getMessage();
}

