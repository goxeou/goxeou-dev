<?php


// +----------------------------------------------------------------------
// | 秒杀 商品设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class SeckillProset extends Common
{
    public function index(){
		$sysset = Db::name('seckill_sysset')->where('aid',aid)->find();
		$systimeset = explode(',',$sysset['timeset']);
		View::assign('sysset',$sysset);
		View::assign('systimeset',$systimeset);
		return View::fetch();
    }
	public function save(){
		$proid = input('post.proid/d');
		$timeset = input('post.timeset/a');
		$dateset = input('post.dateset/a');
		$buymax = input('post.buymax/d');
		if($proid==0){ return json(['status'=>0,'msg'=>'请选择商品']);}
		if(!$timeset){ return json(['status'=>0,'msg'=>'请选择时间']);}
		if(!$dateset){ return json(['status'=>0,'msg'=>'请选择日期']);}
		foreach($dateset as $k=>$date){
			if(!$date) unset($dateset[$k]);
		}
		if(!$dateset){ return json(['status'=>0,'msg'=>'请选择日期']);}
		$gglist = array();
		foreach(input('post.option/a') as $k=>$v){
			$gglist[] = ['aid'=>aid,'bid'=>bid,'proid'=>$proid,'ggid'=>$k,'seckill_price'=>$v['seckill_price'],'sell_price'=>$v['sell_price'],'seckill_num'=>$v['seckill_num'],'createtime'=>time(),'buymax'=>$buymax];
		}
		$datalist = array();
		foreach($dateset as $date){
			foreach($timeset as $time){
				$rs = Db::name('seckill_prodata')->where('aid',aid)->where('proid',$proid)->where('seckill_date',$date)->where('seckill_time',$time)->find();
				if($rs){
					return json(['status'=>0,'msg'=>"该商品在{$date} ".($time<10?'0':'')."{$time}:00已设置秒杀活动,请勿重复设置"]);
				}
				foreach($gglist as $gg){
					$datalist[] = array_merge(['seckill_date'=>$date,'seckill_time'=>$time,'starttime'=>strtotime($date)+$time*3600],$gg);
				}
			}
		}
		foreach($datalist as $data){
			Db::name('seckill_prodata')->insert($data);
		}
		\app\commons\System::plog('添加秒杀活动');
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('SeckillList/index')]);
	}
}