<?php
/**
 * Created by PhpStorm.
 * User: kilmas
 * Date: 2018/6/28
 * Time: 16:06
 */

//创建Server对象，监听 0.0.0.0:3307 端口，类型为SWOOLE_SOCK_UDP
$serv = new swoole_server('0.0.0.0', 3307, SWOOLE_PROCESS, SWOOLE_SOCK_UDP);

$clients = [];

$serv->on('Start',function($serv){
    var_dump('start');
});
// 监听数据接收事件
$serv->on('Packet', function ($serv, $data, $clientInfo) use (&$clients) {
    try {
        $data = json_decode($data, true);
    } catch (exception $e) {
        return sprintf('! Couldn\'t parse data (%s):\n%s', $e, $data);
    }
    if ($data['type'] == 'register') { // 客户端注册
        $clients[$data['name']] = [
            'name' => $data['name'],
            'connections' => [
                'local' => $data['linfo'],
                'public' => $clientInfo
            ]
        ];
        echo sprintf('# Client registered: %s@[%s:%s | %s:%s]', $data['name'],
            $clientInfo['address'], $clientInfo['port'], $data['linfo']['address'], $data['linfo']['port']);
    } elseif ($data['type'] == 'connect') { // 请求连接
        $couple = [$clients[$data['from']], $clients[$data['to']]];
        for ($i = 0; $i < count($couple); $i++) {
            if (!$couple[$i]) return var_dump('Client unknown!');
        }

        for ($i = 0; $i < count($couple); $i++) {
            $serv->sendto($couple[$i]['connections']['public']['address'], $couple[$i]['connections']['public']['port'], json_encode([
                'type' => 'connection',
                'client' => $couple[($i + 1) % count($couple)],
                'self' => $couple[$i]
            ]));
        }
    }
});

//启动服务器
$serv->start();