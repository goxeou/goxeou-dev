<?php


namespace app\controllers;
use think\facade\Db;
use think\facade\Log;

class ApiRestaurantShop extends ApiCommon{
    public function initialize(){
		parent::initialize();
		$this->checklogin();

	}
	
	//点餐页面
	public function index(){
		$bid = input('param.bid/d', 0);
        $tableId = input('param.tableId/d', 0);
		if(!$bid) $bid = 0;
		if($bid!=0){
			$business = Db::name('business')->where('aid',aid)->where('id',$bid)->field('id,name,logo,content,pics,desc,tel,address,sales,start_hours,end_hours,zhengming,longitude,latitude')->find();
			$business['pic'] = explode(',',$business['pics'])[0];
            $business['zhengming'] = $business['zhengming'] ? explode(',',$business['zhengming']) : [];
		}else{
			$business = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,desc,tel,address,longitude,latitude')->find();
		}
		$shop_set = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',$bid)->find();
		if($shop_set['banner']) $business['pic'] = $shop_set['banner'];

		if($shop_set['status']==0){
			return $this->json(['status'=>0,'msg'=>'该商家未开启点餐']);
		}
		if($shop_set['start_hours'] != $shop_set['end_hours']){
			$start_time = strtotime(date('Y-m-d '.$shop_set['start_hours']));
			$end_time = strtotime(date('Y-m-d '.$shop_set['end_hours']));
			if(($start_time < $end_time && ($start_time > time() || $end_time < time()))
                || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
				return $this->json(['status'=>0,'msg'=>'该商家不在营业时间']);
			}
		}
        //系统设置
        $sysset = Db::name('admin_set')->field('name,logo,desc,tel,gzts,ddbb,ddbbtourl,mode,address')->where('aid',aid)->find();
        $shop_set['mode'] =$sysset['mode'];

        $shop_set['is_loc_business'] = 0;
        $cid = input('param.cid');
		if(!$cid) $cid = 0;
		$clist = Db::name('restaurant_product_category')->where('aid',aid)->where('bid',$bid)->where('pid',$cid)->where('status',1)->where('is_shop',1)->order('sort desc,id')->select()->toArray();
		foreach($clist as $k=>$v){
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',$bid];
			//$where[] = ['status','=',1];
			$where[] = ['ischecked','=',1];
//			$where[] = ['stock','>',0];
//			$where[] = Db::raw('stock_daily-sales_daily>0');
			$week = date("w");
			if($week==0) $week = 7;
			$where[] = Db::raw('find_in_set('.$week.',status_week)');
			$nowtime = time();
			$nowhm = date('H:i');
			$where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");

			$where[] = Db::raw("find_in_set(".$v['id'].",cid)");
            //过滤单独购买菜品
            $field  ="pic,id,bid,name,stock,sales,market_price,sell_price,lvprice,lvprice_data,guigedata,limit_start,limit_per,stock_daily,sales_daily";
            if(getcustom('restaurant_weigh') || getcustom('restaurant_product_package')){
                $field .= ",product_type";
            }
            $prolist = Db::name('restaurant_product')->field($field)->where($where)->orderRaw('if(stock_daily>sales_daily,1,0) desc,sort desc,id desc')->select()->toArray();
            if(!$prolist) $prolist = [];
			$prolist = $this->formatprolist($prolist);
			if(!$prolist){
				unset($clist[$k]);
			}else{
				foreach($prolist as $k2=>$v2){
					$gglist = Db::name('restaurant_product_guige')->where('product_id',$v2['id'])->select()->toArray();
					$prolist[$k2]['gglist'] = $gglist;
					$prolist[$k2]['ggcount'] = count($gglist);
					if($v2['limit_start']==0) $v2['limit_start'] = 1;
					if($v2['limit_per']==0) $v2['limit_per'] = 999999;
					$have_jialiao = 0;
					$prolist[$k2]['have_jialiao'] =$have_jialiao;
					}
				$clist[$k]['prolist'] = $prolist;
			}
		}
		$clist = array_values($clist);

		$list = Db::name('restaurant_shop_cart')->where('aid',aid)->where('bid',$bid)->where('mid',mid)->order('createtime desc')->select()->toArray();
		$total = 0;
		$totalprice = 0;
		foreach($list as $k=>$v){
            $field ='cid,pic,aid,id,bid,name,stock,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint,guigedata';
            $product = Db::name('restaurant_product')->field($field)->where('id',$v['proid'])->find();
			if(!$product){
				unset($list[$k]);
				Db::name('restaurant_shop_cart')->where('id',$v['id'])->delete();
				continue;
			}
			$product = $this->formatproduct($product);
			$guige = Db::name('restaurant_product_guige')->where('id',$v['ggid'])->find();

            $deletegg = 1;
            if(!$guige && $deletegg){
				unset($list[$k]);
				Db::name('restaurant_shop_cart')->where('id',$v['id'])->delete();
				continue;
			}
            $guige = $this->formatguige($guige,$product['bid'],$product['lvprice']);
            $total += $v['num'];
			$totalprice += $guige['sell_price'] * $v['num'];
			$list[$k]['jltitle'] = isset($v['jltitle'])?$v['jltitle']:'';
            $list[$k]['jlprice'] = isset($v['jlprice'])?$v['jlprice']:0;
            $list[$k]['product'] = $product;
            $list[$k]['guige'] = $guige;
		}
		$totalprice = number_format($totalprice,2,'.','');
		
		$cartList = ['list'=>$list,'total'=>$total,'totalprice'=>$totalprice];
		$numtotal = [];
		$numCat = [];
		foreach($clist as $i=>$v){
			foreach($v['prolist'] as $j=>$pro){
				$numtotal[$pro['id']] = 0;
                $numCat[$v['id']] = 0;
			}
		}
		foreach($cartList['list'] as $i=>$v){
			$numtotal[$v['proid']] += $v['num'];
            //分类数量
            if($v['product']['cid']) {
                $cids = explode(',', $v['product']['cid']);
                if($cids){
                    foreach ($cids as $cid)
                        $numCat[$cid] += $v['num'];
                }
            }
		}
		$table = [];
		$change_people_number = 0;//选择就餐人数
		if($tableId){
            $table = Db::name('restaurant_table')->where('aid',aid)->where('id',$tableId)->find();
            //显示商品名称
            $show_table_name = 1;
            $table['show_table_name'] = $show_table_name;
            //如果存在订单
            $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('id', $table['orderid'])->find();
            
            if(!$order ||  $order['status'] != 0){
                $change_people_number = 1;
            }
        }
        $mdid = input('param.mdid');
		if(!$tableId && $mdid){
            $change_people_number = 1;
        }
        $shop_set['change_people_number'] = $change_people_number;
        $rdata = [];
		$rdata['status'] = 1;
		$rdata['data'] = $clist;
		$rdata['cartList'] = $cartList;
		$rdata['numtotal'] = $numtotal;
        $rdata['numCat'] = $numCat;
		$rdata['business'] = $business;
		$rdata['sysset'] = $shop_set;
        $rdata['table'] = $table;
        return $this->json($rdata);
	}
	//菜品列表
    public function getprolist(){
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['ischecked','=',1];
        //$where[] = ['status','=',1];
        $week = date("w");
        if($week==0) $week = 7;
        $where[] = Db::raw('find_in_set('.$week.',status_week)');
        $nowtime = time();
        $nowhm = date('H:i');
        $where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");

        if(input('param.bid')){
            $bid = input('param.bid/d');
            $where[] = ['bid','=',input('param.bid/d')];
        }else{
            $business_sysset = Db::name('business_sysset')->where('aid',aid)->find();
            if(!$business_sysset || $business_sysset['status']==0 || $business_sysset['product_isshow']==0){
                $where[] = ['bid','=',0];
            }
            $bid = 0;
        }

        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order').',sort desc,id desc';
        }else{
            $order = 'sort desc,id desc';
        }
        //分类
        if(input('param.cid')){
            $cid = input('post.cid') ? input('post.cid/d') : input('param.cid/d');
            //子分类
            $clist = Db::name('restaurant_product_category')->where('aid',aid)->where('bid',$bid)->where('pid',$cid)->column('id');
            if($clist){
                $clist2 = Db::name('restaurant_product_category')->where('aid',aid)->where('bid',$bid)->where('pid','in',$clist)->column('id');
                $cCate = array_merge($clist, $clist2, [$cid]);
                if($cCate){
                    $whereCid = [];
                    foreach($cCate as $k => $c2){
                        $whereCid[] = "find_in_set({$c2},cid)";
                    }
                    $where[] = Db::raw(implode(' or ',$whereCid));
                }
            } else {
                $where[] = Db::raw("find_in_set(".$cid.",cid)");
            }
        } else {
            $clist = Db::name('restaurant_product_category')->where('aid',aid)->where('bid',$bid)->where('status',1)->where('is_shop',1)->order('sort desc,id')->select()->toArray();
            $cidwhere = '';
            foreach($clist as $k=>$v){
                if($k == count($clist)-1){
                    $cidwhere .= "find_in_set(".$v['id'].",cid)";
                } else
                $cidwhere .= " find_in_set(".$v['id'].",cid) or ";
            }
            $where[] = Db::raw($cidwhere);
        }

        if(input('param.keyword')){
            $where[] = ['name','like','%'.input('param.keyword').'%'];
        } else {
            return $this->json(['status'=>1,'data'=>[]]);
        }

