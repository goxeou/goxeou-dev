<?php


// +----------------------------------------------------------------------
// | 商城-商品管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use app\commons\Wechat;

class ShopProduct extends Common
{
	//商品列表
    public function index(){
        $erpWdtOpen = 0;
        if(getcustom('erp_wangdiantong')){
            $erpWdtOpen = Db::name('wdt_sysset')->where('aid',aid)->value('status');
        }
		if(request()->isAjax()){
			$page = input('param.page')?:1;
			$limit = input('param.limit')?:10;
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(bid==0){
				$bid = input('param.bid');
				if(input('param.showallbusiness')){
					//显示全部
					if(!isset($bid)) $bid = 'all';
				}
				if($bid){
					if($bid == 'all'){
						$where[] = ['bid','>=',0];
					}else{
						$where[] = ['bid','=',$bid];
					}
				}elseif(input('param.showtype')==2){
					$where[] = ['bid','>',0];
					$where[] = ['linkid','=',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
				}elseif(input('param.showtype')==21){
					$where[] = ['bid','=',-1];
				}else{
					$where[] = ['bid','=',0];
				}
                if(getcustom('user_area_agent') && $this->user['isadmin']==3){
                    $areaBids = \app\commons\Business::getUserAgentBids(aid,$this->user);
                    $where[] = ['bid','in',$areaBids];
                }

                //显示全部时查询商家
                if($bid == 'all' && input('param.bname')){
                    $where2 = [];
                    $where2[] = ['name','like','%'.input('param.bname').'%'];
                    $where2[] = ['aid','=',aid];
                    $bids = Db::name('business')->where($where2)->column('id');
                    if($bids){
                        $where[] = ['bid','in',$bids];
                    }else{
                        $where[] = ['id','=',0];
                    }
                }
			}else{
				$where[] = ['bid','=',bid];
			}
			$where[] = ['douyin_product_id','=',''];
            if(getcustom('product_supply_chain')){
                //供应链选品单独显示
                $where[] = ['product_type','<>',7];
            }
			if(input('?param.ischecked') && input('param.ischecked')!=='') $where[] = ['ischecked','=',$_GET['ischecked']];
			if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];
			if(input('?param.status') && input('param.status')!==''){
				$status = input('param.status');
				$nowtime = time();
				$nowhm = date('H:i');
				if($status==1){
					$where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");
				}else{
					$where[] = Db::raw("`status`=0 or (`status`=2 and (unix_timestamp(start_time)>$nowtime or unix_timestamp(end_time)<$nowtime)) or (`status`=3 and ((start_hours<end_hours and (start_hours>'$nowhm' or end_hours<'$nowhm')) or (start_hours>=end_hours and (start_hours>'$nowhm' and end_hours<'$nowhm'))) )");
				}
			}
            if(input('?param.cid') && input('param.cid')!==''){
				$cid = input('param.cid');
				//子分类
				$clist = Db::name('shop_category')->where('aid',aid)->where('pid',$cid)->column('id');
				if($clist){
					$clist2 = Db::name('shop_category')->where('aid',aid)->where('pid','in',$clist)->column('id');
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
			}
            if(input('?param.cid2') && input('param.cid2')!==''){
				$cid = input('param.cid2');
				//子分类
				$clist = Db::name('shop_category2')->where('aid',aid)->where('pid',$cid)->column('id');
				if($clist){
					$clist2 = Db::name('shop_category2')->where('aid',aid)->where('pid','in',$clist)->column('id');
					$cCate = array_merge($clist, $clist2, [$cid]);
					if($cCate){
						$whereCid = [];
						foreach($cCate as $k => $c2){
							$whereCid[] = "find_in_set({$c2},cid2)";
						}
						$where[] = Db::raw(implode(' or ',$whereCid));
					}
				} else {
					$where[] = Db::raw("find_in_set(".$cid.",cid2)");
				}
			}
			if(input('?param.gid') && input('param.gid')!=='') $where[] = Db::raw("find_in_set(".input('param.gid/d').",gid)");

			if(input('?param.wxvideo_status') && input('param.wxvideo_status')!==''){
				if(input('param.wxvideo_status') < 5){
					if(input('param.wxvideo_status') == 0){
						$where[] = ['wxvideo_product_id','=',''];
					}else{
						$where[] = ['wxvideo_edit_status','=',input('param.wxvideo_status')];
					}
				}else{
					$where[] = ['wxvideo_status','=',input('param.wxvideo_status')];
				}
			}

			if(getcustom('mendian_upgrade')){
				if(input('param.mdid')){
					$where[] = Db::raw("`bind_mendian_ids`='-1' or find_in_set(".input('param.mdid').",bind_mendian_ids)");
				}
			}
            //供应商编号搜索
            if(getcustom('product_supplier')){
                if(input('param.supplier_number')){
                    $where[] = ['supplier_number','like','%'.input('param.supplier_number').'%'];
                }
            }
            if(getcustom('shop_product_code_search')){
                $code = trim(input('param.code'));
                if($code){
                    $codeWhere = "procode like '%{$code}%' OR barcode like '%{$code}%'";
                    $proids = Db::name('shop_guige')->where('aid',aid)->where('barcode|procode','like','%'.$code.'%')->column('proid');
                    if($proids){
                        $proids = implode(',',array_unique($proids));
                        $codeWhere .= " OR id in ({$proids})";
                    }
                    $where[] = Db::raw($codeWhere);
                }
            }
            if(getcustom('supply_zhenxin')){
                if(input('?param.source') && input('param.source')!==''){
                	$source = input('param.source');
                	if($source == 'self'){
                		$where[] = ['issource','=',0];
                	}else{
                		$where[] = ['issource','=',1];
                		$where[] = ['source','=',$source];
                	}
                }
            }
            if(input('param.proids'))$where[] = ['id','in',input('param.proids')];
			$count = 0 + Db::name('shop_product')->where($where)->count();
			$data = Db::name('shop_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$cdata = Db::name('shop_category')->where('aid',aid)->column('name','id');
			if(bid > 0){
				$cdata2 = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->column('name','id');
			}
            $iscustomoption = 0;
			if(getcustom('business_copy_product')){
                $iscustomoption  = 1;
            }
			foreach($data as $k=>$v){
				$gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$v['id'])->select()->toArray();
				$ggdata = array();
				foreach($gglist as $gg){
					if($gg['stock']<$gg['stock_warning']){
						if(getcustom('product_stock_warning')){
							$ggdata[] = $gg['name'].' × '.$gg['stock'] .'<button class="layui-btn layui-btn-xs layui-btn-disabled" style="color:#333">￥'.$gg['sell_price'].'</button><font color="red"> (库存不足)</font>';
						}
					}else{
						$ggdata[] = $gg['name'].' × '.$gg['stock'] .' <button class="layui-btn layui-btn-xs layui-btn-disabled" style="color:#333">￥'.$gg['sell_price'].'</button>';
					}
				}
                $v['cid'] = explode(',',$v['cid']);
                $data[$k]['cname'] = null;
                if ($v['cid']) {
                    foreach ($v['cid'] as $cid) {
                        if($data[$k]['cname'])
                            $data[$k]['cname'] .= ' ' . $cdata[$cid];
                        else
                            $data[$k]['cname'] .= $cdata[$cid];
                    }
                }
				if($v['bid'] > 0){
					$v['cid2'] = explode(',',$v['cid2']);
					$data[$k]['cname2'] = null;
					if ($v['cid2']) {
						foreach ($v['cid2'] as $cid) {
							if($data[$k]['cname2'])
								$data[$k]['cname2'] .= ' ' . $cdata2[$cid];
							else
								$data[$k]['cname2'] .= $cdata2[$cid];
						}
					}
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['cname2'] = '';
					$data[$k]['bname'] = '平台自营';
				}
				$data[$k]['ggdata'] = implode('<br>',$ggdata);
				$sales_num = Db::name('shop_order_goods')->where('aid',aid)->where('proid',$v['id'])->where('status','in','1,2,3')->sum('num');
				$refund_num = Db::name('shop_refund_order_goods')
                    ->alias('rg')
                    ->join('shop_refund_order ro','rg.refund_orderid=ro.id')
                    ->join('shop_order o','ro.orderid=o.id')
                    ->where('o.status','in','1,2,3')
                    ->where('rg.aid',aid)->where('rg.proid',$v['id'])->where('ro.refund_status',2)->sum('rg.refund_num');
                $realsalenum = $sales_num-$refund_num;
				$data[$k]['realsalenum'] = $realsalenum>0?$realsalenum:0;
				if($v['status']==2){ //设置上架时间
					if(strtotime($v['start_time']) <= time() && strtotime($v['end_time']) >= time()){
						$data[$k]['status'] = 1;
					}else{
						$data[$k]['status'] = 0;
					}
				}
				if($v['status']==3){ //设置上架周期
					$start_time = strtotime(date('Y-m-d '.$v['start_hours']));
					$end_time = strtotime(date('Y-m-d '.$v['end_hours']));
					if(($start_time < $end_time && $start_time <= time() && $end_time >= time()) || ($start_time >= $end_time && ($start_time <= time() || $end_time >= time()))){
						$data[$k]['status'] = 1;
					}else{
						$data[$k]['status'] = 0;
					}
				}
				if($v['bid'] == -1) $data[$k]['sort'] = $v['sort'] - 1000000;
                $data[$k]['iscustomoption'] = $iscustomoption;
				if(getcustom('price_dollar')){
					$sysset = Db::name('shop_sysset')->field('usdrate')->where(['aid'=>aid])->find();
					$data[$k]['usdprice'] = $sysset['usdrate']>0 ? round($v['sell_price']/$sysset['usdrate'],2) : '';
				}
			}
			$page_total = ceil($count/$limit);
			$page = [
                    'current' => (int)$page,
                    'limit' => (int)$limit,
                    'pages' => $page_total,
                    'total' => $count,
                ];
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'page'=>$page]);
		}
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
		if(bid > 0){
			//商家的商品分类
			$clist2 = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray();
			foreach($clist2 as $k=>$v){
				$clist2[$k]['child'] = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
			}
			View::assign('clist2',$clist2);
		}
		//分组
		$glist = Db::name('shop_group')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('clist',$clist);
		View::assign('glist',$glist);

		$fromwxvideo = input('param.fromwxvideo')==1?true:false;
		View::assign('fromwxvideo',$fromwxvideo);
		
		if($fromwxvideo){
			$rs = curl_post('https://api.weixin.qq.com/shop/account/get_brand_list?access_token='.Wechat::access_token(aid,'wx'),'{}');
			$rs = json_decode($rs,true);
			$brand_list = $rs['data'];
		}else{
			$brand_list = [];
		}

		if(session('BST_ID')){
			$userlist = Db::name('admin_user')->field('id,aid,un')->where('id','<>',$this->user['id'])->where('bid',0)->where('isadmin',1)->select()->toArray();
			View::assign('cancopy',true);
		}else{
			$userlist = [];
			View::assign('cancopy',false);
		}

        if (getcustom('admin_user_group')){
            $groupArr = Db::name('admin_group')->order('sort desc,id desc')->column('name','id');
            View::assign('groupArr',$groupArr);
        }
	
		$default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
		$levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        
		View::assign('levellist',$levellist);
		$brand_list[] = ['brand_id'=>2100000000,'brand_wording'=>'无品牌'];
		View::assign('brand_list',$brand_list);
		View::assign('userlist',$userlist);
		View::assign('admin',$this->admin);
		$add_product = 1;//允许添加商品
        $status_product = 1;//允许上下架商品
        $stock_product = 1;//允许修改商品库存
		if(getcustom('product_sync_business')){
            //商家信息
            $business_lists = Db::name('business')->where('aid',aid)->where('sync_plate_product',1)->select()->toArray();
            View::assign('business_lists',$business_lists);
            if(bid>0){
                $bunsiness = Db::name('business')->where('aid',aid)->where('id',bid)->find();
                $add_product = $bunsiness['add_product'];//允许添加商品
                $status_product = $bunsiness['status_plate_product'];//允许上下架商品
                $stock_product = $bunsiness['stock_plate_product'];//允许修改商品库存
            }
        }
        View::assign('add_product',$add_product);
        View::assign('status_product',$status_product);
        View::assign('stock_product',$stock_product);
        View::assign('erpWdtOpen',$erpWdtOpen);

