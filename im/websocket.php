<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class WebSocket {

    private $_host;
    private $_port;
    private $_server;
    private $_userList = [];

    public function __construct($host, $port) {
        $this->_host = $host;
        $this->_port = $port;
    }

    public function start() {
        // 监听端口
        $this->_server = new swoole_websocket_server($this->_host, $this->_port);

        // 监听websocket连接打开事件
        $this->_server->on('open', function (swoole_websocket_server $server, $request) {
            echo "{$request->fd}连接成功！\n";

            array_push($this->_userList, $request->fd);
        });

        // 监听消息事件
        $this->_server->on("message", function (swoole_websocket_server $server, $request) {
            echo "收到来自：{$request->fd}的消息，内容：{$request->data},发送给：{$request->receiver}\n";

            $msg = [
                'fd' => $request->fd,
                'msg' => $request->data,
                'total' => count($this->_userList),
            ];

            $sendList = $this->_userList;

            if ($request->receiver[0] == '@') {
                $request->receiver = substr($request->receiver, 1);
                echo "{$request->receiver}";
                if (!in_array($request->receiver, $this->_userList)) {
                    echo "对方不存在";
                    $msg['msg'] = '对方不存在';
                    $server->push($request->fd, json_encode($msg, JSON_UNESCAPED_UNICODE));
                    return;
                }

                $sendList = [$request->receiver];
            }

            foreach ($sendList as $fdId) {
                if ($fdId != $request->fd) {
                    $server->push($fdId, $msg);
                }
            }
        });

        // 监听连接关闭事件
        $this->_server->on('close', function ($server, $fd) {
            $msg = [
                'fd' => $fd,
                'msg' => '离开聊天室',
                'total' => count($this->_userList)
            ];

            $sendList = $this->_userList;
            foreach ($sendList as $key => $fdId) {
                if ($fdId == $fd) {
                    unset($this->_userList[$key]);
                }else {
                    $server->push($fdId, json_encode($msg, JSON_UNESCAPED_UNICODE));
                }
            }

            echo "客户端已关闭！";
        });

        $this->_server->start();
    }

}

$im = new WebSocket('0.0.0.0', 8080);

$im->start();
