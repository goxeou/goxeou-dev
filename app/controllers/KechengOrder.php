<?php


// +----------------------------------------------------------------------
// | 课程-订单记录
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class KechengOrder extends Common
{

    public function initialize(){
        parent::initialize();
        $this->defaultSet();
    }
	//订单列表
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
			$where[] = ['bid','=',bid];
			if($this->mdid){
				$where[] = ['mdid','=',$this->mdid];
			}
			if(input('param.orderid')) $where[] = ['id','=',input('param.orderid')];
			if(input('param.proname')) $where[] = ['proname','like','%'.input('param.proname').'%'];
			if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
			if(input('param.linkman')) $where[] = ['linkman','like','%'.input('param.linkman').'%'];
			if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			if(input('?param.status') && input('param.status')!==''){
				if(input('param.status') == 5){
					$where[] = ['refund_status','=',1];
				}elseif(input('param.status') == 6){
					$where[] = ['refund_status','=',2];
				}elseif(input('param.status') == 7){
					$where[] = ['refund_status','=',3];
				}else{
					$where[] = ['status','=',input('param.status')];
				}
			}
			$count = 0 + Db::name('kecheng_order')->where($where)->count();
			$list = Db::name('kecheng_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			foreach($list as $k=>$vo){
				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$list[$k]['goodsdata'] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
					'<img src="'.$vo['propic'].'" style="max-width:60px;float:left">'.
					'<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
						'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$vo['title'].'</div>'.
						'<div style="padding-top:0px;color:#f60;font-size:12px">购买价￥'.$vo['totalprice'].'</div>'.
					'</div>'.
				'</div>';
				$list[$k]['nickname'] = $member['nickname'];
				$list[$k]['headimg'] = $member['headimg'];
				$list[$k]['platform'] = getplatformname($vo['platform']);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		$where = [];
		if(input('param.')) $where = input('param.');
		$where = json_encode($where);
		View::assign('where',$where);
		return View::fetch();
    }
	//导出
	public function excel(){
		set_time_limit(0);
		ini_set('memory_limit', '2000M');
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'id desc';
		}
        $page = input('param.page');
        $limit = input('param.limit');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		if($this->mdid){
			$where[] = ['mdid','=',$this->mdid];
		}
		if(input('param.proname')) $where[] = ['proname','like','%'.input('param.proname').'%'];
		if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
		if(input('param.linkman')) $where[] = ['linkman','like','%'.input('param.linkman').'%'];
		if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['createtime','>=',strtotime($ctime[0])];
			$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('?param.status') && input('param.status')!==''){
			if(input('param.status') == 5){
				$where[] = ['refund_status','=',1];
			}elseif(input('param.status') == 6){
				$where[] = ['refund_status','=',2];
			}elseif(input('param.status') == 7){
				$where[] = ['refund_status','=',3];
			}else{
				$where[] = ['status','=',input('param.status')];
			}
		}
		$list = Db::name('kecheng_order')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('kecheng_order')->where($where)->order($order)->count();
		$title = array('订单号','下单人','课程名称','课程价格','实付款','支付方式','下单时间');
		$data = [];
		foreach($list as $k=>$vo){
			$member = Db::name('member')->where('id',$vo['mid'])->find();
			$status='';
			if($vo['status']==0){
				$status = '未支付';
			}elseif($vo['status']==1){
				$status = '已支付';
			}
			$data[] = [
				' '.$vo['ordernum'],
				$member['nickname'],
				$vo['title'],
				$vo['product_price'],
				$vo['totalprice'],
				$vo['paytype'],
				date('Y-m-d H:i:s',$vo['createtime']),
				$status
			]; 
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//订单详情
	public function getdetail(){
		$orderid = input('post.orderid');
		$order = Db::name('kecheng_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->find();
		$order['formdata'] = $data;
		$member = Db::name('member')->field('id,nickname,headimg,realname,tel')->where('id',$order['mid'])->find();
		if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];
		return json(['order'=>$order,'member'=>$member]);
	}
	//设置备注
	public function setremark(){
		$orderid = input('post.orderid/d');
		$content = input('post.content');
		Db::name('kecheng_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['remark'=>$content]);
		return json(['status'=>1,'msg'=>'设置完成']);
	}
	//改价格
	public function changeprice(){
		$orderid = input('post.orderid/d');
		$newprice = input('post.newprice/f');
		Db::name('kecheng_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>date('ymdHis').aid.rand(1000,9999)]);
		return json(['status'=>1,'msg'=>'修改完成']);
	}


	//删除
	public function del(){
		$id = input('post.id/d');
		Db::name('kecheng_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//关闭订单
	public function closeOrder(){
		$orderid = input('post.orderid/d');
		$order = Db::name('kecheng_order')->where('id',$orderid)->where('aid',aid)->find();
		if(!$order || $order['status']!=0){
			return json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		Db::name('kecheng_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>4]);
		\app\commons\System::plog('课程订单关闭订单'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
    function defaultSet(){
        $set = Db::name('kecheng_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('kecheng_sysset')->insert(['aid'=>aid]);
        }
    }
}
