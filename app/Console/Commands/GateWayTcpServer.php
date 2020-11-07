<?php

namespace App\Console\Commands;

use GatewayWorker\BusinessWorker;
use Illuminate\Console\Command;
use Workerman\Worker;
use GatewayWorker\Gateway;
use GatewayWorker\Register;

class GateWayTcpServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gateway-worker:tcp-server {action} {--daemon}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start a GatewayWorker Tcp Server.';

    /**
     * constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * [@return](https://learnku.com/users/31554) mixed
     */
    public function handle()
    {
        global $argv;

        if (!in_array($action = $this->argument('action'), ['start', 'status', 'stop', 'restart', 'reload'])) {
            $this->error('Error Arguments');
            exit;
        }

        if(strpos(strtolower(PHP_OS), 'win') === 0)
        {
//            exit("start.php not support windows, please use start_for_win.bat\n");
        }

        // 检查扩展
        if(!extension_loaded('pcntl'))
        {
            exit("Please install pcntl extension. See http://doc3.workerman.net/appendices/install-extension.html\n");
        }

        if(!extension_loaded('posix'))
        {
            exit("Please install posix extension. See http://doc3.workerman.net/appendices/install-extension.html\n");
        }

        // 标记是全局启动
        //define('GLOBAL_START', 1);

        $argv[0] = 'gateway-worker:tcp-server';
        $argv[1] = $action;
        $argv[2] = $this->option('daemon') ? '-d' : '';

        $this->start();
    }

    private function start()
    {
        $this->startGateWay();
        $this->startBusinessWorker();
        $this->startRegister();
        Worker::runAll();
    }

    private function startBusinessWorker()
    {
        $worker                  = new BusinessWorker();
        $worker->name            = 'Tcp BusinessWorker';                        #设置BusinessWorker进程的名称  worker名称,根据不同的项目自定义
        $worker->count           = 10;                                       #设置BusinessWorker进程的数量
        $worker->registerAddress = env('REGISTER_ADDR');             #注册服务地址
        $worker->eventHandler    = \App\GatewayWorker\Events::class;        #设置使用哪个类来处理业务,业务类至少要实现onMessage静态方法，onConnect和onClose静态方法可以不用实现
    }

    private function startGateWay()
    {
        $gateway = new Gateway(env('GATEWAY_TCP_NAME'));
        $gateway->name                 = 'Tcp Gateway';                         #设置Gateway进程的名称，方便status命令中查看统计
        // gateway进程数（设置不合理会报错）
        //业务代码偏向IO密集型 进程数为cpu内核的3倍
        //业务代码偏向CPU密集型 进程数为cpu内核个数
        //不确定属于那种   内核的2倍
        $gateway->count                = 10;                                 #进程的数量
        $gateway->lanIp                = env('GATEWAY_TCP_LAN_IP');         #内网ip,多服务器分布式部署的时候需要填写真实的内网ip
        $gateway->startPort            = env('GATEWAY_TCP_START_PORT');     #监听本机端口的起始端口
        //服务端向客户端发送心跳数据的时间间隔 单位：秒。如果设置为0代表不发送心跳检测
        $gateway->pingInterval         = env('GATEWAY_TCP_HEART');
        //客户端连续$pingNotResponseLimit次$pingInterval时间内不回应心跳则断开链接。
        //如果设置为0代表客户端不用发送回应数据，即通过TCP层面检测连接的连通性（极端情况至少10分钟才能检测到）
        $gateway->pingNotResponseLimit = 0;                                 #服务端主动发送心跳
        // 要发送的心跳请求数据，心跳数据是任意的，只要客户端能识别即可
        $gateway->pingData             = '{"milk":"heart"}';
        $gateway->registerAddress      = env('REGISTER_ADDR'); #注册服务地址  127.0.0.1:12360
    }

    private function startRegister()
    {
        // register 必须是text协议；这个端口要保持一致，根据项目自定义
        new Register(env('REGISTER_SOCKET_NAME')); # text://0.0.0.0:12360
    }

}

//ws = new WebSocket("ws://127.0.0.1:23460");
//ws.onopen = function() {
//    ws . send('{"mode":"say","order_id":"21",type:1,"content":"文字内容","user_id":21}');
//    ws . send('{"mode":"chats","order_id":"97"}');
//};
//ws.onmessage = function(e) {
//    console.log("收到服务端的消息：" + e.data);
//};
