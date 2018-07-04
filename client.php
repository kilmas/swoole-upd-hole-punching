<?php
/**
 * Created by PhpStorm.
 * User: kilmas
 * Date: 2018/7/3
 * Time: 20:58
 */


$port = random_int(3000, 3500);
// listening 0.0.0.0:$port,type:SWOOLE_SOCK_UDP
$server = new swoole_server('0.0.0.0', $port, SWOOLE_PROCESS, SWOOLE_SOCK_UDP);

$clientName = $argv[2];
$remoteName = empty($argv[3]) ? null : $argv[3];

$proxyServer = [
    'address' => $argv[1],
    'port' => 3307
];

$client = [
    'ack' => false,
    'connection' => []
];

$server->on('Start', function ($server) use ($proxyServer, $clientName, $remoteName, $port) {
    $ips = swoole_get_local_ip();
    foreach ($ips as $ip) {
        $localInfo['address'] = $ip;
    }
    $localInfo['port'] = $port;
    $data = ['type' => 'register', 'name' => $clientName, 'linfo' => $localInfo];
    $bool = $server->sendto($proxyServer['address'], $proxyServer['port'], json_encode($data));
    // send connect $proxyServer
    if ($bool && $remoteName) {
        $data = ['type' => 'connect', 'from' => $clientName, 'to' => $remoteName];
        $server->sendto($proxyServer['address'], $proxyServer['port'], json_encode($data));
    }
});

// listening Packet
$server->on('Packet', function ($server, $data, $clientInfo) use ($clients, $clientName, &$remoteName, &$client) {
    try {
        $data = json_decode($data, true);
    } catch (exception $e) {
        var_dump($data);
        return;
    }
    if ($data['type'] == 'connection') {
        var_dump('connection punch request');
        $remoteName = $data['client']['name'];
        $punch = ['type' => 'punch', 'from' => $clientName, 'to' => $remoteName];
        // send wan and lan ip
        foreach ($data['client']['connections'] as $key => $value) {
            $server->tick(1000, function ($timer_id) use ($server, $data, $value, &$client, $punch) {
                if ($client['ack']) {
                    swoole_timer_clear($timer_id);
                    return;
                }
                var_dump('per-second punch');
                $server->sendto($value['address'], (int)$value['port'], json_encode($punch));
            });
        }
    } elseif ($data['type'] == 'punch' && $data['to'] == $clientName) {
        var_dump('punch success!');
        $ack = ['type' => 'ack', 'from' => $remoteName];
        var_dump('got punch,sending ACK!');
        $server->sendto($clientInfo['address'], $clientInfo['port'], json_encode($ack));
    } elseif ($data['type'] == 'ack' && !$client['ack']) {
        $client['ack'] = true;
        $client['connection'] = $clientInfo;
        var_dump('got ACK, sending MSG');
        $data = ['type' => 'message', 'from' => $clientName, 'msg' => 'Hello, ' + $remoteName + '!'];
        $server->sendto($client['connection']['address'], $client['connection']['port'], json_encode($data));
    } elseif ($data['type'] == 'message') {
        echo sprintf('> %s [from %s@%s:%s]', $data['msg'], $data['from'], $clientInfo['address'], $clientInfo['port']);
    }
});

// start server
$server->start();