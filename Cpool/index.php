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
    'port' => 6379,
    'passwd' => '11111111'
];
try {
    $rcp = new \Cpool\Redis\RedisCpool($config);
} catch (\Exception $exc) {
    echo $exc->getMessage();
}

for ($i = 0; $i < 10; $i++) {
    $rcp->set('test' . $i, rand(1, 10000));
}

$rcp->scan();