        $pernum = 10;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
//        $datalist = Db::name('restaurant_product')->field("id,pic,name,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint")->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
        $field = "pic,id,name,stock,sales,market_price,sell_price,lvprice,lvprice_data,guigedata,limit_start,limit_per,stock_daily,sales_daily";
        $prolist = Db::name('restaurant_product')->field($field)->where($where)->page($pagenum,$pernum)->orderRaw('if(stock_daily>sales_daily,1,0) desc,'.$order)->select()->toArray();
        if(!$prolist) $prolist = [];
        $prolist = $this->formatprolist($prolist);
        if(!$prolist){
            unset($clist[$k]);
        }else{
            foreach($prolist as $k2=>$v2){
                $gglist = Db::name('restaurant_product_guige')->where('product_id',$v2['id'])->select()->toArray();
                $prolist[$k2]['gglist'] = $gglist;
                $prolist[$k2]['ggcount'] = count($gglist);
                if($v2['limit_start']==0) $v2['limit_start'] = 1;
                if($v2['limit_per']==0) $v2['limit_per'] = 999999;
            }
            $clist[$k]['prolist'] = $prolist;
        }
        if(!$prolist) $prolist = [];
//        $datalist = $this->formatprolist($datalist);
        return $this->json(['status'=>1,'data'=>$prolist]);
    }
	//获取菜品列表 评价列表
	public function getdatalist(){
		$id = input('param.id/d');
		$st = input('param.st/d');
		$pagenum = input('param.pagenum');
		if(!$pagenum) $pagenum = 1;
		if($st == 0){//菜品
			$pernum = 20;
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',$id];
			//$where[] = ['status','=',1];
			$nowtime = time();
			$nowhm = date('H:i');
			$where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");

			$prolist = Db::name('restaurant_product')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$prolist) $prolist = [];
			if(request()->isPost()){
				return $this->json(['status'=>1,'data'=>$prolist]);
			}
		}else{//评价
			$pernum = 10;
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',$id];
			$where[] = ['status','=',1];
			$commentlist = Db::name('business_comment')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
			if(!$commentlist) $commentlist = [];
			foreach($commentlist as $k=>$pl){
				$commentlist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
				if($commentlist[$k]['content_pic']) $commentlist[$k]['content_pic'] = explode(',',$commentlist[$k]['content_pic']);
			}
			if(request()->isPost()){
				return $this->json(['status'=>1,'data'=>$commentlist]);
			}
		}
	}

	//菜品
	public function product(){
		$proid = input('param.id/d');
		$product = Db::name('restaurant_product')->where('id',$proid)->where('aid',aid)->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'菜品不存在']);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'菜品未上架']);
		if($product['ischecked']!=1) return $this->json(['status'=>0,'msg'=>'菜品未审核']);
		
		if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
			return $this->json(['status'=>0,'msg'=>'菜品未上架']);
		}
		if($product['status']==3){
			$start_time = strtotime(date('Y-m-d '.$product['start_hours']));
			$end_time = strtotime(date('Y-m-d '.$product['end_hours']));
			if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
				return $this->json(['status'=>0,'msg'=>'菜品未上架']);
			}
		}
		if($product['status']==2 || $product['status']==3) $product['status']=1;

		if(!$product['pics']) $product['pics'] = $product['pic'];
		$product['pics'] = explode(',',$product['pics']);
		$product = $this->formatproduct($product);

		//是否收藏
		$rs = Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','restaurant_shop')->find();
		if($rs){
			$isfavorite = true;
		}else{
			$isfavorite = false;
		}
		//获取评论
		$commentlist = Db::name('restaurant_shop_comment')->where('aid',aid)->where('proid',$proid)->where('status',1)->order('id desc')->limit(10)->select()->toArray();
		if(!$commentlist) $commentlist = [];
		foreach($commentlist as $k=>$pl){
			$commentlist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
			if($commentlist[$k]['content_pic']) $commentlist[$k]['content_pic'] = explode(',',$commentlist[$k]['content_pic']);
		}
		$commentcount = Db::name('restaurant_shop_comment')->where('aid',aid)->where('proid',$proid)->where('status',1)->count();
		//添加浏览历史
		if(mid){
			$rs = Db::name('member_history')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','restaurant_shop')->find();
			if($rs){
				Db::name('member_history')->where('id',$rs['id'])->update(['createtime'=>time()]);
			}else{
				Db::name('member_history')->insert(['aid'=>aid,'mid'=>mid,'proid'=>$proid,'type'=>'restaurant_shop','createtime'=>time()]);
			}
		}

		$shopset = Db::name('restaurant_admin_set')->where('aid',aid)->field('shop_comment,shop_showcommission showcommission')->find();
        $sysset = Db::name('admin_set')->where('aid',aid)->field('name,logo,desc,fxjiesuantype,tel,kfurl,gzts,ddbb')->find();

        //预计佣金
        $commission = 0;
        $product['commission_desc'] = '元';
        if($this->member && $shopset['showcommission']==1 && $product['commissionset']!=-1){
            $userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
            if($userlevel['can_agent']!=0){
                if($product['commissionset']==1){//按比例
                    $commissiondata = json_decode($product['commissiondata1'],true);
                    if($commissiondata){
                        $commission = $commissiondata[$userlevel['id']]['commission1'] * ($product['sell_price'] - ($sysset['fxjiesuantype']==2 ? $product['cost_price'] : 0)) * 0.01;
                    }
                }elseif($product['commissionset']==2){//按固定金额
                    $commissiondata = json_decode($product['commissiondata2'],true);
                    if($commissiondata){
                        $commission = $commissiondata[$userlevel['id']]['commission1'];
                    }
                }elseif($product['commissionset']==3) {//提成是积分
                    $commissiondata = json_decode($product['commissiondata3'],true);
                    if($commissiondata){
                        $commission = $commissiondata[$userlevel['id']]['commission1'];
                    }
                    $product['commission_desc'] = t('积分');
                }elseif($product['commissionset']==0){//按会员等级
                    if($userlevel['commissiontype']==1){ //固定金额按单
                        $commission = $userlevel['commission1'];
                    }else{
                        $commission = $userlevel['commission1'] * ($product['sell_price'] - ($sysset['fxjiesuantype']==2 ? $product['cost_price'] : 0)) * 0.01;
                    }
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
		$product['detail'] = \app\commons\System::initpagecontent($product['detail'],aid,mid,platform);
		$product['comment_starnum'] = floor($product['comment_score']);
		
		//促销活动
		$cuxiaolist = Db::name('restaurant_cuxiao')
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
				$cids = explode(',',$product['cid']);
				$clist = Db::name('restaurant_product_category')->where('pid','in',$categoryids)->select()->toArray();
				foreach($clist as $kc=>$vc){
					$categoryids[] = $vc['id'];
					$cate2 = Db::name('restaurant_product_category')->where('pid',$vc['id'])->find();
					$categoryids[] = $cate2['id'];
				}
				if(!array_intersect($cids,$categoryids)){
					continue;
				}
			}
			$newcxlist[] = $v;
		}
		//优惠券
		$couponlist = Db::name('coupon')->where('aid',aid)->where('bid',$product['bid'])->where('tolist',1)->where('type','5')->where("unix_timestamp(starttime)<=".time()." and unix_timestamp(endtime)>=".time())->order('sort desc')->select()->toArray();
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
				$cids = explode(',',$product['cid']);
				$clist = Db::name('restaurant_product_category')->where('id','in',$cids)->select()->toArray();
				foreach($clist as $kc=>$vc){
					if($vc['pid']){
						$cids[] = $vc['pid'];
						$cate2 = Db::name('restaurant_product_category')->where('id',$vc['pid'])->find();
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
				$v['yxqdate'] = strtotime($yxqtime[1]);
            }elseif($v['yxqtype'] == 2){
				$v['yxqdate'] = time() + 86400 * $v['yxqdate'];
            }elseif($v['yxqtype'] == 3) {
                //次日起计算有效期
                $v['yxqdate'] = strtotime(date('Y-m-d')) + 86400 * ($v['yxqdate'] + 1) - 1;
            }
            if($v['bid'] > 0){
                $v['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
            }
			$newcplist[] = $v;
		}

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['title'] = $product['name'];
		$rdata['shopset'] = $shopset;
		$rdata['isfavorite'] = $isfavorite;
		$rdata['product'] = $product;
		$rdata['business'] = $business;
		$rdata['commentlist'] = $commentlist;
		$rdata['commentcount'] = $commentcount;
		$rdata['cartnum'] = Db::name('restaurant_shop_cart')->where('aid',aid)->where('mid',mid)->sum('num');
		$rdata['cuxiaolist'] = $newcxlist;
		$rdata['couponlist'] = $newcplist;
		return $this->json($rdata);
	}
	//获取菜品详情
	public function getproductdetail(){
		$proid = input('param.id/d');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','=',$proid];
        $field = "id,pic,name,sales,market_price,sell_price,lvprice,lvprice_data,guigedata,status,ischecked,freighttype,start_time,end_time,start_hours,end_hours,commissionset,commissiondata1,commissiondata2,commissiondata3,sellpoint,status_week";
        $product = Db::name('restaurant_product')->field($field)->where($where)->find();
		if(!$product){
			return $this->json(['status'=>0,'msg'=>'菜品不存在']);
		}
        //判断星期几
        $week = date('w');
        if($week ==0)$week = 7;
        $status_week = explode(',',$product['status_week']);
        if(!in_array($week,$status_week)){
            return $this->json(['status'=>0,'msg'=>'今日不可点']);
        }
		$product = $this->formatproduct($product);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'菜品已下架']);
		if($product['ischecked']!=1) return $this->json(['status'=>0,'msg'=>'菜品未审核']);
		if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
			return $this->json(['status'=>0,'msg'=>'菜品未上架']);
		}
		if($product['status']==3){
			$start_time = strtotime(date('Y-m-d '.$product['start_hours']));
			$end_time = strtotime(date('Y-m-d '.$product['end_hours']));
			if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
				return $this->json(['status'=>0,'msg'=>'菜品未上架']);
			}
		}
		if($product['status']==2 || $product['status']==3) $product['status']=1;

		$gglist = Db::name('restaurant_product_guige')->where('product_id',$product['id'])->select()->toArray();
		$gglist = $this->formatgglist($gglist,$product['bid'],$product['lvprice']);

        $sysset = Db::name('admin_set')->where('aid',aid)->field('name,logo,desc,fxjiesuantype,tel,kfurl,gzts,ddbb')->find();
        $shopset = Db::name('shop_sysset')->where('aid',aid)->field('showjd,comment,showcommission,hide_sales')->find();

        $guigelist = array();
		foreach($gglist as $k=>$v){
            //预计佣金
            $commission = 0;
            $v['commission_desc'] = '元';
            //计算佣金
            if($this->member && $shopset['showcommission']==1 && $product['commissionset']!=-1){
                $userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
                if($userlevel['can_agent']!=0){
                    if($product['commissionset']==1){//按比例
                        $commissiondata = json_decode($product['commissiondata1'],true);
                        if($commissiondata){
                            $commission = $commissiondata[$userlevel['id']]['commission1'] * ($v['sell_price'] - ($sysset['fxjiesuantype']==2 ? $v['cost_price'] : 0)) * 0.01;
                        }
                    }elseif($product['commissionset']==2){//按固定金额
                        $commissiondata = json_decode($product['commissiondata2'],true);
                        if($commissiondata){
                            $commission = $commissiondata[$userlevel['id']]['commission1'];
                        }
                    }elseif($product['commissionset']==3) {//提成是积分
                        $commissiondata = json_decode($product['commissiondata3'],true);
                        if($commissiondata){
                            $commission = $commissiondata[$userlevel['id']]['commission1'];
                        }
                        $v['commission_desc'] = t('积分');
                    }elseif($product['commissionset']==4 && $product['lvprice']==1){//按价格差
                        $lvprice_data = json_decode($v['lvprice_data'],true);
                        $commission = array_shift($lvprice_data) - $v['sell_price'];
                        if($commission < 0) $commission = 0;
                    }elseif($product['commissionset']==0){//按会员等级
                        //fxjiesuantype 0按菜品价格,1按成交价格,2按销售利润
                        if($userlevel['commissiontype']==1){ //固定金额按单
                            $commission = $userlevel['commission1'];
                        }else{
                            $commission = $userlevel['commission1'] * ($v['sell_price'] - ($sysset['fxjiesuantype']==2 ? $v['cost_price'] : 0)) * 0.01;
                        }
                    }
                }
            }
            $v['commission'] = round($commission*100)/100;
			$guigelist[$v['ks']] = $v;
		}
		$guigedata = json_decode($product['guigedata'],true);
	
		$ggselected = [];
		foreach($guigedata as $v) {
			$ggselected[] = 0;
		}
		$ks = implode(',',$ggselected);
        $jialiaodata = [];
        //新加料
        $jldata = [];
        return $this->json(['status'=>1,'product'=>$product,'guigelist'=>$guigelist,'guigedata'=>$guigedata,'ggselected'=>$ggselected,'ks'=>$ks,'shopset' => $shopset,'jialiaodata' =>$jialiaodata,'jldata' =>$jldata]);
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
		$datalist = Db::name('restaurant_shop_comment')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
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
		$this->checklogin();
		$post = input('post.');
		$platform = platform;
		$page = '/restaurant/shop/product';
		$scene = 'id_'.$post['proid'].'-pid_'.$this->member['id'];
		//if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
		//	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
		//}
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','product')->where('platform',$platform)->order('id')->find();

//		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','restaurant_shop')->where('posterid',$posterset['id'])->find();
		if(true || !$posterdata){
			$product = Db::name('restaurant_product')->where('id',$post['proid'])->find();
			$product = $this->formatproduct($product);
			$sysset = Db::name('admin_set')->where('aid',aid)->find();
			$textReplaceArr = [
				'[头像]'=>$this->member['headimg'],
				'[昵称]'=>$this->member['nickname'],
				'[姓名]'=>$this->member['realname'],
				'[手机号]'=>$this->member['mobile'],
				'[商城名称]'=>$sysset['name'],
				'[商品名称]'=>$product['name'],
				'[商品销售价]'=>$product['sell_price'],
				'[商品市场价]'=>$product['market_price'],
				'[商品图片]'=>$product['pic'],
			];

			$poster = $this->_getposter(aid,$product['bid'],$platform,$posterset['content'],$page,$scene,$textReplaceArr);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $this->member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['type'] = 'product';
			$posterdata['poster'] = $poster;
            $posterdata['posterid'] = $posterset['id'];
			$posterdata['createtime'] = time();
//			Db::name('member_poster')->insert($posterdata);
		}
		return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
	}
	//购物车 
	public function cart(){
		//$this->checklogin();
		$gwcdata = [];
		$cartlist = Db::name('restaurant_shop_cart')->field('id,bid,proid,ggid,num')->where('aid',aid)->where('mid',mid)->order('createtime desc')->select()->toArray();
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
				$product = Db::name('restaurant_product')->where('aid',aid)->where('status','<>',0)->where('id',$gwc['proid'])->find();
				if(!$product){
					Db::name('restaurant_shop_cart')->where('aid',aid)->where('proid',$gwc['proid'])->delete();continue;
				}
				$guige = Db::name('restaurant_product_guige')->where('id',$gwc['ggid'])->find();
				if(!$guige){
					Db::name('restaurant_shop_cart')->where('aid',aid)->where('ggid',$gwc['ggid'])->delete();continue;
				}
                $guige = $this->formatguige($guige,$product['bid'],$product['lvprice']);
				$prolist[] = ['id'=>$gwc['id'],'checked'=>true,'product'=>$product,'guige'=>$guige,'num'=>$gwc['num']];
			}
			$newcartlist[$bid] = ['bid'=>$bid,'checked'=>true,'business'=>$business,'prolist'=>$prolist];
		}
		
		$rdata = [];
		$rdata['cartlist'] = array_values($newcartlist);
		$rdata['tjdatalist'] = [];
		return $this->json($rdata);
	}
	public function addcart(){
		$this->checklogin();
		$post = input('post.');
		$oldnum = 0;
		$num = intval($post['num']);
		$product = Db::name('restaurant_product')->where('aid',aid)->where('status','<>',0)->where('ischecked',1)->where('id',$post['proid'])->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
        //周几可点
        $week = date("w");
        if($week==0) $week = 7;
        $status_week = explode(',',$product['status_week']);
        if(!in_array($week,$status_week)){
            $order_day = \app\customs\Restaurant::getStatusWeek($status_week);
            if($order_day) $order_day = '，仅限'.$order_day;
            return $this->json(['status'=>0,'msg'=>'菜品今日不可点'.$order_day]);
        }
        if(!$post['ggid']){
			if($num > 0){
                $post['ggid'] = Db::name('restaurant_product_guige')->where('product_id',$post['proid'])->value('id');
                }else{
				$post['ggid'] = Db::name('restaurant_shop_cart')->where('aid',aid)->where('mid',mid)->where('proid',$post['proid'])->order('id desc')->value('ggid');
			}
		}
        if($post['ggid']){
            $guige =Db::name('restaurant_product_guige')->where('id',$post['ggid'])->find();
            if($guige['stock'] < $num || $guige['stock_daily']-$guige['sales_daily']<$num){
                return $this->json(['status'=>0,'msg'=>'库存不足']);
            }
        }
		if($num > 0 && $product['limit_per'] > 0){ //每单限购
			$hasnum = Db::name('restaurant_shop_cart')->where('aid',aid)->where('mid',mid)->where('proid',$post['proid'])->sum('num');
			if($hasnum + $num > $product['limit_per']){
				return $this->json(['status'=>0,'msg'=>'每单限购'.$product['limit_per'].'份']);
			}
		}
		if($product['limit_start'] > 0){ //有起售数量
			$hasnum = Db::name('restaurant_shop_cart')->where('aid',aid)->where('mid',mid)->where('proid',$post['proid'])->sum('num');
			if($num > 0){ // +
				if($hasnum + $num < $product['limit_start']) $num = $product['limit_start'] - $hasnum;
			}else{ // -
				if($hasnum + $num < $product['limit_start']) $num = -$hasnum;
			}
		}
		//新增条件 加料判断
        $g_where[]= ['aid','=',aid];
        $g_where[]= ['mid','=',mid];
        $g_where[]= ['proid','=',$post['proid']];
        $g_where[]= ['ggid','=',$post['ggid']];
        
        $gwc = Db::name('restaurant_shop_cart')->where($g_where)->find();
		if($gwc) $oldnum = $gwc['num'];

		if($oldnum + $num <=0){
			Db::name('restaurant_shop_cart')->where($g_where)->delete();
			$cartnum = Db::name('restaurant_shop_cart')->where('aid',aid)->where('mid',mid)->sum('num');
			return $this->json(['status'=>1,'msg'=>'移除成功','cartnum'=>$cartnum]);
		}
		if($gwc){
			Db::name('restaurant_shop_cart')->where($g_where)->inc('num',$num)->update();
            }else{
			$data = [];
			$data['aid'] = aid;
			$data['bid'] = $product['bid'];
			$data['mid'] = mid;
			$data['ggid'] = $post['ggid']?$post['ggid']:0;
			$data['createtime'] = time();
			$data['proid'] = $post['proid'];
			$data['num'] = $num;
			$data['num'] = $num;
            Db::name('restaurant_shop_cart')->insert($data);
		}
		$cartnum = Db::name('restaurant_shop_cart')->where('aid',aid)->where('mid',mid)->sum('num');
		return $this->json(['status'=>1,'msg'=>'加入购物车成功','cartnum'=>$cartnum]);
	}
	public function cartChangenum(){
		$this->checklogin();
		$id = input('post.id/d');
		$num = input('post.num/d');
		if($num < 1) $num = 1;
		Db::name('restaurant_shop_cart')->where('id',$id)->where('mid',mid)->update(['num'=>$num]);
		return $this->json(['status'=>1,'msg'=>'修改成功']);
	}
	public function cartdelete(){
		$this->checklogin();
		$id = input('post.id/d');
		if(!$id){
			$bid = input('post.bid/d');
			Db::name('restaurant_shop_cart')->where('bid',$bid)->where('mid',mid)->delete();
			return $this->json(['status'=>1,'msg'=>'删除成功']);
		}
		Db::name('restaurant_shop_cart')->where('id',$id)->where('mid',mid)->delete();
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}
	public function cartclear(){
		$bid = input('param.bid');
		if(!$bid) $bid = 0;
		Db::name('restaurant_shop_cart')->where('aid',aid)->where('bid',$bid)->where('mid',mid)->delete();
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}
	
	//获取促销信息
	public function getcuxiaoinfo(){
		$id = input('post.id/d');
		$info = Db::name('restaurant_cuxiao')->where('id',$id)->where('aid',aid)->find();
		if(!$info){
			return $this->json(['status'=>0,'msg'=>'获取失败']);
		}
		$proinfo = false;
		$gginfo = false;
		if(($info['type'] == 2 || $info['type'] == 3) && $info['proid']){
			$proinfo = Db::name('restaurant_product')->field('id,name,pic,sell_price')->where('aid',aid)->where('id',$info['proid'])->find();
			$gginfo = Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$info['ggid'])->find();
		}
		return $this->json(['status'=>1,'info'=>$info,'product'=>$proinfo,'guige'=>$gginfo]);
	}
	//订单提交页
	public function buy(){
		$this->checklogin();
		$prodata = explode('-',input('param.prodata'));
		$tableid = input('param.tableId');
		$frompage = input('param.frompage');
		$is_bar_table =0;
		if($is_bar_table==0){
            if(!$tableid) return $this->json(['status'=>0,'msg'=>'请先扫描桌台二维码']);
        }

		$tableinfo = Db::name('restaurant_table')->where('id',$tableid)->find();
        
		//桌子有订单号时,并且订单内有菜，为加菜
        $order = [];
        $is_check_table = true;
        if($is_check_table){
            if($tableinfo['orderid']) {
                $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('id', $tableinfo['orderid'])->find();
                if($order){
                    if($order['status'] > 0)
                        return $this->json(['status'=>0,'msg'=>'当前桌台存在未结束的订单，请联系服务员清台后重新下单']);
                    $order['goods_count'] = Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('orderid', $order['id'])->count();
                }else{
                    //订单不存在 清台
                    Db::name('restaurant_table')->where('aid',aid)->where('id',$tableid)->update(['status' => 0, 'orderid' => 0]);
                }
            } 
        }
      
         if($order && ($order['status'] == 0 || $frompage == 'admin')){
             $ordertype = 'edit_order';
             } else{
             $ordertype = 'create_order';
         }
//        $ordertype = $order && ($order['status'] == 0 || $frompage == 'admin') ? 'edit_order' : 'create_order';//加菜，点菜
		                                                                                                    
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
		$autofahuo = 0;
		foreach($prodata as $key=>$gwc){
			list($proid,$ggid,$num,$carid) = explode(',',$gwc);
            $field = "id,aid,bid,cid,pic,name,sales,status_week,market_price,sell_price,lvprice,lvprice_data,freightdata,limit_per,scored_set,scored_val,status,start_time,end_time,start_hours,end_hours";
            $product = Db::name('restaurant_product')->field($field)->where('aid',aid)->where('ischecked',1)->where('id',$proid)->find();
			if(!$product){
				Db::name('restaurant_shop_cart')->where('aid',aid)->where('proid',$proid)->delete();
				return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
			}
			if($product['status']==0){
				return $this->json(['status'=>0,'msg'=>'菜品未上架']);
			}
			if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
				return $this->json(['status'=>0,'msg'=>'菜品未上架']);
			}
			if($product['status']==3){
				$start_time = strtotime(date('Y-m-d '.$product['start_hours']));
				$end_time = strtotime(date('Y-m-d '.$product['end_hours']));
				if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
					return $this->json(['status'=>0,'msg'=>'菜品未上架']);
				}
			}

            //周几可点
            $week = date("w");
            if($week==0) $week = 7;
            $status_week = explode(',',$product['status_week']);
            if(!in_array($week,$status_week)){
                $order_day = \app\customs\Restaurant::getStatusWeek($status_week);
                if($order_day) $order_day = '，仅限'.$order_day;
                return $this->json(['status'=>0,'msg'=>'['.$product['name'].']今日不可点'.$order_day]);
            }
			if($product['limit_per'] > 0 && $num > $product['limit_per']){ //每单限购
				return $this->json(['status'=>0,'msg'=>$product['name'].'每单限购'.$product['limit_per'].'份']);
			}
			if($product['limit_start'] > 0 && $num < $product['limit_start']){ //起售份数
				return $this->json(['status'=>0,'msg'=>$product['name'].'最低购买'.$product['limit_start'].'份']);
			}
			if($product['limit_start'] > 0){ //有起售数量
				$hasnum = Db::name('restaurant_shop_cart')->where('aid',aid)->where('mid',mid)->where('proid',$gwc['proid'])->sum('num');
				if($num > 0){ // +
					if($hasnum + $num < $product['limit_start']) $num = $product['limit_start'] - $hasnum;
				}else{ // -
					if($hasnum + $num < $product['limit_start']) $num = -$hasnum;
				}
			}
           
			$guige = Db::name('restaurant_product_guige')->where('id',$ggid)->find();
            $deletegg = 1;
            if(!$guige && $deletegg){
				Db::name('restaurant_shop_cart')->where('aid',aid)->where('ggid',$ggid)->delete();
				return $this->json(['status'=>0,'msg'=>'产品该规格不存在或已下架']);
			}
            $is_check_stock = 1;
            if($is_check_stock){
                if($guige['stock'] < $num || $guige['stock_daily']-$guige['sales_daily']<$num){
                    return $this->json(['status'=>0,'msg'=>'库存不足']);
                }
            }
		
			//$gettj = explode(',',$product['gettj']);
			//if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj) && (!in_array('0',$gettj) || $this->member['subscribe']!=1)){ //不是所有人
			//	if(!$product['gettjtip']) $product['gettjtip'] = '没有权限购买该菜品';
			//	return $this->json(['status'=>0,'msg'=>$product['gettjtip'],'url'=>$product['gettjurl']]);
			//}
			if($product['limit_per'] > 0){
				if($num > $product['limit_per']){
					return $this->json(['status'=>0,'msg'=>'每单限购'.$product['limit_per'].'件']);
				}
			}
            $guige = $this->formatguige($guige,$product['bid'],$product['lvprice']);

            if($product['bid']>0){
                //判断商家是否能自主修改积分设置
                $business_selfscore = 0;
                if(getcustom('business_selfscore') || getcustom('business_score_jiesuan')){
                    $business_selfscore = Db::name('business_sysset')->where('aid',aid)->value('business_selfscore');
                }
                $bcansetscore = false;//商家能否修改积分
                if(!$business_selfscore && !$bcansetscore){
                    $product['scored_set'] = 0;
                }
            }
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
            $jldata = ['jlprice' =>0,'jltitle' =>''];
			$allbuydata[$product['bid']]['prodata'][] = ['product'=>$product,'guige'=>$guige,'num'=>$num,'jldata' =>$jldata,'carid' => $carid];
		}
