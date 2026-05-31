<?php


namespace app\controllers;
use think\facade\Db;
class ApiCycle extends ApiCommon
{
    public $ps_cycle = ['1' => '每日一期','2' => '每周一期' ,'3' => '每月一期'];

    public function index(){
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',1];
		$where[] = ['ischecked','=',1];
		$business_sysset = Db::name('business_sysset')->where('aid',aid)->find();
		if(input('param.bid')){
			$where[] = ['bid','=',input('param.bid')];
		}elseif(!$business_sysset || $business_sysset['product_isshow']==0){
			$where[] = ['bid','=',0];
		}

		//分类
		if(input('param.cid')){
			$where[] = ['cid','=',input('param.cid/d')];
		}
		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}
		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$field = "pic,id,name,sales,market_price,sell_price,sellpoint,fuwupoint,teamnum,buymax,teamhour,leadermoney,leaderscore";

		$datalist = Db::name('cycle_product')->field($field)->where($where)->page($pagenum,$pernum)->order('sort desc,id desc')->select()->toArray();
		if(!$datalist) $datalist = array();

		if($pagenum == 1){
			$pics = Db::name('cycle_sysset')->where('aid',aid)->value('pics');
			if(!$pics) $pics = [];
			$pics = explode(',',$pics);
			$clist = Db::name('collage_category')->where('aid',aid)->where('pid',0)->where('status',1)->limit(8)->order('sort desc,id')->select()->toArray();
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['pics'] = $pics;
		$rdata['clist'] = $clist;
		return $this->json($rdata);
	}
	public function product(){

		$proid = input('param.id/d');
		$where = [];
		$where[] = ['id','=',$proid];
		$where[] = ['aid','=',aid];
		$product = Db::name('cycle_product')->where($where)->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'商品不存在']);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'商品已下架']);
		if($product['ischecked']!=1) return $this->json(['status'=>0,'msg'=>'商品未审核']);
        $favorite = Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','cycle')->find();
        if($favorite){
            $isfavorite = true;
        }else{
            $isfavorite = false;
        }


		if(!$product['pics']) $product['pics'] = $product['pic'];
		$product['pics'] = explode(',',$product['pics']);
		if($product['fuwupoint']){
			$product['fuwupoint'] = explode(' ',preg_replace("/\s+/",' ',str_replace('　',' ',trim($product['fuwupoint']))));
		}
		$gglist = Db::name('cycle_guige')->where('proid',$product['id'])->select()->toArray();
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
        //修改配送时间

        $ps_cycle = ['1' => '每日一期','2' => '每周一期' ,'3' => '每月一期'];
        $product['ps_cycle_title'] = $ps_cycle[$product['ps_cycle']];
        $product['everyday_item'] = json_decode($product['everyday_item'],true);
		//获取评论
		$commentlist = Db::name('cycle_comment')->where('aid',aid)->where('proid',$proid)->where('status',1)->limit(10)->select()->toArray();
		if(!$commentlist) $commentlist = [];
		foreach($commentlist as $k=>$pl){
			$commentlist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
			if($commentlist[$k]['content_pic']) $commentlist[$k]['content_pic'] = explode(',',$commentlist[$k]['content_pic']);
		}
		$commentcount = Db::name('cycle_comment')->where('aid',aid)->where('proid',$proid)->where('status',1)->count();
		
		$shopset = Db::name('cycle_sysset')->field('comment')->where('aid',aid)->find();
		$sysset = Db::name('admin_set')->field('name,logo,desc,tel,kfurl')->where('aid',aid)->find();

		$product['detail'] = \app\commons\System::initpagecontent($product['detail'],aid,mid,platform);
        $product['comment_starnum'] = floor($product['comment_score']);

		if($product['bid']!=0){
			$business = Db::name('business')->where('aid',aid)->where('id',$product['bid'])->field('id,aid,cid,name,logo,desc,tel,address,sales,kfurl')->find();
			if(!$business){
				return $this->json(['status'=>0,'msg'=>'商家不存在']);
			}
		}else{
			$business = $sysset;
		}
		//
        $now_hour = date('H:i:s');

        $advance_pay_time = $product['advance_pay_time'] < 10? '0'.$product['advance_pay_time']:$product['advance_pay_time'];
        if($now_hour > $advance_pay_time.':00:00'){
            $product['advance_pay_days']=$product['advance_pay_days']+1;
        }
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['product'] = $product;
		$rdata['business'] = $business;
		$rdata['guigelist'] = $guigelist;
		$rdata['guigedata'] = $guigedata;
		$rdata['ggselected'] = $ggselected;
		$rdata['ks'] = $ks;
		$rdata['commentlist'] = $commentlist;
		$rdata['commentcount'] = $commentcount;
		$rdata['shopset'] = $shopset;
		$rdata['sysset'] = $sysset;
		$rdata['nowtime'] = time();
		$rdata['status'] = 1;
        $rdata['isfavorite'] = $isfavorite;
		return $this->json($rdata);
	}
    //商品海报
    function getposter(){
        $this->checklogin();
        $post = input('post.');
        $platform = platform;
        $page = 'pagesExt/cycle/product';
        $scene = 'id_'.$post['proid'].'-pid_'.$this->member['id'];
        //if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
        //	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
        //}
        $posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','cycle')->where('platform',$platform)->order('id')->find();
     
        $posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','cycle')->where('posterid',$posterset['id'])->find();
        if(!$posterdata){
            $product = Db::name('cycle_product')->where('id',$post['proid'])->find();
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
            $posterdata['type'] = 'cycle';
            $posterdata['poster'] = $poster;
            $posterdata['posterid'] = $posterset['id'];
            $posterdata['createtime'] = time();
            Db::name('member_poster')->insert($posterdata);
        }
        return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
    }
	//商品评价
	public function commentlist(){
		$proid = input('param.proid/d');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['proid','=',$proid];
		$where[] = ['status','=',1];
		$datalist = Db::name('cycle_comment')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
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
	public function category(){
		$datalist = Db::name('collage_category')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$rdata = [];
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}
    public function getprolist(){
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['status','=',1];
        $where[] = ['ischecked','=',1];

		if(input('param.bid')){
			$where[] = ['bid','=',input('param.bid/d')];
		}

        //分类
        $searchcid = input('param.cid');
		$cid = input('param.cid/d');
		if(!$cid && input('param.cid2')){
			$cid = input('param.cid2/d');
		}
        if($cid){
            //子分类
            $clist = Db::name('cycle_category')->where('aid',aid)->where('pid',$cid)->select()->toArray();
            if($clist){
                $cateArr = [$cid];
                foreach($clist as $c){
                    $cateArr[] = $c['id'];
                }
                $where[] = ['cid','in',$cateArr];
            }else{
                $where[] = ['cid','=',$cid];
                $pid = Db::name('cycle_category')->where('aid',aid)->where('id',$cid)->value('pid');
                if($pid){
                    $searchcid = $pid;
                    $clist = Db::name('cycle_category')->where('aid',aid)->where('pid',$pid)->select()->toArray();
                }
            }
        }
        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order').',sort desc,id desc';
        }else{
            $order = 'sort desc,id desc';
        }
        if(input('param.keyword')){
            $where[] = ['name','like','%'.input('param.keyword').'%'];
        }
        $pernum = 20;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;


        $datalist = Db::name('cycle_product')->where($where)->page($pagenum,$pernum)->order($order)->select()->toArray();
        if(!$datalist) $datalist = array();
        foreach($datalist as $key=>&$val){
       
            $val['ps_cycle_title'] = $this->ps_cycle[$val['ps_cycle']];
        }
        
        if(request()->isPost()){
            return $this->json(['status'=>1,'data'=>$datalist]);
        }

        $rdata = [];
        $rdata['clist'] = $clist;
        $rdata['searchcid'] = $searchcid;
        $rdata['datalist'] = $datalist;
        return $this->json($rdata);
    }
	public function prolist(){
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['status','=',1];
		$where[] = ['ischecked','=',1];
		//分类
		$searchcid = input('param.cid');
		
        $cid = input('param.cid/d')?input('param.cid/d'):0;
        //子分类
        $clist = Db::name('cycle_category')->where('aid',aid)->where('pid',$cid)->select()->toArray();
        if($clist){
            $cateArr = [$cid];
            foreach($clist as $c){
                $cateArr[] = $c['id'];
            }
            $where[] = ['cid','in',$cateArr];
        }else{
            $where[] = ['cid','=',$cid];
            $pid = Db::name('cycle_category')->where('aid',aid)->where('id',$cid)->value('pid');
            if($pid){
                $searchcid = $pid;
                $clist = Db::name('cycle_category')->where('aid',aid)->where('pid',$pid)->select()->toArray();
            }
        }
	
		if(input('param.keyword')){
			$where[] = ['name','like','%'.input('param.keyword').'%'];
		}
		$pernum = 20;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
	
	
		$datalist = Db::name('cycle_product')->where($where)->page($pagenum,$pernum)->order('sort desc,id desc')->select()->toArray();
		if(!$datalist) $datalist = array();

		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}

		$rdata = [];
		$rdata['clist'] = $clist;
        $rdata['clist2'] = $clist;
		$rdata['searchcid'] = $searchcid;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}
	public function buy(){
		$this->checklogin();

        $prodata = explode(',',input('param.prodata'));
        $proid = $prodata[0];
        $ggid = $prodata[1];
        $totalnum =  $prodata[2];

        $qsdata =  explode(',',input('param.qsdata'));
        $startDate = $qsdata[0];
        $qsnum = $qsdata[1];
        $pspl = $qsdata[2];

        if(!$totalnum) $totalnum = 1;
        if(!$qsnum) $qsnum = 1;
        $qs =  $qsnum;

        $product = Db::name('cycle_product')->where('aid',aid)->where('status',1)->where('ischecked',1)->where('id',$proid)->find();
        //验证最小起订数
        if($qs < $product['min_qsnum']){
            return $this->json(['status'=>0,'msg'=>'最少'.$product['min_qsnum'].'期起订']);
        }
        //验证最小起订数
        if($totalnum < $product['min_num']){
            return $this->json(['status'=>0,'msg'=>'最少'.$product['min_num'].'件起订']);
        }
        //获取是按天还是按周还是按照月 进行时间计算
        $endDate = [];
        $date = $this->computePsplDate($product['ps_cycle'],$qs,$startDate,$pspl);
        
        if(!$product){
			return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
		}
		$guige = Db::name('cycle_guige')->where('id',$ggid)->find();
		if(!$guige){
			return $this->json(['status'=>0,'msg'=>'产品该规格不存在或已下架']);
		}
        if($guige['stock'] < $totalnum * $qsnum){
            return $this->json(['status'=>0,'msg'=>'库存不足']);
        }


		$bid = $product['bid'];
		if($bid!=0){
			$business = Db::name('business')->where('id',$bid)->field('id,aid,cid,name,logo,tel,address,sales,longitude,latitude')->find();
		}else{
			$business = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,desc,tel')->find();
		}
		$product_price = bcmul( bcmul($guige['sell_price'] , $totalnum ,2) , $qsnum,2);
		$totalweight = $guige['weight'] * $totalnum;
		if($product['freighttype']==0){
			$fids = explode(',',$product['freightdata']);
			$freightList = \app\models\Freight::getList([['status','=',1],['aid','=',aid],['bid','=',$bid],['id','in',$fids]]);
		}elseif($product['freighttype']==3 || $product['freighttype']==4){
			$freightList = [['id'=>0,'name'=>($product['freighttype']==3?'自动发货':'在线卡密'),'pstype'=>$product['freighttype']]];
		}else{
			$freightList = \app\models\Freight::getList([['status','=',1],['aid','=',aid],['bid','=',$bid]]);
		}

		$havetongcheng = 0;
		foreach($freightList as $k=>$v){
			if($v['pstype']==2){ //同城配送
				$havetongcheng = 1;
			}
		}
		if($havetongcheng){
			$address = Db::name('member_address')->where('aid',aid)->where('mid',mid)->where('latitude','>',0)->order('isdefault desc,id desc')->find();
		}else{
			$address = Db::name('member_address')->where('aid',aid)->where('mid',mid)->order('isdefault desc,id desc')->find();
		}
		if(!$address) $address = [];

		$needLocation = 0;

		$rs = \app\models\Freight::formatFreightList($freightList,$address,$product_price,$totalnum,$totalweight);

		$freightList = $rs['freightList'];
		$freightArr = $rs['freightArr'];
		if($rs['needLocation']==1) $needLocation = 1;


		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		$adminset = Db::name('admin_set')->where('aid',aid)->find();
		$userinfo = [];
		$userinfo['discount'] = $userlevel['discount'];
		$userinfo['score'] = $this->member['score'];
		$userinfo['score2money'] = $adminset['score2money'];
		$userinfo['scoredk_money'] = round($userinfo['score'] * $userinfo['score2money'],2);
		$userinfo['scoredkmaxpercent'] = $adminset['scoredkmaxpercent'];
		$userinfo['realname'] = $this->member['realname'];
		$userinfo['tel'] = $this->member['tel'];
		$totalprice = $product_price;
		if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$leveldk_money = $product_price * (1 - $userlevel['discount'] * 0.1);
		}
		$leveldk_money = round($leveldk_money,2);
		$totalprice = $totalprice - $leveldk_money;

		$couponList = Db::name('coupon_record')->where("bid=-1 or bid=".$bid)->where('aid',aid)->where('mid',mid)->where('type','in','1,4')->where('status',0)->where('minprice','<=',$totalprice)->where('starttime','<=',time())->where('endtime','>',time())->order('id desc')->select()->toArray();
		if(!$couponList) $couponList = [];
		foreach($couponList as $k=>$v){
			//$couponList[$k]['starttime'] = date('m-d H:i',$v['starttime']);
			//$couponList[$k]['endtime'] = date('m-d H:i',$v['endtime']);
			$couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$v['couponid'])->find();
			if($v['bid'] > 0){
				$binfo = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->find();
				$couponList[$k]['bname'] = $binfo['name'];
			}

			if(empty($couponinfo) || $couponinfo['fwtype']!==0 || $couponinfo['fwscene']!==0){
				unset($couponList[$k]);
			}
			
            if($couponinfo['isgive'] == 2){
                unset($couponList[$k]);
            }
		}
		$couponList = array_values($couponList);

		$rdata = [];
		$rdata['havetongcheng'] = $havetongcheng;
		$rdata['status'] = 1;
		$rdata['address'] = $address;
		$rdata['linkman'] = $address ? $address['name'] : strval($userinfo['realname']);
		$rdata['tel'] = $address ? $address['tel'] : strval($userinfo['tel']);
		if(!$rdata['linkman']){
			$lastorder = Db::name('cycle_order')->where('aid',aid)->where('mid',mid)->where('linkman','<>','')->find();
			if($lastorder){
				$rdata['linkman'] = $lastorder['linkman'];
				$rdata['tel'] = $lastorder['tel'];
			}
		}
		//处理日期列表
        $endDataList = [];
        foreach($endDate as $key=>$val){
            $qs = $key+1;
            $endDataList[$key]['title'] = '第'.$qs.'期';
            $endDataList[$key]['cycle_date'] = $val;
        }
		$rdata['product'] = $product;
		$rdata['guige'] = $guige;
		$rdata['business'] = $business;
		$rdata['freightList'] = $freightList;
		$rdata['freightArr'] = $freightArr;
		$rdata['userinfo'] = $userinfo;
		$rdata['couponList'] = $couponList;
		$rdata['totalnum'] = $totalnum;
		$rdata['qsnum'] = $qsnum;
		$rdata['start_date'] = $date['start_date'];
		$rdata['end_date'] =  $date['end_date'];;
		$rdata['pspl'] = $pspl;

		$rdata['product_price'] = $product_price;
		$rdata['leveldk_money'] = $leveldk_money;
		$rdata['needLocation'] = $needLocation;
		$rdata['scorebdkyf'] = Db::name('admin_set')->where('aid',aid)->value('scorebdkyf');
		return $this->json($rdata);
	}

     public function getDateList(){
         $ps_cycle = input('param.ps_cycle');
         $pspl = input('param.pspl');
         $startDate = input('param.start_date');
         $qs = input('param.qsnum');
         $date = $this->computePsplDate($ps_cycle,$qs,$startDate,$pspl);
         //处理日期列表
         $endDataList = [];
         foreach($date['date_list'] as $key=>$val){
             $qs = $key+1;
             $endDataList[$key]['title'] = '第'.$qs.'期';
             $endDataList[$key]['cycle_date'] = $val;
         }
         return $this->json(['status' => 1,'msg' => '成功','data' => $endDataList]);
     }
     
     /**
      * 计算配送日期   
      * @param $ps_cycle  配送类型 1：按日 2：按周 3：按月
      * @param $pspl 按天的 套餐
      * @param $qs 期数 
      * @param $startDate 开始日期 
      * 
      */
     public function computePsplDate($ps_cycle,$qs,$startDate,$pspl){
         $end_date='';
         switch ($ps_cycle){
             case 1://按天
                 //进行判断 工作日 等

                 if($pspl == 1){//每天配送
                     for($i =0;$i < $qs ;$i++){
                         $endDate[] =  $end_date = date('Y-m-d',strtotime($startDate)+$i*86400) ;
                     }
                 }elseif ($pspl == 2){//工作日配送
                     for($i =0;$i < $qs ;$i++){
                         $date =  strtotime($startDate)+$i *86400;
                         $w = date("w",$date);

                         if($w == 0 || $w ==6){
                             $qs = $qs+1;
                             continue;
                         }
                         $endDate[] = $end_date = date('Y-m-d',$date);;

                     }
                 }else if($pspl == 3){//周末配送

                     for($i =0;$i < $qs ;$i++){
                         $date =  strtotime($startDate)+$i *86400;
                         $w = date("w",$date);

                         if($w == 0 || $w ==6){
                             $endDate[] = $end_date = date('Y-m-d',$date);;

                         }else{
                             $qs = $qs+1;
                         }
                     }

                 }else{//隔天配送
//                    $end_date = date('Y-m-d',strtotime($startDate)+ 2*86400) ;
                     $end_date = date('Y-m-d',strtotime($startDate)) ;
                     $endDate[]= $end_date;
                     for($i =1;$i < $qs ;$i++){
                         $endDate[] = $end_date =  date('Y-m-d',strtotime($end_date)+ 2*86400) ;
                     }
                 }
                 break;
             case 2://按周 
                 for($i =0;$i < $qs ;$i++){
                     $endDate1=  date('Y-m-d',strtotime($startDate)+ $i*7*86400) ;
                     $endDate[] = $end_date = $endDate1;
                 }
                 break;
             case 3://按照 月
                 $d = date('j',strtotime($startDate));
                 $m = date('n',strtotime($startDate));
                 $y = date('Y',strtotime($startDate));

                 for($i =0;$i < $qs ;$i++){
                     //计算当前月的天数是多少，如果选择的天数大于当前月的天数，就是用当前月的天数
                     $month_days = $this->computeMonthDays($m,$y);
                     if($month_days < $d){
                         $n_d  =  $month_days ;
                     }  else{
                         $n_d = $d ;
                     }
                     $endDate[] = $end_date=  date('Y-m-d',strtotime($y.'-'.$m.'-'.$n_d)) ;
                     $m = $m+1;
                     if($m > 12){
                         $m =1;
                         $y++;
                     }
                 }
                 break;
             default:
                 break;
         }
         return ['start_date' =>$startDate,'end_date' =>$end_date,'date_list' =>$endDate];
     }
    /**
     * @title 计算每个月的天数  
     * $month 月份
     *  $year   年份（判断2月的闰月）
     */ 
     public function computeMonthDays($month,$year){
         switch($month){
             
             case 1:
           
             case 3:

             case 5:

             case 7:

             case 8:

             case 10:

             case 12:

                 return 31;
                 break;

             case 4:

             case 6:

             case 9:

             case 11:
                return 30;
                break;
             case 2:
                return  ($year % 400 == 0 || $year % 4 == 0 && $year % 100 != 0)? 29:28;
                break;
         }
     }
     
	public function createOrder(){
		$this->checklogin();
        $post = input('post.');
        $prodata = explode(',',input('param.prodata'));
        $proid = $prodata[0];
        $ggid = $prodata[1];
        $num =  $prodata[2];

        $qsdata =  explode(',',input('param.qsdata'));
        $startDate = $qsdata[0];
        $qsnum = $qsdata[1];
        $pspl = $qsdata[2];

		if($proid && $ggid){
			$num = intval($num) ?intval($num) : 1;
		}else{
			return $this->json(['status'=>0,'msg'=>'产品数据错误']);
		}
		if($startDate && $pspl){
            $qsnum = $qsnum?$qsnum:1;
        }
		$product_price = 0;
		$weight = 0;//重量
        $qs = $qsnum;
		$product = Db::name('cycle_product')->where('aid',aid)->where('status',1)->where('ischecked',1)->where('id',$proid)->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
		$bid = $product['bid'];

		$guige = Db::name('cycle_guige')->where('aid',aid)->where('id',$ggid)->find();
		if(!$guige) return $this->json(['status'=>0,'msg'=>'产品规格不存在或已下架']);
		if($guige['stock'] < $num * $qsnum){
			return $this->json(['status'=>0,'msg'=>'库存不足']);
		}
//		 //验证最小起订数
        if($qsnum < $product['min_qsnum']){
            return $this->json(['status'=>0,'msg'=>'最少'.$product['min_qsnum'].'期起订']);
        }
        //验证最小起订数
        if($num < $product['min_num']){
            return $this->json(['status'=>0,'msg'=>'最少'.$product['min_num'].'件起订']);
        }
		//提前几天下单，超过x点就加一天
        $ys_date = date('Y-m-d', strtotime ( "+".$product['advance_pay_days']." day" ));
		$select_date =  $post['start_date'];
        $now_hour = date('H:i:s');
       
        $advance_pay_time = $product['advance_pay_time'] < 10? '0'.$product['advance_pay_time']:$product['advance_pay_time'];
        if($now_hour > $advance_pay_time.':00:00'){
//            $select_date = date('Y-m-d',strtotime($post['start_date']) + 86400) ;
            $advance_pay_days=$product['advance_pay_days']+1;
            $ys_date = date('Y-m-d', strtotime ( "+".$advance_pay_days." day" ));
        }
        
        if($select_date < $ys_date){
            return $this->json(['status'=>0,'msg'=>'当前产品需要提前'.$product['advance_pay_days'].'天，并且在'.$product['advance_pay_time'].'点前下单']);
        }

		$product_price += $guige['sell_price'] * $num * $qsnum;

		$weight += $guige['weight'] * $num ;
		$totalprice = $product_price ;
		if($totalprice<0) $totalprice = 0;

		//收货地址
		if($post['addressid']=='' || $post['addressid']==0){
			$address = ['id'=>0,'name'=>$post['linkman'],'tel'=>$post['tel'],'area'=>'','address'=>''];
		}else{
			$address = Db::name('member_address')->where('id',$post['addressid'])->where('aid',aid)->where('mid',mid)->find();
		}

		//会员折扣
		$leveldk_money = 0;
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$this->member['levelid'])->find();
		if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$leveldk_money = $totalprice * (1 - $userlevel['discount'] * 0.1);
		}
		$totalprice = $totalprice - $leveldk_money;


		//运费
		$freight_price = 0;
		if($post['freightid']){
			$freight = Db::name('freight')->where('aid',aid)->where('bid',$bid)->where('id',$post['freightid'])->find();
			if(($address['name']=='' || $address['tel'] =='') && ($freight['pstype']==1 || $freight['pstype']==3) && $freight['needlinkinfo']==1){
				return $this->json(['status'=>0,'msg'=>'请填写联系人和联系电话']);
			}

			$rs = \app\models\Freight::getFreightPrice($freight,$address,$product_price,$num,$weight);
			if($rs['status']==0) return $this->json($rs);
			$freight_price = $rs['freight_price'];

			//判断配送时间选择是否符合要求
			if($freight['pstimeset']==1){
				//$freighttime = strtotime(explode('~',$post['freight_time'])[0]);
				$freight_times = explode('~',$post['freight_time']);
				if($freight_times[1]){
					$freighttime = strtotime(explode(' ',$freight_times[0])[0] . ' '.$freight_times[1]);
				}else{
					$freighttime = strtotime($freight_times[0]);
				}
//				if(time() + $freight['psprehour']*3600 > $freighttime){
//					return $this->json(['status'=>0,'msg'=>(($freight['pstype']==0 || $freight['pstype']==2 || $freight['pstype']==10)?'配送':'提货').'时间必须在'.$freight['psprehour'].'小时之后']);
//				}
			}
		}elseif($product['freighttype']==3){
			$freight = ['id'=>0,'name'=>'自动发货','pstype'=>3];
		}elseif($product['freighttype']==4){
			$freight = ['id'=>0,'name'=>'在线卡密','pstype'=>4];
		}else{
			$freight = ['id'=>0,'name'=>'包邮','pstype'=>0];
		}
		//优惠券
		if($post['couponrid'] > 0){
			$couponrid = $post['couponrid'];
			$couponrecord = Db::name('coupon_record')->where('aid',aid)->where('bid',$bid)->where('mid',mid)->where('id',$couponrid)->find();
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
            if(empty($couponinfo)){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不存在或已作废']);
            }
            if($couponrecord['from_mid']==0 && $couponinfo && $couponinfo['isgive']==2){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'仅可转赠']);
            }
			if($couponinfo['fwtype']!==0){
				return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
			}
            if($couponinfo['fwscene']!==0){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不符合条件']);
            }

			Db::name('coupon_record')->where('id',$couponrid)->update(['status'=>1,'usetime'=>time()]);
			if($couponrecord['type']==4){//运费抵扣券
				$coupon_money = $freight_price;
			}else{
				$coupon_money = $couponrecord['money'];
				if($coupon_money > $totalprice) $coupon_money = $totalprice;
			}
		}else{
			$coupon_money = 0;
		}
		$totalprice = $totalprice - $coupon_money;
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
			if($scoredkmaxpercent >= 0 && $scoredkmaxpercent < 100 && $scoredk_money > 0 && $scoredk_money > $totalprice * $scoredkmaxpercent * 0.01){
				$scoredk_money = $totalprice * $scoredkmaxpercent * 0.01;
			}
			$totalprice = $totalprice - $scoredk_money;
			$totalprice = round($totalprice*100)/100;
			if($scoredk_money > 0){
				$scoredkscore = intval($scoredk_money / $score2money);
			}
		}


		$orderdata = [];
		$orderdata['aid'] = aid;
		$orderdata['bid'] = $bid;
		$orderdata['mid'] = mid;

		$ordernum = date('ymdHis').aid.rand(1000,9999);
		$orderdata['ordernum'] = $ordernum;
		$orderdata['title'] = $product['name'];

		$orderdata['proid'] = $product['id'];
		$orderdata['proname'] = $product['name'];
		$orderdata['propic'] = $product['pic'];
		$orderdata['ggid'] = $guige['id'];
		$orderdata['ggname'] = $guige['name'];
		$orderdata['cost_price'] = $guige['cost_price'];
		$orderdata['sell_price'] = $guige['sell_price'];
		$orderdata['num'] = $num;

		$orderdata['linkman'] = $address['name'];
		$orderdata['tel'] = $address['tel'];
		$orderdata['area'] = $address['area'];
		$orderdata['area2'] = $address['province'].','.$address['city'].','.$address['district'];
		$orderdata['address'] = $address['address'];
		$orderdata['longitude'] = $address['longitude'];
		$orderdata['latitude'] = $address['latitude'];
		$orderdata['totalprice'] = $totalprice;
		$orderdata['qsnum'] = $qsnum;//购买期数
        $orderdata['start_date'] =$startDate;//开始日期
        $orderdata['ps_cycle'] = $product['ps_cycle'];
        $orderdata['fwtc'] = $product['ps_cycle'] == 1?$pspl:0;
		$orderdata['product_price'] = $product_price;
		$orderdata['freight_price'] = $freight_price; //运费
		$orderdata['leveldk_money'] = $leveldk_money;  //会员折扣
		$orderdata['scoredk_money'] = $scoredk_money;	//积分抵扣
		$orderdata['scoredkscore'] = $scoredkscore;	//抵扣的积分
       
		if($freight && ($freight['pstype']==0 || $freight['pstype']==10)){
			$orderdata['freight_text'] = $freight['name'].'('.$freight_price.'元)';
			$orderdata['freight_type'] = $freight['pstype'];
		}elseif($freight && $freight['pstype']==1){
			$storename = Db::name('mendian')->where('aid',aid)->where('id',$post['storeid'])->value('name');
			$orderdata['freight_text'] = $freight['name'].'['.$storename.']';
			$orderdata['freight_type'] = 1;
			$orderdata['mdid'] = $post['storeid'];
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
		$orderdata['freight_time'] = $post['freight_time']; //配送时间
		$orderdata['createtime'] = time();
		$orderdata['coupon_rid'] = $couponrid;
		$orderdata['coupon_money'] = $coupon_money; //优惠券抵扣

	
		$orderdata['platform'] = platform;

        if($product['bid'] > 0) {
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			$scoredkmoney = $scoredk_money ?? 0;
			if($bset['scoredk_kouchu'] == 0){ //扣除积分抵扣
				$scoredkmoney = 0;
			}
            $business_feepercent = Db::name('business')->where('aid',aid)->where('id',$product['bid'])->value('feepercent');
            $totalprice_business = $product_price + $freight_price - $coupon_money;
            if($bset['scoredk_kouchu']==1){
                $totalprice_business = $totalprice_business - $scoredkmoney;
            }
            //商品独立费率
            if($product['feepercent'] != '' && $product['feepercent'] != null && $product['feepercent'] >= 0) {
                $orderdata['business_total_money'] = $totalprice_business * (100-$product['feepercent']) * 0.01;
                } else {
                //商户费率
                $orderdata['business_total_money'] = $totalprice_business * (100-$business_feepercent) * 0.01;
                }
        }

		//计算佣金的商品金额
		//$commission_totalprice = $orderdata['totalprice'];
		$commission_totalprice = $product_price;
		//算佣金
		$sysset = Db::name('admin_set')->where('aid',aid)->find();
		if($sysset['fxjiesuantype'] == 1 || $sysset['fxjiesuantype'] == 2){
            $commission_totalprice = $product_price - $leveldk_money - $scoredk_money;
            if($couponrecord['type']!=4) {//运费抵扣券
                $commission_totalprice -= $coupon_money;
            }
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
						$orderdata['parent1'] = $parent1['id'];
					}
				}
			}
			if($parent1['pid']){
				$parent2 = Db::name('member')->where('aid',aid)->where('id',$parent1['pid'])->find();
				if($parent2){
					$agleveldata2 = Db::name('member_level')->where('aid',aid)->where('id',$parent2['levelid'])->find();
					if($agleveldata2['can_agent']>1){
						$orderdata['parent2'] = $parent2['id'];
					}
				}
			}
			if($parent2['pid']){
				$parent3 = Db::name('member')->where('aid',aid)->where('id',$parent2['pid'])->find();
				if($parent3){
					$agleveldata3 = Db::name('member_level')->where('aid',aid)->where('id',$parent3['levelid'])->find();
					if($agleveldata3['can_agent']>2){
						$orderdata['parent3'] = $parent3['id'];
					}
				}
			}
			if($product['commissionset']==1){//按商品设置的分销比例
				$commissiondata = json_decode($product['commissiondata1'],true);
				if($commissiondata){
					if($agleveldata1) $orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
					if($agleveldata2) $orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
					if($agleveldata3) $orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
				}
			}elseif($product['commissionset']==2){//按固定金额
				$commissiondata = json_decode($product['commissiondata2'],true);
				if($commissiondata){
					if($agleveldata1) $orderdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'];
					if($agleveldata2) $orderdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'];
					if($agleveldata3) $orderdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'];
				}
			}elseif($product['commissionset']==3){//提成是积分
				$commissiondata = json_decode($product['commissiondata3'],true);
				if($commissiondata){
					if($agleveldata1) $orderdata['parent1score'] = $commissiondata[$agleveldata1['id']]['commission1'];
					if($agleveldata2) $orderdata['parent2score'] = $commissiondata[$agleveldata2['id']]['commission2'];
					if($agleveldata3) $orderdata['parent3score'] = $commissiondata[$agleveldata3['id']]['commission3'];
				}
			}else{ //按会员等级设置的分销比例
				if($agleveldata1){
					if($agleveldata1['commissiontype']==1){ //固定金额按单
						$orderdata['parent1commission'] = $agleveldata1['commission1'];
					}else{
						$orderdata['parent1commission'] = $agleveldata1['commission1'] * $commission_totalprice * 0.01;
					}
				}
				if($agleveldata2){
					if($agleveldata2['commissiontype']==1){
						$orderdata['parent2commission'] = $agleveldata2['commission2'];
					}else{
						$orderdata['parent2commission'] = $agleveldata2['commission2'] * $commission_totalprice * 0.01;
					}
				}
				if($agleveldata3){
					if($agleveldata3['commissiontype']==1){
						$orderdata['parent3commission'] = $agleveldata3['commission3'];
					}else{
						$orderdata['parent3commission'] = $agleveldata3['commission3'] * $commission_totalprice * 0.01;
					}
				}
			}

		}

		$orderid = Db::name('cycle_order')->insertGetId($orderdata);
		
        $date = $this->computePsplDate($product['ps_cycle'],$qs,$startDate,$pspl);
        $endDate =$date['date_list'];
        
