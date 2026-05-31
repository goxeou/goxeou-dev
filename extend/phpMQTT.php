<?php
// custom_file(lot_cerberuse)
/*
   phpMQTT
    A simple php class to connect/publish/subscribe to an MQTT broker
*/
/*
    Licence
    Copyright (c) 2010 Blue Rhinos Consulting | Andrew Milsted
    andrew@bluerhinos.co.uk | http://www.bluerhinos.co.uk
    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:
    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.
    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    THE SOFTWARE.
     
*/
/* phpMQTT */
class phpMQTT
{
    protected $socket;            /* holds the socket   */
    protected $msgid = 1;            /* counter for message id */
    public $keepalive = 10;        /* default keepalive timmer */
    public $timesinceping;        /* host unix time, used to detect disconects */
    public $topics = [];    /* used to store currently subscribed topics */
    public $debug = false;        /* should output debug messages */
    public $address;            /* broker address */
    public $port;                /* broker port */
    public $clientid;            /* client id sent to brocker */
    public $will;                /* stores the will of the client */
    protected $username;            /* stores username */
    protected $password;            /* stores password */
    public $cafile;
    protected static $known_commands = [
        1 => 'CONNECT',
        2 => 'CONNACK',
        3 => 'PUBLISH',
        4 => 'PUBACK',
        5 => 'PUBREC',
        6 => 'PUBREL',
        7 => 'PUBCOMP',
        8 => 'SUBSCRIBE',
        9 => 'SUBACK',
        10 => 'UNSUBSCRIBE',
        11 => 'UNSUBACK',
        12 => 'PINGREQ',
        13 => 'PINGRESP',
        14 => 'DISCONNECT'
    ];
    /**
     * phpMQTT constructor.
     *
     * @param $address
     * @param $port
     * @param $clientid
     * @param null $cafile
     */
    public function __construct($address, $port, $clientid, $cafile = null)
    {
        $this->broker($address, $port, $clientid, $cafile);
    }
    /**
     * Sets the broker details
     *
     * @param $address
     * @param $port
     * @param $clientid
     * @param null $cafile
     */
    public function broker($address, $port, $clientid, $cafile = null): void
    {
        $this->address = $address;
        $this->port = $port;
        $this->clientid = $clientid;
        $this->cafile = $cafile;
    }
    /**
     * Will try and connect, if fails it will sleep 10s and try again, this will enable the script to recover from a network outage
     *
     * @param bool $clean - should the client send a clean session flag
     * @param null $will
     * @param null $username
     * @param null $password
     *
     * @return bool
     */
    public function connect_auto($clean = true, $will = null, $username = null, $password = null): bool
    {
        while ($this->connect($clean, $will, $username, $password) === false) {
            sleep(10);
        }
        return true;
    }
    /**
     * @param bool $clean - should the client send a clean session flag
     * @param null $will
     * @param null $username
     * @param null $password
     *
     * @return bool
     */
    public function connect($clean = true, $will = null, $username = null, $password = null): bool
    {
        if ($will) {
            $this->will = $will;
        }
        if ($username) {
            $this->username = $username;
        }
        if ($password) {
            $this->password = $password;
        }
        if ($this->cafile) {
            $socketContext = stream_context_create(
                [
                    'ssl' => [
                        'verify_peer_name' => true,
                        'cafile' => $this->cafile
                    ]
                ]
            );
            $this->socket = stream_socket_client('tls://' . $this->address . ':' . $this->port, $errno, $errstr, 60, STREAM_CLIENT_CONNECT, $socketContext);
        } else {
            $this->socket = stream_socket_client('tcp://' . $this->address . ':' . $this->port, $errno, $errstr, 60, STREAM_CLIENT_CONNECT);
        }
        if (!$this->socket) {
            $this->_errorMessage("stream_socket_create() $errno, $errstr");
            return false;
        }
        stream_set_timeout($this->socket, 5);
        stream_set_blocking($this->socket, 0);
        $i = 0;
        $buffer = '';
        $buffer .= chr(0x00);
        $i++; // Length MSB
        $buffer .= chr(0x04);
        $i++; // Length LSB
        $buffer .= chr(0x4d);
        $i++; // M
        $buffer .= chr(0x51);
        $i++; // Q
        $buffer .= chr(0x54);
        $i++; // T
        $buffer .= chr(0x54);
        $i++; // T
        $buffer .= chr(0x04);
        $i++; // // Protocol Level
        //No Will
        $var = 0;
        if ($clean) {
            $var += 2;
        }
        //Add will info to header
        if ($this->will !== null) {
            $var += 4; // Set will flag
            $var += ($this->will['qos'] << 3); //Set will qos
            if ($this->will['retain']) {
                $var += 32;
            } //Set will retain
        }
        if ($this->username !== null) {
            $var += 128;
        }    //Add username to header
        if ($this->password !== null) {
            $var += 64;
        }    //Add password to header
        $buffer .= chr($var);
        $i++;
        //Keep alive
        $buffer .= chr($this->keepalive >> 8);
        $i++;
        $buffer .= chr($this->keepalive & 0xff);
        $i++;
        $buffer .= $this->strwritestring($this->clientid, $i);
        //Adding will to payload
        if ($this->will !== null) {
            $buffer .= $this->strwritestring($this->will['topic'], $i);
            $buffer .= $this->strwritestring($this->will['content'], $i);
        }
        if ($this->username !== null) {
            $buffer .= $this->strwritestring($this->username, $i);
        }
        if ($this->password !== null) {
            $buffer .= $this->strwritestring($this->password, $i);
        }
        $head = chr(0x10);
        while ($i > 0) {
            $encodedByte = $i % 128;
            $i /= 128;
            $i = (int)$i;
            if ($i > 0) {
                $encodedByte |= 128;
            }
            $head .= chr($encodedByte);
        }
        fwrite($this->socket, $head, 2);
        fwrite($this->socket, $buffer);
        $string = $this->read(4);
        if (ord($string[0]) >> 4 === 2 && $string[3] === chr(0)) {
            $this->_debugMessage('Connected to Broker');
        } else {
            $this->_errorMessage(
                sprintf(
                    "Connection failed! (Error: 0x%02x 0x%02x)\n",
                    ord($string[0]),
                    ord($string[3])
                )
            );
            return false;
        }
        $this->timesinceping = time();
        return true;
    }
    /**
     * Reads in so many bytes
     *
     * @param int $int
     * @param bool $nb
     *
     * @return false|string
     */
    public function read($int = 8192, $nb = false)
    {
        $string = '';
        $togo = $int;
        if ($nb) {
            return fread($this->socket, $togo);
        }
        while (!feof($this->socket) && $togo > 0) {
            $fread = fread($this->socket, $togo);
            $string .= $fread;
            $togo = $int - strlen($string);
        }
        return $string;
    }
    /**
     * Subscribes to a topic, wait for message and return it
     *
     * @param $topic
     * @param $qos
     *
     * @return string
     */
    public function subscribeAndWaitForMessage($topic, $qos): string
    {
        $this->subscribe(
            [
                $topic => [
                    'qos' => $qos,
                    'function' => '__direct_return_message__'
                ]
            ]
        );
        do {
            $return = $this->proc();
        } while ($return === true);
        return $return;
    }
    /**
     * subscribes to topics
     *
     * @param $topics
     * @param int $qos
     */
    public function subscribe($topics): void
    {
        $i = 0;
        $buffer = '';
        $id = $this->msgid;
        $buffer .= chr($id >> 8);#MSB
        $i++;
        $buffer .= chr($id & 0xFF);#LSB
        $i++;
        foreach ($topics as $key => $topic) {
            $buffer .= $this->strwritestring($key, $i);#TOPIC
            $buffer .= chr($topic['qos']);#QOS
            $i++;
            $this->topics[$key] = $topic;
        }
        $head = chr(0x82);#Binary: 1 0 0 0 0 0 1 0
        $head .= $this->setmsglength($i);
        fwrite($this->socket, $head, strlen($head));
        $this->_fwrite($buffer);
        $string = $this->read(2);
        $bytes = ord(substr($string, 1, 1));
        $this->read($bytes);
    }
    /**
     * Sends a keep alive ping
     */
    public function ping(): void
    {
        $head = chr(0xc0);
        $head .= chr(0x00);
        fwrite($this->socket, $head, 2);
        $this->timesinceping = time();
        $this->_debugMessage('ping sent');
    }
    /**
     *  sends a proper disconnect cmd
     */
    public function disconnect(): void
    {
        $head = ' ';
        $head[0] = chr(0xe0);
        $head[1] = chr(0x00);
        fwrite($this->socket, $head, 2);
    }
    /**
     * Sends a proper disconnect, then closes the socket
     */
    public function close(): void
    {
        $this->disconnect();
        stream_socket_shutdown($this->socket, STREAM_SHUT_WR);
    }
    public function publishAndWaitForMessage($topic, $content, $qos = 0, $retain = false): void
    {
        $this->publish($topic, $content, $qos, $retain);
        if(!$qos)return;#qos:0直接返回
        $now = strtotime("now");
        while($this->proc(true,function(){})){
            if (strtotime("now") - $now >= 10) break;#10秒超时结束循环
        }
    }
    /**
     * Publishes $content on a $topic
     *
     * @param $topic
     * @param $content
     * @param int $qos
     * @param bool $retain
     */
    public function publish($topic, $content, $qos = 0, $retain = false): void
    {
        $i = 0;
        $buffer = '';
        $buffer .= $this->strwritestring($topic, $i);
        if ($qos) {
            $id = $this->msgid++;
            $buffer .= chr(($id >> 8) & 0xFF);
            $i++;
            $buffer .= chr($id & 0xFF);
            $i++;
        }
        $buffer .= $content;
        $i += strlen($content);
        $head = ' ';
        $cmd = 0x30;#消息类型00110000(3)为public
        if ($qos)$cmd |= $qos << 1;
        if ($retain) {
            #发送保留消息标志位
            $cmd |= 0x1;
        }
        $head[0] = chr($cmd);
        $head .= $this->setmsglength($i);
        fwrite($this->socket, $head, strlen($head));
        $this->_fwrite($buffer);
    }
    /**
     * Writes a string to the socket
     *
     * @param $buffer
     *
     * @return bool|int
     */
    protected function _fwrite($buffer)
    {
        $buffer_length = strlen($buffer);
        for ($written = 0; $written < $buffer_length; $written += $fwrite) {
            $fwrite = fwrite($this->socket, substr($buffer, $written));
            if ($fwrite === false) {
                return false;
            }
        }
        return $buffer_length;
    }
    /**
     * Processes a received topic
     *
     * @param $msg
     *
     * @retrun bool|string
     */
    public function message($cmd,$msg)
    {
        #retain重连的时候,retain收到之前未收到的消息才是1,正常消息retain值还是0
        $retain = ((int)$cmd & 0x1);
        $qos = ((int)$cmd & 0x6);
        $qos >>= 1;
        $tlen = (ord($msg[0]) << 8) + ord($msg[1]);
        $topic = substr($msg, 2, $tlen);
        $msg = substr($msg, ($tlen + 2));
        if ($qos) {
            $msgid = (ord($msg[0]) << 8) + ord($msg[1]);
            $msg = substr($msg, 2);
        }
        $found = false;
        foreach ($this->topics as $key => $top) {
            if (preg_match(
                '/^' . str_replace(
                    '#',
                    '.*',
                    str_replace(
                        '+',
                        "[^\/]*",
                        str_replace(
                            '/',
                            "\/",
                            str_replace(
                                '$',
                                '\$',
                                $key
                            )
                        )
                    )
                ) . '$/',
                $topic
            )) {
                $found = true;
                if ($top['function'] === '__direct_return_message__') {
                    return $msg;
                }
                if (is_callable($top['function'])) {
                    $mqttMsg = array('qos' => $qos,'topic' => $topic, 'payload' => $msg,
                        'msgid' => $msgid, 'retain' => $retain);
                    call_user_func($top['function'], $mqttMsg);
                } else {
                    $this->_errorMessage('Message received on topic ' . $topic . ' but function is not callable.');
                }
            }
        }
        if ($found === false) {
            $this->_debugMessage('msg received but no match in subscriptions');
        }
        return $found;
    }
    /**
     * The processing loop for an "always on" client
     * set true when you are doing other stuff in the loop good for
     * watching something else at the same time
     *
     * @param bool $loop
     *
     * @return bool | string
     */
    public function proc($loop = true, $callable = null)
    {
        if (feof($this->socket)) {
            #连接中断后重连,不清除session
            $this->_debugMessage('eof receive going to reconnect for good measure');
            fclose($this->socket);
            $this->connect_auto(false);
            if (count($this->topics)) {
                $this->subscribe($this->topics);
            }
        }
        #读取一个字节固定协议头
        #0位表示retain,1~2位表示qos的值0~2 (byte & 0x6) >> 1
        #3位表示DUP FLAG,消息是否重复
        #4~7位表示 消息类型 (byte & 0xF) >> 4
        $byte = $this->read(1, true);
        if ((string)$byte === '') {
            if ($loop === true) {
                usleep(10000);
            }
        } else {
            $cmd = (int)(ord($byte) / 16);
            $this->_debugMessage(
                sprintf(
                    'Received CMD: %d (%s)',
                    $cmd,
                    isset(static::$known_commands[$cmd]) === true ? static::$known_commands[$cmd] : 'Unknown'
                )
            );
            $multiplier = 1;
            $value = 0;
            do {
                $digit = ord($this->read(1));
                $value += ($digit & 127) * $multiplier;
                $multiplier *= 128;
            } while (($digit & 128) !== 0);
            $this->_debugMessage('Fetching: ' . $value . ' bytes');
            $string = $value > 0 ? $this->read($value) : '';
            if ($cmd) {
                switch ($cmd) {
                    case 3: #PUBLISH
                        $return = $this->message($byte,$string);
                        if (is_bool($return) === false) {
                            return $return;
                        }
                        break;
                    case 4: # PUBACK qos:1的回复
                        if($callable && is_callable($callable))return call_user_func($callable);
                        break;
                    case 5: #PUBREC 
                        # PUBREL 报文是对 PUBREC 报文的响应
                        $head = ' ';
                        $head[0] = chr(0x62);
                        $head[1] = chr(0x02);
                        #固定报头
                        fwrite($this->socket, $head, 2);
                        #可变报头
                        $this->_fwrite($string);
                        break;
                    case 7: #PUBCOMP
                        #qos:2发送完成
                        if($callable && is_callable($callable))return call_user_func($callable);
                        break;
                }
            }
        }
        if ($this->timesinceping < (time() - $this->keepalive)) {
            $this->_debugMessage('not had something in a while so ping');
            $this->ping();
        }
        if ($this->timesinceping < (time() - ($this->keepalive * 2))) {
            $this->_debugMessage('not seen a packet in a while, disconnecting/reconnecting');
            fclose($this->socket);
            $this->connect_auto(false);
            if (count($this->topics)) {
                $this->subscribe($this->topics);
            }
        }
        return true;
    }
    /**
     * Gets the length of a msg, (and increments $i)
     *
     * @param $msg
     * @param $i
     *
     * @return float|int
     */
    protected function getmsglength(&$msg, &$i)
    {
        $multiplier = 1;
        $value = 0;
        do {
            $digit = ord($msg[$i]);
            $value += ($digit & 127) * $multiplier;
            $multiplier *= 128;
            $i++;
        } while (($digit & 128) !== 0);
        return $value;
    }
    /**
     * @param $len
     *
     * @return string
     */
    protected function setmsglength($len): string
    {
        $string = '';
        do {
            $digit = $len % 128;
            $len >>= 7;
            // if there are more digits to encode, set the top bit of this digit
            if ($len > 0) {
                $digit |= 0x80;
            }
            $string .= chr($digit);
        } while ($len > 0);
        return $string;
    }
    /**
     * @param $str
     * @param $i
     *
     * @return string
     */
    protected function strwritestring($str, &$i): string
    {
        $len = strlen($str);
        $msb = ($len >> 8) & 0xFF;
        $lsb = $len & 0xFF;
        $ret = chr($msb);
        $ret .= chr($lsb);
        $ret .= $str;
        $i += ($len + 2);
        return $ret;
    }
    /**
     * Prints a sting out character by character
     *
     * @param $string
     */
    public function printstr($string): void
    {
        $strlen = strlen($string);
        for ($j = 0; $j < $strlen; $j++) {
            $num = ord($string[$j]);
            if ($num > 31) {
                $chr = $string[$j];
            } else {
                $chr = ' ';
            }
            printf("%4d: %08b : 0x%02x : %s \n", $j, $num, $num, $chr);
        }
    }
    /**
     * @param string $message
     */
    protected function _debugMessage(string $message): void
    {
        if ($this->debug === true) {
            echo date('r: ') . $message . PHP_EOL;
        }
    }
    /**
     * @param string $message
     */
    protected function _errorMessage(string $message): void
    {
        error_log('Error:' . $message);
    }
}