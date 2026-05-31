<?php


namespace app\controllers;
use think\facade\Db;
class ApiCoupon extends ApiCommon
{
	//我的优惠券
	function mycoupon(){
		$this->checklogin();
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 10;
		$st = input('param.st') ? input('param.st') : 0;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		if($st == 0){//未使用
			$where[] = ['status','=',0];
			$where[] = ['endtime','>=',time()];
		}elseif($st == 1){
			$where[] = ['status','=',1];
		}elseif($st == 2){
			$where[] = ['status','=',0];
			$where[] = ['endtime','<',time()];
		}
		if(input('?param.bid') && input('param.bid')!==''){
			$where[] = ['bid','=',input('param.bid')];
		}
		$datalist = Db::name('coupon_record')->field('id,bid,type,limit_count,used_count,couponid,couponname,money,discount,minprice,from_unixtime(starttime,"%Y-%m-%d %H:%i") starttime,from_unixtime(endtime,"%Y-%m-%d %H:%i") endtime,from_unixtime(usetime) usetime,from_unixtime(createtime) createtime,status,from_mid')->where($where)->order('id desc')->page($pagenum,$pernum)->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
            $datalist[$k]['type_txt'] = \app\commons\Coupon::getCouponTypeTxt($v['type']);
			if($v['bid'] > 0){
				$binfo = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->find();
				$datalist[$k]['bname'] = $binfo['name'];
			}
            $coupon = Db::name('coupon')->where('id',$v['couponid'])->field('isgive,fwtype,fwscene')->find();
			//转赠优惠券不可二次转赠
            if($v['from_mid']){
                $datalist[$k]['isgive'] = 0;//不可二次转赠
            }else{
                $datalist[$k]['isgive'] = $coupon['isgive'];
            }
            $datalist[$k]['fwtype'] = $coupon['fwtype'];
            $datalist[$k]['fwscene'] = $coupon['fwscene'];
		}
		$givecheckbox = false;
		if(getcustom('coupon_givecheckbox')){
			$givecheckbox = true;
		}

        //订阅消息
        $tmplids = [];
        if(getcustom('coupon_cika_wxtmpl')){
            $wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->find();
            if(!empty($wx_tmplset['tmpl_couponhexiao'])){
                $tmplids[] = $wx_tmplset['tmpl_couponhexiao'];
            }
        }

		return $this->json(['status'=>1,'data'=>$datalist,'givecheckbox'=>$givecheckbox,'tmplids'=>$tmplids]);
	}

	//优惠券列表
	function couponlist(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;

		$where2 = "find_in_set('-1',showtj)";
		if($this->member){
			$where2 .= " or find_in_set('".$this->member['levelid']."',showtj)";
			if($this->member['subscribe']==1){
				$where2 .= " or find_in_set('0',showtj)";
			}
		}

		if(input('?param.bid') && input('param.bid')!==''){
			$bid = input('param.bid');
			$datalist= Db::name('coupon')->where("aid=".aid." and tolist=1 and ($where2) and starttime<='".date('Y-m-d H:i:s')."' and endtime>='".date('Y-m-d H:i:s')."'")->where('bid',$bid)->order('sort desc,id desc')->page($pagenum,$pernum)->select()->toArray();
		}else{
            $datalist= Db::name('coupon')->where("aid=".aid." and tolist=1 and ($where2) and starttime<='".date('Y-m-d H:i:s')."' and endtime>='".date('Y-m-d H:i:s')."'")->order('sort desc,id desc')->page($pagenum,$pernum)->select()->toArray();
        }
		
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			$haveget = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('couponid',$v['id'])->count();
			$datalist[$k]['haveget'] = $haveget;
			$datalist[$k]['starttime'] = date('m-d H:i',strtotime($v['starttime']));
			$datalist[$k]['endtime'] = date('m-d H:i',strtotime($v['endtime']));
			$datalist[$k]['type_txt'] = \app\commons\Coupon::getCouponTypeTxt($v['type']);
			if($v['yxqtype'] == 1){
				$yxqtime = explode(' ~ ',$v['yxqtime']);
				$datalist[$k]['yxqdate'] = date('Y-m-d',strtotime($yxqtime[1]));
			}elseif($v['yxqtype'] == 2){
                //领取后x天有效
				$datalist[$k]['yxqdate'] = date('Y-m-d',time() + 86400 * $v['yxqdate']);
			}elseif($v['yxqtype'] == 3){
                //次日起计算有效期
                $datalist[$k]['yxqdate'] = date('Y-m-d',strtotime(date('Y-m-d')) + 86400 * ($v['yxqdate'] + 1) - 1);
            }elseif($v['yxqtype'] == 4){
			    if(getcustom('coupon_get_assert_time')){
                    //领取后N天
                    $starttime =time()+ $v['yxqdate4_assert']*86400;
                    $endtime =$starttime+ $v['yxqdate4_expire']*86400;
                    $datalist[$k]['yxqdate'] =date('Y-m-d',$endtime);
                }
            }
			if($v['bid'] > 0){
				$binfo = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->find();
				$datalist[$k]['bname'] = $binfo['name'];
			}
            if(getcustom('plug_tengrui')) {
                //判断是否是否符合一户、用户组，不符合则直接去掉
                $tr_check = new \app\commons\TengRuiCheck();
                $check_coupon = $tr_check->check_coupon($this->member,$v);
                if($check_coupon && $check_coupon['status'] == 0){
                    unset($datalist[$k]);
                }
            }
            $use_tongzheng = 0;//是否使用通证兑换
            if(getcustom('product_givetongzheng')){
                if($v['use_tongzheng'] && $v['tongzheng']>0){
                    $use_tongzheng = 1;
                }
            }
            $datalist[$k]['use_tongzheng'] = $use_tongzheng;
		}
        if(getcustom('plug_tengrui')) {
            $len = count($datalist);
            if($len<10){
                //重置索引,防止上方去掉的数据产生空缺
                $datalist=array_values($datalist);
            }
        }
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	//领取优惠券
	function getcoupon(){
		$this->checklogin();
		$post = input('post.');
		$coupon = Db::name('coupon')->where('aid',aid)->where('id',$post['id'])->find();
		if(!$coupon) return $this->json(['status'=>0,'msg'=>t('优惠券').'已下架']);
		if(strtotime($coupon['starttime']) > time()) return $this->json(['status'=>0,'msg'=>'领取活动还没开始']);
		if(strtotime($coupon['endtime']) < time()) return $this->json(['status'=>0,'msg'=>'领取活动已结束']);
		if($coupon['stock']<=0) return $this->json(['status'=>0,'msg'=>'已抢光了']);
		$haveget = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('couponid',$post['id'])->count();
		if($haveget >= $coupon['perlimit']) return $this->json(['status'=>0,'msg'=>'您已领取过了']);

		if(getcustom('plug_tengrui')) {
            //判断是否是否符合一户、用户组
            $tr_check = new \app\commons\TengRuiCheck();
            $check_coupon = $tr_check->check_coupon($this->member,$coupon);
            if($check_coupon && $check_coupon['status'] == 0){
                return $this->json(['status'=>$check_coupon['status'],'msg'=>$check_coupon['msg']]);
            }
            $tr_roomId = $check_coupon['tr_roomId'];
        }

		$gettj = explode(',',$coupon['gettj']);
		if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
			if(in_array('0',$gettj)){ //关注用户才能领
				if($this->member['subscribe']!=1){
					$appinfo = \app\commons\System::appinfo(aid,'mp');
					return $this->json(['status'=>0,'msg'=>'请先关注'.$appinfo['nickname'].'公众号']);
				}
			}else{
				if(count($gettj)==1){
					$levelname = Db::name('member_level')->where('id',$gettj[0])->value('name');
					return $this->json(['status'=>0,'msg'=>'您没有领取权限，仅限'.$levelname.'才能领取']);
				}
				return $this->json(['status'=>0,'msg'=>'您没有领取权限']);
			}
		}
        $use_tongzheng = 0;//是否使用通证兑换
        if(getcustom('product_givetongzheng')){
            if($coupon['use_tongzheng'] && $coupon['tongzheng']>0){
                $use_tongzheng = 1;
            }
        }
        if($use_tongzheng){
            if($coupon['tongzheng'] > 0){
                if($this->member['tongzheng'] < $coupon['tongzheng']){
                    return $this->json(['status'=>0,'msg'=>t('通证').'不足']);
                }
                \app\commons\Member::addtongzheng(aid,mid,-$coupon['tongzheng'],'兑换'.t('优惠券'));
            }
        }else{
            if($coupon['price'] > 0){
                return $this->json(['status'=>0,'msg'=>'需要购买']);
            }
            if($coupon['score'] > 0){
                if($this->member['score'] < $coupon['score']){
                    return $this->json(['status'=>0,'msg'=>t('积分').'不足']);
                }
                \app\commons\Member::addscore(aid,mid,-$coupon['score'],'兑换'.t('优惠券'));
            }
        }

        if(getcustom('plug_tengrui')) {
            \app\commons\Coupon::send(aid,mid,$coupon['id'],false,$tr_roomId);
        }else{
          $rs =  \app\commons\Coupon::send(aid,mid,$coupon['id']);
		  if(!$rs['status']){
			 return $this->json(['status'=>0,'msg'=>$rs['msg']]);
		  }
        }
		return $this->json(['status'=>1,'msg'=>'领取成功','haveget'=>$haveget+1,'url'=>true]);
	}
    public function getcouponpack(){
        if(getcustom('coupon_pack')){
            //使用券包
            $post = input('post.');
            $coupon = Db::name('coupon')->where('aid',aid)->where('id',$post['id'])->find();
            if(!$coupon) return $this->json(['status'=>0,'msg'=>t('优惠券').'已下架']);
            if(strtotime($coupon['starttime']) > time()) return $this->json(['status'=>0,'msg'=>'活动还没开始']);
            if(strtotime($coupon['endtime']) < time()) return $this->json(['status'=>0,'msg'=>'活动已结束']);
            if($coupon['pack_coupon_ids']){
                $cids = explode(',',$coupon['pack_coupon_ids']);
                foreach ($cids as $id){
                    \app\commons\Coupon::send(aid,mid,$id);
                }
            }

            return $this->json(['status'=>1,'msg'=>'使用成功','url'=>true]);
        }
    }
    //领取赠送优惠券
    function receiveCoupon(){
        $this->checklogin();
        $post = input('post.');
        if(empty($post['id'])) return $this->json(['status'=>0,'msg'=>'参数错误']);
        $coupon = Db::name('coupon')->where('aid',aid)->where('id',$post['id'])->find();
        if(!$coupon) return $this->json(['status'=>0,'msg'=>t('优惠券').'已下架']);
        if($coupon['isgive'] == 0) return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'未开启转赠']);
