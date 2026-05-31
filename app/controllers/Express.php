<?php


// +----------------------------------------------------------------------
// | 配送设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Express extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			if($this->mdid){
				$where[] = ['mdid','=',$this->mdid];
			}
			if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
			if(input('param.linkman')) $where[] = ['linkman','like','%'.input('param.linkman').'%'];
			if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('express_order')->where($where)->count();
			$list = Db::name('express_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			foreach($list as $k=>$vo){
				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$list[$k]['nickname'] = $member['nickname'];
				$list[$k]['headimg'] = $member['headimg'];
				$list[$k]['m_remark'] = $member['remark'];
				$list[$k]['platform'] = getplatformname($vo['platform']);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		$where = [];
		if(input('param.')) $where = input('param.');
		$where = json_encode($where);
		View::assign('where',$where);
        $this->defaultSet();
		return View::fetch();
    }
    function defaultSet(){
        $set = Db::name('express_sysset')->where('bid',bid)->where('aid',aid)->find();
        if(!$set){
            Db::name('express_sysset')->insert(['aid'=>aid,'bid' => bid]);
        }
    }
}