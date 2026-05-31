<?php


namespace app\controllers;
use think\facade\Db;
class ApiKecheng extends ApiCommon{

	public function formatproduct($product){
        $product['price_origin'] = $product['price'];
		if(!$this->member) return $product;
		if($product['lvprice']==1){
            $lvprice_data = json_decode($product['lvprice_data'],true);
            $product['price'] = $lvprice_data[$this->member['levelid']]["money_price"];
		}else{
			$memberlevel = Db::name('member_level')->where('id',$this->member['levelid'])->find();
			if(!$memberlevel) return $product;
			if($memberlevel['kecheng_discount'] > 0 && $memberlevel['kecheng_discount'] < 10){
				$product['market_price'] = $product['price'];
				$product['price'] = $product['price'] * $memberlevel['kecheng_discount'] * 0.1;
			}
		}
		if(getcustom('kecheng_free_memberlevel')){
			if($product['mianfei_memberlevel_open'] == 1){
				$is_price = 1;
				$gettj = explode(',',$product['mianfei_gettj']);
				if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
					if(in_array('0',$gettj)){ //关注用户才能领
						if($this->member['subscribe']!=1){
							$is_price = 0;
						}
					}else{
						$is_price = 0;
					}
				}
				if($is_price ==1){
					$product['price'] = 0;
					if(isset($product['market_price'])){
						$product['market_price'] = 0;
					}							
				}
			}
			
		}
		return $product;
	}
	public function formatprolist($datalist){
		if($this->member){
            //$kechengset = Db::name('kecheng_sysset')->where('aid',aid)->find();
			$memberlevel = Db::name('member_level')->where('id',$this->member['levelid'])->find();
			foreach($datalist as $k=>$v){
				if($v['lvprice']==1){
					 $lvprice_data = json_decode($v['lvprice_data'],true);
					 $datalist[$k]['price'] = $lvprice_data[$this->member['levelid']]["money_price"];
				}else{
					if($memberlevel['kecheng_discount'] > 0 && $memberlevel['kecheng_discount'] < 10){
						$datalist[$k]['market_price'] = $v['price'];
						$datalist[$k]['price'] = $v['price'] * $memberlevel['kecheng_discount'] * 0.1;
					}
				}
//                if($kechengset['show_buyed_kecheng_price'] == 0){
//                    $datalist[$k]['price'] = '已购买';
//                }
				if(getcustom('kecheng_free_memberlevel')){
					if($v['mianfei_memberlevel_open'] == 1){
						$is_price = 1;
						$gettj = explode(',',$v['mianfei_gettj']);
						if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
							if(in_array('0',$gettj)){ //关注用户才能领
								if($this->member['subscribe']!=1){
									$is_price = 0;
								}
							}else{
								$is_price = 0;
							}
						}
						if($is_price == 1){
							$datalist[$k]['price'] = 0;
							if(isset($datalist[$k]['market_price'])){
								$datalist[$k]['market_price'] = 0;
							}							
						}
					}
					
				}
			}
		}
		return $datalist;
	}

	public function getlist(){
		$where = [];
		$where[] = ['aid','=',aid];
		//$where[] = ['status','=',1];
		$where[] = Db::raw("`status`=1");
		
		$bid = input('param.bid') ? input('param.bid') : 0;
		if(input('param.bid')){
			$where[] = ['bid','=',input('param.bid/d')];
		}elseif(getcustom('kecheng_discount')){
		
		}else{
			$where[] = ['bid','=',0];
		}
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order').',sort,id desc';
		}else{
			$order = 'sort desc,id desc';
		}
		//分类 
		if(input('param.cid')){
			$cid = input('post.cid') ? input('post.cid/d') : input('param.cid/d');
			//子分类
			$clist = Db::name('kecheng_category')->where('aid',aid)->where('pid',$cid)->column('id');
			if($clist){
                $bid = Db::name('kecheng_category')->where('aid',aid)->where('pid',$cid)->value('bid');
                $where[2] = ['bid','=',$bid];
                $clist2 = Db::name('kecheng_category')->where('aid',aid)->where('pid','in',$clist)->column('id');
				$cCate = array_merge($clist, $clist2, [$cid]);
				if($cCate){
					$whereCid = [];
					foreach($cCate as $k => $c2){
						$whereCid[] = "find_in_set({$c2},cid) or find_in_set({$c2},pcid)";
					}
                    $where[] = Db::raw(implode(' or ',$whereCid));
				}
			} else {
                $where[] = Db::raw("find_in_set(".$cid.",cid) or find_in_set(".$cid.",pcid)");
            }
		}
		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}
		if(getcustom('kecheng_lecturer')){
			if(input('param.chaptertype')){
				$where[] = ['chaptertype','=',input('param.chaptertype/d')];
			}
        }
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$field = "id,pic,name,price,join_num,kctype,lvprice,lvprice_data";
		if(getcustom('kecheng_lecturer')){
			$field .= ",chaptertype";
		}
		$datalist = Db::name('kecheng_list')->field($field)->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		foreach($datalist as &$d){
			//查看共有几节课
			$d['count'] = 0+Db::name('kecheng_chapter')->where(['aid'=>aid,'kcid'=>$d['id']])->count();
		}
		if(!$datalist) $datalist = [];
		$datalist = $this->formatprolist($datalist);
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
    public function getmululist(){
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = Db::raw("`status`=1");
		$order = 'sort desc,id desc';
		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}
		$where[] = ['kcid','=',input('param.id')];
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$datalist = Db::name('kecheng_chapter')->field("id,pic,name,kctype,ismianfei,kcid,video_duration,video_url")->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
		if(!$datalist) $datalist = [];
		//$datalist = $this->formatprolist($datalist);
		//查看是否已经学完
		foreach($datalist as $k=>$d){
			if($d['video_duration']>0){
				$datalist[$k]['duration'] = $this->changeTimeType($d['video_duration']);
			}

			$study = Db::name('kecheng_studylog')->field('id,status,currentTime,jindu')->where('aid',aid)->where('mid',mid)->where('mlid',$d['id'])->find();
			if($study){
				$datalist[$k]['currentTime'] = $study['currentTime'];
				$datalist[$k]['jindu'] = $study['jindu'];
				if($study['status']==1)	$datalist[$k]['jindu'] = '100';
			}

			$datalist[$k]['key'] = $k;

			if(getcustom('kecheng_order_learn')){

                //study_status 学习状态 -1：未学习 0：未学完 1：已学完
                $datalist[$k]['study_status'] = 0;
                if($study){
                    $datalist[$k]['study_status'] = $study['status'] == 1?1:0;
                }

                //record_status 答题状态 0：未合格 1：已合格
                $datalist[$k]['record_status'] = 0;
                $countrecord = Db::name('kecheng_record')->where(['mlid'=>$d['id'],'kcid'=>$d['kcid'],'aid'=>aid,'mid'=>mid,'ishg'=>1])->count('id');
                if($countrecord){
                    $datalist[$k]['record_status'] = 1;
                }

                //tiku_status 答题状态 0：没有题库 1：有题库
                $datalist[$k]['tiku_status'] = 0;
                $counttiku = Db::name('kecheng_tiku')->field('id,title,type,option_group,right_option')->where('mlid',$d['id'])->where('kcid',$d['kcid'])->where('status',1)->where('aid',aid)->count('id');
                if($counttiku){
                    $datalist[$k]['tiku_status'] = 1;
                }

            }
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function list(){
		$bid = input('param.bid') ? input('param.bid') : 0;
		//分类
		if(input('param.cid')){
			$cid = input('param.cid/d');
			//查询分类
			$cids = [$cid];
			//查询子分类
			$childs = Db::name('kecheng_category')->where('aid',aid)->where('pid',$cid)->where('status',1)->column('id');
			if($childs){
				$cids = array_merge($cids,$childs);
			}
			$clist = Db::name('kecheng_category')->where('id','in',$cids)->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}else{
			$clist = Db::name('kecheng_category')->where('aid',aid)->where('bid',$bid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$clist) $clist = [];
		}
        $kechengset = Db::name('kecheng_sysset')->field('show_join_num')->where('aid',aid)->find();
		return $this->json(['clist'=>$clist, 'sysset'=>$kechengset]);
	}
	//三级分类 平台的为一二级 商家的为三级
	public function category3(){
		$list = Db::name('kecheng_category')->where('aid',aid)->where('bid',0)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
		$rdata = [];
		$rdata['data'] = $list;
		return $this->json($rdata);
	}


	//获取二三分类
	public function getdownclist3(){
		$pid = input('param.id/d');
		$clist = Db::name('kecheng_category')->where('aid',aid)->where('pid',$pid)->where('status',1)->order('sort desc,id')->select()->toArray();
		if(!$clist) $clist = [];
		foreach($clist as $k=>$v){
			$rs = Db::name('kecheng_category')->where('aid',aid)->where('bid','>',0)->where('pid',0)->where('pcid',$v['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
			if(!$rs) $rs = array();
			$clist[$k]['child'] = $rs;
		}
		return $this->json(['status'=>1,'data'=>$clist]);
	}

    //获取分类
    public function newCategory3(){
        $bid = input('param.bid/d');
        $list = Db::name('kecheng_category')->where('aid',aid)->where('bid',$bid)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray();
        $rdata = [];
        $rdata['data'] = $list;
        return $this->json($rdata);
    }

    //获取分类下的课程
    public function getClassifyKecheng()
    {
		$id = input('param.id/d');
		$bid = input('param.bid/d');
        $lastId = input('param.last_id/d');
        $lastClassifyId = input('param.last_classify_id/d');
        $pageSize = 10;

        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['bid','=',$bid];
        $where[] = ['status','=',1];

		$clist = Db::name('kecheng_category')
            ->where($where)
            ->where(function ($query) use ($id) {
                $query->where('pid',$id)->whereOr('id', $id);
            })
            ->order('sort desc,id')
            ->column('id,pid,name,pic,sort,createtime','id');

        if($lastClassifyId){
            // 确定最后一个分类的索引位置
            $key = array_search($lastClassifyId,array_keys($clist));
            $clist = array_slice($clist,$key);
        }

        $resultData = [];
        $totalCount = 0;

        foreach($clist as $k=>$v){
            $whereID = [];
            if($lastId && $v['id'] == $lastClassifyId){
                $whereID[] = ['id','>',$lastId];
            }

            // 根据分类查询课程
            $rs = Db::name('kecheng_list')
                ->where($where)
                ->where($whereID)
                ->where('cid',$v['id'])
                ->order('sort desc,id')
                ->limit($pageSize)
                ->select()
                ->toArray();

            if ($rs) {
                // 计算剩余的空位数量
                $remaining = $pageSize - $totalCount;
                // 如果剩余的空位数量大于等于当前分类的数据数量，则直接将当前分类的数据添加到结果数组中
                $reCount = count($rs);
                if ($remaining >= $reCount) {
                    $v['child'] = $rs;
                    $resultData[] = $v;
                    $totalCount += $reCount;
                } else {
                    // 如果剩余的空位数量小于当前分类的数据数量，则需要将当前分类的数据部分添加到结果数组中，并更新 last_id 值
                    $v['child'] = array_slice($rs, 0, $remaining);
                    $resultData[] = $v;
                    $totalCount+= $remaining;
                    break;
                }
            }
            // 如果当前分类的数据不足以填满一页，则不需要继续查询下一个分类
            if ($totalCount >= $pageSize) {
                break;
            }
		}
		return $this->json(['status'=>1,'data'=>$resultData,'page_size'=>$pageSize,'total' => $totalCount]);
	}

	//课程详情
	public function detail(){
		$proid = input('param.id/d');
		$product = Db::name('kecheng_list')->where('id',$proid)->where('aid',aid)->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'课程不存在']);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'课程未上架']);

		if(!$product['pics']) $product['pics'] = $product['pic'];
		$product['pics'] = explode(',',$product['pics']);
		$product = $this->formatproduct($product);

		//是否收藏
		if($this->member) $rs = Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','kecheng')->find();
		if(isset($rs) && $rs){
			$isfavorite = true;
		}else{
			$isfavorite = false;
		}
		//查看共有几节课
		$count = 0 + Db::name('kecheng_chapter')->where(['aid'=>aid,'kcid'=>$proid])->count();

		//添加浏览历史
		$sysset = Db::name('admin_set')->where('aid',aid)->field('name,logo,desc,fxjiesuantype,tel,kfurl,gzts,ddbb')->find();

		if($product['bid']!=0){
			$business = Db::name('business')->where('aid',aid)->where('id',$product['bid'])->field('id,name,logo,desc,tel,address,sales,kfurl')->find();
		}else{
			$business = $sysset;
		}
		$product['detail'] = \app\commons\System::initpagecontent($product['detail'],aid,mid,platform);
        if(getcustom('form_jingmo_auth')){
            $pagecontent = json_decode($product['detail'],true);
            if(platform == 'wx' || platform == 'mp'){
                if(!$this->member) {
                    foreach ($pagecontent as $k => $v) {
                        if ($v['temp'] == 'form') {
                            //is_jingmo 静默登录注册 1:开启 0：关闭
                            if (isset($v['params']['is_jingmo']) && $v['params']['is_jingmo'] == 1) {
                                return $this->json(['status' => -1, 'msg' => '请先登录', 'authlogin' => 2], 1);
                            }
                        }
                    }
                }
            }
        }

        $kechengset = Db::name('kecheng_sysset')->where('aid',aid)->find();
		if(!$kechengset) $kechengset = [];

		if($kechengset['details_rec']){
			$tjwhere = [];
			$tjwhere[] = ['aid','=',aid];
			$tjwhere[] = ['status','=',1];

			if($product['bid']){
				$tjwhere[] = ['bid','=',$product['bid']];
			}else{
				$business_sysset = Db::name('business_sysset')->where('aid',aid)->find();
				if(!$business_sysset || $business_sysset['status']==0 || $business_sysset['product_isshow']==0){
					$tjwhere[] = ['bid','=',0];
				}
			}
			
			$tjdatalist = Db::name('kecheng_list')->where($tjwhere)->limit(6)->order(Db::raw('rand()'))->select()->toArray();
			if(!$tjdatalist) $tjdatalist = array();
			$tjdatalist = $this->formatprolist($tjdatalist);
			foreach($tjdatalist as &$t){
				$t['count'] = 0 + Db::name('kecheng_chapter')->where(['aid'=>aid,'kcid'=>$t['id']])->count();
			}
		}else{
			$tjdatalist = [];
		}
     
        if($this->member){
			$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		}
        //升级到下一等级预计节省多少钱
		if($kechengset['show_lvupsavemoney'] == 1 && $this->member){
			$upsavemoney = 0;
			$nextlevel = Db::name('member_level')->where('aid',aid)->where('sort','>',$userlevel['sort'])->order('sort,id')->find();
			if($nextlevel){
				$sell_price = $product['price'];
				if($product['lvprice']==0 && $product['no_discount'] == 0 && $userlevel['discount'] > 0 && $userlevel['discount'] < 10){
					$this_sell_price = $sell_price * $userlevel['discount'] * 0.1;
				}else{
					if($product['lvprice']==1){
						$lvprice_data = json_decode($product['lvprice_data'],true);
						if($lvprice_data && isset($lvprice_data[$this->member['levelid']])){
							$this_sell_price = $lvprice_data[$this->member['levelid']];
						}
					}else{
						$this_sell_price = $sell_price;
					}
				}
				if($product['lvprice']==0 && $product['no_discount'] == 0 && $nextlevel['discount'] > 0 && $nextlevel['discount'] < 10){
					$next_sell_price = $sell_price * $nextlevel['discount'] * 0.1;
				}else{
					if($product['lvprice']==1){
						$lvprice_data = json_decode($product['lvprice_data'],true);
						if($lvprice_data && isset($lvprice_data[$nextlevel['id']])){
							$next_sell_price = $lvprice_data[$nextlevel['id']];
						}
					}else{
						$next_sell_price = $sell_price;
					}
				}
				if($this_sell_price > $next_sell_price){
					$upsavemoney = round($this_sell_price["money_price"] - $next_sell_price["money_price"],2);
				}
			}
			$product['upsavemoney'] = $upsavemoney;
			$product['nextlevelname'] = $nextlevel ? $nextlevel['name'] : '';
            //替换升级文字
            $textReplaceArr = [
				'[等级名称]'=>$product['nextlevelname'],
				'[优惠金额]'=>$product['upsavemoney'],
			];
            $upgrade_text = $kechengset['upgrade_text'];
            foreach($textReplaceArr as $key=>$v){
               if(strpos($upgrade_text,$key)){
                   $upgrade_text = str_replace($key,$v,$upgrade_text);
               }
		    } 
            
            $product['upgrade_text'] = $upgrade_text;
		}else{
			$product['upsavemoney'] = 0;
		}

        $sysset = Db::name('admin_set')->where('aid',aid)->field('name,logo,desc,fxjiesuantype,tel,kfurl,gzts,ddbb')->find();
        //预计佣金
		$commission = 0;
        $product['commission_desc'] = '元';
		if($this->member && $kechengset['showcommission']==1){
			if($userlevel['can_agent']!=0){
				if($product['commissionset']==1){//按比例
					$commissiondata = json_decode($product['commissiondata1'],true);
					if($commissiondata){
						$commission = $commissiondata[$userlevel['id']]['commission1'] * ($product['price_origin'] - ($sysset['fxjiesuantype']==2 ? 0 : 0)) * 0.01;
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
				    //fxjiesuantype 0按商品价格,1按成交价格,2按销售利润
					if($userlevel['commissiontype']==1){ //固定金额按单
						$commission = $userlevel['commission1'];
					}else{
						$commission = $userlevel['commission1'] * ($product['price_origin'] - ($sysset['fxjiesuantype']==2 ? 0 : 0)) * 0.01;
					}
				}
				if($product['commissionset4']==1 && $product['lvprice']==1){ //极差分销
					$lvprice_data = json_decode($product['lvprice_data'],true);
					$commission += array_shift($lvprice_data) - $product['price'];
					if($commission < 0) $commission = 0;
				}
			}
		}
		$product['commission'] = round($commission*100)/100;
		$sysset['showgzts'] = false;
        $sysset['show_join_num'] = $kechengset['show_join_num'];
		//关注提示
		if(platform == 'mp'){
			$sysset['gzts'] = explode(',',$sysset['gzts']);
			if(in_array('2',$sysset['gzts']) && $this->member['subscribe']==0){
				$appinfo = \app\commons\System::appinfo(aid,'mp');
				$sysset['qrcode'] = $appinfo['qrcode'];
				$sysset['gzhname'] = $appinfo['nickname'];
				$sysset['showgzts'] = true;
			}
		}
		//查看是否购买
        if(mid)
		    $order =  Db::name('kecheng_order')->where('aid',aid)->where('kcid',$proid)->where('mid',mid)->where('status',1)->order('id','desc')->find();
		$product['ispay'] = 0;
		if($order && $order['status']==1){
            //如果当时会员免费，现在的会员等级收费，依然需要付费
            if($order['totalprice'] == 0 && $product['price'] > 0){
                $product['ispay'] = 0;
            }else{
                $product['ispay'] = 1;
            }
		}
		if(getcustom('kecheng_lecturer')){
            if(mid && $product['lecturerid']){
            	//查看关联的讲师，是则不需要购买
            	$lecturer = Db::name('kecheng_lecturer')->where('id',$product['lecturerid'])->field('id,mid,checkstatus,status')->find();
                if($lecturer && $lecturer['mid'] == mid){
                    $product['ispay'] = 1;
                }
            }
		}
		$product['count'] = $count;
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['title'] = $product['name'];
		$rdata['sysset'] = $sysset;
		$rdata['isfavorite'] = $isfavorite;
		$rdata['product'] = $product;
		$rdata['business'] = $business;
		$rdata['tjdatalist'] = $tjdatalist;

        $ios_canbuy = $kechengset['ios_canbuy']?true:false;
        $ios_tip    = 'iOS端不支持虚拟商品销售';

		$kechengset['ios_tip']    = $ios_tip;
		$kechengset['ios_canbuy'] = $ios_canbuy;
		$rdata['kechengset'] = $kechengset;
		return $this->json($rdata);
	}
	//课程章节详情
	public function mldetail(){
		$this->checklogin();
		$proid = input('param.id/d');
		$kcid = input('param.kcid/d');
		$kc =  Db::name('kecheng_list')->where('id',$kcid)->find();
		if(!$kc){
			return $this->json(['status'=>0,'msg'=>'课程不存在']);
		}
		$kc = $this->formatproduct($kc);

		//所有章节
        $chapters = Db::name('kecheng_chapter')->where('kcid',$kcid)->where('status',1)->where('aid',aid)->order('sort desc,id desc')->field('id')->select()->toArray();
        if(!$chapters){
			return $this->json(['status'=>0,'msg'=>'课程章节不存在']);
		}
		//查看我的课程里面有没有
		$order = Db::name('kecheng_order')->where('kcid',$kcid)->where('aid',aid)->where('mid',mid)->where('status',1)->order('id','desc')->find();
		if(!$proid){
			//章节id不存在，看studylog 有没有
			$studylog = Db::name('kecheng_studylog')->where('aid',aid)->where('mid',mid)->where('kcid',$kcid)->order('id desc')->find();
			if($studylog){
				if($studylog['status'] == 0){
					$detail = Db::name('kecheng_chapter')->where('id',$studylog['mlid'])->where('aid',aid)->find();
				}else if($studylog['status'] == 1){
                    //查看有没有已经学完的
                    $mlid = 0;
                    if(getcustom('kecheng_order_learn')){
                        //是否开启了答题合格后学习下一章
                        if($kc && $kc['learnhg'] == 1){
                            //查看是否有题库
                            $counttiku = Db::name('kecheng_tiku')->field('id,title,type,option_group,right_option')->where('mlid',$studylog['mlid'])->where('kcid',$kcid)->where('status',1)->where('aid',aid)->count('id');
                            if($counttiku){
                                //查看是否已经考完并合格
                                $count = Db::name('kecheng_record')->where(['mlid'=>$studylog['mlid'],'kcid'=>$kcid,'aid'=>aid,'mid'=>mid,'ishg'=>1])->count('id');
                                if(!$count){
                                    $mlid = $studylog['mlid'];
                                }
                            }
                        }
                    }
                    if(!$mlid){
                        //查看下一章
                        $chapternum = count($chapters);
                        $i       = -1;
                        $nextkey = -1;
                        foreach($chapters as $cv){
                            $i++;
                            if($cv['id'] == $studylog['mlid']){
                                $nextkey  = $i+1;
                                if($nextkey>$chapternum){
                                    $nextkey = -1;
                                }
                                break;
                            }
                        };
                        if($nextkey == -1){
                            $nextid = $studylog['mlid'];
                        }else{
                            $nextid = $chapters[$nextkey]['id'];
                        }
                        $detail = Db::name('kecheng_chapter')->where('id',$nextid)->find();
                    }else{
                        $detail = Db::name('kecheng_chapter')->where('id',$mlid)->where('aid',aid)->find();
                    }
                }else{
					return $this->json(['status'=>0,'msg'=>'课程章节记录出错']);
				}
			}else{
	            $detail = Db::name('kecheng_chapter')->where('kcid',$kcid)->where('aid',aid)->where('status',1)->order('sort desc,id desc')->find();
	            $this->addstudys($kcid,$detail);
				//没有订单的课程，学习增加销量
            	\app\models\Payorder::addSales(0,'kecheng',aid,$detail['bid'],1);
            	$studylog = Db::name('kecheng_studylog')->where('aid',aid)->where('mid',mid)->where('kcid',$kcid)->where('mlid',$detail['id'])->order('id desc')->find();
			}
			if(!$detail) return $this->json(['status'=>-4,'msg'=>'暂无章节']);
			if($kc['price']==0 && !$order ){
				$ordernum = date('ymdHis').rand(100000,999999);
				$data1 = [];
				$data1['aid'] = aid;
				$data1['mid'] = mid;
				$data1['bid'] = $kc['bid'];
				$data1['pic'] = $kc['pic'];
				$data1['createtime'] = time();
				$data1['ordernum'] = $ordernum;
				$data1['platform'] = platform;
				$data1['title'] = $kc['name'];
				$data1['kcid'] = $kcid;
				$data1['totalprice'] = $kc['price'];
				$data1['price'] = $kc['price'];
				$data1['status'] = 1;
				//增加学习记录
				$order = Db::name('kecheng_order')->insertGetId($data1);
				if($order) Db::name('kecheng_list')->where('aid',aid)->where('id',$kcid)->inc('join_num')->update();
			}elseif($kc['price']>0 && !$order){
				 return $this->json(['status'=>-4,'msg'=>'请先购买课程','url'=>'/activity/kecheng/product?id='.$kcid]);
			}elseif ($kc['price']>0 && $order['totalprice'] == 0){
                //如果当时会员免费，现在的会员等级收费，依然需要付费
                return $this->json(['status'=>-4,'msg'=>'请先购买课程','url'=>'/activity/kecheng/product?id='.$kcid]);
            }

		}else{
			//查询正在查看的章节信息
			$detail = Db::name('kecheng_chapter')->where('id',$proid)->where('aid',aid)->where('status',1)->find();
			if(!$detail) return $this->json(['status'=>-4,'msg'=>'章节不存在']);
			if(getcustom('kecheng_order_learn')){
                if($kc){
                    //是否开启了按顺序学习
                    if($kc['orderlearn'] == 1){
                        //查看大于的他上一章，有没有学完
                        $preid = 0;
                        foreach($chapters as $cv){
                            if($cv['id'] == $proid){
                                break;
                            }else{
                                $preid = $cv['id'];
                            }
                        };
                        if($preid){
                            $log = Db::name('kecheng_studylog')->where('mid',mid)->where('mlid',$preid)->where('kcid',$kcid)->where('status',1)->where('aid',aid)->order('id desc')->find();
                            if(!$log || $log['status'] !=1){
                                return $this->json(['status'=>0,'msg'=>'请按顺序从上往下学习']);
                            }
                            if($kc['learnhg'] == 1){
                                //查看是否有题库
                                $counttiku = Db::name('kecheng_tiku')->field('id,title,type,option_group,right_option')->where('mlid',$log['mlid'])->where('kcid',$kcid)->where('status',1)->where('aid',aid)->count('id');
                                if($counttiku){
                                    //查看是否已经考完并合格
                                    $record = Db::name('kecheng_record')->where(['mlid'=>$log['mlid'],'kcid'=>$kcid,'aid'=>aid,'mid'=>mid])->field('id,ishg')->order('id desc')->find();
                                    if(!$record){
                                        return $this->json(['status'=>0,'msg'=>'请先完成上一章的答题，考核合格后继续方可继续学习']);
                                    }
                                    if($record['ishg'] !=1){
                                        return $this->json(['status'=>0,'msg'=>'上一章的答题未合格，请考核合格后继续学习']);
                                    }
                                }
                            }
                        }
                    }else{
                        //答题合格后学习下一章
                        if($kc['learnhg'] == 1){
                            //查看学习完的上一章
                            $log = Db::name('kecheng_studylog')->where('mid',mid)->where('kcid',$kcid)->where('aid',aid)->order('id desc')->find();
                            if($log && $log['mlid'] != $proid){
                                //查看是否有题库
                                $counttiku = Db::name('kecheng_tiku')->field('id,title,type,option_group,right_option')->where('mlid',$log['mlid'])->where('kcid',$kcid)->where('status',1)->where('aid',aid)->count('id');
                                if($counttiku){
                                    //查看是否已经考完并合格
                                    $record = Db::name('kecheng_record')->where(['mlid'=>$log['mlid'],'kcid'=>$kcid,'aid'=>aid,'mid'=>mid])->field('id,ishg')->order('id desc')->find();
                                    if(!$record){
                                        return $this->json(['status'=>0,'msg'=>'请先完成上一章的答题，考核合格后继续方可继续学习']);
                                    }
                                    if($record['ishg'] !=1){
                                        return $this->json(['status'=>0,'msg'=>'上一章的答题未合格，请考核合格后继续学习']);
                                    }
                                }
                            }
                        }
                    }
                }
            }
			$studylog = Db::name('kecheng_studylog')->where('aid',aid)->where('mid',mid)->where('kcid',$kcid)->where('mlid',$proid)->order('id desc')->find();
            if(!$studylog){
				$this->addstudys($kcid,$detail);
			}
			$studylog = Db::name('kecheng_studylog')->where('aid',aid)->where('mid',mid)->where('kcid',$kcid)->where('mlid',$proid)->order('id desc')->find();
		}
        if(!$detail['ismianfei'] && $kc['price']>0 && !$order) return $this->json(['status'=>-4,'msg'=>'请先购买课程']);
		if($detail['status']==0) return $this->json(['status'=>-4,'msg'=>'章节未上架']);
        if(getcustom('video_qq_url')){
            $detail['video_url'] = \app\customs\VideoQQ::getMp4Url($detail['video_url']);
        }

		//获取上次播放时间
		if($detail['kctype']>1){
			$detail['startTime'] = $studylog['currentTime']?$studylog['currentTime']:0;
		}
		//查看是否购买
		$detail['ispay'] = 0;
		if($order && $order['status']==1){
			$detail['ispay'] = 1;
		}
		//根据课程查询是否已学完
		$detail['count'] =0 + Db::name('kecheng_studylog')->where(['kcid'=>$detail['kcid'],'status'=>1,'mid'=>mid,'aid'=>aid])->group('mlid')->count();
		//查看共有几节课
		$detail['kccount'] = 0+Db::name('kecheng_chapter')->where(['aid'=>aid,'kcid'=>$detail['kcid']])->count();
		// if($detail['sort']>0){
		// 	$detail['key'] = db('kecheng_chapter')->where('kcid='.$detail['kcid'].' and status=1 and sort>'.$detail['sort'])->order('sort desc,id desc')->count();
		// }else{
		// 	$detail['key'] = db('kecheng_chapter')->where('kcid='.$detail['kcid'].' and status=1 and id<'.$detail['id'])->order('sort desc,id desc')->count();
		// }
		$speedlist=[];
		$showspeed=false;
		if(getcustom('video_speed')){
			$speedlist = ['1'=>'0.8','2'=>'1.0','3'=>'1.5','4'=>'2'];
			if($detail['isspeed']==1){
				$showspeed=true;
			}
		}

		$detail['isdt'] = $kc['isdt'];
		$detail['price'] = $kc['price'];
		$detail['isstudy'] = $studylog['status'];
		$detail['detail'] = \app\commons\System::initpagecontent($detail['detail'],aid,mid,platform);
		Db::name('kecheng_list')->where('id',$detail['kcid'])->inc('readnum',1)->update();
        $detail['is_give_score'] = false;
		if(getcustom('kecheng_give_score')){
            $detail['is_give_score'] = true;
        }

        $detail['orderlearn'] = 0;
        $detail['learnhg']    = 0;
		if(getcustom('kecheng_order_learn')){
			$detail['orderlearn'] = $kc['orderlearn'];
			$detail['learnhg']    = $kc['learnhg'];
		}
        //查询学习的是当前第几个
    	$i = 0;
    	foreach($chapters as $cv){
    		if($cv['id'] == $studylog['mlid']){
    			break;
    		}
    		$i++;
    	};
    	$detail['learnkey'] = $i;

		$rdata = [];
		//查看是否已经考完试
		$record= Db::name('kecheng_record')->where(['aid'=>aid,'kcid'=>$detail['kcid'],'mid'=>mid,'ishg'=>1])->find();
		$rdata['iskaoshi'] = 0;
		$rdata['isauto']   = false;
		if(getcustom('kecheng_dati')) $rdata['isauto'] = true;
		if($record){
			$rdata['isauto']   = false;
			$rdata['iskaoshi'] = 1;
		}
		if(getcustom('kecheng_order_learn')){
			$rdata['isauto'] = false;
		}
		$rdata['speedlist'] = $speedlist;
		$rdata['showspeed'] = $showspeed;
		$rdata['status'] = 1;
    //重置免费时长
    if($detail['ismianfei'] == 1 && $detail['mianfei_unit'] == 2){
      $detail['mianfei_time'] = intval($detail['mianfei_time']*60);
    }
    if($detail['ispay'] == 1){
      $detail['mianfei_time'] = 0;
    }

		$rdata['detail'] = $detail;
		$rdata['studylog'] = $studylog;
		return $this->json($rdata);
	}

	//商品海报
	function getposter(){
		$this->checklogin();
		$post = input('post.');
		$platform = platform;
		$page = '/activity/kecheng/product';
		$scene = 'id_'.$post['proid'].'-pid_'.$this->member['id'];
		if(getcustom('kecheng_lecturer')){
			$type = input('?param.type')?input('param.type'):'';
			if($type == 'lecturer'){
				$page = '/pagesB/kecheng/lecturermldetail';
				$scene = 'id_'.$post['id'].'-kcid_'.$post['proid'].'-pid_'.$this->member['id'];
			}
		}
		//if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
		//	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
		//}
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','kecheng')->where('platform',$platform)->order('id')->find();

//		$posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','kecheng')->where('posterid',$posterset['id'])->find();
        //关闭缓存
        if(true || !$posterdata){
			$product = Db::name('kecheng_list')->where('id',$post['proid'])->find();
//			$product = $this->formatproduct($product);
			$sysset = Db::name('admin_set')->where('aid',aid)->find();
			$textReplaceArr = [
				'[头像]'=>$this->member['headimg'],
				'[昵称]'=>$this->member['nickname'],
				'[姓名]'=>$this->member['realname'],
				'[手机号]'=>$this->member['mobile'],
				'[商城名称]'=>$sysset['name'],
				'[课程名称]'=>$product['name'],
				'[课程销售价]'=>$product['price'],
				'[课程市场价]'=>$product['market_price'],
				'[商品图片]'=>$product['pic'],
			];

			$poster = $this->_getposter(aid,$product['bid'],$platform,$posterset['content'],$page,$scene,$textReplaceArr);
			$posterdata = [];
			$posterdata['aid'] = aid;
			$posterdata['mid'] = $this->member['id'];
			$posterdata['scene'] = $scene;
			$posterdata['page'] = $page;
			$posterdata['type'] = 'kecheng';
			$posterdata['poster'] = $poster;
            $posterdata['posterid'] = $posterset['id'];
			$posterdata['createtime'] = time();
//			Db::name('member_poster')->insert($posterdata);
		}
		return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
	}

	public function createOrder(){
		$this->checklogin();
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		$post = input('post.');
		$ordernum = date('ymdHis').rand(100000,999999);
		$kc =  Db::name('kecheng_list')->where('id',$post['kcid'])->find();
		$kc = $this->formatproduct($kc);
		$data = [];
		$data['aid'] = aid;
		$data['mid'] = mid;
		$data['bid'] = $kc['bid'];
		$data['pic'] = $kc['pic'];
		$data['createtime'] = time();
		$data['ordernum'] = $ordernum;
		$data['platform'] = platform;
		$data['title'] = $kc['name'];
		$data['kcid'] = $post['kcid'];
		$data['totalprice'] = $kc['price'];
		$data['price'] = $kc['price'];
		
		//计算佣金的商品金额
		$commission_totalprice = $data['totalprice'];
		$agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		if($agleveldata['can_agent'] > 0 && $agleveldata['commission1own']==1){
			$this->member['pid'] = mid;
		}
		if($kc['commissionset']!=-1){
			if($this->member['pid']){
				$parent1 = Db::name('member')->where('aid',aid)->where('id',$this->member['pid'])->find();
				if($parent1){
					$agleveldata1 = Db::name('member_level')->where('aid',aid)->where('id',$parent1['levelid'])->find();
					if($agleveldata1['can_agent']!=0){
						$data['parent1'] = $parent1['id'];
					}
				}
			}
			if($parent1['pid']){
				$parent2 = Db::name('member')->where('aid',aid)->where('id',$parent1['pid'])->find();
				if($parent2){
					$agleveldata2 = Db::name('member_level')->where('aid',aid)->where('id',$parent2['levelid'])->find();
					if($agleveldata2['can_agent']>1){
						$data['parent2'] = $parent2['id'];
					}
				}
			}
			if($parent2['pid']){
				$parent3 = Db::name('member')->where('aid',aid)->where('id',$parent2['pid'])->find();
				if($parent3){
					$agleveldata3 = Db::name('member_level')->where('aid',aid)->where('id',$parent3['levelid'])->find();
					if($agleveldata3['can_agent']>2){
						$data['parent3'] = $parent3['id'];
					}
				}
			}
			if($kc['commissionset']==1){//按商品设置的分销比例
				$commissiondata = json_decode($kc['commissiondata1'],true);
				if($commissiondata){
					if($agleveldata1) $data['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
					if($agleveldata2) $data['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
					if($agleveldata3) $data['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
				}
			}elseif($kc['commissionset']==2){//按固定金额
				$commissiondata = json_decode($kc['commissiondata2'],true);
				if($commissiondata){
					if($agleveldata1) $data['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'];
					if($agleveldata2) $data['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'];
					if($agleveldata3) $data['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'];
				}
			}elseif($kc['commissionset']==3){//提成是积分
				$commissiondata = json_decode($kc['commissiondata3'],true);
				if($commissiondata){
					if($agleveldata1) $data['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'];
					if($agleveldata2) $data['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'];
					if($agleveldata3) $data['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'];
				}
			}else{ //按会员等级设置的分销比例
				if($agleveldata1){
					if($agleveldata1['commissiontype']==1){ //固定金额按单
						$data['parent1commission'] = $agleveldata1['commission1'];	
					}else{
						$data['parent1commission'] = $agleveldata1['commission1'] * $commission_totalprice * 0.01;
					}
				}
				if($agleveldata2){
					if($agleveldata2['commissiontype']==1){
						$data['parent2commission'] = $agleveldata2['commission2'];				
					}else{
						$data['parent2commission'] = $agleveldata2['commission2'] * $commission_totalprice * 0.01;
					}
				}
				if($agleveldata3){
					if($agleveldata3['commissiontype']==1){
						$data['parent3commission'] = $agleveldata3['commission3'];
					}else{
						$data['parent3commission'] = $agleveldata3['commission3'] * $commission_totalprice * 0.01;
					}
				}
			}
		}	
		if(getcustom('fenhong_kecheng')){
			if($kc['fenhongset'] == 0){ //不参与分红
				$data['isfenhong'] = 2;
			}else{
				$data['isfenhong'] = 0;
			}
		}
		if(getcustom('kecheng_lecturer')){
			if($kc['lecturerid']){
				$data['lecturerid'] = $kc['lecturerid'];
			}
		}
		$orderid = Db::name('kecheng_order')->insertGetId($data);
		
		if($data['parent1'] && ($data['parent1commission'] || $data['parent1score'])){
			Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$data['parent1'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$kc['id'],'type'=>'kecheng','commission'=>$data['parent1commission'],'score'=>$data['parent1score'],'remark'=>'下级购买课程奖励','createtime'=>time()]);
		}
		if($data['parent2'] && ($data['parent2commission'] || $data['parent2score'])){
			Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$data['parent2'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$kc['id'],'type'=>'kecheng','commission'=>$data['parent2commission'],'score'=>$data['parent2score'],'remark'=>'下二级购买课程奖励','createtime'=>time()]);
		}
		if($data['parent3'] && ($data['parent3commission'] || $data['parent3score'])){
			Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$data['parent3'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$kc['id'],'type'=>'kecheng','commission'=>$data['parent3commission'],'score'=>$data['parent3score'],'remark'=>'下三级购买课程奖励','createtime'=>time()]);
		}

		$payorderid = \app\models\Payorder::createorder(aid,$data['bid'],$data['mid'],'kecheng',$orderid,$data['ordernum'],$data['title'],$data['totalprice']);

		return $this->json(['status'=>1,'payorderid'=>$payorderid,'msg'=>'提交成功']);

	}

	//订单列表
	function orderlist(){
		$this->checklogin();
		$st = input('param.st');
		$pagenum = input('param.pagenum') ? input('param.pagenum') : 1;
		$where = [];
		$where[] = ['aid','=',aid];

		$seetype = input('?param.seetype')?input('param.seetype'):'';
        if($seetype){
            if(getcustom('kecheng_lecturer')){
                //查询讲师订单
                if($seetype == 'lecturer'){
                    $lecturer = Db::name('kecheng_lecturer')->where('mid',mid)->where('aid',aid)->field('id')->find();
                    if($lecturer){
                        $where[] = ['lecturerid','=',$lecturer['id']];
                        $where[] = ['totalprice','>',0];
                    }else{
                        $where[] = ['id','=',0];
                    }
                }
            }
        }else{
            $where[] = ['mid','=',mid];
        }

		$where[] = ['status','=',1];
        
		if(input('param.keyword')){
			$where[] = ['title','like','%'.input('param.keyword').'%'];
		}
		$datalist = Db::name('kecheng_order')->where($where)->order('id desc')->page($pagenum,10)->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $key=>$v){
			if($v['bid']!=0){
				$datalist[$key]['binfo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->field('id,name,logo')->find();
			}
			//根据课程查询是否已学完
			$datalist[$key]['count'] =0 + Db::name('kecheng_studylog')->where(['kcid'=>$v['kcid'],'status'=>1,'mid'=>mid,'aid'=>aid])->group('mlid')->count();
			//查看共有几节课
			$datalist[$key]['kccount'] = 0+Db::name('kecheng_chapter')->where(['aid'=>aid,'kcid'=>$v['kcid']])->count();
			//echo $count;
			//查看是否已经考完试
			$record= Db::name('kecheng_record')->where(['aid'=>aid,'kcid'=>$v['kcid'],'mid'=>mid,'ishg'=>1])->find();
			if($record){
				$datalist[$key]['iskaoshi'] = 1;
			}

			$field = 'isdt';
			if(getcustom('kecheng_order_learn')){
				$field .= ',orderlearn,learnhg';
			}
			$list = Db::name('kecheng_list')->where(['aid'=>aid,'id'=>$v['kcid']])->field($field)->find();
			$datalist[$key]['isdt'] = $list && $list['isdt']?$list['isdt']:0;

			if(getcustom('kecheng_order_learn')){
				$datalist[$key]['orderlearn'] = $list && $list['orderlearn']?$list['orderlearn']:0;
				$datalist[$key]['learnhg']    = $list && $list['learnhg']?$list['learnhg']:0;
			}
			$datalist[$key]['createtime'] = date("Y-m-d H:i",$v['createtime']);
		}
		$rdata = [];
		$rdata['st'] = $st;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}
	//添加学习记录
	public function addstudy(){
		$this->checklogin();
		$post = input('post.');
		$data = [];
		$logid = $post['logid'];
		$study = Db::name('kecheng_studylog')->where('aid',aid)->where('mid',mid)->where('id',$logid)->find();
		$ml = Db::name('kecheng_chapter')->where('id',$study['mlid'])->find();
	
		$data['createtime'] = time();
		if($post['currentTime']){
			$data['currentTime'] = $post['currentTime'];
		}
		if($ml['kctype']==3 || $ml['kctype']==2){
			
			if($post['playJd'] && $study['status']!=1){
				$data['jindu'] = $post['playJd'];
			}elseif(!$post['playJd'] && $ml['video_duration']>0){
				$data['jindu'] = intval($data['currentTime']/$ml['video_duration']*100);
			}
			if($post['currentTime']>=$ml['video_duration'] || $study['status']==1){
				$data['status'] = 1;
				$data['jindu'] = '100';
			}
		}else{
			$data['status'] = 1;
			$data['jindu'] = '已学完';
		}
		//var_dump($data);
		 if($study && $study['stutus']==0){
			 $orderid = Db::name('kecheng_studylog')->where('id',$study['id'])->update($data);
		 }

		 return $this->json(['status'=>1,'jindu'=>$data['jindu']]);
	}
	public function getTiku(){
		$this->checklogin();
		$post = input('post.');
		//取出课程答题设置
		$field = 'id,aid,bid,sxdate,isdt,dtnum';
		if(getcustom('kecheng_order_learn')){
			$field .= ',orderlearn,learnhg';
		}
		$set =  Db::name('kecheng_list')->field($field)->where('id',$post['kcid'])->find();
		if(!$set) return $this->json(['status'=>-4,'msg'=>'该课程不存在']);
		if($set['isdt']==0) return $this->json(['status'=>-4,'msg'=>'该课程未开启答题']);

		//查看是否合格
		$where = ['aid'=>aid,'kcid'=>$post['kcid'],'mid'=>mid,'ishg'=>1];
		if(getcustom('kecheng_order_learn')){
            //是否开启了答题合格后学习下一章
             if($set && ($set['orderlearn'] == 1 || $set['learnhg'] == 1)){
                $mlid = input('?mlid')?input('mlid'):0;
                if(!$mlid){
                    return $this->json(['status'=>0,'msg'=>'请选择要作答的章节']);
                }
                $study = Db::name('kecheng_studylog')->where('mid',mid)->where('mlid',$mlid)->where('kcid',$set['id'])->where('status',1)->where('aid',aid)->find();
                if(!$study){
                	return $this->json(['status'=>0,'msg'=>'请学习完成后再答题']);
                }
                $count = Db::name('kecheng_chapter')->where('id',$mlid)->where('kcid',$set['id'])->where('status',1)->where('aid',aid)->count();
                if(!$count){
                    return $this->json(['status'=>0,'msg'=>'此章节不存在']);
                }
                $where['mlid'] = $mlid;
            }
        }
		$record= Db::name('kecheng_record')->where($where)->find();
		if($record) return $this->json(['status'=>-4,'msg'=>'已考试合格，无需再次考试','url'=>'/activity/kecheng/product?id='.$post['kcid']]);	

		//查看是否已经存入
		$record  = Db::name('kecheng_record')->where('aid',aid)->where('kcid',$post['kcid'])->where('mid',mid)->where('status','0')->find();
		//如果存在直接取出题目 否则新增进去
		$dtnum = $set['dtnum'];
		if(!$record){
			//随机取出题目
			$where = [];
			if(getcustom('kecheng_order_learn')){
                //是否开启了按顺序学习
                if($set && ($set['orderlearn'] == 1 || $set['learnhg'] == 1)){
                    $where[] = ['mlid','=',$mlid];
                }
            }
			$where[] = ['kcid','=',$post['kcid']];
			$where[] = ['status','=',1];
			$where[] = ['aid','=',aid];

			$tklist = Db::name('kecheng_tiku')->where($where)->orderRaw('rand()')->limit($dtnum)->select()->toArray();
			if(!$tklist) return $this->json(['status'=>-4,'msg'=>'暂没有考试题目']);	
			//将题库id 存入record
			$ids = json_encode(array_column($tklist, 'id'));
			$arr = [];
			$arr['aid'] = aid;
			$arr['mid'] = mid;
			$arr['kcid'] = $post['kcid'];
			$arr['time'] = time();
			$arr['timu'] = $ids;
			if(getcustom('kecheng_order_learn')){
                //是否开启了按顺序学习
                if($set && ($set['orderlearn'] == 1 || $set['learnhg'] == 1)){
                    $arr['mlid'] = $mlid;
                }
            }
			$rid = Db::name('kecheng_record')->insertGetId($arr);
			$record  = Db::name('kecheng_record')->where('aid',aid)->where('id',$rid)->find();
		}else{
			$log = Db::name('kecheng_recordlog')->where('aid',aid)->where('mid',mid)->where('recordid',$record['id'])->where('status',0)->order('id desc')->find();
		}
		$count = Db::name('kecheng_recordlog')->where('aid',aid)->where('recordid',$record['id'])->count();
		//取出的题目
		$timu = json_decode($record['timu']);
		$tmid = $timu[$count];
		$nums = count($timu);
		if($nums>$count && !$post['op']){
			if(!$log){
				//将题目存到log表
				$data = [];
				$data['aid'] = aid;
				$data['bid'] = $set['bid'];
				$data['mid'] = mid;
				$data['recordid'] = $record['id'];
				$data['time'] = time();
				$data['kcid'] = $post['kcid'];
				$data['tmid'] = $tmid;
				$data['sort'] = $count+1;
				if(getcustom('kecheng_order_learn')){
                    //是否开启了按顺序学习
                    if($set && $set['orderlearn'] == 1){
                        $data['mlid'] = $mlid;
                    }
                }
				Db::name('kecheng_recordlog')->insertGetId($data);
				$log = Db::name('kecheng_recordlog')->where('aid',aid)->where('mid',mid)->where('recordid',$record['id'])->where('status',0)->order('id desc')->find();
			}
		}
		$tkdata = Db::name('kecheng_tiku')->field('id,title,type,option_group,right_option')->where('aid',aid)->where('id',$log['tmid'])->where('status',1)->find();
		if($tkdata['type']==1){
			$tkdata['sorts'] = ['A','B','C','D','E','F','G'];
			$option = json_decode($tkdata['option_group'],true);
			//var_dump($option);
			$tkdata['option'] = $option;
			$right_option = explode(',',$tkdata['right_option']);
			$tkdata['right_option'] = $right_option;
			$tkdata['rightcount'] = count($right_option);
		}

		//查看剩余时间
		$endtime = $record['time']+$set['sxdate']*60;
		$data['lefttime'] = $endtime-time();
		if($data['lefttime'] <0){
			$data2 = [];
			$score = Db::name('kecheng_recordlog')->where('aid',aid)->where('recordid',$record['recordid'])->where('status',1)->sum('score');
			$data2['status'] = 1;
			$data2['endtime'] = time();
			$data2['score'] = $score;
			$set =  Db::name('kecheng_list')->field('id,aid,sxdate,isdt,dtnum,hgscore')->where('id',$record['kcid'])->find();
			//查看是否合格
			if($score>=$set['hgscore']){
				$data2['ishg'] = 1;
			}
			$res = Db::name('kecheng_record')->where('aid',aid)->where('id',$record['id'])->update($data2);
			return $this->json(['status'=>2,'rid'=>$record['id']]);
		}
		//答的第几道题目
		$data['tkdata'] = $tkdata;
		$data['rid'] = $record['id'];
		$data['nums'] = $nums;
		$data['set'] = $set;
		$data['hasnum'] = $log['sort'];
		$data['dtid'] = $log['id'];
		return $this->json(['status'=>1,'data'=>$data]);
	}
	//上一题
	public function prevquestion(){
		$this->checklogin();
		$post = input('post.');
		if($post['dtid']){
			$log = Db::name('kecheng_recordlog')->where('aid',aid)->where('id','<',$post['dtid'])->order('id desc')->find();
			 $answer = json_decode($log['answer']);
			if(is_null(json_decode($log['answer']))){
				 $answer = $log['answer'];
			}
			$tkdata = Db::name('kecheng_tiku')->field('id,title,type,option_group,right_option')->where('aid',aid)->where('id',$log['tmid'])->find();
			if($tkdata['type']==1){
				$tkdata['sorts'] = ['A','B','C','D','E','F','G'];
				$option = json_decode($tkdata['option_group'],true);
				//var_dump($option);
				$tkdata['option'] = $option;
				$right_option = explode(',',$tkdata['right_option']);
				$tkdata['right_option'] = $right_option;
				$tkdata['rightcount'] = count($right_option);
			}
		}
		$data = [];
		$data['answer'] = $answer;
		$data['tkdata'] = $tkdata;
		$data['hasnum'] = $log['sort'];
		$data['dtid'] = $log['id'];
		return $this->json(['status'=>1,'data'=>$data]);
	}
	//下一题
	public function nextquestion(){
		$this->checklogin();
		$post = input('post.');
		$dtlog = Db::name('kecheng_recordlog')->where('aid',aid)->where('id',$post['dtid'])->find();
		if(!$dtlog) return $this->json(['status'=>0,'msg'=>'答题记录不存在']);
		$dt_options = $post['right_option'];
		$tiku = Db::name('kecheng_tiku')->where('aid',aid)->where('id',$dtlog['tmid'])->find();
		$data = [];
		$right_option = explode(',',$tiku['right_option']); //后台正确答案
		if($tiku['type']==1 && count($right_option)>1){
			if(empty($dt_options) || count($dt_options) != count($right_option)){
				$is_right=0;
			}else{
				$is_right = 1;
				foreach($dt_options as $d){
					if(!in_array($d,$right_option)){
						$is_right=0;
						break; 
					}
				}
			}
			$data['answer'] = json_encode($dt_options);
		}else{
			if($dt_options == $tiku['right_option']){
				$is_right = 1;
			}
			$data['answer'] = $post['right_option'];
		}
		if($is_right==1){
			$data['status'] = 1;
			$data['score'] = $tiku['score'];
		}else{
			$data['status'] = 2;
			$data['score'] = 0;
		}
		Db::name('kecheng_recordlog')->where('aid',aid)->where('id',$dtlog['id'])->update($data);
		$log = Db::name('kecheng_recordlog')->where('aid',aid)->where('recordid',$dtlog['recordid'])->where('id','>',$post['dtid'])->order('id asc')->find();

		$count = Db::name('kecheng_recordlog')->where('aid',aid)->where('recordid',$dtlog['recordid'])->count();
		//取出的题目
		$record  = Db::name('kecheng_record')->where('aid',aid)->where('id',$dtlog['recordid'])->find();
		$timu = json_decode($record['timu']);
		$tmid = $timu[$count];
		$nums = count($timu);
		if(!$log){
			//将下一题存到log表
			$data = [];
			$data['aid'] = aid;
			$data['bid'] = $tiku['bid'];
			$data['mid'] = mid;
			$data['recordid'] = $dtlog['recordid'];
			$data['time'] = time();
			$data['kcid'] = $dtlog['kcid'];
			$data['tmid'] = $tmid;
			$data['sort'] = $count+1;
			Db::name('kecheng_recordlog')->insertGetId($data);
			$log = Db::name('kecheng_recordlog')->where('aid',aid)->where('mid',mid)->where('recordid',$dtlog['recordid'])->where('status',0)->order('id desc')->find();
		}else{
			$answer = json_decode($log['answer']);
			if(is_null(json_decode($log['answer']))){
				 $answer = $log['answer'];
			}
			//$answer = $log['answer'];
		}
		$tkdata = Db::name('kecheng_tiku')->field('id,title,type,option_group,right_option')->where('aid',aid)->where('id',$log['tmid'])->find();
		if($tkdata['type']==1){
			$tkdata['sorts'] = ['A','B','C','D','E','F','G'];
			$option = json_decode($tkdata['option_group'],true);
			//var_dump($option);
			$tkdata['option'] = $option;
			$right_option = explode(',',$tkdata['right_option']);
			$tkdata['right_option'] = $right_option;
			$tkdata['rightcount'] = count($right_option);
		}
		$data = [];
		$data['tkdata'] = $tkdata;
		$data['hasnum'] = $log['sort'];
		$data['dtid'] = $log['id'];
		$data['hasnum'] = $log['sort'];
		$data['answer'] = $answer;
		return $this->json(['status'=>1,'data'=>$data]);
	}
	public function tofinish(){
		$this->checklogin();
		$post = input('post.');
		$dtlog = Db::name('kecheng_recordlog')->where('aid',aid)->where('id',$post['dtid'])->find();
		$tiku = Db::name('kecheng_tiku')->where('aid',aid)->where('id',$dtlog['tmid'])->find();
		$data = [];
		$right_option = explode(',',$tiku['right_option']); //后台正确答案
		$dt_options = $post['right_option']; //我选的答案
		if($tiku['type']==1 && count($right_option)>1){
			if(empty($dt_options) || count($dt_options) != count($right_option)){
				$is_right=0;
			}else{
				$is_right = 1;
				foreach($dt_options as $d){
					if(!in_array($d,$right_option)){
						$is_right=0;
						break; 
					}
				}
			}
			$data['answer'] = json_encode($dt_options);
		}else{
			if($dt_options == $tiku['right_option']){
				$is_right = 1;
			}
			$data['answer'] = $post['right_option'];
		}
		if($is_right==1){
			$data['status'] = 1;
			$data['score'] = $tiku['score'];
		}else{
			$data['status'] = 2;
			$data['score'] = 0;
		}
		//最后
		Db::name('kecheng_recordlog')->where('aid',aid)->where('id',$dtlog['id'])->update($data);	
		//修改答题状态
		$score = Db::name('kecheng_recordlog')->where('aid',aid)->where('recordid',$dtlog['recordid'])->where('status',1)->sum('score');
		$data2 = [];
		$data2['status'] = 1;
		$data2['endtime'] = time();
		$data2['score'] = $score;
		$set =  Db::name('kecheng_list')->field('id,aid,sxdate,isdt,dtnum,hgscore')->where('id',$dtlog['kcid'])->find();
		//查看是否合格
		if($score>=$set['hgscore']){
			$data2['ishg'] = 1;
		}
		$res = Db::name('kecheng_record')->where('aid',aid)->where('id',$dtlog['recordid'])->update($data2);
		return $this->json(['status'=>1]);
	}

	//答题完成页面
	public function complete(){
		$this->checklogin();
		$post = input('post.');
		$field = 'id,score,ishg,time,endtime,kcid';
		if(getcustom('kecheng_order_learn')){
			$field .= ',mlid';
		}
		$record = Db::name('kecheng_record')->field($field)->where('aid',aid)->where('id',$post['rid'])->find();
		$time = $record['endtime'] - $record['time'];
		$record['time'] = date('Y-m-d H:i:s',$record['time']);
		$record['endtime'] = date('Y-m-d H:i:s',$record['endtime']);
		//答对题目
		$rightnum = Db::name('kecheng_recordlog')->where('aid',aid)->where('recordid',$record['id'])->where('status',1)->count();
		//答错题目
		$errornum = Db::name('kecheng_recordlog')->where('aid',aid)->where('recordid',$record['id'])->where('status',2)->count();
		$record['rightnum'] = $rightnum;
		$record['errornum'] = $errornum;
		$data = [];
		$record['longtime'] =$this->changeTimeType($time);
		$data['record'] = $record;
		return $this->json(['status'=>1,'data'=>$record]);
	}

	//答题记录
	public function recordlog(){
		$this->checklogin();
		$kcid = input('param.kcid');
		$pagenum = input('param.pagenum') ? input('param.pagenum') : 1;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$where[] = ['kcid','=',$kcid];
		$where[] = ['status','=',1];
		if(getcustom('kecheng_order_learn')){
			$mlid = input('?mlid')?input('mlid'):0;
			if($mlid){
				$where[] = ['mlid','=',$mlid];
			}
        }
		$datalist = Db::name('kecheng_record')->where($where)->order('id desc')->page($pagenum,10)->select()->toArray();
		//echo db('kecheng_record')->getlastsql();
		if(!$datalist) $datalist = [];
		foreach($datalist as &$d){
			$d['title'] =  Db::name('kecheng_list')->where('id',$d['kcid'])->value('name');
			if(getcustom('kecheng_order_learn')){
                if($d['mlid']){
                    $chapter_name = Db::name('kecheng_chapter')->where('id',$d['mlid'])->value('name');
                    $d['title'] .= $chapter_name?'-'.$chapter_name:'';
                }
            }
			$d['date']  = date('Y-m-d H:i:s',$d['time']); 
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}
	//错题解析
	public function error(){
		$this->checklogin();
		$rid = input('param.rid');
		$op = input('param.op');
		$logid = input('param.logid');
		$timu = Db::name('kecheng_recordlog')->where(['recordid'=>$rid,'status'=>2])->order('id asc')->find();
		if($op=='down'){
			$timu = Db::name('kecheng_recordlog')->where('id','>',$logid)->where('mid',mid)->where('recordid',$rid)->where('status','2')->find();
		}
		if($op=='up'){
			$timu = Db::name('kecheng_recordlog')->where('id','<',$logid)->where('mid',mid)->where('recordid',$rid)->where('status','2')->order('id desc')->find();
		}
		//var_dump($timu);
		$count = Db::name('kecheng_recordlog')->where(['recordid'=>$rid,'status'=>2])->count();
		$tkdata = Db::name('kecheng_tiku')->where('aid',aid)->where('id',$timu['tmid'])->find();
		$tkdata['nums'] = $count;
		$tkdata['hasnum'] = $count;
		if($tkdata['type']==1){
			$tkdata['sorts'] = ['A','B','C','D','E','F','G'];
			$option = json_decode($tkdata['option_group'],true);
			$tkdata['option'] = $option;
		}
		$tkdata['sort'] = $timu['sort'];
		//判断答案是不是json
		$tkdata['answer'] = $timu['answer'];	
		if(!is_null(json_decode($timu['answer']))){
			$tkdata['answer'] = json_decode($timu['answer'],true);		
			$right_option = explode(',',$tkdata['right_option']);
			$tkdata['rightcount'] = count($right_option);
			$right_options = array();
			foreach($right_option as $d){
				$right_options[] = $tkdata['sorts'][$d];
			}
			$tkdata['right_options'] = implode(',',$right_options);
		}
		$up =  Db::name('kecheng_recordlog')->where('id','<',$timu['id'])->where('mid',mid)->where('recordid',$timu['recordid'])->where('status','2')->order('id desc')->find();
		if($up)  $tkdata['isup'] = 1; 
		$down =  Db::name('kecheng_recordlog')->where('id','>',$timu['id'])->where('mid',mid)->where('recordid',$timu['recordid'])->where('status','2')->order('id asc')->find();
		if($down) $tkdata['isdown'] = 1;
		$tkdata['logid'] = $timu['id'];
		return $this->json(['status'=>1,'data'=>$tkdata]);
	}
		//获取下一节
	public function nextsection(){
		$this->checklogin();
		$id = input('param.id');
		$kcid = input('param.kcid');
		$detail = Db::name('kecheng_chapter')->where('aid',aid)->where('id','>',$id)->where('kcid',$kcid)->order('id asc')->find();
		return $this->json(['status'=>1,'id'=>$detail['id']]);
	}
	public function addstudys($kcid,$detail){
		//查询是否存在
		$count = Db::name('kecheng_studylog')->where('kcid',$kcid)->where('mlid',$detail['id'])->where('mid',mid)->where('aid',aid)->count('id');
		if(!$count){
			$data = [];
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['bid'] = $detail['bid'];
			$data['pic'] = $detail['pic'];
			$data['mlid'] = $detail['id'];
			$data['createtime'] = time();
			$data['platform'] = platform;
			$data['title'] = $detail['name'];
			$data['kcid'] = $kcid;
			if($detail['kctype']==1){
				$data['status'] = 1;
				$data['jindu'] = '已学完';
			}
			$data['currentTime'] = 0;
			Db::name('kecheng_studylog')->insertGetId($data);
		}
	}

	//获取下一题或者上一题
	public function geterrotiku(){
		$this->checklogin();
		$recordid = input('param.rid');
		$logid = input('param.logid');
		$op = input('param.op');
		if($op=='down'){
			$down =  Db::name('kecheng_recordlog')->where('id','>',$logid)->where('recordid',$recordid)->where('status','2')->find();
			$tkdata = Db::name('kecheng_tiku')->where('aid',aid)->where('id',$down['tmid'])->find();	

		}
	}
	/**
	 * 将秒数转换成时分秒
	 *
	 * @param 秒数 $seconds
	 * @return void
	 */
	function changeTimeType($seconds)
	{
		if ($seconds > 3600) {
			$hours = intval($seconds / 3600);
			$time = $hours . ":" . gmstrftime('%M:%S', $seconds);
		} else {
			$time = gmstrftime('%H:%M:%S', $seconds);
		}
		return $time;
	}
	
	public function givescore(){
	    if(getcustom('kecheng_give_score')){
            $kccid = input('param.kccid');
            
            $bid = input('param.bid') ? input('param.bid') : 0;
            $scorelog = Db::name('kecheng_give_scorelog')->where('kccid',$kccid)->where('aid',aid)->where('bid',$bid)->where('mid',mid)->find();
            if($scorelog){
                return $this->json(['status'=>0,'msg'=>'已赠送']);
            }
            $givescore= Db::name('kecheng_chapter')->where('id',$kccid)->value('give_score');
            if($givescore > 0){
                $insert = [
                    'aid' =>aid,
                    'bid' => $bid,
                    'mid' => mid,
                    'kccid' => $kccid,
                    'score'  =>$givescore,
                    'createtime' => time()
                ];
                Db::name('kecheng_give_scorelog')->insert($insert);
                \app\commons\Member::addscore(aid,mid,$givescore,'学习视频赠送','',0,0);
                return $this->json(['status'=>1,'msg'=>'恭喜获得'.$givescore.'积分']);
            }
        }
    }

    public function lecturerapply(){
        if(getcustom('kecheng_lecturer')){
            //申请讲师
            $this->checklogin();

            //平台权限给课程权限
            $kechengauth = true;
            $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
            if($admin_user['auth_type'] !=1){
                $admin_auth = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
                if($admin_user['groupid']){
                    $admin_auth = Db::name('admin_user_group')->where('id',$admin_user['groupid'])->value('auth_data');
                }
                if(!$admin_auth || !in_array('KechengLecturerList/index,KechengLecturerList/*',$admin_auth)){
                    $kechengauth = false;
                }
            }
            if(!$kechengauth){
                return $this->json(['status'=>0,'msg'=>'该功能系统暂未开通']); 
            }

            $opttype = input('?param.opttype')?input('param.opttype/d'):0;//操作类型 0：申请 1：修改信息
            $lecturer = Db::name('kecheng_lecturer')->where('mid',mid)->where('aid',aid)->find();
            if($lecturer){
                if(!$opttype){
                    if($lecturer['checkstatus']==1){
                        $tourl='/pagesB/kecheng/lecturercenter';
                        return $this->json(['status'=>2,'msg'=>'您已成功入驻','tourl'=>$tourl]);
                    }
                }else{
                	//修改信息，必须是审核通过的
                    if($lecturer['checkstatus']!=1){
                        if($lecturer['checkstatus']==0){
                            return $this->json(['status'=>0,'msg'=>'您已提交过申请，请等待审核中']); 
                        }
                        if($lecturer['checkstatus']==-1){
                            return $this->json(['status'=>0,'msg'=>'申请被驳回']); 
                        }
                        return $this->json(['status'=>0,'msg'=>'申请未通过']); 
                    }
                }
            }else{
                if($opttype){
                    $tourl='/pagesB/kecheng/lecturerapply';
                    return $this->json(['status'=>2,'msg'=>'请先申请','tourl'=>$tourl]); 
                }
            }

            //是否能发放验证码
            $cansendsms = false;
            // $smsset = Db::name('admin_set_sms')->where('aid',aid)->field('id,tmpl_smscode,tmpl_smscode_st,status')->find();
            // if($smsset && $smsset['status'] == 1 && $smsset['tmpl_smscode'] && $smsset['tmpl_smscode_st']==1){
            //     $cansendsms = true;
            // }

            if(request()->isPost()){
                if($lecturer && $lecturer['checkstatus']==0){
                    return $this->json(['status'=>0,'msg'=>'您已提交过申请，请等待审核中']); 
                }
                $formdata = input('post.info/a');
                if(!$formdata['pic'] || empty($formdata['pic'])) {
                    return $this->json(['status'=>0,'msg'=>'请上传头像']); 
                }

                if(!$formdata['nickname'] || empty($formdata['nickname'])) {
                    return $this->json(['status'=>0,'msg'=>'请填写昵称']); 
                }
                $nickname_len = mb_strlen($formdata['nickname']);
                if($nickname_len>=30){
                    return $this->json(['status'=>0,'msg'=>'昵称不能超出30个字符']); 
                }

                if(!$formdata['realname'] || empty($formdata['realname'])) {
                    return $this->json(['status'=>0,'msg'=>'请填写姓名']); 
                }
                $formdata['realname'] = trim($formdata['realname']);
                $realname_len = mb_strlen($formdata['realname']);
                if($realname_len>=30){
                    return $this->json(['status'=>0,'msg'=>'姓名不能超出30个字符']); 
                }
                $hasrealname = Db::name('kecheng_lecturer')->where('aid',aid)->where('realname',$formdata['realname'])->field('id')->find();
                if($hasrealname){
                    if(($lecturer['id'] && $hasrealname['id'] != $lecturer['id']) || !$lecturer['id']){
                        return json(['status'=>0,'msg'=>'该姓名已存在，请填写其它姓名']);
                    }
                }

                if(!$formdata['tel'] || empty($formdata['tel'])) {
                    return $this->json(['status'=>0,'msg'=>'请填写手机号']); 
                }
                $formdata['tel'] = trim($formdata['tel']);
                if(!checkTel($formdata['tel'])){
                    return $this->json(['status'=>0, 'msg'=>'请填写正确的手机号']);
                }
                $hastel = Db::name('kecheng_lecturer')->where('aid',aid)->where('tel',$formdata['tel'])->field('id')->find();
                if($hastel){
                    if(($lecturer['id'] && $hasrealname['id'] != $lecturer['id']) || !$lecturer['id']){
                        return $this->json(['status'=>0,'msg'=>'该手机号已存在，请填写其它手机号']);
                    }
                }
                //查询管理员账号
                $hasun = Db::name('admin_user')->where('un',$formdata['tel'])->field('id')->find();
                if($hasun){
                	if(($lecturer['id'] && $hasun['id'] != $lecturer['userid']) || !$lecturer['id']){
                        return $this->json(['status'=>0,'msg'=>'该手机号已存在，请填写其它手机号!']);
                    }
                }

                //需要验证码
                if($cansendsms && (!$opttype || ($opttype && $formdata['tel'] != $lecturer['tel']))){
                    $formdata['smscode'] = trim($formdata['smscode']);
                    if(md5($formdata['tel'].'-'.$formdata['smscode']) != cache($this->sessionid.'_smscode') || cache($this->sessionid.'_smscodetimes')>5){
                        cache($this->sessionid.'_smscodetimes',cache($this->sessionid.'_smscodetimes')+1);
                        return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
                    }
                    cache($this->sessionid.'_smscode',null);
                    cache($this->sessionid.'_smscodetimes',null);
                }

                if(!$opttype){
                    if(!$formdata['pwd'] || empty($formdata['pwd'])) {
                        return $this->json(['status'=>0,'msg'=>'请填写登录密码']); 
                    }
                }
                if($formdata['pwd'] && !empty($formdata['pwd'])){
                    $pwd = md5($formdata['pwd']);
                }

                $shortdesc_len = mb_strlen($formdata['shortdesc']);
                if($shortdesc_len>=200){
                    return $this->json(['status'=>0,'msg'=>'简介不能超出200个字符']); 
                }

                $info = [];
                $info['headimg']   = $formdata['pic'];
                $info['nickname']  = $formdata['nickname'];
                $info['realname']  = $formdata['realname'];
                $info['tel']       = $formdata['tel'];
                if($pwd){
                    $info['pwd']  = $pwd;
                }
                $info['shortdesc'] = $formdata['shortdesc'];
                if($opttype || ($lecturer && $lecturer['checkstatus'] == -1)){
                    $info['updatetime']  = time();
                    if($lecturer && $lecturer['checkstatus'] != 1){
                        $info['checkstatus'] = 0;
                    }
                    $sql = Db::name('kecheng_lecturer')->where('id',$lecturer['id'])->update($info);
                    $lecturerid = $lecturer['id'];
                }else{
                    $info['aid'] = aid;
                    $info['mid'] = mid;
                    $info['checkstatus'] = 0;
                    $info['status']      = 1;
                    $info['createtime']  = time();
                    $lecturerid = $sql = Db::name('kecheng_lecturer')->insertGetId($info);
                }
                if(!$sql){
                    return $this->json(['status'=>0,'msg'=>'操作失败，请重试']); 
                }

                if($opttype){
                    //后台管理员账号
                    $uinfo = [];
                    $uinfo['un']   = $formdata['tel'];
                    if($pwd){
                        $uinfo['pwd'] = $pwd;
                    }
                    $user = '';
                    if($lecturer['userid']){
                        $user = Db::name('admin_user')->where('id',$lecturer['userid'])->where('lecturerid',$lecturer['id'])->where('aid',aid)->field('id')->find();
                    }
                    if($user){
                        Db::name('admin_user')->where('id',$user['id'])->where('aid',aid)->update($uinfo);
                    }else{
                        if($lecturer && $lecturer['checkstatus'] == 1){
                            $uinfo['aid']  = aid;
                            $uinfo['mid']  = mid;
                            $uinfo['lecturerid'] = $lecturerid;
                            $uinfo['auth_data']        = '{"150":"KechengLecturer\/mycenter,KechengLecturer\/mycenter","151":"KechengLecturerList\/index,KechengLecturerList\/*"}';
                            $uinfo['hexiao_auth_data'] = '';
                            $uinfo['wxauth_data']      = '';
                            $uinfo['random_str'] = random(16);
                            $uinfo['isadmin']    = 0;
                            $uinfo['status']     = 1;
                            $uinfo['createtime'] = time();
                            $uid = Db::name('admin_user')->insertGetId($uinfo);
                            Db::name('kecheng_lecturer')->where('id',$lecturer['id'])->update(['userid'=>$uid]);
                        }
                    }
                    return $this->json(['status'=>1,'msg'=>'保存成功','tourl'=>'']);
                }else{
                    return $this->json(['status'=>1,'msg'=>'提交成功，等待审核中','tourl'=>'']);
                }
            }
            $rdata = [];
            $rdata['status'] = 1;
            $rdata['canpwd'] = $lecturer && $lecturer['checkstatus'] == 0?false:true;//能否修改密码
            $rdata['cansub'] = $lecturer && $lecturer['checkstatus'] == 0?false:true;//能否提交
            $rdata['info']   = $lecturer?$lecturer:[];

            $backstage = '';
            if($opttype && $lecturer && $lecturer['checkstatus'] == 1){
                $backstage = PRE_URL.'/?s=/Backstage/index';
            }
            $rdata['backstage'] = $backstage;
            $rdata['cansendsms'] = $cansendsms;
            return $this->json($rdata);
        }
    }

    public function lecturercenter(){
        if(getcustom('kecheng_lecturer')){
        	//讲师中心
        	$this->checklogin();
        	//平台权限给课程权限
            $kechengauth = true;
            $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
            if($admin_user['auth_type'] !=1){
                $admin_auth = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
                if($admin_user['groupid']){
                    $admin_auth = Db::name('admin_user_group')->where('id',$admin_user['groupid'])->value('auth_data');
                }
                if(!$admin_auth || !in_array('KechengLecturer/index,KechengLecturer/*',$admin_auth)){
                    $kechengauth = false;
                }
            }
            if(!$kechengauth){
            	return $this->json(['status'=>0,'msg'=>'该功能系统暂未开通']); 
            }
            if(request()->isPost()){
                $lecturerid = input('?param.id')?input('param.id/d'):0;
                $canedit = false;
                if(!$lecturerid){
                    //查询自己的讲师
                    $lecturer = Db::name('kecheng_lecturer')->where('mid',mid)->where('aid',aid)->field('id,mid,nickname,headimg,realname,tel,shortdesc,checkstatus,status')->find();
                    if(!$lecturer){
                        return $this->json(['status'=>0,'msg'=>'讲师不存在']);
                    }
                    if($lecturer['checkstatus'] == 0){
                    	return $this->json(['status'=>0,'msg'=>'等待审核中']);
                    }
                    if($lecturer['checkstatus'] == -1){
                    	return $this->json(['status'=>0,'msg'=>'审核未通过']);
                    }
                    if($lecturer['checkstatus'] !=1){
                    	return $this->json(['status'=>0,'msg'=>'审核未通过，暂无法登录']);
                    }
                    if($lecturer['status'] !=1){
                        return $this->json(['status'=>0,'msg'=>'讲师暂无法登录']);
                    }
                    $canedit = true;
                }else{
                    $lecturer = Db::name('kecheng_lecturer')->where('id',$lecturerid)->where('aid',aid)->where('checkstatus',1)->where('status',1)->field('id,mid,nickname,headimg,realname,tel,shortdesc')->find();
                    if($lecturer['mid'] == mid){
                    	$canedit = true;
                    }
                }
                if(!$lecturer){
                    return $this->json(['status'=>0,'msg'=>'讲师不存在']);
                }

                $st = input('?param.st')?input('param.st/d'):0;
                $pagenum = input('post.pagenum');
                if(!$pagenum) $pagenum = 1;
                $pernum = 20;
                $where = [];
                $where[] = ['aid','=',aid];
                $where[] = ['lecturerid','=',$lecturer['id']];
                $where[] = ['ischecked','=',1];
                $where[] = ['status','=',1];
                if($st == 0){
                	$where[] = ['kctype','=',1];
                }else{
                	$where[] = ['kctype','=',3];
                }
                $datalist = Db::name('kecheng_list')->field("id,name,pic,kctype,chapterid,createtime,join_num")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
                if($datalist){
                    foreach($datalist as &$dv){
                    	$dv['showtime'] = date("Y-m-d H:i",$dv['createtime']);
                        // $timecha = time() - $dv['createtime'];
                        // if($timecha < 60*5 ){
                        //     $dv['showtime'] = '刚刚';
                        // }else if(date('Ymd')==date('Ymd',$dv['createtime'])){
                        //     if($dv['createtime'] + 3600 > time()){
                        //         $dv['showtime'] = floor(($timecha)/60).'分钟前';
                        //     }else{
                        //         $dv['showtime'] = floor(($timecha)/3600).'小时前';
                        //     }
                        // }elseif($timecha<86400){
                        //     $dv['showtime'] = '昨天';
                        // }elseif($timecha<2*86400){
                        //     $dv['showtime'] = '前天';
                        // }else{
                        //     $dv['showtime'] = '三天前';
                        // }
                    }
                    unset($dv);
                }
                return $this->json(['status'=>1,'data'=>$datalist,'lecturer'=>$lecturer,'canedit'=>$canedit]);
            }
        }
    }

    public function lecturermldetail(){
        if(getcustom('kecheng_lecturer')){
            //课程章节详情
            $this->checklogin();
            //平台权限给课程权限
            $kechengauth = true;
            $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
            if($admin_user['auth_type'] !=1){
                $admin_auth = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
                if($admin_user['groupid']){
                    $admin_auth = Db::name('admin_user_group')->where('id',$admin_user['groupid'])->value('auth_data');
                }
                if(!$admin_auth || !in_array('KechengLecturerList/index,KechengLecturerList/*',$admin_auth)){
                    $kechengauth = false;
                }
            }
            if(!$kechengauth){
                return $this->json(['status'=>0,'msg'=>'该功能系统暂未开通']); 
            }
            $proid = input('param.id/d');
            $kcid  = input('param.kcid/d');
            $kc    = Db::name('kecheng_list')->where('id',$kcid)->where('chaptertype',2)->find();
            if(!$kc){
                return $this->json(['status'=>0,'msg'=>'课程不存在']);
            }
            $kc = $this->formatproduct($kc);
            if($proid && $proid != $kc['chapterid']){
                return $this->json(['status'=>0,'msg'=>'课程不存在']);
            }

            $needbuy = true;//是否需要购买
            //查看关联的讲师
            $lecturer = Db::name('kecheng_lecturer')->where('id',$kc['lecturerid'])->field('id,mid,nickname,headimg,checkstatus,status')->find();
            if($lecturer && $lecturer['mid'] == mid){
                $needbuy = false;
            }

            //所有章节
            $chapter = Db::name('kecheng_chapter')->where('id',$kc['chapterid'])->where('kcid',$kcid)->where('status',1)->where('aid',aid)->order('sort desc,id desc')->field('id')->find();
            if(!$chapter){
                return $this->json(['status'=>0,'msg'=>'课程不存在']);
            }

            if(!$proid){
                //章节id不存在，看studylog 有没有
                $studylog = Db::name('kecheng_studylog')->where('aid',aid)->where('mid',mid)->where('kcid',$kcid)->order('id desc')->find();
                if($studylog){
                    $detail = Db::name('kecheng_chapter')->where('id',$studylog['mlid'])->where('aid',aid)->find();
                }else{
                    $detail = Db::name('kecheng_chapter')->where('kcid',$kcid)->where('aid',aid)->where('status',1)->order('sort desc,id desc')->find();
                    $this->addstudys($kcid,$detail);
                    //没有订单的课程，学习增加销量
                    //\app\models\Payorder::addSales(0,'kecheng',aid,$detail['bid'],1);
                    $studylog = Db::name('kecheng_studylog')->where('aid',aid)->where('mid',mid)->where('kcid',$kcid)->where('mlid',$detail['id'])->order('id desc')->find();
                }
                if(!$detail) return $this->json(['status'=>-4,'msg'=>'暂无内容']);
            }else{
                //查询正在查看的章节信息
                $detail = Db::name('kecheng_chapter')->where('id',$proid)->where('aid',aid)->where('status',1)->find();
                if(!$detail) return $this->json(['status'=>-4,'msg'=>'暂无内容']);
                $studylog = Db::name('kecheng_studylog')->where('aid',aid)->where('mid',mid)->where('kcid',$kcid)->where('mlid',$proid)->order('id desc')->find();
                if(!$studylog){
                    $this->addstudys($kcid,$detail);
                }
                $studylog = Db::name('kecheng_studylog')->where('aid',aid)->where('mid',mid)->where('kcid',$kcid)->where('mlid',$proid)->order('id desc')->find();
            }
            $detail['readnum']  = $kc['readnum']??0;
            $detail['price']    = $kc['price'];
            $detail['lecturer'] = $lecturer?$lecturer:['id'=>0,'nickname'=>'账号已失效','headimg'=>''];
            $detail['createtime'] = date("m-d",$detail['createtime']);

            //查看我的课程里面有没有
            $order = Db::name('kecheng_order')->where('kcid',$kcid)->where('aid',aid)->where('mid',mid)->where('status',1)->order('id','desc')->find();
            if($order && $order['totalprice']> 0){
                $needbuy = false;//付款过不需要购买
            }
            if($needbuy){
                if($kc['price']<=0){
                    $needbuy = false;
                    if(!$order){
                        $ordernum = date('ymdHis').rand(100000,999999);
                        $data1 = [];
                        $data1['aid'] = aid;
                        $data1['mid'] = mid;
                        $data1['bid'] = $kc['bid'];
                        $data1['pic'] = $kc['pic'];
                        $data1['createtime'] = time();
                        $data1['ordernum']   = $ordernum;
                        $data1['platform']   = platform;
                        $data1['title']      = $kc['name'];
                        $data1['kcid']       = $kcid;
                        $data1['totalprice'] = 0;
                        $data1['price']      = 0;
                        $data1['status']     = 1;
                        if(getcustom('kecheng_lecturer')){
                            if($kc['lecturerid']){
                                $data1['lecturerid'] = $kc['lecturerid'];
                            }
                        }
                        //增加学习记录
                        $order = Db::name('kecheng_order')->insertGetId($data1);
                    }else{
                        Db::name('kecheng_list')->where('aid',aid)->where('id',$kcid)->inc('join_num')->update();
                    }
                }else{
                    //如果没有免费，且没有购买或者当时会员免费，现在的会员等级收费，依然需要付费
                    if(!$detail['ismianfei'] && (!$order || $order['totalprice'] == 0)){
                        $detail['detail']    = '付费后查看详情';
                        $detail['video_url'] = '付费后查看详情';
                    }
                }
            }
            
            $detail['needbuy'] = $needbuy;

            if($detail['status']==0) return $this->json(['status'=>-4,'msg'=>'暂无内容']);
            if(getcustom('video_qq_url')){
                $detail['video_url'] = \app\customs\VideoQQ::getMp4Url($detail['video_url']);
            }

            //获取上次播放时间
            if($detail['kctype']>1){
                $detail['startTime'] = $studylog['currentTime']?$studylog['currentTime']:0;
            }
            //查看是否购买
            $detail['ispay'] = 0;
            if($order && $order['status']==1){
                $detail['ispay'] = 1;
            }
            //根据课程查询是否已学完
            $detail['count'] =0 + Db::name('kecheng_studylog')->where(['kcid'=>$detail['kcid'],'status'=>1,'mid'=>mid,'aid'=>aid])->group('mlid')->count();
            //查看共有几节课
            $detail['kccount'] = 0+Db::name('kecheng_chapter')->where(['aid'=>aid,'kcid'=>$detail['kcid']])->count();
            $speedlist=[];
            $showspeed=false;
            if(getcustom('video_speed')){
                $speedlist = ['1'=>'0.8','2'=>'1.0','3'=>'1.5','4'=>'2'];
                if($detail['isspeed']==1){
                    $showspeed=true;
                }
            }
            $detail['isdt']    = $kc['isdt'];
            $detail['price']   = $kc['price'];
            $detail['isstudy'] = $studylog['status'];
            $detail['detail']  = \app\commons\System::initpagecontent($detail['detail'],aid,mid,platform);
            Db::name('kecheng_list')->where('id',$detail['kcid'])->inc('readnum',1)->update();
            $detail['is_give_score'] = false;
            if(getcustom('kecheng_give_score')){
                $detail['is_give_score'] = true;
            }
            $detail['orderlearn'] = 0;
            $detail['learnhg']    = 0;
            //查询学习的是当前第几个
            $detail['learnkey'] = 0;

            $rdata = [];
            $rdata['status'] = 1;
            $rdata['iskaoshi']  = 0;
            $rdata['isauto']    = false;
            $rdata['speedlist'] = $speedlist;
            $rdata['showspeed'] = $showspeed;
            
            //重置免费时长
            if($detail['ismianfei'] == 1 && $detail['mianfei_unit'] == 2){
              $detail['mianfei_time'] = intval($detail['mianfei_time']*60);
            }
            if($detail['ispay'] == 1){
              $detail['mianfei_time'] = 0;
            }
            $rdata['detail'] = $detail;
            $rdata['studylog'] = $studylog;
            $rdata['kechengset'] = Db::name('kecheng_sysset')->where('aid',aid)->find();
            return $this->json($rdata);
        }
    }

    public function lecturerEditkecheng(){
        if(getcustom('kecheng_lecturer')){
            //讲师添加编辑课程
            $this->checklogin();
            //平台权限给课程权限
            $kechengauth = true;
            $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
            if($admin_user['auth_type'] !=1){
                $admin_auth = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
                if($admin_user['groupid']){
                    $admin_auth = Db::name('admin_user_group')->where('id',$admin_user['groupid'])->value('auth_data');
                }
                if(!$admin_auth || !in_array('KechengLecturerList/index,KechengLecturerList/*',$admin_auth)){
                    $kechengauth = false;
                }
            }
            if(!$kechengauth){
            	return $this->json(['status'=>0,'msg'=>'该功能系统暂未开通']); 
            }

            $lecturer = Db::name('kecheng_lecturer')->where('mid',mid)->where('aid',aid)->find();
            if(!$lecturer){
                return $this->json(['status'=>0,'msg'=>'请先申请讲师']); 
            }
            if($lecturer['checkstatus'] != 1){
                if($lecturer['checkstatus'] ==0){
                    return $this->json(['status'=>0,'msg'=>'等待审核中']); 
                }
                if($lecturer['checkstatus'] ==-1){
                    return $this->json(['status'=>0,'msg'=>'申请被驳回']); 
                }
                return $this->json(['status'=>0,'msg'=>'审核错误']);
            }
            $lecturerid = $lecturer['id'];
            $id = input('?param.id')?input('param.id/d'):0;
            if($id){
                $kecheng = Db::name('kecheng_list')->where('id',$id)->where('lecturerid',$lecturerid)->where('aid',aid)->find();
                if(!$kecheng){
                    return $this->json(['status'=>0,'msg'=>'课程不存在']);
                }
                $kecheng['lvprice_data'] = json_decode($kecheng['lvprice_data'], true);
                //查询章节
                if($kecheng['chapterid']){
                    $chapter = Db::name('kecheng_chapter')->where('id',$kecheng['chapterid'])->find();
                    if($chapter){
                        $kecheng['freecontent']   = $chapter['freecontent'];
                        $kecheng['video_url']     = $chapter['video_url'];
                        $kecheng['video_duration']= $chapter['video_duration'];
                        if(getcustom('video_speed')){
                            $kecheng['isspeed']   = $chapter['isspeed'];
                        }
                        $kecheng['isjinzhi']      = $chapter['isjinzhi'];
                    }
                }
                if($kecheng['freecontent']){
                    $kecheng['freecontent'] = strip_tags($kecheng['freecontent']);
                }
            }

            $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
            $default_cid = $default_cid ? $default_cid : 0;
            $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->field('id,name')->select()->toArray();

            if(request()->isPost()){
                //关联的章节ID
                $chapterid = 0;
                if($kecheng){
                    $chapterid = $kecheng['chapterid']??0;
                }

                $info = input('post.info/a');
                $info['freedetail'] = $info['freedetail']?json_encode($info['freedetail']):'';
                $pagecontent = input('pagecontent');
                $info['detail'] = $pagecontent?json_encode($pagecontent):'';

                $data = array();
                if(!$info['name'] || empty($info['name'])){
                	return $this->json(['status'=>0,'msg'=>'名称不能为空']);
                }
                $data['name']   = $info['name'];

                if(!$info['pic'] || empty($info['pic'])){
                	return $this->json(['status'=>0,'msg'=>'主图不能为空']);
                }
                $data['pic']   = $info['pic'];

                $data['pics']  = $info['pics']??'';

                $countcat = Db::name('kecheng_category')->where('id',$info['cid'])->where('aid',aid)->where('bid',0)->where('status',1)->count();
                if(!$countcat && $info['cid'] != $kecheng['cid']){
                	return $this->json(['status'=>0,'msg'=>'分类不存在']);
                }
                $data['cid']    = $info['cid'];

                if(!$info['kctype'] || ($info['kctype'] !=1 && $info['kctype'] !=3)){
                	return $this->json(['status'=>0,'msg'=>'类型错误']);
                }
                $data['kctype'] = $info['kctype'];//课程类型
                if(isset($info['detail_text'])){
                    $data['detail_text'] = $info['detail_text']??'';
                }
                if(isset($info['detail_pics'])){
                    $data['detail_pics'] = $info['detail_pics']??'';
                }
                
                $data['status'] = $info['status'];
                $data['detail'] = $info['detail'];
                if(!$kecheng) $data['createtime'] = time();
                $data['price']        = $info['price'];
                $data['market_price'] = $info['market_price'];
                $data['isdt']         = 0;

                $data['lvprice'] = $info['lvprice']??0;
                if($info['lvprice']==1){
                    $lvprice_data = [];
                    if($levellist){
                        foreach($levellist as $lv){
                        	$lvprice_data[$lv['id']] = ['money_price'=>$info['lvprice_data'.$lv['id']]];
                        }
                    }
                    $data['lvprice_data'] = $lvprice_data?jsonEncode($lvprice_data):$lvprice_data;
                    $data['price'] = array_values($lvprice_data)[0]['money_price'];
                }

                $data['lecturerid'] = $lecturerid;
                $data['chaptertype']     = 2;//是否关联章节 1：关联章节 2：不关联章节（不关联章节默认创建一个默认章节）
                $data['isdt']        = 0;//无答题
                $data['freecontent'] = $info['freecontent'];

                if($kecheng){
                    Db::name('kecheng_list')->where('id',$kecheng['id'])->where('aid',aid)->update($data);
                    $proid = $kecheng['id'];
                    //\app\commons\System::plog('讲师课程内容编辑'.$proid);
                }else{
                    $data['aid'] = aid;
                    $data['bid'] = 0;
                    $proid = Db::name('kecheng_list')->insertGetId($data);
                    //\app\commons\System::plog('课程内容编辑'.$proid);
                }

                //讲师模式 关联的章节s
                if($chapterid){
                    $chapter = Db::name('kecheng_chapter')->where('id',$chapterid)->where('aid',aid)->find();
                    if(!$chapter){
                        $chapterid = 0;
                    }
                }
                $data = array();
                $data['name']    = $info['name'];
                $data['pic']     = $info['pic'];
                $data['kcid']    = $proid;
                $data['sort']    = 0;
                $data['status']  = 1;
                $data['detail']  = $info['detail'];
                //$data['jumpurl'] = $info['jumpurl'];
                $data['kctype']  = $info['kctype'];
                $data['freecontent']  = $info['freecontent'];

                if($data['kctype']==1) $data['video_duration'] = '';
                if($data['kctype']==2) $data['video_duration'] = $info['voice_duration'];
                if($data['kctype']==3) $data['video_duration'] = $info['video_duration'];
                if($data['kctype']==1){
                    $data['voice_url'] = '';
                    $data['video_url'] = '';
                }

                $data['ismianfei'] = $info['ismianfei']??0;
                if ($data['ismianfei'] == 1) {
                    if(getcustom('video_free_time')){
                        $data['mianfei_unit'] = $info['mianfei_unit']??1;
                        $mianfei_time = intval($info['mianfei_time']);
                        $max_time = intval($info['video_duration']);
                        if (!$info['mianfei_time'] || $mianfei_time > $max_time || ($data['mianfei_unit'] == 2 && $info['mianfei_time'] * 60 > $max_time)) {
                            $data['mianfei_time'] = $max_time;
                            $data['mianfei_unit'] = 1;
                        } else {
                            $data['mianfei_time'] = $mianfei_time;
                        }
                    }
                }

                if($data['kctype']==2){
                    $data['voice_url'] = $info['voice_url'];
                    $data['video_url'] = '';
                }
                if($data['kctype']==3){
                    $data['voice_url'] = '';
                    $data['video_url'] = $info['video_url'];
                }
                $data['isjinzhi'] = $info['isjinzhi']??0;
                if(getcustom('video_speed')){
                    $data['isspeed'] = $info['isspeed'];
                }

                $data['chaptertype']     = 2;//是否关联章节 1：关联章节 2：不关联章节（不关联章节默认创建一个默认章节）
                if($chapterid){
                    Db::name('kecheng_chapter')->where('id',$chapterid)->where('aid',aid)->update($data);
                    //\app\commons\System::plog('章节内容编辑'.$proid);
                }else{
                    $data['aid'] = aid;
                    $data['bid'] = 0;
                    $data['createtime'] = time();
                    $chapterid = Db::name('kecheng_chapter')->insertGetId($data);
                    $up = Db::name('kecheng_list')->where('id',$proid)->update(['chapterid'=>$chapterid]);
                    //\app\commons\System::plog('章节内容编辑'.$proid);
                }
                //关联的章节e

                $old_sales = 0;
                if($kecheng){
                    $bid = $kecheng['bid'];
                    $old_sales = $kecheng['join_num'];
                }else{
                    $bid = 0;
                }
                //更新商户虚拟销量
                // $sales = $info['join_num']-$old_sales;
                // if($sales!=0){
                //     \app\models\Payorder::addSales(0,'sales',aid,$bid,$sales);
                // }
                return $this->json(['status'=>1,'msg'=>'提交成功','tourl'=>'']);
            }else{
                if(!$kecheng){
                    $kecheng = ['id'=>'','lvprice'=>'0','status'=>1,'kctype'=>1,'isjinzhi'=>0];
                }

                //分类
                $clist = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('bid',0)->where('pid',0)->where('status',1)->order('sort desc,id')->select()->toArray(); 
                foreach($clist as $k=>$v){
                    $child = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('bid',0)->where('pid',$v['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
                    foreach($child as $k2=>$v2){
                        $child2 = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('bid',0)->where('pid',$v2['id'])->where('status',1)->order('sort desc,id')->select()->toArray();
                        $child[$k2]['child'] = $child2;
                    }
                    $clist[$k]['child'] = $child;
                }
                $cateArr = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->column('name','id');

                $rdata = [];
                $rdata['status'] = 1;
                $rdata['levellist']   = $levellist;
                $rdata['clist']       = $clist;
                $rdata['info']        = $kecheng;
                $rdata['cateArr']     = $cateArr;
                $rdata['pic']         = $kecheng['pic'] ? [$kecheng['pic']] : [];
                $rdata['pics']        = $kecheng['pics'] ? explode(',',$kecheng['pics']) : [];
                $rdata['cids']        = $kecheng['cid'] ? explode(',',$kecheng['cid']) : [];
                $rdata['video_url']   = $kecheng['video_url'] ? $kecheng['video_url'] : '';

                $pagecontent = json_decode(\app\commons\System::initpagecontent($kecheng['detail'],aid),true);
                $rdata['pagecontent'] = $pagecontent?$pagecontent:[];

                $rdata['custom']     = [];
                $rdata['canlvprice'] = false;
                return $this->json($rdata);
            }
        }
    }

    public function lecturerlist(){
        if(getcustom('kecheng_lecturer')){
        	//讲师中心
        	$this->checklogin();
        	//平台权限给课程权限
            $kechengauth = true;
            $admin_user = Db::name('admin_user')->where('aid',aid)->where('isadmin','>',0)->field('auth_type,auth_data')->find();
            
            ll($admin_user,'$admin_user');
            
            if($admin_user['auth_type'] !=1){
                $admin_auth = !empty($admin_user['auth_data'])?json_decode($admin_user['auth_data'],true):[];
                if($admin_user['groupid']){
                    $admin_auth = Db::name('admin_user_group')->where('id',$admin_user['groupid'])->value('auth_data');
                }
                if(!$admin_auth || !in_array('KechengLecturer/index,KechengLecturer/*',$admin_auth)){
                    $kechengauth = false;
                }
            }
            if(!$kechengauth){
            	return $this->json(['status'=>0,'msg'=>'该功能系统暂未开通']); 
            }
            if(request()->isPost()){
                $pagenum = input('post.pagenum');
                if(!$pagenum) $pagenum = 1;
                $pernum = 6;
                $where = [];
                $where[] = ['aid','=',aid];
                $where[] = ['checkstatus','=',1];
                $where[] = ['status','=',1];
                $datalist = Db::name('kecheng_lecturer')->field("id,headimg,nickname,shortdesc")->where($where)->page($pagenum,$pernum)->order('sort desc,id')->select()->toArray();
                return $this->json(['status'=>1,'data'=>$datalist]);
            }
        }
    }
}