		return View::fetch();
    }
	//编辑商品
	public function edit(){
		if(input('param.id')){
			$info = Db::name('shop_product')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('商品不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
			if(bid != 0 && $info['linkid']!=0 && !getcustom('business_copy_product')) showmsg('无权限操作');
            $score_weishu = 0;
            if(getcustom('score_weishu')){
                $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
                $score_weishu = $score_weishu?$score_weishu:0;
            }
            if(getcustom('reward_business_score')){
                $info['reward_business_score'] = dd_money_format($info['reward_business_score'],$score_weishu);
            }
		}

		$usdrate = '0';
		if(getcustom('price_dollar')){
			$sysset = Db::name('shop_sysset')->field('usdrate')->where(['aid'=>aid])->find();
			$usdrate = $sysset['usdrate']>0 ? $sysset['usdrate'] : 0;
			View::assign('usdrate',$usdrate);
		}

        $score_weishu = 0;
        if(getcustom('score_weishu')){
            $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
            $score_weishu = $score_weishu?$score_weishu:0;
        }
		//多规格
		$newgglist = array();
		if($info){
            $info['givescore'] = dd_money_format($info['givescore'],$score_weishu);
			$gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$info['id'])->select()->toArray();
			foreach($gglist as $k=>$v){
				if(getcustom('price_dollar') && $usdrate>0){
					$v['usdprice'] = round($v['sell_price']/$usdrate,2);
				}
                if(getcustom('product_service_fee')){
                    $v['service_fee_data'] = json_decode($v['service_fee_data']);
                }
				$v['lvprice_data'] = json_decode($v['lvprice_data']);
                $v['givescore'] = dd_money_format($v['givescore'],$score_weishu);
                $caneditstock = 1;
                if(getcustom('erp_wangdiantong') && $v['wdt_status']==2){
                    $caneditstock = 0;
                }
                $v['caneditstock'] = $caneditstock;
				if($v['ks']!==null){
					$newgglist[$v['ks']] = $v;
				}else{
					Db::name('shop_guige')->where('aid',aid)->where('id',$v['id'])->update(['ks'=>$k]);
					$newgglist[$k] = $v;
				}
                $gglist[$k]['givescore'] = $v['givescore'];
			}
            $commissiondata3 = json_decode($info['commissiondata3'],true);
			foreach($commissiondata3 as $levelid=>$commission){
                $commissiondata3[$levelid]['commission1'] = dd_money_format($commission['commission1'],$score_weishu);
                $commissiondata3[$levelid]['commission2'] = dd_money_format($commission['commission2'],$score_weishu);
                $commissiondata3[$levelid]['commission3'] = dd_money_format($commission['commission3'],$score_weishu);
            }
            $info['commissiondata3'] = jsonEncode($commissiondata3);
		}
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
		}
		//分组
		$glist = Db::name('shop_group')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		$freightdata = array();
		if($info && $info['freightdata']){
			$freightdata = Db::name('freight')->where('aid',aid)->where('id','in',$info['freightdata'])->order('sort desc,id')->select()->toArray();
		}

		$weightdata = array();
		if(getcustom('weight_template')){
			if($info && $info['weightdata']){
				$weightdata = Db::name('shop_weight_template')->where('aid',aid)->where('id','in',$info['weightdata'])->order('sort desc,id')->select()->toArray();
			}
		}

		$bset = Db::name('business_sysset')->where('aid',aid)->find();
		//分成结算类型
        $sysset = Db::name('admin_set')->where('aid',aid)->find();
        if($sysset['fxjiesuantype'] == 1) {
            $jiesuantypeDesc = '成交价';
        }elseif($sysset['fxjiesuantype'] == 2) {
            $jiesuantypeDesc = '销售利润';
        } else {
            $jiesuantypeDesc = '销售价';
        }

		if($info['showtj'] != '') $info['showtj'] = explode(',',$info['showtj']);//0 关注用户
		if($info['gettj'] != '') $info['gettj'] = explode(',',$info['gettj']);
        if($info['cid']) $info['cid'] = explode(',',$info['cid']);
        if($info['cid2']) $info['cid2'] = explode(',',$info['cid2']);
        if($info['commission_mid'])  $info['commission_mid'] = $info['commission_mid'] ? json_decode($info['commission_mid'],true) : [];
		if($info['bid'] == -1) $info['sort'] = $info['sort'] - 1000000;
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        if(getcustom('plug_businessqr') && bid != 0) {
            $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('show_business',1)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
            $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('show_business',1)->order('sort,id')->select()->toArray();
        } else {
            $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
            $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        }
		$gdlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('fenhong','>','0')->order('sort,id')->select()->toArray();
		$teamlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('teamfenhonglv','>','0')->order('sort,id')->select()->toArray();
		$areafhlevellist = Db::name('member_level')->where('aid',aid)->where('areafenhong','>','0')->select()->toArray();
        $gdlevellist_huiben = [];
		if(getcustom('fenhong_gudong_huiben')){
            $gdlevellist_huiben = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('fenhong_huiben','>','0')->order('sort,id')->select()->toArray();

        }
		if(getcustom('teamfenhong_pingji')){
			$teampjlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('teamfenhong_pingji_lv','>','0')->order('sort,id')->select()->toArray();
			View::assign('teampjlevellist',$teampjlevellist);
		}
        if(getcustom('teamfenhong_jiandan')){
			$teamjdlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('teamfenhong_jiandan_lv','>','0')->order('sort,id')->select()->toArray();
			View::assign('teamjdlevellist',$teamjdlevellist);
		}

		if($info['id']){
			$fuwulist = Db::name('shop_fuwu')->where('aid',aid)->where('bid',$info['bid'])->order('sort desc,id')->select()->toArray();
		}else{
			$fuwulist = Db::name('shop_fuwu')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->select()->toArray();
		}
        if(getcustom('everyday_hongbao')) {
            $hset = \db('hongbao_everyday')->where('aid',aid)->find();
            View::assign('ehb_status',$hset['status']);
        }
        if(getcustom('product_bind_mendian')){
			$whereb = [];
			$whereb[] = ['aid','=',aid];
			$whereb[] = ['status','=',1];
			$bindBid = bid;
			if($info && $info['bid']!=bid){
				$bindBid = $info['bid'];
			}
			if(getcustom('business_platform_auth')){
				if($bindBid===0){
					$business = Db::name('business')->where('aid',aid)->where('isplatform_auth',1)->where('status',1)->column('id');		
					array_push($business,$bindBid);
					$whereb[] = ['bid','in',$business];
				}else{
					$whereb[] = ['bid','=',$bindBid];
				}
			}else{
				$whereb[] = ['bid','=',$bindBid];
			}
		
            $mendianlist = Db::name('mendian')->where($whereb)->order('bid')->select()->toArray();
            View::assign('mendianlist',$mendianlist);
        }

        if(getcustom('teamfenhong_jicha')){
            $teamjichalevellist = Db::name('member_level')->where('aid',aid)
                ->where('cid', $default_cid)
//                ->where('teamfenhong_jiandan_lv','>','0')
                ->order('sort,id')->select()->toArray();
            View::assign('teamjichalevellist',$teamjichalevellist);
        }

		//商品参数
		$parambid = bid;
		if(getcustom('business_useplatmendian') && $parambid > 0){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			if($bset['business_useplatshopparam'] == 1){
				$parambid = 0;
			}
		}
        $whereParam = [];
        $whereParam[] = ['aid','=',aid];
        $whereParam[] = ['status','=',1];
        if($info['cid']){
            $whereCid = [];
            foreach($info['cid'] as $k => $c2){
                if($c2 == '') continue;
                $whereCid[] = "find_in_set({$c2},cid)";
            }
            if($whereCid){
				if(getcustom('business_showplatparam') && $parambid > 0){
					$whereParam[] = Db::raw("(bid=0 and (".implode(' or ',$whereCid).")) or (bid=".$parambid." and (".implode(' or ',$whereCid). " or cid =''))");
				}else{
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
//        dd($paramList);
		View::assign('paramList',$paramList);
	
    $storelvlist = Db::name('member_store')->where('aid',aid)->order('sort,id')->select()->toArray();
    View::assign('storelvlist',$storelvlist);

    $teamlvlist = Db::name('member_team_level')->where('aid',aid)->where('teamfenhonglv','>','0')->order('sort,id')->select()->toArray();


    View::assign('teamlvlist',$teamlvlist);






		$paramdata = $info['paramdata'] ? json_decode($info['paramdata'],true) : [];
//        dd($paramdata);
        $auth = [];
        $info['paramdata'] = str_ireplace("'", "\'", $info['paramdata']);
        if(getcustom('product_bonus_pool')){
            $info['bonus_pool_releasetj'] = explode(',',$info['bonus_pool_releasetj']);
        }
        if(getcustom('member_realname_verify')){
            $info['limittj'] = explode(',',$info['limittj']);
        }
        if(getcustom('member_realname_verify')){
            $member_realname_set = Db::name('member_realname_set')->where('aid',aid)->find();
            View::assign('member_realname_set',$member_realname_set);
        }
        if(getcustom('member_levelup_givechild')){
            $team_levelup_data = json_decode($info['team_levelup_data'],true);
            View::assign('team_levelup_data',$team_levelup_data);
        }
        $requiredField = [];
        /*if(getcustom('erp_wangdiantong')){
            $requiredField[] = 'procode';
            $requiredField[] = 'barcode';
        }*/
       
        if(getcustom('yx_queue_free')){
            if(!$info['id']){
                $queue_free_join = Db::name('queue_free_set')->where('aid',aid)->where('bid',0)->value('product_join');
                $info['queue_free_status'] = $queue_free_join;
            }
        }
		View::assign('requiredField',$requiredField);
		View::assign('fuwulist',$fuwulist);
		View::assign('aglevellist',$aglevellist);
		View::assign('levellist',$levellist);
		View::assign('gdlevellist',$gdlevellist);
        View::assign('gdlevellist_huiben',$gdlevellist_huiben);
		View::assign('teamlevellist',$teamlevellist);
		View::assign('areafhlevellist',$areafhlevellist);
		View::assign('info',$info);
		View::assign('newgglist',$newgglist);
		View::assign('clist',$clist);
		View::assign('clist2',$clist2);
		View::assign('glist',$glist);
		View::assign('freightdata',$freightdata);
		View::assign('bset',$bset);
        View::assign('jiesuantypeDesc',$jiesuantypeDesc);
		View::assign('paramdata',$paramdata);
		$fromwxvideo = input('param.fromwxvideo')==1?true:false;
		
		if($fromwxvideo){
			$rs = curl_post('https://api.weixin.qq.com/shop/account/get_brand_list?access_token='.Wechat::access_token(aid,'wx'),'{}');
			$rs = json_decode($rs,true);
			$brand_list = $rs['data'];
		}else{
			$brand_list = [];
		}
		$brand_list[] = ['brand_id'=>2100000000,'brand_wording'=>'无品牌'];
		View::assign('brand_list',$brand_list);
		View::assign('fromwxvideo',$fromwxvideo);

        if(getcustom('pay_yuanbao')) {
            $yuanbao_money_ratio = 0;
            $sysset = Db::name('admin_set')->where('aid',aid)->find();
            if($sysset){
                $yuanbao_money_ratio = $sysset['yuanbao_money_ratio'];
            }
            View::assign('yuanbao_money_ratio',$yuanbao_money_ratio);
        }
        if(getcustom('plug_tengrui')){
            $groupdata = array();
            if($info && $info['group_ids']){
                $groupdata = Db::name('member_tr_group')->where('aid',aid)->where('id','in',$info['group_ids'])->order('id desc')->select()->toArray();
            }
            View::assign('groupdata',$groupdata);
        }
        if(getcustom('diy_light')){
            if($this->auth_data == 'all' || in_array('Backstage/diylight',$this->auth_data)){
                $set = Db::name('diylight_set')->where('aid',aid)->find();
                if($set['status'] == 1){
                    $auth['diy_light'] = true;
                }
            }
        }

        View::assign('auth',$auth);
		
		$business_selfscore = 0;
		if((getcustom('business_selfscore') || getcustom('business_score_jiesuan')) && bid > 0){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			$business_selfscore = $bset['business_selfscore'];
		}

        View::assign('business_selfscore',$business_selfscore);

        if(getcustom('buybutton_custom')){
			$buybtn_status = $this->admin['buybtn_status']?$this->admin['buybtn_status']:0;
			if($buybtn_status && bid !=0){
				$buybtn_status = 0;
				$business = Db::name('business')->where('aid',aid)->where('id',bid)->field('id,buybtn_status')->find();
				if($business && $business['buybtn_status'] ==1){
					$buybtn_status = 1;
				}
			}
			View::assign('buybtn_status',$buybtn_status);
		}
        if(getcustom('addcart_button_custom')){
            $addcart_button_custom_status = 0;
            if($this->auth_data == 'all' || in_array('ShopProduct/addcart_button_custom',$this->auth_data)){
                $addcart_button_custom_status = 1;
            }
            View::assign('addcart_button_custom_status',$addcart_button_custom_status);
        }

        if(getcustom('product_bonus_pool')){
            $bonus_pool_status = Db::name('admin')->where('id',aid)->value('bonus_pool_status');
            $product_bonus_pool_status = 0;
            if(($this->auth_data == 'all' || in_array('ShopProduct/product_bonus_pool',$this->auth_data) )&& $bonus_pool_status){
                $product_bonus_pool_status = 1;
            }
            View::assign('product_bonus_pool_status',$product_bonus_pool_status);
            $bonuspoolclist = Db::name('bonus_pool_category')->where('aid',aid)->where('bid',bid)->where('status',1)->order('sort desc,id desc')->select()->toArray();
            View::assign('bonuspoolclist',$bonuspoolclist);
        }
		if(getcustom('weight_template')){
			View::assign('weightdata',$weightdata);
		}
		if(getcustom('product_memberlevel_limit')){
			$levellimitdata = json_decode($info['levellimitdata'],true);
			foreach($levellimitdata as $k=>$g){
				$level = Db::name('member_level')->field('id,name')->where('aid',aid)->where('id', $g['level_id'])->find();
				$levellimitdata[$k]['name'] = $level['name'];
				$levellimitdata[$k]['days'] = $g['days'];
				$levellimitdata[$k]['limitnum'] = $g['limitnum'];
			}
			View::assign('levellimitdata',$levellimitdata);      
		}

        if(getcustom('commission_butie')){
            $commissionbutie = json_decode($info['commissionbutie'],true);
            $commissionbutie2 = json_decode($info['commissionbutie2'],true);

            View::assign('commissionbutie',$commissionbutie);
            View::assign('commissionbutie2',$commissionbutie2);
        }
        if(getcustom('zhitui_pj')){
            $zhitui_pj = json_decode($info['zhitui_pj'],true);
            View::assign('zhitui_pj',$zhitui_pj);
        }
		$showmendian_upgrade = false;
		if(getcustom('mendian_upgrade')){
			$admin =  Db::name('admin')->field('mendian_upgrade_status')->where('id',aid)->find();
			if($admin['mendian_upgrade_status']==1){
				$showmendian_upgrade = true;
				$mendianlevellist =  Db::name('mendian_level')->field('id,name')->where('aid',aid)->select()->toArray();
				View::assign('mendianlevellist',$mendianlevellist);
				View::assign('showmendian_upgrade',$showmendian_upgrade);
			}
		}
		if(getcustom('level_teamfenhong')){
        	$levelteamfenhongs = $info['levelteamfenhongs']?json_decode($info['levelteamfenhongs'],true):$info['levelteamfenhongs'];
        	View::assign('levelteamfenhongs',$levelteamfenhongs);
        }
        if(getcustom('member_level_parent_not_commission')){
            $parent_not_commission_json = $info['parent_not_commission_json']?json_decode($info['parent_not_commission_json'],true):$info['parent_not_commission_json'];
            View::assign('parent_not_commission_json',$parent_not_commission_json);
        }
		//是否开启佣金上限
		$member_commission_max = 0;
		if(getcustom('member_commission_max')){
            if($sysset['member_commission_max']){
                $member_commission_max = 1;
            }
        }
		View::assign('member_commission_max',$member_commission_max);
		if(getcustom('shop_label')){
			//分类
			$labels = Db::name('shop_label')->Field('id,name')->where('aid',aid)->order('sort desc,id desc')->select()->toArray();
			View::assign('labels',$labels);
		}

		$canaddgg      = true;//是否能添加规格
		$canaddfreight = true;//是否能添加模板
		if(getcustom('supply_zhenxin')){
			$iszhenxin = false;
			//甄新汇选端商品规格不能随意变动
			if($info['sproid']>0 && $info['source'] == 'supply_zhenxin'){
				$iszhenxin = true;
				$canaddgg  = false;
				$canaddfreight = false;
			}
			View::assign('iszhenxin',$iszhenxin);
		}
		View::assign('canaddgg',$canaddgg);
		View::assign('canaddfreight',$canaddfreight);

        if(getcustom('member_goldmoney_silvermoney')){
            $ShopSendGoldmoney   = false;//赠送金值权限
            $ShopSendSilvermoney = false;//赠送银值权限
            if(!bid){
                if($this->auth_data == 'all' || in_array('ShopSendGoldmoney',$this->auth_data)){
                    $ShopSendGoldmoney  = true;
                }
                
                if($this->auth_data == 'all' || in_array('ShopSendSilvermoney',$this->auth_data)){
                    $ShopSendSilvermoney = true;
                }
            }
            View::assign('ShopSendGoldmoney',$ShopSendGoldmoney);
            View::assign('ShopSendSilvermoney',$ShopSendSilvermoney);
        }
        if(getcustom('maidan_fenhong_new')){
            if(bid>0){
                $business = Db::name('business')->where('aid',aid)->where('id',bid)->find();
                if($business['cost_bili_with_edit'] === 0){
                    //设置成本比例后，是否允许商户修改商品成本，0关闭后商家不可修改
                    View::assign('costPriceDisabled',true);
                }
            }
        }
        if(getcustom('team_jiandian')){
            $teamjiandianlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
            View::assign('teamjiandianlevellist',$teamjiandianlevellist);
        }
		return View::fetch();
	}
    public function getParam()
    {
        $cid = input('post.cid');
        //商品参数
		$parambid = bid;
		if(getcustom('business_useplatmendian') && $parambid > 0){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			if($bset['business_useplatshopparam'] == 1){
				$parambid = 0;
			}
		}
        $whereParam = [];
        $whereParam[] = ['aid','=',aid];
        $whereParam[] = ['status','=',1];
        if($cid){
            $cid = explode(',',$cid);
            $whereCid = [];
            foreach($cid as $k => $c2){
                $whereCid[] = "find_in_set({$c2},cid)";
            }
			if(getcustom('business_showplatparam') && $parambid > 0){
				$whereParam[] = Db::raw("(bid=0 and (".implode(' or ',$whereCid).")) or (bid=".$parambid." and (".implode(' or ',$whereCid). " or cid =''))");
			}else{
				$whereParam[] = ['bid','=',$parambid];
				$whereParam[] = Db::raw(implode(' or ',$whereCid). " or cid =''");
			}
        }else{
			$whereParam[] = ['bid','=',$parambid];
            $whereParam[] = Db::raw(" cid =''");
        }

        $paramList = Db::name('shop_param')->where($whereParam)->order('sort desc,id')->select()->toArray();
        $paramList = $paramList ? $paramList : [];
//        dd($paramList);

        return json(['status'=>1,'msg'=>'操作成功','paramlist'=>$paramList]);
    }
	//保存商品
	public function save(){
		if(input('post.id')){
			$product = Db::name('shop_product')->where('aid',aid)->where('id',input('post.id/d'))->find();
			if(!$product) showmsg('商品不存在');
			if(bid != 0 && $product['bid']!=bid) showmsg('无权限操作');
		}
		$info = input('post.info/a');
		if(intval($info['sort']) >= 1000000) showmsg('商品序号不能大于1000000');

        $score_weishu = 0;
        if(getcustom('score_weishu')){
            $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
            $score_weishu = $score_weishu?$score_weishu:0;
        }
        if(bid > 0){
            $bset = Db::name('business_sysset')->where('aid',aid)->find();
        }
		$business_selfscore = 0;
		if((getcustom('business_selfscore') || getcustom('business_score_jiesuan')) && bid > 0){
			$business_selfscore = $bset['business_selfscore'];
		}
		
		$info['detail'] = \app\commons\Common::geteditorcontent($info['detail']);
		$data = array();
		$data['name'] = $info['name'];
		$data['print_name'] = $info['print_name'];
		$data['pic'] = $info['pic'];
		$data['pics'] = $info['pics'];
        $data['diypics'] = $info['diypics'];
		$data['procode'] = $info['procode'];
		$data['sellpoint'] = $info['sellpoint'];
		$data['cid'] = $info['cid'];
		if(bid > 0){
			$data['cid2'] = $info['cid2'];
		}
        $data['price_type'] = $info['price_type'];
		$data['freighttype'] = $info['freighttype'];
		$data['freightdata'] = $info['freightdata'];
        $data['freightcontent'] = $info['freightcontent'];
        $data['contact_require'] = intval($info['contact_require']);
		
        if(bid > 0 && $bset['commission_canset']!=1){
			$data['commissionset'] = -1;
        }else{
			$data['commissionset'] = $info['commissionset'];
		}
        $commissiondata3 = input('post.commissiondata3/a');
        foreach($commissiondata3 as $levelid=>$commission){
            $commissiondata3[$levelid]['commission1'] = dd_money_format($commission['commission1'],$score_weishu);
            $commissiondata3[$levelid]['commission2'] = dd_money_format($commission['commission2'],$score_weishu);
            $commissiondata3[$levelid]['commission3'] = dd_money_format($commission['commission3'],$score_weishu);
        }

		$data['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
		$data['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
		$data['commissiondata3'] = jsonEncode($commissiondata3);
        $data['commissiondata4'] = jsonEncode(input('post.commissiondata4/a'));
        $data['score_data6'] = jsonEncode(input('post.score_data6/a'));	
		$data['score_data7'] = jsonEncode(input('post.score_data7/a'));	
    	$data['commissiondata6'] = jsonEncode(input('post.commissiondata6/a'));
     	$data['commissiondata7'] = jsonEncode(input('post.commissiondata7/a'));	
	
		$data['commissionset4'] = $info['commissionset4'];
     	$data['commissionsettype'] = $info['commissionsettype']??0;
     	
     	$data['fenhongdata1'] = jsonEncode(input('post.fenhongdata1/a'));
     	$data['fenhongdata2'] = jsonEncode(input('post.fenhongdata2/a'));	
		$data['tongji_1'] = $info['tongji_1'];
		$data['tongji_2'] = $info['tongji_2'];
// 		$data['tongji_3'] = $info['tongji_3'];
// 		$data['tongji_4'] = $info['tongji_4'];
    	
		$data['lvprice'] = $info['lvprice'];
		$data['showtj'] = implode(',',$info['showtj']);
		$data['gettj'] = implode(',',$info['gettj']);


        if(getcustom('gongpai')){
            $data['gongpai'] = $info['gongpai'];
        }
                



		if(getcustom('commission_parent_pj')){
			$data['commissionpingjiset'] = $info['commissionpingjiset'];
			$data['commissionpingjidata1'] = jsonEncode(input('post.commissionpingjidata1/a'));
			$data['commissionpingjidata2'] = jsonEncode(input('post.commissionpingjidata2/a'));
		}
		if(getcustom('commission_butie')){
            $data['commissionbutie'] = jsonEncode(input('post.commissionbutie/a'));
            $data['commissionbutie2'] = jsonEncode(input('post.commissionbutie2/a'));
        }
		//if(getcustom('product_glass') || getcustom('product_weight')){
		    $data['product_type'] = $info['product_type'];
        //}
        if(getcustom('product_commission_mid')){
            $data['commission_mid'] = jsonEncode(input('post.commission_mid/a'));
        }
        if(getcustom('shop_yuding')){//预定库存
            $data['yuding_stock'] = $info['yuding_stock'];
        }
		
		if(getcustom('freight_selecthxbids') && bid==0){
			$data['isjici'] = $info['isjici'];
		}
		
        if(getcustom('product_bind_mendian')){
            $data['bind_mendian_ids'] = $info['bind_mendian_ids']?implode(',',$info['bind_mendian_ids']):'-1';
        }
        if(getcustom('product_field_buy')){
        	$data['guige']= $info['guige']?$info['guige']:'';
            $data['brand'] = $info['brand']?$info['brand']:'';
            $data['unit']  = $info['unit']?$info['unit']:'';
            $data['valid_time']  = $info['valid_time']?$info['valid_time']:'';
            $data['remark']  = $info['remark']?$info['remark']:'';
        }
		if(getcustom('weight_template')){
			$data['weighttype'] = $info['weighttype'];
			$data['weightdata'] = $info['weightdata'];
		}
        //购车基金
        if(getcustom('teamfenhong_gouche')){
            $data['gouchebonusset'] = $info['gouchebonusset']??0;
            $data['gouchebonusdata1'] = jsonEncode(input('post.gouchebonusdata1/a'));
            $data['gouchebonusdata2'] = jsonEncode(input('post.gouchebonusdata2/a'));
        }
        //旅游基金
        if(getcustom('teamfenhong_lvyou')){
            $data['lvyoubonusset'] = $info['lvyoubonusset']??0;
            $data['lvyoubonusdata1'] = jsonEncode(input('post.lvyoubonusdata1/a'));
            $data['lvyoubonusdata2'] = jsonEncode(input('post.lvyoubonusdata2/a'));
        }
        //团队分红伯乐奖
        if(getcustom('teamfenhong_bole')){
            $data['teamfenhongblset'] = $info['teamfenhongblset']??0;
            $data['teamfenhongbldata1'] = jsonEncode(input('post.teamfenhongbldata1/a'));
            $data['teamfenhongbldata2'] = jsonEncode(input('post.teamfenhongbldata2/a'));
        }
        if(getcustom('teamfenhong_jiandan')){
            $teamfenhongjddata1 = input('post.teamfenhongjddata1/a');
            $teamfenhongjddata2 = input('post.teamfenhongjddata2/a');
            $data['teamfenhongjdset'] = $info['teamfenhongjdset']??0;
            $data['teamfenhongjddata1'] = $teamfenhongjddata1?jsonEncode($teamfenhongjddata1):'';
            $data['teamfenhongjddata2'] = $teamfenhongjddata2?jsonEncode($teamfenhongjddata2):'';
        }
        if(getcustom('product_keyword')){
            $data['keyword'] = $info['keyword']??'';
        }
        if(getcustom('discount_code_zhongchuang')){
            $data['price_discount_code_zc'] = $info['price_discount_code_zc']??null;
        }
        //加权分红状态
        if(getcustom('fenhong_jiaquan_bylevel')){
            $data['fenhong_jq_status'] = $info['fenhong_jq_status'];
        }
        //团队级差分红奖
        if(getcustom('teamfenhong_jicha')){
            $data['teamjichaset'] = $info['teamjichaset']??0;
            $data['teamjichadata1'] = jsonEncode(input('post.teamjichadata1/a'));
            $data['teamjichadata2'] = jsonEncode(input('post.teamjichadata2/a'));
            $data['teamjichapjset'] = $info['teamjichapjset']??0;
            $data['teamjichapjdata1'] = jsonEncode(input('post.teamjichapjdata1/a'));
            $data['teamjichapjdata2'] = jsonEncode(input('post.teamjichapjdata2/a'));
        }
        //团队级差分红奖
        if(getcustom('team_leader_fh')){
            $data['teamleader_fenhongset'] = $info['teamleader_fenhongset']??0;
            $data['teamleader_fenhongdata1'] = jsonEncode(input('post.teamleader_fenhongdata1/a'));
            $data['teamleader_fenhongdata2'] = jsonEncode(input('post.teamleader_fenhongdata2/a'));
        }
        if(getcustom('level_teamfenhong')){
        	$data['level_teamfenhongset'] = $info['level_teamfenhongset']?$info['level_teamfenhongset']:0;
        	$data['levelteamfenhongs']    = jsonEncode(input('post.levelteamfenhongs/a'));
        }
        if(getcustom('product_xieyi')){
            //商品协议
            $data['xieyi_id'] = $info['xieyi_id'];
        }
        //供应商
        if(getcustom('product_supplier')){
            $data['supplier_id'] = $info['supplier_id'];
            //查询供应商编号
            if($info['supplier_id']){
                $data['supplier_number'] = Db::name('product_supplier')->where('id',$info['supplier_id'])->value('supplier_number');
            }
        }
        if(getcustom('zhongkang_sync')){
            $data['zhongkang_appid'] = $info['zhongkang_appid'];
            $data['zhongkang_levelid'] = $info['zhongkang_levelid'];
        }
        if(getcustom('member_level_parent_not_commission')){
            $data['parent_not_commission_json']    = jsonEncode(input('post.parent_not_commission_json/a'));
        }
		if(bid == 0){
			$data['fenhongset'] = $info['fenhongset'];
			$data['gdfenhongset'] = $info['gdfenhongset'];
			$data['gdfenhongdata1'] = jsonEncode(input('post.gdfenhongdata1/a'));
			$data['gdfenhongdata2'] = jsonEncode(input('post.gdfenhongdata2/a'));
			$data['teamfenhongset'] = $info['teamfenhongset'];
			$data['teamfenhongdata1'] = jsonEncode(input('post.teamfenhongdata1/a'));
			$data['teamfenhongdata2'] = jsonEncode(input('post.teamfenhongdata2/a'));
			$data['areafenhongset'] = $info['areafenhongset'];
			$data['areafenhongdata1'] = jsonEncode(input('post.areafenhongdata1/a'));
			$data['areafenhongdata2'] = jsonEncode(input('post.areafenhongdata2/a'));
			if(getcustom('teamfenhong_pingji')){
				$data['teamfenhongpjset'] = $info['teamfenhongpjset'];
				$data['teamfenhongpjdata1'] = jsonEncode(input('post.teamfenhongpjdata1/a'));
				$data['teamfenhongpjdata2'] = jsonEncode(input('post.teamfenhongpjdata2/a'));
			}
            if(getcustom('fenhong_gudong_huiben')){
                $data['gdfenhongset_huiben'] = $info['gdfenhongset_huiben']??'';
                $data['gdfenhongdata1_huiben'] = jsonEncode(input('post.gdfenhongdata1_huiben/a'));
                $data['gdfenhongdata2_huiben'] = jsonEncode(input('post.gdfenhongdata2_huiben/a'));
            }
		}
		
		if(getcustom('mendian_upgrade')){
			$admin =  Db::name('admin')->where('id',aid)->field('mendian_upgrade_status')->find();
			if($admin['mendian_upgrade_status']==1){
				$data['mendian_hexiao_set'] = $info['mendian_hexiao_set'];
				$data['mendianhexiaodata1'] = jsonEncode(input('post.mendianhexiaodata1/a'));
				$data['mendianhexiaodata2'] = jsonEncode(input('post.mendianhexiaodata2/a'));
			}
		}

		if(getcustom('product_fenzhangmoney')){
			$data['product_fenzhangmoney'] = $info['product_fenzhangmoney'];
		}
        if(getcustom('active_coin')){
            $data['teamfenhongwalletset'] = $info['teamfenhongwalletset'];
            $data['teamfenhongwallet'] = jsonEncode(input('post.teamfenhongwallet/a'));
        }


		if(bid != 0){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
            $business  = Db::name('business')->where('aid',aid)->where('id',bid)->find();
			if($bset['commission_canset']==0){
				$data['commissionset'] = '-1';
			}
            if(getcustom('business_product_isexamine')){
                if($business['is_open_commission'] !=1 ){
                    $data['commissionset'] = -1;
                }else{
                    $data['commissionset'] = 0;
                }
            }
			if($bset['product_showset']==0){
				$data['showtj'] = '-1';
				$data['gettj'] = '-1';
				$data['lvprice'] = 0;
			}
		}
        if(getcustom('member_levelup_givechild')){
            $data['team_levelup_data'] = '';
            if($info['give_team_levelup'] == 1){
                $team_levelup_data = input('post.team_levelup_data/a');           
                $data['team_levelup_data'] = json_encode($team_levelup_data,JSON_UNESCAPED_UNICODE);
            }
            $data['give_team_levelup'] = $info['give_team_levelup'];
        }
		
		$data['video'] = $info['video'];
		$data['video_duration'] = $info['video_duration'];
		$data['perlimit'] = $info['perlimit'];
		$data['perlimitdan'] = $info['perlimitdan'];
		$data['limit_start'] = $info['limit_start'];
		if(bid == 0 || $business_selfscore == 1){
			$data['scoredkmaxset'] = $info['scoredkmaxset'];
			$data['scoredkmaxval'] = $info['scoredkmaxval'];
            $data['feepercent'] = $info['feepercent'] == '' || $info['feepercent'] < 0 ? null : $info['feepercent'];//商品独立抽成费率
		}
		if(getcustom('credit_dk')){
    		if(bid == 0 ){
    			$data['creditdkmaxset'] = $info['creditdkmaxset'];
    			$data['creditdkmaxval'] = $info['creditdkmaxval'];
               
    		}
		}
		if($info['oldsales'] != $info['sales']){
			$data['sales'] = $info['sales'];
		}
		$data['sort'] = $info['sort'];
		$data['status'] = $info['status'];
		$data['start_time'] = $info['start_time'];
		$data['end_time'] = $info['end_time'];
		$data['start_hours'] = $info['start_hours'];
		$data['end_hours'] = $info['end_hours'];

		$data['detail'] = $info['detail'];

		
		$data['sharetitle'] = $info['sharetitle'];
		$data['sharepic'] = $info['sharepic'];
		$data['sharedesc'] = $info['sharedesc'];
		$data['sharelink'] = $info['sharelink'];

		if(getcustom('product_givescore_time') && (bid == 0 || $business_selfscore == 1)){
			$data['givescore_time'] = $info['givescore_time'];
		}

		if($info['gid']){
			$data['gid'] = implode(',',$info['gid']);
		}else{
			$data['gid'] = '';
		}
		if($info['fwid']){
			$data['fwid'] = implode(',',$info['fwid']);
		}else{
			$data['fwid'] = '';
		}
		if(!$product) $data['createtime'] = time();
		$data['gettjtip'] = $info['gettjtip'];
		$data['gettjurl'] = $info['gettjurl'];

		if(getcustom('payaftertourl')){
			$data['payaftertourl'] = $info['payaftertourl'];
			$data['payafterbtntext'] = $info['payafterbtntext'];
		}
		if(getcustom('product_payaftergive')){
			$data['paygive_choujiangtimes'] = $info['paygive_choujiangtimes'];
			$data['paygive_choujiangid'] = $info['paygive_choujiangid'];
			$data['paygive_money'] = $info['paygive_money'];
			$data['paygive_score'] = $info['paygive_score'];
			$data['paygive_couponid'] = $info['paygive_couponid'];
		}
		if(getcustom('to86yk')){
			$data['to86yk_tid'] = $info['to86yk_tid'];
		}
		$data['no_discount'] = $info['no_discount'];
        $data['barcode'] = $info['barcode'];

        if(getcustom('everyday_hongbao')) {
            $data['everyday_hongbao_bl'] = $info['everyday_hongbao_bl'] != '' ? $info['everyday_hongbao_bl'] : null;
        }
		if(getcustom('fengdanjiangli')){
		    $data['fengdanjiangli'] = $info['fengdanjiangli'] ? $info['fengdanjiangli'] : '';
		}
		if(getcustom('pay_yuanbao')) {
            $data['yuanbao'] = $info['yuanbao'];
        }
        if(getcustom('plug_tengrui')){
            $data['is_rzh']        = $info['is_rzh'];
            $data['house_status']  = $info['house_status'];
            $data['group_status']  = $info['group_status'];
            $data['group_ids']     = $info['group_ids'];
            $data['relation_type'] = $info['relation_type'];
        }
		if(getcustom('product_moneypay')) {
            $data['product_moneypay'] = $info['product_moneypay'];
        }
		if(getcustom('shop_product_recommend')) {
            $data['show_recommend'] = $info['show_recommend'];
            $data['recommend_productids'] = $info['recommend_productids'];
        }
		if(getcustom('product_baodan')){
            $data['product_baodan'] = $info['product_baodan'];
        }
        if(getcustom('product_unit')){
            $data['product_unit'] = $info['product_unit'];
        }
        if(getcustom('reward_business_score')){
            $data['reward_business_score'] = $info['reward_business_score'];
        }

		//会员等级限购
		if(getcustom('product_memberlevel_limit')){
			$level_ids = input('post.level_id/a');
			$days = input('post.days/a');
			$limit_num = input('post.limit_num/a');
			$levellimitdata = [];
			foreach($level_ids as $k=>$v){
				$levellimitdata[] = ['level_id'=>$v,'days'=>$days[$k],'limit_num'=>$limit_num[$k]];
			}
			$data['levellimitdata'] = json_encode($levellimitdata);
			//dump($info);
		}
	
		if($info['wxvideo_third_cat_id']) $data['wxvideo_third_cat_id'] = $info['wxvideo_third_cat_id'];
		if($info['wxvideo_brand_id']) $data['wxvideo_brand_id'] = $info['wxvideo_brand_id'];
		if($info['wxvideo_qualification_pics']) $data['wxvideo_qualification_pics'] = $info['wxvideo_qualification_pics'];
        $data['fx_differential'] = $info['fx_differential'];

		if($info['lvprice']==1){
            $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
            $default_cid = $default_cid ? $default_cid : 0;
            $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
			$defaultlvid = $levellist[0]['id'];
			$sellprice_field = 'sell_price_'.$defaultlvid;
            if(getcustom('product_service_fee') && $info['service_fee_switch'] == 1) {
                $service_fee_field = 'service_fee_' . $defaultlvid;
            }
		}else{
			$sellprice_field = 'sell_price';
            if(getcustom('product_service_fee')) {
                $service_fee_field = 'service_fee';
            }
		}
        $cost_bili = 0;
		if(getcustom('maidan_fenhong_new')){
		    if(bid>0){
		        $cost_bili = $business['cost_bili'];
            }else{
                $cost_bili = Db::name('shop_sysset')->where('aid',aid)->value('cost_bili');
            }
        }
		$sell_price = 0;$market_price = 0;$cost_price = 0;$pv=0;$givecredit1=0;$givecredit2=0;$weight = 0;$givescore=0;$lvprice_data = [];$givetongzheng=0;$give_commission_max=0;
		$give_green_score = 0;
		$give_bonus_pool = 0;
		if(getcustom('product_handwork')){
			$hand_fee = 0;
		}
        if(getcustom('product_service_fee')){
            $service_fee = 0;
            $service_fee_data = [];
        }
		$i=0;
		foreach(input('post.option/a') as $ks=>$v){
			if($i==0 || $v[$sellprice_field] < $sell_price){
                if(getcustom('product_service_fee')) {
                    $service_fee = $v[$service_fee_field];
                }
				$sell_price = $v[$sellprice_field];
				$market_price = $v['market_price'];
				$cost_price = $v['cost_price'];
                if($business['cost_bili_with_edit'] === 0){
                    //设置成本比例后，是否允许商户修改商品成本，0关闭后商家不可修改
                    $cost_price = 0;
                }
				if($cost_price<=0 && $cost_bili>0){
                    $cost_price = bcmul($sell_price,$cost_bili/100,2);
                }
				$givescore = dd_money_format($v['givescore'],$score_weishu);
            	$pv = $v['pv'];
				$givecredit1 = $v['givecredit1'];
				$givecredit2 = $v['givecredit2'];
				$weight = $v['weight'];
				if(getcustom('product_handwork')){
					if($info['product_type'] ==3 && $v['hand_fee'] && $v['hand_fee']>0){
						$hand_fee = $v['hand_fee'];
					}
				}
                if(getcustom('product_givetongzheng')){
                    $givetongzheng = $v['givetongzheng'];
                }
                if(getcustom('member_commission_max')){
                    $give_commission_max = $v['give_commission_max']??0;
                }
                if(getcustom('consumer_value_add')){
                    $give_green_score = $v['give_green_score']??0;
                    $give_bonus_pool = $v['give_bonus_pool']??0;
                }
                if($info['lvprice']==1){
                    $lvprice_data = [];
                    foreach($levellist as $lv){
                        $lvprice_data[$lv['id']] = $v['sell_price_'.$lv['id']];
                        if(getcustom('service_fee_model') && $info['service_fee_switch'] == 1){
                            $service_fee_data[$lv['id']] = $v['service_fee_'.$lv['id']];
                        }
                    }
                }
			}
			$i++;
		}
		if($info['lvprice']==1){
			$data['lvprice_data'] = json_encode($lvprice_data);
            if(getcustom('service_fee_model') && $info['service_fee_switch'] == 1){
                $data['service_fee'] = $service_fee;
                $data['service_fee_data'] = json_encode($service_fee_data);
            }
		}

		if(getcustom('shop_other_infor')){
            $data['xunjia_text'] = $info['xunjia_text'];
        }
        if(getcustom('product_xunjia_btn')){
            $data['xunjia_text'] = $info['xunjia_text'];
            $data['show_xunjia_btn'] = $info['show_xunjia_btn'];
            $data['xunjia_btn_bgcolor'] = $info['xunjia_btn_bgcolor'];
            $data['xunjia_btn_color'] = $info['xunjia_btn_color'];
            $data['xunjia_btn_url'] = $info['xunjia_btn_url'];
        }
		$data['market_price'] = $market_price;
		$data['cost_price'] = $cost_price;
		$data['sell_price'] = $sell_price;
		$data['givescore'] = dd_money_format($givescore,$score_weishu);
    	$data['pv'] = $pv;
		$data['givecredit1'] = $givecredit1;
		$data['givecredit2'] = $givecredit2;
		$data['weight'] = $weight;
		$data['stock'] = 0;
        if(getcustom('product_service_fee')){
            $data['service_fee'] = $service_fee;
            $data['service_fee_switch'] = $info['service_fee_switch'];
            $data['shd_remark'] = $info['shd_remark'];
        }
		if(getcustom('buy_selectmember')){
			$data['balance'] = $info['balance'];
		}
		if(getcustom('product_handwork')){
			$data['hand_fee'] = $hand_fee;
		}
		foreach(input('post.option/a') as $v){
			$data['stock'] += $v['stock'];
		}
		//多规格 规格项
		$data['guigedata'] = input('post.specs');

		$data['paramdata'] = jsonEncode(input('post.paramdata/a'));
		if(bid !=0 ){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			if($bset['product_check'] == 1){
				$data['ischecked'] = 0;
			}
            if(getcustom('business_product_isexamine')){
                $business  = Db::name('business')->where('aid',aid)->where('id',bid)->find();
                if($business['is_open_examine'] ==1 ){
                    $data['ischecked'] = 0;
                }else{
                    $data['ischecked'] = 1;
                }
            }
		}
		if(getcustom('buybutton_custom')){
			$buybtn_status = $this->admin['buybtn_status']?$this->admin['buybtn_status']:0;
			if($buybtn_status){
				if(bid!=0){
					$business = Db::name('business')->where('aid',aid)->where('id',bid)->field('id,buybtn_status')->find();
					if($business && $business['buybtn_status'] ==1){
						$data['buybtn_name'] 	 = $info['buybtn_name'];
						$data['buybtn_link_url'] = $info['buybtn_link_url'];
						$data['buybtn_link_name']= $info['buybtn_link_name'];
					}
				}else{
					$data['buybtn_name'] 	 = $info['buybtn_name'];
					$data['buybtn_link_url'] = $info['buybtn_link_url'];
					$data['buybtn_link_name']= $info['buybtn_link_name'];
				}
			}
		}
        if(getcustom('addcart_button_custom')){
            $data['addcart_name'] 	 = $info['addcart_name'];
            $data['addcart_link_url'] = $info['addcart_link_url'];
            $data['addcart_link_name']= $info['addcart_link_name'];
        }
        if(getcustom('commission_xiaofei')) {
            $data['product_xiaofeipay'] = $info['product_xiaofeipay'];
        }
        if(getcustom('product_bonus_pool')){
            $data['bonus_pool_ratio'] = $info['bonus_pool_ratio'];
            $data['bonus_pool_num'] = $info['bonus_pool_num'];
            $data['bonus_pool_releasetj'] = implode(',',$info['bonus_pool_releasetj']); 
            $data['bonus_pool_isrelease'] = $info['bonus_pool_isrelease']; 
            $data['bonus_pool_money_max'] = $info['bonus_pool_money_max']; 
            $data['bonus_pool_cid'] = $info['bonus_pool_cid']; 
        }
        if(getcustom('yx_queue_free')){
            $data['queue_free_status'] = $info['queue_free_status'];
        }
        if(getcustom('mendian_hexiao_givemoney')){
            $data['hexiaogivepercent'] = $info['hexiaogivepercent'] != '' ? $info['hexiaogivepercent'] : null;
            $data['hexiaogivemoney'] = $info['hexiaogivemoney'] != '' ? $info['hexiaogivemoney'] : null;
        }
        if(getcustom('commission_parent_pj_stop_product')){
            $data['commission_parent_pj_status'] = $info['commission_parent_pj_status'];
            $data['commission_parent_pj_lv'] = $info['commission_parent_pj_lv'];
            $data['commission_parent_pj'] = $info['commission_parent_pj'];
            $data['commission_parent_pj_order'] = $info['commission_parent_pj_order'];
        }
        if(getcustom('zhitui_pj')){
            $data['zhitui_pj'] = jsonEncode($info['zhitui_pj']);
        }
        if(getcustom('product_wholesale')){
            $jd_start_num = input('post.jd_start_num/a');
		    $jd_end_num = input('post.jd_end_num/a');
		    $jd_ratio = input('post.jd_ratio/a');
            $jieti_discount_data = [];
            foreach($jd_start_num as $k=>$v){
                if($v>0){
                    $tem = [];
                    $tem['kid']=$k;
                    $tem['start_num']=$v;
                    $tem['end_num'] = $jd_end_num[$k];
                    $tem['ratio'] = empty($jd_ratio[$k])?100:$jd_ratio[$k];
                    $jieti_discount_data[] = $tem;
                }
                 
            }
            if($jieti_discount_data){
                $data['jieti_discount_data'] = json_encode($jieti_discount_data,JSON_UNESCAPED_UNICODE);
            }else{
                $data['jieti_discount_data'] = '';
            }
            $data['jieti_discount_type'] = $info['jieti_discount_type'];
        }
        if(getcustom('more_productunit_guige')){
            $data['prounit'] = json_encode($info['prounit']);
        }
        if(getcustom('shop_product_fenqi_pay')){
            $fenqi_num = input('post.fenqi_num_ratio/a');
		    $fenqi_give_num = input('post.fenqi_give_num/a');
		    $fenqi_fx_start_num = input('post.fenqi_fx_start_num/a');
            $fenqi_data = [];
            foreach($fenqi_num as $k=>$v){
                if($v>0){
                    $tem = [];
                    $tem['kid']=$k;
                    $tem['fenqi_num']=$k+1;
                    $tem['fenqi_num_ratio']=$v;
                    $tem['fenqi_give_num'] = $fenqi_give_num[$k]??0;
                    $tem['fenqi_fx_start_num'] = empty($fenqi_fx_start_num[$k])?0:$fenqi_fx_start_num[$k];
                    $fenqi_data[] = $tem;
                }
                 
            }
            if($fenqi_data){
                $data['fenqi_data'] = json_encode($fenqi_data,JSON_UNESCAPED_UNICODE);
            }else{
                $data['fenqi_data'] = '';
            }
            $data['fenqigive_couponid'] = $info['fenqigive_couponid'];
            $data['fenqigive_fx_couponid'] = $info['fenqigive_fx_couponid'];
            $data['fenqigive_couponnum'] = $info['fenqigive_couponnum'];
            $data['fenqigive_couponnum_fenxiao'] = $info['fenqigive_couponnum_fenxiao'];
        }
        if(getcustom('product_givetongzheng')){
            $data['givetongzheng'] = $givetongzheng;
            $data['tongzhengdkmaxset'] = $info['tongzhengdkmaxset'];
            $data['tongzhengdkmaxval'] = $info['tongzhengdkmaxval'];
        }
        if(getcustom('shopproduct_rewardedvideoad')){
            $data['rewardedvideoad'] = $info['rewardedvideoad'];
        }
        if(getcustom('member_realname_verify')){
            $data['limittj'] = empty($info['limittj']) ? 0 : implode(',',$info['limittj']);
            $data['realname_buy_status'] = $info['realname_buy_status'] ?? 0;
        }
        if(getcustom('shoporder_ranking')){
        	if($this->auth_data=='all' || in_array('ShoporderRanking/*',$this->auth_data)){
        		$data['rankingset']    = $info['rankingset'];
            	$data['ranking_radio'] = $info['ranking_radio'];
            	$data['ranking_money'] = $info['ranking_money'];
        	}
        }
        if(getcustom('member_commission_max')){
            $data['give_commission_max'] = $give_commission_max;
            $data['givecommax_time'] = $info['givecommax_time']??0;
        }

        if(getcustom('consumer_value_add')){
            $data['give_green_score'] = $give_green_score;
            $data['give_bonus_pool'] = $give_bonus_pool;
        }
        if(getcustom('shop_label')){
            $data['labelid']      = $info['labelid']??'';
            $data['labelbgcolor'] = $info['labelbgcolor']??'';
            $data['labelcolor']   = $info['labelcolor']??'';
        }
        if(getcustom('commission_max_times')){
            $data['commission_max_times_status']      = $info['commission_max_times_status']??0;
            $commission_max_times = input('commission_max_times/a');
            $data['commission_max_times'] = $commission_max_times?json_encode($commission_max_times):'';
        }
        if(getcustom('member_goldmoney_silvermoney')){
            $ShopSendGoldmoney   = false;//赠送金值权限
            $ShopSendSilvermoney = false;//赠送银值权限
            if(!bid){
                if($this->auth_data == 'all' || in_array('ShopSendGoldmoney',$this->auth_data)){
                    $ShopSendGoldmoney  = true;
                }
                if($this->auth_data == 'all' || in_array('ShopSendSilvermoney',$this->auth_data)){
                    $ShopSendSilvermoney = true;
                }
                if($ShopSendGoldmoney){
                    $data['goldmoneydec_ratio']  = $info['goldmoneydec_ratio'];
                }
                if($ShopSendSilvermoney){
                    $data['silvermoneydec_ratio'] = $info['silvermoneydec_ratio'];
                }
            }
        }
        if(getcustom('commission_bole')){
            $data['commissionboleset'] = $info['commissionboleset'];
            $commissionboledata1 = input('post.commissionboledata1/a');
            $data['commissionboledata1'] = json_encode($commissionboledata1);
            $commissionboledata2 = input('post.commissionboledata2/a');
            $data['commissionboledata2'] = json_encode($commissionboledata2);
        }
        if(getcustom('lvprice_jicha_lv')){
            $data['lvprice_jicha_lv'] = $info['lvprice_jicha_lv'];
            $data['lvprice_jicha_origin'] = $info['lvprice_jicha_origin']??0;
        }
        if(getcustom('team_jiandian')){
            $data['teamjiandianset'] = $info['teamjiandianset'];
            $teamjiandiandata1 = input('post.teamjiandiandata1/a');
            $data['teamjiandiandata1'] = json_encode($teamjiandiandata1);
            $teamjiandiandata2 = input('post.teamjiandiandata2/a');
            $data['teamjiandiandata2'] = json_encode($teamjiandiandata2);
        }
		if($product){
			if($product['bid'] == -1) $data['sort'] = 1000000 + intval($data['sort']);
			Db::name('shop_product')->where('aid',aid)->where('id',$product['id'])->update($data);
			if(getcustom('image_search')){

                $baidu = new \app\customs\Baidu(aid,bid);
                $data['id'] = $product['id'];
                $baidu->updateProduct($product,$data);
            }
			$proid = $product['id'];
			\app\commons\System::plog('商城商品编辑'.$proid);
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
			if(bid == 0 && $info['bid']){
				$data['bid'] = $info['bid'];
				if($info['bid'] == -1) $data['sort'] = 1000000 + intval($data['sort']);
			}
			$proid = Db::name('shop_product')->insertGetId($data);
			\app\commons\System::plog('商城商品编辑'.$proid);
		}

		if(getcustom('product_mendian_hexiao_givemoney')){
			if($data['bind_mendian_ids']){
				$bind_mendians = explode(',',$data['bind_mendian_ids']);
				if(in_array('-1',$bind_mendians)){
					$whereM = [];
					$whereM[] = ['aid','=',aid];
					$whereM[] = ['status','=',1];
					if($info['bid']>0){
						$whereM[] = ['bid','=',$info['bid']];
					}else{
						$business = Db::name('business')->where('aid',aid)->where('isplatform_auth',1)->where('status',1)->column('id');		
						array_push($business,bid);
						$whereM[] = ['bid','in',$business];
						$bind_mendians = Db::name('mendian')->where($whereM)->column('id');		
					}
				}
				foreach($bind_mendians as $mdid){
					$hxmd = Db::name('shop_product_mendian_hexiaoset')->where('proid',$proid)->where('aid',aid)->where('mdid',$mdid)->find();
					if(!$hxmd){
						$hxdata = [];
						$hxdata['aid'] = aid;
						$hxdata['bid'] = bid;
						$hxdata['proid'] = $proid;
						$hxdata['mdid'] = $mdid;
						$hxdata['createtime'] = time();
						Db::name('shop_product_mendian_hexiaoset')->insert($hxdata);
					}
				}
				Db::name('shop_product_mendian_hexiaoset')->where('proid',$proid)->where('aid',aid)->where('mdid','not in',$bind_mendians)->delete();
				
			}
		}

		if($product){
            $bid = $product['bid'];
        }else{
            $bid = $info['bid']?:bid;
        }
        //更新商户虚拟销量
        $sales = $info['sales']-$info['oldsales'];
		if($sales!=0){
            \app\models\Payorder::addSales(0,'sales',aid,$bid,$sales);
        }

		//dump(input('post.option/a'));die;
		//多规格
		$newggids = array();
		foreach(input('post.option/a') as $ks=>$v){
			$ggdata = array();
			$ggdata['proid'] = $proid;
			$ggdata['ks'] = $ks;
			$ggdata['name'] = $v['name'];
			$ggdata['pic'] = $v['pic'] ? $v['pic'] : '';
			$ggdata['market_price'] = $v['market_price']>0 ? $v['market_price']:0;
			$cost_price = $v['cost_price']>0 ? $v['cost_price']:0;
			if($cost_price<=0 && $cost_bili>0){
                $cost_price = bcmul($v['sell_price'],$cost_bili/100,2);
            }
            $ggdata['cost_price'] = $cost_price;
            if(getcustom('plug_huangfeihong')) {
                $ggdata['cost_price'] = $v['cost_price'] ? $v['cost_price']:0;
            }
			$ggdata['sell_price'] = $v['sell_price']>0 ? $v['sell_price']:0;
			$ggdata['weight'] = $v['weight']>0 ? $v['weight']:0;
			$ggdata['procode'] = $info['procode'];
			$ggdata['barcode'] = $v['barcode'];
			$ggdata['givescore'] = dd_money_format($v['givescore'],$score_weishu);
				$ggdata['pv'] = $v['pv'];	
			$ggdata['givecredit1'] = $v['givecredit1'];
	    	$ggdata['givecredit2'] = $v['givecredit2'];

            if(getcustom('product_givetongzheng')){
                $ggdata['givetongzheng'] = $v['givetongzheng'];
            }
            if(getcustom('commission_duipeng_score_withdraw')){
                $ggdata['give_withdraw_score'] = $v['give_withdraw_score'];
                $ggdata['give_parent1_withdraw_score'] = $v['give_parent1_withdraw_score'];
            }
			$ggdata['stock'] = $v['stock']>0 ? $v['stock']:0;
            $ggdata['limit_start'] = $v['limit_start']>0 ? $v['limit_start']:0;
			
			if(getcustom('product_stock_warning')){
				$ggdata['stock_warning'] = $v['stock_warning']>0 ? $v['stock_warning']:0;
			}
			if(getcustom('product_handwork')){
				$ggdata['hand_fee'] = $info['product_type'] ==3 && $v['hand_fee'] && $v['hand_fee']>0 ? $v['hand_fee']:0;
			}
            if(getcustom('member_commission_max')){
                $ggdata['give_commission_max'] = $v['give_commission_max']??0;
            }
            if(getcustom('product_service_fee')){
                $service_fee_data = [];
                if($info['service_fee_switch']==1 && $info['lvprice'] == 0){
                    $ggdata['service_fee'] = $v['service_fee'];
                }
            }
			$lvprice_data = [];
			if($info['lvprice']==1){
				$ggdata['sell_price'] = $v['sell_price_'.$levellist[0]['id']]>0 ? $v['sell_price_'.$levellist[0]['id']]:0;
                if(getcustom('product_service_fee') && $info['service_fee_switch']==1){
                    $ggdata['service_fee'] = $v['service_fee_'.$levellist[0]['id']]>0 ? $v['service_fee_'.$levellist[0]['id']]:0;
				}
                foreach($levellist as $lv){
					$sell_price = $v['sell_price_'.$lv['id']]>0 ? $v['sell_price_'.$lv['id']]:0;
					$lvprice_data[$lv['id']] = $sell_price;
                    if(getcustom('product_service_fee')&& $info['service_fee_switch']==1){
                        $serviceFeeData = $v['service_fee_'.$lv['id']]>0 ? $v['service_fee_'.$lv['id']]:0;
                        if (!is_float($serviceFeeData)) {
                            $serviceFeeData = sprintf("%.2f",$serviceFeeData);
                        }
                        $service_fee_data[$lv['id']] = $serviceFeeData;
                    }
				}
				$ggdata['lvprice_data'] = json_encode($lvprice_data);
                if(getcustom('product_service_fee')&& $info['service_fee_switch']==1){
                    $ggdata['service_fee_data'] = json_encode($service_fee_data);
                }
			}
            if(getcustom('more_productunit_guige')){
                // foreach($info['prounit'] as $prounitkey=>$prounit){
                //     $prounit_data[$prounitkey] = $v['prounit_'.$prounitkey]>0 ? $v['prounit_'.$prounitkey] : 0;
                // }
                //$ggdata['prounit_data'] = json_encode($prounit_data);
                $ggdata['prounit_0'] = $v['prounit_0']>0 ? $v['prounit_0'] : 0;
                $ggdata['prounit_1'] = $v['prounit_1']>0 ? $v['prounit_1'] : 0;
                $ggdata['prounit_2'] = $v['prounit_2']>0 ? $v['prounit_2'] : 0;
                $ggdata['prounit_3'] = $v['prounit_3']>0 ? $v['prounit_3'] : 0;
                $ggdata['prounit_4'] = $v['prounit_4']>0 ? $v['prounit_4'] : 0;
                $ggdata['prounit_5'] = $v['prounit_5']>0 ? $v['prounit_5'] : 0;
            }
            if(getcustom('consumer_value_add')){
                $ggdata['give_green_score'] = $v['give_green_score']??0;
                $ggdata['give_bonus_pool'] = $v['give_bonus_pool']??0;
            }
            if(getcustom('member_goldmoney_silvermoney')){
                if($ShopSendGoldmoney){
                    $ggdata['givegoldmoney']   = $v['givegoldmoney'];
                }
                if($ShopSendSilvermoney){
                    $ggdata['givesilvermoney'] = $v['givesilvermoney'];
                }
	        }
			$guige = Db::name('shop_guige')->where('aid',aid)->where('proid',$proid)->where('ks',$ks)->find();
			if($guige){
				Db::name('shop_guige')->where('aid',aid)->where('id',$guige['id'])->update($ggdata);
				$ggid = $guige['id'];
			}else{
				$ggdata['aid'] = aid;
				$ggid = Db::name('shop_guige')->insertGetId($ggdata);
			}
			if(getcustom('product_stock_record')){
				if($ggdata['stock']!=$guige['stock']){
					$stock = $ggdata['stock']-$guige['stock'];
					$rs = \app\models\ShopProduct::addStockRecord($proid,$ggid,$stock);
				}
			}
			$newggids[] = $ggid;
		}
		Db::name('shop_guige')->where('aid',aid)->where('proid',$proid)->where('id','not in',$newggids)->delete();
		
		$this->tongbuproduct($proid);
		if(getcustom('product_sync_business')){
            $this->businessProcopy($proid,1);
        }
        //旺店通同步
        if(getcustom('erp_wangdiantong')){
            $c = new \app\customs\Wdt(aid,bid);
            $c->goodsSpecPush($proid);
        }
		\app\commons\Wxvideo::updateproduct($proid);
		
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改价格
	public function changeprice(){
		$proid = input('post.proid/d');
		$product = Db::name('shop_product')->where('aid',aid)->where('id',$proid)->find();
		if(!$product) showmsg('商品不存在');
		if(bid != 0 && $product['bid']!=bid) showmsg('无权限操作');

		if($product['lvprice']==1){
            $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
            $default_cid = $default_cid ? $default_cid : 0;
            $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
			$defaultlvid = $levellist[0]['id'];
			$sellprice_field = 'sell_price_'.$defaultlvid;
		}else{
			$sellprice_field = 'sell_price';
		}

		$sell_price = 0;$market_price = 0;$cost_price = 0;$lvprice_data = [];$i=0;
		foreach(input('post.option/a') as $ks=>$v){
			if($i==0 || $v[$sellprice_field] < $sell_price){
				$sell_price = $v[$sellprice_field];
				$market_price = $v['market_price'];
				$cost_price = $v['cost_price'];
				if($product['lvprice']==1){
					$lvprice_data = [];
					foreach($levellist as $lv){
						$lvprice_data[$lv['id']] = $v['sell_price_'.$lv['id']];
					}
				}
			}
			$i++;
		}
		$data = [];
		if($product['lvprice']==1){
			$data['lvprice_data'] = json_encode($lvprice_data);
		}
		$data['market_price'] = $market_price;
		$data['cost_price'] = $cost_price;
		$data['sell_price'] = $sell_price;
		$data['stock'] = 0;
		foreach(input('post.option/a') as $v){
			$data['stock'] += $v['stock'];
		}
		Db::name('shop_product')->where('aid',aid)->where('id',$proid)->update($data);
		foreach(input('post.option/a') as $ks=>$v){
			$ggdata = [];
			$ggdata['market_price'] = $v['market_price']>0 ? $v['market_price']:0;
			$ggdata['cost_price'] = $v['cost_price']>0 ? $v['cost_price']:0;
			$ggdata['sell_price'] = $v['sell_price']>0 ? $v['sell_price']:0;
			$ggdata['stock'] = $v['stock']>0 ? $v['stock']:0;
			$lvprice_data = [];
			if($product['lvprice']==1){
				$ggdata['sell_price'] = $v['sell_price_'.$levellist[0]['id']]>0 ? $v['sell_price_'.$levellist[0]['id']]:0;
				foreach($levellist as $lv){
					$sell_price = $v['sell_price_'.$lv['id']]>0 ? $v['sell_price_'.$lv['id']]:0;
					$lvprice_data[$lv['id']] = $sell_price;
				}
				$ggdata['lvprice_data'] = json_encode($lvprice_data);
			}
			Db::name('shop_guige')->where('aid',aid)->where('id',$v['ggid'])->update($ggdata);
		}
        if(getcustom('erp_wangdiantong')) {
            $c = new \app\customs\Wdt(aid,bid);
            $c->goodsSpecPush($proid);
        }
		\app\commons\Wxvideo::update_without_audit($proid);

		return json(['status'=>1,'msg'=>'操作成功']);
	}

	
	//批量改价
	public function batchChangeprice(){
		$ids = input('post.ids/a');
		$type = input('post.batchchangeprice_type');
		$type2 = input('post.batchchangeprice_type2');
		$cid = input('post.batchchangeprice_cid');
		$where = [];
		$where[] = ['aid','=',aid];
		if($type == 0){
			if(!$ids) showmsg('请选择商品');
			$where[] = ['id','in',$ids];
		}else{
			if(!$cid) showmsg('请选择分类');
			//子分类
			$clist = Db::name('shop_category')->where('aid',aid)->where('pid',$cid)->column('id');
			if($clist){
				$clist2 = Db::name('shop_category')->where('aid',aid)->where('pid','in',$clist)->column('id');
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
		}
		$prolist = Db::name('shop_product')->where($where)->select()->toArray();
		if(!$prolist) showmsg('未查找到可修改的商品');
		
		$default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
		$default_cid = $default_cid ? $default_cid : 0;
		$levellist = Db::name('member_level')->where('aid',aid)->where('cid',$default_cid)->order('sort,id')->select()->toArray();
		$defaultlvid = $levellist[0]['id'];

		foreach($prolist as $product){
			$updata = [];
			//先更新规格表
			$gglist = Db::name('shop_guige')->where('proid',$product['id'])->select()->toArray();
			$minlvprice_data = [];$i=0;$sell_price = 0;$minsell_price = 0;
			foreach($gglist as $gg){
				if($product['lvprice']==1){
					$ismin = 0;
					$oldlvpirce_data = json_decode($gg['lvprice_data'],true);
					$lvprice_data = [];$j=0;
					foreach($levellist as $lv){
						if(input('post.batchchangeprice_num_'.$lv['id']) === ''){
							$this_price = $oldlvpirce_data[$lv['id']];
						}elseif($type2==0){
							$this_price = round(floatval(input('post.batchchangeprice_num_'.$lv['id'])) + $gg['cost_price'],2);
						}else{
							$this_price = round((floatval(input('post.batchchangeprice_num_'.$lv['id']))/100 + 1 ) * $gg['cost_price'],2);
						}
						$lvprice_data[$lv['id']] = $this_price;
						if($j==0 && ($i==0 || $this_price < $minsell_price)){
							$minsell_price = $this_price;
							//$minlvprice_data[$lv['id']] = $this_price;
							$ismin = 1;
						}
						$j++;
					}
					if($ismin == 1) $minlvprice_data = $lvprice_data;
					if(json_encode($lvprice_data) != $gg['lvprice_data']){
						Db::name('shop_guige')->where('id',$gg['id'])->update(['lvprice_data'=>json_encode($lvprice_data)]);
					}
				}else{
					if(input('post.batchchangeprice_num_'.$defaultlvid) === ''){
						$this_price = $gg['sell_price'];
					}elseif($type2==0){
						$this_price = round(floatval(input('post.batchchangeprice_num_'.$defaultlvid)) + $gg['cost_price'],2);
					}else{
						$this_price = round((floatval(input('post.batchchangeprice_num_'.$defaultlvid))/100 + 1 ) * $gg['cost_price'],2);
					}
					if($i==0 || $minsell_price > $this_price) $minsell_price = $this_price;
					if($this_price != $gg['sell_price']){
						Db::name('shop_guige')->where('id',$gg['id'])->update(['sell_price'=>$this_price]);
					}
				}
				$i++;
			}
			$needupdate = 0;
			if($product['lvprice']==1){
				$updata['lvprice_data'] = json_encode($minlvprice_data);
				if($updata['lvprice_data'] != $product['lvprice_data']) $needupdate = 1;
			}
			$updata['sell_price'] = $minsell_price;
			if($updata['sell_price'] != $product['sell_price']) $needupdate = 1;
			if($needupdate){
				Db::name('shop_product')->where('aid',aid)->where('id',$product['id'])->update($updata);
			}
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}

	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		if(bid !=0){
			$where[] = ['bid','=',bid];
            if(!getcustom('business_copy_product')) {
                $where[] = ['linkid', '=', 0];
            }
		}
		Db::name('shop_product')->where($where)->update(['status'=>$st]);
		
		$this->tongbuproduct($ids);
		if($st == 0){
			\app\commons\Wxvideo::delisting($ids);
		}else{
			\app\commons\Wxvideo::listing($ids);
		}
        if(getcustom('product_sync_business')){
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['plate_id','in',$ids];
            Db::name('shop_product')->where($where)->update(['status'=>$st]);
        }
        if(getcustom('erp_wangdiantong')) {
            $c = new \app\customs\Wdt(aid,bid);
            $c->goodsSpecPush($ids);
        }
		\app\commons\System::plog('商城商品改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('shop_product')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		if(!$ids) $ids = array(input('post.id/d'));
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		if(bid !=0){
			$where[] = ['bid','=',bid];
			if(!getcustom('business_copy_product')){
                $where[] = ['linkid','=',0];
            }
		}
		$prolist = Db::name('shop_product')->where($where)->select();
		foreach($prolist as $pro){
			Db::name('shop_product')->where('id',$pro['id'])->delete();
			Db::name('shop_guige')->where('proid',$pro['id'])->delete();
			if(getcustom('plug_businessqr') && $pro['bid']==-1){
				$prolist2 = Db::name('shop_product')->where('linkid',$pro['id'])->select();
				foreach($prolist2 as $pro2){
					Db::name('shop_product')->where('id',$pro2['id'])->delete();
					Db::name('shop_guige')->where('proid',$pro2['id'])->delete();
				}
			}
            if($pro['wxvideo_product_id']){
                \app\commons\Wxvideo::deleteproduct($pro['id']);
            }
		}
        if(getcustom('product_sync_business')){
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['plate_id','in',$ids];
            $prolist = Db::name('shop_product')->where($where)->select();
            foreach($prolist as $pro){
                Db::name('shop_product')->where('id',$pro['id'])->delete();
                Db::name('shop_guige')->where('proid',$pro['id'])->delete();
            }
        }
		\app\commons\System::plog('商城商品删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//复制商品
	public function procopy(){
        $where = [];
        if(bid > 0){
            $where[] = ['bid','=',bid];
        }
		$product = Db::name('shop_product')->where('aid',aid)->where($where)->where('id',input('post.id/d'))->find();
		if(!$product) return json(['status'=>0,'msg'=>'商品不存在,请重新选择']);
		$gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		$data = $product;
		$data['name'] = '复制-'.$data['name'];
        if($data['detail']){
            //处理tab组件
            $detail = json_decode($data['detail'],true);
            if($detail){
                foreach ($detail as $k => $item){
                    if($item['temp'] == 'tab'){
                        $detail[$k]['id'] = $item['id'].rand(0,9999);
                        $tablist = Db::name('designerpage_tab')->where('aid',aid)->where('tabid',$item['id'])->select()->toArray();
                        if($tablist){
                            foreach ($tablist as $k2 => $item2){
                                unset($item2['id']);
                                $item2['tabid'] = $detail[$k]['id'];
                                Db::name('designerpage_tab')->insert($item2);
                            }
                        }
                    }
                }
                $data['detail'] = json_encode($detail);
            }
        }
		unset($data['id']);
		unset($data['wxvideo_product_id']);
		unset($data['wxvideo_edit_status']);
		unset($data['wxvideo_status']);
		unset($data['wxvideo_reject_reason']);
		$data['status'] = 0;
		$newproid = Db::name('shop_product')->insertGetId($data);
		foreach($gglist as $gg){
			$ggdata = $gg;
			$ggdata['proid'] = $newproid;
			unset($ggdata['id']);
			unset($ggdata['linkid']);
			Db::name('shop_guige')->insert($ggdata);
		}
		$this->tongbuproduct($newproid);
		\app\commons\System::plog('商城商品复制'.$newproid);
		return json(['status'=>1,'msg'=>'复制成功','proid'=>$newproid]);
	}
	//获取分类信息
	public function getcategory(){
		if(!session('BST_ID')) return json(['status'=>0,'msg'=>'无权限操作']);
		$toaid = input('param.toaid/d');
		//分类
		$clist = Db::name('shop_category')->Field('id,name')->where('aid',$toaid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$child = Db::name('shop_category')->Field('id,name')->where('aid',$toaid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
			foreach($child as $k2=>$v2){
				$child2 = Db::name('shop_category')->Field('id,name')->where('aid',$toaid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
				$child[$k2]['child'] = $child2;
			}
			$clist[$k]['child'] = $child;
		}
		return json(['status'=>1,'data'=>$clist]);
	}
	//复制到其他账号商品
	public function userProcopy(){
        set_time_limit(0);
        ini_set('memory_limit',-1);
		if(!session('BST_ID')) return json(['status'=>0,'msg'=>'无权限操作']);
		$ids = input('post.ids/a');
        $togroupid = input('param.togroupid');
		$toaid = input('param.toaid');
		$tocid = input('param.tocid');
		if(!$toaid && !$togroupid) return json(['status'=>0,'msg'=>'请选择账号']);
		if(!$tocid && !$togroupid) return json(['status'=>0,'msg'=>'请选择分类']);
		if(!$ids) $ids = array(input('post.id/d'));
		$where = [];
		$where[] = ['aid','=',aid];
		//$where[] = ['bid','=',bid];
		$where[] = ['id','in',$ids];
		$prolist = Db::name('shop_product')->where($where)->select()->toArray();
		if(!$prolist) return json(['status'=>0,'msg'=>'商品不存在,请重新选择']);

        if(!$toaid && $togroupid){
            if(getcustom('admin_user_group')){
                $toaidArr = Db::name('admin')->where('group_id',$togroupid)->column('id');
                if(empty($toaidArr)) return json(['status'=>0,'msg'=>'该分组下没有账号,请重新选择']);
            }
        }else{
            $toaidArr = [$toaid];
        }

		foreach($prolist as $product){
			$gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
			$data = $product;
            foreach ($toaidArr as $toaid){
                $data['aid'] = $toaid;
                $data['bid'] = 0;
                $data['name'] = $data['name'];
                $data['cid'] = $tocid;
                //$data['status'] = 0;
                unset($data['id']);
                unset($data['wxvideo_product_id']);
                unset($data['wxvideo_edit_status']);
                unset($data['wxvideo_status']);
                unset($data['wxvideo_reject_reason']);
                unset($data['gid']);
                if($data['detail']){
                    //处理tab组件
                    $detail = json_decode($product['detail'],true);
                    if($detail){
                        foreach ($detail as $k => $item){
                            if($item['temp'] == 'tab'){
                                $detail[$k]['id'] = 'M'.time().rand(1000,9999);
                                $tablist = Db::name('designerpage_tab')->where('aid',aid)->where('tabid',$item['id'])->select()->toArray();
                                if($tablist){
                                    foreach ($tablist as $k2 => $item2){
                                        unset($item2['id']);
                                        $item2['aid'] = $toaid;
                                        $item2['tabid'] = $detail[$k]['id'];
                                        Db::name('designerpage_tab')->insert($item2);
                                    }
                                }
                            }
                        }
                        $data['detail'] = json_encode($detail);
                    }
                }
                $newproid = Db::name('shop_product')->insertGetId($data);
                foreach($gglist as $gg){
                    $ggdata = $gg;
                    $ggdata['proid'] = $newproid;
                    $ggdata['aid'] = $toaid;
                    unset($ggdata['id']);
                    unset($ggdata['linkid']);
                    Db::name('shop_guige')->insert($ggdata);
                }
            }
		}
		//$msg = '商城商品复制到账号'.input('post.userid/d').'/'.implode(',',$ids);
		//\app\commons\System::plog($msg);
		return json(['status'=>1,'msg'=>'复制成功']);
	}
	//导入商品
	public function importexcel(){
		set_time_limit(0);
		ini_set('memory_limit',-1);
		$file = input('post.file');
		if(!$file) return json(['status'=>0,'msg'=>'请上传excel文件']);
		$exceldata = $this->import_excel($file);
        $data_count = count($exceldata);
		$cateArr = Db::name('shop_category')->where('aid',aid)->column('name','id');
		$cateArr = array_flip($cateArr);
		$groupArr = Db::name('shop_group')->where('aid',aid)->column('name','id');
		$groupArr = array_flip($groupArr);
		$fuwuArr = Db::name('shop_fuwu')->where('aid',aid)->column('name','id');
		$fuwuArr = array_flip($fuwuArr);

        $pagenum = input('pagenum')?:1;
        $pagelimit = input('pagelimit')?:5;
        $start_hange = ($pagenum-1)*$pagelimit+1;
        $end_hang = $pagenum*$pagelimit;

		$insertnum = 0;
		$updatenum = 0;
		foreach($exceldata as $hang=>$data){
            if($hang+1<$start_hange){
                continue;
            }
            if($hang+1>$end_hang){
                continue;
            }
			$indata = [];
			$indata['aid'] = aid;
			$indata['bid'] = bid;
			$indata['name'] = $data[0];

			$pic = $data[1]?$data[1]:'';
			if($pic){
				if(strpos($pic,'//')===0) $pic = 'https:'.$pic;
				$pic = \app\commons\Pic::uploadoss($pic);
			}
			$indata['pic'] = $pic;

			$pics = [];
			if($data[2]){
				foreach(explode(',',$data[2]) as $v){
					$pic = $v;
					if(strpos($pic,'//')===0) $pic = 'https:'.$pic;
					if($pic){
						$pic = \app\commons\Pic::uploadoss($pic);
					}
					$pics[] = $pic;
				}
			}
			$indata['pics']      = $pics?implode(',',$pics):'';
			$indata['sellpoint'] = $data[3]?$data[3]:'';
			if($data[4]){
				$cids = [];
				foreach(explode(',',$data[4]) as $v){
					if(!$cateArr[$v]){
						if(bid == 0){
							$cid = Db::name('shop_category')->insertGetId(['aid'=>aid,'name'=>$v,'sort'=>0]);
							$cids[] = $cid;
							$cateArr[$v] = $cid;
						}
					}else{
						$cids[] = $cateArr[$v];
					}
				}
				$indata['cid'] = implode(',',$cids);
			}
			if($data[5]){
				$gids = [];
				foreach(explode(',',$data[5]) as $v){
					if(!$groupArr[$v]){
						if(bid == 0){
							$gid = Db::name('shop_group')->insertGetId(['aid'=>aid,'name'=>$v,'sort'=>0]);
							$gids[] = $gid;
							$groupArr[$v] = $gid;
						}
					}else{
						$gids[] = $groupArr[$v];
					}
				}
				$indata['gid'] = implode(',',$gids);
			}
			if($data[6]){
				$fwids = [];
				foreach(explode(',',$data[6]) as $v){
					if(!$fuwuArr[$v]){
						$fwid = Db::name('shop_fuwu')->insertGetId(['aid'=>aid,'bid'=>bid,'name'=>$v,'sort'=>0]);
						$fuwuArr[$v] = $fwid;
					}else{
						$fwid = $fuwuArr[$v];
					}
					$fwids[] = $fwid;
				}
				$indata['fwid'] = implode(',',$fwids);
			}
			$indata['procode']   = $data[7]?$data[7]:'';
			$indata['guigedata'] = '[{"k":0,"title":"规格","items":[{"k":0,"title":"默认规格"}]}]';
			$indata['market_price'] = round($data[8],2);
			$indata['cost_price']   = round($data[9],2);
			$indata['sell_price']   = round($data[10],2);
			$indata['weight'] = intval($data[11]);
			$indata['stock']  = intval($data[12]);
			$indata['sales']  = intval($data[13]);
			if($data[14] == '已上架' || $data[14] == '上架'){
				$indata['status'] = 1;
			}else{
				$indata['status'] = 0;
			}
			$indata['detail'] = jsonEncode([[
				'id'=>'M0000000000000',
				'temp'=>'richtext',
				'params'=>['bgcolor'=>'#FFFFFF','margin_x'=>0,'margin_y'=>0,'padding_x'=>0,'padding_y'=>0,'quanxian'=>['all'=>true],'platform'=>['all'=>true]],
				'data'=>'',
				'other'=>'',
				'content'=>$data[15]
			]]);
			if(getcustom('product_field_buy')){
                $indata['brand'] = $data[16] || $data[16] ===0 || $data[16] ==='0'?$data[16]:'';
                $indata['unit']  = $data[17] || $data[17] ===0 || $data[17] ==='0'?$data[17]:'';

                $time = trim($data[18]);
                if($time){
                	$d = 25569;
	                $t = 24 * 60 * 60;
	                $valid_time =  gmdate('Y-m-d ', ($time - $d) * $t);
	                $indata['valid_time'] = $valid_time;
                }else{
                	$indata['valid_time'] = '';
                }
                $indata['remark'] = $data[19] || $data[19] ===0 || $data[19] ==='0'?$data[19]:'';
                $indata['guige']  = $data[20] || $data[20] ===0 || $data[20] ==='0'?$data[20]:'';
            }

			$indata['createtime'] = time();
			if(bid != 0) $indata['commissionset'] = -1;
			$proid = Db::name('shop_product')->insertGetId($indata);
			$ggdata = [];
			$ggdata['aid'] = aid;
			$ggdata['proid'] = $proid;
			$ggdata['name'] = $indata['name'];
			$ggdata['pic'] = $indata['pic'];
			$ggdata['cost_price'] = $indata['cost_price'];
			$ggdata['market_price'] = $indata['market_price'];
			$ggdata['sell_price'] = $indata['sell_price'];
			$ggdata['stock'] = $indata['stock'];
			$ggdata['weight'] = $indata['weight'];
			$ggdata['ks'] = 0;
			Db::name('shop_guige')->insert($ggdata);

            //更新商户虚拟销量
            $sales = $indata['sales'];
            if($sales!=0){
                \app\models\Payorder::addSales(0,'sales',aid,bid,$sales);
            }


			$insertnum++;
		}
		\app\commons\System::plog('导入商品');
        $suc_total = $pagelimit*($pagenum-1)+$insertnum;
        $status = 1;
        if($suc_total>=$data_count){
            $status = 2;
        }
        $remain = $data_count-$suc_total;
        return json(['status'=>$status,'msg'=>'成功导入'.$suc_total.'条数据,剩余'.$remain]);
	}
	//同步商品到商户
	private function tongbuproduct($proids){
		if(getcustom('plug_businessqr')){
            if(!is_array($proids)){
                $proids = explode(',',$proids);
            }
            $blist = [];
            foreach($proids as $proid){
                $product = Db::name('shop_product')->where('aid',aid)->where('id',$proid)->find();
                if($product && $product['bid'] == -1){
                    $gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
                    if(!$blist){
                        $blist = Db::name('business')->where('aid',aid)->order('sort desc,id desc')->select()->toArray();
                    }
                    foreach($blist as $business){
                        $bpro = Db::name('shop_product')->where('aid',aid)->where('bid',$business['id'])->where('linkid',$product['id'])->find();
                        $data = $product;
                        $data['bid'] = $business['id'];
                        $data['linkid'] = $product['id'];
                        unset($data['id']);
                        unset($data['wxvideo_product_id']);
                        unset($data['wxvideo_edit_status']);
                        unset($data['wxvideo_status']);
                        unset($data['wxvideo_reject_reason']);
                        if(isset($data['bind_mendian_ids'])){
                            unset($data['bind_mendian_ids']);
                        }
                        if($bpro){
                            Db::name('shop_product')->where('id',$bpro['id'])->update($data);
                            $newproid = $bpro['id'];
                        }else{
                            $newproid = Db::name('shop_product')->insertGetId($data);
                        }

                        $newggids = [];
                        foreach($gglist as $gg){
                            $ggdata = $gg;
                            $ggdata['proid'] = $newproid;
                            unset($ggdata['id']);

                            $guige = Db::name('shop_guige')->where('aid',aid)->where('proid',$newproid)->where('ks',$ggdata['ks'])->find();
                            if($guige){
                                Db::name('shop_guige')->where('aid',aid)->where('id',$guige['id'])->update($ggdata);
                                $ggid = $guige['id'];
                            }else{
                                $ggid = Db::name('shop_guige')->insertGetId($ggdata);
                            }
                            $newggids[] = $ggid;
                        }
                        Db::name('shop_guige')->where('aid',aid)->where('proid',$newproid)->where('id','not in',$newggids)->delete();
                    }
                }
            }
		}
	}

	//选择商品
	public function chooseproduct(){
		//分类
		$clist = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		//分组
		$glist = Db::name('shop_group')->where('aid',aid)->order('sort desc,id')->select()->toArray();

		$hidebid = input('?param.hidebid')?input('hidebid/d'):0;
		if(bid) $hidebid = 1;
		//商户
		$blist = [];
		if(!$hidebid){
			$blist = Db::name('business')->where('aid',aid)->order('sort desc,id desc')->select()->toArray();
		}
		View::assign('hidebid',$hidebid);
		View::assign('blist',$blist);
		View::assign('clist',$clist);
		View::assign('glist',$glist);

		return View::fetch();
	}
	//获取商品信息
	public function getproduct(){
		$proid = input('post.proid/d');
		$product = Db::name('shop_product')->where('aid',aid)->where('id',$proid)->find();
        $desc_pics = [];
        $desc_text = '';
		if(getcustom('wx_channels')){
		    //剥离商品详情富文本中的文字和图片
            $detail = json_decode($product['detail'],true);
            foreach($detail as $v){
                if($v['temp']=='richtext'){
                    //富文本
                    // 提取图片链接
                    $pattern = '/<img[^>]+src="(?<src>[^\s"]+)"[^>]*>/';
                    preg_match_all($pattern, $v['content'], $matches);
                    $images = $matches['src'];
                    if($images){
                        $desc_pics = array_merge($desc_pics,$images);
                    }
                    //提取文字
                    $html_string = htmlspecialchars_decode($v['content']);
                    //去掉空格和换行
                    $delspacewrap = array(" ","　","\t","\n","\r");
                    $content = str_replace($delspacewrap,"", $html_string);
                    $text = strip_tags($content);
                    $desc_text .= $text;

                }elseif($v['temp']=='picture' || $v['temp']=='pictures'){
                    //单图或多图
                    foreach($v['data'] as $data){
                        $desc_pics[] = $data['imgurl'];
                    }
                }
            }
        }
        $product['desc_pics'] = implode(',',$desc_pics);
        $product['desc_text'] = $desc_text;
		//多规格
		$newgglist = array();
		$gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
		foreach($gglist as $k=>$v){
            if(getcustom('member_product_price')){
                $mid = input('param.mid');
                $member_product = Db::name('member_product')->where('aid',aid)->where('mid',$mid)->where('proid',$proid)->where('ggid',$v['id'])->find();
                if($member_product){
                    $v['member_sell_price'] = $member_product['sell_price'];
                    $v['market_price'] = $member_product['market_price'];
                    $v['cost_price'] = $member_product['cost_price'];
                }
            }
            $caneditstock = 1;
            if(getcustom('erp_wangdiantong')){
                if($v['wdt_status']==1){
                    $caneditstock = 0;
                }
            }
            $v['caneditstock'] = $caneditstock;
			$newgglist[$v['ks']] = $v;
		}
		$guigedata = json_decode($product['guigedata']);
		return json(['product'=>$product,'gglist'=>$newgglist,'guigedata'=>$guigedata]);
	}
    public function getproductlist(){
        $proids = input('post.proids');
        $productlist = Db::name('shop_product')->where('aid',aid)->where('id','in',$proids)->select();
        return json(['productlist'=>$productlist,]);
    }
    /**
     * 上传商品到视频号
     * 废弃
     * @deprecated
     */
	public function towxvideo(){
		$proids = input('param.ids/a');
		$prolist = Db::name('shop_product')->where('aid',aid)->where('bid',0)->where('id','in',$proids)->where('wxvideo_status',0)->select()->toArray();
		if(!$prolist) return json(['status'=>0,'msg'=>'不存在未上传的商品']);

		$third_cat_id = input('param.third_cat_id');
		$brand_id = input('param.brand_id');
		$qualification_pics = input('param.qualification_pics');
		$update = [];
		$update['wxvideo_third_cat_id'] = $third_cat_id;
		$update['wxvideo_brand_id'] = $brand_id;
		$update['wxvideo_product_id'] = $product_id;
		if($qualification_pics){
			$update['wxvideo_qualification_pics'] = $qualification_pics;
		}
		Db::name('shop_product')->where('bid',0)->where('id','in',$proids)->where('wxvideo_status',0)->update($update);

		$errmsg = '';
		$successnum = 0;
		$errnum = 0;
		foreach($prolist as $product){
			$rs = \app\commons\Wxvideo::updateproduct($product['id']);
			if($rs['status'] == 1){
				$successnum++;
			}else{
				$errnum++;
				$errmsg = $rs['msg'];
			}
		}
		if($errnum == 0){
			return json(['status'=>1,'msg'=>'成功同步商品'.$successnum.'个']);
		}else{
			return json(['status'=>0,'msg'=>'成功'.$successnum.'个,失败'.$errnum.'个,原因:'.$errmsg]);
		}
		
	}
	//更新视频号商品状态
	public function wxvideoupdatest(){
		$url = 'https://api.weixin.qq.com/shop/spu/get_list?access_token='.Wechat::access_token(aid,'wx');
		$prolist = [];
		$postdata = [];
		$postdata['page'] = 1;
		$postdata['page_size'] = 100;
		$rs = curl_post($url,jsonEncode($postdata));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>Wechat::geterror($rs),'$postdata'=>$postdata]);
		}
		$totalnum = $rs['total_num'];
		$prolist = array_merge($prolist,$rs['spus']);
		if($totalnum > 100){
			$pagecount = ceil($totalnum / 100);
			for($i=1;$i<$pagecount;$i++){
				$postdata = [];
				$postdata['page'] = $i+1;
				$postdata['page_size'] = 100;
				$rs = curl_post($url,jsonEncode($postdata));
				$rs = json_decode($rs,true);
				if($rs['spus']){
					$prolist = array_merge($prolist,$rs['spus']);
				}
			}
		}
		foreach($prolist as $pro){
			$product = Db::name('shop_product')->where('wxvideo_product_id',$pro['product_id'])->find();
			if(!$product){
				curl_post('https://api.weixin.qq.com/shop/spu/del?access_token='.Wechat::access_token(aid,'wx'),jsonEncode(['product_id'=>$pro['product_id']]));
				continue;
			}
			$wxvideo_reject_reason = $pro['audit_info']['reject_reason'];
			if($pro['edit_status'] != $v['wxvideo_edit_status'] || $pro['status'] != $v['wxvideo_status']){
				Db::name('shop_product')->where('id',$product['id'])->update(['wxvideo_edit_status'=>$pro['edit_status'],'wxvideo_status'=>$pro['status'],'wxvideo_reject_reason'=>$wxvideo_reject_reason]);
			}
		}
		return json(['status'=>1,'msg'=>'同步更新完成']);
	}

	//撤回审核
	public function wxvideo_del_audit(){
		$proid = input('param.proid/d');
		$product = Db::name('shop_product')->where('aid',aid)->where('id',$proid)->find();
		$rs = curl_post('https://api.weixin.qq.com/shop/spu/del_audit?access_token='.Wechat::access_token(aid,'wx'),jsonEncode(['product_id'=>$product['wxvideo_product_id']]));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return json(['status'=>0,'msg'=>Wechat::geterror($rs)]);
		}else{
			Db::name('shop_product')->where('aid',aid)->where('id',$proid)->update(['wxvideo_edit_status'=>1]);
			return json(['status'=>1,'msg'=>'操作成功']);
		}
	}
	//视频号上架
	public function wxvideo_listing(){
		$proid = input('param.proid/d');
		$rs = \app\commons\Wxvideo::listing($proid);
		return json($rs);
	}
	//视频号下架
	public function wxvideo_delisting(){
		$proid = input('param.proid/d');
		$rs = \app\commons\Wxvideo::delisting($proid);
		return json($rs);
	}
	
	//规格拆分
	public function getsplitdata(){
		$proid = input('post.proid');
		$splitlist = Db::name('shop_ggsplit')->where('aid',aid)->where('proid',$proid)->select()->toArray();
		$gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$proid)->select()->toArray();
		if(!$splitlist) $splitlist = [['ggid1'=>'','ggid2'=>'','multiple'=>'']];
		return json(['splitlist'=>$splitlist,'gglist'=>$gglist]);
	}
	public function ggsplit(){
		//var_dump(input('post.'));
		$proid = input('post.proid/d');
		if(!$proid) return json(['status'=>0,'msg'=>'获取商品信息失败']);
		$ggid1Arr = input('post.ggid1/a');
		$ggid2Arr = input('post.ggid2/a');
		$multipleArr = input('post.multiple/a');
		$datalist = [];
		foreach($ggid1Arr as $k=>$v){
			if($ggid1Arr[$k] == '' || $ggid2Arr[$k] == '') continue;
			$data = [];
			$data['aid'] = aid;
			$data['proid'] = $proid;
			$data['ggid1'] = $ggid1Arr[$k];
			$data['ggid2'] = $ggid2Arr[$k];
			$data['multiple'] = $multipleArr[$k];
			$data['createtime'] = time();
			$datalist[$data['ggid1'].'-'.$data['ggid2']] = $data;
			if(!$multipleArr[$k] || $multipleArr[$k] <=0) return json(['status'=>0,'msg'=>'倍数必须是大于0的整数']);
			if($data['ggid1'] == $data['ggid2']) return json(['status'=>0,'msg'=>'不能设置两个相同的规格进行拆分']);
		}
		Db::name('shop_ggsplit')->where('aid',aid)->where('proid',$proid)->delete();
		foreach($datalist as $k=>$data){
			Db::name('shop_ggsplit')->insert($data);
		}
		\app\models\ShopProduct::calculateStock($proid);
		return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
	}
	
	//锁定
	public function dolock(){
		if(session('IS_ADMIN') == 0) return json(['status'=>1,'msg'=>'无权限操作']);
		$id = input('post.id/d');
		$st = input('post.st/d');
		Db::name('shop_product')->where('aid',aid)->where('id',$id)->update(['islock'=>$st]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}

	public function jsttongbu(){  //聚水潭商品同步
        if(getcustom('jushuitan')){
            $rs = \app\customs\jushuitan::getprolist(aid);
            if($rs['code']){
                return json(['status'=>0,'msg'=>$rs['msg']]);
            }
			if(!$rs['data']){
				   return json(['status'=>0,'msg'=>'未查询到数据']);
			}
            $datas = $rs['data']['datas'];
            //return json(['status'=>1,'msg'=>$datas]);
            $proskuid = [];
            foreach($datas as $d){
                //查看有没有此编号的商品，如果有则修改
                $product = Db::name('shop_product')->where('aid',aid)->where('procode',$d['i_id'])->find();
                $pro = [];
                $pro['name'] = $d['name'];
                $pro['procode'] = $d['i_id'];
                $pro['pic'] =  \app\commons\Pic::tolocal($g['pic']);
                $pro['sell_price'] = $d['s_price'];
                $pro['market_price'] = $d['market_price'];
                $pro['createtime'] = strtotime($d['created']);
                $pro['cost_price'] = $d['c_price'];
                $pro['weight'] = $d['weight'];
                $pro['aid'] = aid;
                $pro['bid'] = bid;
                if($product){
                    Db::name('shop_product')->where('aid',aid)->where('id',$product['id'])->update($pro);
                    $proid = $product['id'];
                }else{
                    $proid = Db::name('shop_product')->insertGetId($pro);
                }
                $ggitems=[];
                $skuids = [];
                foreach($d['skus'] as $key=>$g){
                    $ggitems[] = ['k'=>$key,'title'=>$g['properties_value']];
                    $guige = Db::name('shop_guige')->where('aid',aid)->where('barcode',$g['sku_id'])->find();
                    $sku = [];
                    $skuids[] = $g['sku_id'];
                    $sku['aid'] = aid;
                    $sku['name'] = $g['properties_value'];
                    $sku['pic'] =  \app\commons\Pic::tolocal($g['pic']);
                    $sku['market_price'] = $g['sale_price'];
                    $sku['sell_price'] = $g['market_price'];
                    $sku['cost_price'] = $g['cost_price'];
                    $sku['weight'] = $g['weight'];
                    $sku['proid'] = $proid;
                    $sku['procode'] = $g['i_id'];
                    $sku['barcode'] = $g['sku_id'];
                    $sku['ks'] = $key;
                    if($guige){
                        Db::name('shop_guige')->where('aid',aid)->where('id',$guige['id'])->update($sku);
                    }else{
                        Db::name('shop_guige')->insert($sku);
                    }
                }
                $skuids = implode(',',$skuids);
                $proskuid[] = $skuids;
                $guiges = [['k'=>0,'title'=>'规格','items'=>$ggitems]];
                $guigedata = jsonEncode($guiges);
                Db::name('shop_product')->where('aid',aid)->where('id',$proid)->update(['guigedata'=>$guigedata]);
            }
            $proskuid = implode(',',$proskuid);
			if($proskuid){
				$rs = \app\customs\jushuitan::getstock(aid,$proskuid);
				//修改库存
				if(!$rs['code']){
					$datas = $rs['data']['inventorys'];
					$proids = [];
					foreach($datas as $d){
						$guige = Db::name('shop_guige')->where('aid',aid)->where('barcode',$d['sku_id'])->find();
						$proids[] = $guige['proid'];
						if($guige){
							$sku = [];
							$sku['stock'] = $d['qty']-$d['order_lock'];
							Db::name('shop_guige')->where('aid',aid)->where('id',$guige['id'])->update($sku);
						}
					}
					$proids = array_unique($proids);
					foreach($proids as $proid){
						$sumguge = Db::name('shop_guige')->where('proid', $proid)->where('aid', aid)->sum('stock');
						Db::name('shop_product')->where('id', $proid)->update(['stock'=>$sumguge]);
					}
				}else{
					return json(['status'=>0,'msg'=>$rs['msg']]);
				}
			}
            return json(['status'=>1,'msg'=>'同步成功']);
            \app\commons\System::plog('同步聚水潭商品');
        }
	}


    //导入商品
    public function importexcelnew(){

        set_time_limit(0);
        ini_set('memory_limit',-1);
        $file = input('post.file');
        if(!$file) return json(['status'=>0,'msg'=>'请上传excel文件']);
        $exceldata = $this->import_excel($file);
        foreach($exceldata as $k=>$v){
            if(empty(array_filter($v))){
                unset($exceldata[$k]);
            }
        }
        $exceldata = array_values($exceldata);
        if(bid > 0){
            $cateArr = Db::name('shop_category2')->Field('id,name')->where('aid',aid)
                ->where('bid',bid)->column('name','id');
        }else{
            $cateArr = Db::name('shop_category')->where('aid',aid)->column('name','id');
        }
        $cateArr = array_flip($cateArr);

        $groupArr = Db::name('shop_group')->where('aid',aid)->column('name','id');
        $groupArr = array_flip($groupArr);
        $fuwuArr = Db::name('shop_fuwu')->where('aid',aid)->column('name','id');
        $fuwuArr = array_flip($fuwuArr);

        //会员级别信息
        $level_lists = Db::name('member_level')->where('aid',aid)->order('id asc')->column('name','id');

        $pagenum = input('pagenum')?:1;
        $pagelimit = input('pagelimit')?:5;
        $start_hange = ($pagenum-1)*$pagelimit+1;
        $end_hang = $pagenum*$pagelimit;
        $insertnum = 0;
        $updatenum = 0;
        Db::startTrans();
        $data_count = count($exceldata);
        //dump($start_hange.'=>'.$end_hang);exit;
        foreach($exceldata as $hang=>$data){
            if($hang+1<$start_hange){
                continue;
            }
            if($hang+1>$end_hang){
                continue;
            }
            if(empty($data[2])){
                continue;
            }
            $indata = [];
            $indata['aid'] = aid;
            $indata['bid'] = bid;
            //商品ID
            $proid = $data[0];
            //不是纯数字的商品ID作为0处理
            if(!is_numeric($proid)){
                $proid = 0;
            }else{
                $indata['id'] = $proid;
            }

            $indata['procode'] = $data[2]?$data[2]:'';//商品编码
            $indata['barcode'] = $data[3]?$data[3]:'';//规格编码
            //商品规格处理
            $guige = [];
            $guige_str = '';
            //颜色
            $guige_k = 0;
            if($data[4]){
                $guige[] = [
                    'k' => $guige_k,
                    'title' => '颜色',
                    'items' => [
                        [
                            'k' => 0,
                            'title' => $data[4],
                        ]
                    ],
                ];
                $guige_str .= $data[4].',';
                $guige_k = $guige_k+1;
            }
            //规格
            if($data[5]){
                $guige[] = [
                    'k' => $guige_k,
                    'title' => '规格',
                    'items' => [
                       [
                           'k' => 0,
                            'title' => $data[5],
                       ]
                    ],
                ];
                $guige_str .= $data[5].',';
                $guige_k = $guige_k+1;
            }
            //其他规格(例：内存|硬盘,16|512)(例：内存:16,硬盘:512)
            if($data[6]){
                $arr = explode(',',$data[6]);
                foreach($arr as $guige_group){
                    $guige_name_arr = explode(':',$guige_group);
                    if(count($guige_name_arr)!=2){
                        return json(['status'=>0,'msg'=>'导入失败，第'.$hang.'行其他规格格式错误']);
                    }
                    $guige[] = [
                        'k' => $guige_k,
                        'title' => $guige_name_arr[0],
                        'items' => [
                            [
                                'k' => 0,
                                'title' => $guige_name_arr[1],
                            ]
                        ],
                    ];
                    $guige_str .= $guige_name_arr[1].',';
                    $guige_k = $guige_k+1;
                }
                unset($k);
            }
            $guige_str = rtrim($guige_str,',');
            //dump($guige_str);
            //dump($guige);
            if($guige){
                $indata['guigedata'] = json_encode($guige,JSON_UNESCAPED_UNICODE);
            }else{
                $indata['guigedata'] = '[{"k":0,"title":"规格","items":[{"k":0,"title":"默认规格"}]}]';;
            }
            //商品图片
            $pics = [];
            $pic_main = '';//产品主图，先取第一个
            if($data[7]){
                //有可能是json格式
                $pics_arr = json_decode($data[7],true);
                if(!$pics_arr){
                    $pics_arr = explode(',',$data[7]);
                }
                foreach($pics_arr as $k=>$v){
                    $pic = $v;
                    if(strpos($pic,'//')===0) $pic = 'https:'.$pic;
                    if($pic){
                        $pic = \app\commons\Pic::uploadoss($pic);
                    }
                    if($k==0){
                        $pic_main = $pic;
                    }
                    $pics[] = $pic;
                }
                $indata['pics'] = implode(',',$pics);
                unset($k,$v,$pics);
            }
            //商品详情（图片列表格式）
            $detail = [];
            if($data[8]){
                //有可能是json格式
                $pics_arr = json_decode($data[8],true);
                if(!$pics_arr){
                    $pics_arr = explode(',',$data[8]);
                }
                //转换成富文本格式
                foreach($pics_arr as $k=>$v){
                    $pic = $v;
                    if(strpos($pic,'//')===0) $pic = 'https:'.$pic;
                    if($pic){
                        $pic = \app\commons\Pic::uploadoss($pic);
                    }
                    $detail[] = [
                        'id' => 'M000000000000'.$k,
                        'temp' => 'picture',
                        'params' => [
                            'bgcolor'=>'#FFFFFF','margin_x'=>0,'margin_y'=>0,'padding_x'=>0,'padding_y'=>0,'borderradius'=>0,
                            'quanxian' => ['all'=>true],
                            'platform' => ['all'=>true],
                            'mendian' => ['all'=>true],
                            'mendian_sort' => 'sort',
                        ],
                        'data' => [
                            [
                                'id' => 'P0000000000001',
                                'imgurl' => $pic,
                                'hrefurl' => '',
                                'option' => 0
                            ]
                        ],
                        'other' => '',
                        'content' => ''
                    ];
                }
            }
            //商品详情 富文本格式
            if($data[46]){
                $detail[] = [
                    'id'=>'M0000000000000',
                    'temp'=>'richtext',
                    'params'=>['bgcolor'=>'#FFFFFF','margin_x'=>0,'margin_y'=>0,'padding_x'=>0,'padding_y'=>0,'quanxian'=>['all'=>true],'platform'=>['all'=>true],'mendian' => ['all'=>true],
                        'mendian_sort' => 'sort',],
                    'data'=>'',
                    'other'=>'',
                    'content'=>$data[46]
                ];
            }
            if($detail){
                $indata['detail'] = json_encode($detail);
            }
            //规格图片
            $guige_pic = \app\commons\Pic::uploadoss($data[9]);

            $indata['name']         = $data[10];//商品名称
            $indata['sellpoint']    = $data[12];//商品卖点
            $indata['product_link'] = $data[14]?:'';//宝贝链接
            $indata['stock']  = intval($data[15]);//库存
            $indata['weight'] = intval($data[16]);//重量
            $indata['sell_price']   = round($data[17],2);//销售价
            $indata['market_price'] = round($data[18],2);//市场价
            $indata['cost_price']   = round($data[19],2);//成本价
            //会员价处理(excel固定了20，21，22，23，24是从低到高的会员价)
            $lvprice_data = [];
            $k = 20;
            foreach($level_lists as $level_id=>$level_name){
                if($k<=24){
                    $level_price = $data[$k];
                }else{
                    $level_price = $data[24];
                }
                if($level_price>0){
                    $lvprice_data[$level_id] = $level_price;
                }
                $k++;
            }
            if($lvprice_data){
                $indata['lvprice'] = 1;
                $indata['lvprice_data'] = $lvprice_data;
            }
            //商品分类
            if($data[25]){
                $cids = [];
                foreach(explode(',',$data[25]) as $v){
                    if(!$cateArr[$v]){
                        if(bid == 0){
                            $cid = Db::name('shop_category')->insertGetId(['aid'=>aid,'name'=>$v,'sort'=>0]);
                            $cids[] = $cid;
                            $cateArr[$v] = $cid;
                        }
                    }else{
                        $cids[] = $cateArr[$v];
                    }
                }
                $indata['cid'] = implode(',',$cids);
            }
            //商品分组
            if($data[26]){
                $gids = [];
                foreach(explode(',',$data[26]) as $v){
                    if(!$groupArr[$v]){
                        if(bid == 0){
                            $gid = Db::name('shop_group')->insertGetId(['aid'=>aid,'name'=>$v,'sort'=>0]);
                            $gids[] = $gid;
                            $groupArr[$v] = $gid;
                        }
                    }else{
                        $gids[] = $groupArr[$v];
                    }
                }
                $indata['gid'] = implode(',',$gids);
            }
            //商品服务
            if($data[27]){
                $fwids = [];
                foreach(explode(',',$data[27]) as $v){
                    if(!$fuwuArr[$v]){
                        $fwid = Db::name('shop_fuwu')->insertGetId(['aid'=>aid,'bid'=>bid,'name'=>$v,'sort'=>0]);
                        $fuwuArr[$v] = $fwid;
                    }else{
                        $fwid = $fuwuArr[$v];
                    }
                    $fwids[] = $fwid;
                }
                $indata['fwid'] = implode(',',$fwids);
            }
            //商品类型
            $product_type = strpos($data[28],'眼镜')===false?0:1;
            $indata['product_type'] = $product_type;
            //配送模板
            $indata['freighttype'] = $data[29] || $data[29] ===0 || $data[29] ==='0'?$data[29]:1;
            if($data[29]==0){
                $indata['freightdata'] = $data[30];
            }
            if($data[29]==3){
                $indata['freightcontent'] = $data[30];
            }
            //分红设置
            $indata['fenhongset']     = $data[31] || $data[31] ===0 || $data[31] ==='0'?$data[31]:1;
            //团队分红
            $indata['teamfenhongset'] = $data[32]?$data[32]:0;
            if($data[33]){
                $teamfenhongdata1 = [];
                $teamfenhongdata2 = [];
                $teamfenhong_arr = explode(',',$data[33]);
                foreach($teamfenhong_arr as $teamfenhong_str){
                    $arr = explode(':',$teamfenhong_str);
                    //验证填写的级别是否存在
                    if(empty($level_lists[$arr[0]])){
                        continue;
                    }
                    //单独设置分红比例
                    if($data[32]==1){
                        $teamfenhongdata1[$arr[0]]['commission'] = $arr[1];
                    }elseif($data[32]==2){
                        //单独设置分红金额
                        $teamfenhongdata2[$arr[0]]['commission'] = $arr[1];
                    }elseif($data[32]==3){
                        //单独设置分红积分比例
                        $teamfenhongdata1[$arr[0]]['score'] = $arr[1];
                    }
                }
                $indata['teamfenhongdata1'] = json_encode($teamfenhongdata1);
                $indata['teamfenhongdata2'] = json_encode($teamfenhongdata2);
            }
            //股东分红设置
            $indata['gdfenhongset'] = $data[34]?$data[34]:0;
            if($data[35]){
                $gdfenhong_arr = explode(',',$data[35]);
                $gdfenhongdata1 = [];
                $gdfenhongdata2 = [];
                foreach($gdfenhong_arr as $gdfenhong_str){
                    $arr = explode(':',$gdfenhong_str);
                    //验证填写的级别是否存在
                    if(empty($level_lists[$arr[0]])){
                        continue;
                    }
                    //单独设置分红比例
                    if($data[34]==1){
                        $gdfenhongdata1[$arr[0]]['commission'] = $arr[1];
                    }elseif($data[34]==2){
                        //单独设置分红金额
                        $gdfenhongdata2[$arr[0]]['commission'] = $arr[1];
                    }elseif($data[34]==3){
                        //单独设置分红积分比例
                        $gdfenhongdata1[$arr[0]]['score'] = $arr[1];
                    }
                }
                $indata['gdfenhongdata1'] = json_encode($gdfenhongdata1);
                $indata['gdfenhongdata2'] = json_encode($gdfenhongdata2);
            }
            //区域代理分红
            $indata['areafenhongset'] = $data[36]?$data[36]:0;
            if($data[37]){
                $areafenhong_arr = explode(',',$data[37]);
                $areafenhongdata1 = [];
                $areafenhongdata2 = [];
                foreach($areafenhong_arr as $gdfenhong_str){
                    $arr = explode(':',$gdfenhong_str);
                    //验证填写的级别是否存在
                    if(empty($level_lists[$arr[0]])){
                        continue;
                    }
                    //单独设置分红比例
                    if($data[36]==1){
                        $areafenhongdata1[$arr[0]]['commission'] = $arr[1];
                    }elseif($data[36]==2){
                        //单独设置分红金额
                        $areafenhongdata2[$arr[0]]['commission'] = $arr[1];
                    }elseif($data[36]==3){
                        //单独设置分红积分比例
                        $areafenhongdata1[$arr[0]]['score'] = $arr[1];
                    }
                }
                $indata['areafenhongdata1'] = json_encode($areafenhongdata1);
                $indata['areafenhongdata2'] = json_encode($areafenhongdata2);
            }
            //积分抵扣
            $indata['scoredkmaxset'] = $data[38]?$data[38]:0;
            $indata['scoredkmaxval'] = $data[39]?$data[39]:0;
            //显示条件
            if($data[40]){
                $showtj = implode(',',explode('|',$data[40]));
                $indata['showtj'] = $showtj;
            }
            //购买条件
            if($data[41]){
                $gettj = implode(',',explode('|',$data[41]));
                $indata['gettj'] = $gettj;
            }
            //销量处理

            if($proid){
                $exit_product = Db::name('shop_product')
                    ->where('aid',aid)
                    ->where('bid',bid)
                    ->where('id',$proid)
                    ->find();
            }else{
                $exit_product = Db::name('shop_product')
                    ->where('aid',aid)
                    ->where('bid',bid)
                    ->where('procode',$indata['procode'])
                    ->find();
            }
            //echo Db::getLastSql();
            $sales = intval($data[42]);
            if($exit_product){
                $sales = bcadd($exit_product['sales'],$sales);
            }
            $indata['sales'] = $sales;//销量
            //状态
            if($data[43] == '已上架' || $data[43] == '上架'){
                $indata['status'] = 1;
            }else {
                $indata['status'] = 0;
            }
            $pic_main = $data['44']?:$pic_main;
            if($pic_main){
                $indata['pic'] = $data['44']?:$pic_main;//商品主图
            }
            if($data[45]){
                $indata['video'] = $data[45];//商品视频
            }

            $indata['createtime'] = time();
            if(bid != 0) $indata['commissionset'] = -1;
            //dump($data);
            //dump($indata);exit;
            //商品已存在，修改
            if($exit_product){
                //相同产品，不同规格，重新组织规格数据
                $exit_guige = json_decode($exit_product['guigedata'],true);
                $guige = array_column($guige,null,'title');
                $exit_guige = array_column($exit_guige,null,'title');
                $new_guige = [];
                //dump($guige);
                //dump($exit_guige);
                if($guige && $exit_guige){
                    foreach($guige as $k=>$v){
                        //dump($k.'开始');
                        if(!empty($exit_guige[$k])){
                            $items = array_column($v['items'],'title');
                            $exit_items = array_column($exit_guige[$k]['items'],'title');
                            //dump($items);
                            //dump($exit_items);
                            foreach($items as $item_v){
                                if(!in_array($item_v,$exit_items)){
                                    //dump('加入'.$item_v);
                                    array_push($exit_items,$item_v);
                                }
                            }
                            //dump($exit_items);
                            //$new_items = array_unique(array_merge($exit_items,$items));
                            //dump($exit_items);
                            $new_guige_item = [];
                            foreach($exit_items as $item_k=>$item_title){
                                $new_guige_item[] = ['k'=>$item_k,'title'=>$item_title];
                            }
                            $new_guige[] =  [
                                'k' => $v['k'],
                                'title' => $k,
                                'items' => $new_guige_item,
                            ];


                        }else{
                            return json(['status'=>0,'msg'=>'导入失败，第'.$hang.'行规格格式与相同产品的规格不匹配']);
                        }

                    }
                    $indata['guigedata'] = json_encode($new_guige,JSON_UNESCAPED_UNICODE);
                    //dump($new_guige);
                }


                $proid = $exit_product['id'];
                //dump($indata);
                Db::name('shop_product')->where('id',$proid)->update($indata);
            }else{
                if(getcustom('yx_queue_free')){
                    $queue_free_join = Db::name('queue_free_set')->where('aid',aid)->where('bid',0)->value('product_join');
                    $indata['queue_free_status'] = $queue_free_join;
                }
                $proid = Db::name('shop_product')->insertGetId($indata);
            }
            //删除相同产品id,相同规格的规格数据
            $same = Db::name('shop_guige')->where('aid',aid)->where('proid',$proid)->where('name',$guige_str)->find();
            $guige_id = 0;
            if($same){
                $guige_id = $same['id'];
            }
            //根据产品表规格数据的顺序排列规格表的ks顺序
            $ks_str = '';
            $guige_arr = explode(',',$guige_str);
            $guige_data = json_decode($indata['guigedata'],true);
            foreach($guige_data as $guige_k=>$guige_v){
                $title = $guige_arr[$guige_k];
                $item_titles = array_column($guige_v['items'],'title');
                $ks = array_search($title,$item_titles);
                $ks_str .= $ks.',';
            }
            $ks_str = rtrim($ks_str,',');

            //dump($ks_str);
            //var_dump($ks_str);exit;
            $ggdata = [];
            $ggdata['aid'] = aid;
            $ggdata['proid'] = $proid;
            $ggdata['procode'] = $data[2];
            $ggdata['barcode'] = $data[3];
            $ggdata['name'] = $guige_str;
            $ggdata['ks'] = $ks_str;
            $ggdata['pic'] = $guige_pic;
            $ggdata['cost_price'] = $indata['cost_price'];
            $ggdata['market_price'] = $indata['market_price'];
            $ggdata['sell_price'] = $indata['sell_price'];
            $ggdata['stock'] = $indata['stock'];
            $ggdata['weight'] = $indata['weight'];
            //会员价
            $ggdata['lvprice_data'] = json_encode($lvprice_data);

            //dump($ggdata);exit;
            if($guige_id){
                Db::name('shop_guige')->where('id',$guige_id)->update($ggdata);
            }else{
                Db::name('shop_guige')->insert($ggdata);
            }


            $insertnum++;
        }
        //die('stop');
        Db::commit();
        \app\commons\System::plog('导入商品');
        $suc_total = $pagelimit*($pagenum-1)+$insertnum;
        $status = 1;
        if($suc_total>=$data_count){
            $status = 2;
        }
        $remain = $data_count-$suc_total;
        return json(['status'=>$status,'msg'=>'成功导入'.$suc_total.'条数据,剩余'.$remain]);
    }

    //导出
    public function excel(){

        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order');
        }else{
            $order = 'sort desc,id desc';
        }
        $where = array();
        $where[] = ['s.aid','=',aid];
        if(bid==0){
            if(input('param.bid')){
                $where[] = ['s.bid','=',input('param.bid')];
            }elseif(input('param.showtype')==2){
                $where[] = ['s.bid','>',0];
                $where[] = ['s.linkid','=',0];
            }elseif(input('param.showtype')=='all'){
                $where[] = ['s.bid','>=',0];
            }elseif(input('param.showtype')==21){
                $where[] = ['s.bid','=',-1];
            }else{
                $where[] = ['bid','=',0];
            }
        }else{
            $where[] = ['s.bid','=',bid];
        }
        if(input('ids')){
            $where[] = ['s.id','in',input('ids')];
        }
        $where[] = ['douyin_product_id','=',''];
        if(getcustom('product_supply_chain')){
            //供应链选品单独显示
            $where[] = ['product_type','<>',7];
        }
        if(input('?param.ischecked') && input('param.ischecked')!=='') $where[] = ['s.ischecked','=',$_GET['ischecked']];
        if(input('param.name')) $where[] = ['s.name','like','%'.input('name').'%'];
        if(input('?param.status') && input('param.status')!==''){
            $status = input('param.status');
            $nowtime = time();
            $nowhm = date('H:i');
            if($status==1){
                $where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");
            }else{
                $where[] = Db::raw("`status`=0 or (`status`=2 and (unix_timestamp(start_time)>$nowtime or unix_timestamp(end_time)<$nowtime)) or (`status`=3 and ((start_hours<end_hours and (start_hours>'$nowhm' or end_hours<'$nowhm')) or (start_hours>=end_hours and (start_hours>'$nowhm' and end_hours<'$nowhm'))) )");
            }
        }
        if(input('?param.cid') && input('param.cid')!==''){
            $cid = input('param.cid');
            //子分类
            $clist = Db::name('shop_category')->where('aid',aid)->where('pid',$cid)->column('id');
            if($clist){
                $clist2 = Db::name('shop_category')->where('aid',aid)->where('pid','in',$clist)->column('id');
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
        }
        if(input('?param.cid2') && input('param.cid2')!==''){
            $cid = input('param.cid2');
            //子分类
            $clist = Db::name('shop_category2')->where('aid',aid)->where('pid',$cid)->column('id');
            if($clist){
                $clist2 = Db::name('shop_category2')->where('aid',aid)->where('pid','in',$clist)->column('id');
                $cCate = array_merge($clist, $clist2, [$cid]);
                if($cCate){
                    $whereCid = [];
                    foreach($cCate as $k => $c2){
                        $whereCid[] = "find_in_set({$c2},cid2)";
                    }
                    $where[] = Db::raw(implode(' or ',$whereCid));
                }
            } else {
                $where[] = Db::raw("find_in_set(".$cid.",cid2)");
            }
        }
        if(input('?param.gid') && input('param.gid')!=='') $where[] = Db::raw("find_in_set(".input('param.gid/d').",gid)");

        if(input('?param.wxvideo_status') && input('param.wxvideo_status')!==''){
            if(input('param.wxvideo_status') < 5){
                if(input('param.wxvideo_status') == 0){
                    $where[] = ['wxvideo_product_id','=',''];
                }else{
                    $where[] = ['wxvideo_edit_status','=',input('param.wxvideo_status')];
                }
            }else{
                $where[] = ['wxvideo_status','=',input('param.wxvideo_status')];
            }
        }
        if(getcustom('supply_zhenxin')){
            if(input('?param.source') && input('param.source')!==''){
            	$source = input('param.source');
            	if($source == 'self'){
            		$where[] = ['issource','=',0];
            	}else{
            		$where[] = ['issource','=',1];
            		$where[] = ['source','=',$source];
            	}
            }
        }
        $field = 's.id,s.procode,g.barcode,g.name guige_name,s.pics,s.detail,g.pic guige_pic,s.name,s.sellpoint,s.product_link,g.stock,g.weight,g.sell_price,g.market_price
            ,g.cost_price,g.lvprice_data,s.cid,s.gid,s.fwid,s.product_type,s.freighttype,s.freightdata,s.freightcontent,s.fenhongset,s.teamfenhongset,s.teamfenhongdata1
            ,s.teamfenhongdata2,s.gdfenhongset,s.gdfenhongdata1,s.gdfenhongdata2,s.areafenhongset,s.areafenhongdata1,s.areafenhongdata2,s.scoredkmaxset,s.scoredkmaxval
            ,s.showtj,s.gettj,g.sales,s.status,s.start_time,s.end_time,s.start_hours,s.end_hours,s.pic,s.video,s.guigedata';
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
        $list = Db::name('shop_product')
            ->alias('s')
            ->join('shop_guige g','s.id=g.proid','left')
            ->where($where)->order($order)->field($field)->page($page,$limit)->select()->toArray();
        //echo Db::getlastSql();
        //dump($list);exit;
        $count = 0 + Db::name('shop_product')->alias('s')
                ->join('shop_guige g','s.id=g.proid','left')->where($where)->count();
        foreach($list as $k=>$v){
            $gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$v['id'])->select()->toArray();
            $ggdata = array();
            foreach($gglist as $gg){
                $ggdata[] = $gg['name'].' × '.$gg['stock'] .' <button class="layui-btn layui-btn-xs layui-btn-disabled" style="color:#333">￥'.$gg['sell_price'].'</button>';
            }
            $list[$k]['ggdata'] = implode('<br>',$ggdata);
            $list[$k]['realsalenum'] = Db::name('shop_order_goods')->where('aid',aid)->where('proid',$v['id'])->where('status','in','1,2,3')->sum('num');
            if($v['status']==2){ //设置上架时间
                if(strtotime($v['start_time']) <= time() && strtotime($v['end_time']) >= time()){
                    $list[$k]['status'] = 1;
                }else{
                    $list[$k]['status'] = 0;
                }
            }
            if($v['status']==3){ //设置上架周期
                $start_time = strtotime(date('Y-m-d '.$v['start_hours']));
                $end_time = strtotime(date('Y-m-d '.$v['end_hours']));
                if(($start_time < $end_time && $start_time <= time() && $end_time >= time()) || ($start_time >= $end_time && ($start_time <= time() || $end_time >= time()))){
                    $list[$k]['status'] = 1;
                }else{
                    $list[$k]['status'] = 0;
                }
            }
            if($v['bid'] == -1) $data[$k]['sort'] = $v['sort'] - 1000000;
        }
        //dump($list);
        $title = ["商品ID","明细ID","商品编码","规格编码","颜色","规格","其他规格","商品图片","商品详情（图片列表）","规格图片","商品名称","SKU商品简称","商品卖点","商品描述","宝贝链接","库存",
            "重量","销售价","市场价","成本价","1级供货价","2级供货价","3级供货价","4级供货价","5级供货价","商品分类","商品分组","商品服务","商品类型","配送模板类型","模板信息","分红设置",
            "团队分红设置","团队分红比例","股东分红设置","股东分红比例","区域代理分红设置","区域代理分红比例","积分抵扣设置","积分抵扣设置比例","显示条件","购买条件","销量",
            "状态","商品主图","商品视频","商品详情（富文本）"
        ];
        $data = array();
        foreach($list as $k=>$vo){
            //商品分类
            if(bid > 0){
                $cateArr = Db::name('shop_category2')->Field('id,name')->where('aid',aid)
                    ->where('bid',bid)->whereIn('id',$vo['cid'])->column('name');
            }else{
                $cateArr = Db::name('shop_category')->where('aid',aid)->whereIn('id',$vo['cid'])->column('name');
            }

            $cnames = implode(',',$cateArr);
            //商品分组
            if($vo['gid']){
                $groupArr = Db::name('shop_group')->where('aid',aid)->whereIn('id',$vo['gid'])->column('name');
                $group_names = implode(',',$groupArr);
            }
            //商品服务
            if($vo['fwid']){
                $fuwuArr = Db::name('shop_fuwu')->where('aid',aid)->whereIn('id',$vo['fwid'])->column('name');
                $fw_names = implode(',',$fuwuArr);
            }
            //商品类型
            $product_type_name = $vo['product_type']==1?'2.眼镜商品':'1.普通商品';
            //模板信息
            $freightdata = '';
            if($vo['freighttype']==0){
                $freightdata = $vo['freightdata'] ;
            }
            if($vo['freighttype']==3){
                $freightdata = $vo['freightcontent'] ;
            }
            //团队分红
            $teamfenhongdata = $vo['teamfenhongdata1'];
            if( $vo['teamfenhongset']==2){
                $teamfenhongdata = $vo['teamfenhongdata2'];
            }
            $teamfenhongdata = json_decode($teamfenhongdata,true);
            $teamfenhongdata_str = '';
            foreach($teamfenhongdata as $team_level=>$team_data){
                if($team_data['score']>0){
                    $commission = $team_data['score'];
                }else{
                    $commission = $team_data['commission']?:'0';
                }
                $teamfenhongdata_str .= $team_level.':'.$commission.',';
            }
            $teamfenhongdata_str = rtrim($teamfenhongdata_str,',');
            //股东分红
             $gdfenhongdata = $vo['gdfenhongdata1'];
            if( $vo['gdfenhongset']==2){
                $gdfenhongdata = $vo['gdfenhongdata2'];
            }
            $gdfenhongdata = json_decode($gdfenhongdata,true);
            $gdfenhongdata_str = '';
            foreach($gdfenhongdata as $gudong_level=>$gudong_data){
                if($gudong_data['score']>0){
                    $commission = $gudong_data['score'];
                }else{
                    $commission = $gudong_data['commission']?:'0';
                }
                $gdfenhongdata_str .= $gudong_level.':'.$commission.',';
            }
            $gdfenhongdata_str = rtrim($gdfenhongdata_str,',');
            //区域代理分红
            $areafenhongdata = $vo['areafenhongdata1'];
            if( $vo['areafenhongset']==2){
                $areafenhongdata = $vo['areafenhongdata2'];
            }
            $areafenhongdata = json_decode($areafenhongdata,true);
            $areafenhongdata_str = '';
            foreach($areafenhongdata as $area_level=>$area_data){
                if($area_data['score']>0){
                    $commission = $area_data['score'];
                }else{
                    $commission = $area_data['commission']?:'0';
                }
                $areafenhongdata_str .= $area_level.':'.$commission.',';
            }
            $areafenhongdata_str = rtrim($areafenhongdata_str,',');
            //状态
            $status_str = $vo['status']==1?'已上架':'已下架';
            //商品详情
            $detail = json_decode($vo['detail'],true);
            $detail_pics = [];
            $detail_content = '';
            foreach($detail as $d_k=>$d_v){
                if($d_v['temp']=='picture' || $d_v['temp']=='pictures'){
                    foreach($d_v['data'] as $data_v){
                        $detail_pics[] = $data_v['imgurl'];
                    }
                }elseif($d_v['temp']=='richtext'){
                    $detail_content = $d_v['content'];
                    $pattern ="/<img .*?src=[\'|\"](.*?(?:[\.gif|\.jpg|\.png|\.jpeg]))[\'|\"].*?[\/]?>/";
                    preg_match_all($pattern,$detail_content,$match);
                    if(isset($match[1])&&!empty($match[1])) {
                        $detail_pics = array_merge($detail_pics,$match[1]);
                    }
                }
            }
            $detail_pics = json_encode($detail_pics,JSON_UNESCAPED_SLASHES);
            //级别价格
            $lvprice_arr = json_decode($vo['lvprice_data'],true);
            $lvprice_arr = array_values($lvprice_arr);
            //规格处理
            $guige_yanse = '';
            $guige_guige = '';
            $guige_qita = '';
            $guigedata = json_decode($vo['guigedata'],true);
            $guige_name_arr = explode(',',$vo['guige_name']);
            foreach($guigedata as $g_k=>$g_v){
                if($g_v['title']=='颜色'){
                    $guige_yanse = $guige_name_arr[$g_v['k']];
                }elseif($g_v['title']=='规格'){
                    $guige_guige = $guige_name_arr[$g_v['k']];
                }else{
                    $guige_qita .= $g_v['title'].':'.$guige_name_arr[$g_v['k']].',';
                }
            }
            $guige_qita = rtrim($guige_qita,',');

            //商品图片
            $pics = json_encode(explode(',',$vo['pics']),JSON_UNESCAPED_SLASHES);
            //显示条件
            $showtj = implode('|',explode(',',$vo['showtj']));
            //购买条件
            $gettj = implode('|',explode(',',$vo['gettj']));

            $data[] = [
                $vo['id'],
                '',//明细ID
                $vo['procode'],//商品编码
                $vo['barcode'],//规格编码
                $guige_yanse,//颜色 todo
                $guige_guige,//规格 todo
                $guige_qita,//其他规格 todo
                $pics,//商品图片
                $detail_pics,//商品详情（图片列表）
                $vo['guige_pic'],//规格图片
                $vo['name'],//商品名称
                '',//SKU商品简称
                $vo['sellpoint'],//商品卖点
                '',//商品描述
                $vo['product_link'],//宝贝链接
                $vo['stock'],//库存
                $vo['weight'],//重量
                $vo['sell_price'],//销售价
                $vo['market_price'],//市场价
                $vo['cost_price'],//成本价
                $lvprice_arr[0],//1级供货价
                $lvprice_arr[1],//2级供货价
                $lvprice_arr[2],//3级供货价
                $lvprice_arr[3],//4级供货价
                $lvprice_arr[4],//5级供货价
                $cnames,//商品分类
                $group_names,//商品分组
                $fw_names,//商品服务
                $product_type_name,//商品类型
                $vo['freighttype'],//配送模板类型
                $freightdata,//模板信息
                $vo['fenhongset'],//分红设置
                $vo['teamfenhongset'],//团队分红设置
                $teamfenhongdata_str,//团队分红比例
                $vo['gdfenhongset'],//股东分红设置
                $gdfenhongdata_str,//股东分红比例
                $vo['areafenhongset'],//区域代理分红设置
                $areafenhongdata_str,//区域代理分红比例
                $vo['scoredkmaxset'],//积分抵扣设置
                $vo['scoredkmaxval'],//积分抵扣设置比例
                $showtj,//显示条件
                $gettj,//购买条件
                $vo['sales'],//销量
                $status_str,//状态
                $vo['pic'],//商品主图
                $vo['video'],//商品视频
                $detail_content,//商品详情（富文本）
            ];
        }
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
        //dump($data);exit;
        $this->export_excel($title,$data);
    }

    public function editManyCategory(){
        if (getcustom('product_category_batch_update')){
            $cid = input('param.cid/s');
            $ids = input('param.ids');
            if(empty($ids)){
                return json(['status'=>0,'msg'=>'请选择需要编辑的商品']);
            }
            if(empty($cid)){
                return json(['status'=>0,'msg'=>'请选择分类']);
            }
            Db::name('shop_product')->where('id','in',$ids)->update(['cid' => $cid]);
            return json(['status'=>1,'msg'=>'操作成功']);
        }
    }
    public function editManyCategory2(){
        if (getcustom('product_category_batch_update')){
            $cid = input('param.cid/s');
            $ids = input('param.ids');
            if(empty($ids)){
                return json(['status'=>0,'msg'=>'请选择需要编辑的商品']);
            }
            if(empty($cid)){
                return json(['status'=>0,'msg'=>'请选择分类']);
            }
            Db::name('shop_product')->where('id','in',$ids)->update(['cid2' => $cid]);
            return json(['status'=>1,'msg'=>'操作成功']);
        }
    }

    //编辑商品
    public function edit2(){
        if(input('param.id')){
            $info = Db::name('shop_product')->where('aid',aid)->where('id',input('param.id/d'))->find();
            if(!$info) showmsg('商品不存在');
            if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
            if(bid != 0 && $info['linkid']!=0 && !getcustom('business_copy_product')) showmsg('无权限操作');
        }

        $usdrate = '0';
        if(getcustom('price_dollar')){
            $sysset = Db::name('shop_sysset')->field('usdrate')->where(['aid'=>aid])->find();
            $usdrate = $sysset['usdrate']>0 ? $sysset['usdrate'] : 0;
            View::assign('usdrate',$usdrate);
        }

        //多规格
        $newgglist = array();
        if($info){
            $gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$info['id'])->select()->toArray();
            foreach($gglist as $k=>$v){
                if(getcustom('price_dollar') && $usdrate>0){
                    $v['usdprice'] = round($v['sell_price']/$usdrate,2);
                }
                $v['lvprice_data'] = json_decode($v['lvprice_data']);
                if($v['ks']!==null){
                    $newgglist[$v['ks']] = $v;
                }else{
                    Db::name('shop_guige')->where('aid',aid)->where('id',$v['id'])->update(['ks'=>$k]);
                    $newgglist[$k] = $v;
                }
            }
        }
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
        }
        //分组
        $glist = Db::name('shop_group')->where('aid',aid)->order('sort desc,id')->select()->toArray();
        $freightdata = array();
        if($info && $info['freightdata']){
            $freightdata = Db::name('freight')->where('aid',aid)->where('id','in',$info['freightdata'])->order('sort desc,id')->select()->toArray();
        }

        $weightdata = array();
        if(getcustom('weight_template')){
            if($info && $info['weightdata']){
                $weightdata = Db::name('shop_weight_template')->where('aid',aid)->where('id','in',$info['weightdata'])->order('sort desc,id')->select()->toArray();
            }
        }

        $bset = Db::name('business_sysset')->where('aid',aid)->find();
        //分成结算类型
        $sysset = Db::name('admin_set')->where('aid',aid)->find();
        if($sysset['fxjiesuantype'] == 1) {
            $jiesuantypeDesc = '成交价';
        }elseif($sysset['fxjiesuantype'] == 2) {
            $jiesuantypeDesc = '销售利润';
        } else {
            $jiesuantypeDesc = '销售价';
        }

        $info['showtj'] = explode(',',$info['showtj']);
        $info['gettj'] = explode(',',$info['gettj']);
        $info['cid'] = explode(',',$info['cid']);
        $info['cid2'] = explode(',',$info['cid2']);
        $info['commission_mid'] = $info['commission_mid'] ? json_decode($info['commission_mid'],true) : [];
        if($info['bid'] == -1) $info['sort'] = $info['sort'] - 1000000;
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        if(getcustom('plug_businessqr') && bid != 0) {
            $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('show_business',1)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
            $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('show_business',1)->order('sort,id')->select()->toArray();
        } else {
            $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
            $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        }
        $gdlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('fenhong','>','0')->order('sort,id')->select()->toArray();
        $teamlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('teamfenhonglv','>','0')->order('sort,id')->select()->toArray();
        $areafhlevellist = Db::name('member_level')->where('aid',aid)->where('areafenhong','>','0')->select()->toArray();

        if(getcustom('teamfenhong_pingji')){
            $teampjlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('teamfenhong_pingji_lv','>','0')->order('sort,id')->select()->toArray();
            View::assign('teampjlevellist',$teampjlevellist);
        }
        if(getcustom('teamfenhong_jiandan')){
            $teamjdlevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('teamfenhong_jiandan_lv','>','0')->order('sort,id')->select()->toArray();
            View::assign('teamjdlevellist',$teamjdlevellist);
        }

        if($info['id']){
            $fuwulist = Db::name('shop_fuwu')->where('aid',aid)->where('bid',$info['bid'])->order('sort desc,id')->select()->toArray();
        }else{
            $fuwulist = Db::name('shop_fuwu')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->select()->toArray();
        }
        if(getcustom('everyday_hongbao')) {
            $hset = \db('hongbao_everyday')->where('aid',aid)->find();
            View::assign('ehb_status',$hset['status']);
        }
        if(getcustom('product_bind_mendian')){
            $bindBid = bid;
            if($info && $info['bid']!=bid){
                $bindBid = $info['bid'];
            }
            $mendianlist = Db::name('mendian')->where('aid',aid)->where('bid',$bindBid)->where('status',1)->select()->toArray();
            View::assign('mendianlist',$mendianlist);
        }

        //商品参数
        $parambid = bid;
        if(getcustom('business_useplatmendian') && $parambid > 0){
            $bset = Db::name('business_sysset')->where('aid',aid)->find();
            if($bset['business_useplatshopparam'] == 1){
                $parambid = 0;
            }
        }
        $whereParam = [];
        $whereParam[] = ['aid','=',aid];
        $whereParam[] = ['status','=',1];
        if($info['cid']){
            $whereCid = [];
            foreach($info['cid'] as $k => $c2){
                if($c2 == '') continue;
                $whereCid[] = "find_in_set({$c2},cid)";
            }
            if($whereCid){
                if(getcustom('business_showplatparam') && $parambid > 0){
                    $whereParam[] = Db::raw("(bid=0 and (".implode(' or ',$whereCid).")) or (bid=".$parambid." and (".implode(' or ',$whereCid). " or cid =''))");
                }else{
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
//        dd($paramList);
        View::assign('paramList',$paramList);



        $paramdata = $info['paramdata'] ? json_decode($info['paramdata'],true) : [];
//        dd($paramdata);
        $auth = [];
        $info['paramdata'] = str_ireplace("'", "\'", $info['paramdata']);
        View::assign('fuwulist',$fuwulist);
        View::assign('aglevellist',$aglevellist);
        View::assign('levellist',$levellist);
        View::assign('gdlevellist',$gdlevellist);
        View::assign('teamlevellist',$teamlevellist);
        View::assign('areafhlevellist',$areafhlevellist);
        View::assign('info',$info);
        View::assign('newgglist',$newgglist);
        View::assign('clist',$clist);
        View::assign('clist2',$clist2);
        View::assign('glist',$glist);
        View::assign('freightdata',$freightdata);
        View::assign('bset',$bset);
        View::assign('jiesuantypeDesc',$jiesuantypeDesc);
        View::assign('paramdata',$paramdata);
        $fromwxvideo = input('param.fromwxvideo')==1?true:false;

        if($fromwxvideo){
            $rs = curl_post('https://api.weixin.qq.com/shop/account/get_brand_list?access_token='.Wechat::access_token(aid,'wx'),'{}');
            $rs = json_decode($rs,true);
            $brand_list = $rs['data'];
        }else{
            $brand_list = [];
        }
        $brand_list[] = ['brand_id'=>2100000000,'brand_wording'=>'无品牌'];
        View::assign('brand_list',$brand_list);
        View::assign('fromwxvideo',$fromwxvideo);

        if(getcustom('pay_yuanbao')) {
            $yuanbao_money_ratio = 0;
            $sysset = Db::name('admin_set')->where('aid',aid)->find();
            if($sysset){
                $yuanbao_money_ratio = $sysset['yuanbao_money_ratio'];
            }
            View::assign('yuanbao_money_ratio',$yuanbao_money_ratio);
        }
        if(getcustom('plug_tengrui')){
            $groupdata = array();
            if($info && $info['group_ids']){
                $groupdata = Db::name('member_tr_group')->where('aid',aid)->where('id','in',$info['group_ids'])->order('id desc')->select()->toArray();
            }
            View::assign('groupdata',$groupdata);
        }
        if(getcustom('diy_light')){
            if($this->auth_data == 'all' || in_array('Backstage/diylight',$this->auth_data)){
                $set = Db::name('diylight_set')->where('aid',aid)->find();
                if($set['status'] == 1){
                    $auth['diy_light'] = true;
                }
            }
        }

        View::assign('auth',$auth);

        $business_selfscore = 0;
        if((getcustom('business_selfscore') || getcustom('business_score_jiesuan')) && bid > 0){
            $bset = Db::name('business_sysset')->where('aid',aid)->find();
            $business_selfscore = $bset['business_selfscore'];
        }
        View::assign('business_selfscore',$business_selfscore);

        if(getcustom('buybutton_custom')){
            $buybtn_status = $this->admin['buybtn_status']?$this->admin['buybtn_status']:0;
            if($buybtn_status && bid !=0){
                $buybtn_status = 0;
                $business = Db::name('business')->where('aid',aid)->where('id',bid)->field('id,buybtn_status')->find();
                if($business && $business['buybtn_status'] ==1){
                    $buybtn_status = 1;
                }
            }
            View::assign('buybtn_status',$buybtn_status);
        }
        if(getcustom('addcart_button_custom')){
            $addcart_button_custom_status = 0;
            if($this->auth_data == 'all' || in_array('ShopProduct/addcart_button_custom',$this->auth_data)){
                $addcart_button_custom_status = 1;
            }
            View::assign('addcart_button_custom_status',$addcart_button_custom_status);
        }

        if(getcustom('weight_template')){
            View::assign('weightdata',$weightdata);
        }
        return View::fetch();
    }

	public function mendian_hexiao_set(){
		$proid = input('param.proid');
		if(request()->isAjax()){
			if(getcustom('product_mendian_hexiao_givemoney')){
				$proid = input('post.proid/d');
				$mdids = input('post.mdid/a');
				$hexiaogivepercent = input('post.hexiaogivepercent/a');
				$hexiaogivemoney = input('post.hexiaogivemoney/a');
				$setids = array();
			
				$newmdids = implode(',',$mdids);
				Db::name('shop_product')->where('id',$proid)->update(['bind_mendian_ids'=>$newmdids]);	


				foreach($mdids as $k=>$v){
					$hexiao_set = Db::name('shop_product_mendian_hexiaoset')->where('aid',aid)->where('mdid',$v)->where('bid',bid)->where('proid',$proid)->find();
					$data = [];
					$data['aid'] = aid;
					$data['bid'] = bid;
					$data['proid'] = $proid;
					$data['mdid'] = $v;
					$data['hexiaogivepercent'] = $hexiaogivepercent[$k];
					$data['hexiaogivemoney'] = $hexiaogivemoney[$k];
					$data['createtime'] = time();
					if($hexiao_set){
						Db::name('shop_product_mendian_hexiaoset')->where('id',$hexiao_set['id'])->update($data);
						$setid = $hexiao_set['id'];
					}else{
						$setid = Db::name('shop_product_mendian_hexiaoset')->insertGetId($data);
					}
					$setids[] = $setid;
				}
				Db::name('shop_product_mendian_hexiaoset')->where('aid',aid)->where('proid',$proid)->where('id','not in',$setids)->delete();

			
				\app\commons\System::plog('设置门店提成'.$proid);
				return json(['status'=>1,'msg'=>'操作成功']);

			}
		}
		$datalist = Db::name('shop_product_mendian_hexiaoset')->where('aid',aid)->where('bid',bid)->where('proid',$proid)->select()->toArray();
		if($datalist){
			foreach($datalist as &$d){
				$mendian = Db::name('mendian')->field('name')->where('id',$d['mdid'])->find();
				$d['name'] = $mendian['name'];
			}
		}else{
			$product = Db::name('shop_product')->field('bind_mendian_ids')->where('id',$proid)->find();
			$where = [];
			$where[] = ['aid','=',aid];
			if($product['bind_mendian_ids']!='-1'){
				$bind_mendian_ids = explode(',',$product['bind_mendian_ids']);
				$where[] = ['id','in',$bind_mendian_ids];
			}else{
				if($product['bid']==0){
					$bids = Db::name('business')->where('aid',aid)->where('isplatform_auth',1)->where('status',1)->column('id');	
					array_push($bids,$product['bid']);
					$where[] = ['bid','in',$bids];
				}else{
					$where[] = ['bid','=',$product['bid']];
				}
			}	
			$datalist = Db::name('mendian')->field('id,name')->where($where)->select()->toArray();
			foreach($datalist as &$mendian){
				$mendian['mdid'] = $mendian['id'];
				$mendian['hexiaogivepercent'] = 0;
				$mendian['hexiaogivemoney'] = 0;
			}
		}

		View::assign('datalist',$datalist);
		View::assign('proid',$proid);
		return View::fetch();
	}

    //同步商品到商户
    public function businessProcopy($proids='',$is_update = 0){
        if(getcustom('product_sync_business')){
            set_time_limit(0);
            ini_set('memory_limit','1000m');
            if(empty($proids)){
                $proids = input('ids');
            }
            if($proids && !is_array($proids)){
                $proids = explode(',',$proids);
            }
            $bid = input('tobid')?:0;
            if($is_update){
                $bid = Db::name('shop_product')->where('aid',aid)->where('plate_id','in',$proids)->column('bid');
                if(empty($bid)){
                    return true;
                }
            }
            $map = [];
            $map[] = ['aid','=',aid];
            if($bid){
                $map[] = ['id','in',$bid];
            }
            $map[] = ['sync_plate_product','=',1];
            $blist = Db::name('business')->where($map)->order('sort desc,id desc')->select()->toArray();
            if(empty($blist)){
                return true;
            }
            $where = [];
            $where[] = ['aid','=',aid];
            if(!empty($proids)){
                $where[] = ['id','in',$proids];
            }
            if(input('showtype') && input('showtype')==2){
                $where[] = ['bid','>',0];
            }
            $product_lists =  Db::name('shop_product')->where($where)->select()->toArray();
            foreach($product_lists as $product){
                //$proid = $product['id'];
                if($product ){
                    $gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
                    foreach($blist as $business){
                        $bpro = Db::name('shop_product')->where('aid',aid)->where('bid',$business['id'])->where('plate_id',$product['id'])->find();
                        $data = $product;
                        $data['bid'] = $business['id'];
                        $data['plate_id'] = $product['id'];
                        unset($data['id']);
                        unset($data['wxvideo_product_id']);
                        unset($data['wxvideo_edit_status']);
                        unset($data['wxvideo_status']);
                        unset($data['wxvideo_reject_reason']);
                        if(isset($data['bind_mendian_ids'])){
                            $data['bind_mendian_ids'] = '-1';
                        }
                        if($bpro){
                            unset($data['stock']);
                            Db::name('shop_product')->where('id',$bpro['id'])->update($data);
                            $newproid = $bpro['id'];
                        }else{
                            $newproid = Db::name('shop_product')->insertGetId($data);
                        }

                        $newggids = [];
                        foreach($gglist as $gg){
                            $ggdata = $gg;
                            $ggdata['proid'] = $newproid;
                            $ggdata['plate_id'] = $gg['id'];
                            unset($ggdata['id']);

                            $guige = Db::name('shop_guige')->where('aid',aid)->where('proid',$newproid)->where('ks',$ggdata['ks'])->find();
                            if($guige){
                                unset($ggdata['stock']);
                                Db::name('shop_guige')->where('aid',aid)->where('id',$guige['id'])->update($ggdata);
                                $ggid = $guige['id'];
                            }else{
                                $ggid = Db::name('shop_guige')->insertGetId($ggdata);
                            }
                            $newggids[] = $ggid;
                        }
                        Db::name('shop_guige')->where('aid',aid)->where('proid',$newproid)->where('id','not in',$newggids)->delete();
                    }
                }
            }
            \app\commons\System::plog('商城商品同步到商家'.$newproid);
            return json(['status'=>1,'msg'=>'同步成功','proid'=>$newproid]);
        }
    }
    //改价格
    public function changestock(){
        if(getcustom('product_sync_business')) {
            $proid = input('post.proid/d');
            $product = Db::name('shop_product')->where('aid', aid)->where('id', $proid)->find();
            if (!$product) showmsg('商品不存在');
            if (bid != 0 && $product['bid'] != bid) showmsg('无权限操作');
            $data = [];
            $data['stock'] = 0;
            foreach (input('post.option/a') as $v) {
                $data['stock'] += $v['stock'];
            }
            Db::name('shop_product')->where('aid', aid)->where('id', $proid)->update($data);
            foreach (input('post.option/a') as $ks => $v) {
                $ggdata = [];
                $ggdata['stock'] = $v['stock'] > 0 ? $v['stock'] : 0;
                $lvprice_data = [];
                Db::name('shop_guige')->where('aid', aid)->where('id', $v['ggid'])->update($ggdata);
            }
            return json(['status' => 1, 'msg' => '操作成功']);
        }
    }
    public function erpUnBind(){
        if(getcustom('erp_wangdiantong')){
            $proid = input('post.proid/d');
            $ggids = input('post.ggid/a');
            if(empty($ggids)){
                return json(['status' => 0, 'msg' => '请选择要解绑的单品']);
            }
            $product = Db::name('shop_product')->where('aid', aid)->where('id', $proid)->find();
            if(!$product)  return json(['status' => 0, 'msg' => '商品不存在']);
            if(bid != 0 && $product['bid']!=bid) return json(['status' => 0, 'msg' => '无权操作']);
            Db::name('shop_guige')->where('aid',aid)->where('proid',$proid)->where('id','in',$ggids)->update(['wdt_status'=>2]);
            return json(['status' => 1, 'msg' => '解绑成功']);
        }
    }

    //刷新旺店通库存
    public function refreshWdt(){
        if(getcustom('erp_wangdiantong')) {
            $ids = input('param.ids/a');
            if ($ids) {
                $where = [];
                $where[] = ['g.aid', '=', aid];
                $where[] = ['p.bid', '=', bid];
                $where[] = ['g.proid', 'in', $ids];
                $where[] = ['g.wdt_status', '=', 1];
                $gglist = Db::name('shop_guige')->alias('g')->join('shop_product p', 'g.proid=p.id')->where($where)->field('g.id,g.proid,g.barcode')->select()->toArray();
                $c = new \app\customs\Wdt(aid, bid);
                foreach ($gglist as $k => $v) {
                    $c->stockQueryBySpec($v['barcode'], $v['proid']);
                }
            }
            return json(['status' => 1, 'msg' => '刷新成功']);
        }
    }


    public function refreshzxfreight(){
        if(getcustom('supply_zhenxin')) {
            $id = input('?param.id')?input('param.id/d'):0;
            $product = Db::name('shop_product')->where('id',$id)->field('id,sproid')->find();
            if(!$product){
                return json(['status' =>0, 'msg' => '商品不存在']);
            }
            $zxhtml = '';
            if($product['sproid']){
                $getFreight2 = \app\customs\SupplyZhenxinCustom::getFreight2(aid,bid,$data['sproid']);
                if($getFreight2 && $getFreight2['status'] == 1){
                    $data = [];
                    $zxhtml = $data['zxhtml'] = $getFreight2['zxhtml'];
                    $data['zxdata'] = $getFreight2['zxdata']?json_encode($getFreight2['zxdata']):'';
                    Db::name('shop_product')->where('id',$id)->update($data);
                }
            }
            return json(['status'=>1,'msg' => '刷新成功','zxhtml'=>$zxhtml]);
        }
    }

    // 产品库
    public function library(){
    	if (!getcustom('product_library_admin_user')){
			showmsg('无权限操作');
		}
        $product_library_aid = Db::name('admin')->where('id',aid)->value('product_library_aid');
        
		if(request()->isAjax()){
			$page = input('param.page')?:1;
			$limit = input('param.limit')?:10;
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id desc';
			}
			$where = array();

			
	    	// $product_library_aids = explode(',', $product_library_aids);
	    	$where[] = ['aid','=',$product_library_aid];
	    	$where[] = ['status','=',1];

			// $where[] = ['bid','=',0];
			$where[] = ['douyin_product_id','=',''];

			if(input('?param.ischecked') && input('param.ischecked')!=='') $where[] = ['ischecked','=',$_GET['ischecked']];
			if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];
			
            if(input('?param.cid') && input('param.cid')!==''){
				$cid = input('param.cid');
				//子分类
				$clist = Db::name('shop_category')->where('aid',$product_library_aid)->where('pid',$cid)->column('id');
				if($clist){
					$clist2 = Db::name('shop_category')->where('aid',$product_library_aid)->where('pid','in',$clist)->column('id');
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
			}

			if(input('?param.gid') && input('param.gid')!=='') $where[] = Db::raw("find_in_set(".input('param.gid/d').",gid)");

			if(input('?param.wxvideo_status') && input('param.wxvideo_status')!==''){
				if(input('param.wxvideo_status') < 5){
					if(input('param.wxvideo_status') == 0){
						$where[] = ['wxvideo_product_id','=',''];
					}else{
						$where[] = ['wxvideo_edit_status','=',input('param.wxvideo_status')];
					}
				}else{
					$where[] = ['wxvideo_status','=',input('param.wxvideo_status')];
				}
			}


            if(input('param.proids'))$where[] = ['id','in',input('param.proids')];
			$count = 0 + Db::name('shop_product')->where($where)->count();

			$data = Db::name('shop_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			
			if(bid > 0){
				$cdata2 = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->column('name','id');
			}
            $iscustomoption = 0;

			foreach($data as $k=>$v){

				$cdata = Db::name('shop_category')->where('aid',$v['aid'])->column('name','id');

				$gglist = Db::name('shop_guige')->where('aid',aid)->where('proid',$v['id'])->select()->toArray();
				$ggdata = array();
				foreach($gglist as $gg){
					if($gg['stock']<$gg['stock_warning']){
					}else{
						$ggdata[] = $gg['name'].' × '.$gg['stock'] .' <button class="layui-btn layui-btn-xs layui-btn-disabled" style="color:#333">￥'.$gg['sell_price'].'</button>';
					}
				}
                $v['cid'] = explode(',',$v['cid']);
                $data[$k]['cname'] = null;
                if ($v['cid']) {
                    foreach ($v['cid'] as $cid) {
                        if($data[$k]['cname'])
                            $data[$k]['cname'] .= ' ' . $cdata[$cid];
                        else
                            $data[$k]['cname'] .= $cdata[$cid];
                    }
                }
				if($v['bid'] > 0){
					$v['cid2'] = explode(',',$v['cid2']);
					$data[$k]['cname2'] = null;
					if ($v['cid2']) {
						foreach ($v['cid2'] as $cid) {
							if($data[$k]['cname2'])
								$data[$k]['cname2'] .= ' ' . $cdata2[$cid];
							else
								$data[$k]['cname2'] .= $cdata2[$cid];
						}
					}
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['cname2'] = '';
					$data[$k]['bname'] = '平台自营';
				}

				$data[$k]['ggdata'] = implode('<br>',$ggdata);
				$sales_num = Db::name('shop_order_goods')->where('aid',aid)->where('proid',$v['id'])->where('status','in','1,2,3')->sum('num');
				$refund_num = Db::name('shop_refund_order_goods')
                    ->alias('rg')
                    ->join('shop_refund_order ro','rg.refund_orderid=ro.id')
                    ->join('shop_order o','ro.orderid=o.id')
                    ->where('o.status','in','1,2,3')
                    ->where('rg.aid',aid)->where('rg.proid',$v['id'])->where('ro.refund_status',2)->sum('rg.refund_num');
                $realsalenum = $sales_num-$refund_num;
				$data[$k]['realsalenum'] = $realsalenum>0?$realsalenum:0;
				if($v['status']==2){ //设置上架时间
					if(strtotime($v['start_time']) <= time() && strtotime($v['end_time']) >= time()){
						$data[$k]['status'] = 1;
					}else{
						$data[$k]['status'] = 0;
					}
				}
				if($v['status']==3){ //设置上架周期
					$start_time = strtotime(date('Y-m-d '.$v['start_hours']));
					$end_time = strtotime(date('Y-m-d '.$v['end_hours']));
					if(($start_time < $end_time && $start_time <= time() && $end_time >= time()) || ($start_time >= $end_time && ($start_time <= time() || $end_time >= time()))){
						$data[$k]['status'] = 1;
					}else{
						$data[$k]['status'] = 0;
					}
				}
				if($v['bid'] == -1) $data[$k]['sort'] = $v['sort'] - 1000000;
                $data[$k]['iscustomoption'] = $iscustomoption;
			}
			$page_total = ceil($count/$limit);
			$page = [
                    'current' => (int)$page,
                    'limit' => (int)$limit,
                    'pages' => $page_total,
                    'total' => $count,
                ];
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'page'=>$page]);
		}
		//分类
		$clistl = Db::name('shop_category')->Field('id,name')->where('aid',$product_library_aid)->where('pid',0)->order('sort desc,id')->select()->toArray();
		foreach($clistl as $k=>$v){
            $childl = Db::name('shop_category')->Field('id,name')->where('aid',$product_library_aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
            foreach($childl as $k2=>$v2){
                $childl2 = Db::name('shop_category')->Field('id,name')->where('aid',$product_library_aid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
                $childl[$k2]['child'] = $childl2;
            }
            $clistl[$k]['child'] = $childl;
		}

		$clist = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',0)->order('sort desc,id')->select()->toArray();
		foreach($clist as $k=>$v){
            $child = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
            foreach($child as $k2=>$v2){
                $child2 = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
                $child[$k2]['child'] = $child2;
            }
            $clist[$k]['child'] = $child;
		}
		if(bid > 0){
			//商家的商品分类
			$clist2 = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray();
			foreach($clist2 as $k=>$v){
				$clist2[$k]['child'] = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
			}
			View::assign('clist2',$clist2);
		}

		//分组
		$glist = Db::name('shop_group')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('clistl',$clistl);
		View::assign('clist',$clist);
		View::assign('glist',$glist);

		$fromwxvideo = input('param.fromwxvideo')==1?true:false;
		View::assign('fromwxvideo',$fromwxvideo);
		
		if($fromwxvideo){
			$rs = curl_post('https://api.weixin.qq.com/shop/account/get_brand_list?access_token='.Wechat::access_token(aid,'wx'),'{}');
			$rs = json_decode($rs,true);
			$brand_list = $rs['data'];
		}else{
			$brand_list = [];
		}

		if(session('BST_ID')){
			$userlist = Db::name('admin_user')->field('id,aid,un')->where('id','<>',$this->user['id'])->where('bid',0)->where('isadmin',1)->select()->toArray();
			View::assign('cancopy',true);
		}else{
			$userlist = [];
			View::assign('cancopy',false);
		}

	
		$default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
		$levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        
		View::assign('levellist',$levellist);
		$brand_list[] = ['brand_id'=>2100000000,'brand_wording'=>'无品牌'];
		View::assign('brand_list',$brand_list);
		View::assign('userlist',$userlist);
		View::assign('admin',$this->admin);

		return View::fetch();

    }
    // 转存
     public function copyProduct($ids='',$cid='', $cid1='', $cid2=''){
     	if (!getcustom('product_library_admin_user')){
			showmsg('无权限操作');
		}
        $product_library_aids = Db::name('admin')->where('id',aid)->value('product_library_aid');
	    $product_library_aids = explode(',', $product_library_aids);
	    // dump($ids); dump($cid); dump($cid1); dump($cid2);dump($product_library_aids);
	    if(!is_array($ids)){
	    	$ids = explode(',', $ids);
	    }
	    
        // 复制 粘贴
        foreach ($ids as $key => $id) {
        	$where = [];
	    	$where[] = ['aid','in',$product_library_aids];
			$product = Db::name('shop_product')->where($where)->where('id',$id)->find();
	
			if(!$product) continue;

			$gglist = Db::name('shop_guige')->where('aid',$product['aid'])->where('proid',$product['id'])->select()->toArray();
			$data = $product;
			$data['aid'] = aid;
			$data['bid'] = bid;
			if(bid == 0){
				$data['cid'] = $cid;
			}else{
				$data['cid'] = $cid1;
				$data['cid2'] = $cid2;
			}
			
			// $data['name'] = '复制-'.$data['name'];
	        if($data['detail']){
	            //处理tab组件
	            $detail = json_decode($data['detail'],true);
	            if($detail){
	                foreach ($detail as $k => $item){
	                    if($item['temp'] == 'tab'){
	                        $detail[$k]['id'] = $item['id'].rand(0,9999);
	                        $tablist = Db::name('designerpage_tab')->where('aid',aid)->where('tabid',$item['id'])->select()->toArray();
	                        if($tablist){
	                            foreach ($tablist as $k2 => $item2){
	                                unset($item2['id']);
	                                $item2['tabid'] = $detail[$k]['id'];
	                                Db::name('designerpage_tab')->insert($item2);
	                            }
	                        }
	                    }
	                }
	                $data['detail'] = json_encode($detail);
	            }
	        }
			unset($data['id']);
			unset($data['wxvideo_product_id']);
			unset($data['wxvideo_edit_status']);
			unset($data['wxvideo_status']);
			unset($data['wxvideo_reject_reason']);
			unset($data['sales']);
			unset($data['realsalenum']);
			unset($data['viewnum']);
			unset($data['sort']);
			$data['status'] = 0;
			$data['createtime'] = time();
			$data['baidu_img_sync'] = 0;
			$data['baidu_img_sync_l'] = 0;
			$newproid = Db::name('shop_product')->insertGetId($data);
			foreach($gglist as $gg){
				$ggdata = $gg;
				$ggdata['aid'] = aid;
				$ggdata['proid'] = $newproid;
				unset($ggdata['id']);
				unset($ggdata['linkid']);
				Db::name('shop_guige')->insert($ggdata);
			}
			// 同步到多商家
			// $this->tongbuproduct($newproid);
        }
    }
    // 转存
     public function zhuanCun(){
        if (getcustom('product_library_admin_user')){
        	// 平台
            $cid = input('param.cid/s');
            // 多商家
            $cid1 = input('param.cid1/s');
            $cid2 = input('param.cid2/s');
            $ids = input('param.ids');
            if(empty($ids)){
                return json(['status'=>0,'msg'=>'请选择需要编辑的商品']);
            }
            // if(bid == 0 && empty($cid) ){
            //     return json(['status'=>0,'msg'=>'请选择分类']);
            // }
            // if(bid > 0){
            // 	if(!$cid1 || !$cid2){
            // 		return json(['status'=>0,'msg'=>'请选择分类']);
            // 	}
                
            // }
            $this->copyProduct($ids,$cid,$cid1,$cid2);

            return json(['status'=>1,'msg'=>'操作成功']);
        }
    }

    // 查看
    //编辑商品
	public function libraryview(){
		if (!getcustom('product_library_admin_user')){
			showmsg('无权限操作');
		}
		if(input('param.id')){
			$info = Db::name('shop_product')->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('商品不存在');
            $score_weishu = 0;
		}

		$usdrate = '0';

        $score_weishu = 0;

		//多规格
		$newgglist = array();
		if($info){
            $info['givescore'] = dd_money_format($info['givescore'],$score_weishu);
			$gglist = Db::name('shop_guige')->where('aid',$info['aid'])->where('proid',$info['id'])->select()->toArray();
			foreach($gglist as $k=>$v){
				$v['lvprice_data'] = json_decode($v['lvprice_data']);
                $v['givescore'] = dd_money_format($v['givescore'],$score_weishu);
                $caneditstock = 1;
                $v['caneditstock'] = $caneditstock;
				if($v['ks']!==null){
					$newgglist[$v['ks']] = $v;
				}
                $gglist[$k]['givescore'] = $v['givescore'];
			}
            $commissiondata3 = json_decode($info['commissiondata3'],true);
			foreach($commissiondata3 as $levelid=>$commission){
                $commissiondata3[$levelid]['commission1'] = dd_money_format($commission['commission1'],$score_weishu);
                $commissiondata3[$levelid]['commission2'] = dd_money_format($commission['commission2'],$score_weishu);
                $commissiondata3[$levelid]['commission3'] = dd_money_format($commission['commission3'],$score_weishu);
            }
            $info['commissiondata3'] = jsonEncode($commissiondata3);
		}
		//分类
		$clist = Db::name('shop_category')->Field('id,name')->where('aid',$info['aid'])->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$child = Db::name('shop_category')->Field('id,name')->where('aid',$info['aid'])->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
			foreach($child as $k2=>$v2){
				$child2 = Db::name('shop_category')->Field('id,name')->where('aid',$info['aid'])->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
				$child[$k2]['child'] = $child2;
			}
			$clist[$k]['child'] = $child;
		}
		if($info['bid'] > 0){
			//商家的分类
			$clist2 = Db::name('shop_category2')->Field('id,name')->where('aid',$info['aid'])->where('bid',$info['bid'])->where('pid',0)->order('sort desc,id')->select()->toArray(); 

			foreach($clist2 as $k=>$v){
				$child = Db::name('shop_category2')->Field('id,name')->where('aid',$info['aid'])->where('bid',$info['bid'])->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
				foreach($child as $k2=>$v2){
					$child2 = Db::name('shop_category2')->Field('id,name')->where('aid',$info['aid'])->where('bid',$info['bid'])->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
					$child[$k2]['child'] = $child2;
				}
				$clist2[$k]['child'] = $child;
			}
		}
		//分组
		$glist = Db::name('shop_group')->where('aid',$info['aid'])->order('sort desc,id')->select()->toArray();
		$freightdata = array();
		if($info && $info['freightdata']){
			$freightdata = Db::name('freight')->where('aid',$info['aid'])->where('id','in',$info['freightdata'])->order('sort desc,id')->select()->toArray();
		}

		$weightdata = array();

		$bset = Db::name('business_sysset')->where('aid',$info['aid'])->find();
		//分成结算类型
        $sysset = Db::name('admin_set')->where('aid',$info['aid'])->find();
        if($sysset['fxjiesuantype'] == 1) {
            $jiesuantypeDesc = '成交价';
        }elseif($sysset['fxjiesuantype'] == 2) {
            $jiesuantypeDesc = '销售利润';
        } else {
            $jiesuantypeDesc = '销售价';
        }

		if($info['showtj'] != '') $info['showtj'] = explode(',',$info['showtj']);//0 关注用户
		if($info['gettj'] != '') $info['gettj'] = explode(',',$info['gettj']);
        if($info['cid']) $info['cid'] = explode(',',$info['cid']);
        if($info['cid2']) $info['cid2'] = explode(',',$info['cid2']);
        if($info['commission_mid'])  $info['commission_mid'] = $info['commission_mid'] ? json_decode($info['commission_mid'],true) : [];
		if($info['bid'] == -1) $info['sort'] = $info['sort'] - 1000000;
        $default_cid = Db::name('member_level_category')->where('aid',$info['aid'])->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;

        $aglevellist = Db::name('member_level')->where('aid',$info['aid'])->where('cid', $default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
        $levellist = Db::name('member_level')->where('aid',$info['aid'])->where('cid', $default_cid)->order('sort,id')->select()->toArray();

		$gdlevellist = Db::name('member_level')->where('aid',$info['aid'])->where('cid', $default_cid)->where('fenhong','>','0')->order('sort,id')->select()->toArray();
		$teamlevellist = Db::name('member_level')->where('aid',$info['aid'])->where('cid', $default_cid)->where('teamfenhonglv','>','0')->order('sort,id')->select()->toArray();
		$areafhlevellist = Db::name('member_level')->where('aid',$info['aid'])->where('areafenhong','>','0')->select()->toArray();
        $gdlevellist_huiben = [];


		if($info['id']){
			$fuwulist = Db::name('shop_fuwu')->where('aid',$info['aid'])->where('bid',$info['bid'])->order('sort desc,id')->select()->toArray();
		}else{
			$fuwulist = Db::name('shop_fuwu')->where('aid',$info['aid'])->where('bid',$info['bid'])->order('sort desc,id')->select()->toArray();
		}


		//商品参数
		$parambid = $info['bid'];

		// dump($parambid);die;
        $whereParam = [];
        $whereParam[] = ['aid','=',$info['aid']];
        $whereParam[] = ['status','=',1];
        if($info['cid']){
            $whereCid = [];
            foreach($info['cid'] as $k => $c2){
                if($c2 == '') continue;
                $whereCid[] = "find_in_set({$c2},cid)";
            }
            if($whereCid){
				$whereParam[] = ['bid','=',$parambid];
				$whereParam[] = Db::raw(implode(' or ',$whereCid). " or cid =''");

            }else{
				$whereParam[] = ['bid','=',$parambid];
                $whereParam[] = Db::raw("cid =''");
			}
        }else{
			$whereParam[] = ['bid','=',$parambid];
            $whereParam[] = Db::raw(" cid =''");
        }
        // dump($whereParam);die;
		$paramList = Db::name('shop_param')->where($whereParam)->order('sort desc,id')->select()->toArray();
       // dump($paramList);die;
		View::assign('paramList',$paramList);
	


		$paramdata = $info['paramdata'] ? json_decode($info['paramdata'],true) : [];
//        dd($paramdata);
        $auth = [];
        $info['paramdata'] = str_ireplace("'", "\'", $info['paramdata']);

        $requiredField = [];

		View::assign('requiredField',$requiredField);
		View::assign('fuwulist',$fuwulist);
		View::assign('aglevellist',$aglevellist);
		View::assign('levellist',$levellist);
		View::assign('gdlevellist',$gdlevellist);
        View::assign('gdlevellist_huiben',$gdlevellist_huiben);
		View::assign('teamlevellist',$teamlevellist);
		View::assign('areafhlevellist',$areafhlevellist);
		View::assign('info',$info);
		View::assign('newgglist',$newgglist);
		View::assign('clist',$clist);
		View::assign('clist2',$clist2);
		View::assign('glist',$glist);
		View::assign('freightdata',$freightdata);
		View::assign('bset',$bset);
        View::assign('jiesuantypeDesc',$jiesuantypeDesc);
		View::assign('paramdata',$paramdata);
		$fromwxvideo = input('param.fromwxvideo')==1?true:false;
		
		if($fromwxvideo){
			$rs = curl_post('https://api.weixin.qq.com/shop/account/get_brand_list?access_token='.Wechat::access_token($info['aid'],'wx'),'{}');
			$rs = json_decode($rs,true);
			$brand_list = $rs['data'];
		}else{
			$brand_list = [];
		}
		$brand_list[] = ['brand_id'=>2100000000,'brand_wording'=>'无品牌'];
		View::assign('brand_list',$brand_list);
		View::assign('fromwxvideo',$fromwxvideo);

		return View::fetch();
	}
}
