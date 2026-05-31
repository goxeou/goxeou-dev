<?php


namespace app\controllers;
use think\facade\Db;

//todo 每日库存

/**
 * Class ApiRestaurant
 * @package app\controller
 * @deprecated 废弃
 */
class ApiRestaurant extends ApiCommon{
	public function getProlist(){
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',1];
		if(input('param.bid')){
			$where[] = ['bid','=',input('param.bid/d')];
		}else{
			$business_sysset = Db::name('business_sysset')->where('aid',aid)->find();
			if(!$business_sysset || $business_sysset['product_isshow']==0){
				$where[] = ['bid','=',0];
			}
		}
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order').',sort,id desc';
		}else{
			$order = 'sort desc,id desc';
		}
		//分类
		if(input('param.cid')){
//			$cid = input('post.cid') ? input('post.cid/d') : input('param.cid/d');
//			//子分类
//			$clist = db('shop_category')->where(['aid'=>aid,'pid'=>$cid])->column('id');
//			if($clist){
//				$clist2 = db('shop_category')->where(['aid'=>aid,'pid'=>['in',$clist]])->column('id');
//				$cCate = array_merge($clist, $clist2, [$cid]);
//				if($cCate){
//					$whereCid = [];
//					foreach($cCate as $k => $c2){
//						$whereCid[] = "find_in_set({$c2},cid)";
//					}
//                    $where[] = Db::raw(implode(' or ',$whereCid));
//				}
//			} else {
//                $where[] = Db::raw("find_in_set(".$cid.",cid)");
//            }
		}
		if(input('param.gid')) $where[] = Db::raw("find_in_set(".intval(input('param.gid')).",gid)");
		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}

		//优惠券可用商品列表
		$cpid = input('param.cpid/d');
		if($cpid > 0){
			$coupon = Db::name('coupon')->where('id',$cpid)->find();
			$where[] = ['bid','=',$coupon['bid']];
			if($coupon['fwtype']==1){ //指定类目
				$where[] = ['cid','in',$coupon['categoryids']];
			}
			if($coupon['fwtype']==2){ //指定商品
				$where[] = ['id','in',$coupon['productids']];
			}
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('restaurant_product')->field("id,pic,name,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint")->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		if(!$datalist) $datalist = [];
		$datalist = $this->formatprolist($datalist);
		return $this->json(['status'=>1,'data'=>$datalist]);
	}

	public function prolist(){
		//分类
		if(input('param.cid')){
			$clist = Db::name('shop_category')->where('aid',aid)->where('pid',input('param.cid/d'))->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}else{
			$clist = Db::name('shop_category')->where('aid',aid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}
		//分组
		$glist = Db::name('shop_group')->where('aid',aid)->where('status',1)->select()->toArray();
		if(!$glist) $glist = [];
		return $this->json(['clist'=>$clist,'glist'=>$glist]);
	}

	//优惠券可用商品
	public function prolist2(){
		$cpid = input('param.cpid/d');
		$coupon = Db::name('coupon')->where('id',$cpid)->find();
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',1];
		$where[] = ['bid','=',0];
		if($coupon['fwtype']==1){ //指定类目
			$where[] = ['cid','in',$coupon['categoryids']];
		}
		if($coupon['fwtype']==2){ //指定商品
			$where[] = ['id','in',$coupon['productids']];
		}
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order').',sort,id desc';
		}else{
			$order = 'sort desc,id desc';
		}
		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('shop_product')->field("id,pic,name,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint,fuwupoint")->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		if(!$datalist) $datalist = array();
		$datalist = $this->formatprolist($datalist);
		return $this->json(['status'=>1,'data'=>$datalist]);
	}

	//分类商品
	public function classify(){
		$clist = Db::name('shop_category')->where('aid',aid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
		foreach($clist as $k=>$v){
			$rs = Db::name('shop_category')->where('aid',aid)->where('pid',$v['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$rs) $rs = [];
			$clist[$k]['child'] = $rs;
		}
		return $this->json(['status'=>1,'data'=>$clist]);
	}

	//获取子分类
	public function getdownclist(){
		$pid = input('param.id/d');
		$clist = Db::name('shop_category')->where('aid',aid)->where('pid',$pid)->where('status',1)->order('sort desc,id')->select()->toArray();
		if(!$clist) $clist = [];
		return $this->json(['status'=>1,'data'=>$clist]);
	}

	//分类商品样式二
	public function classify2(){
		$clist = Db::name('shop_category')->where('aid',aid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
		foreach($clist as $k=>$v){
			$child = Db::name('shop_category')->where('aid',aid)->where('pid',$v['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$child) $child = [];
			foreach($child as $k2=>$v2){
				$child2 = Db::name('shop_category')->where('aid',aid)->where('pid',$v2['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
				$child[$k2]['child'] = $child2;
			}
			$clist[$k]['child'] = $child;
		}
		return $this->json(['status'=>1,'data'=>$clist]);
	}

	//一级分类
	public function category1(){
		$list = Db::name('shop_category')->where('aid',aid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
		return $this->json(['status'=>1,'data'=>$list]);
	}

	//二级分类
	public function category(){
		$cid = input('param.cid/d');
		if($cid){
			$rs = Db::name('shop_category')->where('aid',aid)->where('pid',$cid)->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$rs) $rs = array();
			$list = $rs;
		}else{
			$list = Db::name('shop_category')->where('aid',aid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$list) $list = array();
			foreach($list as $k=>$v){
				$rs = Db::name('shop_category')->where('aid',aid)->where('pid',$v['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
				if(!$rs) $rs = array();
				$list[$k]['child'] = $rs;
			}
		}
		return $this->json(['status'=>1,'data'=>$list]);
	}

	//三级分类
	public function category3(){
		$list = Db::name('shop_category')->where('aid',aid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
		$rdata = [];
		$rdata['data'] = $list;
		return $this->json($rdata);
	}

	//获取二三分类
	public function getdownclist3(){
		$pid = input('param.id/d');
		$clist = Db::name('shop_category')->where('aid',aid)->where('pid',$pid)->where('status',1)->order('sort desc,id')->select()->toArray();
		if(!$clist) $clist = [];
		foreach($clist as $k=>$v){
			$rs = Db::name('shop_category')->where('aid',aid)->where('pid',$v['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$rs) $rs = array();
			$clist[$k]['child'] = $rs;
		}
		return $this->json(['status'=>1,'data'=>$clist]);
	}
	
	//商品
	public function product(){
		$proid = input('param.id/d');
		$product = Db::name('restaurant_product')->where('id',$proid)->where('aid',aid)->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'菜品不存在']);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'菜品未上架']);
		if(!$product['pics']) $product['pics'] = $product['pic'];
		$product['pics'] = explode(',',$product['pics']);
		$product = $this->formatproduct($product);

		//是否收藏
		$rs = Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','restaurant_product')->find();
		if($rs){
			$isfavorite = true;
		}else{
			$isfavorite = false;
		}
		//获取评论
		$commentlist = Db::name('restaurant_comment')->where('aid',aid)->where('product_id',$proid)->where('status',1)->order('id desc')->limit(10)->select()->toArray();
		if(!$commentlist) $commentlist = [];
		foreach($commentlist as $k=>$pl){
			$commentlist[$k]['create_time'] = date('Y-m-d H:i',$pl['create_time']);
			if($commentlist[$k]['content_pic']) $commentlist[$k]['content_pic'] = explode(',',$commentlist[$k]['content_pic']);
		}
		$commentcount = Db::name('restaurant_comment')->where('aid',aid)->where('product_id',$proid)->where('status',1)->count();
		//添加浏览历史
		if(mid){
			$rs = Db::name('member_history')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','restaurant_product')->find();
			if($rs){
				Db::name('member_history')->where('id',$rs['id'])->update(['createtime'=>time()]);
			}else{
				Db::name('member_history')->insert(['aid'=>aid,'mid'=>mid,'proid'=>$proid,'type'=>'restaurant_product','createtime'=>time()]);
			}
		}

		//todo
		$sysset = Db::name('admin_set')->where('aid',aid)->field('name,logo,desc,fxjiesuantype,tel,kfurl,gzts,ddbb')->find();
//        $shopset = Db::name('shop_sysset')->where('aid',aid)->field('showjd,comment,showcommission')->find();
        if($product['bid']!=0){
            $restaurantSet = Db::name('restaurant_business_set')->where('aid',aid)->where('bid',$product['bid'])->field('comment_check,comment,show_commission')->find();
        } else {
            $restaurantSet = Db::name('restaurant_admin_set')->where('aid',aid)->field('comment_check,comment,show_commission')->find();
        }

		
		//预计佣金
		$commission = 0;
		if($this->member && $restaurantSet['show_commission']==1 && $product['commissionset']!=-1){
			$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
			if($userlevel['can_agent']!=0){
				if($product['commissionset']==1){//按比例
					$commissiondata = json_decode($product['commissiondata1'],true);
					if($commissiondata){
						$commission = $commissiondata[$userlevel['id']]['commission1'] * ($product['sell_price'] - ($sysset['fxjiesuantype']==1 ? $product['cost_price'] : 0)) * 0.01;
					}
				}elseif($product['commissionset']==2){//按固定金额
					$commissiondata = json_decode($product['commissiondata2'],true);
					if($commissiondata){
						$commission = $commissiondata[$userlevel['id']]['commission1'];
					}
				}else{
					$commission = $userlevel['commission1'] * ($product['sell_price'] - ($sysset['fxjiesuantype']==1 ? $product['cost_price'] : 0)) * 0.01;
				}
			}
		}
		$product['commission'] = round($commission*100)/100;
		unset($product['cost_price']);

		if($product['bid']!=0){
			$business = Db::name('business')->where('aid',aid)->where('id',$product['bid'])->field('id,name,logo,desc,tel,address,sales,kfurl')->find();
		}else{
			$business = $sysset;
		}
		if($product['detail_text'] || $product['detail_pics']){
			$detail = json_decode($product['detail'],true);
			if(!$detail) $detail = [];
			if($product['detail_pics']){
				$pics = explode(',',$product['detail_pics']);
				$picdata = [];
				foreach($pics as $k=>$pic){
					$picdata[] = ["id"=>"P000000000000".$k,"imgurl"=>$pic,"hrefurl"=>"","option"=>"0"];
				}
				$detail_pics = ["id"=>"M1603905871107986852","temp"=>"picture","params"=>["bgcolor"=>"#FFFFFF","margin_x"=>"0","margin_y"=>"0","padding_x"=>"0","padding_y"=>"0"],"data"=>$picdata,"other"=>"","content"=>""];
				array_unshift($detail,$detail_pics);
			}
			if($product['detail_text']){
				$detail_text = ["id"=>"M1603905871107986851","temp"=>"text","params"=>["content"=>$product['detail_text'],"bgcolor"=>"#ffffff","fontsize"=>"14","lineheight"=>"20","align"=>"left","color"=>"#000","margin_x"=>"0","margin_y"=>"0","padding_x"=>"8","padding_y"=>"8"],"data"=>"","other"=>"","content"=>""];
				array_unshift($detail,$detail_text);
			}
			$product['detail'] = jsonEncode($detail);
		}
		$product['detail'] = \app\commons\System::initpagecontent($product['detail'],aid,mid,platform);
        $product['comment_starnum'] = floor($product['comment_score']);
		
		//关注提示
		if(platform == 'mp'){
			$sysset['gzts'] = explode(',',$sysset['gzts']);
			if(in_array('2',$sysset['gzts']) && $this->member['subscribe']==0){
				$appinfo = \app\commons\System::appinfo(aid,'mp');
				$sysset['qrcode'] = $appinfo['qrcode'];
				$sysset['gzhname'] = $appinfo['nickname'];
			}
		}
		//订单播报
		$oglist = [];
		$sysset['ddbb'] = explode(',',$sysset['ddbb']);
		if(in_array('2',$sysset['ddbb'])){
			$oglist = Db::name('restaurant_shop_order_goods')
				->field('mid,name,create_time,product_id')
				->where('aid',aid)
				->where('product_id',$product['id'])
				->where('status','in','0,1,2,3')
				->where('create_time','>',time()-86400*10)
				->order('create_time desc')->limit(10)->select()->toArray();
			if(!$oglist) $oglist = [];
			foreach($oglist as $k=>$og){
				$ogmember = Db::name('member')->where('id',$og['mid'])->find();
				if(!$ogmember){
					unset($oglist[$k]);
					continue;
				}else{
					$oglist[$k]['nickname'] = $ogmember['nickname'];
					$oglist[$k]['headimg'] = $ogmember['headimg'];
				}
				if(time() - $og['create_time'] < 60*5){
					$oglist[$k]['showtime'] = '刚刚';
				}elseif(date('Ymd')==date('Ymd',$og['create_time'])){
					if($og['create_time'] + 3600 > time()){
						$oglist[$k]['showtime'] = floor((time()-$og['create_time'])/60).'分钟前';
					}else{
						$oglist[$k]['showtime'] = floor((time()-$og['create_time'])/3600).'小时前';
					}
				}elseif(time()-$og['create_time']<86400){
					$oglist[$k]['showtime'] = '昨天';
				}elseif(time()-$og['create_time']<2*86400){
					$oglist[$k]['showtime'] = '前天';
				}else{
					$oglist[$k]['showtime'] = '三天前';
				}
			}
		}

		//促销活动 todo
		/*$cuxiaolist = Db::name('cuxiao')
			->where('aid',aid)
			->where('bid',$product['bid'])
			->where('starttime','<',time())
			->where('endtime','>',time())
			->order('sort desc')->select()->toArray();
		$newcxlist = [];
		foreach($cuxiaolist as $k=>$v){
			$gettj = explode(',',$v['gettj']);
			if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
				continue;
			}
			if($v['fwtype']==2){//指定菜品可用
				$productids = explode(',',$v['productids']);
				if(!in_array($product['id'],$productids)){
					continue;
				}
			}
			if($v['fwtype']==1){//指定类目可用
				$categoryids = explode(',',$v['categoryids']);
				$cids = [];
				$cids[] = $product['cid'];
				$clist = Db::name('shop_category')->where('id','in',$cids)->select()->toArray();
				foreach($clist as $kc=>$vc){
					if($vc['pid']){
						$cids[] = $vc['pid'];
						$cate2 = Db::name('shop_category')->where('id',$vc['pid'])->find();
						if($cate2 && $cate2['pid']){
							$cids[] = $cate2['pid'];
						}
					}
				}
				if(!array_intersect($cids,$categoryids)){
					continue;
				}
			}
			$newcxlist[] = $v;
		}*/
        $newcxlist = [];
		//优惠券 todo
		/*$couponlist = Db::name('coupon')->where('aid',aid)->where('bid',$product['bid'])->where('tolist',1)->where('type','in','1,4')->where("unix_timestamp(starttime)<=".time()." and unix_timestamp(endtime)>=".time())->order('sort desc')->select()->toArray();
		$newcplist = [];
		foreach($couponlist as $k=>$v){
			$gettj = explode(',',$v['gettj']);
			if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
				continue;
			}
			if($v['fwtype']==2){//指定菜品可用
				$productids = explode(',',$v['productids']);
				if(!in_array($product['id'],$productids)){
					continue;
				}
			}
			if($v['fwtype']==1){//指定类目可用
				$categoryids = explode(',',$v['categoryids']);
				$cids = [];
				$cids[] = $product['cid'];
				$clist = Db::name('shop_category')->where('id','in',$cids)->select()->toArray();
				foreach($clist as $kc=>$vc){
					if($vc['pid']){
						$cids[] = $vc['pid'];
						$cate2 = Db::name('shop_category')->where('id',$vc['pid'])->find();
						if($cate2 && $cate2['pid']){
							$cids[] = $cate2['pid'];
						}
					}
				}
				if(!array_intersect($cids,$categoryids)){
					continue;
				}
			}
			$haveget = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('couponid',$v['id'])->count();
			$v['haveget'] = $haveget;
			$v['starttime'] = date('m-d H:i',strtotime($v['starttime']));
			$v['endtime'] = date('m-d H:i',strtotime($v['endtime']));
			if($v['yxqtype'] == 1){
				$yxqtime = explode(' ~ ',$v['yxqtime']);
				$v['yxqdate'] = date('Y-m-d',strtotime($yxqtime[1]));
			}else{
				$v['yxqdate'] = date('Y-m-d',time() + 86400 * $v['yxqdate']);
			}
			if($v['bid'] > 0){
				$binfo = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->find();
				$datalist[$k]['bname'] = $binfo['name'];
			}
			$newcplist[] = $v;
		}*/
        $newcplist = [];

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['title'] = $product['name'];
		$rdata['sysset'] = $sysset;
		$rdata['shopset'] = $restaurantSet;
		$rdata['isfavorite'] = $isfavorite;
		$rdata['product'] = $product;
		$rdata['business'] = $business;
		$rdata['commentlist'] = $commentlist;
		$rdata['commentcount'] = $commentcount;
		$rdata['cartnum'] = Db::name('shop_cart')->where('aid',aid)->where('mid',mid)->sum('num');
		$rdata['oglist'] = $oglist;
		$rdata['cuxiaolist'] = $newcxlist;
		$rdata['couponlist'] = $newcplist;
		return $this->json($rdata);
	}

    //获取菜品详情
    public function getProductdetail(){
        $proid = input('param.id/d');
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','=',$proid];
        $product = Db::name('restaurant_product')->field("pic,id,name,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint,guigedata,status")->where($where)->find();
        if(!$product){
            return $this->json(['status'=>0,'msg'=>'菜品不存在']);
        }
        $product = $this->formatproduct($product);
        if($product['status']==0) return $this->json(['status'=>0,'msg'=>'菜品已下架']);
        $gglist = Db::name('restaurant_product_guige')->where('product_id',$product['id'])->select()->toArray();
        $gglist = $this->formatgglist($gglist,$product['bid'],$product['lvprice']);
        $guigelist = array();
        foreach($gglist as $k=>$v){
            $guigelist[$v['ks']] = $v;
        }
        $guigedata = json_decode($product['guigedata'],true);
        $ggselected = [];
        foreach($guigedata as $v) {
            $ggselected[] = 0;
        }
        $ks = implode(',',$ggselected);
        return $this->json(['status'=>1,'product'=>$product,'guigelist'=>$guigelist,'guigedata'=>$guigedata,'ggselected'=>$ggselected,'ks'=>$ks]);
    }

	//菜品评价
	public function commentlist(){
		$proid = input('param.proid/d');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['proid','=',$proid];
		$where[] = ['status','=',1];
		$datalist = Db::name('shop_comment')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$pl){
			$datalist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
			if($datalist[$k]['content_pic']) $datalist[$k]['content_pic'] = explode(',',$datalist[$k]['content_pic']);
		}
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}

	//菜品海报
	function getposter(){

		$post = input('post.');
		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$this->member['id'].'_'.$post['proid'])->where('type','product_'.platform)->find();
		if(true || !$posterdata){
			$product = Db::name('shop_product')->where('id',$post['proid'])->find();
			
			$sysset = Db::name('admin_set')->where('aid',aid)->find();
			
			//if(platform == 'wx' || platform == 'alipay' || platform=='baidu' || platform=='toutiao' || platform=='qq'){
				$page = 'pages/shop/product';
				$scene = 'pid_'.$this->member['id'].'-id_'.$post['proid'];
			//}else{
			//	$page = PRE_URL.aid.'/#/pages/shop/product?id='.$post['proid'].'&pid='.$this->member['id'];
			//	$scene = '';
			//}
			$textReplaceArr = [
				'[昵称]'=>$this->member['nickname'],
				'[姓名]'=>$this->member['realname'],
				'[手机号]'=>$this->member['mobile'],
				'[商城名称]'=>$sysset['name'],
				'[菜品名称]'=>$product['name'],
				'[菜品销售价]'=>$product['sell_price'],
				'[菜品市场价]'=>$product['market_price'],
			];
			$type = 'product_'.platform;
			if(platform=='h5'){
				$type = 'product_mp';
			}
			$poster = $this->_getposter(
				aid,$product['bid'],
				$type,
				$page,
				$scene,
				$this->member['headimg'],
				$textReplaceArr,
				$product['pic']
			);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $this->member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['type'] = $type;
			$posterdata['poster'] = $poster;
			$posterdata['createtime'] = time();
			Db::name('member_poster')->insert($posterdata);
		}
		return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
	}
	//购物车 
	public function cart(){
		//$this->checklogin();
		$gwcdata = [];
		$cartlist = Db::name('shop_cart')->field('id,bid,proid,ggid,num')->where('aid',aid)->where('mid',mid)->order('createtime desc')->select()->toArray();
		if(!$cartlist) $cartlist = [];
		$newcartlist = [];
		foreach($cartlist as $k=>$gwc){
			if($newcartlist[$gwc['bid']]){
				$newcartlist[$gwc['bid']][] = $gwc;
			}else{
				$newcartlist[$gwc['bid']] = [$gwc];
			}
		}
		foreach($newcartlist as $bid=>$gwclist){
			if($bid == 0){
				$business = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,tel')->find();
			}else{
				$business = Db::name('business')->where('aid',aid)->where('id',$bid)->field('id,name,logo,tel')->find();
			}
			$prolist = [];
			foreach($gwclist as $gwc){
				$product = Db::name('shop_product')->where('aid',aid)->where('status',1)->where('id',$gwc['proid'])->find();
				if(!$product){
					Db::name('shop_cart')->where('aid',aid)->where('proid',$gwc['proid'])->delete();continue;
				}
				$guige = Db::name('shop_guige')->where('id',$gwc['ggid'])->find();
				if(!$guige){
					Db::name('shop_cart')->where('aid',aid)->where('ggid',$gwc['ggid'])->delete();continue;
				}
                $guige = $this->formatguige($guige,$product['bid'],$product['lvprice']);
				$prolist[] = ['id'=>$gwc['id'],'checked'=>true,'product'=>$product,'guige'=>$guige,'num'=>$gwc['num']];
			}
			$newcartlist[$bid] = ['bid'=>$bid,'checked'=>true,'business'=>$business,'prolist'=>$prolist];
		}

		//购物车推荐 todo
        $tjdatalist = [];
//        $shopset = Db::name('shop_sysset')->where('aid',aid)->field('gwctj')->find();
//		if($shopset['gwctj']){
//			$tjwhere = [];
//			$tjwhere[] = ['aid','=',aid];
//			$tjwhere[] = ['status','=',1];
//			$business_sysset = Db::name('business_sysset')->where('aid',aid)->find();
//			if(!$business_sysset || $business_sysset['product_isshow']==0){
//				$tjwhere[] = ['bid','=',0];
//			}
//			$tjdatalist = Db::name('shop_product')->field("id,pic,name,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint,fuwupoint")->where($tjwhere)->limit(12)->order(Db::raw('rand()'))->select()->toArray();
//			if(!$tjdatalist) $tjdatalist = array();
//			$tjdatalist = $this->formatprolist($tjdatalist);
//		}
		$rdata = [];
		$rdata['cartlist'] = array_values($newcartlist);
		$rdata['tjdatalist'] = $tjdatalist;
		return $this->json($rdata);
	}

	public function addcart(){
		$this->checklogin();
		$post = input('post.');
		$oldnum = 0;
		$num = intval($post['num']);
		
		$product = Db::name('restaurant_product')->where('aid',aid)->where('status',1)->where('id',$post['proid'])->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
		if(!$post['ggid']){
			if($num > 0){
				$post['ggid'] = Db::name('restaurant_product_guige')->where('product_id',$post['proid'])->value('id');
			}else{
				$post['ggid'] = Db::name('shop_cart')->where('aid',aid)->where('mid',mid)->where('proid',$post['proid'])->order('id desc')->value('ggid');
			}
		}

		$gwc = Db::name('shop_cart')->where('aid',aid)->where('mid',mid)->where('proid',$post['proid'])->where('ggid',$post['ggid'])->find();
		if($gwc) $oldnum = $gwc['num'];

		if($oldnum + $num <=0){
			Db::name('shop_cart')->where('aid',aid)->where('mid',mid)->where('proid',$post['proid'])->where('ggid',$post['ggid'])->delete();
			$cartnum = Db::name('shop_cart')->where('aid',aid)->where('mid',mid)->sum('num');
			return $this->json(['status'=>1,'msg'=>'移除成功','cartnum'=>$cartnum]);
		}
		if($gwc){
			Db::name('shop_cart')->where('aid',aid)->where('mid',mid)->where('proid',$post['proid'])->where('ggid',$post['ggid'])->inc('num',$num)->update();
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['bid'] = $product['bid'];
			$data['mid'] = mid;
			$data['ggid'] = $post['ggid'];
			$data['createtime'] = time();
			$data['proid'] = $post['proid'];
			$data['num'] = $num;
			Db::name('shop_cart')->insert($data);
		}
		$cartnum = Db::name('shop_cart')->where('aid',aid)->where('mid',mid)->sum('num');
		return $this->json(['status'=>1,'msg'=>'加入购物车成功','cartnum'=>$cartnum]);
	}

	public function cartChangenum(){
		$this->checklogin();
		$id = input('post.id/d');
		$num = input('post.num/d');
		if($num < 1) $num = 1;
		Db::name('shop_cart')->where('id',$id)->where('mid',mid)->update(['num'=>$num]);
		return $this->json(['status'=>1,'msg'=>'修改成功']);
	}

	public function cartdelete(){
		$this->checklogin();
		$id = input('post.id/d');
		if(!$id){
			$bid = input('post.bid/d');
			Db::name('shop_cart')->where('bid',$bid)->where('mid',mid)->delete();
			return $this->json(['status'=>1,'msg'=>'删除成功']);
		}
		Db::name('shop_cart')->where('id',$id)->where('mid',mid)->delete();
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}

	public function cartclear(){
		$bid = input('param.bid');
		if(!$bid) $bid = 0;
		Db::name('shop_cart')->where('aid',aid)->where('bid',$bid)->where('mid',mid)->delete();
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}

	//快速购买页
	public function fastbuy(){
		$bid = input('param.bid');
		if(!$bid) $bid = 0;
		$cid = input('param.cid');
		if(!$cid) $cid = 0;
		$clist = Db::name('shop_category')->where('aid',aid)->where('pid',$cid)->where('status',1)->order('sort desc,id')->select()->toArray();
		foreach($clist as $k=>$v){
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',$bid];
			$where[] = ['status','=',1];
			//子分类 
			$childcid = db('shop_category')->where(['aid'=>aid,'pid'=>$v['id']])->column('id');
			if($childcid){
				$child2cid = db('shop_category')->where(['aid'=>aid,'pid'=>['in',$childcid]])->column('id');
				$cCate = array_merge($childcid, $child2cid, [$v['id']]);
				if($cCate){
					$whereCid = [];
					foreach($cCate as $c2){
						$whereCid[] = "find_in_set({$c2},cid)";
					}
					$where[] = Db::raw(implode(' or ',$whereCid));
				}
			} else {
				$where[] = Db::raw("find_in_set(".$v['id'].",cid)");
			}
			//是否可见
			$where2 = "find_in_set('-1',showtj)";
			if($this->member){
				$where2 .= " or find_in_set('".$this->member['levelid']."',showtj)";
				if($this->member['subscribe']==1){
					$where2 .= " or find_in_set('0',showtj)";
				}
			}
			$where[] = Db::raw($where2);

			$prolist = Db::name('shop_product')->field("pic,id,name,stock,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint,fuwupoint,guigedata")->where($where)->order('sort desc,id desc')->select()->toArray();
			if(!$prolist) $prolist = [];
			$prolist = $this->formatprolist($prolist);
			if(!$prolist){
				unset($clist[$k]);
			}else{
				$clist[$k]['prolist'] = $prolist;
			}
		}
		$clist = array_values($clist);

		$list = Db::name('shop_cart')->where('aid',aid)->where('bid',$bid)->where('mid',mid)->order('createtime desc')->select()->toArray();
		$total = 0;
		$totalprice = 0;
		foreach($list as $k=>$v){
			$product = Db::name('shop_product')->field('pic,id,bid,name,stock,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint,fuwupoint,guigedata')->where('id',$v['proid'])->find();
			if(!$product){
				unset($list[$k]);
				Db::name('shop_cart')->where('id',$v['id'])->delete();
				continue;
			}
			$product = $this->formatproduct($product);
			$guige = Db::name('shop_guige')->where('id',$v['ggid'])->find();
			if(!$guige){
				unset($list[$k]);
				Db::name('shop_cart')->where('id',$v['id'])->delete();
				continue;
			}
            $guige = $this->formatguige($guige,$product['bid'],$product['lvprice']);
			$list[$k]['product'] = $product;
			$list[$k]['guige'] = $guige;
			$total += $v['num'];
			$totalprice += $guige['sell_price'] * $v['num'];
		}
		$totalprice = number_format($totalprice,2,'.','');
		$cartList = ['list'=>$list,'total'=>$total,'totalprice'=>$totalprice];
		$numtotal = [];
		foreach($clist as $i=>$v){
			foreach($v['prolist'] as $j=>$pro){
				$numtotal[$pro['id']] = 0;
			}
		}
		foreach($cartList['list'] as $i=>$v){
			$numtotal[$v['proid']] += $v['num'];
		}

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['data'] = $clist;
		$rdata['cartList'] = $cartList;
		$rdata['numtotal'] = $numtotal;
		return $this->json($rdata);
	}

    //点菜快速购买页
    public function diancai(){
        $bid = input('param.bid');
        if(!$bid) $bid = 0;
        $cid = input('param.cid');
        if(!$cid) $cid = 0;
        $tableId = input('param.tableId');
        if (empty($tableId)) {
            return $this->json(['status'=>0,'msg'=>'请扫桌码点餐']);
        }
        cache($this->sessionid.'_tableId', $tableId);
        $clist = Db::name('restaurant_product_category')->where('aid',aid)->where('pid',$cid)->where('status',1)->order('sort desc,id')->select()->toArray();

        foreach($clist as $k=>$v){
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',$bid];
            $where[] = ['status','=',1];
            //子分类
            $where[] = Db::raw("find_in_set(".$v['id'].",cid)");

                $prolist = Db::name('restaurant_product')->field("pic,id,name,stock,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint,guigedata")->where($where)->order('sort desc,id desc')->select()->toArray();
            if(!$prolist) $prolist = [];
            $prolist = $this->formatprolist($prolist);
            if(!$prolist){
                unset($clist[$k]);
            }else{
                $clist[$k]['prolist'] = $prolist;
            }
        }
        $clist = array_values($clist);

        $list = Db::name('shop_cart')->where('aid',aid)->where('bid',$bid)->where('mid',mid)->order('createtime desc')->select()->toArray();
        $total = 0;
        $totalprice = 0;
        foreach($list as $k=>$v){
            $product = Db::name('restaurant_product')->field('pic,id,bid,name,stock,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint,guigedata')->where('id',$v['proid'])->find();
            if(!$product){
                unset($list[$k]);
                Db::name('shop_cart')->where('id',$v['id'])->delete();
                continue;
            }
            $product = $this->formatproduct($product);
            $guige = Db::name('restaurant_product_guige')->where('id',$v['ggid'])->find();
            if(!$guige){
                unset($list[$k]);
                Db::name('shop_cart')->where('id',$v['id'])->delete();
                continue;
            }
            $guige = $this->formatguige($guige,$product['bid'],$product['lvprice']);
            $list[$k]['product'] = $product;
            $list[$k]['guige'] = $guige;
            $total += $v['num'];
            $totalprice += $guige['sell_price'] * $v['num'];
        }
        $totalprice = number_format($totalprice,2,'.','');
        $cartList = ['list'=>$list,'total'=>$total,'totalprice'=>$totalprice];
        $numtotal = [];
        foreach($clist as $i=>$v){
            foreach($v['prolist'] as $j=>$pro){
                $numtotal[$pro['id']] = 0;
            }
        }
        foreach($cartList['list'] as $i=>$v){
            $numtotal[$v['proid']] += $v['num'];
        }

        $rdata = [];
        $rdata['status'] = 1;
        $rdata['data'] = $clist;
        $rdata['cartList'] = $cartList;
        $rdata['numtotal'] = $numtotal;
        return $this->json($rdata);
    }

	//获取促销信息
	public function getcuxiaoinfo(){
		$id = input('post.id/d');
		$info = Db::name('cuxiao')->where('id',$id)->where('aid',aid)->find();
		if(!$info){
			return $this->json(['status'=>0,'msg'=>'获取失败']);
		}
		$proinfo = false;
		$gginfo = false;
		if(($info['type'] == 2 || $info['type'] == 3) && $info['proid']){
			$proinfo = Db::name('shop_product')->field('id,name,pic,sell_price')->where('aid',aid)->where('id',$info['proid'])->find();
			$gginfo = Db::name('shop_guige')->where('aid',aid)->where('id',$info['ggid'])->find();
		}
		return $this->json(['status'=>1,'info'=>$info,'product'=>$proinfo,'guige'=>$gginfo]);
	}
	//订单提交页
	public function buy(){
		$this->checklogin();
		$prodata = explode('-',input('param.prodata'));

		if(empty(cache($this->sessionid.'_tableId'))) {
            return $this->json(['status'=>0,'msg'=>'请扫桌码点餐']);
        }
		
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		$adminset = Db::name('admin_set')->where('aid',aid)->find();
		$userinfo = [];
		$userinfo['discount'] = $userlevel['discount'];
		$userinfo['score'] = $this->member['score'];
		$userinfo['score2money'] = $adminset['score2money'];
		$userinfo['scoredk_money'] = round($userinfo['score'] * $userinfo['score2money'],2);
		$userinfo['scoredkmaxpercent'] = $adminset['scoredkmaxpercent'];
		$userinfo['scoremaxtype'] = 0; //0最大百分比 1最大抵扣金额
		$userinfo['realname'] = $this->member['realname'];
		$userinfo['tel'] = $this->member['tel'];
		
		$scoredkmaxmoney = 0;
		$allbuydata = [];
		foreach($prodata as $key=>$gwc){
			list($proid,$ggid,$num) = explode(',',$gwc);
			$product = Db::name('restaurant_product')->field("id,aid,bid,cid,pic,name,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint,freighttype,freightdata,limit_per,scored_set,scored_val")->where('aid',aid)->where('status',1)->where('id',$proid)->find();
			if(!$product){
				Db::name('shop_cart')->where('aid',aid)->where('proid',$proid)->delete();
				return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
			}
			$guige = Db::name('restaurant_product_guige')->where('id',$ggid)->find();
			if(!$guige){
				Db::name('shop_cart')->where('aid',aid)->where('ggid',$ggid)->delete();
				return $this->json(['status'=>0,'msg'=>'产品该规格不存在或已下架']);
			}
			if($guige['stock'] < $num){
				return $this->json(['status'=>0,'msg'=>'库存不足']);
			}
			if($product['limit_per'] > 0){
				$buynum = $num + Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('mid',mid)->where('product_id',$product['id'])->where('status','in','0,1,2,3')->sum('num');
				if($buynum > $product['limit_per']){
					return $this->json(['status'=>0,'msg'=>'每人限购'.$product['limit_per'].'件']);
				}
			}
            $guige = $this->formatguige($guige,$product['bid'],$product['lvprice']);

			if($product['scored_set']==0){
				if($userinfo['scoredkmaxpercent'] == 0){
					$userinfo['scoremaxtype'] = 1;
					$scoredkmaxmoney += 0;
				}else{
					if($userinfo['scoredkmaxpercent'] > 0 && $userinfo['scoredkmaxpercent']<100){
						$scoredkmaxmoney += $userinfo['scoredkmaxpercent'] * 0.01 * $guige['sell_price'] * $num;
					}else{
						$scoredkmaxmoney += $guige['sell_price'] * $num;
					}
				}
			}elseif($product['scored_set']==1){
				$userinfo['scoremaxtype'] = 1;
				$scoredkmaxmoney += $product['scored_val'] * 0.01 * $guige['sell_price'] * $num;
			}elseif($product['scored_set']==2){
				$userinfo['scoremaxtype'] = 1;
				$scoredkmaxmoney += $product['scored_val'] * $num;
			}else{
				$userinfo['scoremaxtype'] = 1;
				$scoredkmaxmoney += 0;
			}

			if(!$allbuydata[$product['bid']]) $allbuydata[$product['bid']] = [];
			if(!$allbuydata[$product['bid']]['prodata']) $allbuydata[$product['bid']]['prodata'] = [];
			$allbuydata[$product['bid']]['prodata'][] = ['product'=>$product,'guige'=>$guige,'num'=>$num];
		}
		$userinfo['scoredkmaxmoney'] = round($scoredkmaxmoney,2);

		$havetongcheng = 0;
		/*foreach($allbuydata as $bid=>$buydata){
			$freightList = \app\models\Freight::getList([['status','=',1],['aid','=',aid],['bid','=',$bid]]);
			$fids = [];
			foreach($freightList as $v){
				$fids[] = $v['id'];
			}
			foreach($buydata['prodata'] as $prodata){
				if($prodata['product']['freighttype']==0){
					$fids = array_intersect($fids,explode(',',$prodata['product']['freightdata']));
				}else{
					$thisfreightList = \app\models\Freight::getList([['status','=',1],['aid','=',aid],['bid','=',$bid]]);
					$thisfids = [];
					foreach($thisfreightList as $v){
						$thisfids[] = $v['id'];
					}
					$fids = array_intersect($fids,$thisfids);
				}
			}
			if(!$fids){
				if(count($buydata['prodata'])>1){
					return $this->json(['status'=>0,'msg'=>'所选择菜品配送方式不同，请分别下单']);
				}else{
					return $this->json(['status'=>0,'msg'=>'获取配送方式失败']);
				}
			}
			$freightList = \app\models\Freight::getList([['status','=',1],['aid','=',aid],['bid','=',$bid],['id','in',$fids]]);
			foreach($freightList as $k=>$v){
				if($v['pstype']==2){ //同城配送
					$havetongcheng = 1;
				}
			}
			$allbuydata[$bid]['freightList'] = $freightList;
		}*/
		$address = [];
		$needLocation = 0;
		$allproduct_price = 0;
		foreach($allbuydata as $bid=>$buydata){
			if($bid!=0){
				$business = Db::name('business')->where('id',$bid)->field('id,aid,cid,name,logo,tel,address,sales,longitude,latitude')->find();
			}else{
				$business = ['id'=>0,'name'=>$this->sysset['name'],'pic'=>$this->sysset['logo']];
			}
			
			$product_price = 0;
			$needzkproduct_price = 0;
			$totalweight = 0;
			$totalnum = 0;
			$prodataArr = [];
			$proids = [];
			$cids = [];
			foreach($buydata['prodata'] as $prodata){
				$product_price += $prodata['guige']['sell_price'] * $prodata['num'];
				if($prodata['product']['lvprice']==0){ //未开启会员价
					$needzkproduct_price += $prodata['guige']['sell_price'] * $prodata['num'];
				}
				$totalweight += $prodata['guige']['weight'] * $prodata['num'];
				$totalnum += $prodata['num'];
				$prodataArr[] = $prodata['product']['id'].','.$prodata['guige']['id'].','.$prodata['num'];
				$proids[] = $prodata['product']['id'];
				$cids[] = $prodata['product']['cid'];
			}
			$prodatastr = implode('-',$prodataArr);
			
//			$rs = \app\models\Freight::formatFreightList($buydata['freightList'],$address,$product_price,$totalnum,$totalweight);
//
//			$freightList = $rs['freightList'];
//			$freightArr = $rs['freightArr'];
//			if($rs['needLocation']==1) $needLocation = 1;
			
			$leveldk_money = 0;
			if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
				$leveldk_money = $needzkproduct_price * (1 - $userlevel['discount'] * 0.1);
			}
			$leveldk_money = round($leveldk_money,2);
			$price = $product_price - $leveldk_money;
			
			//满减活动
			/*$mjset = Db::name('manjian_set')->where('aid',aid)->find();
			if($mjset && $mjset['status']==1){
				$mjdata = json_decode($mjset['mjdata'],true);
			}else{
				$mjdata = array();
			}*/
			$manjian_money = 0;
			$moneyduan = 0;
			/*if($mjdata){
				foreach($mjdata as $give){
					if(($product_price - $leveldk_money)*1 >= $give['money']*1 && $give['money']*1 > $moneyduan){
						$moneyduan = $give['money']*1;
						$manjian_money = $give['jian']*1;
					}
				}
			}*/
			if($manjian_money > 0){
				$allbuydata[$bid]['manjian_money'] = round($manjian_money,2);
			}else{
				$allbuydata[$bid]['manjian_money'] = 0;
			}

			/*$couponList = Db::name('coupon_record')
				->where("bid=-1 or bid=".$bid)->where('aid',aid)->where('mid',mid)->where('type','in','1,4')->where('status',0)->where('minprice','<=',$price - $manjian_money)->where('starttime','<=',time())->where('endtime','>',time())
				->order('id desc')->select()->toArray();
			if(!$couponList)*/ $couponList = [];
			/*foreach($couponList as $k=>$v){
				$couponList[$k]['starttime'] = date('m-d H:i',$v['starttime']);
				$couponList[$k]['endtime'] = date('m-d H:i',$v['endtime']);
				$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$v['couponid'])->find();
				if($couponinfo['fwtype']==2){//指定菜品可用
					$productids = explode(',',$couponinfo['productids']);
					if(!array_intersect($proids,$productids)){
						unset($couponList[$k]);
					}
				}
				if($couponinfo['fwtype']==1){//指定类目可用
					$categoryids = explode(',',$couponinfo['categoryids']);
					$clist = Db::name('shop_category')->where('id','in',$cids)->select()->toArray();
					foreach($clist as $kc=>$vc){
						if($vc['pid']){
							$cids[] = $vc['pid'];
							$cate2 = Db::name('shop_category')->where('id',$vc['pid'])->find();
							if($cate2 && $cate2['pid']){
								$cids[] = $cate2['pid'];
							}
						}
					}
					if(!array_intersect($cids,$categoryids)){
						unset($couponList[$k]);
					}
				}
			}*/

			//促销活动
            $newcxlist = [];
			/*$cuxiaolist = Db::name('cuxiao')->where('aid',aid)->where('bid',$product['bid'])->where('type','<>',0)->where('minprice','<=',$price - $manjian_money)->where('starttime','<',time())->where('endtime','>',time())->order('sort desc')->select()->toArray();
			foreach($cuxiaolist as $k=>$v){
				$gettj = explode(',',$v['gettj']);
				if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
					continue;
				}
				if($v['fwtype']==2){//指定菜品可用
					$productids = explode(',',$v['productids']);
					if(!in_array($product['id'],$productids)){
						continue;
					}
				}
				if($v['fwtype']==1){//指定类目可用
					$categoryids = explode(',',$v['categoryids']);
					$cids = [];
					$cids[] = $product['cid'];
					$clist = Db::name('shop_category')->where('id','in',$cids)->select()->toArray();
					foreach($clist as $kc=>$vc){
						if($vc['pid']){
							$cids[] = $vc['pid'];
							$cate2 = Db::name('shop_category')->where('id',$vc['pid'])->find();
							if($cate2 && $cate2['pid']){
								$cids[] = $cate2['pid'];
							}
						}
					}
					if(!array_intersect($cids,$categoryids)){
						continue;
					}
				}
				$newcxlist[] = $v;
			}*/

			$allbuydata[$bid]['bid'] = $bid;
			$allbuydata[$bid]['business'] = $business;
			$allbuydata[$bid]['prodatastr'] = $prodatastr;
			$allbuydata[$bid]['couponList'] = $couponList;
			$allbuydata[$bid]['couponCount'] = count($couponList);
			$allbuydata[$bid]['freightList'] = [];
			$allbuydata[$bid]['freightArr'] = [];
			$allbuydata[$bid]['product_price'] = round($product_price,2);
			$allbuydata[$bid]['leveldk_money'] = $leveldk_money;
			$allbuydata[$bid]['coupon_money'] = 0;
			$allbuydata[$bid]['coupontype'] = 1;
			$allbuydata[$bid]['couponrid'] = 0;
			$allbuydata[$bid]['freightkey'] = 0;
			$allbuydata[$bid]['pstimetext'] = '';
			$allbuydata[$bid]['freight_time'] = '';
			$allbuydata[$bid]['storeid'] = 0;
			$allbuydata[$bid]['storename'] = '';
			$allbuydata[$bid]['message'] = '';
			$allbuydata[$bid]['field1'] = '';
			$allbuydata[$bid]['field2'] = '';
			$allbuydata[$bid]['field3'] = '';
			$allbuydata[$bid]['field4'] = '';
			$allbuydata[$bid]['field5'] = '';
			$allbuydata[$bid]['cuxiaolist'] = $newcxlist;
			$allbuydata[$bid]['cuxiaoCount'] = count($newcxlist);
			$allbuydata[$bid]['cuxiao_money'] = 0;
			$allbuydata[$bid]['cuxiaotype'] = 0;
			$allbuydata[$bid]['cuxiaoid'] = 0;

			$allproduct_price += $product_price;
		}
		//dump($allbuydata);

        $where = [
            'aid' => aid,
            'id' => cache($this->sessionid.'_tableId')
        ];
        if($bid!=0){
            $where['bid'] = bid;
        }
        $table = Db::name('restaurant_table')->where($where)->find();
		
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['havetongcheng'] = $havetongcheng;
		$rdata['address'] = $address;
		$rdata['linkman'] = $address ? $address['name'] : strval($userinfo['realname']);
		$rdata['tel'] = $address ? $address['tel'] : strval($userinfo['tel']);
        $rdata['tableName'] = $table['name'];
		$rdata['userinfo'] = $userinfo;
		$rdata['allbuydata'] = $allbuydata;
		$rdata['needLocation'] = $needLocation;
		$rdata['scorebdkyf'] = Db::name('admin_set')->where('aid',aid)->value('scorebdkyf');
		return $this->json($rdata);
	}
	public function createOrder(){
		$this->checklogin();

        if(empty(cache($this->sessionid.'_tableId'))) {
            return $this->json(['status'=>0,'msg'=>'请扫桌码点餐']);
        }

		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$post = input('post.');
		//收货地址
        $address = ['id'=>0,'name'=>$post['linkman'],'tel'=>$post['tel'],'area'=>'','address'=>''];

		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
        $adminset = Db::name('admin_set')->where('aid',aid)->find();
        $userinfo = [];
        $userinfo['discount'] = $userlevel['discount'];
        $userinfo['score'] = $this->member['score'];
        $userinfo['score2money'] = $adminset['score2money'];
        $userinfo['scoredk_money'] = round($userinfo['score'] * $userinfo['score2money'],2);
        $userinfo['scoredkmaxpercent'] = $adminset['scoredkmaxpercent'];
        $userinfo['scoremaxtype'] = 0; //0最大百分比 1最大抵扣金额
        $userinfo['realname'] = $this->member['realname'];
        $userinfo['tel'] = $this->member['tel'];

		$buydata = $post['buydata'];
		$couponridArr = [];
		foreach($buydata as $data){ //判断有没有重复选择的优惠券
			if($data['couponrid'] && in_array($data['couponrid'],$couponridArr)){
				return $this->json(['status'=>0,'msg'=>t('优惠券').'不可重复使用']);
			}elseif($data['couponrid']){
				$couponridArr[] = $data['couponrid'];
			}
		}

		$ordernum = date('ymdHis').rand(100000,999999);
		$scoredkmaxmoney = 0;
		$scoremaxtype = 0;
		$i = 0;
		foreach($buydata as $data){
			$i++;
			$bid = $data['bid'];
			if($data['prodata']){
				$prodata = explode('-',$data['prodata']);
			}else{
				return $this->json(['status'=>0,'msg'=>'产品数据错误']);
			}
			$product_price = 0;
			$needzkproduct_price = 0;
			$givescore = 0; //奖励积分
			$totalweight = 0;//重量
			$totalnum = 0;
			$prolist = [];
			$proids = [];
			$cids = [];
			
			$freightList = \app\models\Freight::getList([['status','=',1],['aid','=',aid],['bid','=',$bid]]);

			/*$fids = [];
			foreach($freightList as $v){
				$fids[] = $v['id'];
			}*/
			foreach($prodata as $key=>$pro){
				$sdata = explode(',',$pro);
				$sdata[2] = intval($sdata[2]);
				if($sdata[2] <= 0) return $this->json(['status'=>0,'msg'=>'购买数量有误']);
				$product = Db::name('restaurant_product')->where('aid',aid)->where('status',1)->where('bid',$bid)->where('id',$sdata[0])->find();
				if(!$product) return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
				
				if($key==0) $title = $product['name'];

				$guige = Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$sdata[1])->find();
				if(!$guige) return $this->json(['status'=>0,'msg'=>'产品规格不存在或已下架']);
				if($guige['stock'] < $sdata[2]){
					return $this->json(['status'=>0,'msg'=>'库存不足']);
				}
				if($product['limit_per'] > 0){
					$buynum = $sdata[2] + Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('mid',mid)->where('product_id',$product['id'])->where('status','in','0,1,2,3')->sum('num');
					if($buynum > $product['limit_per']){
						return $this->json(['status'=>0,'msg'=>'每人限购'.$product['limit_per'].'件']);
					}
				}
                $guige = $this->formatguige($guige,$product['bid'],$product['lvprice']);
				$product_price += $guige['sell_price'] * $sdata[2];
				if($product['lvprice']==0){ //未开启会员价
					$needzkproduct_price += $guige['sell_price'] * $sdata[2];
				}
				$totalweight += $guige['weight'] * $sdata[2];
				$totalnum += $sdata[2];
				
				if($product['scored_set']==0){
					if($userinfo['scoredkmaxpercent'] == 0){
						$scoremaxtype = 1;
						$scoredkmaxmoney += 0;
					}else{
						if($userinfo['scoredkmaxpercent'] >= 0 && $userinfo['scoredkmaxpercent']<100){
							$scoredkmaxmoney += $userinfo['scoredkmaxpercent'] * 0.01 * $guige['sell_price'] * $sdata[2];
						}else{
							$scoredkmaxmoney += $guige['sell_price'] * $sdata[2];
						}
					}
				}elseif($product['scored_set']==1){
					$scoremaxtype = 1;
					$scoredkmaxmoney += $product['scored_val'] * 0.01 * $guige['sell_price'] * $sdata[2];
				}elseif($product['scored_set']==2){
					$scoremaxtype = 1;
					$scoredkmaxmoney += $product['scored_val'] * $sdata[2];
				}else{
					$scoremaxtype = 1;
					$scoredkmaxmoney += 0;
				}

				$prolist[] = ['product'=>$product,'guige'=>$guige,'num'=>$sdata[2],'skrs'=>$skrs,'isSeckill'=>$isSeckill];
				
				/*if($product['freighttype']==0){
					$fids = array_intersect($fids,explode(',',$product['freightdata']));
				}else{
					$thisfreightList = \app\models\Freight::getList([['status','=',1],['aid','=',aid],['bid','=',$bid]]);
					$thisfids = [];
					foreach($thisfreightList as $v){
						$thisfids[] = $v['id'];
					}
					$fids = array_intersect($fids,$thisfids);
				}*/
				$proids[] = $product['id'];
				$cids[] = $product['cid'];
				$givescore += $guige['givescore'] * $sdata[2];
			}
//			if(!$fids){
//				if(count($productList)>1){
//					return $this->json(['status'=>0,'msg'=>'所选择菜品配送方式不同，请分别下单']);
//				}else{
//					return $this->json(['status'=>0,'msg'=>'获取配送方式失败']);
//				}
//			}
			//会员折扣
			$leveldk_money = 0;
			if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
				$leveldk_money = $needzkproduct_price * (1 - $userlevel['discount'] * 0.1);
			}
			$totalprice = $product_price - $leveldk_money;

			//满减活动
            $manjian_money = 0;
			/*$mjset = Db::name('manjian_set')->where('aid',aid)->find();
			if($mjset && $mjset['status']==1){
				$mjdata = json_decode($mjset['mjdata'],true);
			}else{
				$mjdata = array();
			}

			$moneyduan = 0;
			if($mjdata){
				foreach($mjdata as $give){
					if($totalprice*1 >= $give['money']*1 && $give['money']*1 > $moneyduan){
						$moneyduan = $give['money']*1;
						$manjian_money = $give['jian']*1;
					}
				}
			}
			if($manjian_money <= 0) $manjian_money = 0;*/
			$totalprice = $totalprice - $manjian_money;
			if($totalprice < 0) $totalprice = 0;

			//运费
			$freight_price = 0;
			/*if($data['freight_id']){
				$freight = Db::name('freight')->where('aid',aid)->where('id',$data['freight_id'])->find();
				if($freight['minpriceset']==1 && $freight['minprice']>0 && $freight['minprice'] > $product_price){
					return $this->json(['status'=>0,'msg'=>$freight['name'] . '满'.$freight['minprice'].'元起送']);
				}
				if(($address['name']=='' || $address['tel'] =='') && ($freight['pstype']==1 || $freight['pstype']==3) && $freight['needlinkinfo']==1){
					return $this->json(['status'=>0,'msg'=>'请填写联系人和联系电话']);
				}
				
				$field_list = json_decode($freight['field_list'],true);
				if($field_list){
					if($field_list['message']['isshow']==1 && $field_list['message']['required']==1 && $data['message']==''){
						return $this->json(['status'=>0,'msg'=>'请填写备注信息']);
					}
					if($field_list['field1']['isshow']==1 && $field_list['field1']['required']==1 && $data['field1']==''){
						return $this->json(['status'=>0,'msg'=>'请填写'.$field_list['field1']['name']]);
					}
					if($field_list['field2']['isshow']==1 && $field_list['field2']['required']==1 && $data['field2']==''){
						return $this->json(['status'=>0,'msg'=>'请填写'.$field_list['field2']['name']]);
					}
					if($field_list['field3']['isshow']==1 && $field_list['field3']['required']==1 && $data['field3']==''){
						return $this->json(['status'=>0,'msg'=>'请填写'.$field_list['field3']['name']]);
					}
					if($field_list['field4']['isshow']==1 && $field_list['field4']['required']==1 && $data['field4']==''){
						return $this->json(['status'=>0,'msg'=>'请填写'.$field_list['field4']['name']]);
					}
					if($field_list['field5']['isshow']==1 && $field_list['field5']['required']==1 && $data['field5']==''){
						return $this->json(['status'=>0,'msg'=>'请填写'.$field_list['field5']['name']]);
					}
					if($field_list['field1']['isshow']==1){
						$data['field1'] = $field_list['field1']['name'] . '^_^' .$data['field1'];
					}
					if($field_list['field2']['isshow']==1){
						$data['field2'] = $field_list['field2']['name'] . '^_^' .$data['field2'];
					}
					if($field_list['field3']['isshow']==1){
						$data['field3'] = $field_list['field3']['name'] . '^_^' .$data['field3'];
					}
					if($field_list['field4']['isshow']==1){
						$data['field4'] = $field_list['field4']['name'] . '^_^' .$data['field4'];
					}
					if($field_list['field5']['isshow']==1){
						$data['field5'] = $field_list['field5']['name'] . '^_^' .$data['field5'];
					}
				}
				$rs = \app\models\Freight::getFreightPrice($freight,$address,$product_price,$totalnum,$totalweight);
				if($rs['status']==0) return $this->json([$rs]);
				$freight_price = $rs['freight_price'];
				//判断配送时间选择是否符合要求
				if($freight['pstimeset']==1){
					$freight_times = explode('~',$data['freight_time']);
					if($freight_times[1]){
						$freighttime = strtotime(explode(' ',$freight_times[0])[0] . ' '.$freight_times[1]);
					}else{
						$freighttime = strtotime($freight_times[0]);
					}
					if(time() + $freight['psprehour']*3600 > $freighttime){
						return $this->json(['status'=>0,'msg'=>($freight['pstype']==0?'配送':'提货').'时间必须在'.$freight['psprehour'].'小时之后']);
					}
				}
			}else{

			}*/$freight = ['id'=>0,'name'=>'包邮','pstype'=>0];
			//优惠券
            $coupon_money = 0;
			if($data['couponrid'] > 0){
				/*$couponrid = $data['couponrid'];
				$couponrecord = Db::name('coupon_record')->where("bid=-1 or bid=".$data['bid'])->where('aid',aid)->where('mid',mid)->where('id',$couponrid)->find();
				if(!$couponrecord){
					return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不存在']);
				}elseif($couponrecord['status']!=0){
					return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'已使用过了']);
				}elseif($couponrecord['starttime'] > time()){
					return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'尚未开始使用']);	
				}elseif($couponrecord['endtime'] < time()){
					return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'已过期']);	
				}elseif($couponrecord['minprice'] > $totalprice){
					return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
				}elseif($couponrecord['type']!=1 && $couponrecord['type']!=4){
					return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
				}

				$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$couponrecord['couponid'])->find();
				if($couponinfo['fwtype']==2){//指定菜品可用
					$productids = explode(',',$couponinfo['productids']);
					if(!array_intersect($proids,$productids)){
						return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'指定菜品可用']);
					}
				}
				if($couponinfo['fwtype']==1){//指定类目可用
					$categoryids = explode(',',$couponinfo['categoryids']);
					$clist = Db::name('shop_category')->where('id','in',$cids)->select()->toArray();
					foreach($clist as $kc=>$vc){
						if($vc['pid']){
							$cids[] = $vc['pid'];
							$cate2 = Db::name('shop_category')->where('id',$vc['pid'])->find();
							if($cate2 && $cate2['pid']){
								$cids[] = $cate2['pid'];
							}
						}
					}
					if(!array_intersect($cids,$categoryids)){
						return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'指定分类可用']);
					}
				}

				Db::name('coupon_record')->where('id',$couponrid)->update(['status'=>1,'usetime'=>time()]);
				if($couponrecord['type']==4){//运费抵扣券
					$coupon_money = $freight_price;
				}else{
					$coupon_money = $couponrecord['money'];
					if($coupon_money > $totalprice) $coupon_money = $totalprice;
				}*/
			}
			//促销活动
            $cuxiaomoney = 0;
			if($data['cuxiaoid'] > 0){
				/*$cuxiaoid = $data['cuxiaoid'];
				$cuxiaoinfo = Db::name('cuxiao')->where("bid=-1 or bid=".$data['bid'])->where('aid',aid)->where('id',$cuxiaoid)->find();
				if(!$cuxiaoinfo){
					return $this->json(['status'=>0,'msg'=>'该促销活动不存在']);
				}elseif($cuxiaoinfo['starttime'] > time()){
					return $this->json(['status'=>0,'msg'=>'该促销活动尚未开始']);	
				}elseif($cuxiaoinfo['endtime'] < time()){
					return $this->json(['status'=>0,'msg'=>'该促销活动已结束']);	
				}elseif($cuxiaoinfo['minprice'] > $totalprice){
					return $this->json(['status'=>0,'msg'=>'该促销活动不符合条件']);
				}
				if($cuxiaoinfo['fwtype']==2){//指定菜品可用
					$productids = explode(',',$cuxiaoinfo['productids']);
					if(!array_intersect($proids,$productids)){
						return $this->json(['status'=>0,'msg'=>'该促销活动指定菜品可用']);
					}
				}
				if($cuxiaoinfo['fwtype']==1){//指定类目可用
					$categoryids = explode(',',$cuxiaoinfo['categoryids']);
					$clist = Db::name('shop_category')->where('id','in',$cids)->select()->toArray();
					foreach($clist as $kc=>$vc){
						if($vc['pid']){
							$cids[] = $vc['pid'];
							$cate2 = Db::name('shop_category')->where('id',$vc['pid'])->find();
							if($cate2 && $cate2['pid']){
								$cids[] = $cate2['pid'];
							}
						}
					}
					if(!array_intersect($cids,$categoryids)){
						return $this->json(['status'=>0,'msg'=>'该促销活动指定分类可用']);
					}
				}
				if($cuxiaoinfo['type']==1){//满减
					$cuxiaomoney = $cuxiaoinfo['money'] * -1;
				}elseif($cuxiaoinfo['type']==2){//满赠
					$cuxiaomoney = 0;
					$product = Db::name('shop_product')->where('aid',aid)->where('id',$cuxiaoinfo['proid'])->find();
					$guige = Db::name('shop_guige')->where('aid',aid)->where('id',$cuxiaoinfo['ggid'])->find();
					if(!$product) return $this->json(['status'=>0,'msg'=>'赠送产品不存在']);
					if(!$guige) return $this->json(['status'=>0,'msg'=>'赠送产品规格不存在']);
					if($guige['stock'] < 1){
						return $this->json(['status'=>0,'msg'=>'赠送产品库存不足']);
					}
					$prolist[] = ['product'=>$product,'guige'=>$guige,'num'=>1,'isSeckill'=>0];
				}elseif($cuxiaoinfo['type']==3){//换购
					$cuxiaomoney = $cuxiaoinfo['money'];
					$product = Db::name('shop_product')->where('aid',aid)->where('id',$cuxiaoinfo['proid'])->find();
					$guige = Db::name('shop_guige')->where('aid',aid)->where('id',$cuxiaoinfo['ggid'])->find();
					if(!$product) return $this->json(['status'=>0,'msg'=>'换购产品不存在']);
					if(!$guige) return $this->json(['status'=>0,'msg'=>'换购产品规格不存在']);
					if($guige['stock'] < 1){
						return $this->json(['status'=>0,'msg'=>'换购产品库存不足']);
					}
					$prolist[] = ['product'=>$product,'guige'=>$guige,'num'=>1,'isSeckill'=>0];
				}else{
					$cuxiaomoney = 0;
				}*/
			}
			$totalprice = $totalprice - $coupon_money + $cuxiaomoney;
			$totalprice = $totalprice + $freight_price;
			//积分抵扣
			$scoredkscore = 0;
			$scoredk_money = 0;
			if($post['usescore']==1){
				$adminset = Db::name('admin_set')->where('aid',aid)->find();
				$score2money = $adminset['score2money'];
				$scoredkmaxpercent = $adminset['scoredkmaxpercent'];
				$scorebdkyf = $adminset['scorebdkyf'];
				$scoredk_money = $this->member['score'] * $score2money;
				if($scorebdkyf == 1){//积分不抵扣运费
					if($scoredk_money > $totalprice - $freight_price) $scoredk_money = $totalprice - $freight_price;
				}else{
					if($scoredk_money > $totalprice) $scoredk_money = $totalprice;
				}
				if($scoremaxtype == 0){
					if($scoredkmaxpercent >= 0 && $scoredkmaxpercent < 100 && $scoredk_money > 0 && $scoredk_money > $totalprice * $scoredkmaxpercent * 0.01){
						$scoredk_money = $totalprice * $scoredkmaxpercent * 0.01;
					}
				}else{
					if($scoredk_money > $scoredkmaxmoney) $scoredk_money = $scoredkmaxmoney;
				}
				$totalprice = $totalprice - $scoredk_money;
				$totalprice = round($totalprice*100)/100;
				if($scoredk_money > 0){
					$scoredkscore = $scoredk_money / $score2money;
					\app\commons\Member::addscore(aid,mid,-$scoredkscore,'购买菜品'.t('积分').'抵扣');
				}
			}

			//桌号
            $whereTable = [
                'aid' => aid,
                'id' => cache($this->sessionid.'_tableId')
            ];
            if($bid!=0){
                $whereTable['bid'] = bid;
            }
            $table = Db::name('restaurant_table')->where($whereTable)->find();

			$orderdata = [];
			$orderdata['aid'] = aid;
			$orderdata['mid'] = mid;
			$orderdata['bid'] = $data['bid'];
			if(count($buydata) > 1){
				$orderdata['order_no'] = $ordernum.'_'.$i;
			}else{
				$orderdata['order_no'] = $ordernum;
			}
            $orderdata['table_id'] = $table['id'];
            $orderdata['table_cid'] = $table['cid'];
            $orderdata['seat'] = $table['seat'];
			$orderdata['title'] = $title.(count($prodata)>1?'等':'');
			
			$orderdata['linkman'] = $address['name'];
			$orderdata['tel'] = $address['tel'];
			$orderdata['totalprice'] = $totalprice;
			$orderdata['product_price'] = $product_price;
			$orderdata['leveldk_money'] = $leveldk_money;  //会员折扣
			$orderdata['manjian_money'] = $manjian_money;  //满减活动
			$orderdata['scoredk_money'] = $scoredk_money;	//积分抵扣
			$orderdata['scoredkscore'] = $scoredkscore;	//抵扣掉的积分
//			$orderdata['freight_price'] = $freight_price; //运费
			$orderdata['givescore'] = $givescore;
			$orderdata['message'] = $data['message'];
            $orderdata['create_time'] = time();
			/*if($freight && $freight['pstype']==0){ //快递
				$orderdata['freight_text'] = $freight['name'].'('.$freight_price.'元)';
			}elseif($freight && $freight['pstype']==1){ //到店自提
				$orderdata['freight_storeid'] = $data['storeid'];
				if($data['bid']!=0){
					$storename = Db::name('business')->where('aid',aid)->where('id',$data['bid'])->value('name');
				}else{
					$storename = Db::name('mendian')->where('aid',aid)->where('id',$data['storeid'])->value('name');
				}
				$orderdata['freight_text'] = $freight['name'].'['.$storename.']';
				$orderdata['freight_type'] = 1;
			}elseif($freight && $freight['pstype']==2){ //同城配送
				$orderdata['freight_text'] = $freight['name'].'('.$freight_price.'元)';
				$orderdata['freight_type'] = 2;
			}elseif($freight && $freight['pstype']==3){ //自动发货
				$orderdata['freight_text'] = $freight['name'];
				$orderdata['freight_type'] = 3;
			}else{
				$orderdata['freight_text'] = '包邮';
			}*/
//			$orderdata['freight_id'] = $freight['id'];
//			$orderdata['freight_time'] = $data['freight_time']; //配送时间
			$orderdata['coupon_rid'] = $couponrid;
			$orderdata['coupon_money'] = $coupon_money; //优惠券抵扣
			$orderdata['platform'] = platform;
			$orderid = Db::name('restaurant_shop_order')->insertGetId($orderdata);
			
			$istc = 0; //设置了按单固定提成时 只将佣金计算到第一个菜品里
			foreach($prolist as $key=>$v){
				$product = $v['product'];
				$guige = $v['guige'];
				$num = $v['num'];
				$ogdata = [];
				$ogdata['aid'] = aid;
				$ogdata['bid'] = $product['bid'];
				$ogdata['mid'] = mid;
				$ogdata['order_id'] = $orderid;
				$ogdata['order_no'] = $orderdata['order_no'];
				$ogdata['product_id'] = $product['id'];
				$ogdata['name'] = $product['name'];
				$ogdata['pic'] = $product['pic'];
				$ogdata['procode'] = $product['procode'];
				$ogdata['guige_id'] = $guige['id'];
				$ogdata['guige_name'] = $guige['name'];
				$ogdata['cid'] = $product['cid'];
				$ogdata['num'] = $num;
				$ogdata['cost_price'] = $guige['cost_price'];
				$ogdata['sell_price'] = $guige['sell_price'];
				$ogdata['totalprice'] = $num * $guige['sell_price'];
				$ogdata['status'] = 0;
				$ogdata['create_time'] = time();
				if($istc!=1){
					$ogtotalprice = $ogdata['totalprice'];
					if($sysset['fxjiesuantype'] == 1 || $sysset['fxjiesuantype'] == 2){
						$allproduct_price = $product_price - $leveldk_money + $cuxiaomoney;
						$leveldk_money = 0;
						$ogcouponmoney = 0;
						$ogscoredk = 0;
						if($product['lvprice']==0 && $userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){ //未开启会员价
							$leveldk_money = $ogtotalprice * (1 - $userlevel['discount'] * 0.1);
							$ogtotalprice = $ogtotalprice - $leveldk_money;
						}
						if($coupon_money){
							$ogcouponmoney = $ogtotalprice / $allproduct_price * $coupon_money;
						}
						//todo 积分
						if($scoredk){
							$ogscoredk = $ogtotalprice / $allproduct_price * $scoredk;
						}
						if($manjian_money){
							$ogmanjian_money = $ogtotalprice / $allproduct_price * $manjian_money;
						}
						if($cuxiaomoney < 0){
							$ogcuxiaomoney = $ogtotalprice / $allproduct_price * $cuxiaomoney * -1;
						}
						$ogtotalprice = round($ogtotalprice - $ogcouponmoney - $ogscoredk - $ogmanjian_money - $ogcuxiaomoney,2);
						if($ogtotalprice < 0) $ogtotalprice = 0;
					}
					$agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
					if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
						$this->member['pid'] = mid;
					}
					//$ppath = array_reverse(explode(',',$this->member['path']));
					
					if($product['commissionset']!=-1){
						//return $this->json(['status'=>0,'msg'=>'11','data'=>$this->member]);
						if($this->member['pid']){
							$parent1 = Db::name('member')->where('aid',aid)->where('id',$this->member['pid'])->find();
							
							if($parent1){
								$agleveldata1 = Db::name('member_level')->where('aid',aid)->where('id',$parent1['levelid'])->find();
								if($agleveldata1['can_agent']!=0){
									$ogdata['parent1'] = $parent1['id'];
								}
							}
							//return $this->json(['status'=>0,'msg'=>'11','data'=>$parent1,'data2'=>$agleveldata1]);
						}
						if($parent1['pid']){
							$parent2 = Db::name('member')->where('aid',aid)->where('id',$parent1['pid'])->find();
							if($parent2){
								$agleveldata2 = Db::name('member_level')->where('aid',aid)->where('id',$parent2['levelid'])->find();
								if($agleveldata2['can_agent']>1){
									$ogdata['parent2'] = $parent2['id'];
								}
							}
						}
						if($parent2['pid']){
							$parent3 = Db::name('member')->where('aid',aid)->where('id',$parent2['pid'])->find();
							if($parent3){
								$agleveldata3 = Db::name('member_level')->where('aid',aid)->where('id',$parent3['levelid'])->find();
								if($agleveldata3['can_agent']>2){
									$ogdata['parent3'] = $parent3['id'];
								}
							}
						}
						if($sysset['fxjiesuantype']==2){ //按利润提成
							$ogtotalprice = $ogtotalprice - $guige['cost_price'] * $num;
							if($ogtotalprice < 0) $ogtotalprice = 0;
						}
						if($product['commissionset']==1){//按比例
							$commissiondata = json_decode($product['commissiondata1'],true);
							if($commissiondata){
								$ogdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $ogtotalprice * 0.01;
								$ogdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $ogtotalprice * 0.01;
								$ogdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $ogtotalprice * 0.01;
							}
						}elseif($product['commissionset']==2){//按固定金额
							$commissiondata = json_decode($product['commissiondata2'],true);
							if($commissiondata){
								$ogdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
								$ogdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
								$ogdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
							}
						}elseif($product['commissionset']==3){//提成是积分
							$commissiondata = json_decode($product['commissiondata3'],true);
							if($commissiondata){
								$ogdata['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
								$ogdata['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
								$ogdata['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
							}
						}else{
							if($agleveldata1['commissiontype']==1){ //固定金额按单
								$ogdata['parent1commission'] = $agleveldata1['commission1'];
								$ogdata['parent2commission'] = $agleveldata2['commission2'];
								$ogdata['parent3commission'] = $agleveldata3['commission3'];
								$istc = 1;
							}else{
								$ogdata['parent1commission'] = $agleveldata1['commission1'] * $ogtotalprice * 0.01;
								$ogdata['parent2commission'] = $agleveldata2['commission2'] * $ogtotalprice * 0.01;
								$ogdata['parent3commission'] = $agleveldata3['commission3'] * $ogtotalprice * 0.01;
							}
						}
					}
				}
				$ogid = Db::name('restaurant_shop_order_goods')->insertGetId($ogdata);
	
				//删除购物车
                Db::name('shop_cart')->where('aid',aid)->where('mid',mid)->where('ggid',$guige['id'])->where('proid',$product['id'])->delete();

				Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$guige['id'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
				Db::name('restaurant_product')->where('aid',aid)->where('id',$product['id'])->update(['stock'=>Db::raw("stock-$num"),/*'stock_daily'=>Db::raw("stock_daily-$num"),*/'sales'=>Db::raw("sales+$num"), 'real_sales'=>Db::raw("real_sales+$num")]);
			}
			//上线开启
			/*if($orderdata['bid']==0){
				//公众号通知 订单提交成功
				$tmplcontent = [];
				$tmplcontent['first'] = '有新订单提交成功';
				$tmplcontent['remark'] = '点击进入查看~';
				$tmplcontent['keyword1'] = Db::name('admin_set')->where('aid',aid)->value('name'); //店铺
				$tmplcontent['keyword2'] = date('Y-m-d H:i:s',$orderdata['createtime']);//下单时间
				$tmplcontent['keyword3'] = $orderdata['title'];//菜品
				$tmplcontent['keyword4'] = $orderdata['totalprice'].'元';//金额
                //todo 跳转订单url
				\app\commons\Wechat::sendhttmpl(aid,'tmpl_orderconfirm',$tmplcontent,PRE_URL.'/am.php?s=/order/shoporder/aid/'.aid,$orderdata['freight_storeid']);
			}*/
            if($data['bid']) {
                $set = Db::name('restaurant_business_set')->where('aid',aid)->where('bid',$data['bid'])->find();
            } else {
                $set = Db::name('restaurant_admin_set')->where('aid',aid)->find();
            }
            //小票打印机 如先下单后付款，先打印
            if($set['pay_after']) {
                \app\commons\Wifiprint::print(aid,'restaurant_shop_order',$orderid);
            }
		}

		return $this->json(['status'=>1,'orderid'=>$orderid,'ordercount'=>count($buydata),'ordernum'=>$ordernum,'msg'=>'提交成功']);
	}

	private function getparentsmids($aid,$mid,$levelid,$deepnum,$nowdeep=1,$mids=[]){
		if(!$mid || !$levelid || $nowdeep>$deepnum) return $this->json([]);
		$member = Db::name('member')->where('id',$mid)->find();
		if($member['levelid'] == $levelid){
			$mids[] = $mid;
		}
		if(!$member['pid'] || $nowdeep>=$deepnum){
			return $mids;
		}else{
			return $this->getparentsmids($aid,$member['pid'],$levelid,$deepnum,$nowdeep+1,$mids);
		}
	}
}