<?php


// +----------------------------------------------------------------------
// | 商城 发票
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Invoice extends Common
{
	//列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
            if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
            if(input('param.invoice_name')) $where[] = ['invoice_name','like','%'.input('param.invoice_name').'%'];
            if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['create_time','>=',strtotime($ctime[0])];
				$where[] = ['create_time','<',strtotime($ctime[1]) + 86400];
			}
			//dump($where);
			$count = 0 + Db::name('invoice')->where($where)->count();
			$data = Db::name('invoice')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			if($data){
				foreach($data as &$v){
					$v['totalprice'] = '';
					$detail = Db::name($v['order_type'].'_order')->where('id',$v['orderid'])->where('aid',aid)->find();
			        if($detail){
			        	if($detail['refund_money']){
				        	$detail['totalprice'] -= $detail['refund_money'];
				        }
				        if($detail['totalprice']<0){
				        	$detail['totalprice'] = 0;
				        }
				        $v['totalprice'] = '￥'.$detail['totalprice'];
		        	}
				}
				unset($v);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//审核
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		$list = Db::name('invoice')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->select()->toArray();
		foreach($list as $v){
			Db::name('invoice')->where('aid',aid)->where('bid',bid)->where('id',$v['id'])->update(['status'=>$st]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//回复
	public function reply(){
		$id = input('post.id/d');
		Db::name('invoice')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['check_remark'=>$_POST['content'],'check_time'=>time()]);
		\app\commons\System::plog('商城发票回复'.$id);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('invoice')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('商城发票删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}
