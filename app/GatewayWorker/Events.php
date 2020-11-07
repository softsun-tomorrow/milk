<?php

namespace App\GatewayWorker;

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
declare(ticks=1);

use GatewayWorker\Lib\Gateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use App\Models\User;
use Workerman\Lib\Timer;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{

    public static function onWorkerStart($businessWorker){
        if(Redis::setnx( 'global_timer_lock', 'milk')){
            //进程启动开启定时任务
            $task = new Task;
            $task->start();

            Redis::expire( 'global_timer_lock', 10);
        }
    }

    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     *
     * @param int $clientId 连接id
     */
    public static function onConnect($clientId)
    {
        // 向当前client_id发送数据
        Gateway::sendToClient($clientId, json_encode(['milk' => 'connect', 'client_id' => $clientId, "message"=>"Hello $clientId\r\n"]));

        // 向所有人发送
        //Gateway::sendToAll("$clientId login\r\n");
    }

    /**
     * 当客户端发来消息时触发
     * @param int $clientId 连接id
     * @param mixed $message 具体消息
     */
    public static function onMessage($clientId, $message)
    {
        $response = ['errcode' => 0, 'msg' => 'ok', 'data' => []];
        $message = json_decode($message);

        if (!$message->milk) {
            $response['msg'] = 'Missing parameter milk';
            $response['errcode'] = 1001;
            Gateway::sendToClient($clientId, json_encode($response));
            return false;
        }

        if(!$message->user_id){
            $response['msg'] = 'Missing parameter user_id';
            $response['errcode'] = 1002;
            Gateway::sendToClient($clientId, json_encode($response));
            return false;
        }
        if(! $user=self::authentication($clientId, $message->user_id)){
            $response['msg'] = 'Authentication failure';
            $response['errcode'] = 1003;
            Gateway::sendToClient($clientId, json_encode($response));
            return false;
        }

        switch ($message->milk) {
            case 'heart':
                $response['data'] = ['heart' => env('GATEWAY_HEART')];
                break;
            case 'login':
                $response['data'] = ['user' => (array)$user];
                break;
            case 'is_login':
//                $userId = Redis::hget( 'online_user_client', $message->user_id);
                $response['data'] = ['user_id' => $userId??0];
                break;
            default:
                $response['errcode'] = 1100;
                $response['msg'] = 'Undefined';
                break;
        }

        Gateway::sendToClient($clientId, json_encode($response));
    }

    /**
     * 当用户断开连接时触发
     * @param int $clientId 连接id
     */
    public static function onClose($clientId)
    {
//        $userId = Redis::hget( 'online_client_user', $clientId);
//
//        Redis::hdel( 'online_user_client', $userId);
//        Redis::hdel( 'online_client_user', $clientId);

        // 向所有人发送
        GateWay::sendToAll("$clientId logout\r\n");
    }

    public static function onWorkerStop($businessWorker)
    {
//        $keys = Redis::hkeys( 'client_user');
//        foreach($keys as $clientId){
//            $userId = Redis::hget( 'client_user', $clientId);

//            Redis::hdel( 'online_user_client', $userId);
//            Redis::hdel( 'online_client_user', $clientId);
//        }
        echo "WorkerStop\n";
    }

    public static function authentication($clientId, $userId)
    {
        $user = DB::table('users')->where('id',$userId)->select(['id as user_id','mobile','name'])->first();
//        Log::info('user', [$user]);

        if(!$user){
            return false;
        }
        //redis 记录 client_id, user_id
        //如果连接关掉，则删除该redis记录
//        Redis::hset( 'online_client_user', $clientId, $userId);
//        Redis::hset( 'online_user_client', $userId, $clientId);

        if(!Gateway::isUidOnline($userId))
        {
            Gateway::bindUid($clientId, $userId);
        }

        return $user;
    }
}
