<?php


// +----------------------------------------------------------------------
// | 菜品
// +----------------------------------------------------------------------
namespace app\controllers;

use app\models\RestaurantProductCategoryModel as Category;
use app\models\RestaurantProductModel;
use think\facade\Db;
use think\facade\View;

class RestaurantProduct extends Common
{
    public function initialize(){
        parent::initialize();
    }

    //菜品
    public function index()
    {
        if (request()->isAjax()) {
            $page = input('param.page');
            $limit = input('param.limit');

            if (input('param.field') && input('param.order')) {
                $order = input('param.field') . ' ' . input('param.order');
            } else {
                $order = 'sort desc,id desc';
            }
            $where[] = ['aid', '=', aid];
            if(bid==0){
                if(input('param.bid')){
                    $where[] = ['bid','=',input('param.bid')];
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
            }else{
                $where[] = ['bid','=',bid];
            }
            if (getcustom('restaurant_product_import'))   {
                $is_import = input('param.is_import');
                $aid = input('param.aid');
                $bid = input('param.bid','');
                if($is_import==1){//如果是导入的查询 重置where
                    $where = [];
                   if($aid ==''){
                       $where[] = ['aid','=',aid];
                   } else{
                       $where[] = ['aid','=',$aid];
                   }
                   if($bid !=''){
                       $where[] = ['bid','=',$bid];
                   }
                }
                
            }
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
            if(input('?get.cid') && input('param.cid')!=='') $where[] = Db::raw("find_in_set(".input('param.cid/d').",cid)");
            $data = (new RestaurantProductModel())->getList($where, $page, $limit, $order);
			$count = $data['count'];
			$list = $data['list'];
			foreach($list as $k=>$v){
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
				if(getcustom('restaurant_product_package')){
				    if($v['product_type'] ==2){
                        $list[$k]['sell_price'] = dd_money_format($v['package_price']);
                    }
                }
			}

            return json(['code' => 0, 'msg' => '查询成功', 'count' => $count, 'data' => $list]);
        }
        View::assign('rest_product_demo',0);
        if(getcustom('rest_product_demo')){
            View::assign('rest_product_demo',1);
        }

        //分类
        $clist = Db::name('restaurant_product_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray();
        View::assign('clist',$clist);
        if(getcustom('restaurant_product_showtj')){
            $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
            $default_cid = $default_cid ? $default_cid : 0;
            $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
            View::assign('levellist',$levellist); 
        }
        $area_list = Db::name('restaurant_area')->field('id,name')->where('aid',aid)->where('bid',bid)->select()->toArray();
        View::assign('area_list',$area_list);
        return View::fetch();
    }

    public function edit()
    {
        if(input('param.id')){
            $info = Db::name('restaurant_product')->where('aid',aid)->where('id',input('param.id/d'))->find();
            if(!$info) showmsg('商品不存在');
            if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
            if(bid != 0 && $info['linkid']!=0) showmsg('无权限操作');
        }
        //多规格
        $guige_list = array();
        if($info){
            $gglist = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$info['id'])->select()->toArray();
            foreach($gglist as $k=>$v){
                $v['lvprice_data'] = json_decode($v['lvprice_data'],true);
                if($v['ks']!==null && $v['ks'] !== ''){
                    $guige_list[$v['ks']] = $v;
                }else{
                    Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$v['id'])->update(['ks'=>$k]);
                    $guige_list[$k] = $v;
                }
            }
            $info['cid'] = explode(',', $info['cid']);
            $info['status_week'] = explode(',', $info['status_week']);
        }else{
			$info = [];
			$info['status_week'] = ['1','2','3','4','5','6','7'];
		}
//        dump($guige_list);                                  
//        dump($newgglist);
        //分类
        $clist = Db::name('restaurant_product_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray();
        $area_list = Db::name('restaurant_area')->field('id,name')->where('aid',aid)->where('bid',bid)->select()->toArray();
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
        $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        $sysset = Db::name('admin_set')->where('aid',aid)->find();
        if($sysset['fxjiesuantype'] == 1) {
            $jiesuantypeDesc = '成交价';
        }elseif($sysset['fxjiesuantype'] == 2) {
            $jiesuantypeDesc = '销售利润';
        } else {
            $jiesuantypeDesc = '销售价';
        }
		$bset = Db::name('business_sysset')->where('aid',aid)->find();
        
        //加料
        if(getcustom('product_jialiao')){
            if($info){
                $jialiaodata = $info['jialiaodata'];
            }  else{
                $jialiaodata = '';
            }
            View::assign('jialiaodata',$jialiaodata);
        }
        if (getcustom('restaurant_product_jialiao')){
            $jialiao = Db::name('restaurant_product_jialiao')->where('proid',$info['id'])->select()->toArray();
            View::assign('jialiao',$jialiao);
        }
        $business_selfscore = 0;
        if((getcustom('business_selfscore') || getcustom('business_score_jiesuan')) && bid > 0){
            $business_selfscore = $bset['business_selfscore'];
        }
        //商品类型
        $typelist = [];
        if(getcustom('restaurant_weigh')){
            $typelist[] = ['type'=>1,'name'=>'称重菜品'];
        }
        if(getcustom('restaurant_product_package')){
            $typelist[] = ['type'=>2,'name'=>'套餐'];
        }
        if(count($typelist)>0){
           array_unshift($typelist,['type'=>0,'name'=>'普通菜品']);
        }
        View::assign('aglevellist',$aglevellist);
        View::assign('levellist',$levellist);
        View::assign('info',$info);
        View::assign('guige_list',$guige_list);
        View::assign('clist',$clist);
        View::assign('area_list',$area_list);
		View::assign('bset',$bset);
		View::assign('typelist',$typelist);
		View::assign('business_selfscore',$business_selfscore);
        View::assign('jiesuantypeDesc',$jiesuantypeDesc);

        if(getcustom('business_cansetscore')){
            //商家能否修改积分
            $bcansetscore = false;
            if(bid){
                $business_cansetscore = Db::name('business')->where('id',bid)->value('business_cansetscore');
                $bcansetscore = $business_cansetscore && $business_cansetscore == 1?true:false;
            }
            View::assign('bcansetscore',$bcansetscore);
        }
        return View::fetch();
    }

    //保存
    public function save(){
        if(input('post.id')){
            $product = Db::name('restaurant_product')->where('aid',aid)->where('id',input('post.id/d'))->find();
            if(!$product) showmsg('商品不存在');
            if(bid != 0 && $product['bid']!=bid) showmsg('无权限操作');
        }

        $info = input('post.info/a');
        $info['detail'] = \app\commons\Common::geteditorcontent($info['detail']);
        if(!$info['pic']){
            if(bid > 0){
                $logo = Db::name('business')->where('aid',aid)->where('id',bid)->value('logo');
            }else{
                $logo = Db::name('admin_set')->where('aid',aid)->value('logo');
            }
            $info['pic'] = $logo;  
        }
        $data = array();
        $data['name'] = $info['name'];
        $data['pic'] = $info['pic'];
        $data['pics'] = $info['pics'];
        $data['sellpoint'] = $info['sellpoint'];
        $data['procode'] = $info['procode'];
        $data['cid'] = $info['cid'];
        $data['area_id'] = $info['area_id'];
        $data['freighttype'] = $info['freighttype'];
        $data['freightdata'] = $info['freightdata'];
        $data['commissionset'] = $info['commissionset'];
        $data['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
        $data['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
        $data['commissiondata3'] = jsonEncode(input('post.commissiondata3/a'));
		if(bid != 0){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			if($bset['commission_canset']==0){
				$data['commissionset'] = '-1';
			}
		}
            if(getcustom('restaurant_product_showtj')){
            if(isset($info['showtj'])) $data['showtj'] = implode(',',$info['showtj']);
        }
        $data['limit_per'] = $info['limit_per'];

        $business_selfscore = 0;
        if((getcustom('business_selfscore') || getcustom('business_score_jiesuan')) && bid > 0){
            $business_selfscore = Db::name('business_sysset')->where('aid',aid)->value('business_selfscore');
        }
        $bcansetscore = false;//商家能否修改积分
        if(getcustom('business_cansetscore')){
            //能否修改积分
            if(bid){
                $business_cansetscore = Db::name('business')->where('id',bid)->value('business_cansetscore');
                $bcansetscore = $business_cansetscore && $business_cansetscore == 1?true:false;
            }
        }
        if(!bid || $business_selfscore || $bcansetscore){
            $data['scored_set'] = $info['scored_set'];
            $data['scored_val'] = $info['scored_val'];
        }

        $data['limit_start'] = $info['limit_start'];
        $data['status_week'] = implode(',', $info['status_week']);
        if(isset($info['detail_text'])){
            $data['detail_text'] = $info['detail_text'];
        }
        if(isset($info['detail_pics'])){
            $data['detail_pics'] = $info['detail_pics'];
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
        if($info['gid']){
            $data['gid'] = implode(',',$info['gid']);
        }else{
            $data['gid'] = '';
        }

        $data['lvprice'] = $info['lvprice'];

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
        foreach(input('post.option/a') as $ks=>$v){
            if($sell_price==0 || $v[$sellprice_field] < $sell_price){
                $sell_price = $v[$sellprice_field];
                $market_price = $v['market_price'];
                $cost_price = $v['cost_price'];
                $givescore = $v['givescore'];
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
        $data['product_type'] = $info['product_type']??0;
        $data['market_price'] = $market_price;
        $data['cost_price'] = $cost_price;
        $data['sell_price'] = $sell_price;
        $data['pack_fee'] = $info['pack_fee'];
        $data['givescore'] = $givescore;
        $data['stock'] = 0;
        $data['stock_daily'] = 0;//每日库存
        foreach(input('post.option/a') as $v){
            $data['stock'] += $v['stock'];
            $data['stock_daily'] += $v['stock_daily'];
        }

        //多规格 规格项
        $data['guigedata'] = input('post.specs');
        
        //加料
        if(getcustom('product_jialiao')){
            $jldata =input('post.jldata'); 
            $jldata = json_decode($jldata,true);
            $jialiaodata = [];
            foreach($jldata as $key=>$val){
                 if(!empty($val['jltitle'])){
                     $jialiaodata[] = $val;
                 }
            }
            $data['jialiaodata'] =$jialiaodata?json_encode($jialiaodata,JSON_UNESCAPED_UNICODE):null;
        }
        if(getcustom('restaurant_duli_buy')){
            $data['duli_buy'] = $info['duli_buy'];
        }
        if(getcustom('restaurant_takeaway_limit')){
            $data['limit_takeaway'] = $info['limit_takeaway'];
        }
//        //[{"k":0,"title":"规格","items":[{"k":0,"title":"大码"},{"k":1,"title":"中码"},{"k":2,"title":"小"}]}]
//        $data['guigedata'] = [];
//        $dataGuige = [
//            "k" => 0,
//            "title" => '规格',
//            "items" => []
//        ];
        if(getcustom('one_buy_not_send')){
            $data['one_buy_status'] = $info['one_buy_status'];
        }
        if(getcustom('restaurant_weigh')){
            $data['product_type'] = $info['product_type'];
            $data['weigh_price'] = $info['weigh_price'];
            //判断是否开启先餐后付
            $pay_after = Db::name('restaurant_shop_sysset')->where('aid',aid)->where('bid',bid)->value('pay_after');
            if($pay_after==0 && $data['product_type'] ==1){
                return json(['status'=>0,'msg'=>'使用称重菜品请在设置中开启先餐后付功能']);
            }
        }
        if (getcustom('restaurant_product_jialiao')){
            //设置加料选项
            $data['jl_is_discount'] = $info['jl_is_discount'];
            $data['jl_is_selected'] = $info['jl_is_selected'];
            $data['jl_is_cuxiao'] = $info['jl_is_cuxiao'];
            $data['jl_total_limit'] = $info['jl_total_limit'];
        }
        if(getcustom('restaurant_product_package')){
            if($data['product_type'] ==2){
                $data['stock_daily'] = $data['stock'];//每日库存
                $data['package_is_discount'] = $info['package_is_discount'];
                $data['package_is_coupon'] = $info['package_is_coupon'];
                $data['package_is_cuxiao'] = $info['package_is_cuxiao'];
                $data['sell_price'] = $data['package_price'] = $info['package_price'];
                $productdata = input('param.product');
                $category_name = input('param.category_name');
                $package_type = input('param.package_type');
                $selectnum = input('param.selectnum');
                $packagedata = [];
                $i=0;
                foreach($category_name as $key=>$val){
                    $packagedata[$i]['category_name'] = $val;
                    $packagedata[$i]['type'] = $package_type[$key];
                    $packagedata[$i]['selectnum'] = $selectnum[$key]?$selectnum[$key]:0;
                    $n_proid = $productdata['proid'][$key];
                    $n_proname = $productdata['proname'][$key];
                    $n_ggname = $productdata['guigename'][$key];
                    $n_ggid = $productdata['ggid'][$key];
                    $n_sell_price = $productdata['sell_price'][$key];
                    $n_add_price = $productdata['add_price'][$key];
                    $n_add_num = $productdata['num'][$key];
                    $plist = [];
                    foreach($n_proid as $k=>$v){
                        $plist[$k]['proid'] = $v;
                        $plist[$k]['proname'] = $n_proname[$k];
                        $plist[$k]['ggid'] = $n_ggid[$k];
                        $plist[$k]['ggname'] = $n_ggname[$k];
                        $plist[$k]['sell_price'] = $n_sell_price[$k];
                        $plist[$k]['add_price'] = $n_add_price[$k];
                        $plist[$k]['num'] = $n_add_num[$k];
                    }
                    $packagedata[$i]['prolist'] = $plist;
                    $i++;
                }
                $data['packagedata'] = json_encode($packagedata,JSON_UNESCAPED_UNICODE);
            }
        }
        if($product){
            $table = new RestaurantProductModel();
            $table->where('aid',aid)->where('id',$product['id'])->save($data);
            $product_id = $product['id'];
            \app\commons\System::plog(sprintf('餐饮菜品编辑:%s[%s]',$data['name'],$product_id));
        }else{
            $data['aid'] = aid;
            $data['bid'] = bid;
            if(bid == 0 && $info['bid']){
                $data['bid'] = $info['bid'];
                if($info['bid'] == -1) $data['sort'] = 1000000 + intval($data['sort']);
            }
            $table = new RestaurantProductModel();
            $table->save($data);
            $product_id = $table->id;
            \app\commons\System::plog(sprintf('餐饮菜品添加:%s[%s]',$data['name'],$product_id));

            
			//菜品二维码 参数格式：id_1-cid_2
            //$path = 'pages/diancan/product';
            //$scene = ['id' => $product_id];
            //$qrcode = \app\commons\Wechat::getQRCode(aid,'wx',$path,$scene);
            //if ($qrcode['status'] == 1 && $qrcode['url']) {
            //    $table->save(['qrcode' => $qrcode['url']]);
            //}
        }
        //更新商户虚拟销量
        if($product){
            $bid = $product['bid'];
        }else{
            $bid = $info['bid']?:bid;
        }
        $sales = $info['sales']-$info['oldsales'];
        if($sales!=0){
            \app\models\Payorder::addSales(0,'sales',aid,$bid,$sales);
        }
        //dump(input('post.option/a'));die;
        //多规格
        $newggids = array();
        foreach(input('post.option/a') as $ks=>$v){
            if (empty(trim($v['name']))) {
                continue;
            }
            $ggdata = array();
            $ggdata['product_id'] = $product_id;
            $ggdata['ks'] = $ks;
            $ggdata['name'] = $v['name'];
            $ggdata['pic'] = $v['pic'] ? $v['pic'] : '';
            $ggdata['market_price'] = $v['market_price']>0 ? $v['market_price']:0;
            $ggdata['cost_price'] = $v['cost_price']>0 ? $v['cost_price']:0;
            $ggdata['sell_price'] = $v['sell_price']>0 ? $v['sell_price']:0;
            $ggdata['procode'] = $v['procode'];
            $ggdata['barcode'] = $v['barcode'];
            $ggdata['givescore'] = $v['givescore'];
            $ggdata['stock'] = $v['stock']>0 ? $v['stock']:0;
            $ggdata['stock_daily'] = $v['stock_daily']>0 ? $v['stock_daily']:0;
            $lvprice_data = [];
            //会员价格
            if($info['lvprice']==1){
                $ggdata['sell_price'] = $v['sell_price_'.$levellist[0]['id']]>0 ? $v['sell_price_'.$levellist[0]['id']]:0;
                foreach($levellist as $lv){
                    $sell_price = $v['sell_price_'.$lv['id']]>0 ? $v['sell_price_'.$lv['id']]:0;
                    $lvprice_data[$lv['id']] = $sell_price;
                }
                $ggdata['lvprice_data'] = json_encode($lvprice_data);
            }

            $guige = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$product_id)->where('ks',$ks)->find();
            if($guige){
                Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$guige['id'])->update($ggdata);
                $ggid = $guige['id'];
            }else{
                $ggdata['aid'] = aid;
                $ggid = Db::name('restaurant_product_guige')->insertGetId($ggdata);
            }

            $newggids[] = $ggid;
//            $dataGuige['items'][] = [
//                "k" => $ks,
//                "title" => $v['name']
//            ];
        }
        Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$product_id)->where('id','not in',$newggids)->delete();
//        $data['guigedata'] = json_encode([$dataGuige]);
        $table = new RestaurantProductModel();
        $table->where('aid',aid)->where('id',$product_id)->save($data);
        if(getcustom('restaurant_product_jialiao')){
            $jl = input('param.jl');
            $insertdata = [];
            $updateid = [];
            for($i=0;$i < count($jl['title']);$i++){
                if(!$jl['id'][$i]){
                    $insertdata[] = [
                        'aid' => aid,
                        'bid' => bid,
                        'proid' => $product_id,
                        'title' =>$jl['title'][$i],
                        'price' => $jl['price'][$i],
                        'limit_num' =>$jl['limit_num'][$i],
                        'createtime' => time()
                    ];

                }else{
                    $updateid[]= $jl['id'][$i];
                    $updata = [
                        'title' =>$jl['title'][$i],
                        'price' => $jl['price'][$i],
                        'limit_num' =>$jl['limit_num'][$i],
                    ];
                    Db::name('restaurant_product_jialiao')->where('id',$jl['id'][$i])->update($updata);
                }
            }
            //删除
            $oldid = Db::name('restaurant_product_jialiao')->where('proid',$product_id)->column('id');
            $diff_id = array_diff($oldid,$updateid);
            if($diff_id){
                Db::name('restaurant_product_jialiao')->where('id','in',$diff_id)->delete();
            }
            Db::name('restaurant_product_jialiao')->insertAll($insertdata);

        }
        //删除多余规格
        Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$product_id)->where('id','not in',$newggids)->delete();
        $this->tongbuproduct($product_id);
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);

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
            $where[] = ['linkid','=',0];
        }
        Db::name('restaurant_product')->where($where)->update(['status'=>$st]);
        $this->tongbuproduct($ids);
        \app\commons\System::plog('餐饮菜品改状态'.implode(',',$ids));
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
            $where[] = ['linkid','=',0];
        }
        $prolist = Db::name('restaurant_product')->where($where)->select();
        foreach($prolist as $pro) {
            $where2 = [];
            $where2[] = ['aid','=',aid];
            if(bid !=0){
                $where2[] = ['bid','=',bid];
            }
            if(bid>0 && $pro['linkid']>0){
                return json(['status'=>0,'msg'=>'默认菜品不能删除']);break;
            }
            Db::name('restaurant_product')->where($where2)->where('id', $pro['id'])->delete();
            Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id', $pro['id'])->delete();
            if(getcustom('restaurant_product_jialiao')){
                Db::name('restaurant_product_jialiao')->where('aid',aid)->where('proid', $pro['id'])->delete();
            }
            if(getcustom('rest_product_demo') && $pro['bid']==-1){
                $prolist2 = Db::name('restaurant_product')->where('linkid',$pro['id'])->select();
                foreach($prolist2 as $pro2){
                    Db::name('restaurant_product')->where('id',$pro2['id'])->delete();
                    Db::name('restaurant_product_guige')->where('product_id',$pro2['id'])->delete();
                }
            }
        }