//    print_r($allbuydata);exit;
		$userinfo['scoredkmaxmoney'] = round($scoredkmaxmoney,2);
		
		$allproduct_price = 0;
		foreach($allbuydata as $bid=>$buydata){
			if($bid!=0){
				$business = Db::name('business')->where('id',$bid)->field('id,aid,cid,name,logo,tel,address,sales,longitude,latitude,start_hours,end_hours,is_open')->find();
                if($business['is_open']==0) return $this->json(['status'=>-4,'msg'=>$business['name'].'未营业']);
				if($business['start_hours'] != $business['end_hours']){
					$start_time = strtotime(date('Y-m-d '.$business['start_hours']));
					$end_time = strtotime(date('Y-m-d '.$business['end_hours']));
					if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
						return $this->json(['status'=>0,'msg'=>'商家不在营业时间']);
					}
				}
			}else{
				$business = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,desc,tel')->find();
			}
			
			$shop_set = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',$bid)->find();
			if($shop_set['status']==0){
				return $this->json(['status'=>0,'msg'=>'商家未开启点餐']);
			}
			if($shop_set['start_hours'] != $shop_set['end_hours']){
				$start_time = strtotime(date('Y-m-d '.$shop_set['start_hours']));
				$end_time = strtotime(date('Y-m-d '.$shop_set['end_hours']));
				if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
					return $this->json(['status'=>0,'msg'=>'商家不在点餐时间']);
				}
			}
			
			$product_price = 0;
			$needzkproduct_price = 0;
			$notcuxiao_price = 0;//不需要促销的金额
            $notcoupon_price = 0; //不需要优惠券的金额
            $notcuxiao_num = 0;//不需要促销的数量
			$totalweight = 0;
			$totalnum = 0;
			$prodataArr = [];
			$proids = [];
			$cids = [];
			foreach($buydata['prodata'] as $prodata){
                $product_price += $prodata['guige']['sell_price']  * $prodata['num'];
                if($prodata['product']['lvprice']==0){ //未开启会员价
					$needzkproduct_price += $prodata['guige']['sell_price'] * $prodata['num'];
				}
                $totalweight += $prodata['guige']['weight'] * $prodata['num'];
				$totalnum += $prodata['num'];
				$prodataArr[] = $prodata['product']['id'].','.$prodata['guige']['id'].','.$prodata['num'].','.$prodata['carid'];
				$proids[] = $prodata['product']['id'];
				$cids = array_merge(explode(',',$prodata['product']['cid']),$cids);
			}
			$prodatastr = implode('-',$prodataArr);
			
			$leveldk_money = 0;
			if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
				$leveldk_money = $needzkproduct_price * (1 - $userlevel['discount'] * 0.1);
			}
			$leveldk_money = round($leveldk_money,2);
			$price = $product_price - $leveldk_money;
			if($ordertype == 'create_order') {
                //满减活动
                $mjset = Db::name('manjian_set')->where('aid',aid)->find();
                if($mjset && $mjset['status']==1){
                    $mjdata = json_decode($mjset['mjdata'],true);
                }else{
                    $mjdata = array();
                }
                $manjian_money = 0;
                $moneyduan = 0;
                if($mjdata){
                    foreach($mjdata as $give){
                        if(($product_price - $leveldk_money)*1 >= $give['money']*1 && $give['money']*1 > $moneyduan){
                            $moneyduan = $give['money']*1;
                            $manjian_money = $give['jian']*1;
                        }
                    }
                }
                if($manjian_money > 0){
                    $allbuydata[$bid]['manjian_money'] = round($manjian_money,2);
                }else{
                    $allbuydata[$bid]['manjian_money'] = 0;
                }

                $newcouponlist = [];
                $couponwhere = [];
                $couponwhere[] = ['aid','=',aid];
                $couponwhere[] = ['mid','=',mid];
                $couponwhere[] = ['status','=',0];
                $couponwhere[] = ['minprice','<=',$price - $manjian_money - $notcoupon_price];
                $couponwhere[] = ['starttime','<=',time()];
                $couponwhere[] = ['endtime','>',time()];
                $coupontype = [1,4,5];
                if(getcustom('restaurant_cashdesk_discount_coupon'))$coupontype[] = 10;//折扣券
                $couponwhere[] = ['type','in',$coupontype];
                $couponList = Db::name('coupon_record')
                    ->where("bid=-1 or bid=".$bid)
                    ->where($couponwhere)
                    ->order('id desc')->select()->toArray();
                if(!$couponList) $couponList = [];
                foreach($couponList as $k=>$v){
                    //$couponList[$k]['starttime'] = date('m-d H:i',$v['starttime']);
                    //$couponList[$k]['endtime'] = date('m-d H:i',$v['endtime']);
                    $couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$v['couponid'])->find();
                    if(empty($couponinfo)) continue;
                    if($couponinfo['fwscene']!==0){
                        continue;
                    }

                    $v['thistotalprice'] = $price - $manjian_money;
                    $v['couponmoney'] = $v['money'];//可抵扣金额
                    if($couponinfo['fwtype']==2){//指定菜品可用
                        $productids = explode(',',$couponinfo['productids']);
                        if(!array_intersect($proids,$productids)){
                            continue;
                        }
                        $thistotalprice = 0;
                        foreach($buydata['prodata'] as $k2=>$v2){
                            $product = $v2['product'];
                            if(in_array($product['id'],$productids)){
                                $thistotalprice += $v2['guige']['sell_price'] * $v2['num'];
                            }
                        }
                        if($thistotalprice < $v['minprice']){
                            continue;
                        }
                        $v['thistotalprice'] = $thistotalprice;
                        $v['couponmoney'] = min($thistotalprice,$v['money']);//可抵扣金额
                    }

                    if($couponinfo['fwtype']==1){//指定类目可用
                        $categoryids = explode(',',$couponinfo['categoryids']);
                        $clist = Db::name('restaurant_product_category')->where('pid','in',$categoryids)->select()->toArray();
                        foreach($clist as $kc=>$vc){
                            $categoryids[] = $vc['id'];
                            $cate2 = Db::name('restaurant_product_category')->where('pid',$vc['id'])->find();
                            $categoryids[] = $cate2['id'];
                        }
                        if(!array_intersect($cids,$categoryids)){
                            continue;
                        }
                        $thistotalprice = 0;
                        foreach($buydata['prodata'] as $k2=>$v2){
                            $product = $v2['product'];
                            if(array_intersect(explode(',',$product['cid']),$categoryids)){
                                $thistotalprice += $v2['guige']['sell_price'] * $v2['num'];
                            }
                        }
                        if($thistotalprice < $v['minprice']){
                            continue;
                        }
                        $v['thistotalprice'] = $thistotalprice;
                        $v['couponmoney'] = min($thistotalprice,$v['money']);//可抵扣金额
                    }
                    $newcouponlist[] = $v;
                }
                $couponList = $newcouponlist;

                //促销活动
                $swhere = '';
                $totalnum = $totalnum - $notcuxiao_num;
                $cuxiao_price =  $price - $manjian_money - $notcuxiao_price;
                $cuxiaolist = Db::name('restaurant_cuxiao')->where('aid',aid)->where('bid',$bid)->where("(type in (1,2,3,4) and minprice<=".($cuxiao_price).") or ((type=5 or type=6) and minnum<=".$totalnum.") ".$swhere)->where('starttime','<',time())->where('endtime','>',time())->order('sort desc')->select()->toArray();
                $newcxlist = [];
                foreach($cuxiaolist as $k=>$v){
                    $gettj = explode(',',$v['gettj']);
                    if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
                        continue;
                    }
                    if($v['fwtype']==2){//指定菜品可用
                        $productids = explode(',',$v['productids']);
                        if(!array_intersect($proids,$productids)){
                            continue;
                        }
                        if($v['type']==1 || $v['type']==2 || $v['type']==3 || $v['type']==4){//指定菜品是否达到金额要求
                            $cuxiao_product_total = 0;
                            foreach($buydata['prodata'] as $vpro){
                                if(in_array($vpro['product']['id'], $productids)){
                                    $this_cuxiao_product_total = $vpro['guige']['sell_price'] * $vpro['num'];
                                    $cuxiao_product_total += $this_cuxiao_product_total;
                                }
                            }
                            if($cuxiao_product_total < $v['minprice']){
                                continue;
                            }
                        }
                        if($v['type']==6 || $v['type']==5){//指定菜品是否达到件数要求
                            $thistotalnum = 0;
                            foreach($buydata['prodata'] as $vpro){
                                if(in_array($vpro['product']['id'], $productids)){
                                    $thistotalnum += $vpro['num'];
                                }
                            }
                            if($thistotalnum < $v['minnum']){
                                continue;
                            }
                        }
                    }
                    if($v['fwtype']==1){//指定类目可用
                        $categoryids = explode(',',$v['categoryids']);
                        $clist = Db::name('restaurant_product_category')->where('pid','in',$categoryids)->select()->toArray();
                        foreach($clist as $kc=>$vc){
                            $categoryids[] = $vc['id'];
                            $cate2 = Db::name('restaurant_product_category')->where('pid',$vc['id'])->find();
                            $categoryids[] = $cate2['id'];
                        }
                        if(!array_intersect($cids,$categoryids)){
                            continue;
                        }
                        if($v['type']==1 || $v['type']==2 || $v['type']==3 || $v['type']==4){//指定菜品是否达到金额要求
                            $cuxiao_cate_total = 0;
                            foreach($buydata['prodata'] as $vpro){
                                $cuxiao_pro_cidArr = explode(',',$vpro['product']['cid']);
                                if(array_intersect($cuxiao_pro_cidArr, $categoryids)){
                                    $cuxiao_cate_total += $vpro['guige']['sell_price'] * $vpro['num'];
                                }
                            }
                            if($cuxiao_cate_total < $v['minprice']){
                                continue;
                            }
                        }
                        if($v['type']==6 || $v['type']==5){//指定类目内菜品是否达到件数要求
                            $thistotalnum = 0;
                            foreach($buydata['prodata'] as $vpro){
                                $cuxiao_pro_cidArr = explode(',',$vpro['product']['cid']);
                                if(array_intersect($cuxiao_pro_cidArr, $categoryids)){
                                    $thistotalnum += $vpro['num'];
                                }
                            }
                            if($thistotalnum < $v['minnum']){
                                continue;
                            }
                        }
                    }
                    $newcxlist[] = $v;
                }

                $allbuydata[$bid]['couponList'] = $couponList;
                $allbuydata[$bid]['couponCount'] = count($couponList);
                $allbuydata[$bid]['tea_fee'] = $shop_set['tea_fee_status']==1 ? round($shop_set['tea_fee'],2) : 0;
                $allbuydata[$bid]['tea_fee_text'] = $shop_set['tea_fee_text'];
                $allbuydata[$bid]['coupon_money'] = 0;
                $allbuydata[$bid]['coupontype'] = 1;
                $allbuydata[$bid]['couponrid'] = 0;
                $allbuydata[$bid]['cuxiaolist'] = $newcxlist;
                $allbuydata[$bid]['cuxiaoCount'] = count($newcxlist);
                $allbuydata[$bid]['cuxiao_money'] = 0;
                $allbuydata[$bid]['cuxiaotype'] = 0;
                $allbuydata[$bid]['cuxiaoid'] = 0;
                $allbuydata[$bid]['renshu'] = 0;
            } elseif($ordertype == 'edit_order') {
                $allbuydata[$bid]['coupon_money'] = 0;
                $allbuydata[$bid]['cuxiao_money'] = 0;
                $allbuydata[$bid]['tea_fee'] = 0;
                $allbuydata[$bid]['manjian_money'] = 0;
                $allbuydata[$bid]['renshu'] = 0;
            }

			$allbuydata[$bid]['bid'] = $bid;
			$allbuydata[$bid]['business'] = $business;
			$allbuydata[$bid]['prodatastr'] = $prodatastr;
			$allbuydata[$bid]['product_price'] = round($product_price,2);
			$allbuydata[$bid]['not_cuxiao_price'] = round($notcuxiao_price,2);
			$allbuydata[$bid]['not_coupon_price'] = round($notcoupon_price,2);
            $allbuydata[$bid]['leveldk_money'] = $leveldk_money;
            $allbuydata[$bid]['message'] = '';
            $allbuydata[$bid]['field1'] = '';
            $allbuydata[$bid]['field2'] = '';
            $allbuydata[$bid]['field3'] = '';
            $allbuydata[$bid]['field4'] = '';
            $allbuydata[$bid]['field5'] = '';
			$allproduct_price += $product_price;
		}
      
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['linkman'] = $userinfo['realname'] ? strval($userinfo['realname']) : strval($userinfo['nickname']);
		$rdata['tel'] = strval($userinfo['tel']);
		$rdata['userinfo'] = $userinfo;
		$rdata['allbuydata'] = $allbuydata;
		$rdata['scorebdkyf'] = Db::name('admin_set')->where('aid',aid)->value('scorebdkyf');
		$rdata['order'] = $order ? $order : [];
        $rdata['ordertype'] = $ordertype;
		$rdata['tableinfo'] = $tableinfo;
        $rdata['is_bar_table'] = $is_bar_table;
        $change_people_number = 1;
        $rdata['change_people_number'] = $change_people_number;
		return $this->json($rdata);
	}
	public function createOrder(){
        $post = input('post.');
        if($post['frompage'] != 'admin') {
            //非管理员点餐验证登录
            $this->checklogin();
        }
        $buydata = $post['buydata'];
        $shop_sysset = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',$buydata[0]['bid'])->find();
        $mdid = input('param.mdid',0);
        
        $is_bar_table =0;
        if(empty($post['tableid']) && $is_bar_table==0) {
            return $this->json(['status'=>0,'msg'=>'请先扫描桌台二维码或选择桌台']);
        }

		//todo 一个桌子同时只能点一单
        $table = Db::name('restaurant_table')->where('aid',aid)->where('id', $post['tableid'])->find();
		//是否校验桌台
        $is_check_table = true;
        if($is_check_table){
            if($table) {
                if($table['orderid']) {
                    //修改菜品
                    return $this->json(['status'=>-4,'msg'=>'当前桌台已存在订单，请重新下单','url'=>'/restaurant/shop/index?tableId='.$post['tableid'].'&bid='.$buydata[0]['bid']]);
                }
            }else{
                if($is_bar_table==0){
                    return $this->json(['status'=>0,'msg'=>'桌台数据异常，请联系服务员']);
                }
            }
        }

        $sysset = Db::name('admin_set')->where('aid',aid)->find();
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();

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
		$alltotalprice = 0;
		$alltotalscore = 0;
		foreach($buydata as $data){
			$i++;
			$bid = $data['bid'];
			if($data['prodata']){
				$prodata = explode('-',$data['prodata']);
			}else{
				return $this->json(['status'=>0,'msg'=>'产品数据错误']);
			}
			if($bid!=0){
				$business = Db::name('business')->where('id',$bid)->field('id,aid,cid,name,logo,tel,address,sales,longitude,latitude,start_hours,end_hours,is_open')->find();
                if($business['is_open']==0) return $this->json(['status'=>-4,'msg'=>$business['name'].'未营业']);
                if($business['start_hours'] != $business['end_hours']){
					$start_time = strtotime(date('Y-m-d '.$business['start_hours']));
					$end_time = strtotime(date('Y-m-d '.$business['end_hours']));
					if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
						return $this->json(['status'=>0,'msg'=>'商家不在营业时间']);
					}
				}
			}
			$shop_set = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',$bid)->find();
			if($shop_set['status']==0){
				return $this->json(['status'=>0,'msg'=>'该商家未开启点餐']);
			}
			if($shop_set['start_hours'] != $shop_set['end_hours']){
				$start_time = strtotime(date('Y-m-d '.$shop_set['start_hours']));
				$end_time = strtotime(date('Y-m-d '.$shop_set['end_hours']));
				if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
					return $this->json(['status'=>0,'msg'=>'该商家不在点餐时间']);
				}
			}

			$product_price = 0;
			$needzkproduct_price = 0;
            $notcuxiao_price = 0;//不需要促销的金额
            $notcoupon_price = 0; //不需要优惠券的金额
			$givescore = 0; //奖励积分
			$totalweight = 0;//重量
			$totalnum = 0;
			$prolist = [];
			$proids = [];
			$cids = [];
			foreach($prodata as $key=>$pro){
				$sdata = explode(',',$pro);
				$sdata[2] = intval($sdata[2]);
				if($sdata[2] <= 0) return $this->json(['status'=>0,'msg'=>'购买数量有误']);
				$product = Db::name('restaurant_product')->where('aid',aid)->where('ischecked',1)->where('bid',$bid)->where('id',$sdata[0])->find();
				if(!$product) return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
				
				if($product['status']==0){
					return $this->json(['status'=>0,'msg'=>'菜品未上架']);
				}
				if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
					return $this->json(['status'=>0,'msg'=>'菜品未上架']);
				}
				if($product['status']==3){
					$start_time = strtotime(date('Y-m-d '.$product['start_hours']));
					$end_time = strtotime(date('Y-m-d '.$product['end_hours']));
					if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
						return $this->json(['status'=>0,'msg'=>'菜品未上架']);
					}
				}
				
				if($key==0) $title = $product['name'];

				$guige = Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$sdata[1])->find();
                $cartdata = Db::name('restaurant_shop_cart')->where('id',$sdata[3]) ->find();
                $deletegg = 1;
                if(!$guige && $deletegg) return $this->json(['status'=>0,'msg'=>'产品规格不存在或已下架']);
                $is_check_stock = 1;
                if($is_check_stock){
                    if($guige['stock'] < $sdata[2] || $guige['stock_daily']-$guige['sales_daily']<$sdata[2]){
                        return $this->json(['status'=>0,'msg'=>'库存不足']);
                    }
                }
				//$gettj = explode(',',$product['gettj']);
				//if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj) && (!in_array('0',$gettj) || $this->member['subscribe']!=1)){ //不是所有人
				//	if(!$product['gettjtip']) $product['gettjtip'] = '没有权限购买该菜品';
				//	return $this->json(['status'=>0,'msg'=>$product['gettjtip'],'url'=>$product['gettjurl']]);
				//}
				if($product['limit_per'] > 0 && $sdata[2] > $product['limit_per']){ //每单限购
					return $this->json(['status'=>0,'msg'=>$product['name'].'每单限购'.$product['limit_per'].'份']);
				}
				if($product['limit_start'] > 0 && $sdata[2] < $product['limit_start']){ //起售份数
					return $this->json(['status'=>0,'msg'=>$product['name'].'最低购买'.$product['limit_start'].'份']);
				}

                $guige = $this->formatguige($guige,$product['bid'],$product['lvprice']);
               
				if($product['lvprice']==0){ //未开启会员价
					$needzkproduct_price += $guige['sell_price'] * $sdata[2];
				}
                $cartid  =  intval($sdata[3]);//购物车
                $totalweight += $guige['weight'] * $sdata[2];
				$totalnum += $sdata[2];
			    
                if($product['bid']>0){
                    //判断商家是否能自主修改积分设置
                    $business_selfscore = 0;
                    if(getcustom('business_selfscore') || getcustom('business_score_jiesuan')){
                        $business_selfscore = Db::name('business_sysset')->where('aid',aid)->value('business_selfscore');
                    }
                    $bcansetscore = false;//商家能否修改积分
                    if(!$business_selfscore && !$bcansetscore){
                        $product['scored_set'] = 0;
                    }
                }

				if($product['scored_set']==0){
					if($sysset['scoredkmaxpercent'] == 0){
						$scoremaxtype = 1;
						$scoredkmaxmoney += 0;
					}else{
						if($sysset['scoredkmaxpercent'] >= 0 && $sysset['scoredkmaxpercent']<100){
							$scoredkmaxmoney += $sysset['scoredkmaxpercent'] * 0.01 * $guige['sell_price'] * $sdata[2];
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
                $product_price += $guige['sell_price'] * $sdata[2];
				$prolist[] = ['product'=>$product,'guige'=>$guige,'num'=>$sdata[2],'cartid'=>$cartid];
				
				$proids[] = $product['id'];
				$cids = array_merge($cids,explode(',',$product['cid']));
				$givescore += $guige['givescore'] * $sdata[2];
			}
			
			//会员折扣
			$leveldk_money = 0;
			if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
				$leveldk_money = $needzkproduct_price * (1 - $userlevel['discount'] * 0.1);
			}
		
			$totalprice = $product_price - $leveldk_money;
         
			//满减活动
			$mjset = Db::name('manjian_set')->where('aid',aid)->find();
			if($mjset && $mjset['status']==1){
				$mjdata = json_decode($mjset['mjdata'],true);
			}else{
				$mjdata = array();
			}
			$manjian_money = 0;
			$moneyduan = 0;
			if($mjdata){
				foreach($mjdata as $give){
					if($totalprice*1 >= $give['money']*1 && $give['money']*1 > $moneyduan){
						$moneyduan = $give['money']*1;
						$manjian_money = $give['jian']*1;
					}
				}
			}
			if($manjian_money <= 0) $manjian_money = 0;
			$totalprice = $totalprice - $manjian_money;
			if($totalprice < 0) $totalprice = 0;
			//优惠券
			if($data['couponrid'] > 0){
                $coupontotal = $totalprice - $notcoupon_price;
				$couponrid = $data['couponrid'];
				$couponrecord = Db::name('coupon_record')->where("bid=-1 or bid=".$data['bid'])->where('aid',aid)->where('mid',mid)->where('id',$couponrid)->find();
				$coupon_type = [1,4,5];
                if(getcustom('restaurant_cashdesk_discount_coupon'))$coupon_type[] = 10;//折扣券
				if(!$couponrecord){
					return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不存在']);
				}elseif($couponrecord['status']!=0){
					return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'已使用过了']);
				}elseif($couponrecord['starttime'] > time()){
					return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'尚未开始使用']);	
				}elseif($couponrecord['endtime'] < time()){
					return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'已过期']);	
				}elseif($couponrecord['minprice'] > $coupontotal){
					return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
				}elseif(!in_array($couponrecord['type'],$coupon_type)){
					return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
				}

				$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$couponrecord['couponid'])->find();
                if(empty($couponinfo)){
                    return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不存在或已作废']);
                }
                if($couponinfo['fwscene']!==0){
                    return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合使用条件']);
                }
                
				if($couponinfo['fwtype']==2){//指定菜品可用
					$productids = explode(',',$couponinfo['productids']);
					if(!array_intersect($proids,$productids)){
						return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'指定菜品可用']);
					}
                    $thistotalprice = 0;
                    foreach($prolist as $k2=>$v2){
                        $product = $v2['product'];
                        if(in_array($product['id'],$productids)){
                            $thistotal =  $v2['guige']['sell_price'] * $v2['num'];
                            $thistotalprice += $thistotal;
                        }
                    }
                    if($thistotalprice < $couponinfo['minprice']){
                        return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'指定菜品未达到'.$couponinfo['minprice'].'元']);
                    }
                    $couponrecord['money'] = min($thistotalprice,$couponrecord['money']);
				}
				if($couponinfo['fwtype']==1){//指定类目可用
					$categoryids = explode(',',$couponinfo['categoryids']);
					$clist = Db::name('restaurant_product_category')->where('pid','in',$categoryids)->select()->toArray();
					foreach($clist as $kc=>$vc){
						$categoryids[] = $vc['id'];
						$cate2 = Db::name('restaurant_product_category')->where('pid',$vc['id'])->find();
						$categoryids[] = $cate2['id'];
					}
					if(!array_intersect($cids,$categoryids)){
						return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'指定分类可用']);
					}
                    $thistotalprice = 0;
                    foreach($prolist as $k2=>$v2){
                        $product = $v2['product'];
                        if(array_intersect(explode(',',$product['cid']),$categoryids)){
                            $thistotal =  $v2['guige']['sell_price'] * $v2['num'];
                            $thistotalprice += $thistotal;
                        }
                    }
                    if($thistotalprice < $couponinfo['minprice']){
                        return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'指定分类未达到'.$couponinfo['minprice'].'元']);
                    }
                    $couponrecord['money'] = min($thistotalprice,$couponrecord['money']);
				}

				Db::name('coupon_record')->where('id',$couponrid)->update(['status'=>1,'usetime'=>time()]);
				if($couponrecord['type']==4){//运费抵扣券
					$coupon_money = 0;
				}if($couponrecord['type']==10){//折扣券
                    $coupon_money = $totalprice * (100- $couponrecord['discount']) * 0.01;
                }else{
					$coupon_money = $couponrecord['money'];
					if($coupon_money > $totalprice) $coupon_money = $totalprice;
				}
			}else{
				$coupon_money = 0;
			}
			//促销活动
			if($data['cuxiaoid'] > 0){
			   
			    $cuxiaototal = $totalprice - $notcuxiao_price;
				$cuxiaoid = $data['cuxiaoid'];
				$cuxiaoinfo = Db::name('restaurant_cuxiao')->where("bid=-1 or bid=".$data['bid'])->where('aid',aid)->where('id',$cuxiaoid)->find();
                if(!$cuxiaoinfo){
					return $this->json(['status'=>0,'msg'=>'该促销活动不存在']);
				}elseif($cuxiaoinfo['starttime'] > time()){
					return $this->json(['status'=>0,'msg'=>'该促销活动尚未开始']);	
				}elseif($cuxiaoinfo['endtime'] < time()){
					return $this->json(['status'=>0,'msg'=>'该促销活动已结束']);	
				}elseif($cuxiaoinfo['type']!=5 && $cuxiaoinfo['type']!=6 && $cuxiaoinfo['type']!=7 && $cuxiaoinfo['minprice'] > $cuxiaototal){
					return $this->json(['status'=>0,'msg'=>'该促销活动不符合条件']);
				}elseif(($cuxiaoinfo['type']==5 || $cuxiaoinfo['type']==6) && $cuxiaoinfo['minnum'] > $totalnum){
					return $this->json(['status'=>0,'msg'=>'该促销活动不符合条件']);
				}
				if($cuxiaoinfo['fwtype']==2){//指定菜品可用
					$productids = explode(',',$cuxiaoinfo['productids']);
					if(!array_intersect($proids,$productids)){
						return $this->json(['status'=>0,'msg'=>'该促销活动指定菜品可用']);
					}
                    if($cuxiaoinfo['type']==1 || $cuxiaoinfo['type']==2 || $cuxiaoinfo['type']==3 || $cuxiaoinfo['type']==4){//指定菜品是否达到金额要求
                        //判断金额是否满足
                        $cuxiao_product_total = 0;
                        foreach($prolist as $vpro){
                            if(in_array($vpro['product']['id'], $productids)){
                                $this_cuxiao_product_total =$vpro['guige']['sell_price'] * $vpro['num'];
                                $cuxiao_product_total += $this_cuxiao_product_total;
                            }
                        }
                        if($cuxiao_product_total < $cuxiaoinfo['minprice']){
                            return $this->json(['status'=>0,'msg'=>$cuxiaoinfo['name'].'指定菜品总价未达到'.$cuxiaoinfo['minprice'].'元']);
                        }
                    }
                    if($cuxiaoinfo['type']==6 || $cuxiaoinfo['type']==5){//指定菜品是否达到件数要求
                        $thistotalnum = 0;
                        foreach($prolist as $vpro){
                            if(in_array($vpro['product']['id'], $productids)){
                                $thistotalnum += $vpro['num'];
                            }
                        }
                        if($thistotalnum < $cuxiaoinfo['minnum']){
                            return $this->json(['status'=>0,'msg'=>$cuxiaoinfo['name'].'指定菜品总数未达到'.$cuxiaoinfo['minnum'].'件']);
                        }
                    }
				}
				if($cuxiaoinfo['fwtype']==1){//指定类目可用
					$categoryids = explode(',',$cuxiaoinfo['categoryids']);
					$clist = Db::name('restaurant_product_category')->where('pid','in',$categoryids)->select()->toArray();
					foreach($clist as $kc=>$vc){
						$categoryids[] = $vc['id'];
						$cate2 = Db::name('restaurant_product_category')->where('pid',$vc['id'])->find();
						$categoryids[] = $cate2['id'];
					}
					if(!array_intersect($cids,$categoryids)){
						return $this->json(['status'=>0,'msg'=>'该促销活动指定分类可用']);
					}
                    if($cuxiaoinfo['type']==1 || $cuxiaoinfo['type']==2 || $cuxiaoinfo['type']==3 || $cuxiaoinfo['type']==4){//指定菜品是否达到金额要求
                        $cuxiao_cate_total = 0;
                        foreach($prolist as $vpro){
                            $cuxiao_pro_cidArr = explode(',',$vpro['product']['cid']);
                            if(array_intersect($cuxiao_pro_cidArr, $categoryids)){
                                $this_cuxiao_cate_total =  $vpro['guige']['sell_price'] * $vpro['num'];
                                $cuxiao_cate_total += $this_cuxiao_cate_total;
                            }
                        }
                        if($cuxiao_cate_total < $cuxiaoinfo['minprice']){
                            return $this->json(['status'=>0,'msg'=>$cuxiaoinfo['name'].'指定类目总价未达到'.$cuxiaoinfo['minprice'].'元']);
                        }
                    }
                    if($cuxiaoinfo['type']==6 || $cuxiaoinfo['type']==5){//指定类目内菜品是否达到件数要求
                        $thistotalnum = 0;
                        foreach($prolist as $vpro){
                            $cuxiao_pro_cidArr = explode(',',$vpro['product']['cid']);
                            if(array_intersect($cuxiao_pro_cidArr, $categoryids)){
                                $thistotalnum += $vpro['num'];
                            }
                        }
                        if($thistotalnum < $cuxiaoinfo['minnum']){
                            return $this->json(['status'=>0,'msg'=>$cuxiaoinfo['name'].'指定分类总数未达到'.$cuxiaoinfo['minnum'].'件']);
                        }
                    }
				}
				if($cuxiaoinfo['type']==1 || $cuxiaoinfo['type']==6){//满额立减 满件立减
					$manjian_money = $manjian_money + $cuxiaoinfo['money'];
					$cuxiaomoney = $cuxiaoinfo['money'] * -1;
				}elseif($cuxiaoinfo['type']==2){//满额赠送
					$cuxiaomoney = 0;
					$product = Db::name('restaurant_product')->where('aid',aid)->where('id',$cuxiaoinfo['proid'])->find();
					$guige = Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$cuxiaoinfo['ggid'])->find();
					if(!$product) return $this->json(['status'=>0,'msg'=>'赠送产品不存在']);
					if(!$guige) return $this->json(['status'=>0,'msg'=>'赠送产品规格不存在']);
					if($guige['stock'] < 1){
						return $this->json(['status'=>0,'msg'=>'赠送产品库存不足']);
					}
					$prolist[] = ['product'=>$product,'guige'=>$guige,'num'=>1,'isSeckill'=>0];
				}elseif($cuxiaoinfo['type']==3){//加价换购
					$cuxiaomoney = $cuxiaoinfo['money'];
					$product = Db::name('restaurant_product')->where('aid',aid)->where('id',$cuxiaoinfo['proid'])->find();
					$guige = Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$cuxiaoinfo['ggid'])->find();
					if(!$product) return $this->json(['status'=>0,'msg'=>'换购产品不存在']);
					if(!$guige) return $this->json(['status'=>0,'msg'=>'换购产品规格不存在']);
					if($guige['stock'] < 1){
						return $this->json(['status'=>0,'msg'=>'换购产品库存不足']);
					}
					$prolist[] = ['product'=>$product,'guige'=>$guige,'num'=>1,'isSeckill'=>0];
				}elseif($cuxiaoinfo['type']==4 || $cuxiaoinfo['type']==5){//满额打折 满件打折
					$cuxiaomoney = $cuxiaototal * (1 - $cuxiaoinfo['zhekou'] * 0.1);
					$manjian_money = $manjian_money + $cuxiaomoney;
					$cuxiaomoney = $cuxiaomoney * -1;
				}elseif($cuxiaoinfo['type']==7){
                    }else{
					$cuxiaomoney = 0;
				}
			}else{
				$cuxiaomoney = 0;
			}
			$totalprice = $totalprice - $coupon_money + $cuxiaomoney;
			$tea_fee = ($shop_set['tea_fee_status']==1 && $shop_set['tea_fee']>0 ? $shop_set['tea_fee'] * $data['renshu'] : 0);
			$totalprice = $totalprice + $tea_fee;

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
					$scoredkscore = intval($scoredk_money / $score2money);
				}
			}

            Log::write([
                'file'=>__FILE__.__LINE__,
                '餐饮点餐订单编辑,orderid：'=>$table['orderid'],
                'post'=>json_encode(input('post.')),
                'mid'=>mid
            ]);

			$orderdata = [];
