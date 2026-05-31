<?php


//管理员中心 - 产品管理
namespace app\controllers;
use think\facade\Db;
class ApiAdminProduct extends ApiAdmin
{	
	public function index(){
        $where[] = ['aid', '=', aid];
        $where[] = ['bid', '=', bid];
		$st = input('param.st');
		if(!input('?param.st') || input('param.st') === ''){
			$st = 'all';
		}
        if(input('param.keyword')) $where[] = ['name', 'like', '%'.input('param.keyword').'%'];

        $countall = Db::name('shop_product')->where($where)->count();
        $count0 = Db::name('shop_product')->where(array_merge($where,[['status', '=', 0]]))->count();
        $count1 = Db::name('shop_product')->where(array_merge($where,[['status', '=', 1]]))->count();

        if($st == 'all'){

        }elseif($st == '0'){
            $where[] = ['status', '=', 0];
        }elseif($st == '1'){
            $where[] = ['status', '=', 1];
        }
		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('shop_product')->where($where)->page($pagenum,$pernum)->order('sort desc,id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $k=>$d){
			$isstock_warning=0;
			$datalist[$k]['plate_id'] = $d['plate_id']??0;
			$datalist[$k]['isstock_warning']=$isstock_warning;
		}
		if(request()->isAjax()){
			return ['status'=>1,'data'=>$datalist];
		}
		$rdata = [];
		$rdata['countall'] = $countall;
		$rdata['count0'] = $count0;
		$rdata['count1'] = $count1;
		$rdata['datalist'] = $datalist;
		$rdata['st'] = $st;
        $add_product = 1;//允许添加商品
        $status_product = 1;//允许上下架商品
        $stock_product = 1;//允许修改商品库存
        $manage_set = [
            'add_product' => $add_product,
            'status_product' => $status_product,
            'stock_product' => $stock_product
        ];
        $rdata['manage_set'] = $manage_set;
		return $this->json($rdata);
	}
	//商品编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('shop_product')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
			if($info['lvprice'] == 1) return json(['status'=>-4,'msg'=>'该商品已开启会员价,暂不支持手机端修改','url'=>'index']);
		}else{
			$info = ['id'=>'','gettj'=>'-1','showtj'=>'-1','status'=>1];
		}
		//多规格
		$newgglist = array();
		if($info){
			$gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$info['id'])->select()->toArray();
			foreach($gglist as $k=>$v){
				$v['lvprice_data'] = json_decode($v['lvprice_data']);
				$isstock_warning=0;
				$v['isstock_warning'] = $isstock_warning;
				if($v['ks']!==null){
					$newgglist[$v['ks']] = $v;
				}else{
					Db::name('shop_guige')->where('aid',aid)->where('id',$v['id'])->update(['ks'=>$k]);
					$newgglist[$k] = $v;
				}
			}
			if(!$info['guigedata']) $info['guigedata'] = '[{"k":0,"title":"规格","items":[{"k":0,"title":"默认规格"}]}]';
		}else{
			$info = ['id'=>'','freighttype'=>1,'sales'=>0,'sort'=>0,'perlimit'=>0,'status'=>1,'guigedata'=>'[{"k":0,"title":"规格","items":[{"k":0,"title":"默认规格"}]}]'];
		}
		$guigedata = json_decode($info['guigedata'],true);
		//分类
		$clist = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$child = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
			foreach($child as $k2=>$v2){
				$child2 = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
				$child[$k2]['child'] = $child2;
			}
			$clist[$k]['child'] = $child;
		}
		$cateArr = Db::name('shop_category')->Field('id,name')->where('aid',aid)->column('name','id');
		
		if(bid > 0){
			//商家的分类
			$clist2 = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
			foreach($clist2 as $k=>$v){
				$child = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
				foreach($child as $k2=>$v2){
					$child2 = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
					$child[$k2]['child'] = $child2;
				}
				$clist2[$k]['child'] = $child;
			}
			$cateArr2 = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('bid',bid)->column('name','id');
		}else{
			$clist2 = [];
			$cateArr2 = [];
		}

		//分组
		$glist = Db::name('shop_group')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		$groupArr = Db::name('shop_group')->Field('id,name')->where('aid',aid)->column('name','id');
		$freightList = Db::name('freight')->where('aid',aid)->where('bid',bid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$freightdata = array();
		if($info && $info['freightdata']){
			$freightdata = Db::name('freight')->where('aid',aid)->where('bid',bid)->where('id','in',$info['freightdata'])->order('sort desc,id')->select()->toArray();
		}
		$info['gettj'] = explode(',',$info['gettj']);
        $info['showtj'] = explode(',',$info['showtj']);
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
		$levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();

		$pagecontent = json_decode(\app\commons\System::initpagecontent($info['detail'],aid),true);
		if(!$pagecontent) $pagecontent = [];

		$product_showset = 1;
		$commission_canset = 1;
		$parambid = bid;
		if(bid != 0){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			$product_showset = $bset['product_showset'];
			$commission_canset = $bset['commission_canset'];
			}
		//商品参数
        $whereParam = [];
        $whereParam[] = ['aid','=',aid];
        $whereParam[] = ['status','=',1];
        if($info['cid']){
            $whereCid = [];
            foreach(explode(',',$info['cid']) as $k => $c2){
                if($c2 == '') continue;
                $whereCid[] = "find_in_set({$c2},cid)";
            }
            if($whereCid){
				if(false){}else{
					$whereParam[] = ['bid','=',$parambid];
					$whereParam[] = Db::raw(implode(' or ',$whereCid). " or cid =''");
				}
            }else{
				$whereParam[] = ['bid','=',$parambid];
                $whereParam[] = Db::raw("cid =''");
			}
        }else{
			$whereParam[] = ['bid','=',$parambid];
            $whereParam[] = Db::raw(" cid =''");
        }
		$paramList = Db::name('shop_param')->where($whereParam)->order('sort desc,id')->select()->toArray();

		//$paramList = Db::name('shop_param')->where('aid',aid)->where('bid',$parambid)->where('status',1)->order('id,sort desc')->select()->toArray();
		foreach($paramList as $k=>$v){
			$paramList[$k]['params'] = json_decode($v['params'],true);
		}
		$paramdata = $info['paramdata'] && $info['paramdata']!='null' ? json_decode($info['paramdata'],true) : [];

		$business_selfscore = 0;
		$rdata = [];
		$rdata['aglevellist'] = $aglevellist;
		$rdata['levellist'] = $levellist;
		$rdata['info'] = $info;
		$rdata['pagecontent'] = $pagecontent;
		$rdata['newgglist'] = $newgglist;
		$rdata['freightList'] = $freightList;
		$rdata['freightdata'] = $freightdata;
		$rdata['clist'] = $clist;
		$rdata['clist2'] = $clist2;
		$rdata['glist'] = $glist;
		$rdata['guigedata'] = $guigedata;
		$rdata['pic'] = $info['pic'] ? [$info['pic']] : [];
		$rdata['pics'] = $info['pics'] ? explode(',',$info['pics']) : [];
		$rdata['cids'] = $info['cid'] ? explode(',',$info['cid']) : [];
		$rdata['cids2'] = $info['cid2'] ? explode(',',$info['cid2']) : [];
		$rdata['gids'] = $info['gid'] ? explode(',',$info['gid']) : [];
		$rdata['cateArr'] = $cateArr;
		$rdata['cateArr2'] = $cateArr2;
		$rdata['groupArr'] = $groupArr;
		$rdata['product_showset'] = $product_showset;
		$rdata['commission_canset'] = $commission_canset;
		$rdata['bid'] = bid;
		$rdata['paramList'] = $paramList;
		$rdata['paramdata'] = $paramdata;
		$rdata['business_selfscore'] = $business_selfscore;
		return $this->json($rdata);
	}
    public function getParam(){
        $cid = input('post.cid');
        //商品参数
		$parambid = bid;
		$whereParam = [];
        $whereParam[] = ['aid','=',aid];
        $whereParam[] = ['status','=',1];
        if($cid){
            $cid = explode(',',$cid);
            $whereCid = [];
            foreach($cid as $k => $c2){
                $whereCid[] = "find_in_set({$c2},cid)";
            }
			if(false){}else{
				$whereParam[] = ['bid','=',$parambid];
				$whereParam[] = Db::raw(implode(' or ',$whereCid). " or cid =''");
			}
        }else{
			$whereParam[] = ['bid','=',$parambid];
            $whereParam[] = Db::raw(" cid =''");
        }

        $paramList = Db::name('shop_param')->where($whereParam)->order('sort desc,id')->select()->toArray();
        $paramList = $paramList ? $paramList : [];
		foreach($paramList as $k=>$v){
			$paramList[$k]['params'] = json_decode($v['params'],true);
		}

        return json(['status'=>1,'msg'=>'操作成功','paramList'=>$paramList]);
    }
	//保存商品
	public function save(){
		if(input('post.id')) $product = Db::name('shop_product')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
		$info = input('post.info/a');
		$data = array();
		$data['name'] = $info['name'];
		$data['pic'] = $info['pic'];
		$data['pics'] = $info['pics'];
		if(isset($info['procode'])) $data['procode'] = $info['procode'];
        if(isset($info['sellpoint'])) $data['sellpoint'] = $info['sellpoint'];
        if(isset($info['cid'])) $data['cid'] = $info['cid'];
        if(isset($info['freighttype'])) $data['freighttype'] = $info['freighttype'];
        if(isset($info['freightdata'])) $data['freightdata'] = $info['freightdata'];
        if(isset($info['freightcontent'])) $data['freightcontent'] = $info['freightcontent'];
		//$data['commissionset'] = $info['commissionset'];
		//$data['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
		//$data['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
		//$data['commissiondata3'] = jsonEncode(input('post.commissiondata3/a'));
		
		//$data['video'] = $info['video'];
		//$data['video_duration'] = $info['video_duration'];
        if(isset($info['perlimit'])) $data['perlimit'] = $info['perlimit'];
        if(isset($info['limit_start'])) $data['limit_start'] = $info['limit_start'];
		//$data['scoredkmaxset'] = $info['scoredkmaxset'];
		//$data['scoredkmaxval'] = $info['scoredkmaxval'];
        if(isset($info['gettj'])) $data['gettj'] = implode(',',$info['gettj']);
        if(isset($info['showtj'])) $data['showtj'] = implode(',',$info['showtj']);

		if(bid != 0){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			//if($bset['commission_canset']==0){
			//	$data['commissionset'] = '-1';
			//}
			if($bset['product_showset']==0){
				$data['showtj'] = '-1';
				$data['gettj'] = '-1';
				$data['lvprice'] = 0;
			}
			$data['cid2'] = $info['cid2'];
		}
		
		if($info['oldsales'] != $info['sales']){
			$data['sales'] = $info['sales'];
		}
		$data['sort'] = $info['sort'];
		$data['status'] = $info['status'];
		if($info['status'] == 2){
            if(isset($info['start_time'])) $data['start_time'] = $info['start_time'];
            if(isset($info['end_time'])) $data['end_time'] = $info['end_time'];
		}
		if($info['status'] == 3){
            if(isset($info['start_hours'])) $data['start_hours'] = $info['start_hours'];
            if(isset($info['end_hours'])) $data['end_hours'] = $info['end_hours'];
		}
        if(input('?post.pagecontent')) $data['detail'] = json_encode(input('post.pagecontent'));
		if($info['gid']){
			$data['gid'] = implode(',',$info['gid']);
		}else{
			$data['gid'] = '';
		}
		if(!$product){
			$data['createtime'] = time();
			//$data['gettj'] = '-1';
		}
        if(isset($info['lvprice'])) $data['lvprice'] = $info['lvprice'];

        if(isset($info['gettjtip'])) $data['gettjtip'] = $info['gettjtip'];
        if(isset($info['gettjurl'])) $data['gettjurl'] = $info['gettjurl'];
		
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
		$sell_price = 0;$market_price = 0;$cost_price = 0;$weight = 0;$givescore=0;$lvprice_data = [];$i=0;
		foreach($gglist as $ks=>$v){
			if($i==0 || $v[$sellprice_field] < $sell_price){
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
			$i++;
		}
		if($info['lvprice']==1){
			$data['lvprice_data'] = json_encode($lvprice_data);
		}
		
		$data['market_price'] = $market_price;
		$data['cost_price'] = $cost_price;
		$data['sell_price'] = $sell_price;

		$business_selfscore = 0;
		if(bid == 0 || $business_selfscore==1){
			$data['givescore'] = $givescore;
		}
		$data['weight'] = $weight;
		$data['stock'] = 0;
		foreach($gglist as $v){
			$data['stock'] += $v['stock'];
		}
		//多规格 规格项
		$data['guigedata'] = json_encode(input('post.guigedata'));

		$data['paramdata'] = jsonEncode(input('post.paramdata/a'));
		
		if(bid !=0 ){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			if($bset['product_check'] == 1){
				$data['ischecked'] = 0;
			}
		}
		if($product){
			Db::name('shop_product')->where('aid',aid)->where('id',$product['id'])->update($data);
			$proid = $product['id'];
			\app\commons\System::plog('商城商品编辑'.$proid);
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
			$proid = Db::name('shop_product')->insertGetId($data);
			\app\commons\System::plog('商城商品编辑'.$proid);
		}
		//dump(input('post.option/a'));die;
		//多规格
		$newggids = array();
		foreach($gglist as $ks=>$v){
			$ggdata = array();
			$ggdata['proid'] = $proid;
			$ggdata['ks'] = $v['ks'];
			$ggdata['name'] = $v['name'];
			$ggdata['pic'] = $v['pic'] ? $v['pic'] : '';
			$ggdata['market_price'] = $v['market_price']>0 ? $v['market_price']:0;
			$ggdata['cost_price'] = $v['cost_price']>0 ? $v['cost_price']:0;
			$ggdata['sell_price'] = $v['sell_price']>0 ? $v['sell_price']:0;
			$ggdata['weight'] = $v['weight']>0 ? $v['weight']:0;
			$ggdata['procode'] = $v['procode'];
			if(bid == 0 || $business_selfscore==1){
				$ggdata['givescore'] = $v['givescore'];
			}
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

			$guige = Db::name('shop_guige')->where('aid',aid)->where('proid',$proid)->where('ks',$ks)->find();
			if($guige){
				Db::name('shop_guige')->where('aid',aid)->where('id',$guige['id'])->update($ggdata);
				$ggid = $guige['id'];
			}else{
				$ggdata['aid'] = aid;
				$ggid = Db::name('shop_guige')->insertGetId($ggdata);
			}
			$newggids[] = $ggid;
		}
		Db::name('shop_guige')->where('aid',aid)->where('proid',$proid)->where('id','not in',$newggids)->delete();

		\app\commons\Wxvideo::updateproduct($proid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//上下架
	public function setst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		Db::name('shop_product')->where(['aid'=>aid,'bid'=>bid,'id'=>$id])->update(['status'=>$st]);
		
		if($st == 0){
			\app\commons\Wxvideo::delisting($id);
		}else{
			\app\commons\Wxvideo::listing($id);
		}
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	//商品删除
	public function del(){
		$id = input('post.id/d');
		$rs = Db::name('shop_product')->where(['aid'=>aid,'bid'=>bid,'id'=>$id])->delete();
		if($rs){
			Db::name('shop_guige')->where(['aid'=>aid,'proid'=>$id])->delete();
			\app\commons\Wxvideo::deleteproduct($id);
		}
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}

    //保存商品库存
    public function savestock(){
	    }
}