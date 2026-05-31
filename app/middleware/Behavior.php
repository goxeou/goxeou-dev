<?php
declare (strict_types = 1);

namespace app\middleware;

class Behavior
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure       $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        //start 加入以下内容
         //$admin  =  get_admin_info();   				//当前登录用户的信息,自己实现
        $method = strtolower($request->method());
        $is_ajax = $request->isAjax();
        $route = $request->pathinfo();
        $req = $_REQUEST;
        unset($req['s'],$req['_session']);  
        $req_data = $req ?  json_encode($req) : '';
        $data = [
           //'admin_id' => $admin['id'],				//操作人id
           'route' => $route,						//操作的路由地址
           'method' => $method,						//get/post
           'req_tp' => $is_ajax ? 'ajax' : 'normal',
           'req_data' =>$req,   				 //get/post的数据
            'ip' => request()->ip(),
           'create_time' => time()
        ];
        $log_name = date('Y-m-d').'.log';
        $log_pth = ROOT_PATH.'runtime/behavior/'.date('Ym').'/';
        if(!file_exists($log_pth)){
            mk_dir($log_pth);
        }
        file_put_contents($log_pth.$log_name,date('Y-m-d H:i:s').'::'.json_encode($data)."\r\n",FILE_APPEND);
                 //end
        return $next($request);
    }
}
