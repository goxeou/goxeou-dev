<?php
//custom_file(taocan_product)
namespace app\controllers;
use think\facade\Db;
class ApiTaocan extends ApiCommon
{
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
        $where2 = "find_in_set('-1',showtj)";
        if($this->member){
            $where2 .= " or find_in_set('".$this->member['levelid']."',showtj)";
            if($this->member['subscribe']==1){
                $where2 .= " or find_in_set('0',showtj)";
            }
        }
        $where[] = Db::raw($where2);

        //分类
        if(input('param.keyword')){
            $where[] = ['name','like','%'.input('param.keyword').'%'];
        }
        $pernum = 20;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $field = "pic,id,name,sales,market_price,sell_price,perlimit";
        $datalist = Db::name('taocan_product')->field($field)->where($where)->page($pagenum,$pernum)->order('sort desc,id desc')->select()->toArray();
        if(!$datalist) $datalist = array();
        if($pagenum == 1){
            $pics = Db::name('taocan_sysset')->where('aid',aid)->value('pics');
            if(!$pics) $pics = [];
            $pics = explode(',',$pics);
        }
        $rdata = [];
        $rdata['datalist'] = $datalist;
        $rdata['pics'] = $pics;
        $rdata['clist'] = [];
        return $this->json($rdata);
    }
    public function product(){
        $proid = input('param.id/d');
        $where = [];
        $where[] = ['id','=',$proid];
        $where[] = ['aid','=',aid];
        $product = Db::name('taocan_product')->where($where)->find();
        if(!$product) return $this->json(['status'=>0,'msg'=>'商品不存在']);
        if($product['status']==0) return $this->json(['status'=>0,'msg'=>'商品已下架']);
        if($product['ischecked']!=1) return $this->json(['status'=>0,'msg'=>'商品未审核']);
        if(mid){
            $levelid = $this->member['levelid'];
            $levelprice_arr = json_decode($product['level_price'],true);
            $product['sell_price'] = $levelprice_arr[$levelid]?:$product['sell_price'];
        }

        if(!$product['pics']) $product['pics'] = $product['pic'];
        $product['pics'] = explode(',',$product['pics']);
        if($product['fuwupoint']){
            $product['fuwupoint'] = explode(' ',preg_replace("/\s+/",' ',str_replace('　',' ',trim($product['fuwupoint']))));
        }
        $taocan_guige = json_decode($product['product_guige'],true);
        $gglist = Db::name('shop_product')->where('id','in',$product['product_ids'])->field('id,name')->select()->toArray();

        $guigelist = array();
        foreach($gglist as $k=>$v){
            $ggid = $taocan_guige[$v['id']]??0;
            $guige = Db::name('shop_guige')->where('id',$ggid)->field('name,stock')->find();
            $v['ggname'] = $guige['name'];
            $v['stock'] = $guige['stock'];
            $guigelist[$v['id']] = $v;
        }
        $guigedata = json_decode($product['product_guige'],true);
        $ggselected = [];
        foreach($guigedata as $v) {
            $ggselected[] = 0;
        }
        $ks = implode(',',$ggselected);

        //获取评论
        $commentlist = Db::name('taocan_comment')->where('aid',aid)->where('proid',$proid)->where('status',1)->limit(10)->select()->toArray();
        if(!$commentlist) $commentlist = [];
        foreach($commentlist as $k=>$pl){
            $commentlist[$k]['createtime'] = date('Y-m-d H:i',$pl['createtime']);
            if($commentlist[$k]['content_pic']) $commentlist[$k]['content_pic'] = explode(',',$commentlist[$k]['content_pic']);
        }
        $commentcount = Db::name('taocan_comment')->where('aid',aid)->where('proid',$proid)->where('status',1)->count();


        $rs = Db::name('member_favorite')->where('aid',aid)->where('mid',mid)->where('proid',$proid)->where('type','taocan')->find();
        if($rs){
            $isfavorite = true;
        }else{
            $isfavorite = false;
        }
        if($this->member){
            //添加浏览历史
            $rs = Db::name('member_history')->where(array('aid'=>aid,'mid'=>mid,'proid'=>$proid,'type'=>'taocan'))->find();
            if($rs){
                Db::name('member_history')->where(array('id'=>$rs['id']))->update(['createtime'=>time()]);
            }else{
                Db::name('member_history')->insert(array('aid'=>aid,'mid'=>mid,'proid'=>$proid,'type'=>'taocan','createtime'=>time()));
            }
        }

        $shopset = Db::name('taocan_sysset')->field('comment,showjd')->where('aid',aid)->find();
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


        $tjdatalist = [];
        if($product['show_recommend'] == 1){
            $tjwhere = [];
            $tjwhere[] = ['aid','=',aid];
            $tjwhere[] = ['status','=',1];
            $tjwhere[] = ['ischecked','=',1];
            $where2 = "find_in_set('-1',showtj)";
            if($this->member){
                $where2 .= " or find_in_set('".$this->member['levelid']."',showtj)";
                if($this->member['subscribe']==1){
                    $where2 .= " or find_in_set('0',showtj)";
                }
            }
            $tjwhere[] = Db::raw($where2);

            if($product['bid']){
                $tjwhere[] = ['bid','=',$product['bid']];
            }else{
                $business_sysset = Db::name('business_sysset')->where('aid',aid)->find();
                if(!$business_sysset || $business_sysset['status']==0 || $business_sysset['product_isshow']==0){
                    $tjwhere[] = ['bid','=',0];
                }
            }
            $tjdatalist = Db::name('taocan_product')->where($tjwhere)->limit(8)->order(Db::raw('rand()'))->select()->toArray();
            if(!$tjdatalist) $tjdatalist = array();
            $tjdatalist = $this->formatprolist($tjdatalist);
        }elseif($product['show_recommend'] == 2){
            $tjdatalist = Db::name('taocan_product')->where('aid',aid)->where('id','in',$product['recommend_productids'])->order(Db::raw('field(id,'.$product['recommend_productids'].')'))->select()->toArray();
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
        $rdata['tjdatalist'] = $tjdatalist;
        $rdata['showtoptabbar'] = 0;

        return $this->json($rdata);
    }
    public function getproductdetail(){
        $proid = input('param.id/d');
        $where = [];
        $where[] = ['id','=',$proid];
        $where[] = ['aid','=',aid];
        $product = Db::name('taocan_product')->where($where)->find();
        if(!$product) return $this->json(['status'=>0,'msg'=>'商品不存在']);
        if($product['status']==0) return $this->json(['status'=>0,'msg'=>'商品已下架']);
        if($product['ischecked']!=1) return $this->json(['status'=>0,'msg'=>'商品未审核']);
        if($product['buy_limit']>0){
            //每人限购
            $buy_num = Db::name('taocan_order')
                ->where('mid','=',mid)
                ->where('proid','=',$proid)
                ->where('status','in','1,2,3')
                ->sum('beishu');
            if($buy_num>=$product['buy_limit']){
                return json(['status'=>0,'msg'=>'该产品每人限购'.$product['buy_limit'].'件']);
            }
        }

        $levelid = $this->member['levelid'];
        $levelprice_arr = json_decode($product['level_price'],true);
        $product['sell_price'] = $levelprice_arr[$levelid]?:$product['sell_price'];

        if(!$product['pics']) $product['pics'] = $product['pic'];
        $product['pics'] = explode(',',$product['pics']);
        $gettj = explode(',',$product['gettj']);
        if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj) && (!in_array('0',$gettj) || $this->member['subscribe']!=1)){ //不是所有人
            if(!$product['gettjtip']) $product['gettjtip'] = '没有权限购买该商品';
            return $this->json(['status'=>0,'msg'=>$product['gettjtip'],'url'=>$product['gettjurl']]);
        }
        $taocan_guige = json_decode($product['product_guige'],true);
        $product_nums = json_decode($product['product_nums'],true);
        $gglist = Db::name('shop_product')->where('id','in',$product['product_ids'])->field('id,name,pic')->select()->toArray();

        $guigelist = array();
        $guige_num = [];
        foreach($gglist as $k=>$v){
            $guige_id = $taocan_guige[$v['id']];
            $guige_num[$guige_id] = 0;
            $guige = Db::name('shop_guige')->where('id',$guige_id)->field('name ggname,stock')->find();
            $max_num = $product_nums[$v['id']]??0;
            $v['ggname'] = $guige['ggname'];
            $v['stock'] = $guige['stock'];
            $v['max_num'] = $max_num?:$guige['stock'];
            $v['gid'] = $guige_id;
            $guigelist[$guige_id] = $v;
        }
        $guigedata = json_decode($product['product_guige'],true);
        $ggselected = [];
        foreach($guigedata as $v) {
            $ggselected[] = 0;
        }
        $ks = implode(',',$ggselected);
        $product['detail'] = \app\commons\System::initpagecontent($product['detail'],aid,mid,platform);

        $rdata = [];
        $rdata['status'] = 1;
        $rdata['product'] = $product;
        $rdata['guigelist'] = $guigelist;
        $rdata['guigedata'] = $guigedata;
        $rdata['ggselected'] = $ggselected;
        $rdata['ks'] = $ks;
        $rdata['nowtime'] = time();
        $rdata['status'] = 1;
        $rdata['showtoptabbar'] = 0;
        $rdata['guige_num'] = $guige_num;

        return $this->json($rdata);
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
        $datalist = Db::name('taocan_comment')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
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
        $datalist = Db::name('taocan_category')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
        $rdata = [];
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
        if(input('param.cid')){
            $cid = input('param.cid/d');
            //子分类
            $clist = Db::name('taocan_category')->where('aid',aid)->where('pid',$cid)->select()->toArray();
            if($clist){
                $cateArr = [$cid];
                foreach($clist as $c){
                    $cateArr[] = $c['id'];
                }
                $where[] = ['cid','in',$cateArr];
            }else{
                $where[] = ['cid','=',$cid];
                $pid = Db::name('taocan_category')->where('aid',aid)->where('id',$cid)->value('pid');
                if($pid){
                    $searchcid = $pid;
                    $clist = Db::name('taocan_category')->where('aid',aid)->where('pid',$pid)->select()->toArray();
                }
            }
        }
        if(input('param.keyword')){
            $where[] = ['name','like','%'.input('param.keyword').'%'];
        }
        $pernum = 20;
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $field = "pic,id,name,sales,market_price,sell_price,sellpoint,fuwupoint,buymax,leadermoney,leaderscore";
        $datalist = Db::name('taocan_product')->field($field)->where($where)->page($pagenum,$pernum)->order('sort desc,id desc')->select()->toArray();
        if(!$datalist) $datalist = array();
        if(request()->isPost()){
            return $this->json(['status'=>1,'data'=>$datalist]);
        }

        $rdata = [];
        $rdata['clist'] = $clist;
        $rdata['searchcid'] = $searchcid;
        $rdata['datalist'] = $datalist;
        return $this->json($rdata);
    }
    public function buy(){
        $this->checklogin();
        //dump(input());
        $guige_num = input('guige_num');
        $guige_num = htmlspecialchars_decode($guige_num);
        $guige_num = json_decode($guige_num,true);
        //dump($guige_num);exit;
        $proid = input('param.proid/d');
        $ggid = input('param.ggid/d');
        $totalnum = input('param.num_total/d');
        if(!$totalnum) $totalnum = 1;

        $product = Db::name('taocan_product')->where('aid',aid)->where('status',1)->where('ischecked',1)->where('id',$proid)->find();

        if(!$product){
            return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
        }
        //级别价格
        $levelid = $this->member['levelid'];
        $levelprice_arr = json_decode($product['level_price'],true);
        $level_price = $levelprice_arr[$levelid]??0;
        $product['sell_price'] = $level_price>0?$level_price:$product['sell_price'];

        $gettj = explode(',',$product['gettj']);
        if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj) && (!in_array('0',$gettj) || $this->member['subscribe']!=1)){ //不是所有人
            if(!$product['gettjtip']) $product['gettjtip'] = '没有权限购买该商品';
            return $this->json(['status'=>0,'msg'=>$product['gettjtip'],'url'=>$product['gettjurl']]);
        }
        $guige_data = [];
        foreach($guige_num as $gid=>$num){
            if($num<=0){
                unset($guige_num[$gid]);
                continue;
            }

            $guige = Db::name('shop_guige')->where('id',$gid)->find();
            if(!$guige){
                return $this->json(['status'=>0,'msg'=>'产品该规格不存在或已下架']);
            }
            if($guige['stock'] < $num){
                return $this->json(['status'=>0,'msg'=>'库存不足']);
            }
            $guige_porduct = Db::name('shop_product')->where('id',$guige['proid'])->find();
            $guige_data[] = ['name'=>$guige['name'],'product_name'=>$guige_porduct['name'],'num'=>$num,'pic'=>$guige_porduct['pic']];
        }

        $yushu = $totalnum%$product['perlimit'];
        if($totalnum<$product['prelimit'] || $yushu>0){
            return $this->json(['status'=>0,'msg'=>'购买数量不足']);
        }
        $beishu = bcdiv($totalnum,$product['perlimit'],0);
        if($beishu<=0){
            $beishu = 1;
        }
        $bid = $product['bid'];
        if($bid!=0){
            $business = Db::name('business')->where('id',$bid)->field('id,aid,cid,name,logo,tel,address,sales,longitude,latitude')->find();
        }else{
            $business = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,desc,tel')->find();
        }

        $product_price = $product['sell_price'] * $beishu;
        $totalweight = $guige['weight'] * $beishu;
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
        if($level_price>0){
            $userlevel['discount'] = 10;
        }
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
        $leadermoney = 0;
        $totalprice = $totalprice - $leadermoney;
        $leveldk_money = 0;
        if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
            $leveldk_money = $product_price * (1 - $userlevel['discount'] * 0.1);
        }
        $leveldk_money = round($leveldk_money,2);
        $totalprice = $totalprice - $leveldk_money;

        if($bid > 0){
            $business = Db::name('business')->where('aid',aid)->where('id', $bid)->find();
            $bcids = $business['cid'] ? explode(',',$business['cid']) : [];
        }else{
            $bcids = [];
        }
        if($bcids){
            $whereCid = [];
            foreach($bcids as $bcid){
                $whereCid[] = "find_in_set({$bcid},canused_bcids)";
            }
            $whereCids = implode(' or ',$whereCid);
        }else{
            $whereCids = '0=1';
        }

        $couponList = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('type','in','1,4')->where('status',0)
            ->whereRaw("bid=-1 or bid=".$bid." or (bid=0 and (canused_bids='all' or find_in_set(".$bid.",canused_bids) or ($whereCids)))")
            ->where('minprice','<=',$totalprice)
            ->where('starttime','<=',time())->where('endtime','>',time())->order('id desc')->select()->toArray();
        if(!$couponList) $couponList = [];
        foreach($couponList as $k=>$v){
            //$couponList[$k]['starttime'] = date('m-d H:i',$v['starttime']);
            //$couponList[$k]['endtime'] = date('m-d H:i',$v['endtime']);
            $couponinfo = Db::name('coupon')->where('aid',aid)->where('id',$v['couponid'])->find();
            if($v['bid'] > 0){
                $binfo = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->find();
                $couponList[$k]['bname'] = $binfo['name'];
            }
            $fwscene = [0];
            if(!in_array($couponinfo['fwscene'],$fwscene)){//全部可用
                unset($couponList[$k]);
            }
            if(empty($couponinfo) || $couponinfo['fwtype']!==0){
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
            $lastorder = Db::name('taocan_order')->where('aid',aid)->where('mid',mid)->where('linkman','<>','')->find();
            if($lastorder){
                $rdata['linkman'] = $lastorder['linkman'];
                $rdata['tel'] = $lastorder['tel'];
            }
        }
        $rdata['product'] = $product;
        $rdata['guige'] = $guige_data;
        $rdata['business'] = $business;
        $rdata['freightList'] = $freightList;
        $rdata['freightArr'] = $freightArr;
        $rdata['userinfo'] = $userinfo;
        $rdata['couponList'] = $couponList;
        $rdata['totalnum'] = $totalnum;
        $rdata['leadermoney'] = $leadermoney;
        $rdata['product_price'] = $product_price;
        $rdata['leveldk_money'] = $leveldk_money;
        $rdata['needLocation'] = $needLocation;
        $rdata['scorebdkyf'] = Db::name('admin_set')->where('aid',aid)->value('scorebdkyf');
        return $this->json($rdata);
    }
    public function createOrder(){
        $this->checklogin();
        $post = input('post.');
        if($post['proid'] && $post['guige_num']){
            $proid = $post['proid'];
            $guige_num = $post['guige_num'];
            $guige_num = htmlspecialchars_decode($guige_num);
            $guige_num = json_decode($guige_num,true);
            $total_num = $post['num_total'] ? $post['num_total'] : 1;
        }else{
            return $this->json(['status'=>0,'msg'=>'产品数据错误']);
        }
        $num = intval($total_num);
        if($num <=0) return $this->json(['status'=>0,'msg'=>'产品数据错误']);

        $product_price = 0;
        $givescore  = 0; //奖励积分
        $weight = 0;//重量
        $goodsnum = $num;

        $product = Db::name('taocan_product')->where('aid',aid)->where('status',1)->where('ischecked',1)->where('id',$proid)->find();
        if(!$product) return $this->json(['status'=>0,'msg'=>'产品不存在或已下架']);
        //级别价格
        $levelid = $this->member['levelid'];
        $levelprice_arr = json_decode($product['level_price'],true);
        $level_price = $levelprice_arr[$levelid]??0;
        $product['sell_price'] = $level_price>0?$level_price:$product['sell_price'];

        $bid = $product['bid'];
        $levelid = $this->member['levelid'];
        $levelprice_arr = json_decode($product['level_price'],true);
        $product['sell_price'] = $levelprice_arr[$levelid]?:$product['sell_price'];
        $guige_data = [];
        foreach($guige_num as $gid=>$gg_num){
            if($gg_num<=0){
                unset($guige_num[$gid]);
                continue;
            }

            $guige = Db::name('shop_guige')->where('id',$gid)->find();
            if(!$guige){
                return $this->json(['status'=>0,'msg'=>'产品该规格不存在或已下架']);
            }
            if($guige['stock'] < $gg_num){
                return $this->json(['status'=>0,'msg'=>'库存不足']);
            }
            $guige_porduct = Db::name('shop_product')->where('id',$guige['proid'])->find();
            $guige_data[] = ['name'=>$guige['name'],'product_name'=>$guige_porduct['name'],'num'=>$gg_num,'pic'=>$guige_porduct['pic']];
        }

        $yushu = $total_num%$product['perlimit'];
        if($total_num<$product['prelimit'] || $yushu>0){
            return $this->json(['status'=>0,'msg'=>'购买数量不足']);
        }
        $beishu = bcdiv($total_num,$product['perlimit'],0);
        if($beishu<=0){
            $beishu = 1;
        }

        $product_price = $product['sell_price'] * $beishu;

        $weight = $product['weight'] * $num;

        $totalprice = $product_price;
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
        if($level_price>0){
            $userlevel['discount'] = 10;
        }
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
        //优惠券
        if($post['couponrid'] > 0){
            $couponrid = $post['couponrid'];
            if($bid > 0){
                $business = Db::name('business')->where('aid',aid)->where('id', $bid)->find();
                $bcids = $business['cid'] ? explode(',',$business['cid']) : [];
            }else{
                $bcids = [];
            }
            if($bcids){
                $whereCid = [];
                foreach($bcids as $bcid){
                    $whereCid[] = "find_in_set({$bcid},canused_bcids)";
                }
                $whereCids = implode(' or ',$whereCid);
            }else{
                $whereCids = '0=1';
            }

            $couponrecord = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$couponrid)
                ->whereRaw("bid=-1 or bid=".$bid." or (bid=0 and (canused_bids='all' or find_in_set(".$bid.",canused_bids) or ($whereCids)))")->find();
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
            //适用场景
            $fwscene = [0];
            if(!in_array($couponinfo['fwscene'],$fwscene)){//全部可用
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
        $orderdata['cost_price'] = $product['cost_price'];
        $orderdata['sell_price'] = $product['sell_price'];
        $orderdata['num'] = $num;

        $orderdata['linkman'] = $address['name'];
        $orderdata['tel'] = $address['tel'];
        $orderdata['area'] = $address['area'];
        $orderdata['area2'] = $address['province'].','.$address['city'].','.$address['district'];
        $orderdata['address'] = $address['address'];
        $orderdata['longitude'] = $address['longitude'];
        $orderdata['latitude'] = $address['latitude'];
        $orderdata['totalprice'] = $totalprice;
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

        $orderdata['givescore'] = $givescore;

        $orderdata['hexiao_code'] = random(16);
        $orderdata['hexiao_qr'] = createqrcode(m_url('admin/hexiao/hexiao?type=taocan&co='.$orderdata['hexiao_code']));
        $orderdata['platform'] = platform;
        $orderdata['beishu'] = $beishu;
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
        $orderid = Db::name('taocan_order')->insertGetId($orderdata);
        Db::name('taocan_product')->where('id',$product['id'])->update(['stock'=>Db::raw("stock-".$beishu),'sales'=>Db::raw("sales+".$beishu)]);
        //插入分表
        foreach($guige_num as $gid=>$num){
            if($num<=0){
                unset($guige_num[$gid]);
                continue;
            }
            $guige = Db::name('shop_guige')->where('id',$gid)->find();
            $guige_porduct = Db::name('shop_product')->where('id',$guige['proid'])->find();
            $ogdata = [];
            $ogdata['aid'] = aid;
            $ogdata['bid'] = $product['bid'];
            $ogdata['mid'] = mid;
            $ogdata['orderid'] = $orderid;
            $ogdata['ordernum'] = $orderdata['ordernum'];
            $ogdata['proid'] = $product['id'];
            $ogdata['name'] = $product['name'];
            $ogdata['pic'] = $guige_porduct['pic']?$guige_porduct['pic']:$product['pic'];
            $ogdata['procode'] = $product['procode'];
            $ogdata['barcode'] = $guige['barcode'];
            $ogdata['gg_proid'] = $guige_porduct['id'];
            $ogdata['gg_proname'] = $guige_porduct['name'];
            $ogdata['ggid'] = $guige['id'];
            $ogdata['ggname'] = $guige['name'];
            $ogdata['cid'] = $product['cid'];
            $ogdata['num'] = $num;
            $ogdata['cost_price'] = $guige['cost_price'];
            $ogdata['sell_price'] = $guige['sell_price'];
            $ogdata['totalprice'] = $num * $guige['sell_price'];
            $ogdata['total_weight'] = $num * $guige['weight'];
            $ogdata['status'] = 0;
            $ogdata['createtime'] = time();
            $ogdata['isfg'] = 1;
            $ogdata['isfenhong'] = 2;
            $og_totalprice = $ogdata['totalprice'];
            $ogdata['real_totalprice'] = $og_totalprice; //实际商品销售金额
            $ogid = Db::name('taocan_order_goods')->insertGetId($ogdata);


            //减库存加销量
            $stock = $guige['stock'] - $num;
            if($stock < 0) $stock = 0;
            $pstock = $product['stock'] - $num;
            if($pstock < 0) $pstock = 0;
            $sales = $guige['sales'] + $num;
            $psales = $product['sales'] + $num;
            Db::name('shop_guige')->where('aid',aid)->where('id',$guige['id'])->update(['stock'=>$stock,'sales'=>$sales]);
            Db::name('shop_product')->where('aid',aid)->where('id',$guige_porduct['id'])->update(['stock'=>$pstock,'sales'=>$psales]);
        }



        \app\models\Freight::saveformdata($orderid,'taocan_order',$freight['id'],$post['formdata']);

        $payorderid = \app\models\Payorder::createorder(aid,$orderdata['bid'],$orderdata['mid'],'taocan',$orderid,$ordernum,$orderdata['title'],$orderdata['totalprice'],$orderdata['scoredkscore']);
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
        $datalist = Db::name('taocan_order')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
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
        $shopset = Db::name('taocan_sysset')->where('aid',aid)->find();
        $rdata['shopset'] = $shopset;

        return $this->json($rdata);
    }
    public function orderdetail(){
        $this->checklogin();
        $detail = Db::name('taocan_order')->where('id',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();
        if(!$detail) return $this->json(['status'=>0,'msg'=>'订单不存在']);

        $detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
        $detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
        $detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
        $detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
        $detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
        $detail['formdata'] = \app\models\Freight::getformdata($detail['id'],'taocan_order');

        $storeinfo = [];
        if($detail['freight_type'] == 1){
            $storeinfo = Db::name('mendian')->where('id',$detail['mdid'])->field('id,name,address,longitude,latitude')->find();
        }

        $shopset = Db::name('taocan_sysset')->where('aid',aid)->find();

        $rdata = [];
        $rdata['status'] = 1;
        //发票
        $rdata['invoice'] = 0;
        if($detail['bid']) {
            $rdata['invoice'] = Db::name('business')->where('aid',aid)->where('id',$detail['bid'])->value('invoice');
        } else {
            $rdata['invoice'] = Db::name('admin_set')->where('aid',aid)->value('invoice');
        }
        $prolist = Db::name('taocan_order_goods')->where('orderid',$detail['id'])->field('id,gg_proname,gg_proid,pic,num,ggname,sell_price,iscomment')->select()->toArray();
        $rdata['detail'] = $detail;
        $rdata['shopset'] = $shopset;
        $rdata['storeinfo'] = $storeinfo;
        $rdata['prolist'] = $prolist;
        return $this->json($rdata);
    }
    function closeOrder(){
        $this->checklogin();
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('taocan_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
        if(!$order || $order['status']!=0){
            return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
        }
        //加库存
        Db::name('taocan_product')->where('aid',aid)->where('id',$order['proid'])->update(['stock'=>Db::raw("stock+".$order['beishu']),'sales'=>Db::raw("sales-".$order['beishu'])]);
        //加库存
        $oglist = Db::name('taocan_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
        foreach($oglist as $og){
            Db::name('shop_guige')->where('aid',aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
            Db::name('shop_product')->where('aid',aid)->where('id',$og['gg_proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
        }
        //优惠券抵扣的返还
        if($order['coupon_rid'] > 0){
            Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
        }
        $rs = Db::name('taocan_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);
        Db::name('taocan_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('mid',mid)->update(['status'=>4]);

        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }
    function delOrder(){
        $this->checklogin();
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('taocan_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->find();
        if(!$order || ($order['status']!=4 && $order['status']!=3)){
            return $this->json(['status'=>0,'msg'=>'删除失败,订单状态错误']);
        }
        if($order['status']==3){
            $rs = Db::name('taocan_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->update(['delete'=>1]);
        }else{
            $rs = Db::name('taocan_order')->where('id',$orderid)->where('aid',aid)->where('mid',mid)->delete();
            $rs = Db::name('taocan_order_goods')->where('orderid',$orderid)->where('aid',aid)->where('mid',mid)->delete();
        }
        return $this->json(['status'=>1,'msg'=>'删除成功']);
    }
    function orderCollect(){ //确认收货
        $this->checklogin();
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('taocan_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
        if(!$order || ($order['status']!=2)){
            return $this->json(['status'=>0,'msg'=>'订单状态不符合收货要求']);
        }
        $rs = \app\commons\Order::collect($order,'taocan');
        if($rs['status'] == 0) return $this->json($rs);
        Db::name('taocan_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
        Db::name('taocan_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);
        \app\commons\Member::uplv(aid,mid);

        return $this->json(['status'=>1,'msg'=>'确认收货成功']);
    }
    function refund(){//申请退款
        $this->checklogin();
        if(request()->isPost()){
            $post = input('post.');
            $orderid = intval($post['orderid']);
            $money = floatval($post['money']);
            $order = Db::name('taocan_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->find();
            if(!$order || ($order['status']!=1 && $order['status'] != 2) || $order['refund_status'] == 2){
                return $this->json(['status'=>0,'msg'=>'订单状态不符合退款要求']);
            }
            if($money < 0 || $money > $order['totalprice']){
                return $this->json(['status'=>0,'msg'=>'退款金额有误']);
            }
            Db::name('taocan_order')->where('aid',aid)->where('mid',mid)->where('id',$orderid)->update(['refund_time'=>time(),'refund_status'=>1,'refund_reason'=>$post['reason'],'refund_money'=>$money]);

            return $this->json(['status'=>1,'msg'=>'提交成功,请等待商家审核']);
        }
        $rdata = [];
        $rdata['price'] = input('param.price/f');
        $rdata['orderid'] = input('param.orderid/d');
        $order = Db::name('taocan_order')->where('aid',aid)->where('mid',mid)->where('id',$rdata['orderid'])->find();
        $rdata['price'] = $order['totalprice'];
        return $this->json($rdata);
    }
    //评价商品
    public function comment(){
        $this->checklogin();
        $ogid = input('param.ogid/d');
        $og = Db::name('taocan_order_goods')->where('id',$ogid)->where('mid',mid)->find();
        if(!$og){
            return $this->json(['status'=>0,'msg'=>'未查找到相关记录']);
        }
        $comment = Db::name('taocan_comment')->where('ogid',$ogid)->where('aid',aid)->where('mid',mid)->find();
        if(request()->isPost()){
            $order = Db::name('taocan_order')->where('id',$og['orderid'])->where('mid',mid)->find();
            $shopset = Db::name('taocan_sysset')->where('aid',aid)->find();
            if($shopset['comment']==0){
                return $this->json(['status'=>0,'msg'=>'评价功能未开启']);
            }
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
            $data['orderid'] = $og['orderid'];
            $data['ogid'] = $ogid;
            $data['ordernum']= $order['ordernum'];
            $data['proid'] = $og['gg_proid'];
            $data['proname'] = $og['gg_proname'];
            $data['propic'] = $og['pic'];
            $data['ggid'] = $og['ggid'];
            $data['ggname'] = $og['ggname'];
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
            Db::name('taocan_comment')->insert($data);
            Db::name('taocan_order_goods')->where('aid',aid)->where('mid',mid)->where('id',$ogid)->update(['iscomment'=>1]);

            //如果不需要审核 增加产品评论数及评分
            if($shopset['comment_check']==0){
                $countnum = Db::name('taocan_comment')->where('proid',$order['proid'])->where('status',1)->count();
                $score = Db::name('taocan_comment')->where('proid',$order['proid'])->where('status',1)->avg('score');
                $haonum = Db::name('taocan_comment')->where('proid',$order['proid'])->where('status',1)->where('score','>',3)->count(); //好评数
                if($countnum > 0){
                    $haopercent = $haonum/$countnum*100;
                }else{
                    $haopercent = 100;
                }
                Db::name('shop_product')->where('id',$order['proid'])->update(['comment_num'=>$countnum,'comment_score'=>$score,'comment_haopercent'=>$haopercent]);
            }
            return $this->json(['status'=>1,'msg'=>'评价成功']);
        }
        $rdata = [];
        $rdata['og'] = $og;
        $rdata['comment'] = $comment;
        return $this->json($rdata);
    }

    public function logistics(){//查快递单号
        $get = input('param.');
        $content = \app\commons\Common::ali_getwuliu($get['express_no'],$get['express'],aid);
        $data = json_decode($content,true);

        if(!$data || $data['msg']!='ok'){
            $list = [];
        }else{
            $list = $data['result']['list'];
            foreach($list as $k=>$v){
                $list[$k]['context'] = $v['status'];
            }
        }

        $rdata = [];
        $rdata['express_no'] = $get['express_no'];
        $rdata['express'] = $get['express'];
        $rdata['datalist'] = $list;
        return $this->json($rdata);
    }
    //商品海报
    function getposter(){
        $this->checklogin();
        $post = input('post.');
        $platform = platform;
        $page = '/pagesA/taocan/product';
        $scene = 'id_'.$post['proid'].'-pid_'.$this->member['id'];
        //if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
        //	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
        //}
        $posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','product')->where('platform',$platform)->order('id')->find();
        $posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','taocan')->where('posterid',$posterset['id'])->find();
        if(!$posterdata){
            $product = Db::name('taocan_product')->where('id',$post['proid'])->find();
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

            //dump($textReplaceArr);exit;
            $poster = $this->_getposter(aid,$product['bid'],$platform,$posterset['content'],$page,$scene,$textReplaceArr);
            $posterdata = [];
            $posterdata['aid'] = aid;
            $posterdata['mid'] = $this->member['id'];
            $posterdata['scene'] = $scene;
            $posterdata['page'] = $page;
            $posterdata['type'] = 'taocan';
            $posterdata['poster'] = $poster;
            $posterdata['createtime'] = time();
            Db::name('member_poster')->insert($posterdata);
        }
        return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
    }
}