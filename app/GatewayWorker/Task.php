<?php

namespace App\GatewayWorker;

use Events;
use GatewayWorker\Lib\Gateway;
use Workerman\Lib\Timer;
use \Workerman\Worker;

class Task
{
    public function start()
    {
        $this->timeTask();
    }

    public function timeTask()
    {
        //定时任务
        //参数1 循环时间（秒）一次
        //参数2 命名空间到类
        //      静态类直接调用，  array('\App\Logic\ChargeRentalsLogic', 'test')
        //      如果是非静态类要先实例化  array(new \App\Logic\ChargeRentalsLogic(), 'test')
        //参数3 任务方法
        //参数4  方法传入参数
        //参数5 是否循环一次就停止此定时器   true 一直循环  false 循环一次就停止
        //Timer::add(参数1, array(参数2, 参数3), 参数4, 参数5);
        $client_id = "test";
        $_SESSION['auth_timer_id'] = Timer::add(30, function($client_id){
            Gateway::closeClient($client_id);
        }, array($client_id), false);

        // Timer::add(1, array('\App\Logic\ChargeRentalsLogic', 'autoScanRentTime'), [], true);
//        Timer::add(1, array('\App\Logic\ChargeRentalsLogic', 'autoNotificationRentals24'), [], true);
//        Timer::add(1, array('\App\Logic\ChargeRentalsLogic', 'autoNotificationRentals48'), [], true);
//        Timer::add(1, array('\App\Logic\ChargeRentalsLogic', 'autoNotificationRentals72'), [], true);
    }
}
