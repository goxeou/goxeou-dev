<?php


// +----------------------------------------------------------------------
// | 小程序 打开半屏小程序
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use app\commons\Wechat;

class Wxembedded extends Common
{	
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
        $appinfo = \app\commons\System::appinfo(aid,'wx');
        if($appinfo['authtype'] ==0){
            showmsg('无权限，手动接入的请到[微信公众平台-设置-第三方设置-半屏小程序管理]中进行申请');
        }
	}
	//列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
            $count = input('param.count',0);
            //第一页 为了分页 查询所有的数量，其他页按照正常的条数查询
            $start = ($page -1) *$limit;
            $limit = $start==0?0:$limit;
            $list = Wechat::getEmbeddedList(aid,$start,$limit);
		    if($list && $start ==0){
		        $count = count($list);
                $list = array_slice($list,0,10);
            }
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		
		return View::fetch();
    }
	//编辑
	public function edit(){
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		if(!$info['appid']){
		    return json(['status'=> 0,'msg' => '请输入目标小程序appid']);
        }
	    $data = Wechat::addEmbedded(aid,$info['appid'],$info['apply_reason']);
		if($data['status'] ==0){
            return json(['status'=>0,'msg'=>$data['msg']]);
        }
        \app\commons\System::plog('添加半屏小程序申请，AppId'.$info['appid']);
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}

}