<?php


namespace app\controllers;
use think\facade\Db;
class ApiLipin extends ApiCommon
{	
	public function initialize(){
		parent::initialize();
		$this->checklogin();
	}
	public function index(){
		
		$lipinset = Db::name('lipin_set')->where('aid',aid)->find();
		if(!$lipinset) $lipinset = ['status'=>1,'scanshow'=>0,'guize'=>''];
		if($lipinset['status'] == 0){
			return $this->json(['status'=>-4,'msg'=>'功能未开启']);
		}

		if(request()->isPost()){
			$dhcode = input('param.dhcode');
			$errnum = cache($this->sessionid.'_lipincode_errnum');
			$errnum = intval($errnum);
			if($errnum > 5) return $this->json(['status'=>0,'msg'=>'输入错误次数过多，请稍后再试']);
			if(getcustom('lipinka_no') && $lipinset['needno'] == 1){
				$cardno = input('param.cardno');
				$codeinfo = Db::name('lipin_codelist')->where('aid',aid)->where('cardno',$cardno)->where('code',$dhcode)->find();
			}else{
				$codeinfo = Db::name('lipin_codelist')->where('aid',aid)->where('code',$dhcode)->find();
			}
			if(!$codeinfo){
				$errnum++;
				cache($this->sessionid.'_lipincode_errnum',$errnum,600);
				return $this->json(['status'=>0,'msg'=>'兑换码输入错误']);
			}
			if($codeinfo['status'] == 1){
				return $this->json(['status'=>0,'msg'=>'兑换码已使用']);
			}
			if(getcustom('lipinka_jihuo2') && $codeinfo['jhstatus'] == 0){
				return $this->json(['status'=>0,'msg'=>'兑换码未激活']);
			}
			$hid = $codeinfo['hid'];
			$hdinfo = Db::name('lipin')->where('id',$hid)->where('aid',aid)->find();
			if(!$hdinfo || $hdinfo['status'] == 0) return $this->json(['status'=>0,'msg'=>'活动未开启']);
			if($hdinfo['starttime'] > time()) return $this->json(['status'=>0,'msg'=>'活动尚未开始']);
			if($hdinfo['endtime'] < time()) return $this->json(['status'=>0,'msg'=>'活动已结束']);

            if(getcustom('lipinka_bind_pid')){
                if($codeinfo['pid'] && in_array($hdinfo['type'],[0,2,3])){
                    $member = Db::name('member')->where('id',mid)->where('aid',aid)->find();
                    if(!$member['pid']){
                        //不存在上级，直接绑定
                        \app\models\Member::edit(aid,['id'=>mid,'pid'=>$codeinfo['pid']]);
                    }else{
                        $level = Db::name('member_level')->where('id',$member['levelid'])->where('aid',aid)->find();
                        if($level['isdefault'] == 1){
                            //强制绑定，仅限普通会员
                            \app\models\Member::edit(aid,['id'=>mid,'pid'=>$codeinfo['pid']]);
                        }
                    }
                }
            }

            if($hdinfo['type']==0){ //余额
				$money = $hdinfo['money'];
                Db::name('lipin_codelist')->where('id',$codeinfo['id'])->update(['status'=>1,'usetime'=>time(),'mid'=>mid,'headimg'=>$this->member['headimg'],'nickname'=>$this->member['nickname'],'remark'=>'兑换'.t('余额').$money.'元']);
                \app\commons\Member::addmoney(aid,mid,$money,$hdinfo['name']);
				return $this->json(['status'=>1,'msg'=>'成功兑换'.t('余额').$money.'元','url'=>true]);
			}elseif($hdinfo['type']==2){ //积分
				$score = $hdinfo['score'];
                Db::name('lipin_codelist')->where('id',$codeinfo['id'])->update(['status'=>1,'usetime'=>time(),'mid'=>mid,'headimg'=>$this->member['headimg'],'nickname'=>$this->member['nickname'],'remark'=>'兑换'.t('积分').$score.'个']);
                \app\commons\Member::addscore(aid,mid,$score,$hdinfo['name']);
				return $this->json(['status'=>1,'msg'=>'成功兑换'.t('积分').$score.'个','url'=>true]);
			}elseif($hdinfo['type']==3){ //优惠券
			    if($hdinfo['coupon_ids']) {
					$coupon_ids = explode(',', $hdinfo['coupon_ids']);
					if($coupon_ids) {
						foreach($coupon_ids as $coupon_id){
							\app\commons\Coupon::send(aid,mid,$coupon_id);
						}
						$tmpl_re = "成功兑换优惠券".count($coupon_ids).'张';
					}
				}
				Db::name('lipin_codelist')->where('id',$codeinfo['id'])->update(['status'=>1,'usetime'=>time(),'mid'=>mid,'headimg'=>$this->member['headimg'],'nickname'=>$this->member['nickname'],'remark'=>$tmpl_re]);
				return $this->json(['status'=>3,'msg'=>$tmpl_re,'url'=>'/pagesExt/coupon/mycoupon']);
			}elseif($hdinfo['type']==5){ //兑换会员等级
				if(getcustom('lipinka_memberlevel')){
					$level = Db::name('member_level')->field('id,name')->where('id',$hdinfo['memberlevel_id'])->where('aid',aid)->find();
					if($level){
						$tmpl_re = "成功兑换会员等级".$level['name'];
						$member = Db::name('member')->where('id',mid)->where('aid',aid)->find();
						if($level['id'] != $member['levelid']){
							//增加升级记录
							$order = [
								'aid' => aid,
								'mid' => $member['id'],
								'from_mid' => 0,
								'pid'=>$member['pid'],
								'levelid' => $level['id'] ,
								'title' => '礼品兑换会员等级',
								'totalprice' => 0,
								'createtime' => time(),
								'levelup_time' => time(),
								'beforelevelid' => $member['levelid'],
								'form0' => '类型^_^礼品兑换会员等级',
								'platform' => platform,
								'status' => 2
							];
							Db::name('member_levelup_order')->insert($order);
						}
						Db::name('member')->where('aid', aid)->where('id', mid)->update(['levelid' => $level['id'], 'levelendtime' => 0]);
						Db::name('lipin_codelist')->where('id',$codeinfo['id'])->update(['status'=>1,'usetime'=>time(),'mid'=>mid,'headimg'=>$this->member['headimg'],'nickname'=>$this->member['nickname'],'remark'=>$tmpl_re]);
						return $this->json(['status'=>1,'msg'=>$tmpl_re]);
					}
					
				}
			}elseif($hdinfo['type']==6){ //知识付费
				if(getcustom('lipinka_kecheng')){
					if($hdinfo['kecheng_ids']) {
						$kecheng_ids = explode(',', $hdinfo['kecheng_ids']);
						if($kecheng_ids) {
							$kc_name = '';
							foreach($kecheng_ids as $k_id){
								$kcorder = Db::name('kecheng_order')->where('aid',aid)->where('kcid',$k_id)->where('mid',mid)->find();
								if(!$kcorder){
									$ordernum = date('ymdHis').rand(100000,999999);
									$kc =  Db::name('kecheng_list')->where('id',$k_id)->find();
									$data = [];
									$data['aid'] = aid;
									$data['mid'] = mid;
									$data['bid'] = $kc['bid'];
									$data['pic'] = $kc['pic'];
									$data['createtime'] = time();
									$data['ordernum'] = $ordernum;
									$data['platform'] = platform;
									$data['title'] = $kc['name'];
									$data['kcid'] = $k_id;
									$data['totalprice'] = $kc['price'];
									$data['price'] = $kc['price'];
									$data['status'] = 1;
									$data['paytime'] = time();
									$orderid = Db::name('kecheng_order')->insertGetId($data);
									$kc_name .=$kc['name'].' ';
								}
								
							}
							$tmpl_re = "成功兑换课程 ".$kc_name;
						}
					}
					Db::name('lipin_codelist')->where('id',$codeinfo['id'])->update(['status'=>1,'usetime'=>time(),'mid'=>mid,'headimg'=>$this->member['headimg'],'nickname'=>$this->member['nickname'],'remark'=>$tmpl_re]);
					return $this->json(['status'=>6,'msg'=>$tmpl_re,'url'=>'/activity/kecheng/orderlist']);
				}
			}elseif($hdinfo['type']==7){ //活动报名
				if(getcustom('lipinka_huodong_baoming')){
					if($hdinfo['prodata7']) {
						$prodata = explode('-',$hdinfo['prodata7']);
						if($prodata) {
							$dh_name = '';
							foreach($prodata as $v){
								$thisv = explode(',',$v);
								$product = Db::name('huodong_baoming_product')->where('id',$thisv[0])->find();
								if($thisv[1]){
									$guige = Db::name('huodong_baoming_guige')->where('id',$thisv[1])->find();
								}else{
									$guige = ['id'=>'0','name'=>'免费','sell_price'=>0];
								}
								if($product){
									$ordernum = date('ymdHis').rand(100000,999999);
									$orderdata = [];
									$orderdata['aid'] = aid;
									$orderdata['mid'] = mid;
									$orderdata['bid'] = $product['bid'];
									$orderdata['ordernum'] = $ordernum;
									$orderdata['title'] = $product['name'];
									$orderdata['linkman'] = $this->member['realname']?$this->member['realname']:$this->member['nickname'];
									$orderdata['tel'] = $this->member['realname'];
									$orderdata['totalprice'] = $guige['sell_price'];
									$orderdata['status'] = 1;
									$orderdata['paytime'] = time();
									$orderdata['product_price'] = $guige['sell_price'];
									$orderdata['createtime'] = time();
									$orderdata['platform'] = platform;
									$orderdata['hexiao_code'] = random(16);
									$orderdata['remark'] = '礼品兑换';
									$orderdata['proname'] = $product['name'];
									$orderdata['protype'] = $product['protype'];
									$orderdata['ggname'] = $guige['name'];
									$orderdata['num'] = $thisv[2];
									$orderdata['propic'] = $guige['pic'] ? $guige['pic'] : $product['pic'];
									$orderdata['proid'] = $product['id'];
									$orderdata['ggid'] = $guige['id'];
									$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=huodong_baoming&co='.$orderdata['hexiao_code']));
									$orderid = Db::name('huodong_baoming_order')->insertGetId($orderdata);
									Db::name('huodong_baoming_product')->where('aid',aid)->where('id',$product['id'])->update(['sales'=>Db::raw("sales+1")]);
									$dh_name .=$product['name'].' ';
								}
								
							}
							$tmpl_re = "成功兑换活动 ".$dh_name;
						}
					}
					Db::name('lipin_codelist')->where('id',$codeinfo['id'])->update(['status'=>1,'usetime'=>time(),'mid'=>mid,'headimg'=>$this->member['headimg'],'nickname'=>$this->member['nickname'],'remark'=>$tmpl_re]);
					return $this->json(['status'=>6,'msg'=>$tmpl_re,'url'=>'/pagesB/huodongbaoming/orderlist']);
				}
			}else{ //商品
				return $this->json(['status'=>2,'type'=>$hdinfo['type'],'msg'=>'验证成功，正在前往兑换']);
			}
		}
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['lipinset'] = $lipinset;
		return $this->json($rdata);
	}
	
