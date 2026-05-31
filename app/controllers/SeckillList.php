<?php


// +----------------------------------------------------------------------
// | 秒杀 秒杀列表
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class SeckillList extends Common
{
    public function index(){
        $sysset = Db::name('seckill_sysset')->where('aid',aid)->find();
        $systimeset = explode(',',$sysset['timeset']);
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				if(input('param.field') == 'seckill_date'){
					$order = 'seckill_prodata.seckill_date '.input('param.order') . ',seckill_prodata.seckill_time '.input('param.order');
				}else{
					$order = input('param.field').' '.input('param.order');
				}
			}else{
				$order = 'seckill_prodata.seckill_date desc,seckill_prodata.seckill_time desc';
			}
			$where = [];
			$where[] = ['seckill_prodata.aid','=',aid];
			$where[] = ['seckill_prodata.bid','=',bid];
			if(input('param.proname')) $where[] = ['shop_product.name','like','%'.input('param.proname').'%'];
			if(input('param.seckill_date')) $where[] = ['seckill_prodata.seckill_date','=',input('param.seckill_date')];
			if(input('param.seckill_time')) $where[] = ['seckill_prodata.seckill_time','=',input('param.seckill_time')];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}

			$subQuery = Db::name('seckill_prodata')->alias('seckill_prodata')->field("seckill_prodata.id")->join('shop_product shop_product','shop_product.id=seckill_prodata.proid')->group('proid,seckill_date,seckill_time')->where($where)->buildSql();
			//var_dump($subQuery);die;
			$count = 0 + Db::table('('.$subQuery.') a')->count();
			$data = Db::name('seckill_prodata')->alias('seckill_prodata')->field('shop_product.cid,shop_product.name,shop_product.pic,seckill_prodata.*,min(seckill_prodata.seckill_price)seckill_price,sum(seckill_prodata.seckill_num)seckill_num,sum(seckill_prodata.sales)sales')->join('shop_product shop_product','shop_product.id=seckill_prodata.proid')->group('proid,seckill_date,seckill_time')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			//dump($data);die;

			$clist = Db::name('shop_category')->where('aid',aid)->select()->toArray();
			$cdata = array();
			foreach($clist as $c){
				$cdata[$c['id']] = $c['name'];
			}
			foreach($data as $k=>$v){
				$data[$k]['cname'] = $cdata[$v['cid']];
				$data[$k]['starttime'] = strtotime($v['seckill_date']) + $v['seckill_time']*3600;
				//下一场
				$thisindex = array_search($v['seckill_time'],$systimeset);
				if($thisindex+1 == count($systimeset)){
					$nextstarttime = strtotime($v['seckill_date'])+86400 + $systimeset[0] * 3600;
				}else{
					$nextstarttime = strtotime($v['seckill_date']) + $systimeset[$thisindex+1] * 3600;
				}
				$data[$k]['nextstarttime'] = $nextstarttime;
				$data[$k]['salepercent'] = intval($v['sales']/$v['seckill_num'] * 100);
				
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		View::assign('sysset',$sysset);
		View::assign('systimeset',$systimeset);
		return View::fetch();
    }
	public function getggdate(){
		$gglist = Db::name('seckill_prodata')->alias('seckill_prodata')->field('shop_guige.name,shop_guige.sell_price,shop_guige.stock,shop_guige.procode,shop_guige.ks,seckill_prodata.*')->join('shop_guige shop_guige','shop_guige.id=seckill_prodata.ggid')->where(['seckill_prodata.aid'=>aid,'seckill_prodata.bid'=>bid,'seckill_prodata.proid'=>$_POST['proid'],'seckill_prodata.seckill_date'=>$_POST['seckill_date'],'seckill_prodata.seckill_time'=>$_POST['seckill_time']])->select()->toArray();

		$product = Db::name('shop_product')->where('aid',aid)->where('bid',bid)->where('id',$_POST['proid'])->find();
		//多规格
		$newgglist = array();
		foreach($gglist as $k=>$v){
			$newgglist[$v['ks']] = $v;
		}
		$guigedata = json_decode($product['guigedata']);
		return json(['product'=>$product,'gglist'=>$newgglist,'guigedata'=>$guigedata]);
	}
	public function save(){
		$info = input('post.info/a');
		$timeset = array();
		$timesetArr = input('post.timeset/a');
		//if(!$timesetArr) $timesetArr = array();
		foreach($timesetArr as $k=>$v){
			$timeset[] = $k;
		}
		if(!$info['status']) $info['status'] = 0;
		$info['aid'] = aid;
		$info['timeset'] = implode(',',$timeset);

		Db::name('seckill_sysset')->where('aid',aid)->update($info);
		\app\commons\System::plog('秒杀系统设置');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		foreach($ids as $id){
			$rs = Db::name('seckill_prodata')->where('aid',aid)->where('bid',bid)->where('id',intval($id))->find();
			Db::name('seckill_prodata')->where('aid',aid)->where('bid',bid)->where(['proid'=>$rs['proid'],'seckill_date'=>$rs['seckill_date'],'seckill_time'=>$rs['seckill_time']])->delete();
		}
		\app\commons\System::plog('秒杀活动删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	public function edit(){
		$post = input('post.');
		foreach($post['option'] as $k=>$v){
			Db::name('seckill_prodata')->where('aid',aid)->where('bid',bid)->where('id',$k)->update($v);
		}
		\app\commons\System::plog('编辑秒杀活动');
		return json(['status'=>1,'msg'=>'保存成功']);
	}
}