        \app\commons\System::plog('餐饮菜品删除'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
    //复制商品
    public function procopy(){
        $product = Db::name('restaurant_product')->where('aid',aid)->where('id',input('post.id/d'))->find();
        if(!$product) return json(['status'=>0,'msg'=>'商品不存在,请重新选择']);
        if(bid != 0 && $product['bid']!=bid) showmsg('无权限操作');
        if(bid != 0 && $product['linkid']!=0) showmsg('无权限操作');
        $gglist = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$product['id'])->select()->toArray();
        $data = $product;
        $data['name'] = '复制-'.$data['name'];
        unset($data['id']);
        $data['status'] = 0;
        $newproid = Db::name('restaurant_product')->insertGetId($data);
        foreach($gglist as $gg){
            $ggdata = $gg;
            $ggdata['product_id'] = $newproid;
            unset($ggdata['id']);
            Db::name('restaurant_product_guige')->insert($ggdata);
        }
        if(getcustom('restaurant_product_jialiao')){
            $jialiao = Db::name('restaurant_product_jialiao')->where('aid',aid)->where('bid',$product['bid'])->where('proid',$product['id'])->select()->toArray();
            if($jialiao){
                foreach ($jialiao as $jl){
                    $jl_insert = $jl;
                    $jl_insert['proid'] = $newproid;
                    unset($jl_insert['id']) ;
                    Db::name('restaurant_product_jialiao')->insert($jl_insert);
                }
            }
        }
//        $this->tongbuproduct($newproid);
        \app\commons\System::plog('餐饮菜品复制'.$newproid);
        return json(['status'=>1,'msg'=>'复制成功','product_id'=>$newproid]);
    }
	//选择商品
	public function chooseproduct(){
		//分类
		$clist = Db::name('restaurant_product_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('restaurant_product_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);
		if(getcustom('restaurant_product_import')){
            View::assign('bid',input('param.bid'));
            View::assign('is_import',input('param.is_import',0));
            //所有平台列表
            $alist = Db::name('admin')->alias('admin')->field('admin.id,admin_user.un')->join('admin_user admin_user','admin.id=admin_user.aid and admin_user.bid=0 and admin_user.isadmin>0 and admin_user.bid=0')->where('admin.status',1)->order('id desc')->select()->toArray();
            View::assign('alist',$alist);
            $blist = Db::name('business')->where('aid',aid)->where('status',1)->field('id,name')->select()->toArray();
            View::assign('blist',$blist);
            //目标商家的菜品分类列表
            $bid = input('param.bid');
            $bclist = Db::name('restaurant_product_category')->Field('id,name')->where('aid',aid)->where('bid',$bid)->where('pid',0)->order('sort desc,id')->select()->toArray();
            View::assign('bclist',$bclist);
        }
		return View::fetch();
	}
	//获取商品信息
	public function getproduct(){
		$proid = input('post.proid/d');
		$product = Db::name('restaurant_product')->where('aid',aid)->where('id',$proid)->find();
		//多规格
		$newgglist = array();
		$gglist = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$product['id'])->select()->toArray();
		foreach($gglist as $k=>$v){
			$newgglist[$v['ks']] = $v;
		}
		$guigedata = json_decode($product['guigedata']);
		return json(['product'=>$product,'gglist'=>$newgglist,'guigedata'=>$guigedata]);
	}

    public function editCommon()
    {
        if(input('param.id')){
            $info = Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
        }
        //多规格
        $guige_list = array();
        if($info){
            $gglist = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$info['id'])->select()->toArray();
            foreach($gglist as $k=>$v){
                $v['lvprice_data'] = json_decode($v['lvprice_data'],true);
                if($v['ks']!==null && $v['ks'] !== ''){
                    $guige_list[$v['ks']] = $v;
                }else{
                    Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$v['id'])->update(['ks'=>$k]);
                    $guige_list[$k] = $v;
                }
            }
            $info['cid'] = explode(',', $info['cid']);
            $info['status_week'] = explode(',', $info['status_week']);
        }else{
            $info = [];
            $info['status_week'] = ['1','2','3','4','5','6','7'];
        }

