<?php
// +----------------------------------------------------------------------
// | 门店中心     custom_file(mendian_upgrade)
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\Db;
class ApiMendianCenter extends ApiCommon
{
	public $mendian;
	public function initialize(){
		parent::initialize();
		//$this->checklogin();
		if(!$this->member){
			echojson(['status'=>-4,'msg'=>'请先登录'.t('门店').'绑定的用户','url'=>'/pages/index/login?frompage=/pagesA/mendiancenter/my']);
		}
		if(!$this->mendian && request()->action() != 'login'){
			$mendian = Db::name('mendian')->where('aid',aid)->where('mid',mid)->find();
			if(!$mendian){
				echojson(['status'=>-4,'msg'=>'请先申请'.t('门店'),'url'=>'/pagesA/mendianup/apply']);
			}else{
				$this->mendian = $mendian;
			}
		}
		//查看状态
		if($mendian){
			if($mendian['check_status']!=1){
				echojson(['status'=>-4,'msg'=>'账号审核未通过','url'=>'/pagesA/mendianup/apply']);
			}
		}
	}
	//我的
	public function my(){
		$sets = Db::name('mendian_sysset')->where('aid',aid)->find();
		$mendian = Db::name('mendian')->where('id',$this->mendian['id'])->find();

		$member = Db::name('member')->where('id',$mendian['mid'])->find();
		$mendian['headimg'] = $member['headimg'];
		$mendian['nickname'] = $member['nickname'];
		if(!$mendian['totalcommission']) $mendian['totalcommission'] = 0;
		
		$mendian['totalnum'] =0+Db::name('shop_order')->where('mdid',$this->mendian['id'])->where('aid',aid)->count();
		$mendian['membernum'] =0+Db::name('member')->where('pid',$this->mendian['mid'])->where('aid',aid)->count();
		$level = Db::name('mendian_level')->where('id',$this->mendian['levelid'])->find();
		$mendian['levelname'] = $level['name'];
		
		//今日订单
	    $today_start = strtotime(date('Y-m-d').' 00:00:01');
        $today_end = strtotime(date('Y-m-d').' 23:59:59');
	
		$mendian['daytotalnum'] =0+Db::name('shop_order')->where('mdid',$this->mendian['id'])->where('aid',aid)->where('createtime','between',[$today_start,$today_end])->count();

		
		$mendian['paymembernum'] =0+Db::name('shop_order')->where('mdid',$this->mendian['id'])->where('aid',aid)->where('createtime','between',[$today_start,$today_end])->group('mid')->count();

		$mendian['daypaymoney'] =Db::name('shop_order')->where('mdid',$this->mendian['id'])->where('aid',aid)->where('createtime','between',[$today_start,$today_end])->sum('totalprice');
		$mendian['daypaymoney']  = number_format($mendian['daypaymoney'],2);

		$mendian['daymembercount'] =0+Db::name('member')->where('pid',$mendian['mid'])->where('aid',aid)->where('createtime','between',[$today_start,$today_end])->count();

		$mendian['count4'] = 0 + Db::name('shop_refund_order')->where('mdid',$this->mendian['id'])->where('aid',aid)->where('createtime','between',[$today_start,$today_end])->where('refund_status','in', [1,4])->count();
	
		//预估收入
		$mendian['ygmoney'] = Db::name('mendian_commission_record')->where('mid',$this->mendian['mid'])->where('aid',aid)->where('status',0)->sum('commission');

		$where = [];
		$where['aid'] = aid;
		$where['bid'] = bid;
		$where['mdid'] = $this->mendian['id'];
		$order = [];
		$order['count0'] = 0 + Db::name('shop_order')->where($where)->where('status',0)->count();
		$order['count1'] = 0 + Db::name('shop_order')->where($where)->where('status',1)->count();
		$order['count2'] = 0 + Db::name('shop_order')->where($where)->where('status',2)->count();
		$order['count3'] = 0 + Db::name('shop_order')->where($where)->where('status',3)->count();
		$order['count4'] = 0 + Db::name('shop_refund_order')->where($where)->where('refund_status','in', [1,4])->count();


		return $this->json(['status'=>1,'mendian'=>$mendian,'sets'=>$sets,'order'=>$order]);
	}



