<?php

// +----------------------------------------------------------------------
// | 日志设置
// +----------------------------------------------------------------------
return [
    // 默认日志记录通道
    'default'      => env('log.channel', 'file'),
    // 日志记录级别
    'level'        => ['error','info'],
    // 日志类型记录的通道 ['error'=>'email',...]
    'type_channel' => [],
    // 关闭全局日志写入
    'close'        => false,
    // 全局日志处理 支持闭包
    'processor'    => null,
 // 其它日志通道配置
     //行为日志
    
    // 日志通道列表
    'channels'     => [
        'file' => [
            // 日志记录方式
            'type'           => 'File',
            // 日志保存目录
            'path'           => '',
            // 单文件日志写入
            'single'         => false,
            // 独立日志级别
            'apart_level'    => [],
            // 最大日志文件数量
            'max_files'      => 0,
            // 使用JSON格式记录
            'json'           => false,
            // 日志处理
            'processor'      => null,
            // 关闭通道日志写入
            'close'          => false,
            // 日志输出格式化
            'format'         => '[%s][%s] %s',
            // 是否实时写入
            'realtime_write' => false,
        ],
        // 其它日志通道配置
         'behavior'    =>    [
             'path'           => runtime_path().'behavior',  //日志存放目录
             'type'    =>    'File',
             'single' =>     'b',        		//单一文件日志:文件名
             'file_size'   	=> 	1024*1024*10, 	//日志文件大小限制（超出会生成多个文件
             'max_files' => 30,                  //文件最大数量
             'realtime_write'    =>    false,    // 关闭实时写入
         ],
    ],

];