	public function dhlog(){
		$pagenum = input('post.pagenum');
        $st = input('post.st');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$where[] = ['status','=',1];
		$field = 'id,code,remark,from_unixtime(usetime)createtime';
		if(getcustom('lipinka_no')){
			$field.=',cardno';
		}
		$datalist = Db::name('lipin_codelist')->field($field)->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		
		$count = Db::name('lipin_codelist')->where($where)->count();

		$rdata = [];
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		$rdata['pernum'] = $pernum;
		$rdata['st'] = $st;
		$rdata['myscore'] = $this->member['score'];
		return $this->json($rdata);
	}
	//兑换商品
	public function prodh(){
		$lipinset = Db::name('lipin_set')->where('aid',aid)->find();
		if($lipinset['status'] == 0){
			return $this->json(['status'=>-4,'msg'=>'功能未开启']);
		}
		
		$dhcode = input('param.dhcode');
		$errnum = cache($this->sessionid.'_lipincode_errnum');
		$errnum = intval($errnum);
		if($errnum > 5) return $this->json(['status'=>0,'msg'=>'输入错误次数过多，请稍后再试']);

		if(getcustom('lipinka_no') && $lipinset['needno'] == 1){
			$cardno = input('param.cardno');
			$codeinfo = Db::name('lipin_codelist')->where('aid',aid)->where('cardno',$cardno)->where('code',$dhcode)->find();
		}else{
			$codeinfo = Db::name('lipin_codelist')->where('aid',aid)->where('code',$dhcode)->find();
		}
		if(!$codeinfo){
			$errnum++;
			cache($this->sessionid.'_lipincode_errnum',$errnum,600);
			return $this->json(['status'=>0,'msg'=>'兑换码输入错误']);
		}
		if($codeinfo['status'] == 1){
			return $this->json(['status'=>0,'msg'=>'兑换码已使用']);
		}
		if(getcustom('lipinka_jihuo2') && $codeinfo['jhstatus'] == 0){
			return $this->json(['status'=>0,'msg'=>'兑换码未激活']);
		}
		$hid = $codeinfo['hid'];
		$hdinfo = Db::name('lipin')->where('id',$hid)->where('aid',aid)->find();
		if(!$hdinfo || $hdinfo['status'] == 0) return $this->json(['status'=>0,'msg'=>'活动未开启']);
		if($hdinfo['starttime'] > time()) return $this->json(['status'=>0,'msg'=>'活动尚未开始']);
		if($hdinfo['endtime'] < time()) return $this->json(['status'=>0,'msg'=>'活动已结束']);
		if($hdinfo['type']!=1 && $hdinfo['type']!=4) return $this->json(['status'=>0,'msg'=>'兑换类型不是商品']);
		
		if($hdinfo['type']==4){
			$prodata = explode('-',$hdinfo['prodata4']);
			$hdinfo['num_type'] = $hdinfo['num_type4'];
		}else{
			$prodata = explode('-',$hdinfo['prodata']);
		}
		$address = Db::name('member_address')->where('aid',aid)->where('mid',mid)->order('isdefault desc,id desc')->find();
		if(!$address) $address = array();
		
		$userinfo = [];
		$userinfo['realname'] = $this->member['realname'];
		$userinfo['tel'] = $this->member['tel'];

		$allbuydata = [];
		$autofahuo = 0;
		foreach($prodata as $key=>$gwc){
			list($proid,$ggid,$num) = explode(',',$gwc);

			if($hdinfo['type']==4){
				$product = Db::name('scoreshop_product')->field("id,aid,bid,cid,pic,name,sales,stock,money_price sell_price,score_price,lvprice,lvprice_data,sellpoint,fuwupoint,freighttype,freightdata")->where('aid',aid)->where('status',1)->where('ischecked',1)->where('id',$proid)->find();
				
				if(!$product){
					Db::name('scoreshop_cart')->where('aid',aid)->where('proid',$proid)->delete();
					return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
				}
				if($product['freighttype'] == 3 || $product['freighttype'] == 4) $autofahuo = $product['freighttype'];
				if($ggid){
					$guige = Db::name('scoreshop_guige')->where('id',$ggid)->find();
					if(!$guige){
						Db::name('scoreshop_guige')->where('aid',aid)->where('ggid',$ggid)->delete();
						return $this->json(['status'=>0,'msg'=>'产品该规格不存在或已下架']);
					}
					$guige['sell_price'] = $guige['money_price'];
				}else{
					$guige = ['id'=>'0','name'=>'默认规格','pic'=>'','sell_price'=>$product['sell_price'],'score_price'=>$product['score_price'],'weight'=>$product['weight'],'stock'=>$product['stock'],'ks'=>'0'];
				}
				if($guige['stock'] < $num){
					return $this->json(['status'=>0,'msg'=>'库存不足!']);
				}
				$price = round($guige['sell_price'] * $num,2);
				$score = round($guige['score_price'] * $num);
			}else{
				$product = Db::name('shop_product')->field("id,aid,bid,cid,pic,name,sales,market_price,sell_price,lvprice,lvprice_data,sellpoint,fuwupoint,freighttype,freightdata,perlimit,gettj,gettjtip,gettjurl,scoredkmaxset,scoredkmaxval")->where('aid',aid)->where('status',1)->where('ischecked',1)->where('id',$proid)->find();
				
				if(!$product){
					Db::name('shop_cart')->where('aid',aid)->where('proid',$proid)->delete();
					return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
				}
				if($product['freighttype'] == 3 || $product['freighttype'] == 4) $autofahuo = $product['freighttype'];
				$guige = Db::name('shop_guige')->where('id',$ggid)->find();
				if(!$guige){
					Db::name('shop_cart')->where('aid',aid)->where('ggid',$ggid)->delete();
					return $this->json(['status'=>0,'msg'=>'产品该规格不存在或已下架']);
				}
				if($guige['stock'] < $num){
					return $this->json(['status'=>0,'msg'=>'库存不足']);
				}
				$gettj = explode(',',$product['gettj']);
				if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj) && (!in_array('0',$gettj) || $this->member['subscribe']!=1)){ //不是所有人
					if(platform == 'wx'){
						return $this->json(['status'=>0,'msg'=>$product['gettjtip'],'url'=>$product['gettjurl']]);
					}
				}
				$price = round($guige['sell_price'] * $num,2);
				$score = 0;
			}