//		//设置周期 期数订单
        $qsOrderData = [];

        foreach ($endDate as $key=>$val){
            $zqordernum = date('ymdHis').aid.rand(1000,9999);
            $qsOrderData[$key]['aid'] = aid;
            $qsOrderData[$key]['bid'] =$bid;
            $qsOrderData[$key]['mid'] = mid;
            $qsOrderData[$key]['orderid'] = $orderid;
            $qsOrderData[$key]['ordernum'] = $zqordernum;
            $qsOrderData[$key]['cycle_date'] = $val;
            $qsOrderData[$key]['cycle_strtotime'] = strtotime($val);
            $qsOrderData[$key]['cycle_number'] = $key+1;
            $qsOrderData[$key]['longitude'] = $orderdata['longitude'];
            $qsOrderData[$key]['latitude'] = $orderdata['latitude'];
            $qsOrderData[$key]['latitude'] = $orderdata['latitude'];
            $qsOrderData[$key]['proname'] = $orderdata['proname'];
            $qsOrderData[$key]['ggname'] = $orderdata['ggname'];
            $qsOrderData[$key]['propic'] = $orderdata['propic'];
            $qsOrderData[$key]['sell_price'] = $orderdata['sell_price'];
            $qsOrderData[$key]['num'] = $orderdata['num'];
            $qsOrderData[$key]['hexiao_code'] = random(16);
            $qsOrderData[$key]['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=cycle&co='.$qsOrderData[$key]['hexiao_code']));
        }
        Db::name('cycle_order_stage')->insertAll($qsOrderData);

		if($orderdata['parent1'] && ($orderdata['parent1commission'] || $orderdata['parent1score'])){
			Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent1'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$product['id'],'type'=>'cycle','commission'=>$orderdata['parent1commission'],'score'=>$orderdata['parent1score'],'remark'=>'下级购买商品奖励','createtime'=>time()]);
		}
		if($orderdata['parent2'] && ($orderdata['parent2commission'] || $orderdata['parent2score'])){
			Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent2'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$product['id'],'type'=>'cycle','commission'=>$orderdata['parent2commission'],'score'=>$orderdata['parent2score'],'remark'=>'下二级购买商品奖励','createtime'=>time()]);
		}
		if($orderdata['parent3'] && ($orderdata['parent3commission'] || $orderdata['parent3score'])){
			Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$orderdata['parent3'],'frommid'=>mid,'orderid'=>$orderid,'ogid'=>$product['id'],'type'=>'cycle','commission'=>$orderdata['parent3commission'],'score'=>$orderdata['parent3score'],'remark'=>'下三级购买商品奖励','createtime'=>time()]);
		}

		\app\models\Freight::saveformdata($orderid,'cycle_order',$freight['id'],$post['formdata']);

		$payorderid = \app\models\Payorder::createorder(aid,$orderdata['bid'],$orderdata['mid'],'cycle',$orderid,$ordernum,$orderdata['title'],$orderdata['totalprice'],$orderdata['scoredkscore']);

		//减库存加销量
		$stock = $guige['stock'] - $num * $qsnum;
		if($stock < 0) $stock = 0;
		$pstock = $product['stock'] -  $num * $qsnum;
		if($pstock < 0) $pstock = 0;
		$sales = $guige['sales'] + $num * $qsnum;
		$psales = $product['sales'] + $num * $qsnum;
		Db::name('cycle_guige')->where('aid',aid)->where('id',$guige['id'])->update(['stock'=>$stock,'sales'=>$sales]);
		Db::name('cycle_product')->where('aid',aid)->where('id',$product['id'])->update(['stock'=>$pstock,'sales'=>$psales]);

        $store_name = Db::name('admin_set')->where('aid',aid)->value('name');
		//公众号通知 订单提交成功
		$tmplcontent = [];
		$tmplcontent['first'] = '有周期购订单提交成功';
		$tmplcontent['remark'] = '点击进入查看~';
		$tmplcontent['keyword1'] = $store_name; //店铺
		$tmplcontent['keyword2'] = date('Y-m-d H:i:s',$orderdata['createtime']);//下单时间
		$tmplcontent['keyword3'] = $orderdata['title'];//商品
		$tmplcontent['keyword4'] = $orderdata['totalprice'].'元';//金额
        $tempconNew = [];
        $tempconNew['character_string2'] = $orderdata['ordernum'];//订单号
        $tempconNew['thing8'] = $store_name;//门店名称
        $tempconNew['thing3'] = $orderdata['title'];//商品名称
        $tempconNew['amount7'] = $orderdata['totalprice'];//金额
        $tempconNew['time4'] = date('Y-m-d H:i:s',$orderdata['createtime']);//下单时间
		\app\commons\Wechat::sendhttmpl(aid,$orderdata['bid'],'tmpl_orderconfirm',$tmplcontent,m_url('admin/order/cycleorder'),$orderdata['mdid'],$tempconNew);

		$tmplcontent = [];
		$tmplcontent['thing11'] = $orderdata['title'];
		$tmplcontent['character_string2'] = $orderdata['ordernum'];
		$tmplcontent['phrase10'] = '待付款';
		$tmplcontent['amount13'] = $orderdata['totalprice'].'元';
		$tmplcontent['thing27'] = $this->member['nickname'];
		\app\commons\Wechat::sendhtwxtmpl(aid,$orderdata['bid'],'tmpl_orderconfirm',$tmplcontent,'admin/order/cycleorder',$orderdata['mdid']);

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
		$datalist = Db::name('cycle_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
            //发票
            $datalist[$key]['invoice'] = 0;
            if($v['bid']) {
                $datalist[$key]['invoice'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('invoice');
            } else {
                $datalist[$key]['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
            }
		}
		$rdata = [];
		$rdata['st'] = $st;
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}
	public function orderdetail(){
		$this->checklogin();

		$detail = Db::name('cycle_order')->where('id',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();

		if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);

		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'cycle_order');
        //配送频率

        $ps_cycle = ['1' => '每日一期','2' => '每周一期' ,'3' => '每月一期'];
        $every_day = ['1' => '每天配送','2' => '工作日配送' ,'3' => '周末配送','4' => '隔天配送'];

        $detail['pspl'] = $ps_cycle[$detail['ps_cycle']];
        if($detail['ps_cycle'] == 1){
            $detail['every_day'] =$every_day[$detail['fwtc']];
        }else{
            $detail['every_day'] = '';

        }

		$storeinfo = [];
		if($detail['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('id,name,address,longitude,latitude')->find();
		}

		$shopset = Db::name('cycle_sysset')->where('aid',aid)->find();
        if($detail['status']==0 && $shopset['autoclose'] > 0 && $detail['paytypeid'] != 5){
            $lefttime = strtotime($detail['createtime']) + $shopset['autoclose']*60 - time();
            if($lefttime < 0) $lefttime = 0;
        }else{
            $lefttime = 0;
        }

		$rdata = [];
		//发票
        $rdata['invoice'] = 0;
        if($detail['bid']) {
            $rdata['invoice'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->value('invoice');
        } else {
            $rdata['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
        }
		$rdata['detail'] = $detail;
		$rdata['shopset'] = $shopset;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['lefttime'] = $lefttime;
		return $this->json($rdata);
	}
	function closeOrder(){
		$this->checklogin();
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('cycle_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || $order['status']!=0){
			return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		//加库存
		Db::name('cycle_guige')->where('aid',aid)->where('id',$order['ggid'])->update(['stock'=>Db::raw("stock+".$order['num']),'sales'=>Db::raw("sales-".$order['num'])]);
		Db::name('cycle_product')->where('aid',aid)->where('id',$order['proid'])->update(['stock'=>Db::raw("stock+".$order['num']),'sales'=>Db::raw("sales-".$order['num'])]);

		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
		}
		$rs = Db::name('cycle_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);
		return $this->json(['status'=>1,'msg'=>'操作成功']);
	}
	function delOrder(){
		$this->checklogin();
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('cycle_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(!$order || ($order['status']!=4 && $order['status']!=3)){
			return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
		}
		if($order['status']==3){
			$rs = Db::name('cycle_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['delete'=>1]);
		}else{
			$rs = Db::name('cycle_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->delete();
            Db::name('cycle_order_stage')->where('aid',aid)->where('bid',bid)->where('orderid',$orderid)->delete();
		}
		return $this->json(['status'=>1,'msg'=>'删除成功']);
	}
	function orderCollect(){ //确认收货
		$this->checklogin();
		$post = input('post.');
		$orderid = intval($post['id']);
		$order = Db::name('cycle_order_stage')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
		if(!$order || ($order['status'] !=2)){
			return $this->json(['status'=>0,'msg'=>'订单状态不符合收货要求']);
		}

		Db::name('cycle_order_stage')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
		
        $order_stage_count = Db::name('cycle_order_stage')
            ->where('status','in','0,1,2')
            ->where('orderid',$order['orderid'])
            ->count();
        if($order_stage_count == 0){
            Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->update(['status'=>3,'collect_time'=>time()]);
            
            $cycle_order = Db::name('cycle_order')->where('aid',aid)->where('bid',bid)->where('id',$order['orderid'])->find();
            $rs = \app\commons\Order::collect($cycle_order, 'cycle');
            if($rs['status']==0) return $rs;
        }
       
		return $this->json(['status'=>1,'msg'=>'确认收货成功']);
	}
	function refund(){//申请退款
		$this->checklogin();
		if(request()->isPost()){
			$post = input('post.');
			$orderid = intval($post['orderid']);
			$money = floatval($post['money']);
			$order = Db::name('cycle_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
			if(!$order || ($order['status']!=1 && $order['status'] != 2) || $order['refund_status'] == 2){
				return $this->json(['status'=>0,'msg'=>'订单状态不符合退款要求']);
			}
			//计算money
            $stage = Db::name('cycle_order_stage')->where('orderid',$order['id'])->select();
            $money = 0;
			foreach($stage as $key=>$val){
                if($val['status'] == 1){
                    $money = bcadd($money,$val['sell_price']*$val['num'],2);
                }
            }
          
			if($order['leveldk_money']){
			     $money = bcsub($money,$order['leveldk_money'],2);
            }
            if($order['coupon_money']){
                $money = bcsub($money,$order['coupon_money'],2);
            }
            if($order['freight_price']){
                $money = bcadd($money,$order['freight_price'],2);
            }
            
			if($money >= $order['totalprice']){
                $money = $order['totalprice'];
            }
           
            
			Db::name('cycle_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['refund_time'=>time(),'refund_status'=>1,'refund_reason'=>$post['reason'],'refund_money'=>$money]);

			$tmplcontent = [];
			$tmplcontent['first'] = '有周期购订单客户申请退款';
			$tmplcontent['remark'] = '点击进入查看~';
			$tmplcontent['keyword1'] = $order['ordernum'];
			$tmplcontent['keyword2'] = $money.'元';
			$tmplcontent['keyword3'] = $post['reason'];
            $tmplcontentNew = [];
            $tmplcontentNew['number2'] = $order['ordernum'];//订单号
            $tmplcontentNew['amount4'] = $money;//退款金额
			\app\commons\Wechat::sendhttmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,m_url('admin/order/collageorder'),$order['mdid'],$tmplcontentNew);

			$tmplcontent = [];
			$tmplcontent['thing1'] = $order['title'];
			$tmplcontent['character_string4'] = $order['ordernum'];
			$tmplcontent['amount2'] = $order['totalprice'];
			$tmplcontent['amount9'] = $money.'元';
			$tmplcontent['thing10'] = $post['reason'];
			\app\commons\Wechat::sendhtwxtmpl(aid,$order['bid'],'tmpl_ordertui',$tmplcontent,'admin/order/collageorder',$order['mdid']);

			return $this->json(['status'=>1,'msg'=>'提交成功,请等待商家审核']);
		}
		$rdata = [];
		$rdata['price'] = input('param.price/f');
		$rdata['orderid'] = input('param.orderid/d');
		$order = Db::name('cycle_order')->where('aid',aid)->where('mid',mid)->where('id',$rdata['orderid'])->find();
		$rdata['price'] = $order['totalprice'];
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
        $rdata['tmplids'] = $tmplids;
		return $this->json($rdata);
	}
	//评价商品
	public function comment(){
		$this->checklogin();
		$orderid = input('param.orderid/d');
		$og = Db::name('cycle_order')->where('id',$orderid)->where('mid',mid)->find();
		if(!$og){
			return $this->json(['status'=>0,'msg'=>'未查找到相关记录']);
		}
        //配送频率

        $ps_cycle = ['1' => '每日一期','2' => '每周一期' ,'3' => '每月一期'];
        $every_day = ['1' => '每天配送','2' => '工作日配送' ,'3' => '周末配送','4' => '隔天配送'];

        $og['pspl'] = $ps_cycle[$og['ps_cycle']];
        if($og['ps_cycle'] == 1){
            $og['every_day'] =$every_day[$og['fwtc']];
        }else{
            $og['every_day'] = '';
        }

		$comment = Db::name('cycle_comment')->where('orderid',$orderid)->where('aid',aid)->where('mid',mid)->find();
		if(request()->isPost()){
			$shopset = Db::name('cycle_sysset')->where('aid',aid)->find();
			if($shopset['comment']==0){
				return $this->json(['status'=>0,'msg'=>'评价功能未开启']);
			}
			if($comment){
				return $this->json(['status'=>0,'msg'=>'您已经评价过了']);
			}
			$order = Db::name('cycle_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
			$content = input('post.content');
			$content_pic = input('post.content_pic');
			$score = input('post.score/d');
			if($score < 1){
				return $this->json(['status'=>0,'msg'=>'请打分']);
			}
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['orderid'] = $order['id'];
			$data['ordernum']= $order['ordernum'];
			$data['proid'] =$order['proid'];
			$data['proname'] = $order['proname'];
			$data['propic'] = $order['propic'];
			$data['ggid'] = $order['ggid'];
			$data['ggname'] = $order['ggname'];
			$data['score'] = $score;
			$data['content'] = $content;
			$data['nickname']= $this->member['nickname'];
			$data['headimg'] = $this->member['headimg'];
			$data['createtime'] = time();
			$data['content_pic'] = $content_pic;
			$data['status'] = ($shopset['comment_check']==1 ? 0 : 1);
			//if($shopset['comment_check']==0){
			//	$data['status'] = 1;
				//$data['givescore'] = $shopset['comment_givescore'];
			//}else{
			//	$data['status'] = 0;
				//$data['givescore'] = 0;
			//}
			Db::name('cycle_comment')->insert($data);
			Db::name('cycle_order')->where('aid',aid)->where('mid',mid)->where('id',$order['id'])->update(['iscomment'=>1]);

			//如果不需要审核 增加产品评论数及评分
			if($shopset['comment_check']==0){
				$countnum = Db::name('cycle_comment')->where('proid',$order['proid'])->where('status',1)->count();
				$score = Db::name('cycle_comment')->where('proid',$order['proid'])->where('status',1)->avg('score');
				$haonum = Db::name('cycle_comment')->where('proid',$order['proid'])->where('status',1)->where('score','>',3)->count(); //好评数
				if($countnum > 0){
					$haopercent = $haonum/$countnum*100;
				}else{
					$haopercent = 100;
				}
				Db::name('cycle_product')->where('id',$order['proid'])->update(['comment_num'=>$countnum,'comment_score'=>$score,'comment_haopercent'=>$haopercent]);
			}
			return $this->json(['status'=>1,'msg'=>'评价成功']);
		}
		$rdata = [];
		$rdata['og'] = $og;
		$rdata['comment'] = $comment;
		return $this->json($rdata);
	}

	function team(){
		$this->checklogin();
		$teamid = input('param.teamid/d');
		$team = Db::name('cycle_order_team')->where('aid',aid)->where('id',$teamid)->find();
		$product = Db::name('cycle_product')->where('aid',aid)->where('id',$team['proid'])->find();
		if(!$product) return $this->json(['status'=>0,'msg'=>'商品不存在']);
		if($product['status']==0) return $this->json(['status'=>0,'msg'=>'商品已下架']);
		if($product['ischecked']!=1) return $this->json(['status'=>0,'msg'=>'商品未审核']);

		if(!$product['pics']) $product['pics'] = $product['pic'];
		$product['pics'] = explode(',',$product['pics']);
		if($product['fuwupoint']){
			$product['fuwupoint'] = explode(' ',preg_replace("/\s+/",' ',str_replace('　',' ',trim($product['fuwupoint']))));
		}
		$gglist = Db::name('cycle_guige')->where('proid',$product['id'])->select()->toArray();
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

		$orderlist = Db::name('cycle_order')->where('aid',aid)->where('teamid',$teamid)->where('status','in','1,2,3')->select()->toArray();
		$userlist = [];
		$haveme = 0;
		foreach($orderlist as $v){
			$user = Db::name('member')->field('id,nickname,headimg,province,city,sex')->where('aid',aid)->where('id',$v['mid'])->find();
			$userlist[] = $user;
			if($user['id'] == mid) $haveme =1;
		}
		if($team['teamnum'] > $team['num']){
			for($i=0;$i<$team['teamnum'] - $team['num'];$i++){
				$userlist[] = ['id'=>'','nickanme'=>'','headimg'=>''];
			}
		}
		$rtime = $team['createtime'] + $team['teamhour'] * 3600 - time();
		$set = Db::name('admin_set')->field('name,logo,desc,tel')->where('aid',aid)->find();
		$shopset = Db::name('cycle_sysset')->field('comment')->where('aid',aid)->find();
		$product['detail'] = \app\commons\System::initpagecontent($product['detail'],aid,mid,platform);

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['team'] = $team;
		$rdata['product'] = $product;
		$rdata['guigelist'] = $guigelist;
		$rdata['guigedata'] = $guigedata;
		$rdata['ggselected'] = $ggselected;
		$rdata['ks'] = $ks;
		$rdata['sysset'] = $set;
		$rdata['shopset'] = $shopset;
		$rdata['userlist'] = $userlist;
		$rdata['rtime'] = $rtime;
		$rdata['haveme'] = $haveme;
		return $this->json($rdata);
	}
    function logistics(){
        $get = input('param.');
        if($get['express_com'] == '同城配送'){
            if($get['type'] == 'express_wx'){
                $psorder = Db::name('express_wx_order')->where('id',$get['express_no'])->find();
                $psuser=['realname'=>$psorder['rider_name'],'tel'=>$psorder['rider_phone'],'latitude' => $psorder['rider_lat'],'longitude'=>$psorder['rider_lng']];
                $orderinfo = json_decode($psorder['orderinfo'],true);
                $binfo = json_decode($psorder['binfo'],true);
                $prolist = json_decode($psorder['prolist'],true);
                if($psorder['distance']> 1000){
                    $psorder['juli'] = round($psorder['distance']/1000,1);
                    $psorder['juli_unit'] = 'km';
                }else{
                    $psorder['juli']=$psorder['distance'];
                    $psorder['juli_unit'] = 'm';
                }

                //查询骑行距离
                $mapqq = new \app\commons\MapQQ();
                $bicycl = $mapqq->getDirectionDistance($psorder['orderinfo']['longitude'],$psorder['orderinfo']['latitude'],$psuser['longitude'],$psuser['latitude'],1);
                if($bicycl && $bicycl['status']==1){
                    $juli2 = $bicycl['distance'];
                }else{
                    $juli2 = getdistance($psorder['orderinfo']['longitude'],$psorder['orderinfo']['latitude'],$psuser['longitude'],$psuser['latitude'],1);
                }
                $psorder['juli2'] = $juli2;
                if($juli2> 1000){
                    $psorder['juli2'] = round($juli2/1000,1);
                    $psorder['juli2_unit'] = 'km';
                }else{
                    $psorder['juli2_unit'] = 'm';
                }
            }else{
                $psorder = Db::name('peisong_order')->where('id',$get['express_no'])->find();
                if($psorder['psid']<0){
                    $psuser=['realname'=>$psorder['make_rider_name'],'tel'=>$psorder['make_rider_mobile']];
                }else{
                    $psuser = Db::name('peisong_user')->where('id',$psorder['psid'])->find();
                }
                $orderinfo = json_decode($psorder['orderinfo'],true);
                $binfo = json_decode($psorder['binfo'],true);
                $prolist = json_decode($psorder['prolist'],true);

                if($psorder['juli']> 1000){
                    $psorder['juli'] = round($psorder['juli']/1000,1);
                    $psorder['juli_unit'] = 'km';
                }else{
                    $psorder['juli_unit'] = 'm';
                }
                //查询骑行距离
                $mapqq = new \app\commons\MapQQ();
                $bicycl = $mapqq->getDirectionDistance($psorder['longitude2'],$psorder['latitude2'],$psuser['longitude'],$psuser['latitude'],1);
                if($bicycl && $bicycl['status']==1){
                    $juli2 = $bicycl['distance'];
                }else{
                    $juli2 = getdistance($psorder['longitude2'],$psorder['latitude2'],$psuser['longitude'],$psuser['latitude'],1);
                }
                $psorder['juli2'] = $juli2;
                if($juli2> 1000){
                    $psorder['juli2'] = round($juli2/1000,1);
                    $psorder['juli2_unit'] = 'km';
                }else{
                    $psorder['juli2_unit'] = 'm';
                }
                $psorder['leftminute'] = ceil(($psorder['yujitime'] - time()) / 60);
                $psorder['ticheng'] = round($psorder['ticheng'],2);
                if($psorder['status']==4){
                    $psorder['useminute'] = ceil(($psorder['endtime'] - $psorder['createtime']) / 60);
                    $psorder['useminute2'] = ceil(($psorder['endtime'] - $psorder['starttime']) / 60);
                }
            }

            $rdata = [];
            $rdata['psorder'] = $psorder;
            $rdata['binfo'] = $binfo;
            $rdata['psuser'] = $psuser;
            $rdata['orderinfo'] = $orderinfo;
            $rdata['prolist'] = $prolist;
            return $this->json($rdata);
        }elseif($get['express_com'] == '货运托运'){
            $data = Db::name('freight_type10_record')->where('id',$get['express_no'])->find();
            return $this->json(['datalist'=>$data]);
        }
        else{
            if($get['express_com'] == '顺丰速运'){
                $totel = Db::name('shop_order')->where('aid',aid)->where('express_no',$get['express_no'])->value('tel');
                if(!$totel){
                    $totel = Db::name('seckill_order')->where('aid',aid)->where('express_no',$get['express_no'])->value('tel');
                }
                if(!$totel){
                    $totel = Db::name('collage_order')->where('aid',aid)->where('express_no',$get['express_no'])->value('tel');
                }
                if(!$totel){
                    $totel = Db::name('kanjia_order')->where('aid',aid)->where('express_no',$get['express_no'])->value('tel');
                }
                if(!$totel){
                    $totel = Db::name('scoreshop_order')->where('aid',aid)->where('express_no',$get['express_no'])->value('tel');
                }
                $get['express_no'] = $get['express_no'].":".substr($totel,-4);
            }
            $list = \app\commons\Common::getwuliu($get['express_no'],$get['express_com'], '', aid);
            $rdata = [];
            $rdata['datalist'] = $list;
            return $this->json($rdata);
        }
    }

    /**
	 * 获取周期列表
	 */
	public function getCycleList(){
	    $orderid = input('param.id/d');
        $this->checklogin();
        $detail = Db::name('cycle_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
        if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
        $list = Db::name('cycle_order_stage')
            ->where('orderid',$orderid)
            ->field('id,cycle_date,cycle_number,status')
            ->order('cycle_number asc')
            ->select()->toArray();
        foreach ($list as $k=>&$v){
            $v['title'] = '第'.$v['cycle_number'].'期';
        }
        return $this->json(['status'=>1,'data'=>$list,'detail' => $detail]);
    }
    /**
     * 获取周期详情
     */
    public function getCycleDetail(){
	    $id = input('param.id/d');
        $detail = Db::name('cycle_order_stage')
            ->where('id',$id)
            ->where('aid',aid)
            ->find();
        if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);
        $week = ['0' => '星期天','1' =>'星期一','2' =>'星期二','3' =>'星期三','4' =>'星期四','5' =>'星期五','6' =>'星期六'];
        $detail['week'] = $week[date('w',$detail['cycle_strtotime'])];
        //订单
        $order = Db::name('cycle_order')->where('id',$detail['orderid'])->find();
        $detail['order'] = $order;
        //提前顺延
        $product = Db::name('cycle_product')->where('id',$order['proid'])->find();
        $advance_extend_days =$product['advance_extend_days'];
        $time = $detail['cycle_strtotime'] - $advance_extend_days * 86400;
        $advance_date =   date('Y-m-d ',$time);
        if( date('Y-m-d',time()) > $advance_date){ //当前时间 > 延顺时间 不可再延顺
            $detail['is_advance'] = 0;
        }else{
            $detail['is_advance'] = 1;
        }
        $storeinfo = [];
        if($order['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$order['mdid'])->field('id,name,address,longitude,latitude')->find();
        }
        $detail['storeinfo']  = $storeinfo;
        return $this->json(['status'=>1,'data'=>$detail]);
    }

    /**
     *  顺延天数
     */
    public function advanceDays(){
        $id = input('param.id/d');
        $days = input('param.days/d');
        $detail = Db::name('cycle_order_stage')
            ->where('id',$id)
            ->where('aid',aid)
            ->find();
        $advance_date_str = $detail['cycle_strtotime'] + $days*86400;
        $advance_date = date('Y-m-d',$advance_date_str);
        $res =  Db::name('cycle_order_stage')
            ->where('id',$id)
            ->where('aid',aid)
            ->update(['cycle_date' =>$advance_date,'cycle_strtotime' => $advance_date_str]);
        if($res ===false){
            return $this->json(['status'=>0,'msg'=>'顺延失败']);
        }else{
            return $this->json(['status'=>1,'msg'=>'顺延成功']);
        }
    }

    //收藏
    public function addfavorite(){
        $this->checklogin();
        $post = input('post.');
        $rs = Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$post['proid'])->where('type',$post['type'])->find();
        if($rs){
            Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$post['proid'])->where('type',$post['type'])->delete();
            return json(['status'=>1,'msg'=>'已取消','url'=>true]);
        }else{
            Db::name('member_favorite')->insert(['aid'=>aid,'mid'=>mid,'proid'=>$post['proid'],'type'=>$post['type'],'createtime'=>time()]);
            return json(['status'=>1,'msg'=>'已收藏','url'=>true]);
        }
    }
}
