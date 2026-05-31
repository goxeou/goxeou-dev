<?php


//管理员中心 - 菜品管理
namespace app\controllers;
use think\facade\Db;
class ApiAdminRestaurantProduct extends ApiAdmin
{	
	public function index(){
		$where[] = ['aid', '=', aid];
        $where[] = ['bid', '=', bid];
		$st = input('param.st');
		if(!input('?param.st') || input('param.st') === ''){
			$st = 'all';
		}
		if(input('param.keyword')) $where[] = ['name', 'like', '%'.input('param.keyword').'%'];

        $countall = Db::name('restaurant_product')->where($where)->count();
        $count0 = Db::name('restaurant_product')->where(array_merge($where,[['status', '=', 0]]))->count();
        $count1 = Db::name('restaurant_product')->where(array_merge($where,[['status', '=', 1]]))->count();

        if($st == 'all'){

        }elseif($st == '0'){
            $where[] = ['status', '=', 0];
        }elseif($st == '1'){
            $where[] = ['status', '=', 1];
        }

		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('restaurant_product')->where($where)->page($pagenum,$pernum)->order('sort desc,id desc')->select();
		if(!$datalist) $datalist = array();
		if(request()->isAjax()){
			return ['status'=>1,'data'=>$datalist];
		}

		$rdata = [];
		$rdata['countall'] = $countall;
		$rdata['count0'] = $count0;
		$rdata['count1'] = $count1;
		$rdata['datalist'] = $datalist;
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	//菜品编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
		}else{
			$info = ['id'=>'','status'=>1];
		}
		//多规格
		$newgglist = array();
		if($info){
			$gglist = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$info['id'])->select()->toArray();
			foreach($gglist as $k=>$v){
				$v['lvprice_data'] = json_decode($v['lvprice_data']);
				if($v['ks']!==null){
					$newgglist[$v['ks']] = $v;
				}else{
					Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$v['id'])->update(['ks'=>$k]);
					$newgglist[$k] = $v;
				}
			}
			if(!$info['guigedata']) $info['guigedata'] = '[{"k":0,"title":"规格","items":[{"k":0,"title":"默认规格"}]}]';
		}else{
			$info = ['id'=>'','freighttype'=>1,'sales'=>0,'sort'=>0,'limit_per'=>0,'status'=>1,'guigedata'=>'[{"k":0,"title":"规格","items":[{"k":0,"title":"默认规格"}]}]'];
		}
		$guigedata = json_decode($info['guigedata'],true);
		//分类
		$clist = Db::name('restaurant_product_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray();

		$cateArr = Db::name('restaurant_product_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->column('name','id');

        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
		$levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();

		$pagecontent = json_decode(\app\commons\System::initpagecontent($info['detail'],aid),true);
		if(!$pagecontent) $pagecontent = [];
		
		$rdata = [];
		$rdata['aglevellist'] = $aglevellist;
		$rdata['levellist'] = $levellist;
		$rdata['info'] = $info;
		$rdata['pagecontent'] = $pagecontent;
		$rdata['newgglist'] = $newgglist;
		$rdata['clist'] = $clist;
		$rdata['guigedata'] = $guigedata;
		$rdata['pic'] = $info['pic'] ? [$info['pic']] : [];
		$rdata['pics'] = $info['pics'] ? explode(',',$info['pics']) : [];
		$rdata['cids'] = $info['cid'] ? explode(',',$info['cid']) : [];
		$rdata['gids'] = $info['gid'] ? explode(',',$info['gid']) : [];
		$rdata['cateArr'] = $cateArr;
        $rdata['product_showset'] = 0;
		return $this->json($rdata);
	}
	//保存菜品
	public function save(){
		if(input('post.id')) $product = Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
		$info = input('post.info/a');
		$data = array();
		$data['name'] = $info['name'];
		$data['pic'] = $info['pic'];
		$data['pics'] = $info['pics'];
		$data['procode'] = $info['procode'];
		$data['cid'] = $info['cid'];
//		$data['freighttype'] = $info['freighttype'];
//		$data['freightdata'] = $info['freightdata'];
//		$data['freightcontent'] = $info['freightcontent'];
		//$data['commissionset'] = $info['commissionset'];
		//$data['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
		//$data['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
		//$data['commissiondata3'] = jsonEncode(input('post.commissiondata3/a'));
        //$data['video'] = $info['video'];
		//$data['video_duration'] = $info['video_duration'];
		$data['limit_per'] = $info['limit_per'];
		//$data['scoredkmaxset'] = $info['scoredkmaxset'];
		//$data['scoredkmaxval'] = $info['scoredkmaxval'];
		
		if($info['oldsales'] != $info['sales']){
			$data['sales'] = $info['sales'];
		}
		$data['sort'] = $info['sort'];
		$data['status'] = $info['status'];
		if($info['status'] == 2){
			$data['start_time'] = $info['start_time'];
			$data['end_time'] = $info['end_time'];
		}
		if($info['status'] == 3){
			$data['start_hours'] = $info['start_hours'];
			$data['end_hours'] = $info['end_hours'];
		}
		$data['detail'] = json_encode(input('post.pagecontent'));
		if($info['gid']){
			$data['gid'] = implode(',',$info['gid']);
		}else{
			$data['gid'] = '';
		}
		if(!$product){
			$data['create_time'] = time();
		}
		$data['lvprice'] = $info['lvprice'];

		
		$gglist = input('post.gglist');
		if($info['lvprice']==1){
            $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
            $default_cid = $default_cid ? $default_cid : 0;
            $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
			$defaultlvid = $levellist[0]['id'];
			$sellprice_field = 'sell_price_'.$defaultlvid;
		}else{
			$sellprice_field = 'sell_price';
		}
		$sell_price = 0;$market_price = 0;$cost_price = 0;$weight = 0;$givescore=0;$lvprice_data = [];
		foreach($gglist as $ks=>$v){
			if($sell_price==0 || $v[$sellprice_field] < $sell_price){
				$sell_price = $v[$sellprice_field];
				$market_price = $v['market_price'];
				$cost_price = $v['cost_price'];
				$givescore = $v['givescore'];
				$weight = $v['weight'];
				if($info['lvprice']==1){
					$lvprice_data = [];
					foreach($levellist as $lv){
						$lvprice_data[$lv['id']] = $v['sell_price_'.$lv['id']];
					}
				}
			}
		}
		if($info['lvprice']==1){
			$data['lvprice_data'] = json_encode($lvprice_data);
		}
		
		$data['market_price'] = $market_price;
		$data['cost_price'] = $cost_price;
		$data['sell_price'] = $sell_price;
		$data['givescore'] = $givescore;
		$data['stock'] = 0;
		foreach($gglist as $v){
			$data['stock'] += $v['stock'];
		}
		//多规格 规格项
		$data['guigedata'] = json_encode(input('post.guigedata'));
		
//		if(bid !=0 ){
//			$bset = Db::name('business_sysset')->where('aid',aid)->find();
//			if($bset['product_check'] == 1){
//				$data['ischecked'] = 0;
//			}
//		}
		if($product){
			Db::name('restaurant_product')->where('aid',aid)->where('id',$product['id'])->update($data);
			$proid = $product['id'];
			\app\commons\System::plog('餐饮菜品编辑'.$proid);
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
			$proid = Db::name('restaurant_product')->insertGetId($data);
			\app\commons\System::plog('餐饮菜品编辑'.$proid);
		}
		//dump(input('post.option/a'));die;
		//多规格
		$newggids = array();
		foreach($gglist as $ks=>$v){
			$ggdata = array();
			$ggdata['product_id'] = $proid;
			$ggdata['ks'] = $v['ks'];
			$ggdata['name'] = $v['name'];
			$ggdata['pic'] = $v['pic'] ? $v['pic'] : '';
			$ggdata['market_price'] = $v['market_price']>0 ? $v['market_price']:0;
			$ggdata['cost_price'] = $v['cost_price']>0 ? $v['cost_price']:0;
			$ggdata['sell_price'] = $v['sell_price']>0 ? $v['sell_price']:0;
			$ggdata['procode'] = $v['procode'];
			$ggdata['givescore'] = $v['givescore'];
            $ggdata['stock_daily'] = $v['stock_daily']>0 ? $v['stock_daily']:0;
			$ggdata['stock'] = $v['stock']>0 ? $v['stock']:0;
			$lvprice_data = [];
			if($info['lvprice']==1){
				$ggdata['sell_price'] = $v['sell_price_'.$levellist[0]['id']]>0 ? $v['sell_price_'.$levellist[0]['id']]:0;
				foreach($levellist as $lv){
					$sell_price = $v['sell_price_'.$lv['id']]>0 ? $v['sell_price_'.$lv['id']]:0;
					$lvprice_data[$lv['id']] = $sell_price;
				}
				$ggdata['lvprice_data'] = json_encode($lvprice_data);
			}

			$guige = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$proid)->where('ks',$ks)->find();
			if($guige){
				Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$guige['id'])->update($ggdata);
				$ggid = $guige['id'];
			}else{
				$ggdata['aid'] = aid;
				$ggid = Db::name('restaurant_product_guige')->insertGetId($ggdata);
			}
			$newggids[] = $ggid;
		}
		Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$proid)->where('id','not in',$newggids)->delete();
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//上下架
	public function setst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		Db::name('restaurant_product')->where(['aid'=>aid,'bid'=>bid,'id'=>$id])->update(['status'=>$st]);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	//菜品删除
	public function del(){
		$id = input('post.id/d');
		Db::name('restaurant_product')->where(['aid'=>aid,'bid'=>bid,'id'=>$id])->delete();
		Db::name('restaurant_product_guige')->where(['aid'=>aid,'product_id'=>$id])->delete();
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
}