//        if(strtotime($coupon['starttime']) > time()) return $this->json(['status'=>0,'msg'=>'领取活动还没开始']);
//        if(strtotime($coupon['endtime']) < time()) return $this->json(['status'=>0,'msg'=>'领取活动已结束']);
//        if($coupon['stock']<=0) return $this->json(['status'=>0,'msg'=>'已抢光了']);
        $haveget = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('couponid',$post['id'])->count();
        if($haveget >= $coupon['perlimit']) return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'您已领取过了']);

        if(getcustom('plug_tengrui')) {
            //判断是否是否符合一户、用户组
            $tr_check = new \app\commons\TengRuiCheck();
            $check_coupon = $tr_check->check_coupon($this->member,$coupon);
            if($check_coupon && $check_coupon['status'] == 0){
                return $this->json(['status'=>$check_coupon['status'],'msg'=>$check_coupon['msg']]);
            }
            $tr_roomId = $check_coupon['tr_roomId'];
        }
        
        $gettj = explode(',',$coupon['gettj']);
        if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
            if(count($gettj)==1){
                $levelname = Db::name('member_level')->where('id',$gettj[0])->value('name');
                return $this->json(['status'=>0,'msg'=>'您没有领取权限，仅限'.$levelname.'才能领取']);
            }
            return $this->json(['status'=>0,'msg'=>'您没有领取权限']);
        }

        $record = Db::name('coupon_record')->where('aid',aid)->where('id',$post['rid'])->find();
        if(empty($record)) {
            return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不存在']);
        }
        if($record['mid'] == mid) {
            return $this->json(['status'=>0,'msg'=>'本人不可领取']);
        }
        if($record['from_mid'] > 0) {
            return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'已被他人领取了']);
        }
        Db::name('coupon_record')->where('aid',aid)->where('id',$post['rid'])->update(['mid' => mid, 'from_mid' => $record['mid'], 'receive_time' => time()]);

        return $this->json(['status'=>1,'msg'=>'领取成功','haveget'=>$haveget+1,'url'=>true]);
    }
	//优惠券详情
	public function coupondetail(){
		if(getcustom('coupon_creategiveqr') && strpos(input('param.rid'),'all') === 0){
			$rid = substr(input('param.rid'),3);
			$record = Db::name('coupon_record')->where('aid',aid)->where('id',$rid)->find();
			if($record['from_mid']){
				$rid = Db::name('coupon_record')->where('aid',aid)->where('couponid',$record['couponid'])->whereRaw('from_mid is null')->where('mid',$record['from_mid'])->value('id');
			}
		}else{
			$rid = input('param.rid/d');
		}
		if($rid){
		    //如开启赠送，未登录也可查看
			$record = Db::name('coupon_record')->where('aid',aid)->where('id',$rid)->find();
			$coupon = Db::name('coupon')->where('id',$record['couponid'])->find();
			if(!$coupon['isgive']) {
                $this->checklogin();
            } else {
			    if($record['from_mid']) {
                    //已经被领取，不可再次赠送，仅自用
                    $coupon['isgive'] = 0;
                }
            }
			$record['createtime'] = date('Y-m-d H:i:s',$record['createtime']);
			$record['usetime'] = date('Y-m-d H:i:s',$record['usetime']);
			$record['starttime'] = date('Y-m-d H:i:s',$record['starttime']);
			$record['endtime'] = date('Y-m-d H:i:s',$record['endtime']);
            if(empty($record['hexiaoqr'])){
                $record['hexiaoqr'] = $up_data['hexiaoqr'] = createqrcode(m_url('admin/hexiao/hexiao?type=coupon&co='.$record['code'], $record['aid']));
                $up = Db::name('coupon_record')->where('aid',aid)->where('id',$rid)->update($up_data);
            }
            //isgive 使用范围:0自用，1自用+转赠，2仅转赠
			if(mid != $record['mid'] || $coupon['isgive'] == 2 || $coupon['type'] == 20) {
			    unset($record['hexiaoqr']);unset($record['code']);
            }
			if(getcustom('restaurant_cashdesk_scan_coupon_qrcode')){
			    //实现收银台扫描二维码 使用该优惠券
                if($coupon['type'] ==5 || $coupon['type'] ==51 ){
                    $record['cashdesk_hexiaoqr'] = createqrcode('co='.$record['code']);
                }
            }
            if(getcustom('restaurant_cashdesk_discount_coupon')){
                //实现收银台扫描二维码 使用该优惠券,折扣券
                if( $coupon['type'] ==10 ){
                    $record['cashdesk_hexiaoqr'] = createqrcode('co='.$record['code']);
                }
            }
		}else{
            $id = input('param.id/d');
            if(!$id){
                return $this->json(['status'=>0,'msg'=>'参数错误']);
            }
			$coupon = Db::name('coupon')->where('aid',aid)->where('id',$id)->find();
            if(empty($coupon)){
                return $this->json(['status'=>0,'msg'=>'该'.t('优惠券').'不存在']);
            }
			$record = [];
			$canget = true;
			if(strtotime($coupon['starttime']) > time()){
				$canget = false;
			}
			if(strtotime($coupon['endtime']) < time()){
				$canget = false;
			}
			if($coupon['stock']<=0){
				$canget = false;
			}
			$haveget = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('couponid',$coupon['id'])->count();	
			if($haveget >= $coupon['perlimit']){
				$canget = false;
			}
			$coupon['canget'] = $canget;
            //isgive 使用范围:0自用，1自用+转赠，2仅转赠
            if($coupon['isgive'] == 2) {
                unset($record['hexiaoqr']);unset($record['code']);
            }
		}
        if(getcustom('yuyue_coupon')){
            $product = Db::name('yuyue_product')->where('aid',aid)->whereFindInSet('couponids',$coupon['id'])->find();
            if($product) $record['yuyue_proid'] = $product['id'];
        }
        $coupon['pack_coupon_list']=[];
        if(getcustom('coupon_pack')){
            if($coupon['type'] == 20 && $coupon['pack_coupon_ids']){
                $couponList = Db::name('coupon')->whereIn('id',$coupon['pack_coupon_ids'])->select()->toArray();
                $coupon['pack_coupon_list'] = $couponList ? $couponList : [];
            }
        }        

		if($coupon['bid']!=0) $coupon['bname'] = Db::name('business')->where('id',$coupon['bid'])->value('name');
		$rdata = [];
        $record['surplus_count'] = 0; //剩余次数
        if($coupon['limit_count'] !== '' && $record['used_count'] !== ''){
            $record['surplus_count'] = $coupon['limit_count']-$record['used_count'];
        }
        $use_tongzheng = 0;//是否使用通证兑换
        if(getcustom('product_givetongzheng')){
            if($coupon['use_tongzheng'] && $coupon['tongzheng']>0){
                $use_tongzheng = 1;
            }
        }
        $coupon['use_tongzheng'] = $use_tongzheng;
		$rdata['record'] = $record;
		$rdata['coupon'] = $coupon;
		return $this->json($rdata);
	}
	//赠送列表
	function coupongive(){
		$this->checklogin();
		$frommid = input('param.frommid/d');
		$rids = input('param.rids');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',$frommid];
		$where[] = ['id','in',$rids];
		$datalist = Db::name('coupon_record')->field('id,bid,type,limit_count,used_count,couponid,couponname,money,discount,minprice,from_unixtime(starttime,"%Y-%m-%d %H:%i") starttime,from_unixtime(endtime,"%Y-%m-%d %H:%i") endtime,from_unixtime(usetime) usetime,from_unixtime(createtime) createtime,status')->where($where)->order('id desc')->select()->toArray();
		if(!$datalist) return $this->json(['status'=>-4,'msg'=>'没有可领取的'.t('优惠券'),'url'=>'/pages/my/usercenter']);
		foreach($datalist as $k=>$v){
			if($v['bid'] > 0){
				$binfo = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->find();
				$datalist[$k]['bname'] = $binfo['name'];
			}
            $coupon = Db::name('coupon')->where('id',$v['couponid'])->find();
            $datalist[$k]['isgive'] = $coupon['isgive'];
            $datalist[$k]['fwtype'] = $coupon['fwtype'];
            $datalist[$k]['fwscene'] = $coupon['fwscene'];
			$datalist[$k]['rewardedvideoad'] = $coupon['rewardedvideoad'];
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
    //领取赠送优惠券多张
    function receiveCoupon2(){
        $this->checklogin();
		$frommid = input('param.frommid/d');
		$rids = input('param.rids');
		if($frommid == mid) return $this->json(['status'=>0,'msg'=>'不可领取自己赠送的'.t('优惠券')]);
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',$frommid];
		$where[] = ['id','in',$rids];
		$datalist = Db::name('coupon_record')->where($where)->order('id desc')->select()->toArray();
		if(!$datalist) return $this->json(['status'=>0,'msg'=>t('优惠券').'已被领取了']);
		$successnum = 0;
		$errmsg = '';
		foreach($datalist as $k=>$record){
			$coupon = Db::name('coupon')->where('aid',aid)->where('id',$record['couponid'])->find();
			if(!$coupon){
				$errmsg .= '['.$record['couponname'].']已下架 ';
				continue;
			}
			if($coupon['isgive'] == 0){
				$errmsg .= '['.$record['couponname'].']未开启转赠 ';
				continue;
			}
			$haveget = Db::name('coupon_record')->where('aid',aid)->where('mid',mid)->where('couponid',$coupon['id'])->count();
			if($haveget >= $coupon['perlimit']){
				$errmsg .= '['.$record['couponname'].']每人最多只能领取'.$coupon['perlimit'].'张 ';
				continue;
			}
			$gettj = explode(',',$coupon['gettj']);
			if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
				if(count($gettj)==1){
					$levelname = Db::name('member_level')->where('id',$gettj[0])->value('name');
					$errmsg .= '['.$record['couponname'].']您没有领取权限，仅限'.$levelname.'才能领取 ';
					continue;
				}
				$errmsg .= '['.$record['couponname'].']您没有领取权限 ';
				continue;
			}
			if($record['from_mid'] > 0) {
				$errmsg .= '['.$record['couponname'].']已被他人领取了 ';
				continue;
			}
			Db::name('coupon_record')->where('aid',aid)->where('id',$record['id'])->update(['mid' => mid, 'from_mid' => $record['mid'], 'receive_time' => time()]);
			$successnum++;
		}
		if($successnum == 0) return $this->json(['status'=>0,'msg'=>$errmsg]);
		if($errmsg == '') return $this->json(['status'=>1,'msg'=>'领取成功']);
		if($errmsg != '') return $this->json(['status'=>1,'msg'=>$errmsg]);
    }
    //核销记录
    public function record(){
        $crid = input('param.crid/d');
        $page = input('param.pagenum');
        $limit = input('param.limit',10);
        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order');
        }else{
            $order = 'id desc';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['orderid','=',$crid];
        $where[] = ['type','=','coupon'];
        $data = Db::name('hexiao_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
        if(!$data) $data = [];
        else {
            foreach ($data as $k => $v){
                $data[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
            }
        }
        return $this->json(['status'=>1,'datalist'=>$data]);
    }
	//购买优惠券
	public function buycoupon(){
		$this->checklogin();
		$id = input('param.id/d');
		$coupon = db('coupon')->where(['id'=>$id,'aid'=>aid])->find();
		if(!$coupon) return $this->json(['status'=>0,'msg'=>t('优惠券').'已下架']);
		if(strtotime($coupon['starttime']) > time()) return $this->json(['status'=>0,'msg'=>'领取活动还没开始']);
		if(strtotime($coupon['endtime']) < time()) return $this->json(['status'=>0,'msg'=>'领取活动已结束']);
		if($coupon['stock']<=0) return $this->json(['status'=>0,'msg'=>'已抢光了']);
		$haveget = db('coupon_record')->where(['aid'=>aid,'mid'=>mid,'couponid'=>$id])->count();	
		if($haveget >= $coupon['perlimit']) return $this->json(['status'=>0,'msg'=>'您已购买过了']);
        if(getcustom('plug_tengrui')) {
            //判断是否是否符合一户、用户组
            $tr_check = new \app\commons\TengRuiCheck();
            $check_coupon = $tr_check->check_coupon($this->member,$coupon);
            if($check_coupon && $check_coupon['status'] == 0){
                return $this->json(['status'=>$check_coupon['status'],'msg'=>$check_coupon['msg']]);
            }
            $tr_roomId = $check_coupon['tr_roomId'];
        }
		//创建订单
		$order = [];
		$order['aid'] = aid;
		$order['bid'] = $coupon['bid'];
		$order['mid'] = mid;
		$order['cpid'] = $coupon['id'];
		$order['title'] = $coupon['name'];
		$order['price'] = $coupon['price'];
		$order['ordernum'] = date('ymdHis').aid.rand(1000,9999);
		$order['createtime'] = time();
		$order['status'] = 0;
        if(getcustom('plug_tengrui')) {
            $order['tr_roomId'] = $tr_roomId;
        }
		$orderid = db('coupon_order')->insertGetId($order);
        if(getcustom('commission_times_coupon')){
            $commission = $this-> getcommission($coupon,$orderid,$coupon['price'],1);
            if($commission['parent1'] && $commission['parent1commission']){
                Db::name('coupon_order')->where('id',$orderid)-> update(['parent1' => $commission['parent1'],'parent1commission' => $commission['parent1commission'],'parent2' => $commission['parent2'],'parent2commission' =>$commission['parent2commission'],'parent3' => $commission['parent3'],'parent3commission' => $commission['parent3commission']]);
            }
        }
		$payorderid = \app\models\Payorder::createorder(aid,$order['bid'],$order['mid'],'coupon',$orderid,$order['ordernum'],$order['title'],$order['price']);
		return $this->json(['status'=>1,'payorderid'=>$payorderid]);
	}

    public function getcommission($product,$orderid=0,$commission_totalprice = 0,$num=0){
        if(getcustom('commission_times_coupon')){
            $member = $this->member;
            $ogupdate =  ['parent1' =>0,'parent2' => 0,'parent3' => 0 ,'parent1commission' => 0,'parent2commission' => 0,'parent3commission' => 0 ];

            if(!$product || !$member || $commission_totalprice==0 || $product['commissionset'] ==-1){
                return  $ogupdate;
            }
            if($product['commissionset']!=-1){
                $isfg = 0;
                $istc1 = 0;
                $istc2 = 0;
                $istc3 = 0;
                if($member['pid']){
                    $parent1 = Db::name('member')->where('aid',aid)->where('id',$member['pid'])->find();
                    if($parent1){
                        $agleveldata1 = Db::name('member_level')->where('aid',aid)->where('id',$parent1['levelid'])->find();
                        if($agleveldata1['can_agent']!=0 && (!$agleveldata1['commission_appointlevelid'] || in_array($member['levelid'],explode(',',$agleveldata1['commission_appointlevelid'])))){
                            $ogupdate['parent1'] = $parent1['id'];
                        }
                    }
                }

                if($parent1['pid']){
                    $parent2 = Db::name('member')->where('aid',aid)->where('id',$parent1['pid'])->find();
                    if($parent2){
                        $agleveldata2 = Db::name('member_level')->where('aid',aid)->where('id',$parent2['levelid'])->find();
                        if($agleveldata2['can_agent']>1 && (!$agleveldata2['commission_appointlevelid'] || in_array($member['levelid'],explode(',',$agleveldata2['commission_appointlevelid'])))){
                            $ogupdate['parent2'] = $parent2['id'];
                        }
                    }
                }
                if($parent2['pid']){
                    $parent3 = Db::name('member')->where('aid',aid)->where('id',$parent2['pid'])->find();
                    if($parent3){
                        $agleveldata3 = Db::name('member_level')->where('aid',aid)->where('id',$parent3['levelid'])->find();
                        if($agleveldata3['can_agent']>2 && (!$agleveldata3['commission_appointlevelid'] || in_array($member['levelid'],explode(',',$agleveldata3['commission_appointlevelid'])))){
                            $ogupdate['parent3'] = $parent3['id'];
                        }
                    }
                }
                if($parent3['pid']){
                    $parent4 = Db::name('member')->where('aid',aid)->where('id',$parent3['pid'])->find();
                    if($parent4){
                        $agleveldata4 = Db::name('member_level')->where('aid',aid)->where('id',$parent4['levelid'])->find();
                        if($product['commissionpingjiset'] != 0){
                            if($product['commissionpingjiset'] == 1){
                                $commissionpingjidata1 = json_decode($product['commissionpingjidata1'],true);
                                $agleveldata4['commission_parent_pj'] = $commissionpingjidata1[$agleveldata4['id']];
                            }elseif($product['commissionpingjiset'] == 2){
                                $commissionpingjidata2 = json_decode($product['commissionpingjidata2'],true);
                                $agleveldata4['commission_parent_pj'] = $commissionpingjidata2[$agleveldata4['id']];
                            }else{
                                $agleveldata4['commission_parent_pj'] = 0;
                            }
                        }
                        //持续推荐奖励
                        if($agleveldata4['can_agent'] > 0 && ($agleveldata4['commission_parent'] > 0 || ($parent4['levelid']==$parent3['levelid'] && $agleveldata4['commission_parent_pj'] > 0))){
                            $ogupdate['parent4'] = $parent4['id'];
                        }
                    }
                }
                if($product['commissionset']==1){//按商品设置的分销比例
                    $commissiondata = json_decode($product['commissiondata1'],true);
                    if($commissiondata){
                        if($agleveldata1) $ogupdate['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $commission_totalprice * 0.01;
                        if($agleveldata2) $ogupdate['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $commission_totalprice * 0.01;
                        if($agleveldata3) $ogupdate['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $commission_totalprice * 0.01;
                    }
                }elseif($product['commissionset']==2){//按固定金额
                    $commissiondata = json_decode($product['commissiondata2'],true);
                    if($commissiondata){
                        if(getcustom('fengdanjiangli') && $product['fengdanjiangli']){

                        }else{
                            if($agleveldata1) $ogupdate['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $num;
                            if($agleveldata2) $ogupdate['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $num;
                            if($agleveldata3) $ogupdate['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $num;
                        }
                    }
                }else{ //按会员等级设置的分销比例
                    if($agleveldata1){
                        if(getcustom('plug_ttdz') && $isfg == 1){
                            $agleveldata1['commission1'] = $agleveldata1['commission4'];
                        }
                        if($agleveldata1['commissiontype']==1){ //固定金额按单
                            if($istc1==0){
                                $ogupdate['parent1commission'] = $agleveldata1['commission1'];
                                $istc1 = 1;
                            }
                        }else{
                            $ogupdate['parent1commission'] = $agleveldata1['commission1'] * $commission_totalprice * 0.01;
                        }
                    }
                    if($agleveldata2){
                        if(getcustom('plug_ttdz') && $isfg == 1){
                            $agleveldata2['commission2'] = $agleveldata2['commission5'];
                        }
                        if($agleveldata2['commissiontype']==1){
                            if($istc2==0){
                                $ogupdate['parent2commission'] = $agleveldata2['commission2'];
                                $istc2 = 1;
                                //持续推荐奖励
                                if($agleveldata2['commission_parent'] > 0 && $ogupdate['parent1']) {
                                    $ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $agleveldata2['commission_parent'];
                                }
                            }
                        }else{
                            $ogupdate['parent2commission'] = $agleveldata2['commission2'] * $commission_totalprice * 0.01;
                            //持续推荐奖励
                            if($agleveldata2['commission_parent'] > 0 && $ogupdate['parent1commission'] > 0 && $ogupdate['parent1']) {
                                $ogupdate['parent2commission'] = $ogupdate['parent2commission'] + $ogupdate['parent1commission'] * $agleveldata2['commission_parent'] * 0.01;
                            }
                        }

                    }
                    if($agleveldata3){
                        if(getcustom('plug_ttdz') && $isfg == 1){
                            $agleveldata3['commission3'] = $agleveldata3['commission6'];
                        }

                        if($agleveldata3['commissiontype']==1){
                            if($istc3==0){
                                $ogupdate['parent3commission'] = $agleveldata3['commission3'];
                                $istc3 = 1;
                                //持续推荐奖励
                                if($agleveldata3['commission_parent'] > 0 && $ogupdate['parent2']) {
                                    $ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $agleveldata3['commission_parent'];
                                }
                            }
                        }else{
                            $ogupdate['parent3commission'] = $agleveldata3['commission3'] * $commission_totalprice * 0.01;
                            //持续推荐奖励
                            if($agleveldata3['commission_parent'] > 0 && $ogupdate['parent2commission'] > 0 && $ogupdate['parent2']) {
                                $ogupdate['parent3commission'] = $ogupdate['parent3commission'] + $ogupdate['parent2commission'] * $agleveldata3['commission_parent'] * 0.01;
                            }
                        }
                    }
                }
            }
            $totalcommission = 0;
            if($ogupdate['parent1'] && ($ogupdate['parent1commission'] || $ogupdate['parent1score'])){
                Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent1'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>0,'type'=>'coupon','commission'=>$ogupdate['parent1commission'],'remark'=>'下级购买优惠券奖励','createtime'=>time()]);
                $totalcommission += $ogupdate['parent1commission'];
            }
            if($ogupdate['parent2'] && ($ogupdate['parent2commission'] || $ogupdate['parent2score'])){
                Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent2'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>0,'type'=>'coupon','commission'=>$ogupdate['parent2commission'],'remark'=>'下二级购买优惠券奖励','createtime'=>time()]);
                $totalcommission += $ogupdate['parent2commission'];
            }
            if($ogupdate['parent3'] && ($ogupdate['parent3commission'] || $ogupdate['parent3score'])){
                Db::name('member_commission_record')->insert(['aid'=>aid,'mid'=>$ogupdate['parent3'],'frommid'=>$member['id'],'orderid'=>$orderid,'ogid'=>0,'type'=>'coupon','commission'=>$ogupdate['parent3commission'],'remark'=>'下三级购买优惠券奖励','createtime'=>time()]);
                $totalcommission += $ogupdate['parent3commission'];
            }
           
            return  $ogupdate;
        }
    }
	
	//卡券cardExt
	public function getcardext(){
		$card_id = input('param.card_id');
		$api_ticket = card_ticket(aid);
		$timestamp = time();
		$arrays = array($api_ticket,$timestamp,$card_id);
		sort($arrays , SORT_STRING);
		$cardSign = sha1(implode($arrays));
		$cardExt = json_encode(['timestamp'=>strval($timestamp),'signature'=>$cardSign]);
		return $this->json(['status'=>1,'cardExt'=>$cardExt]);
	}
	//领取会员卡参数
	public function getmembercardparam(){
		$card_id = input('param.card_id');
		if(!$card_id || $card_id == 'undefined'){
			$membercard = Db::name('membercard')->where('aid',aid)->where('status',1)->order('id desc')->find();
			$card_id = $membercard['card_id'];
		}else{
			$membercard = Db::name('membercard')->where('aid',aid)->where('status',1)->where('card_id',$card_id)->order('id desc')->find();
		}
		$url = 'https://api.weixin.qq.com/card/membercard/activate/geturl?access_token='.\app\commons\Wechat::access_token(aid,'mp');
		$rs = request_post($url,jsonEncode(['card_id'=>$card_id,'outer_str'=>'1']));
		$rs = json_decode($rs,true);

		$url = str_replace('#wechat_redirect','',urldecode($rs['url']));
		$params =  explode('&',explode('?',$url)[1]);
		$paramArr = [];
		foreach($params as $v){
			if($v!=''){
				$vArr = explode('=',$v);
				if(in_array($vArr[0],['biz','encrypt_card_id','outer_str'])){
					$paramArr[$vArr[0]] = $vArr[1];
				}
			}
		}
		return $this->json(['status'=>1,'extraData'=>$paramArr,'ret_url'=>$membercard['ret_url']]);
	}
	public function koulingSet()
    {
        if(getcustom('yx_kouling')){
            $bid = input('post.bid');
            $select_bid = input('post.select_bid',0);

            $bid = $bid != '' ? $bid : $select_bid;
            $bid = $bid > 0 ? $bid : 0;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',$bid];

            $set = \db('kouling_set')->where($where)->find();

            return $this->json(['status'=>1,'info'=>$set ? $set: []]);
        }
    }
    //
    public function kouling(){
        if(getcustom('yx_kouling')){
            //口令
            $this->checklogin();
            $kouling = input('post.kouling');
            $bid = input('post.bid');
            $select_bid = input('post.select_bid',0);
            $bid = $bid != '' ? $bid : $select_bid;
            $bid = $bid > 0 ? $bid : 0;
            if(empty($kouling)) {
                return $this->json(['status'=>0,'msg'=>'请输入口令']);
            }

            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',$bid];
            $where[] = ['name','=',$kouling];
            $where[] = ['starttime','<=',time()];
            $where[] = ['endtime','>=',time()];
            $where[] = ['status','=',1];

            $info = Db::name('kouling')->where($where)->find();
            if(empty($info)){
                return $this->json(['status'=>0,'msg'=>'口令不存在或已过期，请重新输入']);
            }
            if($bid > 0 && $info['bid'] > 0 && $info['bid'] != $bid){
                return $this->json(['status'=>0,'msg'=>'口令不存在或已过期，请重新输入']);
            }
            $gettj = explode(',',$info['gettj']);
            if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
                if(in_array('0',$gettj)){ //关注用户才能领
                    if($this->member['subscribe']!=1){
                        $appinfo = \app\commons\System::appinfo(aid,'mp');
                        return $this->json(['status'=>0,'msg'=>'请先关注'.$appinfo['nickname'].'公众号']);
                    }
                }else{
                    return $this->json(['status'=>0,'msg'=>'您没有参与权限']);
                }
            }
            if($info['zjnum'] >= $info['num'] && $info['num'] > 0) {
                return $this->json(['status'=>0,'msg'=>'奖品已领完']);
            }
            if($info['perday'] >0){
                $memberTodayCount = \db('kouling_record')->where('aid',aid)->where('objid',$info['id'])->where('mid',mid)->where('createdate',date('Y-m-d'))->count();
                if($memberTodayCount && $memberTodayCount >= $info['perday']) {
                    return $this->json(['status'=>0,'msg'=>'今日参加次数已达上限，请明日再来']);
                }
            }
            if($info['pertotal'] > 0) {
                $memberCount = \db('kouling_record')->where('aid',aid)->where('objid',$info['id'])->where('mid',mid)->count();
                if($memberCount && $memberCount >= $info['pertotal']) {
                    return $this->json(['status'=>0,'msg'=>'此口令活动参与已达上限，换个口令吧']);
                }
            }

            //赠送优惠券
            if($info['give_coupon'] == 1 && $info['coupon_ids']) {
                $coupon_ids = explode(',',$info['coupon_ids']);
                foreach ($coupon_ids as $coupon_id){
                    \app\commons\Coupon::send(aid,mid,$coupon_id);
                }
            }

            \db('kouling_record')->insert([
                'aid'=>aid,
                'bid'=>$info['bid'],
                'objid'=>$info['id'],
                'name'=>$info['name'],
                'mid'=>mid,
                'headimg'=>$this->member['headimg'],
                'nickname'=>$this->member['nickname'],
                'coupon_ids'=>$info['coupon_ids'],
                'money'=>$info['money'],
                'score'=>$info['score'],
                'createtime'=>time(),
                'createdate'=>date('Y-m-d'),
                'status'=>1
            ]);
            Db::name('kouling')->where('id',$info['id'])->inc('zjnum')->update();

            return $this->json(['status'=>1,'msg'=>'恭喜获得奖励']);
        }
    }

    public function riddle()
    {
        if(getcustom('yx_riddle')){
            //谜语
            $this->checklogin();
            $id = input('post.id');
            $name = input('post.kouling');
            $select_bid = input('post.select_bid');
            if (empty($id)) {
                return $this->json(['status' => 0, 'msg' => '参数错误']);
            }

            $where = [];
            $where[] = ['aid','=',aid];
            if($select_bid > 0){
//            $where[] = ['bid','=',$select_bid];
            }
            $where[] = ['id','=',$id];
//        $where[] = ['starttime','<=',time()];
//        $where[] = ['endtime','>=',time()];
            $where[] = ['status','=',1];

            $info = Db::name('riddle')->where($where)->find();
            if(empty($info)){
                return $this->json(['status'=>0,'msg'=>'谜语不存在或已过期']);
            }
            if($select_bid > 0 && $info['bid'] > 0 && $info['bid'] != $select_bid){
                return $this->json(['status'=>0,'msg'=>'活动不属于此商家，请切换到对应商家']);
            }
            if(empty($info['title'])) $info['title'] = '猜谜语';
            if(empty($info['alias'])) $info['alias'] = '谜底';
            if(input('post.action') == 'submit') {
                if(time() < $info['starttime']){
                    return $this->json(['status'=>0,'msg'=>'活动还未开始']);
                }
                if(time() > $info['endtime']){
                    return $this->json(['status'=>0,'msg'=>'活动已结束']);
                }
                if(empty($name)){
                    return $this->json(['status'=>0,'msg'=>'请输入'.$info['alias']]);
                }
                $gettj = explode(',',$info['gettj']);
                if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
                    if(in_array('0',$gettj)){ //关注用户才能领
                        if($this->member['subscribe']!=1){
                            $appinfo = \app\commons\System::appinfo(aid,'mp');
                            return $this->json(['status'=>0,'msg'=>'请先关注'.$appinfo['nickname'].'公众号']);
                        }
                    }else{
                        return $this->json(['status'=>0,'msg'=>'您没有参与权限']);
                    }
                }
                if($info['zjnum'] >= $info['num'] && $info['num'] > 0) {
                    return $this->json(['status'=>0,'msg'=>'奖品已领完']);
                }
                if($info['perday'] >0){
                    $memberTodayCount = \db('riddle_record')->where('aid',aid)->where('objid',$info['id'])->where('mid',mid)->where('createdate',date('Y-m-d'))->count();
                    if($memberTodayCount && $memberTodayCount >= $info['perday']) {
                        return $this->json(['status'=>0,'msg'=>'今日参加次数已达上限，请明日再来']);
                    }
                }
                if($info['pertotal'] > 0) {
                    $memberCount = \db('riddle_record')->where('aid',aid)->where('objid',$info['id'])->where('mid',mid)->count();
                    if($memberCount && $memberCount >= $info['pertotal']) {
                        return $this->json(['status'=>0,'msg'=>'此活动参与已达上限']);
                    }
                }

                if($name != $info['name']){
                    return $this->json(['status'=>0,'msg'=>$info['alias'].'不正确']);
                }

                //赠送优惠券
                if($info['give_coupon'] == 1 && $info['coupon_ids']) {
                    $coupon_ids = explode(',',$info['coupon_ids']);
                    foreach ($coupon_ids as $coupon_id){
                        \app\commons\Coupon::send(aid,mid,$coupon_id);
                    }
                }

                \db('riddle_record')->insert([
                    'aid'=>aid,
                    'bid'=>$info['bid'],
                    'objid'=>$info['id'],
                    'name'=>$info['name'],
                    'mid'=>mid,
                    'headimg'=>$this->member['headimg'],
                    'nickname'=>$this->member['nickname'],
                    'coupon_ids'=>$info['coupon_ids'],
                    'money'=>$info['money'],
                    'score'=>$info['score'],
                    'createtime'=>time(),
                    'createdate'=>date('Y-m-d'),
                    'status'=>1
                ]);
                Db::name('riddle')->where('id',$info['id'])->inc('zjnum')->update();

                return $this->json(['status'=>1,'msg'=>'恭喜获得奖励']);
            }

            return $this->json(['status'=>1,'msg'=>'', 'detail' => $info]);
        }
    }

    public function jidian()
    {
        if(getcustom('yx_jidian')){
            //集点
            $bid = input('post.bid');
            $guize = '';
            $set = Db::name('jidian_set')->where('aid', aid)->where('bid', $bid)->find();
            if ($set && $set['status'] == 1) {
                $guize = $set['guize'];
            }
            return $this->json(['status'=>1,'msg'=>'', 'guize' => $guize]);
        }
    }
    //我的线下优惠券

    public function myXianxiaCoupon(){
        if(getcustom('coupon_xianxia_buy')){
            $this->checklogin();
            $pagenum = input('post.pagenum');
            if(!$pagenum) $pagenum = 1;
            $pernum = 10;
            $st = input('param.st') ? input('param.st') : 0;
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['is_xianxia_buy','=',1];
            $rid = Db::name('coupon_send')->where('aid',aid)->where('from_mid',mid)->column('rid');
            if($st == 0){//未售卖
                $where[] = ['status','=',0];
                $where[] = ['mid','=',mid];
                if(input('?param.bid') && input('param.bid')!==''){
                    $where[] = ['bid','=',input('param.bid')];
                }
            }elseif($st == 1){
                if($rid){
                    $where[] = ['id','in',$rid];
                } else{
                    $where[] = ['id','in','-1'];
                }
            }
            $sycount =   Db::name('coupon_record')->where('aid',aid)->where('is_xianxia_buy',1)->where('status',0)->where('mid',mid)->count();
            $sendcount  = count($rid);
           
            $datalist = Db::name('coupon_record')->field('id,bid,type,limit_count,used_count,couponid,couponname,money,discount,minprice,from_unixtime(starttime,"%Y-%m-%d %H:%i") starttime,from_unixtime(endtime,"%Y-%m-%d %H:%i") endtime,from_unixtime(usetime) usetime,from_unixtime(createtime) createtime,status,from_mid')->where($where)->order('id desc')->page($pagenum,$pernum)->group('couponid')->select()->toArray();
            if(!$datalist) $datalist = [];
            foreach($datalist as $k=>$v){
                $datalist[$k]['type_txt'] = \app\commons\Coupon::getCouponTypeTxt($v['type']);
                if($v['bid'] > 0){
                    $binfo = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->find();
                    $datalist[$k]['bname'] = $binfo['name'];
                }
                $coupon = Db::name('coupon')->where('id',$v['couponid'])->field('isgive,fwtype,fwscene')->find();
                //转赠优惠券不可二次转赠
                if($v['from_mid']){
                    $datalist[$k]['isgive'] = 0;//不可二次转赠
                }else{
                    $datalist[$k]['isgive'] = $coupon['isgive'];
                }
                $datalist[$k]['fwtype'] = $coupon['fwtype'];
                $datalist[$k]['fwscene'] = $coupon['fwscene'];
                $cwhere = [];
                $cwhere[] = ['aid','=',aid];
                $cwhere[] = ['is_xianxia_buy','=',1];
                $cwhere[] = ['couponid','=',$v['couponid']];
                if($st ==0 ){
                    $cwhere[] = ['mid','=',mid];
                    $cwhere[] = ['status','=',0];
                }else{
                    $cwhere[] = ['id','in',$rid];
                }
                $count = Db::name('coupon_record')->where($cwhere)->count();
                $datalist[$k]['count'] = $count;
            }
            
            return $this->json(['status'=>1,'data'=>$datalist,'sycount'=>$sycount,'sendcount' => $sendcount]);
        }
    }
    public function getXianxiaCount(){
        if(getcustom('coupon_xianxia_buy')){
            $couponid = input('param.couponid');
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $where[] = ['couponid','=',$couponid];
            $where[] = ['is_xianxia_buy','=',1];
            $where[] = ['status','=',0];
            $count = Db::name('coupon_record')->where($where)->count();
            return $this->json(['status'=>1,'data'=>$count?$count:0]);
        }
    }
    public function sendXianxiaCoupon(){
        if(getcustom('coupon_xianxia_buy')){
            $tomid = input('param.tomid');
            $couponid = input('param.couponid');
            $sendcount = input('param.sendcount',0);
            if(!$tomid){
                return $this->json(['status'=>0,'msg'=>'请选择发放对象']);
            }
            if(!$sendcount){
                return $this->json(['status'=>0,'msg'=>'请输入发放数量']);
            }
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['mid','=',mid];
            $where[] = ['couponid','=',$couponid];
            $where[] = ['status','=',0];
            $where[] = ['is_xianxia_buy','=',1];
            $count = Db::name('coupon_record')->where($where)->count();
            if($count < $sendcount){
                return $this->json(['status'=>0,'msg'=>'发放数量超出优惠券数量']);
            }
           
            $datalist = Db::name('coupon_record')->where($where)->order('id asc')->limit(0,$sendcount)->select()->toArray();
            $successnum = 0;
            $errmsg = '';
            $tomember = Db::name('member')->where('id',$tomid)->where('aid',aid)->find();
            if(getcustom('coupon_xianxia_buy')){
                \app\commons\Member::xianxiaUpLevel($tomember,$sendcount,mid);
                $tomember = Db::name('member')->where('id',$tomid)->where('aid',aid)->find();
            }
            foreach($datalist as $k=>$record){
                $coupon = Db::name('coupon')->where('aid',aid)->where('id',$record['couponid'])->find();
                if(!$coupon){
                    $errmsg .= '['.$record['couponname'].']已下架 ';
                    continue;
                }
//                if($coupon['isgive'] == 0){
//                    $errmsg .= '['.$record['couponname'].']未开启转赠 ';
//                    continue;
//                }
                $gettj = explode(',',$coupon['gettj']);
                if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
                    if(count($gettj)==1){
                        $levelname = Db::name('member_level')->where('id',$gettj[0])->value('name');
                        $errmsg .= '['.$record['couponname'].']对方没有领取权限，仅限'.$levelname.'才能领取 ';
                        continue;
                    }
                    $errmsg .= '['.$record['couponname'].']对方没有领取权限 ';
                    continue;
                }
//                if($record['from_mid'] > 0) {
//                    $errmsg .= '['.$record['couponname'].']已被他人领取了 ';
//                    continue;
//                }
                Db::name('coupon_record')->where('aid',aid)->where('id',$record['id'])->update(['mid' => $tomid, 'from_mid' => $record['mid'], 'receive_time' => time()]);
                //计算业绩
                $product_id =   explode(',',$coupon['productids']);
                $product = Db::name('shop_product')->where('aid',aid)->where('id',$product_id[0])->find();
               
                if($product['lvprice']==1){
                    $lvprice_data = json_decode($product['lvprice_data'],true);
                    $coupon_yeji = $lvprice_data[$tomember['levelid']];
                    $from_coupon_yeji = $lvprice_data[$this->member['levelid']];
                }else{
                    $coupon_yeji =$product['sell_price'];
                    $from_coupon_yeji =$product['sell_price'];
                }
                //收益额  = 转发出去的业绩 - 按照自己价格计算的业绩 
                $shouyi = $coupon_yeji - $from_coupon_yeji  ;
                //增加记录
                $send_insert = [
                    'aid' => aid,
                    'rid' => $record['id'],
                    'tomid' => $tomid,
                    'from_mid' =>$record['mid'],
                    'send_time' => time(),
                    'coupon_yeji' => dd_money_format($coupon_yeji),
                    'from_coupon_yeji' => dd_money_format($from_coupon_yeji),
                    'shouyi' => dd_money_format($shouyi)
                ];
                Db::name('coupon_send')->insert($send_insert);
                $successnum++;
            }
            
            if($successnum == 0) return $this->json(['status'=>0,'msg'=>$errmsg]);
            if($errmsg == '') return $this->json(['status'=>1,'msg'=>'发放成功']);
            if($errmsg != '') return $this->json(['status'=>1,'msg'=>$errmsg]);
        }
    }
    //我的团队
    public function xianxiaCouponTeam(){
        if(getcustom('coupon_xianxia_buy')) {
            $mid = input('mid');
            if (!$mid) {
                $mid = mid;
            }
            $date_start = 0;
            $date_end = 0;
            if (input('date_start') && input('date_end')) {
                $date_start = strtotime(input('date_start'));
                $date_end = strtotime(input('date_end'));
            }
            $checkLevelid = input('checkLevelid') ?: 0;

            $userinfo = Db::name('member')->field('id,nickname,headimg,levelid,pid')->where('aid', aid)->where('id', $mid)->find();
            $userlevel = Db::name('member_level')->where('aid', aid)->where('id', $userinfo['levelid'])->find();

            $downdeep = input('param.st/d');
            $pernum = 20;
            $pagenum = input('post.pagenum');
            $keyword = input('post.keyword');
            $where2 = "1=1";
            if ($keyword) $where2 = "(nickname like '%{$keyword}%' or realname like '%{$keyword}%' or tel like '%{$keyword}%')";
            if ($date_start && $date_end) {
                $where_date = "createtime>=" . $date_start . " and createtime<=" . $date_end;
                if ($where2 == '1=1') {
                    $where2 = $where_date;
                } else {
                    $where2 = $where2 . ' and ' . $where_date;
                }
            }
            if ($checkLevelid) {
                $where_level = 'levelid=' . $checkLevelid;
                if ($where2 == '1=1') {
                    $where2 = $where_level;
                } else {
                    $where2 = $where2 . ' and ' . $where_level;
                }
            }
            if (!$pagenum) $pagenum = 1;
            $deep = 999;
            $downmids = \app\commons\Member::getteammids(aid,$mid,$deep);
            $datalist = Db::name('member')->field("id,nickname,headimg,tel,pid,score,from_unixtime(createtime)createtime,levelid")->where('aid', aid)->where('id','in', $downmids)->where($where2)->page($pagenum, $pernum)->order('id desc')->select()->toArray();
            if (!$datalist) $datalist = [];

            foreach ($datalist as $k => $v) {
            
                $level = Db::name('member_level')->where('aid', aid)->where('id', $v['levelid'])->find();
                $datalist[$k]['levelname'] = $level['name'];
                $datalist[$k]['levelsort'] = $level['sort'];
                if ($userlevel['team_showtel'] == 0) {
                    $datalist[$k]['tel'] = '';
                }
            }

            //团队业绩
            $downmids = \app\commons\Member::getteammids(aid, $userinfo['id']);
            $userinfo['team_down_total'] = count($downmids);
            $rdata = [];
            $rdata['datalist'] = $datalist;
            $rdata['userinfo'] = $userinfo;
            $rdata['userlevel'] = $userlevel;
            $rdata['st'] = $downdeep;
            return $this->json($rdata);
        }
    }
    //佣金记录 
    public function xianxianCommissionLog(){
        if(getcustom('coupon_xianxia_buy')){
            $this->checklogin();
            $pagenum = input('post.pagenum');
            if(!$pagenum) $pagenum = 1;
            $pernum = 10;
            $where = [];
            $where[] = ['aid','=',aid];
            if(input('param.type') =='from'){
                $where[] = ['tomid','=',$this->member['id']];
            } else{
                $where[] = ['mid','=',$this->member['id']];
            }
            $st = input('param.st',0);
            $where[] = ['status','=',$st];
            
            $count =  Db::name('xianxia_commission_log')->where($where)->count();
            $list = Db::name('xianxia_commission_log')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
            foreach($list as $key=>$val){
                $tomember = Db::name('member')->where('id',$val['tomid'])->field('id,nickname,headimg,tel,levelid')->find();
                $tolevelname = Db::name('member_level')->where('id',$tomember['levelid'])->value('name');
                $list[$key]['nickname'] = $tomember['nickname'];
                $list[$key]['headimg'] = $tomember['headimg'];
                $list[$key]['tel'] = $tomember['tel'];
                $list[$key]['levelname'] = $tolevelname;
                $sendmember = Db::name('member')->where('id',$val['mid'])->where('aid',aid)->find();
                $slevelname = Db::name('member_level')->where('id',$sendmember['levelid'])->value('name');
                $list[$key]['s_nickname'] = $sendmember['nickname'];
                $list[$key]['s_headimg'] = $sendmember['headimg'];
                $list[$key]['s_tel'] = $sendmember['tel'];     
                $list[$key]['s_levelname'] = $slevelname;     
                $list[$key]['createtime'] = date('Y-m-d',$val['createtime']);
            }
            
           return $this->json(['status'=>1,'data'=>$list,'count'=>$count,'mid' => $this->member['id']]);
        } 
    }
    //获取线下优惠券和个人信息
    public function getCommissionInfo(){
        if(getcustom('coupon_xianxia_buy')){
            $this->checklogin();
            $id = input('param.id');
            $detail = Db::name('xianxia_commission_log')->where('aid',aid)->where('id',$id)->find();
            $pics = $detail['pics']?explode(',',$detail['pics']):[];
            $objection_pics = $detail['objection_pics']?explode(',',$detail['objection_pics']):[];
            $member = Db::name('member')->where('aid',aid)->where('id',$detail['tomid'])->find();
            $detail = [
                'pics' =>$pics,
                'objection_pics' =>$objection_pics,
                'objection_content' =>$detail['objection_content'],
                'aliaccountname' => $member['aliaccountname']?$member['aliaccountname']:'',
                'aliaccount' => $member['aliaccount']?$member['aliaccount']:'',
                'bankname' => $member['bankname']?$member['bankname']:'',
                'bankaddress' => $member['bankaddress']?$member['bankaddress']:'',
                'bankcarduser' => $member['bankcarduser']?$member['bankcarduser']:'',
                'bankcardnum' => $member['bankcardnum']?$member['bankcardnum']:'',
                'nowmid' =>  $this->member['id'],
                'tomid' => $detail['tomid'],
                'mid' => $detail['mid'],
                'status' => $detail['status'],
                'id' => $detail['id']
            ];
            return $this->json(['status'=>1,'data'=>$detail]);
        }
    }
    //佣金线下打款
    public function giveXianxiaCommission(){
        if(getcustom('coupon_xianxia_buy')){
            $this->checklogin();
            $id = input('param.id');
            $detail = Db::name('xianxia_commission_log')->where('aid',aid)->where('id',$id)->find();
            if(!$detail){
                return $this->json(['status'=>0,'msg' => '记录不存在']);
            }
            $pics_str = input('param.pics');
            $pics = implode(',',$pics_str);
            $update =[
                'pics' => $pics,
                'status' =>1
            ];
            Db::name('xianxia_commission_log')->where('aid',aid)->where('id',$id)->update($update);
            return $this->json(['status'=>1,'msg'=>'提交成功']);
        }
    }
    //确定打款
    public function enterPayment(){
        if(getcustom('coupon_xianxia_buy')){
            $this->checklogin();
            $id = input('param.id');
            $detail = Db::name('xianxia_commission_log')->where('aid',aid)->where('id',$id)->find();
            if(!$detail){
                return $this->json(['status'=>0,'msg' => '记录不存在']);
            }
            Db::name('xianxia_commission_log')->where('aid',aid)->where('id',$id)->update(['status' => 2]);
            return $this->json(['status'=>1,'msg'=>'操作成功']);
        }
    }
    //提交异议
    public function submitObjection(){
        if(getcustom('coupon_xianxia_buy')){
            $this->checklogin();
            $id = input('param.id');
            $detail = Db::name('xianxia_commission_log')->where('aid',aid)->where('id',$id)->find();
            if(!$detail){
                return $this->json(['status'=>0,'msg' => '记录不存在']);
            }
            $pics_str = input('param.pics');
            $pics = implode(',',$pics_str);
            $content = input('param.content');
            $update = [
                'objection_pics' => $pics,
                'objection_content' => $content,
                'status' =>3
            ];
            Db::name('xianxia_commission_log')->where('aid',aid)->where('id',$id)->update($update);
            return $this->json(['status'=>1,'msg'=>'操作成功']);
        }
    }
}