	public function hexiaouser(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mdid','=', $this->mendian['id']];
		if(input('param.keyword')){
			$where[] = ['id|nickname|realname|tel','like','%'.input('param.keyword').'%'];
		}
		$datalist = Db::name('mendian_hexiaouser')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			$datalist[$k]['createtime'] = date('Y-m-d H:i',$v['createtime']);
			//查看核销笔数
			$datalist[$k]['hxnum'] =0+Db::name('hexiao_order')->where('hxmid',$v['mid'])->count();
			
			
		}
		if($pagenum == 1){
			$count = Db::name('mendian_hexiaouser')->where($where)->count();
		}
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['count'] = $count;
		$rdata['datalist'] = $datalist;
		$rdata['mdid'] = $this->mendian['id'];
		return $this->json($rdata);
	}

	public function gethxqrcode(){
		$mendianid = $this->mendian['id'];
		if(!$mendianid) return json(['status'=>0,'msg'=>'参数错误']);
		if(platform=='wx'){
			$qrcode = \app\commons\Wechat::getQRCode(aid,'wx','pagesA/mendiancenter/addhexiaouser',['mdid'=>$mendianid]);
		}else{
			$qrcode = createqrcode(m_url('pagesA/mendiancenter/addhexiaouser?mdid='.$mendianid));
		}
		return json(['status'=>1,'qrcode'=>$qrcode]);
	}

	//商城订单
	public function shoporder(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mdid','=',$this->mendian['id']];
        if(input('param.keyword')){
            $keywords = input('param.keyword');
            $orderids = Db::name('shop_order_goods')->where($where)->where('name','like','%'.input('param.keyword').'%')->column('orderid');
            if(!$orderids){
                $where[] = ['ordernum|title', 'like', '%'.$keywords.'%'];
            }
        }
		if($this->user['mdid']){
			$where[] = ['mdid','=',$this->user['mdid']];
		}

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

		if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];

		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('shop_order')->where($where);
        $datalist = $datalist->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
            $datalist[$key]['prolist'] = [];
			$prolist = Db::name('shop_order_goods')->where('orderid',$v['id'])->select()->toArray();
            if($prolist) $datalist[$key]['prolist'] = $prolist;
			$datalist[$key]['procount'] = Db::name('shop_order_goods')->where('orderid',$v['id'])->sum('num');
			$datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
			if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
		}

		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
		$rdata['st'] = $st;
		return $this->json($rdata);
	}


	//商城订单详情
	public function orderdetail(){
		$detail = Db::name('shop_order')->where('id',input('param.id/d'))->where('aid',aid)->where('bid',bid)->find();
		if(!$detail) $this->json(['status'=>0,'msg'=>'订单不存在']);
		$detail['createtime'] = $detail['createtime'] ? date('Y-m-d H:i:s',$detail['createtime']) : '';
		$detail['collect_time'] = $detail['collect_time'] ? date('Y-m-d H:i:s',$detail['collect_time']) : '';
		$detail['paytime'] = $detail['paytime'] ? date('Y-m-d H:i:s',$detail['paytime']) : '';
		$detail['refund_time'] = $detail['refund_time'] ? date('Y-m-d H:i:s',$detail['refund_time']) : '';
		$detail['send_time'] = $detail['send_time'] ? date('Y-m-d H:i:s',$detail['send_time']) : '';
		$detail['formdata'] = \app\model\Freight::getformdata($detail['id'],'shop_order');

		$member = Db::name('member')->where('id',$detail['mid'])->field('id,nickname,headimg')->find();
		$detail['nickname'] = $member['nickname'];
		$detail['headimg'] = $member['headimg'];

		$storeinfo = [];
		$storelist = [];
		$detail['hidefahuo'] = false;
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
			if(getcustom('freight_selecthxbids')){
				$detail['hidefahuo'] = true;
			}
		}

		$prolist = Db::name('shop_order_goods')->where('orderid',$detail['id'])->select()->toArray();

		if(getcustom('probgcolor')){
			$shopset = Db::name('shop_sysset')->where('aid',aid)->field('comment,autoclose,canrefund,order_detail_toppic')->find();
		}else{
			$shopset = Db::name('shop_sysset')->where('aid',aid)->field('comment,autoclose,canrefund')->find();
		}

		if($detail['status']==0 && $shopset['autoclose'] > 0){
			$lefttime = strtotime($detail['createtime']) + $shopset['autoclose']*60 - time();
			if($lefttime < 0) $lefttime = 0;
		}else{
			$lefttime = 0;
		}

		//弃用
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
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
		$detail['canpeisong'] = ($detail['freight_type']==2 && $peisong_set['status']==1) ? true : false;
		$detail['express_wx_status'] = $peisong_set['express_wx_status']==1 ? true : false;


		if($detail['freight_type'] == 2){
			$peisong = Db::name('peisong_order')->where('orderid',$detail['id'])->where('type','shop_order')->field('id,psid')->find();
			if($peisong){
				$detail['psid'] = $peisong['psid'];
			}
		}

		if($detail['checkmemid']){
			$detail['checkmember'] = Db::name('member')->field('id,nickname,headimg,realname,tel')->where('id',$detail['checkmemid'])->find();
		}else{
			$detail['checkmember'] = [];
		}
		$detail['message'] = \app\model\ShopOrder::checkOrderMessage($detail['id'],$detail);
		$rdata = [];
		$rdata['detail'] = $detail;
		$rdata['prolist'] = $prolist;
		$rdata['shopset'] = $shopset;
		$rdata['storeinfo'] = $storeinfo;
		$rdata['lefttime'] = $lefttime;
		$rdata['expressdata'] = array_keys(express_data(['aid'=>aid,'bid'=>bid]));
		$rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
		$rdata['storelist'] = $storelist;

		return $this->json($rdata);
	}
    //改为核销
    function hexiao(){
        $type = input('post.type');
        $orderid = input('post.orderid/d');
		$code = input('post.co');

		if($type=='shop'){
			$where = [];
			$where[] = ['aid','=',aid];
			if($code){
				$where[] = ['hexiao_code','=',$code];
			}
			if($orderid){
				$where[] = ['id','=',$orderid];
			}
			$order = Db::name($type.'_order')->where($where)->find();
			if($this->mendian['id'] != 0 && $this->mendian['id']!=$order['mdid']){
				return $this->json(['status'=>0,'msg'=>'您没有该门店核销权限']);
			}
			$prolist = Db::name($type.'_order_goods')->where(['orderid'=>$order['id']])->select()->toArray();
		    $order['prolist'] = $prolist;
			if($order['freight_type'] == 1){
				$order['storeinfo'] = Db::name('mendian')->where('id',$order['mdid'])->field('name,address,longitude,latitude')->find();
				if(!$order['storeinfo']) $order['storeinfo'] = [];
			}
		    if($order['createtime']){
                $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            }
            if($order['paytime']){
                $order['paytime'] = date('Y-m-d H:i:s',$order['paytime']);
            }
		}else if($type=='coupon'){

			$order = Db::name('coupon_record')->where('aid',aid)->where('code', $code)->find();
			if($order['bid']!=bid && !$plat_hexiao) return $this->json(['status'=>0,'msg'=>'登录的账号不是该商家的管理账号']);
            if(!$order) return $this->json(['status'=>0,'msg'=>t('优惠券').'不存在']);
            if($order['status']==1) return $this->json(['status'=>0,'msg'=>t('优惠券').'已使用']);
            if($order['starttime'] > time()) return $this->json(['status'=>0,'msg'=>t('优惠券').'尚未生效']);
            if($order['endtime'] < time()) return $this->json(['status'=>0,'msg'=>t('优惠券').'已过期']);
            if($order['type']==3 && $order['used_count']>=$order['limit_count']) return $this->json(['status'=>0,'msg'=>'已达到使用次数']);
            $order['show_addnum'] = 0;
            if($order['type']==3 && $order['limit_perday'] > 0){ //是否达到每天使用次数限制
                $dayhxnum = Db::name('hexiao_order')->where('orderid',$order['id'])->where('type','coupon')->where('createtime','between',[strtotime(date('Y-m-d 00:00:00')),strtotime(date('Y-m-d 23:59:59'))])->count();
                if($dayhxnum >= $order['limit_perday']){
                    return $this->json(['status'=>0,'msg'=>'该计次券每天最多核销'.$order['limit_perday'].'次']);
                }
            }
			$coupon = Db::name('coupon')->where('id',$order['couponid'])->find();
			$order['usetips'] = $coupon['usetips'];
            $order['createtime'] = date('Y-m-d H:i:s',$order['createtime']);
            $order['usetime'] = date('Y-m-d H:i:s',$order['usetime']);
            $order['starttime'] = date('Y-m-d H:i:s',$order['starttime']);
            $order['endtime'] = date('Y-m-d H:i:s',$order['endtime']);
		}
		if(input('post.op') == 'confirm'){
			$typeArr=['shop'];
			if(in_array($type,$typeArr)){
				$order['createtime'] = strtotime($order['createtime']);
				$order['paytime']    = strtotime($order['paytime']);
				if($order['status']==3) return $this->json(['status'=>0,'msg'=>'订单已核销']);
				$data = array();
				$data['aid'] = aid;
				$data['bid'] = bid;
				$data['uid'] = 0;
				$data['mid'] = $order['mid'];
				$data['orderid'] = $order['id'];
				$data['ordernum'] = $order['ordernum'];
				$data['title'] = $order['title'];
				$data['type'] = $type;
				$data['createtime'] = time();
				$data['remark'] = '核销员['.$this->mendian['name'].']核销';
				$data['mdid']   = $this->mendian['id'];
				Db::name('hexiao_order')->insert($data);
				$remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];

				$rs = \app\commons\Order::collect($order,$type);
				if($rs['status']==0) return $this->json($rs);
		
				db($type.'_order')->where(['aid'=>aid,'id'=>$order['id']])->update(['status'=>3,'collect_time'=>time(),'remark'=>$remark]);
				if($type == 'shop'){
					 Db::name('shop_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->update(['status'=>3,'endtime'=>time()]);
					\app\commons\Member::uplv(aid,$order['mid']);
				}
				//门店升级
				//\app\custom\Mendian::uplv(aid,$this->mendian);

				//发货信息录入 微信小程序+微信支付
				if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
					\app\commons\Order::wxShipping(aid,$order,$type);
				}
			}elseif($type=='coupon'){
				$mendian = Db::name('mendian')->where('id',$this->mendian['id'])->find();
				$data = array();
				$data['aid'] = aid;
				$data['bid'] = bid;
				$data['uid'] = $this->uid;
				$data['mid'] = $order['mid'];
				$data['orderid'] = $order['id'];
				$data['ordernum'] = date('ymdHis').aid.rand(1000,9999);
				$data['title'] = $order['couponname'];
				$data['type'] = $type;
				$data['createtime'] = time();
				$data['remark'] = '['.$mendian['name'].']核销';
				$data['mdid']   = empty($mendian['id'])?0:$mendian['id'];
                Db::name('hexiao_order')->insert($data);
				$remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];
				if($order['type']==3){//计次券
				    $hxnum = 1;
				    if(getcustom('coupon_view_range')){
                        $hxnum = input('param.hxnum',1);
                        $hxnum =   $hxnum?$hxnum:1;
                        $synum = $order['limit_count'] - $order['used_count'];
                        if($hxnum > $synum ){
                            $this->json(['status'=>0,'msg'=>'剩余核销次数不足']);
                        } 
                    }
					Db::name($type.'_record')->where(['aid'=>aid,'code'=>$code])->inc('used_count',$hxnum)->update();
					if($order['used_count']+$hxnum>=$order['limit_count']){
						Db::name($type.'_record')->where(['aid'=>aid,'code'=>$code])->update(['status'=>1,'usetime'=>time(),'remark'=>$remark]);
					}
				}else{
					Db::name($type.'_record')->where(['aid'=>aid,'code'=>$code])->update(['status'=>1,'usetime'=>time(),'remark'=>$remark]);
                    $record = Db::name($type.'_record')->where(['aid'=>aid,'code'=>$code])->find();
                    \app\commons\Coupon::useCoupon(aid,$record['id'],'hexiao');
				}
				\app\commons\Wechat::updatemembercard(aid,$order['mid']);
			}
			return $this->json(['status'=>1,'msg'=>'核销成功']);
        }
        return $this->json(['status'=>1,'order'=>$order]);
    }
	//备注
	public function setremark(){
		$post = input('post.');
		$type = $post['type'];
		$orderid = $post['orderid'];
		$content = $post['content'];
		Db::name($type.'_order')->where(['aid'=>aid,'bid'=>bid,'id'=>$orderid])->update(['remark'=>$content]);
		return $this->json(['status'=>1,'msg'=>'设置完成']);
	}




	//提现信息设置
	public function txset(){
		if(request()->isPost()){
			$postinfo = input('post.');
			$data = [];
			$data['weixin'] = $postinfo['weixin'];
			$data['aliaccount'] = $postinfo['aliaccount'];
			$data['bankname'] = $postinfo['bankname'];
			$data['bankcarduser'] = $postinfo['bankcarduser'];
			$data['bankcardnum'] = $postinfo['bankcardnum'];
			Db::name('business')->where('id',bid)->update($data);
			return $this->json(['status'=>1,'msg'=>'保存成功']);
		}
		$info = Db::name('business')->field('id,weixin,aliaccount,bankname,bankcarduser,bankcardnum')->where(['id'=>bid])->find();
		return $this->json(['status'=>1,'info'=>$info]);
	}
	//门店余额提现记录
	public function withdrawlog(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mdid','=',$this->mendian['id']];
		$datalist = Db::name('mendian_withdrawlog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function withdraw(){
		$set = Db::name('admin_set')->where(['aid'=>aid])->field('withdrawmin,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard,withdraw_autotransfer')->find();
		$mendian = Db::name('mendian')->where('id',$this->mendian['id'])->find();
		$set['withdrawfee'] = $mendian['withdrawfee'];
		if(request()->isPost()){
			$post = input('post.');
			if($post['paytype']=='支付宝' && $mendian['aliaccount']==''){
				return $this->json(['status'=>0,'msg'=>'请先设置支付宝账号']);
			}
			if($post['paytype']=='银行卡' && ($mendian['bankname']==''||$mendian['bankcarduser']==''||$mendian['bankcardnum']=='')){
				return $this->json(['status'=>0,'msg'=>'请先设置完整银行卡信息']);
			}

			$money = $post['money'];
			if($money<=0 || $money < $set['withdrawmin']){
				return $this->json(['status'=>0,'msg'=>'提现金额必须大于'.($set['withdrawmin']?$set['withdrawmin']:0)]);
			}
			if($money > $mendian['money']){
				return $this->json(['status'=>0,'msg'=>'可提现佣金不足']);
			}
			if(empty($this->mendian['mid'])){
				return $this->json(['status'=>0,'msg'=>'此账号未绑定用户，请绑定']);
			}
			$ordernum = date('ymdHis').aid.rand(1000,9999);
			$record['aid'] = aid;
			$record['bid'] = $mendian['bid'];
			$record['mid'] = $this->mendian['mid'];
			$record['mdid'] = $mendian['id'];
			$record['createtime']= time();
			$record['money'] =dd_money_format( $money*(1-$set['withdrawfee']*0.01));
			$record['txmoney'] = $money;
			$record['ordernum'] = $ordernum;
			$record['paytype'] = $post['paytype'];
			if(empty($mendian['bid']) && ($post['paytype']=='微信' || $post['paytype']=='微信钱包')){
				if($set['commission_autotransfer']==1){
					if($record['bid']>0){
		                //查询多商户的金额
		                $business = Db::name('business')->where('id',$record['bid'])->where('aid',aid)->field('money')->find();
		                if($business['money']<$record['money']){
		                    return json(['status'=>0,'msg'=>'提现失败，商户余额不足']);
		                }
		            }
					\app\commons\Mendian::addmoney(aid,$mendian['id'],-$money,'佣金提现');
					$mid = $this->user['mid'];
					if(!$mid) return json(['status'=>0,'msg'=>'未绑定微信']);
					$rs = \app\commons\Wxpay::transfers(aid,$mid,$record['money'],$record['ordernum'],'','佣金提现');
					if($rs['status']==0){
						\app\commons\Mendian::addmoney(aid,$mendian['id'],$money,'佣金提现失败返还');
						return json(['status'=>0,'msg'=>$rs['msg']]);
					}else{
						$record['weixin'] = t('会员').'ID：'.$mid;
						$record['status'] = 3;
						$record['paytime'] = time();
						$record['paynum'] = $rs['resp']['payment_no'];
						$id = db('mendian_withdrawlog')->insertGetId($record);

						//提现成功通知
						$tmplcontent = [];
						$tmplcontent['first'] = '您的提现申请已打款，请留意查收';
						$tmplcontent['remark'] = '请点击查看详情~';
						$tmplcontent['money'] = (string) $record['money'];
						$tmplcontent['timet'] = date('Y-m-d H:i',$record['createtime']);
                        $tempconNew = [];
                        $tempconNew['amount2'] = (string) $record['money'];//提现金额
                        $tempconNew['time3'] = date('Y-m-d H:i',$record['createtime']);//提现时间
						\app\commons\Wechat::sendtmpl(aid,$mid,'tmpl_tixiansuccess',$tmplcontent,m_url('admin/index/index'),$tempconNew);
						//短信通知
						$member = Db::name('member')->where('id',$mid)->find();
						if($member['tel']){
							$tel = $member['tel'];
							\app\commons\Sms::send(aid,$tel,'tmpl_tixiansuccess',['money'=>$record['money']]);
						}
						return json(['status'=>1,'msg'=>$rs['msg']]);
					}
				}
				if($mendian['weixin']==''){
					return json(['status'=>0,'msg'=>'请填写完整提现信息','url'=>'mdtxset']);
				}
				$record['weixin'] = $mendian['weixin'];
			}else{
				if($post['paytype']=='微信' || $post['paytype']=='微信钱包'){
					if($mendian['weixin']==''){
						return json(['status'=>0,'msg'=>'请填写完整提现信息','url'=>'mdtxset']);
					}
					$record['weixin'] = $mendian['weixin'];
				}
			}
			if($post['paytype']=='支付宝'){
				$record['aliaccountname'] = $mendian['aliaccountname'];
				$record['aliaccount'] = $mendian['aliaccount'];
			}
			if($post['paytype']=='银行卡'){
				$record['bankname'] = $mendian['bankname'];
				$record['bankcarduser'] = $mendian['bankcarduser'];
				$record['bankcardnum'] = $mendian['bankcardnum'];
			}
			$recordid = db('mendian_withdrawlog')->insertGetId($record);

			\app\commons\Mendian::addmoney(aid,$mendian['id'],-$money,'佣金提现');

			return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
		}
		$userinfo = db('mendian')->where(['id'=>$mendian['id']])->field('id,money,weixin,aliaccount,aliaccountname,bankname,bankcarduser,bankcardnum')->find();

		$rdata = [];
		$rdata['userinfo'] = $userinfo;
		$rdata['sysset'] = $set;
		return $this->json($rdata);
	}
	//佣金转余额
	public function commission2money(){
        try{
            Db::startTrans();
            $post = input('post.');
            $money = floatval($post['money']);
			if(!$this->mendian['mid']){
				 return $this->json(['status'=>0,'msg'=>'未绑定会员信息']);
			}
            $mendian = Db::name('mendian')->where('aid',aid)->where('id',$this->mendian['id'])->lock(true)->find();
            if($money <= 0 || $money > $mendian['money']){
                return $this->json(['status'=>0,'msg'=>'转入金额不正确']);
            }
            \app\commons\Mendian::addmoney(aid,$mendian['id'],-$money,'佣金转'.t('余额'));
            \app\commons\Member::addmoney(aid,$this->mendian['mid'],$money,t('佣金').'转'.t('余额'));
            Db::commit();
        }catch(Exception $e){
            Db::rollback();
        }
		
		return $this->json(['status'=>1,'msg'=>'转入成功']);
	}
	//门店余额明细
	public function moneylog(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mdid','=',$this->mendian['id']];
		$datalist = Db::name('mendian_moneylog')->field("id,money,`after`,createtime,remark")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	//提现信息设置
	public function mdtxset(){
		if(request()->isPost()){
			$postinfo = input('post.');
			$data = [];
			$data['weixin'] = $postinfo['weixin'];
			$data['aliaccountname'] = $postinfo['aliaccountname'];
			$data['aliaccount'] = $postinfo['aliaccount'];
			$data['bankname'] = $postinfo['bankname'];
			$data['bankcarduser'] = $postinfo['bankcarduser'];
			$data['bankcardnum'] = $postinfo['bankcardnum'];
			Db::name('mendian')->where('id',$this->mendian['id'])->update($data);
			return $this->json(['status'=>1,'msg'=>'保存成功']);
		}
		$info = Db::name('mendian')->field('id,weixin,aliaccountname,aliaccount,bankname,bankcarduser,bankcardnum')->where(['id'=>$this->mendian['id']])->find();
		return $this->json(['status'=>1,'info'=>$info]);
	}
	public function set(){
		$smsset = Db::name('admin_set_sms')->where('aid',aid)->find();
		if($smsset && $smsset['status'] == 1 && $smsset['tmpl_smscode'] && $smsset['tmpl_smscode_st']==1){
			$needsms = 1;
		}else{
			$needsms = 0;
		}
		if(request()->isPost()){
			$formdata = input('post.info/a');
			if($needsms==1){
				if(md5($formdata['tel'].'-'.$formdata['code']) != cache(input('param.session_id').'_smscode') || cache(input('param.session_id').'_smscodetimes') > 5){
					return $this->json(['status'=>0,'msg'=>'短信验证码错误']);
				}
			}
			cache(input('param.session_id').'_smscode',null);
			cache(input('param.session_id').'_smscodetimes',null);
			
			$info = [];
			$info['realname'] = $formdata['realname'];
			$info['tel'] = $formdata['tel'];
			$info['usercard'] = $formdata['usercard'];
			$info['weixin'] = $formdata['weixin'];
			$info['aliaccount'] = $formdata['aliaccount'];
			$info['bankname'] = $formdata['bankname'];
			$info['bankcarduser'] = $formdata['bankcarduser'];
			$info['bankcardnum'] = $formdata['bankcardnum'];
			$info['sex'] = $formdata['sex'];
			if($formdata['province_city']){
				$province_city = explode(' ',$formdata['province_city']);
				if($province_city){
					$info['province'] = $province_city[0];
					$info['city'] = $province_city[1];
				}
			}
			$info['birthday'] = $formdata['birthday'];
			Db::name('member')->where('id',mid)->update($info);
			return $this->json(['status'=>1,'msg'=>'修改成功']);
		}
		$mendian= Db::name('mendian')->where('id',$this->mendian['id'])->find();
		$member = Db::name('member')->where('id',$this->mendian['mid'])->find();
		$rdata = [];
		$rdata['needsms'] = $needsms;
		$rdata['mendian'] = $mendian;
		$rdata['member'] = $member;
		return $this->json($rdata);
	}

	public function getqrcode(){
	
		$mendian_upgrade_status = Db::name('admin')->where('id',aid)->value('mendian_upgrade_status');
		if($mendian_upgrade_status==1){
			$field = platform.'qrcode';
			if(platform=='h5'){
				$wxthqrcode = createqrcode(m_url('pages/index/index?pid='.mid));
			}else{
				$mendian =  Db::name('mendian')->field($field)->where('mid',mid)->where('aid',aid)->find();
				if(!$mendian[$field]){
					$wxthqrcode = \app\commons\Wechat::getQRCode(aid,platform,'pages/index/index',['pid'=>mid]);
					Db::name('mendian')->where('id',$mendian['id'])->where('aid',aid)->update([$field=>$wxthqrcode]);
				}
			}

			$rdata = [];
			$rdata['status']=1;
			if($wxthqrcode['msg'] && $rdata['status']===0){
				$rdata['status']=0;
				$rdata['msg'] = $wxthqrcode['msg'];
			}else{
				$wxthqrcode = $wxthqrcode['url'];
			}
			
			$rdata['url'] = $wxthqrcode;
			$rdata['showthqrcode'] = true;
			return $this->json($rdata);
		}

	}

	//核销记录
	public function hxrecord(){
		$pagenum = input('post.pagenum');
		$type = input('post.type/d',0);
		if(!$pagenum) $pagenum = 1;

		$pernum = 20;
		$where = [];
		$where[] = ['order.aid','=',aid];
		$where[] = ['order.bid','=',bid];
        $where[] = ['order.mdid','=',$this->mendian['id']];
		if(input('param.keyword')) $where[] = ['member.nickname','like','%'.trim(input('param.keyword')).'%'];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_moneylog.status','=',input('param.status')];
		$datalist = Db::name('hexiao_order')->alias('order')->field('member.nickname,member.headimg,order.*')->join('member member','member.id=order.mid')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if($pagenum==1){
			$count = 0 + Db::name('hexiao_order')->alias('order')->field('member.nickname,member.headimg,order.*')->join('member member','member.id=order.mid')->where($where)->count();
		}
		return $this->json(['status'=>1,'count'=>$count,'data'=>$datalist]);
	}

}