//			dump($table);
//            if(!$table['orderid']) {
                $orderdata['aid'] = aid;
                $orderdata['mid'] = mid;
                $orderdata['bid'] = $data['bid'];
                $orderdata['tableid'] = input('param.tableid');
//            }
			if(count($buydata) > 1){
				$orderdata['ordernum'] = $ordernum.'_'.$i;
			}else{
				$orderdata['ordernum'] = $ordernum;
			}
			$orderdata['title'] = $title.(count($prodata)>1?'等':'');
			
//			$orderdata['linkman'] = $address['name'];
//			$orderdata['tel'] = $address['tel'];
//			$orderdata['area'] = $address['area'];
//			$orderdata['address'] = $address['address'];
//			$orderdata['longitude'] = $address['longitude'];
//			$orderdata['latitude'] = $address['latitude'];
//			$orderdata['area2'] = $address['province'].','.$address['city'].','.$address['district'];
			$orderdata['totalprice'] = $totalprice;
			$orderdata['product_price'] = $product_price;
			$orderdata['renshu'] = $data['renshu'];
			$orderdata['tea_fee'] = $tea_fee;
			$orderdata['leveldk_money'] = $leveldk_money;  //会员折扣
			$orderdata['manjian_money'] = $manjian_money;	//满减活动
			$orderdata['scoredk_money'] = $scoredk_money;	//积分抵扣
			$orderdata['coupon_money'] = $coupon_money;		//优惠券抵扣
			$orderdata['scoredkscore'] = $scoredkscore;	//抵扣掉的积分
            $orderdata['cuxiao_money'] = abs($cuxiaomoney);
            $orderdata['cuxiao_ids'] = $cuxiaoid;
			$orderdata['coupon_rid'] = $couponrid;
			$orderdata['freight_price'] = $freight_price; //运费
            if($orderdata['bid'] ==0){
                $orderdata['givescore'] = $givescore;
            }
		
			$orderdata['message'] = $data['message'];
			
			$orderdata['createtime'] = time();
			$orderdata['platform'] = platform;
			$orderdata['hexiao_code'] = random(16);
			$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=restaurant_shop&co='.$orderdata['hexiao_code']));
			$orderdata['field1'] = $data['field1'];
			$orderdata['field2'] = $data['field2'];
			$orderdata['field3'] = $data['field3'];
			$orderdata['field4'] = $data['field4'];
			$orderdata['field5'] = $data['field5'];
			//dd($orderdata); 
			if($table['orderid'] &&  $post['frompage'] == 'admin') {
                Db::name('restaurant_shop_order')->where('id', $table['orderid'])->update($orderdata);
                $orderid = $table['orderid'];
            } else {
                $orderid = Db::name('restaurant_shop_order')->insertGetId($orderdata);
            }
            $orderdata = Db::name('restaurant_shop_order')->where('id', $orderid)->find();
            if(!$orderdata){
            	 return $this->json(['status'=>0,'msg'=>'下单失败']);
            }
			$payorderid = \app\models\Payorder::createorder(aid,$orderdata['bid'],$orderdata['mid'],'restaurant_shop',$orderid,$orderdata['ordernum'],$orderdata['title'],$orderdata['totalprice'],$orderdata['scoredkscore']);
            //更新餐桌状态
            $is_update_table = true;
            if($is_update_table){
                Db::name('restaurant_table')->where('aid', aid)->where('id', $table['id'])->update(['status' => 2, 'orderid' => $orderid]);
            }
           
			$alltotalprice += $orderdata['totalprice'];
			$alltotalscore += $orderdata['scoredkscore'];
			
			$istc1 = 0; //设置了按单固定提成时 只将佣金计算到第一个菜品里
            $istc2 = 0;
            $istc3 = 0;
            $njlprice = 0;
            $njltitle = '';
			foreach($prolist as $key=>$v){
				$product = $v['product'];
				$guige = $v['guige'];
				$num = $v['num'];
				$ogdata = [];
				$ogdata['aid'] = aid;
				$ogdata['bid'] = $product['bid'];
				$ogdata['mid'] = mid;
				$ogdata['orderid'] = $orderid;
				$ogdata['ordernum'] = $orderdata['ordernum'];
				$ogdata['proid'] = $product['id'];
				$ogdata['name'] = $product['name'];
				$ogdata['pic'] = $guige['pic'] ? $guige['pic'] : $product['pic'];
				$ogdata['procode'] = $product['procode'];
				$ogdata['ggid'] = $guige['id'];
				$ogdata['ggname'] = $guige['name'];
				//$ogdata['cid'] = $product['cid'];
				$ogdata['num'] = $num;
				$ogdata['cost_price'] = $guige['cost_price'];
				$sell_price =  $guige['sell_price'];
				$ogdata['sell_price'] = $sell_price;
				$ogdata['status'] = 0;
				$ogdata['createtime'] = time();
                $og_totalprice = $num * $sell_price;
               
				$ogdata['totalprice'] =$og_totalprice;
                //计算菜品实际金额  菜品金额 - 会员折扣 - 积分抵扣 - 满减抵扣 - 优惠券抵扣
					if($sysset['fxjiesuantype'] == 1 || $sysset['fxjiesuantype'] == 2){
						$allproduct_price = $product_price;
						$og_leveldk_money = 0;
						$og_coupon_money = 0;
						$og_scoredk_money = 0;
						$og_manjian_money = 0;
						if($allproduct_price > 0 && $og_totalprice > 0){
							if($leveldk_money){
								$og_leveldk_money = $og_totalprice / $allproduct_price * $leveldk_money;
							}
							if($coupon_money){
								$og_coupon_money = $og_totalprice / $allproduct_price * $coupon_money;
							}
							if($scoredk_money){
								$og_scoredk_money = $og_totalprice / $allproduct_price * $scoredk_money;
							}
							if($manjian_money){
								$og_manjian_money = $og_totalprice / $allproduct_price * $manjian_money;
							}
						}
						$og_totalprice = round($og_totalprice - $og_leveldk_money - $og_coupon_money - $og_scoredk_money - $og_manjian_money,2);
						if($og_totalprice < 0) $og_totalprice = 0;
					}
					$ogdata['real_totalprice'] = $og_totalprice; //实际菜品销售金额
                    Log::write([
                        'file'=>__FILE__.__LINE__,
                        'real_totalprice：'=>$og_totalprice,
                        'mid'=>mid
                    ]);
					
					//计算佣金的菜品金额
					$commission_totalprice = $ogdata['totalprice']; 
					if($sysset['fxjiesuantype'] == 1){ //按成交价格
						$commission_totalprice = $ogdata['real_totalprice'];
                        if($commission_totalprice < 0) $commission_totalprice = 0;
					}
					if($sysset['fxjiesuantype']==2){ //按利润提成
						$commission_totalprice = $ogdata['real_totalprice'] - $guige['cost_price'] * $num;
						if($commission_totalprice < 0) $commission_totalprice = 0;
					}
                    if($commission_totalprice < 0) $commission_totalprice = 0;

					$agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
					if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
						$this->member['pid'] = mid;
					}
					if($product['commissionset']!=-1){
                        if($this->member['pid']){
                            $parent1 = \app\customs\Restaurant::getParentWithLevel(aid, $this->member['pid']);
                            if($parent1 && $parent1['levelData']['can_agent'] != 0){
                                $ogdata['parent1'] = $parent1['id'];
                            }
                        }
                        if($parent1['pid']){
                            $parent2 = \app\customs\Restaurant::getParentWithLevel(aid, $parent1['pid']);
                            if($parent2 && $parent2['levelData']['can_agent'] > 1){
                                $ogdata['parent2'] = $parent2['id'];
                            }
                        }
                        if($parent2['pid']){
                            $parent3 = \app\customs\Restaurant::getParentWithLevel(aid, $parent2['pid']);
                            if($parent3 && $parent3['levelData']['can_agent'] > 2){
                                $ogdata['parent3'] = $parent3['id'];
                            }
                        }
                        if($product['commissionset']==1){//按菜品设置的分销比例
                            $commissiondata = json_decode($product['commissiondata1'],true);
                            if($commissiondata){
                                if($ogdata['parent1']) $ogdata['parent1commission'] = $commissiondata[$parent1['levelData']['id']]['commission1'] * $commission_totalprice * 0.01;
                                if($ogdata['parent2']) $ogdata['parent2commission'] = $commissiondata[$parent2['levelData']['id']]['commission2'] * $commission_totalprice * 0.01;
                                if($ogdata['parent3']) $ogdata['parent3commission'] = $commissiondata[$parent3['levelData']['id']]['commission3'] * $commission_totalprice * 0.01;
                            }
                        }elseif($product['commissionset']==2){//按固定金额
                            $commissiondata = json_decode($product['commissiondata2'],true);
                            if($commissiondata){
                                if($ogdata['parent1']) $ogdata['parent1commission'] = $commissiondata[$parent1['levelData']['id']]['commission1'] * $num;
                                if($ogdata['parent2']) $ogdata['parent2commission'] = $commissiondata[$parent2['levelData']['id']]['commission2'] * $num;
                                if($ogdata['parent3']) $ogdata['parent3commission'] = $commissiondata[$parent3['levelData']['id']]['commission3'] * $num;
                            }
                        }elseif($product['commissionset']==3){//提成是积分
                            $commissiondata = json_decode($product['commissiondata3'],true);
                            if($commissiondata){
                                if($ogdata['parent1']) $ogdata['parent1score'] = $commissiondata[$parent1['levelData']['id']]['commission1'] * $num;
                                if($ogdata['parent2']) $ogdata['parent2score'] = $commissiondata[$parent2['levelData']['id']]['commission2'] * $num;
                                if($ogdata['parent3']) $ogdata['parent3score'] = $commissiondata[$parent3['levelData']['id']]['commission3'] * $num;
                            }
                        }else{ //按会员等级设置的分销比例
                            if($ogdata['parent1']){
                                if($parent1['levelData']['commissiontype']==1){ //固定金额按单
                                    if($istc1==0){
                                        $ogdata['parent1commission'] = $parent1['levelData']['commission1'];
                                        $istc1 = 1;
                                    }
                                }else{
                                    $ogdata['parent1commission'] = $parent1['levelData']['commission1'] * $commission_totalprice * 0.01;
                                }
                            }
                            if($ogdata['parent2']){
                                if($parent2['levelData']['commissiontype']==1){
                                    if($istc2==0){
                                        $ogdata['parent2commission'] = $parent2['levelData']['commission2'];
                                        $istc2 = 1;
                                    }
                                }else{
                                    $ogdata['parent2commission'] = $parent2['levelData']['commission2'] * $commission_totalprice * 0.01;
                                }
                            }
                            if($ogdata['parent3']){
                                if($parent3['levelData']['commissiontype']==1){
                                    if($istc3==0){
                                        $ogdata['parent3commission'] = $parent3['levelData']['commission3'];
                                        $istc3 = 1;
                                    }
                                }else{
                                    $ogdata['parent3commission'] = $parent3['levelData']['commission3'] * $commission_totalprice * 0.01;
                                }
                            }
                        }
					}
				$ogid = Db::name('restaurant_shop_order_goods')->insertGetId($ogdata);
                $ogids[] = $ogid;
				if($ogdata['parent1'] && ($ogdata['parent1commission'] > 0 || $ogdata['parent1score'] > 0)){
					Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent1'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'restaurant_shop','commission'=>$ogdata['parent1commission'],'score'=>$ogdata['parent1score'],'remark'=>'下级购买菜品奖励','createtime'=>time()]);
				}
				if($ogdata['parent2'] && ($ogdata['parent2commission'] || $ogdata['parent2score'])){
					Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent2'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'restaurant_shop','commission'=>$ogdata['parent2commission'],'score'=>$ogdata['parent2score'],'remark'=>'下二级购买菜品奖励','createtime'=>time()]);
				}
				if($ogdata['parent3'] && ($ogdata['parent3commission'] || $ogdata['parent3score'])){
					Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent3'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'restaurant_shop','commission'=>$ogdata['parent3commission'],'score'=>$ogdata['parent3score'],'remark'=>'下三级购买菜品奖励','createtime'=>time()]);
				}
	            
				//删除购物车
                if(input('btype') ==0 && $guige['id']){
                    Db::name('restaurant_shop_cart')->where('aid',aid)->where('mid',mid)->where('ggid',$guige['id'])->where('proid',$product['id'])->delete();
                }
				Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$guige['id'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num"),'sales_daily'=>Db::raw("sales_daily+$num")]);
				Db::name('restaurant_product')->where('aid',aid)->where('id',$product['id'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num"),'sales_daily'=>Db::raw("sales_daily+$num")]);
				}
            //根据餐后付款设置，开启时下单后打印小票，关闭时付款后打印小票
            $restaurant_shop_sysset = Db::name('restaurant_shop_sysset')->where('aid', aid)->where('bid', $data['bid'])->find();
			if($restaurant_shop_sysset['pay_after'] == 1) {
                \app\customs\Restaurant::print('restaurant_shop',$orderdata);
            }

            $store_name = Db::name('admin_set')->where('aid',aid)->value('name');
			//公众号通知 订单提交成功
			$tmplcontent = [];
			$tmplcontent['first'] = '有新点餐订单提交成功';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $store_name; //店铺
			$tmplcontent['keyword2'] = date('Y-m-d H:i:s',$orderdata['createtime']);//下单时间
			$tmplcontent['keyword3'] = $orderdata['title'];//菜品
			$tmplcontent['keyword4'] = $orderdata['totalprice'].'元';//金额
            $tempconNew = [];
            $tempconNew['character_string2'] = $orderdata['ordernum'];//订单号
            $tempconNew['thing8'] = $store_name;//门店名称
            $tempconNew['thing3'] = $orderdata['title'];//商品名称
            $tempconNew['amount7'] = $orderdata['totalprice'];//金额
            $tempconNew['time4'] = date('Y-m-d H:i:s',$orderdata['createtime']);//下单时间
			\app\commons\Wechat::sendhttmpl(aid,$orderdata['bid'],'tmpl_orderconfirm',$tmplcontent,m_url('admin/restaurant/shoporder'),$orderdata['mdid'],$tempconNew);
			
			$tmplcontent = [];
			$tmplcontent['thing11'] = $orderdata['title'];
			$tmplcontent['character_string2'] = $orderdata['ordernum'];
			$tmplcontent['phrase10'] = '待付款';
			$tmplcontent['amount13'] = $orderdata['totalprice'].'元';
			$tmplcontent['thing27'] = $this->member['nickname'];
			\app\commons\Wechat::sendhtwxtmpl(aid,$orderdata['bid'],'tmpl_orderconfirm',$tmplcontent,'admin/restaurant/shoporder',$orderdata['mdid']);
		}
		if(count($buydata) > 1){ //创建合并支付单
			$payorderid = \app\models\Payorder::createorder(aid,0,mid,'restaurant_shop_hb',$orderid,$ordernum,$orderdata['title'],$alltotalprice,$alltotalscore);
		}

		return $this->json(['status'=>1,'payorderid'=>$payorderid,'msg'=>'提交成功','pay_after' => $shop_sysset['pay_after']]);
	}

    //编辑加菜
    public function editOrder(){
        $post = input('post.');
        if($post['frompage'] != 'admin') {
            //非管理员点餐验证登录
            $this->checklogin();
        }
        if(empty($post['tableid'])) {
            return $this->json(['status'=>0,'msg'=>'请先扫描桌台二维码或选择桌台']);
        }

        //todo 一个桌子同时只能点一单
        $table = Db::name('restaurant_table')->where('aid',aid)->where('id', $post['tableid'])->find();
        //桌子有订单号时,并且订单内有菜，为加菜
        if($table['orderid']) {
            $order = Db::name('restaurant_shop_order')->where('aid',aid)->where('id', $table['orderid'])->find();
            if($order && $order['status'] > 0 && $post['frompage'] != 'admin'){
                return $this->json(['status'=>0,'msg'=>'当前桌台存在未结束的订单，请联系服务员清台后重新下单']);
            }
            $order['goods_count'] = Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('orderid', $order['id'])->count();
        }
        $shop_set = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',$order['bid'])->find();
        $mid = $order['mid'] ? $order['mid'] : mid;
        $member = Db::name('member')->where('aid',aid)->where('id', $mid)->find();

        $ordertype = $order['goods_count'] ? 'edit_order' : 'create_order';//加菜，点菜

        $sysset = Db::name('admin_set')->where('aid',aid)->find();
        $levelid = Db::name('member')->where('aid',aid)->where('id', $mid)->value('levelid');
        $userlevel = Db::name('member_level')->where('aid',aid)->where('id',$levelid)->find();

        $buydata = $post['buydata'];
        $couponridArr = [];
        $userinfo = [];
        $userinfo['discount'] = $userlevel['discount'];
        $userinfo['score'] = $member['score'];
        $userinfo['score2money'] = $sysset['score2money'];
        $userinfo['scoredk_money'] = round($userinfo['score'] * $userinfo['score2money'],2);
        $userinfo['scoredkmaxpercent'] = $sysset['scoredkmaxpercent'];
        $userinfo['scoremaxtype'] = 0; //0最大百分比 1最大抵扣金额
        $userinfo['realname'] = $member['realname'];
        $userinfo['tel'] = $member['tel'];

        $ordernum = date('ymdHis').rand(100000,999999);
        $scoredkmaxmoney = 0;
        $scoremaxtype = 0;
        $i = 0;
        $alltotalprice = 0;
        $alltotalscore = 0;
        Log::write([
            'file'=>__FILE__.__LINE__,
            '餐饮点餐订单编辑,orderid：'=>$table['orderid'],
            'post'=>json_encode(input('post.')),
            'mid'=>mid
        ]);
        foreach($buydata as $data){
            $i++;
            $bid = $data['bid'];
            if($data['prodata']){
                $prodata = explode('-',$data['prodata']);
            }else{
                return $this->json(['status'=>0,'msg'=>'产品数据错误']);
            }
            if($bid!=0){
                $business = Db::name('business')->where('id',$bid)->field('id,aid,cid,name,logo,tel,address,sales,longitude,latitude,start_hours,end_hours')->find();
                if($business['start_hours'] != $business['end_hours']){
                    $start_time = strtotime(date('Y-m-d '.$business['start_hours']));
                    $end_time = strtotime(date('Y-m-d '.$business['end_hours']));
                    if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
                        return $this->json(['status'=>0,'msg'=>'商家不在营业时间']);
                    }
                }
            }
            $shop_set = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',$bid)->find();
            if($shop_set['status']==0){
                return $this->json(['status'=>0,'msg'=>'该商家未开启点餐']);
            }
            if($shop_set['start_hours'] != $shop_set['end_hours']){
                $start_time = strtotime(date('Y-m-d '.$shop_set['start_hours']));
                $end_time = strtotime(date('Y-m-d '.$shop_set['end_hours']));
                if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
                    return $this->json(['status'=>0,'msg'=>'该商家不在点餐时间']);
                }
            }

            $product_price = 0;
            $needzkproduct_price = 0;
            $givescore = 0; //奖励积分
            $totalweight = 0;//重量
            $totalnum = 0;
            $prolist = [];
            $proids = [];
            $cids = [];

            foreach($prodata as $key=>$pro){
                $sdata = explode(',',$pro);
                $sdata[2] = intval($sdata[2]);
                if($sdata[2] <= 0) return $this->json(['status'=>0,'msg'=>'购买数量有误']);
                $product = Db::name('restaurant_product')->where('aid',aid)->where('ischecked',1)->where('bid',$bid)->where('id',$sdata[0])->find();
                if(!$product) return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);

                if($product['status']==0){
                    return $this->json(['status'=>0,'msg'=>'菜品未上架']);
                }
                if($product['status']==2 && (strtotime($product['start_time']) > time() || strtotime($product['end_time']) < time())){
                    return $this->json(['status'=>0,'msg'=>'菜品未上架']);
                }
                if($product['status']==3){
                    $start_time = strtotime(date('Y-m-d '.$product['start_hours']));
                    $end_time = strtotime(date('Y-m-d '.$product['end_hours']));
                    if(($start_time < $end_time && ($start_time > time() || $end_time < time())) || ($start_time >= $end_time && ($start_time > time() && $end_time < time()))){
                        return $this->json(['status'=>0,'msg'=>'菜品未上架']);
                    }
                }

                if($key==0) $title = $product['name'];
                $deletegg = 1;
                $guige = Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$sdata[1])->find();
                if(!$guige && $deletegg) return $this->json(['status'=>0,'msg'=>'产品规格不存在或已下架']);
                $is_check_stock = 1;
                if($is_check_stock){
                    if($guige['stock'] < $sdata[2] || $guige['stock_daily']-$guige['sales_daily']<$sdata[2]){
                        return $this->json(['status'=>0,'msg'=>'库存不足']);
                    }
                }

                if($product['limit_per'] > 0 && $sdata[2] > $product['limit_per']){ //每单限购
                    return $this->json(['status'=>0,'msg'=>$product['name'].'每单限购'.$product['limit_per'].'份']);
                }
                if($product['limit_start'] > 0 && $sdata[2] < $product['limit_start']){ //起售份数
                    return $this->json(['status'=>0,'msg'=>$product['name'].'最低购买'.$product['limit_start'].'份']);
                }

                $guige = $this->formatguige($guige,$product['bid'],$product['lvprice']);
                $product_price += $guige['sell_price'] * $sdata[2];
                if($product['lvprice']==0){ //未开启会员价
                    $needzkproduct_price += $guige['sell_price'] * $sdata[2];
                }
                $cartid  =  intval($sdata[3]);//购物车
                $totalweight += $guige['weight'] * $sdata[2];
                $totalnum += $sdata[2];

                 if($product['bid']>0){
                    //判断商家是否能自主修改积分设置
                    $business_selfscore = 0;
                    if(getcustom('business_selfscore') || getcustom('business_score_jiesuan')){
                        $business_selfscore = Db::name('business_sysset')->where('aid',aid)->value('business_selfscore');
                    }
                    $bcansetscore = false;//商家能否修改积分
                    if(!$business_selfscore && !$bcansetscore){
                        $product['scored_set'] = 0;
                    }
                }
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

                $prolist[] = ['product'=>$product,'guige'=>$guige,'num'=>$sdata[2],'cartid'=>$cartid];

                $proids[] = $product['id'];
                $cids = array_merge($cids,explode(',',$product['cid']));
                $givescore += $guige['givescore'] * $sdata[2];
            }

