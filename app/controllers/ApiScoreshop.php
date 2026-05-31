<?php


namespace app\controllers;
use think\facade\Db;
class ApiScoreshop extends ApiCommon
{
	public function initialize(){
		parent::initialize();
		$set = Db::name('scoreshop_sysset')->where('aid',aid)->find();
		$gettj = explode(',',$set['gettj']);
		if(!in_array('-1',$gettj)){ //不是所有人
			$this->checklogin();
			if(!in_array($this->member['levelid'],$gettj)){
				echojson(['status'=>-4,'msg'=>$set['gettjtip'],'url'=>$set['gettjurl']]);
			}
		}
	}
	public function index(){
		$clist = Db::name('scoreshop_category')->where('aid',aid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray();
		$score = $this->member ? $this->member['score'] : 0;
		$bid = input('param.bid');
		if($bid > 0 && $this->member){
			$memberscore = Db::name('business_memberscore')->where('aid',aid)->where('bid',$bid)->where('mid',mid)->find();
			$score = $memberscore['score'] ?? 0;
		}

        $score_weishu = $this->score_weishu;
        $score = dd_money_format($score,$score_weishu);

        $background = PRE_URL.'/static/img/scoreshop_top.png';
		$rdata = [];
		$rdata['clist'] = $clist;
		$rdata['bgurl'] = $background;
		$rdata['score'] = $score;
		return $this->json($rdata);
	}
	public function category(){
		$datalist = Db::name('scoreshop_category')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$rdata = [];
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}
	public function prolist(){
		//分类
		if(input('param.cid')){
			$clist = Db::name('scoreshop_category')->where('aid',aid)->where('pid',input('param.cid/d'))->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}else{
			$clist = Db::name('scoreshop_category')->where('aid',aid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}
		return $this->json(['clist'=>$clist]);
	}
	public function getprolist(){
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',1];
		$where[] = ['ischecked','=',1];
		if(input('param.bid')){
			$where[] = ['bid','=',input('param.bid/d')];
		}else{
			$business_sysset = Db::name('business_sysset')->where('aid',aid)->find();
			if(!$business_sysset || $business_sysset['status']==0 || $business_sysset['product_isshow']==0){
				$where[] = ['bid','=',0];
			}
		}
		//分类 
		$searchcid = input('param.cid');
		if(input('param.cid')){
			$cid = input('param.cid/d');
			//子分类
			$clist = Db::name('scoreshop_category')->where('aid',aid)->where('pid',$cid)->select()->toArray();
			if($clist){
				$cateArr = [$cid];
				foreach($clist as $c){
					$cateArr[] = $c['id'];
				}
				$where[] = ['cid','in',$cateArr];
			}else{
				$where[] = ['cid','=',$cid];
				$pid = Db::name('scoreshop_category')->where('aid',aid)->where('id',$cid)->value('pid');
				if($pid){
					$searchcid = $pid;
					$clist = Db::name('scoreshop_category')->where('aid',aid)->where('pid',$pid)->select()->toArray();
				}
			}
		}
		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}

		$where2 = "find_in_set('-1',showtj)";
		if($this->member){
			$where2 .= " or find_in_set('".$this->member['levelid']."',showtj)";
			if($this->member['subscribe']==1){
				$where2 .= " or find_in_set('0',showtj)";
			}
		}
		$where[] = Db::raw($where2);
		
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order').',sort,id desc';
		}else{
			$order = 'sort desc,id desc';
		}

		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$field = "id,pic,name,sales,score_price,money_price,sell_price,sellpoint,fuwupoint,sales,lvprice,lvprice_data,freighttype";
        $datalist = Db::name('scoreshop_product')->field($field)->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
        $score_weishu = $this->score_weishu;
		if(!$datalist){
			$datalist = array();
		} else {
		    foreach ($datalist as $k=>&$product) {
				if(false){}else{
					$product = $this->formatScoreProduct($product);
	        	}
                $product['score_price'] = dd_money_format($product['score_price'],$score_weishu);

                $canaddcart = true;
                if($product['freighttype']==3 || $product['freighttype']==4){ 
                	//虚拟商品不能加入购物车
					$canaddcart = false;
				}
				$product['canaddcart'] = $canaddcart;
            }
            }
		return $this->json(['status'=>1,'data'=>$datalist]);
		$count = Db::name('scoreshop_product')->where($where)->count();

		$rdata = [];
		$rdata['clist'] = $clist;
		$rdata['searchcid'] = $searchcid;
		$rdata['pernum'] = $pernum;
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}
	public function product(){
		//if(!$this->member){
		//	return $this->json(['status'=>-1,'msg'=>'请先登录']);
		//}
		$proid = input('param.id/d');
		$where = [];
		$where[] = ['id','=',$proid];
		$where[] = ['aid','=',aid];
		$product = Db::name('scoreshop_product')->where($where)->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'商品不存在']);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'商品已下架']);
		if($product['ischecked']==0) return $this->json(['status'=>0,'msg'=>'商品未审核']);
        $score_weishu = $this->score_weishu;
        $product['score_price'] = dd_money_format($product['score_price'],$score_weishu);

		//显示条件
		if($product['showtj'] == '' && $product['bid']!=0) $product['showtj'] = '-1';
        $levelids = explode(',',$product['showtj']);
        //限制等级
        if(!in_array('-1',$levelids)){
            $this->checklogin();
            $showtj1 = false;
            $showtj2 = false;
            if(in_array($this->member['levelid'], $levelids)) {
                $showtj1 = true;
            }
            if(in_array('0',$levelids) && $this->member['subscribe']==1){
                $showtj2 = true;
            }
            if(!$showtj1 && !$showtj2){
                return $this->json(['status'=>0,'msg'=>'商品状态不可见']);
            }
        }

		if(!$product['pics']) $product['pics'] = $product['pic'];
		$product['pics'] = explode(',',$product['pics']);
		if($product['fuwupoint']){
			$product['fuwupoint'] = explode(' ',preg_replace("/\s+/",' ',str_replace('　',' ',trim($product['fuwupoint']))));
		}
        $product = $this->formatScoreProduct($product);

        //是否收藏
		$rs = Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','scoreshop')->find();
		if($rs){
			$isfavorite = true;
		}else{
			$isfavorite = false;
		}
		if($this->member){
			//添加浏览历史
			$rs = Db::name('member_history')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','scoreshop')->find();
			if($rs){
				Db::name('member_history')->where('id',$rs['id'])->update(['createtime'=>time()]);
			}else{
				Db::name('member_history')->insert(['aid'=>aid,'mid'=>mid,'proid'=>$proid,'type'=>'scoreshop','createtime'=>time()]);
			}
		}
		
        $sysset = Db::name('admin_set')->where('aid',aid)->field('name,logo,desc,fxjiesuantype,tel,kfurl,gzts,ddbb')->find();
        $shopset = Db::name('scoreshop_sysset')->where('aid',aid)->field('showjd,showcommission')->find();
		
		if($product['bid']!=0){
			$business = Db::name('business')->where('aid',aid)->where('id',$product['bid'])->field('id,name,logo,desc,tel,address,sales,kfurl')->find();
		}else{
			$business = $sysset;
		}

        //预计佣金
        $commission = 0;
        $product['commission_desc'] = '元';
        if($this->member && $shopset['showcommission']==1 && $product['commissionset']!=-1){
            $userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
            if($userlevel['can_agent']!=0){
                if($product['commissionset']==1){//按比例
                    $commissiondata = json_decode($product['commissiondata1'],true);
                    if($commissiondata){
                        $commission = $commissiondata[$userlevel['id']]['commission1'] * ($product['money_price'] - ($sysset['fxjiesuantype']==2 ? $product['cost_price'] : 0)) * 0.01;
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
                }elseif($product['commissionset']==4 && $product['lvprice']==1){//按价格差
                    $lvprice_data = json_decode($product['lvprice_data'],true);
                    $commission = array_shift($lvprice_data)['money_price'] - $product['money_price'];
                    if($commission < 0) $commission = 0;
                }elseif($product['commissionset']==5){//比例+积分
                    $commissiondata = json_decode($product['commissiondata5'],true);
                    if($commissiondata){
                        $commission = $commissiondata[$userlevel['id']]['commission1']['money'] * ($product['money_price'] - ($sysset['fxjiesuantype']==2 ? $product['cost_price'] : 0)) * 0.01;
                    }
                    $commissiondata = json_decode($product['commissiondata5'],true);
                    if($commissiondata){
                        $commission_score = $commissiondata[$userlevel['id']]['commission1']['score'];
                    }
                    $product['commission_score_desc'] = t('积分');
                }elseif($product['commissionset']==0){//按会员等级
                    //fxjiesuantype 0按商品价格,1按成交价格,2按销售利润
                    if($userlevel['commissiontype']==1){ //固定金额按单
                        $commission = $userlevel['commission1'];
                    }else{
                        $commission = $userlevel['commission1'] * ($product['money_price'] - ($sysset['fxjiesuantype']==2 ? $product['cost_price'] : 0)) * 0.01;
                    }
                }
            }
        }
        $product['commission'] = round($commission*100)/100;
        $product['commission_score'] = $commission_score ? $commission_score : 0;
        unset($product['cost_price']);

		$product['detail'] = \app\commons\System::initpagecontent($product['detail'],aid,mid,platform);
        if($product['guigeset'] == 1){
			$gglist = Db::name('scoreshop_guige')->where('aid',aid)->where('proid',$product['id'])->select()->toArray();
			if($product['lvprice']==1) $gglist = $this->formatscoreshopgglist($gglist);
			$guigelist = array();
			foreach($gglist as $k=>$v){
				$guigelist[$v['ks']] = $v;
			}
			$guigedata = json_decode($product['guigedata'],true);
		}else{
			$guigelist = [
				['id'=>'','name'=>'','pic'=>'','market_price'=>$product['sell_price'],'cost_price'=>$product['cost_price'],'money_price'=>$product['money_price'],'score_price'=>$product['score_price'],'weight'=>$product['weight'],'stock'=>$product['stock'],'ks'=>'0']
			];
			$guigedata = json_decode('[{"k":0,"title":"规格","items":[{"k":0,"title":"默认规格"}]}]',true);
		}

		$rdata = [];
		$rdata['product'] = $product;
		$rdata['myscore'] = $this->member['score'];
		$rdata['sysset'] = $sysset;
		$rdata['shopset'] = $shopset;
		$rdata['business'] = $business;
		$rdata['isfavorite'] = $isfavorite;
		$rdata['cartnum'] = Db::name('scoreshop_cart')->where('aid',aid)->where('mid',mid)->sum('num');
		$rdata['guigelist'] = $guigelist ?? [];
		$rdata['guigedata'] = $guigedata ?? [];
		return $this->json($rdata);
	}
	public function formatscoreshopgglist($gglist){
		if(!$this->member) return $gglist;
		foreach($gglist as $k=>$v){
			$lvprice_data = json_decode($v['lvprice_data'],true);
			if($lvprice_data && isset($lvprice_data[$this->member['levelid']])){
			    $gglist[$k]['money_price'] = $lvprice_data[$this->member['levelid']]['money'];
			    $gglist[$k]['score_price'] = $lvprice_data[$this->member['levelid']]['score'];
			}
		}
		return $gglist;
	}
	//购物车 
	public function cart(){
		$this->checklogin();
		$gwcdata = [];
		if(input('param.bid')){
			$cartlist = Db::name('scoreshop_cart')->field('id,bid,proid,ggid,num')->where('aid',aid)->where('mid',mid)->where('bid',input('param.bid'))->order('createtime desc')->select()->toArray();
		}else{
			$cartlist = Db::name('scoreshop_cart')->field('id,bid,proid,ggid,num')->where('aid',aid)->where('mid',mid)->order('createtime desc')->select()->toArray();
		}
		if(!$cartlist) $cartlist = [];
		
		if(input('param.isnew') == 1){ //新的方式 按商家归类
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
					$business = [
						'id'=>$this->sysset['id'],
						'name'=>$this->sysset['name'],
						'logo'=>$this->sysset['logo'],
						'tel'=>$this->sysset['tel']
					];
				}else{
					$business = Db::name('business')->where('aid',aid)->where('id',$bid)->field('id,name,logo,tel')->find();
				}
				$prolist = [];
				foreach($gwclist as $gwc){
					$product = Db::name('scoreshop_product')->where('aid',aid)->where('status',1)->where('id',$gwc['proid'])->find();
					if(!$product){
						Db::name('scoreshop_cart')->where('aid',aid)->where('proid',$gwc['proid'])->delete();continue;
					}
					$product = $this->formatScoreProduct($product);

					if($product['guigeset'] == 1){
						if(!$gwc['ggid']){
							Db::name('scoreshop_cart')->where('id',$gwc['id'])->delete();continue;
						}
						$guige = Db::name('scoreshop_guige')->where('aid',aid)->where('proid',$gwc['proid'])->where('id',$gwc['ggid'])->find();
						if(!$guige){
							Db::name('scoreshop_cart')->where('id',$gwc['id'])->delete();continue;
						}
						if($product['lvprice']==1){
							$lvprice_data = json_decode($guige['lvprice_data'],true);
							if($lvprice_data && isset($lvprice_data[$this->member['levelid']])){
								$guige['money_price'] = $lvprice_data[$this->member['levelid']]['money'];
								$guige['score_price'] = $lvprice_data[$this->member['levelid']]['score'];
							}
						}
						$product['money_price'] = $guige['money_price'];
						$product['score_price'] = $guige['score_price'];
						$product['ggname'] = $guige['name'];
						$product['ggpic'] = $guige['pic'];
						$product['stock'] = $guige['stock'];
					}
					$cartlist[$k]['product'] = $product;
					$tmpitem = ['id'=>$gwc['id'],'checked'=>true,'product'=>$product,'num'=>$gwc['num'],'ggid'=>$gwc['ggid']];
					$prolist[] = $tmpitem;
				}
				$newcartlist[$bid] = ['bid'=>$bid,'checked'=>true,'business'=>$business,'prolist'=>$prolist];
			}
			$cartlist = array_values($newcartlist);
		}else{
			foreach($cartlist as $k=>$gwc){
				$product = Db::name('scoreshop_product')->where('aid',aid)->where('status',1)->where('id',$gwc['proid'])->find();
				if(!$product){
					Db::name('scoreshop_cart')->where('aid',aid)->where('proid',$gwc['proid'])->delete();continue;
				}
				$product = $this->formatScoreProduct($product);

				if($product['guigeset'] == 1){
					if(!$gwc['ggid']){
						Db::name('scoreshop_cart')->where('id',$gwc['id'])->delete();continue;
					}
					$guige = Db::name('scoreshop_guige')->where('aid',aid)->where('proid',$gwc['proid'])->where('id',$gwc['ggid'])->find();
					if(!$guige){
						Db::name('scoreshop_cart')->where('id',$gwc['id'])->delete();continue;
					}
					if($product['lvprice']==1){
						$lvprice_data = json_decode($guige['lvprice_data'],true);
						if($lvprice_data && isset($lvprice_data[$this->member['levelid']])){
							$guige['money_price'] = $lvprice_data[$this->member['levelid']]['money'];
							$guige['score_price'] = $lvprice_data[$this->member['levelid']]['score'];
						}
					}
					$product['money_price'] = $guige['money_price'];
					$product['score_price'] = $guige['score_price'];
					$product['ggname'] = $guige['name'];
				}
				$cartlist[$k]['product'] = $product;
			}
		}

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['cartlist'] = $cartlist;
		return $this->json($rdata);
	}
	public function addcart(){
		$this->checklogin();
		$post = input('post.');
		$oldnum = 0;
		$proid = intval($post['proid']);
		$ggid = $post['ggid'] ? intval($post['ggid']) : null;
		$num = intval($post['num']);
		$gwc = Db::name('scoreshop_cart')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('ggid',$ggid)->find();
		if($gwc) $oldnum = $gwc['num'];

		$product = Db::name('scoreshop_product')->where('aid',aid)->where('status',1)->where('id',$proid)->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
		if($product['freighttype']==3 || $product['freighttype']==4) return $this->json(['status'=>0,'msg'=>'虚拟商品不能加入购物车']);
		if($oldnum + $num <=0){
			Db::name('scoreshop_cart')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('ggid',$ggid)->update(['num'=>1]);
			$cartnum = Db::name('scoreshop_cart')->where('aid',aid)->where('mid',mid)->sum('num');
			return $this->json(['status'=>1,'msg'=>'加入购物车成功','cartnum'=>$cartnum]);
		}
		if($gwc){
			Db::name('scoreshop_cart')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('ggid',$ggid)->inc('num',$num)->update();
		}else{
			$data = [];
			$data['aid'] = aid;
			$data['bid'] = $product['bid'];
			$data['mid'] = mid;
			$data['proid'] = $proid;
			$data['ggid'] = $ggid;
			$data['num'] = $num;
			$data['createtime'] = time();
			Db::name('scoreshop_cart')->insert($data);
		}
		$cartnum = Db::name('scoreshop_cart')->where('aid',aid)->where('mid',mid)->sum('num');
		return $this->json(['status'=>1,'msg'=>'加入购物车成功','cartnum'=>$cartnum]);
	}
	public function cartChangenum(){
		$this->checklogin();
		$id = input('post.id/d');
		$num = input('post.num/d');
		if($num < 1) $num = 1;
		Db::name('scoreshop_cart')->where('id',$id)->where('mid',mid)->update(['num'=>$num]);
		return $this->json(['status'=>1,'msg'=>'修改成功']);
	}
	public function cartdelete(){
		$this->checklogin();
		$id = input('post.id/d');
		if(!$id){
			$bid = input('post.bid/d');
			Db::name('scoreshop_cart')->where('bid',$bid)->where('mid',mid)->delete();
			return $this->json(['status'=>1,'msg'=>'删除成功']);
		}
		Db::name('scoreshop_cart')->where('id',$id)->where('mid',mid)->delete();
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}
	public function buy(){
		$this->checklogin();

		$prodata = explode('-',input('param.prodata'));
		
		$product = Db::name('scoreshop_product')->where('aid',aid)->where('status',1)->where('id',explode(',',$prodata[0])[0])->find();
		$bid = $product['bid'];

		$freightList = \app\models\Freight::getList([['status','=',1],['aid','=',aid],['bid','=',$bid]]);

		$fids = [];
		foreach($freightList as $v){
			$fids[] = $v['id'];
		}
		$allbuydata = [];
		$totalmoney = 0;
		$totalscore = 0;
		$totalweight = 0;
		$totalnum = 0;
		$prolist = [];
		$autofahuo = 0;
		$bids = [];
        $score_weishu = $this->score_weishu;
        $contact_require = 0;
		foreach($prodata as $key=>$gwc){
			$gwcArr = explode(',',$gwc);
			$proid = intval($gwcArr[0]);
			$num = intval($gwcArr[1]);
			$ggid = $gwcArr[2] && $gwcArr[2] != 'null' ? intval($gwcArr[2]) : null;
			if($num < 1) $num = 1;
			$product = Db::name('scoreshop_product')->where('aid',aid)->where('status',1)->where('id',$proid)->find();
            $product['score_price'] = dd_money_format($product['score_price'],$score_weishu);
			if(!$product){
				return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
			}

      if(($product['freighttype'] == 3||$product['freighttype']==4) && $product['contact_require'] == 1){
        $contact_require = 1;
      }

			if($product['stock'] < $num){
				return $this->json(['status'=>0,'msg'=>'库存不足']);
			}
			if($product['gettj'] == '' && $product['bid']!=0) $product['gettj'] = '-1';
			$gettj = explode(',',$product['gettj']);
			if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj) && (!in_array('0',$gettj) || $this->member['subscribe']!=1)){ //不是所有人
				if(!$product['gettjtip']) $product['gettjtip'] = '没有权限兑换该商品';
				return $this->json(['status'=>0,'msg'=>$product['gettjtip'],'url'=>$product['gettjurl']]);
			}

            //是否达到限制兑换数
            if($product['buymax'] > 0){
                $buynum = $num + Db::name('scoreshop_order_goods')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('status','in','0,1,2,3')->sum('num');
                if($buynum > $product['buymax']){
                    return $this->json(['status'=>0,'msg'=>'每人限兑'.$product['buymax'].'次']);
                }
            }
            //是否达到每天限制兑换数
            if($product['everyday_buymax'] > 0){
                $today_start = strtotime(date('Y-m-d').' 00:00:01');
                $today_end = strtotime(date('Y-m-d').' 23:59:59');
                $everydaybuynum = $num + Db::name('scoreshop_order_goods')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('status','in','0,1,2,3')->where('createtime','between',[$today_start,$today_end])->sum('num');
                if($everydaybuynum > $product['everyday_buymax']){
                    return $this->json(['status'=>0,'msg'=>'每人每天限兑'.$product['everyday_buymax'].'次']);
                }
            }
            $product = $this->formatScoreProduct($product);
			
			if($product['guigeset'] == 1){
				if(!$ggid) return $this->json(['status'=>0,'msg'=>'请选择规格']);
				$guige = Db::name('scoreshop_guige')->where('aid',aid)->where('proid',$proid)->where('id',$ggid)->find();
				if(!$guige) return $this->json(['status'=>0,'msg'=>'规格不存在']);
				if($guige['stock'] < $num){
					return $this->json(['status'=>0,'msg'=>'库存不足']);
				}
				if($product['lvprice']==1){
					$lvprice_data = json_decode($guige['lvprice_data'],true);
					if($lvprice_data && isset($lvprice_data[$this->member['levelid']])){
						$guige['money_price'] = $lvprice_data[$this->member['levelid']]['money'];
						$guige['score_price'] = $lvprice_data[$this->member['levelid']]['score'];
					}
				}
				$product['money_price'] = $guige['money_price'];
				$product['score_price'] = $guige['score_price'];
				$product['ggname'] = $guige['name'];
				$product['ggpic'] = $guige['pic'];
				$product['weight'] = $guige['weight'];
				$product['stock'] = $guige['stock'];
			}

			$totalmoney += $product['money_price'] * $num;
			$totalscore += $product['score_price'] * $num;
			$totalweight += $product['weight'] * $num;
			$totalnum += $num;

			if($product['freighttype']==3 || $product['freighttype']==4) $autofahuo = $product['freighttype'];
		
			if($product['freighttype']==0){
				$fids = array_intersect($fids,explode(',',$product['freightdata']));
			}elseif($product['freighttype']==3 || $product['freighttype']==4){
				$autofahuo = $product['freighttype'];
			}else{
				$thisfreightList = \app\models\Freight::getList([['status','=',1],['aid','=',aid],['bid','=',$bid]]);
				$thisfids = [];
				foreach($thisfreightList as $v){
					$thisfids[] = $v['id'];
				}
				$fids = array_intersect($fids,$thisfids);
			}
			$product['num'] = $num;
			if(!in_array($product['bid'],$bids)) $bids[] = $product['bid'];
			$prolist[] = $product;

			if(!$allbuydata[$product['bid']]) $allbuydata[$product['bid']] = [];
			if(!$allbuydata[$product['bid']]['prodata']) $allbuydata[$product['bid']]['prodata'] = [];
			$allbuydata[$product['bid']]['prodata'][] = ['product'=>$product,'num'=>$num,'ggid'=>$ggid];
		}
		//if(count($bids) > 1) return $this->json(['status'=>0,'msg'=>'不同商家的商品请分别下单']);
		
		if($autofahuo>0 && count($prodata) > 1){
			return $this->json(['status'=>0,'msg'=>'虚拟商品请单独购买']);
		}
		
		$havetongcheng = 0;
		$needLocation = 0;
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
		if(!$address) $address = array();

		
		foreach($allbuydata as $bid=>$buydata){
			$product_priceArr = [];
			$product_scoreArr = [];
			$product_price = 0;
			$product_score = 0;
			$totalweight = 0;
			$totalnum = 0;
			$prodataArr = [];
			$proids = [];
			foreach($buydata['prodata'] as $prodata){
				$product_priceArr[] = $prodata['product']['money_price'] * $prodata['num'];
				$product_scoreArr[] = $prodata['product']['score_price'] * $prodata['num'];
				$product_price += $prodata['product']['money_price'] * $prodata['num'];
				$product_score += $prodata['product']['score_price'] * $prodata['num'];
				$totalweight += $prodata['product']['weight'] * $prodata['num'];
				$totalnum += $prodata['num'];
				$prodataArr[] =  $prodata['product']['id'].','.$prodata['num'].','.$prodata['ggid'];
				$proids[] = $prodata['product']['id'];
			}
			$rs = \app\models\Freight::formatFreightList($buydata['freightList'],$address,$product_price,$totalnum,$totalweight);
			$freightList = $rs['freightList'];
			$freightArr = $rs['freightArr'];
			if($rs['needLocation']==1) $needLocation = 1;

			
			if($bid!=0){
				$business = Db::name('business')->where('id',$bid)->field('id,aid,cid,name,logo,tel,address,sales,longitude,latitude,start_hours,end_hours,start_hours2,end_hours2,start_hours3,end_hours3,end_buy_status,invoice,invoice_type')->find();
			}else{
				$business = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,desc,tel,invoice,invoice_type,invoice_rate')->find();
			}

			$allbuydata[$bid]['bid'] = $bid;
			$allbuydata[$bid]['business'] = $business;
			$allbuydata[$bid]['prodatastr'] = implode('-',$prodataArr);
			$allbuydata[$bid]['freightList'] = $freightList;
			$allbuydata[$bid]['freightArr'] = $freightArr;
			$allbuydata[$bid]['product_price'] = round($product_price,2);
			$allbuydata[$bid]['product_score'] = $product_score;
			$allbuydata[$bid]['freightkey'] = 0;
			$allbuydata[$bid]['pstimetext'] = '';
			$allbuydata[$bid]['freight_time'] = '';
			$allbuydata[$bid]['storeid'] = 0;
			$allbuydata[$bid]['storename'] = '';
            $allbuydata[$bid]['cuxiao_money'] = 0;
            $allbuydata[$bid]['cuxiaotype'] = 0;
            $allbuydata[$bid]['cuxiaoid'] = 0;
            $allbuydata[$bid]['invoice_money'] = 0;
            $allbuydata[$bid]['editorFormdata'] = [];
		}

		$rdata = [];
		$rdata['linkman'] = $address ? $address['name'] : strval($this->member['realname']);
		$rdata['tel'] = $address ? $address['tel'] : strval($this->member['tel']);
		if(!$rdata['linkman']){
			$lastorder = Db::name('scoreshop_order')->where('aid',aid)->where('mid',mid)->where('linkman','<>','')->find();
			if($lastorder){
				$rdata['linkman'] = $lastorder['linkman'];
				$rdata['tel'] = $lastorder['tel'];
			}
		}
		$rdata['totalmoney'] = $totalmoney;
		$rdata['totalscore'] = $totalscore;
		$rdata['totalnum'] = $totalnum;
		$rdata['totalweight'] = $totalweight;
		$rdata['havetongcheng'] = $havetongcheng;
		$rdata['address'] = $address;
		$rdata['prolist'] = $prolist;
		$rdata['freightList'] = $freightList;
		$rdata['freightArr'] = $freightArr;
		$rdata['needLocation'] = $needLocation;
		$rdata['allbuydata'] = $allbuydata;
    $rdata['contact_require'] = $contact_require;
		return $this->json($rdata);
	}
	public function createOrder(){
		$this->checklogin();
		$post = input('post.');
		if(input('param.prodata')){
			$buydata = [[
				'bid'=>0,
				'prodata'=>$post['prodata'],
				'freight_id'=>$post['freightid'],
				'freight_time'=>$post['freight_time'],
				'storeid'=>$post['storeid'],
				'formdata'=>$post['formdata']
			]];
		}else{
			$buydata = $post['buydata'];
		}
        $sysset = Db::name('admin_set')->where('aid',aid)->find();

		
		$alltotalprice = 0;
		$alltotalscore = 0;
		$i = 0;
		$ordernum = date('ymdHis').aid.rand(1000,9999);

		foreach($buydata as $data){
			$i++;
			$bid = $data['bid'];
			if($data['prodata']){
				$prodata = explode('-',$data['prodata']);
			}else{
				return $this->json(['status'=>0,'msg'=>'产品数据错误']);
			}

			$totalmoney = 0;
			$totalscore = 0;
			$totalweight = 0;
			$totalnum = 0;
			$prolist = [];
			$autofahuo = 0;
            $isdghongbao = 0;//是否是兑换红包
			foreach($prodata as $key=>$gwc){
				$gwcArr = explode(',',$gwc);
				$proid = intval($gwcArr[0]);
				$num = intval($gwcArr[1]);
				$ggid = $gwcArr[2] && $gwcArr[2] != 'null' ? intval($gwcArr[2]) : null;
				if($num < 1) $num = 1;
				$product = Db::name('scoreshop_product')->where('aid',aid)->where('status',1)->where('id',$proid)->find();
				if(!$product){
					return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
				}
				if($product['stock'] < $num){
					return $this->json(['status'=>0,'msg'=>'库存不足']);
				}
				if($product['gettj'] == '' && $product['bid']!=0) $product['gettj'] = '-1';
				$gettj = explode(',',$product['gettj']);
				if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj) && (!in_array('0',$gettj) || $this->member['subscribe']!=1)){ //不是所有人
					if(!$product['gettjtip']) $product['gettjtip'] = '没有权限兑换该商品';
					return $this->json(['status'=>0,'msg'=>$product['gettjtip'],'url'=>$product['gettjurl']]);
				}

				$bid = $product['bid'];
				//是否达到限制兑换数
				if($product['buymax'] > 0){
					$buynum = $num + Db::name('scoreshop_order_goods')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('status','in','0,1,2,3')->sum('num');
					if($buynum > $product['buymax']){
						return $this->json(['status'=>0,'msg'=>'每人限兑'.$product['buymax'].'次']);
					}
				}
				$product = $this->formatScoreProduct($product);

				if($product['guigeset'] == 1){
					if(!$ggid) return $this->json(['status'=>0,'msg'=>'请选择规格']);
					$guige = Db::name('scoreshop_guige')->where('aid',aid)->where('proid',$proid)->where('id',$ggid)->find();
					if(!$guige) return $this->json(['status'=>0,'msg'=>'规格不存在']);
					if($guige['stock'] < $num){
						return $this->json(['status'=>0,'msg'=>'库存不足']);
					}
					if($product['lvprice']==1){
						$lvprice_data = json_decode($guige['lvprice_data'],true);
						if($lvprice_data && isset($lvprice_data[$this->member['levelid']])){
							$guige['money_price'] = $lvprice_data[$this->member['levelid']]['money'];
							$guige['score_price'] = $lvprice_data[$this->member['levelid']]['score'];
						}
					}
					$product['money_price'] = $guige['money_price'];
					$product['score_price'] = $guige['score_price'];
					$product['ggid'] = $guige['id'];
					$product['ggname'] = $guige['name'];
				}

				$totalmoney += $product['money_price'] * $num;
				$totalscore += $product['score_price'] * $num;
				$totalweight += $product['weight'] * $num;
				$totalnum += $num;
				$product['num'] = $num;
				$prolist[] = $product;
				if($product['freighttype']==3 || $product['freighttype']==4){
					$autofahuo = $product['freighttype'];
				}
				}
			if($autofahuo && count($prodata)>1) $this->json(['status'=>0,'msg'=>'虚拟商品请分别下单']);
			//收货地址
			if($post['addressid']=='' || $post['addressid']==0){
				$address = ['id'=>0,'name'=>$post['linkman'],'tel'=>$post['tel'],'area'=>'','address'=>''];
			}else{
				$address = Db::name('member_address')->where('id',$post['addressid'])->where('aid',aid)->where('mid',mid)->find();
			}
			//运费
			$freight_price = 0;
			if($data['freight_id']){
				$freight = Db::name('freight')->where('aid',aid)->where('bid',$bid)->where('id',$data['freight_id'])->find();
				if(($address['name']=='' || $address['tel'] =='') && ($freight['pstype']==1 || $freight['pstype']==3) && $freight['needlinkinfo']==1){
					return $this->json(['status'=>0,'msg'=>'请填写联系人和联系电话']);
				}

				$rs = \app\models\Freight::getFreightPrice($freight,$address,$totalmoney,$totalnum,$totalweight);
				if($rs['status']==0) return $this->json($rs);
				$freight_price = $rs['freight_price'];

				//判断配送时间选择是否符合要求
				if($freight['pstimeset']==1){
					//$freighttime = strtotime(explode('~',$data['freight_time'])[0]);
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
                if($product['contact_require'] == 1 && ($address['name']=='' || $address['tel'] =='')){
                    return $this->json(['status'=>0,'msg'=>'请填写联系人和联系电话']);
                }
                if($address['tel']!='' && !checkTel($address['tel'])){
                    return $this->json(['status'=>0,'msg'=>'请填写正确的联系电话']);
                }
			}elseif($product['freighttype']==4){
				$freight = ['id'=>0,'name'=>'在线卡密','pstype'=>4];
                if($product['contact_require'] == 1 && ($address['name']=='' || $address['tel'] =='')){
                    return $this->json(['status'=>0,'msg'=>'请填写联系人和联系电话']);
                }
                if($address['tel']!='' && !checkTel($address['tel'])){
                    return $this->json(['status'=>0,'msg'=>'请填写正确的联系电话']);
                }
			}else{
				$freight = ['id'=>0,'name'=>'包邮','pstype'=>0];
			}
			//$totalmoney = $totalmoney + $freight_price;
			
			$orderdata = [];
			$orderdata['aid'] = aid;
			$orderdata['bid'] = $bid;
			$orderdata['mid'] = mid;
			if(count($buydata) > 1){
				$orderdata['ordernum'] = $ordernum.'_'.$i;
			}else{
				$orderdata['ordernum'] = $ordernum;
			}
			$orderdata['title'] = removeEmoj($prolist[0]['name']).(count($prolist)>1 ? '等' : '');
			$orderdata['linkman'] = $address['name'];
			$orderdata['tel'] = $address['tel'];
			$orderdata['area'] = $address['area'];
			$orderdata['area2'] = $address['province'].','.$address['city'].','.$address['district'];
			$orderdata['address'] = $address['address'];
			$orderdata['longitude'] = $address['longitude'];
			$orderdata['latitude'] = $address['latitude'];
            $score_weishu = $this->score_weishu;
			$orderdata['totalscore'] = dd_money_format($totalscore,$score_weishu);
			$orderdata['totalmoney'] = $totalmoney;
			$orderdata['totalnum'] = $totalnum;
			$orderdata['freight_price'] = $freight_price; //运费
			$orderdata['totalprice'] = $totalmoney + $freight_price*1;
			if($freight && ($freight['pstype']==0||$freight['pstype']==10)){
				$orderdata['freight_text'] = $freight['name'].'('.$freight_price.'元)';
				$orderdata['freight_type'] = $freight['pstype'];
			}elseif($freight && $freight['pstype']==1){
				$storename = Db::name('mendian')->where('aid',aid)->where('id',$data['storeid'])->value('name');
				$orderdata['freight_text'] = $freight['name'].'['.$storename.']';
				$orderdata['freight_type'] = 1;
				$orderdata['mdid'] = $data['storeid'];
				}elseif($freight && $freight['pstype']==2){
				$orderdata['freight_text'] = $freight['name'].'('.$freight_price.'元)';
				$orderdata['freight_type'] = 2;
			}elseif($freight && ($freight['pstype']==3 || $freight['pstype']==4)){ //自动发货 在线卡密
				$orderdata['freight_text'] = $freight['name'];
				$orderdata['freight_type'] = $freight['pstype'];
			}else{
				$orderdata['freight_text'] = '包邮';
			}
			$orderdata['freight_id'] = $freight['id'];
			$orderdata['freight_time'] = $data['freight_time']; //配送时间
			$orderdata['createtime'] = time();
			$orderdata['hexiao_code'] = random(16);
			$orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=scoreshop&co='.$orderdata['hexiao_code']));
			$orderdata['platform'] = platform;
			$orderid = Db::name('scoreshop_order')->insertGetId($orderdata);
			\app\models\Freight::saveformdata($orderid,'scoreshop_order',$freight['id'],$data['formdata']);
			$payorderid = \app\models\Payorder::createorder(aid,$orderdata['bid'],$orderdata['mid'],'scoreshop',$orderid,$orderdata['ordernum'],$orderdata['title'],$orderdata['totalprice'],$orderdata['totalscore']);

			
			$alltotalprice += $orderdata['totalprice'];
			$alltotalscore += $orderdata['totalscore'];

			$istc1 = 0; //设置了按单固定提成时 只将佣金计算到第一个商品里
			$istc2 = 0;
			$istc3 = 0;
			foreach($prolist as $product){
				$ogdata = [];
				$ogdata['aid'] = aid;
				$ogdata['bid'] = $bid;
				$ogdata['mid'] = mid;
				$ogdata['orderid'] = $orderid;
				$ogdata['ordernum'] = $orderdata['ordernum'];
				$ogdata['proid'] = $product['id'];
				$ogdata['name'] = $product['name'];
				$ogdata['ggid'] = $product['ggid'] ?? null;
				$ogdata['ggname'] = $product['ggname'] ?? null;
				$ogdata['pic'] = $product['pic'];
				$ogdata['procode'] = $product['procode'];
				$ogdata['num'] = $product['num'];
				$ogdata['sell_price'] = $product['sell_price'];
				$ogdata['cost_price'] = $product['cost_price'];
				$ogdata['money_price'] = $product['money_price'];
				$ogdata['score_price'] = $product['score_price'];
				$ogdata['totalscore'] = $product['score_price'] * $product['num'];
				$ogdata['totalmoney'] = $product['money_price'] * $product['num'];
				$ogdata['status'] = 0;
				$ogdata['createtime'] = time();
				//分销
				$og_totalprice = $ogdata['totalmoney'];

				//计算商品实际金额  商品金额 - 会员折扣 - 积分抵扣 - 满减抵扣 - 优惠券抵扣
				//0按商品价格，1按成交价，2按销售利润
				$leveldk_money = 0;
				$coupon_money = 0;
				$scoredk_money = 0;
				$manjian_money = 0;
				if($sysset['fxjiesuantype'] == 1 || $sysset['fxjiesuantype'] == 2){
					$allproduct_price = $og_totalprice;
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
					$og_totalprice = $og_totalprice - $og_leveldk_money - $og_scoredk_money - $og_manjian_money;
	//                if($couponrecord['type']!=4) {//运费抵扣券
						$og_totalprice -= $og_coupon_money;
	//                }
					$og_totalprice = round($og_totalprice,2);
					if($og_totalprice < 0) $og_totalprice = 0;
				}

				//计算佣金的商品金额
				$commission_totalprice = $ogdata['totalmoney'];
				if($sysset['fxjiesuantype']==1){ //按成交价格
					$commission_totalprice = $og_totalprice;
					if($commission_totalprice < 0) $commission_totalprice = 0;
				}
				if($sysset['fxjiesuantype']==2){ //按销售利润
					$commission_totalprice = $og_totalprice - $product['cost_price'] * $product['num'];
					if($commission_totalprice < 0) $commission_totalprice = 0;
				}

				$agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
				if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
					$this->member['pid'] = mid;
				}

				if($product['commissionset']!=-1){
					if($this->member['pid']){
						$parent1 = Db::name('member')->where('aid',aid)->where('id',$this->member['pid'])->find();
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
					if($product['commissionset']==1){//按商品设置的分销比例
						$commissiondata = json_decode($product['commissiondata1'],true);
						if($commissiondata){
							if($agleveldata1) $ogdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
							if($agleveldata2) $ogdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
							if($agleveldata3) $ogdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
						}
					}elseif($product['commissionset']==2){//按固定金额
						$commissiondata = json_decode($product['commissiondata2'],true);
						if($commissiondata){
							if($agleveldata1) $ogdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $ogdata['num'];
							if($agleveldata2) $ogdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $ogdata['num'];
							if($agleveldata3) $ogdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $ogdata['num'];
						}
					}elseif($product['commissionset']==3){//提成是积分
						$commissiondata = json_decode($product['commissiondata3'],true);
						if($commissiondata){
							if($agleveldata1) $ogdata['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'] * $ogdata['num'];
							if($agleveldata2) $ogdata['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'] * $ogdata['num'];
							if($agleveldata3) $ogdata['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'] * $ogdata['num'];
						}
					}elseif($product['commissionset']==5){//比例+积分
						$commissiondata = json_decode($product['commissiondata5'],true);
						if($commissiondata){
							if($agleveldata1) {
								$ogdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1']['money'] * $commission_totalprice * 0.01;
								$ogdata['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1']['score'] * $ogdata['num'];
							}
							if($agleveldata2) {
								$ogdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2']['money'] * $commission_totalprice * 0.01;
								$ogdata['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2']['score'] * $ogdata['num'];
							}
							if($agleveldata3) {
								$ogdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3']['money'] * $commission_totalprice * 0.01;
								$ogdata['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3']['score'] * $ogdata['num'];
							}
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

				//计算门店佣金
				if(getcustom('scoreshop_fenhong') && $product['fenhongset'] == 0){ //不参与分红
                    $ogdata['isfenhong'] = 2;
                }
				$ogid = Db::name('scoreshop_order_goods')->insertGetId($ogdata);
				if($ogdata['parent1'] && ($ogdata['parent1commission'] > 0 || $ogdata['parent1score'] > 0)){
					Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent1'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'scoreshop','commission'=>$ogdata['parent1commission'],'score'=>$ogdata['parent1score'],'remark'=>'下级购买积分商品奖励','createtime'=>time()]);
				}
				if($ogdata['parent2'] && ($ogdata['parent2commission'] || $ogdata['parent2score'])){
					Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent2'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'scoreshop','commission'=>$ogdata['parent2commission'],'score'=>$ogdata['parent2score'],'remark'=>'下二级购买积分商品奖励','createtime'=>time()]);
				}
				if($ogdata['parent3'] && ($ogdata['parent3commission'] || $ogdata['parent3score'])){
					Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogdata['parent3'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$ogid,'type'=>'scoreshop','commission'=>$ogdata['parent3commission'],'score'=>$ogdata['parent3score'],'remark'=>'下三级购买积分商品奖励','createtime'=>time()]);
				}
				//减库存加销量
				Db::name('scoreshop_product')->where('aid',aid)->where('id',$product['id'])->update(['stock'=>$product['stock'] - $ogdata['num'],'sales'=>$product['sales'] + $ogdata['num']]);
			}
            $store_name = Db::name('admin_set')->where('aid',aid)->value('name');
			//公众号通知 订单提交成功
			$tmplcontent = [];
			$tmplcontent['first'] = '有新'.t('积分').'兑换订单提交成功';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $store_name; //店铺
			$tmplcontent['keyword2'] = date('Y-m-d H:i:s',$orderdata['createtime']);//下单时间
			$tmplcontent['keyword3'] = $orderdata['title'];//商品
			$tmplcontent['keyword4'] = $orderdata['totalscore'].t('积分').($orderdata['totalprice']>0?' + '.$orderdata['totalprice'].'元':'');//金额
            $tempconNew = [];
            $tempconNew['character_string2'] = $orderdata['ordernum'];//订单号
            $tempconNew['thing8'] = $store_name;//门店名称
            $tempconNew['thing3'] = $orderdata['title'];//商品名称
            $tempconNew['amount7'] = $orderdata['totalscore'].t('积分').($orderdata['totalprice']>0?' + '.$orderdata['totalprice'].'元':'');//金额
            $tempconNew['time4'] = date('Y-m-d H:i:s',$orderdata['createtime']);//下单时间
			\app\commons\Wechat::sendhttmpl(aid,0,'tmpl_orderconfirm',$tmplcontent,m_url('admin/order/scoreshoporder'),$orderdata['mdid'],$tempconNew);
			
			$tmplcontent = [];
			$tmplcontent['thing11'] = $orderdata['title'];
			$tmplcontent['character_string2'] = $orderdata['ordernum'];
			$tmplcontent['phrase10'] = '待付款';
			$tmplcontent['amount13'] = $orderdata['totalprice'].'元';
			$tmplcontent['thing27'] = $this->member['nickname'];
			\app\commons\Wechat::sendhtwxtmpl(aid,0,'tmpl_orderconfirm',$tmplcontent,'admin/order/scoreshoporder',$orderdata['mdid']);
		}
		
		if(count($buydata) > 1){ //创建合并支付单
			$payorderid = \app\models\Payorder::createorder(aid,0,mid,'scoreshop_hb',$orderid,$ordernum,$orderdata['title'],$alltotalprice,$alltotalscore);
		}

		return $this->json(['status'=>1,'orderid'=>$orderid,'payorderid'=>$payorderid,'msg'=>'提交成功']);
	}
	public function orderlist(){
        $this->checklogin();
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$where[] = ['delete','=',0];
        if(input('param.keyword')) $where[] = ['ordernum|title', 'like', '%'.input('param.keyword').'%'];
		if($st == 'all'){
			
		}elseif($st == '0'){
			$where[] = ['status','=',0];
		}elseif($st == '1'){
			$where[] = ['status','=',1];
		}elseif($st == '2'){
			$where[] = ['status','=',2];
		}elseif($st == '3'){
			$where[] = ['status','=',3];
		}elseif($st == '10'){
			$where[] = ['refund_status','>',0];
		}
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('scoreshop_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
        $score_weishu = $this->score_weishu;
		foreach($datalist as $key=>$v){
		    $prolist = Db::name('scoreshop_order_goods')->where('orderid',$v['id'])->select()->toArray();
		    foreach($prolist as $k_p=>$v_p){
                $prolist[$k_p]['score_price'] = dd_money_format($v_p['score_price'],$score_weishu);
            }
			$datalist[$key]['prolist'] = $prolist;
			if(!$datalist[$key]['prolist']) $datalist[$key]['prolist'] = [];
			$datalist[$key]['procount'] = Db::name('scoreshop_order_goods')->where('orderid',$v['id'])->sum('num');
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['st'] = $st;
		return $this->json($rdata);
	}
	public function orderdetail(){
        $this->checklogin();
        $score_weishu = $this->score_weishu;
		$detail = Db::name('scoreshop_order')->where('id',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();
        $detail['totalscore'] = dd_money_format($detail['totalscore'],$score_weishu);
		if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'scoreshop_order');

		$storeinfo = [];//门店
		$storelist = [];
		if($detail['freight_type'] == 1){
			if($detail['mdid'] == -1){
				$freight = Db::name('freight')->where('id',$detail['freight_id'])->find();
				if($freight && $freight['hxbids']){
					if($detail['longitude'] && $detail['latitude']){
						$orderBy = Db::raw("({$detail['longitude']}-longitude)*({$detail['longitude']}-longitude) + ({$detail['latitude']}-latitude)*({$detail['latitude']}-latitude) ");
					}else{
						$orderBy = 'sort desc,id';
					}
					$storelist = Db::name('business')->where('aid',$freight['aid'])->where('id','in',$freight['hxbids'])->where('status',1)->field('id,name,logo pic,longitude,latitude,address')->order($orderBy)->select()->toArray();
					foreach($storelist as $k2=>$v2){
						if($detail['longitude'] && $detail['latitude'] && $v2['longitude'] && $v2['latitude']){
							$v2['juli'] = '距离'.getdistance($detail['longitude'],$detail['latitude'],$v2['longitude'],$v2['latitude'],2).'千米';
						}else{
							$v2['juli'] = '';
						}
						$storelist[$k2] = $v2;
					}
				}
			}else{
				$storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('id,name,address,longitude,latitude')->find();
			}
		}

        $sysset = Db::name('admin_set')->where('aid',aid)->field('name,logo,desc,fxjiesuantype,tel,kfurl,gzts,ddbb')->find();
        if($detail['bid']>0){
            $business = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->field('id,name,logo,desc,tel,address,sales,kfurl')->find();
        }else{
            $business = $sysset;
        }
        $detail['binfo'] = $business;
        $prolist = Db::name('scoreshop_order_goods')->where('orderid',$detail['id'])->select()->toArray();
        foreach($prolist as $k=>$v){
            $prolist[$k]['score_price'] = dd_money_format($v['score_price'],$score_weishu);
        }
		$scoreshopset = Db::name('scoreshop_sysset')->where('aid',aid)->field('comment,autoclose')->find();
		if($detail['status']==0 && $scoreshopset['autoclose'] > 0){
			$lefttime = strtotime($detail['createtime']) + $scoreshopset['autoclose']*60 - time();
			if($lefttime < 0) $lefttime = 0;
		}else{
			$lefttime = 0;
		}

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['detail'] = $detail;
		$rdata['prolist'] = $prolist;
		$rdata['scoreshopset'] = $scoreshopset;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['storelist'] = $storelist;
		$rdata['lefttime'] = $lefttime;
		return $this->json($rdata);
	}
	public function logistics(){
		$get = input('param.');
		$list = \app\commons\Common::getwuliu($get['express_no'],$get['logistics'], '', aid);

		$rdata = [];
		$rdata['express_no'] = $get['express_no'];
		$rdata['logistics'] = $get['logistics'];
		$rdata['datalist'] = $list;
		return $this->json($rdata);
	}
	
	public function closeOrder(){
        $this->checklogin();
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('scoreshop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || $order['status']!=0){
			return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		//加库存
		$oglist = Db::name('scoreshop_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
		foreach($oglist as $og){
			Db::name('scoreshop_product')->where('aid',aid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
		}

		$rs = Db::name('scoreshop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);
		Db::name('scoreshop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	public function delOrder(){
        $this->checklogin();
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('scoreshop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || ($order['status']!=4 && $order['status']!=3)){
			return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
		}
		if($order['status']==3){
			$rs = Db::name('scoreshop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['delete'=>1]);
		}else{
			$rs = Db::name('scoreshop_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->delete();
			$rs = Db::name('scoreshop_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('mid',mid)->delete();
		}
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}
	public function orderCollect(){ //确认收货
        $this->checklogin();
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('scoreshop_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
		if(!$order || ($order['status']!=2) || $order['paytypeid']==4){
			return $this->json(['status'=>0,'msg'=>'订单状态不符合收货要求']);
		}
        $rs = \app\commons\Order::collect($order,'scoreshop');
        if($rs['status'] == 0) return $this->json($rs);
		
		Db::name('scoreshop_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
		Db::name('scoreshop_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);
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
		\app\commons\Wechat::sendhttmpl(aid,0,'tmpl_ordershouhuo',$tmplcontent,m_url('admin/order/scoreshoporder'),$order['mdid'],$tmplcontentNew);

		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['character_string6'] = $order['ordernum'];
		$tmplcontent['thing3'] = $this->member['nickname'];
		$tmplcontent['date5'] = date('Y-m-d H:i');
		\app\commons\Wechat::sendhtwxtmpl(aid,0,'tmpl_ordershouhuo',$tmplcontent,'admin/order/scoreshoporder',$order['mdid']);

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
	public function refund(){//申请退款
        $this->checklogin();
		if(request()->isPost()){
			$post = input('post.');
			$orderid = intval($post['orderid']);
			$money = floatval($post['money']);
            $score = intval($post['score']);
			$order = Db::name('scoreshop_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
			if(!$order || ($order['status']!=1 && $order['status'] != 2) || $order['refund_status'] == 2){
				return $this->json(['status'=>0,'msg'=>'订单状态不符合退款要求']);
			}
            //金额可为0
            if($money < 0 || $money > $order['totalprice']){
                return $this->json(['status'=>0,'msg'=>'退款金额有误']);
            }
            //积分可为0
            if($score <0 || $score > $order['totalscore']){
                return $this->json(['status'=>0,'msg'=>'退回'.t('积分').'有误']);
            }
            Db::name('scoreshop_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['refund_time'=>time(),'refund_status'=>1,'refund_reason'=>$post['reason'],'refund_money'=>$money]);

            $tmplcontent = [];
			$tmplcontent['first'] = '有订单客户申请退款';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $order['ordernum'];
			$tmplcontent['keyword2'] = $money.'元';
			$tmplcontent['keyword3'] = $post['reason'];
            $tmplcontentNew = [];
            $tmplcontentNew['number2'] = $order['ordernum'];//订单号
            $tmplcontentNew['amount4'] = $money;//退款金额
			\app\commons\Wechat::sendhttmpl(aid,0,'tmpl_ordertui',$tmplcontent,m_url('admin/order/scoreshoporder'),$order['mdid'],$tmplcontentNew);
			
			$tmplcontent = [];
			$tmplcontent['thing1'] = $order['title'];
			$tmplcontent['character_string4'] = $order['ordernum'];
			$tmplcontent['amount2'] = $order['totalprice'];
			$tmplcontent['amount9'] = $money.'元';
			$tmplcontent['thing10'] = $post['reason'];
			\app\commons\Wechat::sendhtwxtmpl(aid,0,'tmpl_ordertui',$tmplcontent,'admin/order/scoreshoporder',$order['mdid']);

			return $this->json(['status'=>1,'msg'=>'提交成功,请等待商家审核']);
		}
		$orderid = input('param.orderid/d');
		$price = input('param.price/f');
		$order = Db::name('scoreshop_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
		$price = $order['totalprice'];
		$this->assign('orderid',$orderid);
		$this->assign('price',$price);
		return $this->fetch();
	}
	//评价商品
	public function comment(){
        $this->checklogin();
		$ogid = input('param.ogid/d');
		$og = Db::name('scoreshop_order_goods')->where('id',$ogid)->where('mid',mid)->find();
		if(!$og){
			return $this->json(['status'=>0,'msg'=>'未查找到相关记录']);
		}
		$comment = Db::name('shop_comment')->where('ogid',$ogid)->where('aid',aid)->where('mid',mid)->find();
		if(request()->isPost()){
			$scoreshopset = Db::name('scoreshop_sysset')->where('aid',aid)->find();
			if($scoreshopset['comment']==0) return $this->json(['status'=>0,'msg'=>'评价功能未开启']);
			if($comment){
				return $this->json(['status'=>0,'msg'=>'您已经评价过了']);
			}
			$order_good = Db::name('scoreshop_order_goods')->where('aid',aid)->where('mid',mid)->where('id',$ogid)->find();
			$order = Db::name('scoreshop_order')->where('id',$order_good['orderid'])->find();
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
			$data['status'] = ($scoreshopset['comment_check']==1 ? 0 : 1);
			Db::name('scoreshop_comment')->insert($data);
			Db::name('scoreshop_order_goods')->where('aid',aid)->where('mid',mid)->where('id',$ogid)->update(['iscomment'=>1]);
			//Db::name('scoreshop_order')->where('id',$order['id'])->update(['iscomment'=>1]);
			
			//如果不需要审核 增加产品评论数及评分
			if($scoreshopset['comment_check']==0){
				$countnum = Db::name('scoreshop_comment')->where('proid',$order_good['proid'])->where('status',1)->count();
				$score = Db::name('scoreshop_comment')->where('proid',$order_good['proid'])->where('status',1)->avg('score');
				Db::name('scoreshop_product')->where('id',$order_good['proid'])->update(['comment_num'=>$countnum,'comment_score'=>$score]);
			}
			return $this->json(['status'=>1,'msg'=>'评价成功']);
		}
		$rdata = [];
		$rdata['og'] = $og;
		$rdata['comment'] = $comment;
		return $this->json($rdata);
	}
	
	//商品海报
	public function getposter(){
		$this->checklogin();
		$post = input('post.');
		$platform = platform;
		$page = '/activity/scoreshop/product';
		$scene = 'id_'.$post['proid'].'-pid_'.$this->member['id'];
		//if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
		//	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
		//}
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','scoreshop')->where('platform',$platform)->order('id')->find();

		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','scoreshop')->where('posterid',$posterset['id'])->find();
		if(true || !$posterdata){
			$product = Db::name('scoreshop_product')->where('id',$post['proid'])->find();
			$sysset = Db::name('admin_set')->where('aid',aid)->find();
			$textReplaceArr = [
				'[头像]'=>$this->member['headimg'],
				'[昵称]'=>$this->member['nickname'],
				'[姓名]'=>$this->member['realname'],
				'[手机号]'=>$this->member['mobile'],
				'[商城名称]'=>$sysset['name'],
				'[商品名称]'=>$product['name'],
				'[商品销售价]'=>$product['score_price'].'积分'.($product['money_price']>0?'+'.$product['money_price'].'元':''),
				'[商品市场价]'=>$product['sell_price'],
				'[商品图片]'=>$product['pic'],
			];

			$poster = $this->_getposter(aid,$product['bid'],$platform,$posterset['content'],$page,$scene,$textReplaceArr);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $this->member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['type'] = 'scoreshop';
			$posterdata['poster'] = $poster;
			$posterdata['createtime'] = time();
			Db::name('member_poster')->insert($posterdata);
		}
		return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
	}

    //分类商品样式
    public function classify2(){
        }
}