			if(!$allbuydata[$product['bid']]) $allbuydata[$product['bid']] = [];
			if(!$allbuydata[$product['bid']]['prodata']) $allbuydata[$product['bid']]['prodata'] = [];
			$allbuydata[$product['bid']]['prodata'][] = ['product'=>$product,'guige'=>$guige,'num'=>$num,'price'=>$price,'score'=>$score];
		}
		if($autofahuo>0 && count($prodata) > 1){
			return $this->json(['status'=>0,'msg'=>'虚拟商品和实物商品不能一起兑换']);
		}
		
		$havetongcheng = 0;
		foreach($allbuydata as $bid=>$buydata){
			if($autofahuo>0){
				$freightList = [['id'=>0,'name'=>($autofahuo==3?'自动发货':'在线卡密'),'pstype'=>$autofahuo]];
			}else{
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
						return $this->json(['status'=>0,'msg'=>'所选择商品配送方式不同，请分别下单']);
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
			}
			$allbuydata[$bid]['freightList'] = $freightList;
		}
		if($havetongcheng){
			$address = Db::name('member_address')->where('aid',aid)->where('mid',mid)->where('latitude','>',0)->order('isdefault desc,id desc')->find();
		}else{
			$address = Db::name('member_address')->where('aid',aid)->where('mid',mid)->order('isdefault desc,id desc')->find();
		}
		if(!$address) $address = [];
		$needLocation = 0;
		$allproduct_price = 0;
		foreach($allbuydata as $bid=>$buydata){
			if($bid!=0){
				$business = Db::name('business')->where('id',$bid)->field('id,aid,cid,name,logo,tel,address,sales,longitude,latitude')->find();
			}else{
				$business = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,desc,tel')->find();
			}
			
			$product_price = 0;
			$score_price = 0;
			$totalweight = 0;
			$totalnum = 0;
			$prodataArr = [];
			foreach($buydata['prodata'] as $prodata){
				$product_price += $prodata['guige']['sell_price'] * $prodata['num'];
				$totalweight += $prodata['guige']['weight'] * $prodata['num'];
				$totalnum += $prodata['num'];
				$score_price += $prodata['score'];
				$prodataArr[] = $prodata['product']['id'].','.$prodata['guige']['id'].','.$prodata['num'];
			}
			$prodatastr = implode('-',$prodataArr);
			
			$rs = \app\models\Freight::formatFreightList($buydata['freightList'],$address,$product_price,$totalnum,$totalweight);

			$freightList = $rs['freightList'];
			$freightArr = $rs['freightArr'];
			if($rs['needLocation']==1) $needLocation = 1;
			
			
			$allbuydata[$bid]['bid'] = $bid;
			$allbuydata[$bid]['business'] = $business;
			$allbuydata[$bid]['prodatastr'] = $prodatastr;
//			$allbuydata[$bid]['couponList'] = $couponList;
//			$allbuydata[$bid]['couponCount'] = count($couponList);
			$allbuydata[$bid]['freightList'] = $freightList;
			$allbuydata[$bid]['freightArr'] = $freightArr;
			$allbuydata[$bid]['product_price'] = round($product_price,2);
			$allbuydata[$bid]['score_price'] = round($score_price);
//			$allbuydata[$bid]['leveldk_money'] = $leveldk_money;
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
//			$allbuydata[$bid]['cuxiaolist'] = $newcxlist;
//			$allbuydata[$bid]['cuxiaoCount'] = count($newcxlist);
			$allbuydata[$bid]['cuxiao_money'] = 0;
			$allbuydata[$bid]['cuxiaotype'] = 0;
			$allbuydata[$bid]['cuxiaoid'] = 0;
			$allbuydata[$bid]['editorFormdata'] = [];

			$allproduct_price += $product_price;
		}
		
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['havetongcheng'] = $havetongcheng;
		$rdata['address'] = $address;
		$rdata['linkman'] = $address ? $address['name'] : strval($userinfo['realname']);
		$rdata['tel'] = $address ? $address['tel'] : strval($userinfo['tel']);
		$rdata['userinfo'] = $userinfo;
		$rdata['allbuydata'] = $allbuydata;
		$rdata['needLocation'] = $needLocation;

		if(getcustom('lipinka_morefee')){
            $hdinfo['fee_items'] = $hdinfo['fee_items'] && !empty($hdinfo['fee_items']) ? json_decode($hdinfo['fee_items'],true) : '';
        }
        $rdata['hdinfo'] = $hdinfo;

		return $this->json($rdata);
	}

	public function createOrder(){

		$lipinset = Db::name('lipin_set')->where('aid',aid)->find();
		if(!$lipinset) $lipinset = ['status'=>1,'scanshow'=>0,'guize'=>''];
		if($lipinset['status'] == 0){
			return $this->json(['status'=>-4,'msg'=>'功能未开启']);
		}

		$dhcode = input('param.dhcode');
		$errnum = cache($this->sessionid.'_lipincode_errnum');
		$errnum = intval($errnum);
		if($errnum > 5) return $this->json(['status'=>0,'msg'=>'输入错误次数过多，请稍后再试']);
		if(getcustom('lipinka_no') && $lipinset['needno'] == 1){
			$cardno = input('param.cardno');
			$codeinfo = Db::name('lipin_codelist')->where('aid',aid)->where('cardno',$cardno)->where('code',$dhcode)->find();
		}else{
			$codeinfo = Db::name('lipin_codelist')->where('aid',aid)->where('code',$dhcode)->find();
		}
		if(!$codeinfo){
			$errnum++;
			cache($this->sessionid.'_lipincode_errnum',$errnum,600);
			return $this->json(['status'=>0,'msg'=>'兑换码输入错误']);
		}
		if($codeinfo['status'] == 1){
			return $this->json(['status'=>0,'msg'=>'兑换码已使用']);
		}
		if(getcustom('lipinka_jihuo2') && $codeinfo['jhstatus'] == 0){
			return $this->json(['status'=>0,'msg'=>'兑换码未激活']);
		}
		$hid = $codeinfo['hid'];
		$hdinfo = Db::name('lipin')->where('id',$hid)->where('aid',aid)->find();
		if(!$hdinfo || $hdinfo['status'] == 0) return $this->json(['status'=>0,'msg'=>'活动未开启']);
		if($hdinfo['starttime'] > time()) return $this->json(['status'=>0,'msg'=>'活动尚未开始']);
		if($hdinfo['endtime'] < time()) return $this->json(['status'=>0,'msg'=>'活动已结束']);
		if($hdinfo['type']!=1 && $hdinfo['type']!=4) return $this->json(['status'=>0,'msg'=>'兑换类型不是商品']);

		if($hdinfo['type']==4){
			$hdinfo['num_type'] = $hdinfo['num_type4'];
			$hdinfo['prodata'] = $hdinfo['prodata4'];
		}

		$post = input('post.');
		//收货地址
		if($post['addressid']=='' || $post['addressid']==0){
			$address = ['id'=>0,'name'=>$post['linkman'],'tel'=>$post['tel'],'area'=>'','address'=>''];
		}else{
			$address = Db::name('member_address')->where('id',$post['addressid'])->where('aid',aid)->where('mid',mid)->find();
		}

        $allbuydata = [];
        $postdata = input('post.buydata/a');
        $pro_kind=0;
        $goods_name = '';//兑换的商品信息
        $totalnum = 0;
        $totalweight = 0;//重量    
        $morefee = 0;
        foreach ($postdata as $pdata){
            $buydata = explode('-',$pdata['prodata']);
            foreach($buydata as $key=>$gwc){
                $pro_kind ++;
                list($proid,$ggid,$num) = explode(',',$gwc);
                $totalnum += $num;   
				if($hdinfo['type']==4){
					$product = Db::name('scoreshop_product')->field("id,aid,bid,cid,pic,name,sales,stock,money_price sell_price,score_price,lvprice,lvprice_data,sellpoint,fuwupoint,freighttype,freightdata")->where('aid',aid)->where('status',1)->where('ischecked',1)->where('id',$proid)->find();
					if(!$product){
						return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
					}
					if($ggid){
						$guige = Db::name('scoreshop_guige')->where('id',$ggid)->find();
						if(!$guige){
							Db::name('scoreshop_guige')->where('aid',aid)->where('ggid',$ggid)->delete();
							return $this->json(['status'=>0,'msg'=>'产品该规格不存在或已下架']);
						}
						$guige['sell_price'] = $guige['money_price'];
					}else{
						$guige = ['id'=>'0','name'=>'默认规格','pic'=>'','sell_price'=>$product['sell_price'],'score_price'=>$product['score_price'],'weight'=>$product['weight'],'stock'=>$product['stock'],'ks'=>'0'];
					}
					if($guige['stock'] < $num){
						return $this->json(['status'=>0,'msg'=>'库存不足!']);
					}
				}else{
				    $field = "id,aid,bid,cid,pic,name,sales,market_price,sell_price,lvprice,lvprice_data,freighttype,freightdata,perlimit,isjici";
				    if(getcustom('lipinka_commission')){
                        $field = '*';
                    }
					$product = Db::name('shop_product')->field($field)->where('aid',aid)->where('id',$proid)->find();
					if(!$product){
						return $this->json(['status'=>0,'msg'=>'产品不存在']);
					}
					$guige = Db::name('shop_guige')->where('id',$ggid)->find();
					if(!$guige){
						Db::name('shop_cart')->where('aid',aid)->where('ggid',$ggid)->delete();
						return $this->json(['status'=>0,'msg'=>'产品该规格不存在']);
					}
					if($guige['stock'] < $num){
						return $this->json(['status'=>0,'msg'=>'库存不足']);
					}
                    $totalweight += $guige['weight'] * $num;
				}
                if(!$allbuydata[$product['bid']]) $allbuydata[$product['bid']] = [];
                if(!$allbuydata[$product['bid']]['prodata']) $allbuydata[$product['bid']]['prodata'] = [];
                $allbuydata[$product['bid']]['prodata'][] = ['product'=>$product,'guige'=>$guige,'num'=>$num];

                if($goods_name){
                	//查询字数是否超出100字
	                $gn_len = mb_strlen($goods_name." | ".$product['name']);
	                if($gn_len<=100){
	                	$goods_name .= " | ".$product['name'];
	                }else{
	                	//查询是否有等字，没则添加
	                	$d_len = strrpos($goods_name,"等");
	                	if(!$d_len && $d_len !==0){
	                		$goods_name .= "等";
	                	}
	                }
                }else{
                	$goods_name .= $product['name'];
                }

            }
        }

        if(getcustom('lipin_num_type')){
            //N选1
            if($hdinfo['num_type'] == 1 && $pro_kind > 1){
                $this->json(['status'=>0,'msg'=>'只能兑换一种商品']);
            }
        }

        if(getcustom('lipinka_morefee')){
        	//附加费用
        
			//查询是否有附加费用
            $fee_items = $hdinfo['fee_items'] && !empty($hdinfo['fee_items']) ? json_decode($hdinfo['fee_items'],true) : '';
            if($fee_items){
            	foreach($fee_items as $fv){
            		$morefee += $fv['money'];
            	}
            	unset($fv);
            }
        }

		$ordernum = date('ymdHis').rand(100000,999999);

		$i = 0;
		foreach($allbuydata as $bid=>$allprodata){
			foreach($postdata as $v){
				if($v['bid'] == $bid){
					$data = $v;
				}
			}
			$i++;
			$product_price = 0;
			$weight = 0;//重量
			$goodsnum = 0;
			$prolist = [];
			$proids = [];
			$cids = [];
            $freight_price = 0;
			$freightList = Db::name('freight')->where('status',1)->where('aid',aid)->where('bid',$bid)->order('sort desc,id')->select()->toArray();
			if(!$freightList) $freightList = [['id'=>0,'name'=>'包邮','pstype'=>0,'pricedata'=>'[{"region":"全国(默认运费)","fristweight":"1000","fristprice":"0","secondweight":"1000","secondprice":"0"}]']];
			$fids = [];
			foreach($freightList as $v){
				$fids[] = $v['id'];
			}
			foreach($allprodata['prodata'] as $key=>$prodata){
				$product = $prodata['product'];
				if($key==0) $title = $product['name'];
				$guige = $prodata['guige'];
				$num = $prodata['num'];
				$product_price += $guige['sell_price'] * $num;
				$weight += $guige['weight'] * $num;
				$goodsnum += $num;

				$prolist[] = ['product'=>$product,'guige'=>$guige,'num'=>$num];
				
				if($product['freighttype']==0){
					$fids = array_intersect($fids,explode(',',$product['freightdata']));
				}else{
					$thisfreightList = Db::name('freight')->where('status',1)->where('aid',aid)->where('bid',$bid)->where('pstype','<>',3)->order('sort desc,id')->select()->toArray();
					if(!$thisfreightList){
						$thisfreightList = [
							['id'=>0,'name'=>'包邮','pstype'=>0,'pricedata'=>'[{"region":"全国(默认运费)","fristweight":"1000","fristprice":"0","secondweight":"1000","secondprice":"0"}]']
						];
					}

					$thisfids = [];
					foreach($thisfreightList as $v){
						$thisfids[] = $v['id'];
					}
					$fids = array_intersect($fids,$thisfids);
				}

				$proids[] = $product['id'];
				$cids[] = $product['cid'];
			}
			if(!$fids){
				if(count($prolist)>1){
					return $this->json(['status'=>0,'msg'=>'所选择商品配送方式不同，请联系商家']);
				}else{
					return $this->json(['status'=>0,'msg'=>'获取配送方式失败']);
				}
			}
			$totalprice = $product_price;

			if($data['freight_id']){
				$freight = Db::name('freight')->where('aid',aid)->where('id',$data['freight_id'])->find();
				
				if(($address['name']=='' || $address['tel'] =='') && ($freight['pstype']==1 || $freight['pstype']==3) && $freight['needlinkinfo']==1){
					return $this->json(['status'=>0,'msg'=>'请填写联系人和联系电话']);
				}
				$rs = \app\models\Freight::getFreightPrice($freight,$address,$product_price,$totalnum,$totalweight);
				if($rs['status']==0) return $this->json($rs);
                if(getcustom('lipinka_freight_free')){
                    if($hdinfo['freight_status'] ==1){
                        $freight_price = $rs['freight_price'];
                        $morefee +=  $rs['freight_price'];
                    }
                }
				//判断配送时间选择是否符合要求
				if($freight['pstimeset']==1){
					$freight_times = explode('~',$data['freight_time']);
					if($freight_times[1]){
						$freighttime = strtotime(explode(' ',$freight_times[0])[0] . ' '.$freight_times[1]);
					}else{
						$freighttime = strtotime($freight_times[0]);
					}
					if(time() + $freight['psprehour']*3600 > $freighttime){
						return $this->json(['status'=>0,'msg'=>(($freight['pstype']==0 || $freight['pstype']==2 || $freight['pstype']==10)?'配送':'提货').'时间必须在'.$freight['psprehour'].'小时之后']);
					}
				}
			}elseif($product['freighttype']==3){
				$freight = ['id'=>0,'name'=>'自动发货','pstype'=>3];
			}elseif($product['freighttype']==4){
				$freight = ['id'=>0,'name'=>'在线卡密','pstype'=>4];
			}else{
				$freight = ['id'=>0,'name'=>'包邮','pstype'=>0];
			}
			//积分抵扣
			$dscore = 0;
			$scoredk = 0;
			
			$orderdata = [];
			$orderdata['aid'] = aid;
			$orderdata['mid'] = mid;
			$orderdata['bid'] = $data['bid'];
			if(count($allbuydata) > 1){
				$orderdata['ordernum'] = $ordernum.'_'.$i;
			}else{
				$orderdata['ordernum'] = $ordernum;
			}
			$orderdata['title'] = $title.(count($prodata)>1?'等':'');
			
			$orderdata['linkman'] = $address['name'];
			$orderdata['tel'] = $address['tel'];
			$orderdata['area'] = $address['area'];
			$orderdata['area2'] = $address['province'].','.$address['city'].','.$address['district'];
			$orderdata['address'] = $address['address'];
            $orderdata['totalprice'] =  $freight_price?$freight_price:0;
			if($hdinfo['type']==1){
				$orderdata['product_price'] = $product_price;
			}
			$orderdata['message'] = $data['message'];
			if($freight && $freight['pstype']==0){ //快递
				$orderdata['freight_text'] = $freight['name'].'('.$freight_price.'元)';
			}elseif($freight && $freight['pstype']==1){ //到店自提
				$orderdata['mdid'] = $data['storeid'];
				if($data['bid']!=0){
					$storename = Db::name('business')->where('aid',aid)->where('id',$data['bid'])->value('name');
				}else{
					$storename = Db::name('mendian')->where('aid',aid)->where('id',$data['storeid'])->value('name');
				}
				$orderdata['freight_text'] = $freight['name'].'['.$storename.']';
				$orderdata['freight_type'] = 1;
				if(getcustom('freight_selecthxbids') && $freight['bid']==0){
					$orderdata['freight_text'] = $freight['name'];
					$orderdata['area2'] = '';
					$orderdata['mdid'] = '-1';
					if(!$orderdata['longitude']){
						$orderdata['longitude'] = $post['longitude'];
						$orderdata['latitude'] = $post['latitude'];
					}
				}
			}elseif($freight && $freight['pstype']==2){ //同城配送
				$orderdata['freight_text'] = $freight['name'].'('.$freight_price.'元)';
				$orderdata['freight_type'] = 2;
			}elseif($freight && ($freight['pstype']==3 || $freight['pstype']==4)){ //自动发货 在线卡密
				$orderdata['freight_text'] = $freight['name'];
				$orderdata['freight_type'] = $freight['pstype'];
			}else{
				$orderdata['freight_text'] = '包邮';
			}
            $orderdata['freight_price'] = $freight_price; //运费 
			$orderdata['freight_id'] = $freight['id'];
			$orderdata['freight_time'] = $data['freight_time']; //配送时间
			$orderdata['createtime'] = time();
			$orderdata['platform'] = platform;
			$orderdata['hexiao_code'] = random(16);
			$orderstatus = 1;
            if(getcustom('lipinka_freight_free')){
                if($freight_price > 0){
                    $orderstatus = 0;
                }
            }
			$orderdata['status'] = $orderstatus;
			$orderdata['paytype'] = '兑换码兑换';
			$orderdata['paytime'] = time();
			if(getcustom('lipinka_morefee')){
	        	//附加费用
	        	if($morefee>0){
	        		$orderdata['status'] = 0;
	        		$orderdata['paytime'] = 0;
	        	};
	        }

			if(getcustom('lipinka_no') && $lipinset['needno'] == 1){
				$orderdata['duihuan_cardno'] = input('param.cardno');
			}
			if($hdinfo['type']==4){
				$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=scoreshop&co='.$orderdata['hexiao_code']));
				$orderid = Db::name('scoreshop_order')->insertGetId($orderdata);
				\app\models\Freight::saveformdata($orderid,'scoreshop_order',$freight['id'],$data['formdata']);
			}else{
				$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=shop&co='.$orderdata['hexiao_code']));
				if(getcustom('lipinka_commission')){
                    $givescore = 0;
                    foreach($prolist as $key=>$v){
                        $givescore +=  $v['guige']['givescore']* $v['num'];
                    }
				    $orderdata['givescore'] = $givescore;
                }
				$orderid = Db::name('shop_order')->insertGetId($orderdata);
				\app\models\Freight::saveformdata($orderid,'shop_order',$freight['id'],$data['formdata']);
			}
			
			
            if(getcustom('lipinka_bind_pid')){
                if($codeinfo['pid']){
                    $member = Db::name('member')->where('id',mid)->where('aid',aid)->find();
                    if(!$member['pid']){
                        //不存在上级，直接绑定
                        \app\models\Member::edit(aid,['id'=>mid,'pid'=>$codeinfo['pid']]);
                    }else{
                        $level = Db::name('member_level')->where('id',$member['levelid'])->where('aid',aid)->find();
                        if($level['isdefault'] == 1){
                            //强制绑定，仅限普通会员
                            \app\models\Member::edit(aid,['id'=>mid,'pid'=>$codeinfo['pid']]);
                        }
                    }
                }
            }
            if(getcustom('lipinka_commission')) {
                //是否是复购
                $hasordergoods = Db::name('shop_order_goods')->where('aid', aid)->where('mid', mid)->where('status', 'in', '1,2,3')->find();
                if ($hasordergoods) {
                    $isfg = 1;
                } else {
                    $isfg = 0;
                }
                $istc1 = 0; //设置了按单固定提成时 只将佣金计算到第一个商品里
                $istc2 = 0;
                $istc3 = 0;
            }
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
				$ogdata['pic'] = $product['pic'];
				$ogdata['procode'] = $product['procode'];
				$ogdata['ggid'] = $guige['id'];
				$ogdata['ggname'] = $guige['name'];
				if($hdinfo['type']==1){
					$ogdata['cid'] = $product['cid'];
				}
				$ogdata['num'] = $num;
				$ogdata['cost_price'] = $guige['cost_price'];
				$ogdata['sell_price'] = $guige['sell_price'];
				if($hdinfo['type']==4){
					$ogdata['totalscore'] = $num * $guige['score_price'];
					$ogdata['totalmoney'] = $num * $guige['sell_price'];
					$ogdata['money_price'] = $guige['sell_price'];
					$ogdata['score_price'] = $guige['score_price'];
				}else{
				    $real_totalprice =  $num * $guige['sell_price'];
					$ogdata['totalprice'] = $real_totalprice + $freight_price;
				}
				$ogstatus = 1;
				if(getcustom('lipinka_freight_free')){
				     if($freight_price > 0){
                         $ogstatus = 0;
                     }
                }
				$ogdata['status'] = $ogstatus;
				$ogdata['createtime'] = time();

				if($product['isjici'] == 1){
					$ogdata['hexiao_code'] = random(18);
					$ogdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=shopproduct&co='.$ogdata['hexiao_code']));
				}
				
				if($hdinfo['type']==4){
					$ogid = Db::name('scoreshop_order_goods')->insertGetId($ogdata);
					if($guige['id']){
						Db::name('scoreshop_guige')->where('aid',aid)->where('id',$guige['id'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
					}
					Db::name('scoreshop_product')->where('aid',aid)->where('id',$product['id'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
				}else{
					$ogid = Db::name('shop_order_goods')->insertGetId($ogdata);
                    if(getcustom('lipinka_commission')){
                        $sysset = Db::name('admin_set')->where('aid',aid)->find();
                        //计算佣金的商品金额
                        $ogdata['real_totalprice'] =  $ogdata['totalprice'];  
                        
                        $commission_totalprice =  $ogdata['real_totalprice'];
                        if($sysset['fxjiesuantype']==1){ //按成交价格
                            $commission_totalprice = $ogdata['real_totalprice'];
                        }
                        if($sysset['fxjiesuantype']==2){ //按销售利润
                            $commission_totalprice = $ogdata['real_totalprice'] - $guige['cost_price'] * $num;
                        }
                        
                        if($commission_totalprice < 0) $commission_totalprice = 0;
                        $agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
                        if($product['commissionset']!=-1 && $hdinfo['commission_status']){
                            if(!getcustom('fenxiao_manage')){
                                $sysset['fenxiao_manage_status'] = 0;
                            }
                            if($sysset['fenxiao_manage_status']){
                                $commission_data = \app\commons\Fenxiao::fenxiao_jicha($sysset,$this->member,$product,$num,$commission_totalprice);
                            }else{
                                $commission_data = \app\commons\Fenxiao::fenxiao($sysset,$this->member,$product,$num,$commission_totalprice,$isfg,$istc1,$istc2,$istc3);
                            }
                           
                            if(getcustom('member_level_parent_not_commission1')){
                                //定制，购买人上级无任何分销奖励
                                if($product['parent_not_commission_json']){
                                    $parent_not_commission = json_decode($product['parent_not_commission_json'],true);
                                    if(isset($parent_not_commission[$agleveldata['id']])){
                                        if($parent_not_commission[$agleveldata['id']] == -1){//跟随会员等级
                                            if($agleveldata['parent_not_commission'] == 1) {
                                                $commission_data = [];
                                            }
                                        }elseif($parent_not_commission[$agleveldata['id']] == 1){//开启
                                            $commission_data = [];
                                        }
                                    }else{
                                        //商品未设置，使用会员等级设置
                                        if($agleveldata['parent_not_commission'] == 1) {
                                            $commission_data = [];
                                        }
                                    }
                                }else{
                                    //商品未设置，使用会员等级设置
                                    if($agleveldata['parent_not_commission'] == 1) {
                                        $commission_data = [];
                                    }
                                }
                            }
                            $ogupdate['parent1'] = $commission_data['parent1']??0;
                            $ogupdate['parent2'] = $commission_data['parent2']??0;
                            $ogupdate['parent3'] = $commission_data['parent3']??0;
                            $ogupdate['parent4'] = $commission_data['parent4']??0;
                            $ogupdate['parent1commission'] = $commission_data['parent1commission']??0;
                            $ogupdate['parent2commission'] = $commission_data['parent2commission']??0;
                            $ogupdate['parent3commission'] = $commission_data['parent3commission']??0;
                            $ogupdate['parent4commission'] = $commission_data['parent4commission']??0;
                            $ogupdate['parent1score'] = $commission_data['parent1score']??0;
                            $ogupdate['parent2score'] = $commission_data['parent2score']??0;
                            $ogupdate['parent3score'] = $commission_data['parent3score']??0;
                            $istc1 = $commission_data['istc1']??0;
                            $istc2 = $commission_data['istc2']??0;
                            $istc3 = $commission_data['istc3']??0;
                            if(getcustom('commission_butie')){
                                $butie_data = [];
                                $butie_data['parent1commission_butie'] = $commission_data['parent1commission_butie']??0;
                                $butie_data['parent2commission_butie'] = $commission_data['parent2commission_butie']??0;
                                $butie_data['parent3commission_butie'] = $commission_data['parent3commission_butie']??0;
                            }
                           
                        }
                      
                        if($ogupdate){
                            Db::name('shop_order_goods')->where('id',$ogid)->update($ogupdate);
                        }
                        $totalcommission = 0;
                     
                        if($product['commissionset']!=4){
                            $ordermid =  $hdinfo['mid'];
                            if(getcustom('plug_ttdz') && $isfg == 1){
                                if($ogupdate['parent1'] && ($ogupdate['parent1commission'] || $ogupdate['parent1score'])){
                                    Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent1'],'frommid'=>$ordermid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent1commission'],'score'=>$ogupdate['parent1score'],'remark'=>t('下级').'复购奖励','createtime'=>time()]);
                                    $totalcommission += $ogupdate['parent1commission'];
                                }
                                if($ogupdate['parent2'] && ($ogupdate['parent2commission'] || $ogupdate['parent2score'])){
                                    Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent2'],'frommid'=>$ordermid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent2commission'],'score'=>$ogupdate['parent2score'],'remark'=>t('下二级').'复购奖励','createtime'=>time()]);
                                    $totalcommission += $ogupdate['parent2commission'];
                                }
                                if($ogupdate['parent3'] && ($ogupdate['parent3commission'] || $ogupdate['parent3score'])){
                                    Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent3'],'frommid'=>$ordermid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent3commission'],'score'=>$ogupdate['parent3score'],'remark'=>t('下三级').'复购奖励','createtime'=>time()]);
                                    $totalcommission += $ogupdate['parent3commission'];
                                }
                            }else{
                                if($ogupdate['parent1'] && ($ogupdate['parent1commission']>0 || $ogupdate['parent1score']>0)){
                                    $data_c = ['aid'=>aid,'mid'=>$ogupdate['parent1'],'frommid'=>$ordermid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent1commission'],'score'=>$ogupdate['parent1score'],'remark'=>t('下级').'购买商品奖励','createtime'=>time()];
                                    if(getcustom('commission_butie')){
                                        $data_c['butie'] = $butie_data['parent1commission_butie'];
                                        $data_c['commission'] =  bcsub($ogupdate['parent1commission'],$butie_data['parent1commission_butie'],2);
                                    }
                                    if(getcustom('commission_max_times')){
                                        //分销份数限制
                                        $data_c['proid'] = $product['id'];
                                        $data_c['level'] = 1;
                                    }
                                    Db::name('member_commission_record')->insert($data_c);
                                    $totalcommission += $ogupdate['parent1commission'];
                                }
                                if($ogupdate['parent2'] && ($ogupdate['parent2commission']>0 || $ogupdate['parent2score']>0)){
                                    $data_c = ['aid'=>aid,'mid'=>$ogupdate['parent2'],'frommid'=>$ordermid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent2commission'],'score'=>$ogupdate['parent2score'],'remark'=>t('下二级').'购买商品奖励','createtime'=>time()];
                                    if(getcustom('commission_butie')){
                                        $data_c['butie'] = $butie_data['parent2commission_butie'];
                                        $data_c['commission'] =  bcsub($ogupdate['parent2commission'],$butie_data['parent2commission_butie'],2);
                                    }
                                    if(getcustom('commission_max_times')){
                                        //分销份数限制
                                        $data_c['proid'] = $product['id'];
                                        $data_c['level'] = 2;
                                    }
                                    Db::name('member_commission_record')->insert($data_c);
                                    $totalcommission += $ogupdate['parent2commission'];
                                }
                                if($ogupdate['parent3'] && ($ogupdate['parent3commission']>0 || $ogupdate['parent3score']>0)){
                                    $data_c = ['aid'=>aid,'mid'=>$ogupdate['parent3'],'frommid'=>$ordermid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent3commission'],'score'=>$ogupdate['parent3score'],'remark'=>t('下三级').'购买商品奖励','createtime'=>time()];
                                    if(getcustom('commission_butie')){
                                        $data_c['butie'] = $butie_data['parent3commission_butie'];
                                        $data_c['commission'] =  bcsub($ogupdate['parent3commission'],$butie_data['parent3commission_butie'],2);
                                    }
                                    if(getcustom('commission_max_times')){
                                        //分销份数限制
                                        $data_c['proid'] = $product['id'];
                                        $data_c['level'] = 3;
                                    }
                                    Db::name('member_commission_record')->insert($data_c);
                                    $totalcommission += $ogupdate['parent3commission'];
                                }
                                if($ogupdate['parent4'] && ($ogupdate['parent4commission']>0)){
                                    $remark = '持续推荐奖励';
                                    if(getcustom('commission_parent_pj_stop')){
                                        $remark = '平级奖';
                                    }

                                    Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent4'],'frommid'=>$ordermid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$ogupdate['parent4commission'],'score'=>0,'remark'=>$remark,'createtime'=>time()]);
                                    $totalcommission += $ogupdate['parent4commission'];
                                }
                                if(getcustom('commission_bole')){
                                    //分销伯乐奖
                                    if($commission_data['parent2_bole'] && $commission_data['parent2commission_bole']>0){
                                        $data_c = ['aid'=>aid,'mid'=>$commission_data['parent2_bole'],'frommid'=>$ogupdate['parent1'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$commission_data['parent2commission_bole'],'remark'=>'分销伯乐奖','createtime'=>time()];
                                        Db::name('member_commission_record')->insert($data_c);
                                    }
                                    if($commission_data['parent3_bole'] && $commission_data['parent3commission_bole']>0){
                                        $data_c = ['aid'=>aid,'mid'=>$commission_data['parent3_bole'],'frommid'=>$ogupdate['parent2'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$commission_data['parent3commission_bole'],'remark'=>'分销伯乐奖','createtime'=>time()];
                                        Db::name('member_commission_record')->insert($data_c);
                                    }
                                    if($commission_data['parent4_bole'] && $commission_data['parent4commission_bole']>0){
                                        $data_c = ['aid'=>aid,'mid'=>$commission_data['parent4_bole'],'frommid'=>$ogupdate['parent3'],'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$commission_data['parent4commission_bole'],'remark'=>'分销伯乐奖','createtime'=>time()];
                                        Db::name('member_commission_record')->insert($data_c);
                                    }
                                }
                            }

                            if($post['checkmemid'] && $commission_totalprice > 0){
                                $checkmember = Db::name('member')->where('aid',aid)->where('id',$post['checkmemid'])->find();
                                if($checkmember){
                                    $buyselect_commission = Db::name('member_level')->where('id',$checkmember['levelid'])->value('buyselect_commission');
                                    $checkmemcommission = $buyselect_commission * $commission_totalprice * 0.01;
                                    Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$checkmember['id'],'frommid'=>$ordermid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'shop','commission'=>$checkmemcommission,'score'=>0,'remark'=>'购买商品时指定奖励','createtime'=>time()]);
                                }
                            }
                        }
                    }

					Db::name('shop_guige')->where('aid',aid)->where('id',$guige['id'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
					Db::name('shop_product')->where('aid',aid)->where('id',$product['id'])->update(['stock'=>Db::raw("stock-$num"),'sales'=>Db::raw("sales+$num")]);
				}
			}

			if($orderdata['status'] == 1){
				if($hdinfo['type']==4){
					\app\models\Payorder::scoreshop_pay($orderid);
					$tmplcontent = [];
					$tmplcontent['first'] = '有新的兑换订单下单成功';
					$tmplcontent['remark'] = '点击进入查看~';
					$tmplcontent['keyword1'] = $this->member['nickname']; //用户名
					$tmplcontent['keyword2'] = $ordernum;//订单号
					$tmplcontent['keyword3'] = $orderdata['totalscore'].t('积分').($orderdata['totalprice']>0?' + '.$orderdata['totalprice'].'元':'');//订单金额
					$tmplcontent['keyword4'] = $orderdata['title'];//商品信息
                    $tmplcontentNew = [];
                    $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($orderdata);//门店
                    $tmplcontentNew['phrase18'] = $this->member['nickname']; //用户名
                    $tmplcontentNew['character_string2'] = $ordernum;//订单号
                    $tmplcontentNew['amount5'] = $orderdata['totalscore'].t('积分').($orderdata['totalprice']>0?' + '.$orderdata['totalprice'].'元':'');//订单金额
                    $tmplcontentNew['thing3'] = $orderdata['title'];//商品信息
					\app\commons\Wechat::sendhttmpl(aid,$orderdata['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/scoreshoporder'),$orderdata['mdid'],$tmplcontentNew);

					$tmplcontent = [];
					$tmplcontent['thing11'] = $orderdata['title'];
					$tmplcontent['character_string2'] = $orderdata['ordernum'];
					$tmplcontent['phrase10'] = '已支付';
					$tmplcontent['amount13'] = $orderdata['totalprice'].'元';
					$tmplcontent['thing27'] = $this->member['nickname'];
					\app\commons\Wechat::sendhtwxtmpl(aid,$orderdata['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/scoreshoporder',$orderdata['mdid']);
				}else{
					\app\models\Payorder::shop_pay($orderid);
					$tmplcontent = [];
					$tmplcontent['first'] = '有新的兑换订单下单成功';
					$tmplcontent['remark'] = '点击进入查看~';
					$tmplcontent['keyword1'] = $this->member['nickname']; //用户名
					$tmplcontent['keyword2'] = $ordernum;//订单号
					$tmplcontent['keyword3'] = $orderdata['totalprice'].'元';//订单金额
					$tmplcontent['keyword4'] = $orderdata['title'];//商品信息
                    $tmplcontentNew = [];
                    $tmplcontentNew['thing8'] = \app\commons\Mendian::getNameWithBusines($orderdata);//门店
                    $tmplcontentNew['phrase18'] = $this->member['nickname']; //用户名
                    $tmplcontentNew['character_string2'] = $ordernum;//订单号
                    $tmplcontentNew['amount5'] = $orderdata['totalprice']==0?'0.00':$orderdata['totalprice'];//订单金额
                    $tmplcontentNew['thing3'] = $orderdata['title'];//商品信息
					\app\commons\Wechat::sendhttmpl(aid,$orderdata['bid'],'tmpl_orderpay',$tmplcontent,m_url('admin/order/shoporder'),$orderdata['mdid'],$tmplcontentNew);

					$tmplcontent = [];
					$tmplcontent['thing11'] = $orderdata['title'];
					$tmplcontent['character_string2'] = $orderdata['ordernum'];
					$tmplcontent['phrase10'] = '已支付';
					$tmplcontent['amount13'] = $orderdata['totalprice'].'元';
					$tmplcontent['thing27'] = $this->member['nickname'];
					\app\commons\Wechat::sendhtwxtmpl(aid,$orderdata['bid'],'tmpl_orderpay',$tmplcontent,'admin/order/shoporder',$orderdata['mdid']);
				}
			}
		}

		//使用状态
		$usestatus = 1;
		if(getcustom('lipinka_morefee') || getcustom('lipinka_freight_free')){
        	//附加费用
        	if($morefee>0){
        		$usestatus = 0;
        	}
        	//增加礼品卡兑换记录
        	$orderdata = [];
        	$orderdata['aid']        = aid;
        	$orderdata['mid']        = mid;
        	$orderdata['title']      = $goods_name;
        	$orderdata['lpid']       = $hdinfo['id'];
        	$orderdata['codeid']     = $codeinfo['id'];
        	$orderdata['ordernum']   = $ordernum;
        	$orderdata['type']       = $hdinfo['type'];
        	$orderdata['fee_items']  = $hdinfo['fee_items'];
        	$orderdata['totalprice'] = $morefee;
        	$orderdata['status']     = $usestatus;
        	$orderdata['createtime'] = time();

        	$orderid = Db::name('lipin_order')->insertGetId($orderdata);
        	if(!$orderid){
        		return $this->json(['status'=>0,'msg'=>'兑换失败']);
        	}
        }
        if($usestatus){
        	Db::name('lipin_codelist')->where('id',$codeinfo['id'])->update(['status'=>1,'usetime'=>time(),'mid'=>mid,'headimg'=>$this->member['headimg'],'nickname'=>$this->member['nickname'],'remark'=>'兑换商品:'.$goods_name]);
			return $this->json(['status'=>1,'orderid'=>0,'ordercount'=>0,'ordernum'=>$ordernum,'msg'=>'兑换成功']);
        }else{
        	if(getcustom('lipinka_morefee') || getcustom('lipinka_freight_free')){
	        	$payorderid = \app\models\Payorder::createorder(aid,0,$orderdata['mid'],'lipin',$orderid,$orderdata['ordernum'],$orderdata['title'],$orderdata['totalprice'],0);
	        	return $this->json(['status'=>1,'payorderid'=>$payorderid,'msg'=>'提交成功']);
	        }
        }
		
	}
}