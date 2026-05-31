<?php


namespace app\models;
use think\facade\Db;
use think\facade\Log;
class ShopProduct
{
	//减关联库存
	public static function declinkstock($proid,$ggid,$num){
		$splitlist = Db::name('shop_ggsplit')->where('proid',$proid)->where("ggid1={$ggid} or ggid2={$ggid}")->select()->toArray();
		if(!$splitlist) return;
		$guige = Db::name('shop_guige')->where('id',$ggid)->find();
		foreach($splitlist as $k=>$v){
			if($v['ggid1'] == $ggid){
				$thisnum = $num * $v['multiple'];
				Db::name('shop_guige')->where('id',$v['ggid2'])->update(['stock'=>Db::raw("IF(stock>={$thisnum},stock-{$thisnum},0)"),'sales'=>Db::raw("sales+$thisnum")]);
			}
			if($v['ggid2'] == $ggid){
				//$thisnum = ceil($num / $v['multiple']);
				$thisStock = floor($guige['stock'] / $v['multiple']);
				$thisSales = floor($guige['sales'] / $v['multiple']);
				Db::name('shop_guige')->where('id',$v['ggid1'])->update(['stock'=>$thisStock,'sales'=>$thisSales]);
			}
		}
		self::calculateStock($proid);
	}
	//加关联库存
	public static function addlinkstock($proid,$ggid,$num){
		$splitlist = Db::name('shop_ggsplit')->where('proid',$proid)->where("ggid1={$ggid} or ggid2={$ggid}")->select()->toArray();
		if(!$splitlist) return;
		$guige = Db::name('shop_guige')->where('id',$ggid)->find();
		foreach($splitlist as $k=>$v){
			if($v['ggid1'] == $ggid){
				$thisnum = $num * $v['multiple'];
				Db::name('shop_guige')->where('id',$v['ggid2'])->update(['stock'=>Db::raw("stock+$thisnum"),'sales'=>Db::raw("sales-$thisnum")]);
			}
			if($v['ggid2'] == $ggid){
				$thisStock = floor($guige['stock'] / $v['multiple']);
				$thisSales = floor($guige['sales'] / $v['multiple']);
				Db::name('shop_guige')->where('id',$v['ggid1'])->update(['stock'=>$thisStock,'sales'=>$thisSales]);
			}
		}
		self::calculateStock($proid);
	}
	//计算商品总库存
	public static function calculateStock($proid){
		$ggids = Db::name('shop_ggsplit')->where('proid',$proid)->column('ggid1');
		if($ggids){
			$totalstock = Db::name('shop_guige')->where('proid',$proid)->where('id','not in',$ggids)->sum('stock');
			$totalsales = Db::name('shop_guige')->where('proid',$proid)->where('id','not in',$ggids)->sum('sales');
			Db::name('shop_product')->where('id',$proid)->update(['stock'=>$totalstock,'sales'=>$totalsales]);
		}
	}
	//验证关联库存
	public static function checkstock($prolist){
		$ggidnums = [];
		foreach($prolist as $v){
			$ggid = $v['guige']['id'];
			$linkggArr = Db::name('shop_ggsplit')->where('ggid1',$ggid)->select()->toArray();
			if($linkggArr){
				foreach($linkggArr as $linkgg){
					$ggid2 = $linkgg['ggid2'];
					$num = $v['num'] * $linkgg['multiple'];
					if($ggidnums[$ggid2]){
						$ggidnums[$ggid2] += $num;
					}else{
						$ggidnums[$ggid2] = $num;
					}
				}
			}else{
				if($ggidnums[$ggid]){
					$ggidnums[$ggid] += $v['num'];
				}else{
					$ggidnums[$ggid] = $v['num'];
				}
			}
		}
		foreach($ggidnums as $ggid=>$num){
			$guige = Db::name('shop_guige')->where('id',$ggid)->find();
			if($guige['stock'] < $num) return ['status'=>0,'msg'=>'库存不足'];
		}
		return ['status'=>1];
	}

	//加库存记录
	public static function addStockRecord($proid,$ggid,$stock){
		$product = Db::name('shop_product')->where('id',$proid)->find();
		if(!$product) return ['status'=>0,'msg'=>'商品不存在'];
		$guige = Db::name('shop_guige')->where('id',$ggid)->find();
		if(!$guige) return ['status'=>0,'msg'=>'规格不存在'];
		$data = [];
		$data['aid'] = $product['aid'];
		$data['bid'] = $product['bid'];
		$data['proid'] = $proid;
		$data['ggid'] = $ggid;
		$data['stock'] = $stock;
		$data['afterstock'] = $guige['stock'];
		$data['createtime'] = time();
		Db::name('shop_product_stockrecord')->insertGetId($data);
		return ['status'=>1];
	}

	/*验证会员等级限购*/
	public static function memberlevel_limit($aid,$mid,$product,$levelid){
		$levellimitdata = json_decode($product['levellimitdata'],true);
		$limitdata = [];
		foreach($levellimitdata as $level){
			if($levelid == $level['level_id'] && $level['days'] > 0){
				$startdays = strtotime('-'.$level['days'].' day');
				$buynum =Db::name('shop_order_goods')->where('aid',$aid)->where('mid',$mid)->where('proid',$product['id'])->where('status','in','0,1,2,3')->where('createtime','between',[$startdays,time()])->sum('num');
				if($buynum>=$level['limit_num']){
					$limitdata['ismemberlevel_limit'] = true;
					$limitdata['days'] = $level['days'];
					$limitdata['limit_num'] = $level['limit_num'];
				}else{
					$limitdata['ismemberlevel_limit'] = false;
				}
			}
		}	
		return ['status'=>1,'limitdata'=>$limitdata];
	}
	
}