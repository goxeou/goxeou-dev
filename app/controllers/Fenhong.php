<?php


// +----------------------------------------------------------------------
// | 分红  股东分红 团队分红
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
/**
 * @deprecated Commission/fenhonglog替代
 */
class Fenhong extends Common
{
	//股东分红记录
	public function partner(){
		if(bid > 0) showmsg('无权限操作');
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'fh.id desc';
			}
			$where = array();
			$where[] = ['fh.aid','=',aid];
			if(input('?param.status') && input('param.status')!==''){
				if(input('param.status') == 3){
					$where[] = ['fh.status','=',1];
				}else{
					$where[] = ['og.status','=',input('param.status')];
				}
			}
			if(input('param.ordernum')) $where[] = ['fh.ordernum','like','%'.input('param.ordernum').'%'];
			if(input('param.remark')) $where[] = ['fh.remark','like','%'.input('param.remark').'%'];
			if(input('param.proid')) $where[] = ['fh.proid','=',input('param.proid')];
			if(input('param.ctime')){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['fh.createtime','>=',strtotime($ctime[0])];
				$where[] = ['fh.createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('fenhong_partner')->alias('fh')->join('shop_order_goods og','og.id=fh.ogid','left')->where($where)->count();
			$data = Db::name('fenhong_partner')->alias('fh')->join('shop_order_goods og','og.id=fh.ogid','left')->field('fh.*,og.name,og.pic,og.ggname,og.num,og.totalprice,og.endtime,og.status st')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//股东分红记录导出
	public function partnerexcel(){
		if(bid > 0) showmsg('无权限操作');
		set_time_limit(0);
		ini_set('memory_limit', '2000M');
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'fh.id desc';
		}
		$where = array();
		$where[] = ['fh.aid','=',aid];
		if(input('?param.status') && input('param.status')!==''){
			if(input('param.status') == 3){
				$where[] = ['fh.status','=',1];
			}else{
				$where[] = ['og.status','=',input('param.status')];
			}
		}
		if(input('param.ordernum')) $where[] = ['fh.ordernum','like','%'.input('param.ordernum').'%'];
		if(input('param.remark')) $where[] = ['fh.remark','like','%'.input('param.remark').'%'];
		if(input('param.proid')) $where[] = ['fh.proid','=',input('param.proid')];
		if(input('param.ctime')){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['fh.createtime','>=',strtotime($ctime[0])];
			$where[] = ['fh.createtime','<',strtotime($ctime[1]) + 86400];
		}
		$list = Db::name('fenhong_partner')->alias('fh')->join('shop_order_goods og','og.id=fh.ogid','left')->field('fh.*,og.name,og.pic,og.ggname,og.num,og.totalprice,og.endtime,og.status st')->where($where)->order($order)->select()->toArray();
	
		$title = array('ID','商品名称','订单号','订单金额','分红人数','总分红金额','每人获得','分红状态','订单状态','创建时间','备注');
		$data = [];
		foreach($list as $k=>$vo){
			$st = '';
			if($vo['st']==0) $st = '未支付';
			if($vo['st']==1) $st = '待发货';
			if($vo['st']==2) $st = '已发货';
			if($vo['st']==3) $st = '已完成';
			if($vo['st']==4) $st = '已关闭';
			$data[] = [
				$vo['id'],
				$vo['name'].'('.$vo['ggname'].')',
				$vo['ordernum'],
				$vo['totalprice'],
				$vo['membercount'],
				$vo['totalcommission'],
				$vo['percommission'],
				$vo['status']==1?'已到账':'未到账',
				$st,
				date('Y-m-d H:i',$vo['createtime']),
				$vo['remark']
			]; 
		}
		$this->export_excel($title,$data);
	}
	//删除
	public function fhdel(){
		if(bid > 0) showmsg('无权限操作');
		$id = input('post.id/d');
		Db::name('fenhong_partner')->where('aid',aid)->where('id',$id)->delete();
		\app\commons\System::plog('删除股东分红记录'.$id);
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//团队分红记录
	public function team(){
		if(bid > 0) showmsg('无权限操作');
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'fh.id desc';
			}
			$where = [];
			$where[] = ['fh.aid','=',aid];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['og.status','=',input('param.status')];
			if(input('param.ordernum')) $where[] = ['fh.ordernum','like','%'.input('param.ordernum').'%'];
			if(input('param.remark')) $where[] = ['fh.remark','like','%'.input('param.remark').'%'];
			if(input('param.proid')) $where[] = ['fh.proid','=',input('param.proid')];
			if(input('param.ctime')){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['fh.createtime','>=',strtotime($ctime[0])];
				$where[] = ['fh.createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('shop_order_teamfenhong')->alias('fh')->join('shop_order_goods og','og.id=fh.ogid','left')->where($where)->count();
			$data = Db::name('shop_order_teamfenhong')->alias('fh')->join('shop_order_goods og','og.id=fh.ogid','left')->join('member member','member.id=fh.mid')->field('fh.*,og.name,og.pic,og.ggname,og.num,og.totalprice,og.endtime,og.status st,member.nickname,member.headimg')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//团队分红记录导出
	public function teamexcel(){
		if(bid > 0) showmsg('无权限操作');
		set_time_limit(0);
		ini_set('memory_limit', '2000M');
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'fh.id desc';
		}
		
		$where = [];
		$where[] = ['fh.aid','=',aid];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['og.status','=',input('param.status')];
		if(input('param.ordernum')) $where[] = ['fh.ordernum','like','%'.input('param.ordernum').'%'];
		if(input('param.remark')) $where[] = ['fh.remark','like','%'.input('param.remark').'%'];
		if(input('param.proid')) $where[] = ['fh.proid','=',input('param.proid')];
		if(input('param.ctime')){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['fh.createtime','>=',strtotime($ctime[0])];
			$where[] = ['fh.createtime','<',strtotime($ctime[1]) + 86400];
		}
		$list = Db::name('shop_order_teamfenhong')->alias('fh')->alias('fh')->join('shop_order_goods og','og.id=fh.ogid','left')->join('member member','member.id=fh.mid')->field('fh.*,og.name,og.pic,og.ggname,og.num,og.totalprice,og.endtime,og.status st,member.nickname,member.headimg')->where($where)->order($order)->select()->toArray();
	
		$title = array('ID','商品名称',t('会员').'信息','订单号','订单金额','分红金额','分红状态','订单状态','创建时间','备注');
		$data = [];
		foreach($list as $k=>$vo){
			$st = '';
			if($vo['st']==0) $st = '未支付';
			if($vo['st']==1) $st = '待发货';
			if($vo['st']==2) $st = '已发货';
			if($vo['st']==3) $st = '已完成';
			if($vo['st']==4) $st = '已关闭';
			$data[] = [
				$vo['id'],
				$vo['name'].'('.$vo['ggname'].')',
				$vo['nickname'].'(ID:'.$vo['mid'].')',
				$vo['ordernum'],
				$vo['totalprice'],
				$vo['commission'],
				$vo['status']==1?'已到账':'未到账',
				$st,
				date('Y-m-d H:i',$vo['createtime']),
				$vo['remark']
			]; 
		}
		$this->export_excel($title,$data);
	}
}