//        dump($guige_list);
//        dump($newgglist);
        //分类
        $clist = Db::name('restaurant_product_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray();
        $area_list = Db::name('restaurant_area')->field('id,name')->where('aid',aid)->where('bid',bid)->select()->toArray();
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
        $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        $sysset = Db::name('admin_set')->where('aid',aid)->find();
        if($sysset['fxjiesuantype'] == 1) {
            $jiesuantypeDesc = '成交价';
        }elseif($sysset['fxjiesuantype'] == 2) {
            $jiesuantypeDesc = '销售利润';
        } else {
            $jiesuantypeDesc = '销售价';
        }

        $bset = Db::name('business_sysset')->where('aid',aid)->find();
        View::assign('aglevellist',$aglevellist);
        View::assign('levellist',$levellist);
        View::assign('info',$info);
        View::assign('guige_list',$guige_list);
        View::assign('clist',$clist);
        View::assign('area_list',$area_list);
        View::assign('bset',$bset);
        View::assign('jiesuantypeDesc',$jiesuantypeDesc);
        return View::fetch();
    }

    //保存
    public function saveCommon(){
        if(input('post.id'))
            $product = Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
        $info = input('post.info/a');
        $data = array();
        $data['cid'] = $info['cid'];
        $data['area_id'] = $info['area_id'];

//        $data['limit_per'] = $info['limit_per'];
//        $data['scored_set'] = $info['scored_set'];
//        $data['scored_val'] = $info['scored_val'];
//        $data['limit_start'] = $info['limit_start'];
//        $data['status_week'] = implode(',', $info['status_week']);
//        if(isset($info['detail_text'])){
//            $data['detail_text'] = $info['detail_text'];
//        }
//        if(isset($info['detail_pics'])){
//            $data['detail_pics'] = $info['detail_pics'];
//        }
//
//        if($info['oldsales'] != $info['sales']){
//            $data['sales'] = $info['sales'];
//        }
        $data['sort'] = $info['sort'];
//        $data['status'] = $info['status'];

//        $data['start_time'] = $info['start_time'];
//        $data['end_time'] = $info['end_time'];
//        $data['start_hours'] = $info['start_hours'];
//        $data['end_hours'] = $info['end_hours'];
//
//        $data['detail'] = $info['detail'];
//        if($info['gid']){
//            $data['gid'] = implode(',',$info['gid']);
//        }else{
//            $data['gid'] = '';
//        }

//        $data['lvprice'] = $info['lvprice'];

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
        foreach(input('post.option/a') as $ks=>$v){
            if($sell_price==0 || $v[$sellprice_field] < $sell_price){
                $sell_price = $v[$sellprice_field];
                $market_price = $v['market_price'];
                $cost_price = $v['cost_price'];
                $givescore = $v['givescore'];
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

//        $data['market_price'] = $market_price;
//        $data['cost_price'] = $cost_price;
        $data['sell_price'] = $sell_price;
//        $data['pack_fee'] = $info['pack_fee'];
//        $data['givescore'] = $givescore;
        $data['stock'] = 0;
        $data['stock_daily'] = 0;//每日库存
        foreach(input('post.option/a') as $v){
            $data['stock'] += $v['stock'];
            $data['stock_daily'] += $v['stock_daily'];
        }

        //多规格 规格项
        //[{"k":0,"title":"规格","items":[{"k":0,"title":"大码"},{"k":1,"title":"中码"},{"k":2,"title":"小"}]}]
        $data['guigedata'] = [];
        $dataGuige = [
            "k" => 0,
            "title" => '规格',
            "items" => []
        ];

        if($product){
            $table = new RestaurantProductModel();
            $table->where('aid',aid)->where('id',$product['id'])->save($data);
            $product_id = $product['id'];
            \app\commons\System::plog(sprintf('餐饮菜品编辑:%s[%s]',$data['name'],$product_id));
        }else{
            $data['aid'] = aid;
            $data['bid'] = bid;
            if(bid == 0 && $info['bid']){
                $data['bid'] = $info['bid'];
                if($info['bid'] == -1) $data['sort'] = 1000000 + intval($data['sort']);
            }
            $table = new RestaurantProductModel();
            $table->save($data);
            $product_id = $table->id;
            \app\commons\System::plog(sprintf('餐饮菜品添加:%s[%s]',$data['name'],$product_id));


            //菜品二维码 参数格式：id_1-cid_2
            //$path = 'pages/diancan/product';
            //$scene = ['id' => $product_id];
            //$qrcode = \app\commons\Wechat::getQRCode(aid,'wx',$path,$scene);
            //if ($qrcode['status'] == 1 && $qrcode['url']) {
            //    $table->save(['qrcode' => $qrcode['url']]);
            //}
        }
        //dump(input('post.option/a'));die;
        //多规格
        $newggids = array();
        foreach(input('post.option/a') as $ks=>$v){
            if (empty(trim($v['name']))) {
                continue;
            }
            $ggdata = array();
            $ggdata['product_id'] = $product_id;
//            $ggdata['ks'] = $ks;
//            $ggdata['name'] = $v['name'];
//            $ggdata['pic'] = $v['pic'] ? $v['pic'] : '';
//            $ggdata['market_price'] = $v['market_price']>0 ? $v['market_price']:0;
//            $ggdata['cost_price'] = $v['cost_price']>0 ? $v['cost_price']:0;
            $ggdata['sell_price'] = $v['sell_price']>0 ? $v['sell_price']:0;
//            $ggdata['procode'] = $v['procode'];
//            $ggdata['givescore'] = $v['givescore'];
            $ggdata['stock'] = $v['stock']>0 ? $v['stock']:0;
            $ggdata['stock_daily'] = $v['stock_daily']>0 ? $v['stock_daily']:0;
            $lvprice_data = [];
            //会员价格
            if($info['lvprice']==1){
                $ggdata['sell_price'] = $v['sell_price_'.$levellist[0]['id']]>0 ? $v['sell_price_'.$levellist[0]['id']]:0;
                foreach($levellist as $lv){
                    $sell_price = $v['sell_price_'.$lv['id']]>0 ? $v['sell_price_'.$lv['id']]:0;
                    $lvprice_data[$lv['id']] = $sell_price;
                }
                $ggdata['lvprice_data'] = json_encode($lvprice_data);
            }

            $guige = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$product_id)->where('ks',$ks)->find();
            if($guige){
                Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$guige['id'])->update($ggdata);
                $ggid = $guige['id'];
            }else{
                $ggdata['aid'] = aid;
                $ggid = Db::name('restaurant_product_guige')->insertGetId($ggdata);
            }

            $newggids[] = $ggid;
            $dataGuige['items'][] = [
                "k" => $ks,
                "title" => $v['name']
            ];
        }
        $data['guigedata'] = json_encode([$dataGuige]);
        $table = new RestaurantProductModel();
        $table->where('aid',aid)->where('id',$product_id)->save($data);

        //删除多余规格
        Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$product_id)->where('id','not in',$newggids)->delete();
        $this->tongbuproduct($product_id);
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);

    }

    public function setCommon()
    {
        if(!getcustom('rest_product_demo')){
            return;
        }
        $id = input('post.id/d');
        //先添加，后同步
        $product = Db::name('restaurant_product')->where('aid',aid)->where('id',$id)->find();
        if($product){
            unset($product['id']);
            $product['bid'] = -1;
            $product['create_time'] = time();
            $product['sort'] = 1000000 + intval($product['sort']);
            $newproid = Db::name('restaurant_product')->where('aid',aid)->insertGetId($product);
            $guige = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$id)->select()->toArray();
            if($guige){
                foreach ($guige as $item) {
                    unset($item['id']);
                    $item['product_id'] = $newproid;
                    Db::name('restaurant_product_guige')->insert($item);
                }
            }
            $this->tongbuproduct($newproid);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }

    //同步商品到商户
    private function tongbuproduct($proids){
        if(!getcustom('rest_product_demo')){
            return;
        }
        if(!is_array($proids)){
            $proids = explode(',',$proids);
        }
        $blist = [];
        foreach($proids as $proid){
            $product = Db::name('restaurant_product')->where('aid',aid)->where('id',$proid)->find();
            if($product && $product['bid'] == -1){
                $gglist = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$product['id'])->select()->toArray();
                if(!$blist){
                    $blist = Db::name('business')->where('aid',aid)->order('sort desc,id desc')->select()->toArray();
                }
                foreach($blist as $business){
                    $bpro = Db::name('restaurant_product')->where('aid',aid)->where('bid',$business['id'])->where('linkid',$product['id'])->find();
                    $data = $product;
                    $data['bid'] = $business['id'];
                    $data['linkid'] = $product['id'];
                    unset($data['id']); unset($data['cid']); unset($data['area_id']);
                    if($bpro){
                        Db::name('restaurant_product')->where('id',$bpro['id'])->update($data);
                        $newproid = $bpro['id'];
                    }else{
                        $newproid = Db::name('restaurant_product')->insertGetId($data);
                    }

                    $newggids = [];
                    foreach($gglist as $gg){
                        $ggdata = $gg;
                        $ggdata['product_id'] = $newproid;
                        unset($ggdata['id']);

                        $guige = Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$newproid)->where('ks',$ggdata['ks'])->find();
                        if($guige){
                            Db::name('restaurant_product_guige')->where('aid',aid)->where('id',$guige['id'])->update($ggdata);
                            $ggid = $guige['id'];
                        }else{
                            $ggid = Db::name('restaurant_product_guige')->insertGetId($ggdata);
                        }
                        $newggids[] = $ggid;
                    }
                    Db::name('restaurant_product_guige')->where('aid',aid)->where('product_id',$newproid)->where('id','not in',$newggids)->delete();
                }
            }
        }
    }
    //平台下店铺列表
    public function getBusinessListByAid(){
        if (getcustom('restaurant_product_import'))   {
            $aid = input('param.aid');
            $list = Db::name('business')->where('aid',$aid)->where('status',1)->field('id,name')->select()->toArray();
            $clist = Db::name('restaurant_product_category')->Field('id,name')->where('aid',$aid)->where('bid',0)->where('pid',0)->order('sort desc,id')->select()->toArray();
            return json(['status' => 1, 'msg' => '查询成功',  'list' => $list,'clist' => $clist]);
        }
    }
    //平台下店铺列表
    public function getBusinessCategoryList(){
        if (getcustom('restaurant_product_import'))   {
            $aid = input('param.aid');
            $bid = input('param.bid');
            $clist = Db::name('restaurant_product_category')->Field('id,name')->where('aid',$aid)->where('bid',$bid)->order('sort desc,id')->select()->toArray();
            return json(['status' => 1, 'msg' => '查询成功', 'clist' => $clist]);
        }
    }
    //导入菜品
    public function importRestaurant(){
        if (getcustom('restaurant_product_import'))   {
            $bid = input('param.bid',0);
            $id = input('param.id',0);
            if(!$bid){
                return json(['status' => 0, 'msg' => '请选择要导入的商家']);
            }
            if(!$id){
                return json(['status' => 0, 'msg' => '请选择要导入的菜品']);
            }
            $importcid = input('param.importcid');
            $business = Db::name('business')->where('id',$bid)->find();
            $productlist = Db::name('restaurant_product')->where('id','in',$id)->select()->toArray();
            foreach($productlist as $key=>$product){
               
                $p_insert  = [
                    'aid' => $business['aid'],
                    'bid' => $business['id'],
                    'name' => $product['name'],
                    'procode' => $product['procode'],
                    'sellpoint' => $product['sellpoint'],
                    'pic' => $product['pic'],
                    'cid' => $importcid,
                    'pics' => $product['pics'],
                    'qrcode' => $product['qrcode'],
                    'sales' => $product['sales'],
                    'detail' => $product['detail'],
                    'market_price' => $product['market_price'],
                    'sell_price' => $product['sell_price'],
                    'cost_price' => $product['cost_price'],
                    'pack_fee' => $product['pack_fee'],
                    'givescore' => $product['givescore'],
                    'sort' => $product['sort'],
                    'status' => 0,
                    'status_week' => $product['status_week'],
                    'stock' => $product['stock'],
                    'stock_daily' => $product['stock_daily'],
                    'create_time' => time(),
                    'update_time' => time(),
                
                    'guigedata' => $product['guigedata'],
                 
                    'limit_per' => $product['limit_per'],
                    'limit_start' => $product['limit_start'],
                    'scored_set' => $product['scored_set'],
                    'scored_val' => $product['scored_val'],
                    'start_hours' => $product['start_hours'],
                    'start_time' => $product['start_time'],
                    'end_time' => $product['end_time'],
                    'ischecked' => $product['ischecked'],
                ];
                //如果导入的商家和 选择的商品在同一个系统中，可使用会员价和分销设置
                if($business['aid'] ==$product['aid'] ){
                    $p_insert['commissionset'] =  $product['commissionset'];
                    $p_insert['commissiondata1'] =  $product['commissiondata1'];
                    $p_insert['commissiondata2'] =  $product['commissiondata2'];
                    $p_insert['commissiondata3'] =  $product['commissiondata3'];
                    $p_insert['lvprice'] =  $product['lvprice'];
                    $p_insert['lvprice_data'] =  $product['lvprice_data'];
                }
                $proid = Db::name('restaurant_product')->insertGetId($p_insert);
               if(!$proid){
                   continue;
               } 
                $guige = Db::name('restaurant_product_guige')->where('product_id',$product['id'])->select()->toArray();
                $g_insert = [];
                foreach($guige as $kk=>$gg){
                    $ggdata= [
                        'aid' => $business['aid'],
                        'product_id' => $proid,
                        'name' => $gg['name'],
                        'pic' =>$gg['pic'],
                        'market_price' => $gg['market_price'],
                        'cost_price' => $gg['cost_price'],
                        'sell_price' => $gg['sell_price'],
                        'stock' => $gg['stock'],
                        'stock_daily' => $gg['stock_daily'],
                        'procode' => $gg['procode'],
                        'sales_daily' => $gg['sales_daily'],
                        'ks' => $gg['ks'],
                        'givescore' => $gg['givescore'],
                        'barcode' => $gg['barcode'],
                    ];
                    if($business['aid'] ==$product['aid'] ){
                        $ggdata['lvprice_data'] =  $gg['lvprice_data'];
                    }
                    $g_insert[] = $ggdata; 
                }
                Db::name('restaurant_product_guige')->insertAll($g_insert);
                if(getcustom('restaurant_product_jialiao')){
                    $jialiao = Db::name('restaurant_product_jialiao')->where('proid',$product['id'])->select()->toArray();
                    $jl_insert = [];
                    foreach($jialiao as $kk=>$jl){
                        $jldata = [
                            'aid' => $business['aid'],
                            'bid' => $business['id'], 
                            'proid' => $proid,
                            'title' => $jl['title'],
                            'price' => $jl['price'],
                            'limit_num' => $jl['limit_num'],
                            'createtime' => time()
                        ];
                        $jl_insert[] = $jldata;
                    }
                    Db::name('restaurant_product_jialiao')->insertAll($jl_insert);
                }
            }
            return json(['status' => 1, 'msg' => '导入成功']);
        }
    }
    public function editManyPrintArea(){
        $area_id = input('param.area_id/s');
        $ids = input('param.ids');
        if(empty($ids)){
            return json(['status'=>0,'msg'=>'请选择需要编辑的菜品']);
        }
        if(empty($area_id)){
            return json(['status'=>0,'msg'=>'请选择出餐区域']);
        }
        Db::name('restaurant_product')->where('id','in',$ids)->update(['area_id' => $area_id]);
        return json(['status'=>1,'msg'=>'操作成功']);
    }
    //菜品导入
    public function importexcel()
    {
        if(getcustom('restaurant_product_import')){
            set_time_limit(0);
            ini_set('memory_limit',-1);
            $file = input('post.upload_file');
            $exceldata = $this->import_excel($file);
            $p_insert = [];
            foreach($exceldata as $data){
                if(!$data[0]){
                    continue;
                }
                $no = $data[0];//编号 一个产品一个编号
                $title =  $data[1];
                $pic =  $data[2];
                $pics =  $data[3];
                $procode =  $data[4];
                $cids =  $data[5];
                $sellpoint =  $data[6];
                $area_id  =$data[7];
                $jialiaodata  =$data[8];
                $jialiaototal =$data[9];
                $jl_selected =$data[10];
                $jl_discount =$data[11];
                $jl_cuxiao =$data[12];
                $score_dk =$data[13];//积分抵扣 需要截取scored_set|scored_val
                $one_buy_status =$data[14];
                $sales =$data[15];
                $limit_start =$data[16];
                $limit_per =$data[17];
                $status_week =$data[18];
                $showtj =$data[19];
                $pack_fee =$data[20];//打包费
                $sort =$data[21];
                $status =$data[22];//上架状态
                $status_value =$data[23];//上架周期、上架时间
                $detail =$info['detail'] = \app\commons\Common::geteditorcontent($data[24]);
                    
                $guige = $data[25];
                $g_name = $data[26];
                $g_barcode = $data[27];
                $g_market_price = $data[28];
                $g_cost_price = $data[29];
                $g_sell_price = $data[30];
                $g_stock_daily = $data[31];
                $g_stock = $data[32];
                $g_givescore = $data[33];
                $g_pic = $data[34];
               
                //标题
                if($title)$p_insert[$no]['name'] =$title;
                //主图
                if($pic)$p_insert[$no]['pic'] =$pic;
                //多图
                if($pics)$p_insert[$no]['pics'] =$pics;
                //产品编号
                if($procode)$p_insert[$no]['procode'] =$procode;
                //分类
                if($cids !='')$p_insert[$no]['cid'] =$cids;
                //卖点
                if($sellpoint)$p_insert[$no]['sellpoint'] =$sellpoint;
                //打印区域
                if($area_id)$p_insert[$no]['area_id'] =$area_id;
                //加料处理
                if($jialiaodata){
                    $jl_list = explode('|',$jialiaodata);
                    $jldata = [];
                    foreach($jl_list as $jk=>$jv){
                        $jl_field = explode('-',$jv);
                        $jl = [
                            'title' => $jl_field[0],
                            'price' => $jl_field[1],
                            'limit_num' => $jl_field[2]
                        ];
                        $jldata[] = $jl;
                    }
                    $p_insert[$no]['jldata'] =$jldata;
                }
                if($jialiaototal !='')$p_insert[$no]['jl_total_limit'] =$jialiaototal;
                if($jl_selected !='')$p_insert[$no]['jl_is_selected'] =$jl_selected;
                if($jl_discount !='')$p_insert[$no]['jl_is_discount'] =$jl_discount;
                if($jl_cuxiao !='')$p_insert[$no]['jl_is_cuxiao'] =$jl_cuxiao;
                //积分 需要截取 方式-值
                if($score_dk){
                    $score_dk_ex = explode('-',$score_dk);
                    $p_insert[$no]['scored_set'] = $score_dk_ex[0]?$score_dk_ex[0]:0;
                    $p_insert[$no]['scored_val'] =$score_dk_ex[1]?$score_dk_ex[1]:0; 
                }
                if($one_buy_status !='')$p_insert[$no]['one_buy_status'] =$one_buy_status;
                if($sales !='')$p_insert[$no]['sales'] =$sales;
                if($limit_start !='')$p_insert[$no]['limit_start'] =$limit_start;
                if($limit_per !='')$p_insert[$no]['limit_per'] =$limit_per;
                if($status_week )$p_insert[$no]['status_week'] =$status_week;
                if($showtj)$p_insert[$no]['showtj'] =$showtj;
                if($pack_fee !='')$p_insert[$no]['pack_fee'] =$pack_fee;
                if($sort !='')$p_insert[$no]['sort'] =$sort;
                if($status !='')$p_insert[$no]['status'] =$status;
                if($status_value){
                    $status_value_ex = explode('|',$status_value);
                    if($status ==2){
                        $p_insert[$no]['start_time'] =$status_value_ex[0];
                        $p_insert[$no]['end_time'] =$status_value_ex[1];
                    }
                    if($status ==3){
                        $p_insert[$no]['start_hours'] =$status_value_ex[0];
                        $p_insert[$no]['end_hours'] =$status_value_ex[1];
                    }
                }
                if($detail)$p_insert[$no]['detail'] =$detail;
                //规格分组 杯型-大杯,中杯,小杯|温度-热,冷   [{"k":0,"title":"规格","items":[{"k":0,"title":"默认规格"}]}]
                if($guige){
                    $specdata = explode('|',$guige);
                    $guigedata = [];
                    foreach($specdata as $sk => $spec){
                        //$spec =杯型-大杯,中杯,小杯
                        // $sp = ['杯型','大杯,中杯,小杯']
                        $sp = explode('-',$spec);
                        $guigedata[$sk]['k'] = $sk;
                        $guigedata[$sk]['title'] = $sp[0];
                        // $spitems = ['大杯','中杯','小杯']
                        $spitems =  explode(',',$sp[1]);
                     
                        $items = [];
                        foreach($spitems as $ik=>$item){
                            $items[$ik]['k'] = $ik;
                            $items[$ik]['title'] = $item;
                        }
                        $guigedata[$sk]['items'] = $items;
                    }
                    $p_insert[$no]['guigedata'] = $guigedata;
                }
                $returndata = $this->specArrCombination($p_insert[$no]['guigedata']);
                $ks = '';
                foreach($returndata as $rk=>$rv){
                    if($rv['tmp_sukarr'] == $g_name){
                        $ks = $rv['tmp_ks'];
                    }
                }
                $ggdata = [
                    'name' => $g_name,
                    'barcode' => $g_barcode,
                    'market_price' => $g_market_price,
                    'cost_price' => $g_cost_price,
                    'sell_price' => $g_sell_price,
                    'stock_daily' => $g_stock_daily,
                    'stock' => $g_stock,
                    'givescore' => $g_givescore,
                    'pic' => $g_pic,
                    'ks' => $ks,
                ];
                $p_insert[$no]['ggdata'][] = $ggdata;
            }
            $errorinsert = 0;
            $successinsert = 0;
            if($p_insert){
                foreach($p_insert as $key=>$val){
                    $sell_price = 0;
                    $market_price = 0;
                    $cost_price = 0;
                    $givescore=0;
                    foreach($val['ggdata'] as  $gk=>$gv){
                        if($sell_price==0 || $gv['sell_price'] < $sell_price){
                            $sell_price = $gv['sell_price'];
                            $market_price = $gv['market_price'];
                            $cost_price = $gv['cost_price'];
                            $givescore = $gv['givescore']; 
                        }    
                    }
                    $product = $val;
                    unset($product['jldata']);
                    unset($product['ggdata']);
                    $product['guigedata'] = json_encode($product['guigedata'],JSON_UNESCAPED_UNICODE);
                    $product['sell_price'] = $sell_price;
                    $product['market_price'] = $market_price;
                    $product['cost_price'] = $cost_price;
                    $product['givescore'] = $givescore;
                    $product['aid'] = aid;
                    $product['bid'] = bid;
                    $product['create_time'] = time();
                    $proid = Db::name('restaurant_product')->insertGetId($product);
                    if(!$proid){
                        $errorinsert +=count($val['ggdata']);
                        continue;
                    }
                    //添加规格
                    foreach($val['ggdata'] as $ggk=>$gg){
                        $gginsert = $gg;
                        $gginsert['aid'] = aid;
                        $gginsert['product_id'] = $proid;
                        $ggres = Db::name('restaurant_product_guige')->insert($gginsert);
                        if(!$ggres){
                            $errorinsert ++;
                            continue;
                        }
                        $successinsert ++;
                    }
                    //添加加料
                    foreach($val['jldata'] as $jlk=>$jl){
                        $jlinsert = $jl;
                        $jlinsert['aid'] = aid;
                        $jlinsert['bid'] = bid;
                        $jlinsert['proid'] = $proid;
                        $jlinsert['createtime'] = time();
                        Db::name('restaurant_product_jialiao')->insert($jlinsert);
                    }
                }
            }
            return json(['status'=>1,'msg'=>'成功导入'.$successinsert.'条数据,失败'.$errorinsert.'条数据']);
        }
    }
    //生成规格名和ks
   public function specArrCombination($arr,$i=0,$tmp='',$ks=''){
       if(getcustom('restaurant_product_import')) {
           $g_a = [];
           if (count($arr) - 1 <= $i) {
               $ar = [];
               foreach ($arr[$i]['items'] as $key => $val) {
                   $tmp_sukarr = '';
                   $tmp_ks = '';
                   if ($i == 0) {
                       $tmp_sukarr .= $val['title'];
                       $tmp_ks .= $val['k'];
                       $ar[$key]['tmp_sukarr'] = $tmp_sukarr;
                       $ar[$key]['tmp_ks'] = $tmp_ks;
                   } else {
                       $tmp_sukarr = $tmp;
                       $tmp_ks = $ks;
                       $tmp_sukarr .= ',' . $val['title'];
                       $tmp_ks .= ',' . $val['k'];
                       $ar[$key]['tmp_sukarr'] = $tmp_sukarr;
                       $ar[$key]['tmp_ks'] = $tmp_ks;
                   }
               }
               return $ar;
           } else {
               foreach ($arr[$i]['items'] as $key => $val) {
                   $tmp_sukarr = '';
                   $tmp_ks = '';
                   if ($tmp == '') {
                       $tmp_sukarr .= $val['title'];
                       $tmp_ks .= $val['k'];
                   } else {
                       $tmp_sukarr = $tmp;
                       $tmp_ks = $ks;
                       $tmp_sukarr .= ',' . $val['title'];
                       $tmp_ks .= ',' . $val['k'];
                   }
                   $tmpNode = $tmp_sukarr;
                   $tmpKs = $tmp_ks;
                   $g_a = array_merge($g_a, $this->specArrCombination($arr, $i + 1, $tmpNode, $tmpKs));
               }
           }
           return $g_a;
       }
    }
}