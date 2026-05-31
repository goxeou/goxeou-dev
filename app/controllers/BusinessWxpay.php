<?php


// +----------------------------------------------------------------------
// | 多商户的微信支付
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class BusinessWxpay extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
	//微支付日志
    public function wxpaylog(){
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
			$where[] = ['bid','>',0];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('wxpay_log')->where($where)->count();
			$data = Db::name('wxpay_log')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			foreach($data as $k=>$v){
				$member = Db::name('member')->where('id',$v['mid'])->find();
				$data[$k]['nickname'] = $member['nickname'];
				$data[$k]['headimg'] = $member['headimg'];
				if($bset['wxfw_status'] == 1){
					$data[$k]['fenzhangmoney2'] = $v['fenzhangmoney'];
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//删除
	public function wxpaylogdel(){
		return;
		$ids = input('post.ids/a');
		if(!$ids) $ids = array(input('post.id/d'));
		Db::name('wxpay_log')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('微信支付日志删除'.implode(',',$ids),1);
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}