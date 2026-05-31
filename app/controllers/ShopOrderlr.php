<?php


// +----------------------------------------------------------------------
// | 录入订单
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class ShopOrderlr extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
    public function index(){
		$freightList = \app\models\Freight::getList([['status','=',1],['aid','=',aid],['bid','=',bid]]);
		View::assign('freightList',$freightList);
		$mendianArr = Db::name('mendian')->field('name,id')->where('aid',aid)->where('bid',bid)->order('id')->select();
		View::assign('mendianArr',$mendianArr);
		return View::fetch();
    }
	public function save(){
		$data = input('post.');
		$prodata = explode('-',$data['prodata']);
		$member = Db::name('member')->where('aid',aid)->where('id',$data['mid'])->find();
		if(!$member){
			$member = [];
			$data['mid'] = 0;
			//return json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
		}		
		$prolist = [];
		$mdid = 0;
		if($data['freight_id']){
			$freight_id = $data['freight_id'];
			$freight = Db::name('freight')->where('aid',aid)->where('id',$data['freight_id'])->find();
			$freight_price = $data['freightprice'];
			$pstype = $freight['pstype'];
			$freight_text = '';
			if($freight && ($freight['pstype']==0 || $freight['pstype']==10)){ //快递
				$freight_text = $freight['name'].'('.$freight_price.'元)';
				$pstype = $freight['pstype'];
			}elseif($freight && $freight['pstype']==1){ //到店自提
				$mdid = $data['storeid'];
				$mendian = Db::name('mendian')->where('aid',aid)->where('id',$mdid)->find();
				$freight_text = $freight['name'].'['.$mendian['name'].']';
				$pstype = 1;
            }elseif($freight && $freight['pstype']==5){ //门店配送
				$mdid = $data['storeid_ps'];
                $mendian = Db::name('mendian')->where('aid',aid)->where('id',$mdid)->find();
				$freight_text = $freight['name'].'['.$mendian['name'].']';
                $pstype = 5;
			}elseif($freight && $freight['pstype']==2){ //同城配送
                $freight_text = $freight['name'].'('.$freight_price.'元)';
				$pstype = 2;
			}elseif($freight && $freight['pstype']==12){ //app配送
				$freight_text = $freight['name'].'('.$freight_price.'元)';
				$pstype = 2;
			}elseif($freight && ($freight['pstype']==3 || $freight['pstype']==4)){ //自动发货 在线卡密
				$freight_text = $freight['name'];
				$pstype = $freight['pstype'];
			}elseif($freight && $freight['pstype']==11){ //选择物流配送
				$freight_text = $freight['name'].'('.$freight_price.'元)';
				$pstype = $freight['pstype'];
			}else{
				$freight_text = '包邮';
			}
		}else{
			$pstype = 0;
			$freight_text = '商家配送';
			$freight_id = 0;
		}
		//$pstype = 0;
		$givescore = 0; //奖励积分 确认收货后赠送
		$givescore2 = 0; //奖励积分2 付款后赠送
		foreach($prodata as $key=>$pro){
			$sdata = explode(',',$pro);
			$product = Db::name('shop_product')->where('aid',aid)->where('id',$sdata[0])->find();
			if(!$product) return json(['status'=>0,'msg'=>'产品不存在或已下架']);
			$guige = Db::name('shop_guige')->where('aid',aid)->where('id',$sdata[1])->find();
			if(!$guige) return json(['status'=>0,'msg'=>'产品规格不存在或已下架']);
			if($guige['stock'] < $sdata[2]){
				return json(['status'=>0,'msg'=>'库存不足']);
			}
            if($product['lvprice']==1 && $member) {
                $lvprice_data = json_decode($guige['lvprice_data'],true);
                if($lvprice_data)
                    $guige['sell_price'] = $lvprice_data[$member['levelid']];
            }
             if(getcustom('member_product_price')){
                //一客一价,存在用户时，存在设置的专享商品时
                $guige['is_member_product'] = 0;
                if($member){
                    $member_product = Db::name('member_product')->where('aid',aid)->where('mid',$member['id'])->where('proid',$sdata[0])->where('ggid',$sdata[1])->find();
                    if($member_product){
                        $guige['sell_price'] = $member_product['sell_price'];
                        $guige['givescore'] = $member_product['givescore'];
                        $guige['is_member_product'] = 1;
                    }
                }
            }
            if($key==0) $title = $product['name'];
			$prolist[] = ['product'=>$product,'guige'=>$guige,'num'=>$sdata[2]];

			if($product['freighttype']==3){
				$pstype = 3;
			}elseif($product['freighttype']==4){
				$pstype = 4;
			}
			if($product['givescore_time'] == 0){
				$givescore += $guige['givescore'] * $sdata[2];
			}else{
				$givescore2 += $guige['givescore'] * $sdata[2];
			}
		}
		if(($pstype == 3 || $pstype == 4) && count($prolist) > 1){
			return json(['status'=>0,'msg'=>($pstype==3 ? '自动发货' : '在线卡密').'商品需要单独录入']);
		}
		$sysset = Db::name('admin_set')->where('aid',aid)->find();

		$ordernum = \app\commons\Common::generateOrderNo(aid);
		$this->saveaddress($data);

		$orderdata = [];
		$orderdata['aid'] = aid;
		$orderdata['mid'] = $data['mid'];
		$orderdata['bid'] = bid;
		$orderdata['ordernum'] = $ordernum;
		$orderdata['title'] = $title.(count($prodata)>1?'等':'');
		
		$orderdata['linkman'] = $data['linkman'];
		$orderdata['tel'] = $data['tel'];
		$orderdata['area'] = $data['province'].$data['city'].$data['district'];;
		$orderdata['area2'] = $data['province']?$data['province'].','.$data['city'].','.$data['district']:'';
		$orderdata['address'] = $data['address'];
		$orderdata['totalprice'] = $data['totalprice'];
		$orderdata['product_price'] = $data['goodsprice'];
		$orderdata['leveldk_money'] = 0;  //会员折扣
		$orderdata['scoredk_money'] = 0;	//积分抵扣
		$orderdata['scoredkscore'] = 0;	//抵扣掉的积分
		$orderdata['freight_price'] = $data['freightprice']; //运费
		$orderdata['message'] = '';
		$orderdata['freight_text'] = $freight_text;
		$orderdata['freight_id'] = $freight_id;
		$orderdata['freight_type'] = $pstype;
		$orderdata['mdid'] = $mdid;
		$orderdata['platform'] = 'admin';
		$orderdata['hexiao_code'] = random(16);
		$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=shop&co='.$orderdata['hexiao_code']));
		$orderdata['status'] = 1;
		$orderdata['paytype'] = $data['paytype'];
		if($data['paytime']){
			$orderdata['createtime'] = strtotime($data['paytime']);
			$orderdata['paytime'] = $orderdata['createtime'];
		}else{
			$orderdata['createtime'] = time();
			$orderdata['paytime'] = time();
		}
		if(session('IS_ADMIN')==0){
			$user = Db::name('admin_user')->where('id',$this->uid)->find();
			$remark = '后台录入，操作员：'.$user['un'];
		}else{
			$remark = '后台录入';			
		}
		$orderdata['remark'] = $remark;
		$orderdata['givescore'] = $givescore;
		$orderdata['givescore2'] = $givescore2;
		$orderid = Db::name('shop_order')->insertGetId($orderdata);
		$istc = 0; //设置了按单固定提成时 只将佣金计算到第一个商品里
        $istc1 = 0;
        $istc2 = 0;
        $istc3 = 0;
		foreach($prolist as $key=>$v){
			$product = $v['product'];
			$guige = $v['guige'];
			$num = $v['num'];
			$ogdata = [];
			$ogdata['aid'] = aid;
			$ogdata['bid'] = $product['bid'];
			$ogdata['mid'] = $data['mid'];
			$ogdata['orderid'] = $orderid;
			$ogdata['ordernum'] = $orderdata['ordernum'];
			$ogdata['proid'] = $product['id'];
			$ogdata['name'] = $product['name'];
			$ogdata['pic'] = $guige['pic']?$guige['pic']:$product['pic'];
			$ogdata['procode'] = $product['procode'];
            $ogdata['barcode'] = $product['barcode'];
			$ogdata['ggid'] = $guige['id'];
			$ogdata['ggname'] = $guige['name'];
			$ogdata['cid'] = $product['cid'];
			$ogdata['num'] = $num;
			$ogdata['cost_price'] = $guige['cost_price'];
			$ogdata['sell_price'] = $guige['sell_price'];
			$ogdata['totalprice'] = $num * $guige['sell_price'];
			$ogdata['status'] = 1;
			$ogdata['createtime'] = time();
            if($product['fenhongset'] == 0){ //不参与分红
                $ogdata['isfenhong'] = 2;
            }
            
			$agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
			if($istc!=1){
				$og_totalprice = $ogdata['totalprice'];
				$leveldk_money = 0;
				$coupon_money = 0;
				$scoredk_money = 0;
				$manjian_money = $orderdata['product_price'] + $orderdata['freight_price'] - $orderdata['totalprice'];

				//计算商品实际金额  商品金额 - 会员折扣 - 积分抵扣 - 满减抵扣 - 优惠券抵扣
				if($sysset['fxjiesuantype'] == 1 || $sysset['fxjiesuantype'] == 2){
					$allproduct_price = $orderdata['product_price'];
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
				$ogdata['real_totalprice'] = $og_totalprice; //实际商品销售金额
				
				//计算佣金的商品金额
				$commission_totalprice = $ogdata['totalprice']; 
				if($sysset['fxjiesuantype'] == 1){
					$commission_totalprice = $ogdata['real_totalprice'];
				}
				if($sysset['fxjiesuantype']==2){ //按利润提成
					$commission_totalprice = $ogdata['real_totalprice'] - $guige['cost_price'] * $num;
				}
                if($commission_totalprice < 0) $commission_totalprice = 0;
                $commission_totalpriceCache = $commission_totalprice;

				$agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
				if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
					$member['pid'] = $member['id'];
				}
				if($product['commissionset']!=-1){
                    if(!getcustom('fenxiao_manage')){
                        $sysset['fenxiao_manage_status'] = 0;
                    }
                    if($sysset['fenxiao_manage_status']){
                        $commission_data = \app\commons\Fenxiao::fenxiao_jicha($sysset,$member,$product,$num,$commission_totalprice);
                    }else{
                        $commission_data = \app\commons\Fenxiao::fenxiao($sysset,$member,$product,$num,$commission_totalprice,0,$istc1,$istc2,$istc3);
                    }
                    $ogdata['parent1'] = $commission_data['parent1']??0;
                    $ogdata['parent2'] = $commission_data['parent2']??0;
                    $ogdata['parent3'] = $commission_data['parent3']??0;
                    $ogdata['parent4'] = $commission_data['parent4']??0;
                    $ogdata['parent1commission'] = $commission_data['parent1commission']??0;
                    $ogdata['parent2commission'] = $commission_data['parent2commission']??0;
                    $ogdata['parent3commission'] = $commission_data['parent3commission']??0;
                    $ogdata['parent4commission'] = $commission_data['parent4commission']??0;
                    $ogdata['parent1score'] = $commission_data['parent1score']??0;
                    $ogdata['parent2score'] = $commission_data['parent2score']??0;
                    $ogdata['parent3score'] = $commission_data['parent3score']??0;
                    $istc1 = $commission_data['istc1']??0;
                    $istc2 = $commission_data['istc2']??0;
                    $istc3 = $commission_data['istc3']??0;
				}
			}
			$ogid = Db::name('shop_order_goods')->insertGetId($ogdata);
			if(getcustom('member_product_price')){
                if($guige['is_member_product'] ==1){
                    $buylog = [
                        'aid' => aid,
                        'mid' => $member['id'],
                        'ordernum' => $orderdata['ordernum'],
                        'type' =>'admin',
                        'proid' => $ogdata['proid'],
                        'ggid' => $ogdata['ggid'],
                        'orderid' => $orderid,
                        'sell_price' => $ogdata['sell_price'],
                        'num' => $ogdata['num'],
                        'createtime' => time()
                    ];
                    Db::name('member_product_buylog')->insert($buylog);
                }
            }
            $totalcommission = 0;
			if($ogdata['parent1'] && ($ogdata['parent1commission'] > 0 || $ogdata['parent1score'] > 0)){
				Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent1'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogdata['parent1commission'],'score'=>$ogdata['parent1score'],'remark'=>'下级购买商品奖励','createtime'=>time()]);
                $totalcommission += $ogdata['parent1commission'];
			}
			if($ogdata['parent2'] && ($ogdata['parent2commission'] || $ogdata['parent2score'])){
				Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent2'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogdata['parent2commission'],'score'=>$ogdata['parent2score'],'remark'=>'下二级购买商品奖励','createtime'=>time()]);
                $totalcommission += $ogdata['parent2commission'];
			}
			if($ogdata['parent3'] && ($ogdata['parent3commission'] || $ogdata['parent3score'])){
				Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent3'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogdata['parent3commission'],'score'=>$ogdata['parent3score'],'remark'=>'下三级购买商品奖励','createtime'=>time()]);
                $totalcommission += $ogdata['parent3commission'];
			}
			
			if($product['commissionset4']==1 && $product['lvprice']==1){ //极差分销
                if($member['path']){
					$parentList = Db::name('member')->where('id','in',$member['path'])->order(Db::raw('field(id,'.$member['path'].')'))->select()->toArray();
					if($parentList){
						$parentList = array_reverse($parentList);
						$lvprice_data = json_decode($guige['lvprice_data'],true);
						$nowprice = $commission_totalpriceCache;
						$giveidx = 0;
						foreach($parentList as $k=>$parent){
							if($parent['levelid'] && $lvprice_data[$parent['levelid']]){
								$thisprice = floatval($lvprice_data[$parent['levelid']]) * $num;
								if($nowprice > $thisprice){
									$commission = $nowprice - $thisprice;
									$nowprice = $thisprice;
									$giveidx++;
									//if($giveidx <=3){
									//	$ogupdate['parent'.$giveidx] = $parent['id'];
									//	$ogupdate['parent'.$giveidx.'commission'] = $commission;
									//}
									Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$parent['id'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$commission,'score'=>0,'remark'=>'下级购买商品差价','createtime'=>time()]);

									//平级奖
									}
							}
						}
					}
				}
			}
			Db::name('shop_guige')->where('aid',aid)->where('id',$guige['id'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
			Db::name('shop_product')->where('aid',aid)->where('id',$product['id'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
				
		}
		if($orderdata['status'] == 1){
			\app\models\Payorder::shop_pay($orderid);
		}
        \app\commons\System::plog('商城订单录入'.$orderid);

		return json(['status'=>1,'msg'=>'录单成功','url'=>true]);
	}

	//未存在的地址新增保存
	public function saveaddress($post){
		$check = Db::name('member_address')->where('aid',aid)->where('mid',$post['mid'])->where(['name'=>$post['linkman'],'tel'=>$post['tel'],'province'=>$post['province'],'city'=>$post['city'],'district'=>$post['district']])->find();
		if(!$check && $post['mid']){
			$data = array();
			$data['aid'] = aid;
			$data['mid'] = $post['mid'];
			$data['name'] = $post['linkman'];
			$data['tel'] = $post['tel'];
			$data['address'] = $post['address'];
			$data['createtime'] = time();
			$data['province'] = $post['province'];
			$data['city'] = $post['city'];
			$data['district'] = $post['district'];
			$data['area'] = $data['province'].$data['city'].$data['district'];
			$addressid = Db::name('member_address')->insertGetId($data);
		}
		return true;
		
	}

	public function getUser()
    {
        $mid = input('param.mid');
        $info = Db::name('member')->where('aid', aid)->where('id', $mid)->field('nickname,headimg,levelid')->find();
        return json($info);
    }

	//选择地址
	public function choosearea(){
		if(request()->isAjax()){
			$page = input('param.page');
			$mid = input('param.mid');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['mid','=',$mid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			$count = 0 + Db::name('member_address')->where($where)->count();
			$data = Db::name('member_address')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			//dump($data);die;
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	public function getUserArea()
    {
        $addresid = input('param.addressid');
        $info = Db::name('member_address')->where('aid', aid)->where('id', $addresid)->field('name,tel,area,address,province,city,district')->find();
        return json($info);
    }
	public function shibie(){
		$addressxx = input('param.addressxx');
		$postdata = [];
		$postdata['text'] = $addressxx;
	//	$rs = request_post('https://www.diandashop.com/index/api/address',$postdata);
		$rs = json_decode($rs,true);
		return json($rs);
	}
}