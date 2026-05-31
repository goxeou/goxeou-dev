<?php
// +----------------------------------------------------------------------
// | 应用设置
// +----------------------------------------------------------------------

return [
    // 应用地址
    'app_host'         => env('app.host', ''),
    // 应用的命名空间
    'app_namespace'    => '',
    // 是否启用路由
    'with_route'       => true,
    // 默认应用
    'default_app'      => 'index',
    // 默认时区
    'default_timezone' => 'Asia/Shanghai',
    // 应用映射（自动多应用模式有效）
    'app_map'          => [],
    // 域名绑定（自动多应用模式有效）
    'domain_bind'      => [],
    // 禁止URL访问的应用列表（自动多应用模式有效）
    'deny_app_list'    => [],

    // 异常页面的模板文件
    'exception_tmpl'   => app()->getThinkPath() . 'tpl/think_exception.tpl',

    // 错误显示信息,非调试模式有效
    'error_message'    => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'   => true,
	'upload_type'      =>'jpg,jpeg,png,bmp,gif,webp,ico,pem,mp3,ogg,mp4,avi,mov,rmvb,rm,flv,3gp,mpg,mlv,mpe,mpeg,mpv,xls,xlsx,pdf,pdg,doc,docs,docx,ppt,pptx,zip,7z,rar,crt',
    'upload_mime'      => 'image/jpeg,image/gif,image/png,image/bmp,image/webp,image/x-icon',
    'upload_type_image_arr'=>['jpg','jpeg','png','bmp','gif','webp','ico'],
    'upload_type_video_arr'=>['mp4','avi','mov','rmvb','rm','flv','3gp','mpg','mlv','mpe','mpeg','mpv'],
    'upload_type_no_oss_arr'=> ['pem','xls','xlsx','crt'],
];