//            dd($prolist);
            //会员折扣
            $leveldk_money = 0;
            if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
                $leveldk_money = $needzkproduct_price * (1 - $userlevel['discount'] * 0.1);
            }
            $totalprice = $product_price - $leveldk_money;

            //积分抵扣
            $scoredkscore = 0;
            $scoredk_money = 0;
            $orderdata = [];
            $end_totalprice = $totalprice + $order['totalprice'];
            $orderdata['totalprice'] = dd_money_format($end_totalprice);
            $orderdata['product_price'] = $product_price + $order['product_price'];
            $orderdata['leveldk_money'] = $leveldk_money + $order['leveldk_money'];  //会员折扣
            if($order['bid'] ==0){
                $orderdata['givescore'] = $givescore + $order['givescore'];
            }
            $orderdata['message'] = $data['message'] . ' '. $order['message'];
            //重新生成单号
            $orderdata['ordernum'] = $ordernum;
            $orderid = $order['id'];
            $rsupdate = Db::name('restaurant_shop_order')->where('id', $table['orderid'])->update($orderdata);
            if($rsupdate ===false){
                return $this->json(['status'=>0,'msg'=>'系统繁忙，请重试']);
            }
            $orderdata = Db::name('restaurant_shop_order')->where('id', $table['orderid'])->find();
            $rsupdate = Db::name('restaurant_shop_order_goods')->where('orderid',$table['orderid'])->update(['ordernum'=>$orderdata['ordernum']]);
            if($rsupdate ===false){
                return $this->json(['status'=>0,'msg'=>'系统繁忙，请重试']);
            }

            $payorderid = \app\models\Payorder::createorder(aid,$orderdata['bid'],$orderdata['mid'],'restaurant_shop',$orderid,$orderdata['ordernum'],$orderdata['title'],$orderdata['totalprice'],$orderdata['scoredkscore']);

            $alltotalprice += $orderdata['totalprice'];
            $alltotalscore += $orderdata['scoredkscore'];

            $istc1 = 0; //设置了按单固定提成时 只将佣金计算到第一个菜品里
            $istc2 = 0;
            $istc3 = 0;
            foreach($prolist as $key=>$v){
                $product = $v['product'];
                $guige = $v['guige'];
                $num = $v['num'];
                $ogdata = [];
                $ogdata['aid'] = aid;
                $ogdata['bid'] = $product['bid'];
                $ogdata['mid'] = $mid;
                $ogdata['orderid'] = $orderid;
                $ogdata['ordernum'] = $orderdata['ordernum'];
                $ogdata['proid'] = $product['id'];
                $ogdata['name'] = $product['name'];
                $ogdata['pic'] = $product['pic'];
                $ogdata['procode'] = $product['procode'];
                $ogdata['ggid'] = $guige['id'];
                $ogdata['ggname'] = $guige['name'];
                //$ogdata['cid'] = $product['cid'];
                $ogdata['num'] = $num;
                $ogdata['cost_price'] = $guige['cost_price'];
                $sell_price =  $guige['sell_price'];
                $og_totalprice = $num * $sell_price;
                $ogdata['sell_price'] = $sell_price;
                $ogdata['totalprice'] = $og_totalprice;
                $ogdata['real_totalprice'] = $og_totalprice; //实际菜品销售金额

                $ogdata['status'] = 0;
                $ogdata['createtime'] = time();
                //计算菜品实际金额  菜品金额 - 会员折扣 - 积分抵扣 - 满减抵扣 - 优惠券抵扣
                    if($sysset['fxjiesuantype'] == 1 || $sysset['fxjiesuantype'] == 2){
                        $allproduct_price = $product_price;
                        $og_leveldk_money = 0;
                        $og_coupon_money = 0;
                        $og_scoredk_money = 0;
                        $og_manjian_money = 0;
                        if($allproduct_price > 0 && $og_totalprice > 0){
                            if($leveldk_money){
                                $og_leveldk_money = $og_totalprice / $allproduct_price * $leveldk_money;
                            }
                            if($coupon_money){
                                $og_coupon_money = $og_totalprice / $allproduct_price * $coupon_money;
                            }
                            if($scoredk_money){
                                $og_scoredk_money = $og_totalprice / $allproduct_price * $scoredk_money;
                            }
                            if($manjian_money){
                                $og_manjian_money = $og_totalprice / $allproduct_price * $manjian_money;
                            }
                        }
                        $og_totalprice = round($og_totalprice - $og_coupon_money - $og_scoredk_money - $og_manjian_money,2);
                        if($og_totalprice < 0) $og_totalprice = 0;
                    }
                    $ogdata['real_totalprice'] = $og_totalprice; //实际菜品销售金额

                    Log::write([
                        'file'=>__FILE__.__LINE__,
                        'real_totalprice：'=>$og_totalprice,
                        'mid'=>mid
                    ]);

                    //计算佣金的菜品金额
                    $commission_totalprice = $ogdata['totalprice'];
                    if($sysset['fxjiesuantype'] == 1){ //按成交价格
                        $commission_totalprice = $ogdata['real_totalprice'];
                    }
                    if($sysset['fxjiesuantype']==2){ //按利润提成
                        $commission_totalprice = $ogdata['real_totalprice'] - $guige['cost_price'] * $num;
                    }
                    if($commission_totalprice < 0) $commission_totalprice = 0;

                    $pid = $member['pid'];
                    $agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$levelid)->find();
                    if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
                        $pid = $mid;
                    }
                    if($product['commissionset']!=-1){
                        if($pid){
                            $parent1 = Db::name('member')->where('aid',aid)->where('id',$pid)->find();
                            if($parent1){
                                $agleveldata1 = Db::name('member_level')->where('aid',aid)->where('id',$parent1['levelid'])->find();
                                if($agleveldata1['can_agent']!=0){
                                    $ogdata['parent1'] = $parent1['id'];
                                }
                            }
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
                        if($product['commissionset']==1){//按菜品设置的分销比例
                            $commissiondata = json_decode($product['commissiondata1'],true);
                            if($commissiondata){
                                if($agleveldata1) $ogdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
                                if($agleveldata2) $ogdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
                                if($agleveldata3) $ogdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
                            }
                        }elseif($product['commissionset']==2){//按固定金额
                            $commissiondata = json_decode($product['commissiondata2'],true);
                            if($commissiondata){
                                if($agleveldata1) $ogdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
                                if($agleveldata2) $ogdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
                                if($agleveldata3) $ogdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
                            }
                        }elseif($product['commissionset']==3){//提成是积分
                            $commissiondata = json_decode($product['commissiondata3'],true);
                            if($commissiondata){
                                if($agleveldata1) $ogdata['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
                                if($agleveldata2) $ogdata['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
                                if($agleveldata3) $ogdata['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
                            }
                        }else{ //按会员等级设置的分销比例
                            if($agleveldata1){
                                if($agleveldata1['commissiontype']==1){ //固定金额按单
                                    if($istc1==0){
                                        $ogdata['parent1commission'] = $agleveldata1['commission1'];
                                        $istc1 = 1;
                                    }
                                }else{
                                    $ogdata['parent1commission'] = $agleveldata1['commission1'] * $commission_totalprice * 0.01;
                                }
                            }
                            if($agleveldata2){
                                if($agleveldata2['commissiontype']==1){
                                    if($istc2==0){
                                        $ogdata['parent2commission'] = $agleveldata2['commission2'];
                                        $istc2 = 1;
                                    }
                                }else{
                                    $ogdata['parent2commission'] = $agleveldata2['commission2'] * $commission_totalprice * 0.01;
                                }
                            }
                            if($agleveldata3){
                                if($agleveldata3['commissiontype']==1){
                                    if($istc3==0){
                                        $ogdata['parent3commission'] = $agleveldata3['commission3'];
                                        $istc3 = 1;
                                    }
                                }else{
                                    $ogdata['parent3commission'] = $agleveldata3['commission3'] * $commission_totalprice * 0.01;
                                }
                            }
                        }
                    }
                $ogid = Db::name('restaurant_shop_order_goods')->insertGetId($ogdata);
                $ogids[] = $ogid;
                if($ogdata['parent1'] && ($ogdata['parent1commission'] > 0 || $ogdata['parent1score'] > 0)){
                    Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent1'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'restaurant_shop','commission'=>$ogdata['parent1commission'],'score'=>$ogdata['parent1score'],'remark'=>'下级购买菜品奖励','createtime'=>time()]);
                }
                if($ogdata['parent2'] && ($ogdata['parent2commission'] || $ogdata['parent2score'])){
                    Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent2'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'restaurant_shop','commission'=>$ogdata['parent2commission'],'score'=>$ogdata['parent2score'],'remark'=>'下二级购买菜品奖励','createtime'=>time()]);
                }
                if($ogdata['parent3'] && ($ogdata['parent3commission'] || $ogdata['parent3score'])){
                    Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent3'],'frommid'=>$mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'restaurant_shop','commission'=>$ogdata['parent3commission'],'score'=>$ogdata['parent3score'],'remark'=>'下三级购买菜品奖励','createtime'=>time()]);
                }
                //删除购物车
                if($guige['id'] && $product['id']){
                    Db::name('restaurant_shop_cart')->where('aid',aid)->where('mid',mid)->where('ggid',$guige['id'])->where('proid',$product['id'])->delete();
                }
                Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$guige['id'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num"),'sales_daily'=>Db::raw("sales_daily+$num")]);
                Db::name('restaurant_product')->where('aid',aid)->where('id',$product['id'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num"),'sales_daily'=>Db::raw("sales_daily+$num")]);
                }

            //根据餐后付款设置，开启时下单后打印小票，关闭时付款后打印小票
            $restaurant_shop_sysset = Db::name('restaurant_shop_sysset')->where('aid', aid)->where('bid', $data['bid'])->find();
            if($restaurant_shop_sysset['pay_after'] == 1) {
                //仅打印加菜
                $orderGoods = Db::name('restaurant_shop_order_goods')->alias('og')->where('orderid',$orderid)->where('og.id', 'in', $ogids)->leftJoin('restaurant_product p', 'p.id=og.proid')
                    ->fieldRaw('og.*,p.area_id')->select()->toArray();
                \app\customs\Restaurant::print('restaurant_shop',$orderdata, $orderGoods);
            }

        }
        if(count($buydata) > 1){ //创建合并支付单
            $payorderid = \app\models\Payorder::createorder(aid,0,$mid,'restaurant_shop_hb',$orderid,$ordernum,$orderdata['title'],$alltotalprice,$alltotalscore);
        }

        return $this->json(['status'=>1,'payorderid'=>$payorderid,'msg'=>'提交成功', 'pay_after' => $shop_set['pay_after']]);
    }

	public function orderlist(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$where[] = ['delete','=',0];
		if($st == 'all'){
			
		}elseif($st == '0'){
			$where[] = ['status','=',0];
		}elseif($st == '1'){
			$where[] = ['status','in','1,12'];
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '3'){
			$where[] = ['status','=',3];
		}elseif($st == '10'){
			$where[] = ['refund_status','>',0];
		}
        $is_member = 1;
        if($is_member){
            $where[] = ['mid','=',mid];
        }
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('restaurant_shop_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
            $datalist[$key]['procount'] = 0+Db::name('restaurant_shop_order_goods')->where('orderid',$v['id'])->sum('num');
		    $prolist= Db::name('restaurant_shop_order_goods')->where('orderid',$v['id'])->select()->toArray();
            foreach($prolist as $pgk=>$pro){
                }
			$datalist[$key]['prolist'] = $prolist; 
			if(!$prolist) $datalist[$key]['prolist'] = [];
		
			if($v['bid']!=0){
				$datalist[$key]['binfo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
			}else{
				$datalist[$key]['binfo'] = Db::name('admin_set')->where('aid',aid)->field('id,name,logo')->find();
			}
			$commentdp = Db::name('business_comment')->where('orderid',$v['id'])->where('aid',aid)->where('mid',mid)->find();
			if($commentdp){
				$datalist[$key]['iscommentdp'] = 1;
			}else{
				$datalist[$key]['iscommentdp'] = 0;
			}
			$is_topay = 1;
			$datalist[$key]['is_topay'] =$is_topay;
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	public function orderdetail(){
		$detail = Db::name('restaurant_shop_order')->where('id',input('param.id/d'))->where('aid',aid)->find();
		if(empty($detail)) $this->json(['status'=>0,'msg'=>'订单不存在']);
		//可在6小时内查看非本人订单
        if(mid != $detail['mid'] && time() > ($detail['createtime'] + 3600*6)) {
            $this->json(['status'=>0,'msg'=>'订单不存在']);
        }

		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';

		$storeinfo = [];
		if($detail['freight_type'] == 1){
			if($detail['bid'] == 0){
				$storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('name,address,longitude,latitude')->find();
			}else{
				$storeinfo = Db::name('business')->where('id',$detail['bid'])->field('name,address,longitude,latitude')->find();
			}
		}
		
		if($detail['bid'] > 0){
			$detail['binfo'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->field('id,name,logo')->find();
		}else{
			$detail['binfo'] = Db::name('admin_set')->where('aid',aid)->field('id,name,logo')->find();
		}
		$iscommentdp = 0;
		$commentdp = Db::name('business_comment')->where('orderid',$detail['id'])->where('aid',aid)->where('mid',mid)->find();
		if($commentdp) $iscommentdp = 1;

		$prolist = Db::name('restaurant_shop_order_goods')->where('orderid',$detail['id'])->select()->toArray();
        $shopset = Db::name('shop_sysset')->where('aid',aid)->field('comment,autoclose')->find();
        
        
        $shop_set = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',$detail['bid'])->find();
        $shopset = array_merge($shopset,$shop_set);
		
		if($detail['status']==0 && $shopset['autoclose'] > 0){
			$lefttime = strtotime($detail['createtime']) + $shopset['autoclose']*60 - time();
			if($lefttime < 0) $lefttime = 0;
		}else{
			$lefttime = 0;
		}
		
		if($detail['field1']){
			$detail['field1data'] = explode('^_^',$detail['field1']);
		}
		if($detail['field2']){
			$detail['field2data'] = explode('^_^',$detail['field2']);
		}
		if($detail['field3']){
			$detail['field3data'] = explode('^_^',$detail['field3']);
		}
		if($detail['field4']){
			$detail['field4data'] = explode('^_^',$detail['field4']);
		}
		if($detail['field5']){
			$detail['field5data'] = explode('^_^',$detail['field5']);
		}

        $detail['tableName'] = Db::name('restaurant_table')->where('id',$detail['tableid'])->value('name');
		//开启餐后付款用户不可关闭订单（可能厨房已经做好或者已经吃了）

        $detail['tabletext'] = '桌号';
        $is_topay = 1;
        $detail['is_topay'] = $is_topay;
        $detail['show_printdaynum'] = 0;
        if(getcustom('sys_print_set')){
            $detail['show_printdaynum'] = 1;
        }
        $rdata = [];
		$rdata['detail'] = $detail;
		$rdata['iscommentdp'] = $iscommentdp;
		$rdata['prolist'] = $prolist;
		$rdata['shopset'] = $shopset;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['lefttime'] = $lefttime;
		return $this->json($rdata);
	}
	//取消订单
	function quxiao(){
//		$post = input('post.');
//		$orderid = intval($post['orderid']);
//		$order = Db::name('restaurant_shop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
//		if(!$order || $order['status']!=1){
//			return $this->json(['status'=>0,'msg'=>'取消失败,订单状态错误']);
//		}
//		$refund_money = $order['totalprice'];
//		$reason = '用户取消订单';
//		if($refund_money > 0) {
//            $rs = \app\commons\Order::refund($order,$refund_money,$reason);
//            if($rs['status']==0){
//                return json(['status'=>0,'msg'=>$rs['msg']]);
//            }
//        }
//
//		Db::name('restaurant_shop_order')->where('id',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4,'refund_status'=>2,'refund_money'=>$refund_money,'refund_reason'=>$reason]);
//		Db::name('restaurant_shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('bid',bid)->update(['status'=>4]);
//
//		//积分抵扣的返还
//		if($order['scoredkscore'] > 0){
//			\app\commons\Member::addscore(aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
//		}
//		//优惠券抵扣的返还
//		if($order['coupon_rid'] > 0){
//			Db::name('coupon_record')->where('aid',aid)->where(['mid'=>$order['mid'],'id'=>$order['coupon_rid']])->update(['status'=>0,'usetime'=>'']);
//		}
//
//		//退款成功通知
//		$tmplcontent = [];
//		$tmplcontent['first'] = '您的订单已经完成退款，¥'.$refund_money.'已经退回您的付款账户，请留意查收。';
//		$tmplcontent['remark'] = $reason.'，请点击查看详情~';
//		$tmplcontent['orderProductPrice'] = $refund_money;
//		$tmplcontent['orderProductName'] = $order['title'];
//		$tmplcontent['orderName'] = $order['ordernum'];
//		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('restaurant/shop/orderlist'));
//		//订阅消息
//		$tmplcontent = [];
//		$tmplcontent['amount6'] = $refund_money;
//		$tmplcontent['thing3'] = $order['title'];
//		$tmplcontent['character_string2'] = $order['ordernum'];
//
//		$tmplcontentnew = [];
//		$tmplcontentnew['amount3'] = $refund_money;
//		$tmplcontentnew['thing6'] = $order['title'];
//		$tmplcontentnew['character_string4'] = $order['ordernum'];
//		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'restaurant/shop/orderlist',$tmplcontent);
//
//		//短信通知
//		$member = Db::name('member')->where('id',$order['mid'])->find();
//		if($member['tel']){
//			$tel = $member['tel'];
//		}else{
//			$tel = $order['tel'];
//		}
//		$rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$refund_money]);
//
//		return json(['status'=>1,'msg'=>'已退款成功']);
	}
	function closeOrder(){
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('restaurant_shop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || $order['status']!=0){
			return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		//加库存
		$oglist = Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
		foreach($oglist as $og){
			Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
			Db::name('restaurant_product')->where('aid',aid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
			if($og['seckill_starttime']){
				Db::name('seckill_prodata')->where('aid',aid)->where('proid',$og['proid'])->where('ggid',$og['ggid'])->where('starttime',$og['seckill_starttime'])->dec('sales',$og['num'])->update();
			}
		}
		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
		}

		$rs = Db::name('restaurant_shop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);
		Db::name('restaurant_shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);
		//清除桌台
        if($order['tableid'] > 0){
            Db::name('restaurant_table')->where('id',$order['tableid'])->where('aid',$order['aid'])->where('orderid',$order['id'])->where('bid',$order['bid'])->update(['status' => 0, 'orderid' => 0]);
        }
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	function delOrder(){
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('restaurant_shop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || ($order['status']!=4 && $order['status']!=3)){
			return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
		}
//		if($order['status']==3){
			$rs = Db::name('restaurant_shop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['delete'=>1]);
//		}else{
//			$rs = Db::name('restaurant_shop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->delete();
//			$rs = Db::name('restaurant_shop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('mid',mid)->delete();
//		}
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}
	function orderCollect(){ //确认收货
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('restaurant_shop_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
		if(!$order || ($order['status']!=2) || $order['paytypeid']==4){
			return $this->json(['status'=>0,'msg'=>'订单状态不符合收货要求']);
		}
        $rs = \app\customs\Restaurant::shop_orderconfirm($orderid);
		if($rs['status'] == 0) return $this->json($rs);

//		Db::name('restaurant_shop_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
//		Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);
		\app\commons\Member::uplv(aid,mid);
		$return = ['status'=>1,'msg'=>'确认收货成功','url'=>true];

		$tmplcontent = [];
		$tmplcontent['first'] = '有订单客户已确认收货';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $this->member['nickname'];
		$tmplcontent['keyword2'] = $order['ordernum'];
		$tmplcontent['keyword3'] = $order['totalprice'].'元';
		$tmplcontent['keyword4'] = date('Y-m-d H:i',$order['paytime']);
        $tmplcontentNew = [];
        $tmplcontentNew['thing3'] = $this->member['nickname'];//收货人
        $tmplcontentNew['character_string7'] = $order['ordernum'];//订单号
        $tmplcontentNew['time8'] = date('Y-m-d H:i');//送达时间
		\app\commons\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordershouhuo',$tmplcontent,m_url('admin/order/shoporder'),$order['mdid'],$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['character_string6'] = $order['ordernum'];
		$tmplcontent['thing3'] = $this->member['nickname'];
		$tmplcontent['date5'] = date('Y-m-d H:i');
		\app\commons\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordershouhuo',$tmplcontent,'admin/order/shoporder',$order['mdid']);

		return $this->json($return);
	}
	public function refundinit(){
		//订阅消息
		$wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
		$tmplids = [];
		if($wx_tmplset['tmpl_tuisuccess_new']){
			$tmplids[] = $wx_tmplset['tmpl_tuisuccess_new'];
		}elseif($wx_tmplset['tmpl_tuisuccess']){
			$tmplids[] = $wx_tmplset['tmpl_tuisuccess'];
		}
		if($wx_tmplset['tmpl_tuierror_new']){
			$tmplids[] = $wx_tmplset['tmpl_tuierror_new'];
		}elseif($wx_tmplset['tmpl_tuierror']){
			$tmplids[] = $wx_tmplset['tmpl_tuierror'];
		}
		$rdata = [];
		$rdata['tmplids'] = $tmplids;
		return $this->json($rdata);
	}
	function refund(){//申请退款
		if(request()->isPost()){
			$post = input('post.');
			$orderid = intval($post['orderid']);
			$money = floatval($post['money']);
			$order = Db::name('restaurant_shop_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
			if(!$order || ($order['status']!=1 && $order['status'] != 2) || $order['refund_status'] == 2){
				return $this->json(['status'=>0,'msg'=>'订单状态不符合退款要求']);
			}
			if($money < 0 || $money > $order['totalprice']){
				return $this->json(['status'=>0,'msg'=>'退款金额有误']);
			}
			Db::name('restaurant_shop_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['refund_time'=>time(),'refund_status'=>1,'refund_reason'=>$post['reason'],'refund_money'=>$money]);

			$tmplcontent = [];
			$tmplcontent['first'] = '有订单客户申请退款';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $order['ordernum'];
			$tmplcontent['keyword2'] = $money.'元';
			$tmplcontent['keyword3'] = $post['reason'];
            $tmplcontentNew = [];
            $tmplcontentNew['number2'] = $order['ordernum'];//订单号
            $tmplcontentNew['amount4'] = $money;//退款金额
			\app\commons\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,m_url('admin/restaurant/shoporder'),$order['mdid'],$tmplcontentNew);
			
			$tmplcontent = [];
			$tmplcontent['thing1'] = $order['title'];
			$tmplcontent['character_string4'] = $order['ordernum'];
			$tmplcontent['amount2'] = $order['totalprice'];
			$tmplcontent['amount9'] = $money.'元';
			$tmplcontent['thing10'] = $post['reason'];
			\app\commons\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,'admin/restaurant/shoporder',$order['mdid']);

			return $this->json(['status'=>1,'msg'=>'提交成功,请等待商家审核']);
		}
		$orderid = input('param.orderid/d');
		$price = input('param.price/f');
		$order = Db::name('restaurant_shop_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
		$price = $order['totalprice'];
		$this->assign('orderid',$orderid);
		$this->assign('price',$price);
		return $this->fetch();
	}
	//评价菜品
	public function comment(){
		$ogid = input('param.ogid/d');
		$og = Db::name('restaurant_shop_order_goods')->where('id',$ogid)->where('mid',mid)->find();
		if(!$og){
			return $this->json(['status'=>0,'msg'=>'未查找到相关记录']);
		}
		$comment = Db::name('restaurant_shop_comment')->where('ogid',$ogid)->where('aid',aid)->where('mid',mid)->find();
		if(request()->isPost()){
			$shopset = Db::name('shop_sysset')->where('aid',aid)->find();
			if($shopset['comment']==0) return $this->json(['status'=>0,'msg'=>'评价功能未开启']);
			if($comment){
				return $this->json(['status'=>0,'msg'=>'您已经评价过了']);
			}
			$order_good = Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('mid',mid)->where('id',$ogid)->find();
			$order = Db::name('restaurant_shop_order')->where('id',$order_good['orderid'])->find();
			$content = input('post.content');
			$content_pic = input('post.content_pic');
			$score = input('post.score/d');
			if($score < 1){
				return $this->json(['status'=>0,'msg'=>'请打分']);
			}
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['ogid'] = $order_good['id'];
			$data['proid'] =$order_good['proid'];
			$data['proname'] = $order_good['name'];
			$data['propic'] = $order_good['pic'];
			$data['orderid']= $order['id'];
			$data['ordernum']= $order['ordernum'];
			$data['score'] = $score;
			$data['content'] = $content;
			$data['openid']= $this->member['openid'];
			$data['nickname']= $this->member['nickname'];
			$data['headimg'] = $this->member['headimg'];
			$data['createtime'] = time();
			$data['content_pic'] = $content_pic;
			$data['ggid'] = $order_good['ggid'];
			$data['ggname'] = $order_good['ggname'];
			$data['status'] = ($shopset['comment_check']==1 ? 0 : 1);
			Db::name('restaurant_shop_comment')->insert($data);
			Db::name('restaurant_shop_order_goods')->where('aid',aid)->where('mid',mid)->where('id',$ogid)->update(['iscomment'=>1]);
			//Db::name('restaurant_shop_order')->where('id',$order['id'])->update(['iscomment'=>1]);
			
			//如果不需要审核 增加产品评论数及评分
			if($shopset['comment_check']==0){
				$countnum = Db::name('restaurant_shop_comment')->where('proid',$order_good['proid'])->where('status',1)->count();
				$score = Db::name('restaurant_shop_comment')->where('proid',$order_good['proid'])->where('status',1)->avg('score'); //平均评分
				$haonum = Db::name('restaurant_shop_comment')->where('proid',$order_good['proid'])->where('status',1)->where('score','>',3)->count(); //好评数
				if($countnum > 0){
					$haopercent = $haonum/$countnum*100;
				}else{
					$haopercent = 100;
				}
				Db::name('restaurant_product')->where('id',$order_good['proid'])->update(['comment_num'=>$countnum,'comment_score'=>$score,'comment_haopercent'=>$haopercent]);
			}
			return $this->json(['status'=>1,'msg'=>'评价成功']);
		}
		$rdata = [];
		$rdata['og'] = $og;
		$rdata['comment'] = $comment;
		return $this->json($rdata);
	}
	//评价店铺
	public function commentdp(){
		$orderid = input('param.orderid/d');
		$order = Db::name('restaurant_shop_order')->where('id',$orderid)->where('mid',mid)->find();
		if(!$order){
			return $this->json(['status'=>0,'msg'=>'未查找到相关记录']);
		}
		$comment = Db::name('business_comment')->where('orderid',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(request()->isPost()){
			if($comment){
				return $this->json(['status'=>0,'msg'=>'您已经评价过了']);
			}
			$content = input('post.content');
			$content_pic = input('post.content_pic');
			$score = input('post.score/d');
			if($score < 1){
				return $this->json(['status'=>0,'msg'=>'请打分']);
			}
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = $order['bid'];
			$data['bname'] = Db::name('business')->where('id',$order['bid'])->value('name');
			$data['orderid']= $order['id'];
			$data['ordernum']= $order['ordernum'];
			$data['score'] = $score;
			$data['content'] = $content;
			$data['content_pic'] = $content_pic;
			$data['openid']= $this->member['openid'];
			$data['nickname']= $this->member['nickname'];
			$data['headimg'] = $this->member['headimg'];
			$data['createtime'] = time();
			$data['status'] = 1;
			Db::name('business_comment')->insert($data);
			
			//如果不需要审核 增加店铺评论数及评分
			$countnum = Db::name('business_comment')->where('bid',$order['bid'])->where('status',1)->count();
			$score = Db::name('business_comment')->where('bid',$order['bid'])->where('status',1)->avg('score');
			Db::name('business')->where('id',$order['bid'])->update(['comment_num'=>$countnum,'comment_score'=>$score]);
			return $this->json(['status'=>1,'msg'=>'评价成功']);
		}
		$rdata = [];
		$rdata['order'] = $order;
		$rdata['comment'] = $comment;
		return $this->json($rdata);
	}
	//评价配送员
	public function commentps(){
		$id = input('param.id/d');
		$psorder = Db::name('peisong_order')->where('id',$id)->where('mid',mid)->find();
		if(!$psorder) return $this->json(['status'=>0,'msg'=>'未找到相关记录']);
		$comment = Db::name('peisong_order_comment')->where('orderid',$id)->where('aid',aid)->where('mid',mid)->find();
		if(request()->isPost()){
			if($comment){
				return $this->json(['status'=>0,'msg'=>'您已经评价过了']);
			}
			$content = input('post.content');
			$content_pic = input('post.content_pic');
			$score = input('post.score/d');
			if($score < 1){
				return $this->json(['status'=>0,'msg'=>'请打分']);
			}
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = $psorder['bid'];
			$data['psid'] = $psorder['psid'];
			$data['orderid']= $psorder['id'];
			$data['ordernum']= $psorder['ordernum'];
			$data['score'] = $score;
			$data['content'] = $content;
			$data['content_pic'] = $content_pic;
			$data['nickname']= $this->member['nickname'];
			$data['headimg'] = $this->member['headimg'];
			$data['createtime'] = time();
			$data['status'] = 1;
			Db::name('peisong_order_comment')->insert($data);
			
			//如果不需要审核 增加配送员评论数及评分
			$countnum = Db::name('peisong_order_comment')->where('psid',$psorder['psid'])->where('status',1)->count();
			$score = Db::name('peisong_order_comment')->where('psid',$psorder['psid'])->where('status',1)->avg('score'); //平均评分
			$haonum = Db::name('peisong_order_comment')->where('psid',$psorder['psid'])->where('status',1)->where('score','>',3)->count(); //好评数
			if($countnum > 0){
				$haopercent = $haonum/$countnum*100;
			}else{
				$haopercent = 100;
			}
			Db::name('peisong_user')->where('id',$psorder['psid'])->update(['comment_num'=>$countnum,'comment_score'=>$score,'comment_haopercent'=>$haopercent]);

			return $this->json(['status'=>1,'msg'=>'评价成功']);
		}
		$rdata = [];
		$rdata['psorder'] = $psorder;
		$rdata['comment'] = $comment;
		return $this->json($rdata);
	}
	//套餐详情
	public function getPackageData(){
        }
    
}