<?php


// +----------------------------------------------------------------------
// | 商城-商品订单
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class ScoreshopOrder extends Common
{
    public function initialize(){
		parent::initialize();
		//if(bid > 0) showmsg('无访问权限');
	}
	//订单列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['aid','=',aid];
            if (getcustom('scoreshop_wx_hongbao')){
                $where[] = ['type','=',0];
            }
            if (getcustom('scoreshop_to_money')){
                $where[] = ['type','in',[0,2]];
            }
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['bid','<>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
				}else{
					$where[] = ['bid','=',0];
				}
			}else{
				$where[] = ['bid','=',bid];
			}
			if($this->mdid){
				$where[] = ['mdid','=',$this->mdid];
			}
            if(input('?param.ogid')){
                if(input('param.ogid')==''){

                }else{
                    $ids = Db::name('scoreshop_order_goods')->where('id','in',input('param.ogid'))->column('orderid');
                    $where[] = ['id','in',$ids];
                }
            }
			if(input('param.orderid')) $where[] = ['id','=',input('param.orderid')];
			if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
			if(input('param.proname')) $where[] = ['proname','like','%'.input('param.proname').'%'];
			if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
			if(input('param.linkman')) $where[] = ['linkman','like','%'.input('param.linkman').'%'];
			if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			if(input('?param.status') && input('param.status')!==''){
				if(input('param.status') == 5){
					$where[] = ['refund_status','=',1];
				}elseif(input('param.status') == 6){
					$where[] = ['refund_status','=',2];
				}elseif(input('param.status') == 7){
					$where[] = ['refund_status','=',3];
				}else{
					$where[] = ['status','=',input('param.status')];
				}
			}
            if(getcustom('scoreshop_otheradmin_buy')){
                if(input('param.otheraid')) $where[] = ['otheraid','=',input('param.otheraid')];
                if(input('param.othermid')) $where[] = ['othermid','=',input('param.othermid')];
            }
			$count = 0 + Db::name('scoreshop_order')->where($where)->count();
			//echo M()->_sql();
			$list = Db::name('scoreshop_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            $score_weishu = 0;
            if(getcustom('score_weishu')){
                $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
                $score_weishu = $score_weishu?$score_weishu:0;
            }
			foreach($list as $k=>$vo){
				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$oglist = Db::name('scoreshop_order_goods')->where('aid',aid)->where('orderid',$vo['id'])->select()->toArray();
				$goodsdata=array();
				foreach($oglist as $og){
                    $og['score_price'] = dd_money_format($og['score_price'],$score_weishu);
					$goodsdata[] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
						'<img src="'.$og['pic'].'" style="max-width:60px;float:left">'.
						'<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
							'<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].'</div>'.
							($og['ggname'] ? '<div style="padding-top:0px;color:#f60"><span style="color:#666">规格：'.$og['ggname'].'</span></div>' : '<div style="padding-top:0px;color:#f60"><span style="color:#888">价值：￥'.$og['sell_price'].'</span></div>').
							'<div style="padding-top:0px;color:#f60;">'.($og['money_price']>0?'￥'+$og['money_price'].'+':'').$og['score_price'].t('积分').' × '.$og['num'].'</div>'.
						'</div>'.
					'</div>';
				}
				$list[$k]['goodsdata'] = implode('',$goodsdata);
				$list[$k]['nickname'] = $member['nickname'];
				$list[$k]['headimg'] = $member['headimg'];
				$list[$k]['m_remark'] = $member['remark'];
				$list[$k]['platform'] = getplatformname($vo['platform']);

				if($vo['bid'] > 0){
					$list[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$vo['bid'])->value('name');
				}else{
					$list[$k]['bname'] = '平台自营';
				}
                $list[$k]['totalscore'] = dd_money_format($vo['totalscore'],$score_weishu);
                if(getcustom('scoreshop_otheradmin_buy')){
                    $otherinfo = '';
                    //查询是否其他账号购买
                    if($vo['othermid']){
                    	$appname = '';
                        $admin_user = Db::name('admin_user')->where('aid',$vo['otheraid'])->where('isadmin','>',0)->where('bid',0)->field('un as name')->find();
                        if($admin_user && !empty($admin_user['name'])){
                            $appname = $admin_user['name'];
                        }
                        $otherinfo = '来自平台:ID'.$vo['otheraid'].' '.$appname.'<br>用户:ID'.$vo['othermid'].'兑换';
                    }
                    $list[$k]['otherinfo'] = $otherinfo;
                }
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		$peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
		if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
		View::assign('peisong_set',$peisong_set);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
		return View::fetch();
    }
	//导出
	public function excel(){
		set_time_limit(0);
		ini_set('memory_limit', '2000M');
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'id desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = [];
		$where[] = ['aid','=',aid];
		if(bid==0){
			if(input('param.bid')){
				$where[] = ['bid','=',input('param.bid')];
			}elseif(input('param.showtype')==2){
				$where[] = ['bid','<>',0];
            }elseif(input('param.showtype')=='all'){
                $where[] = ['bid','>=',0];
			}else{
				$where[] = ['bid','=',0];
			}
		}else{
			$where[] = ['bid','=',bid];
		}
		if($this->mdid){
			$where[] = ['mdid','=',$this->mdid];
		}
		if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
		if(input('param.proname')) $where[] = ['proname','like','%'.input('param.proname').'%'];
		if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
		if(input('param.linkman')) $where[] = ['linkman','like','%'.input('param.linkman').'%'];
		if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['createtime','>=',strtotime($ctime[0])];
			$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('?param.status') && input('param.status')!==''){
			if(input('param.status') == 5){
				$where[] = ['refund_status','=',1];
			}elseif(input('param.status') == 6){
				$where[] = ['refund_status','=',2];
			}elseif(input('param.status') == 7){
				$where[] = ['refund_status','=',3];
			}else{
				$where[] = ['status','=',input('param.status')];
			}
		}
		$list = Db::name('scoreshop_order')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('scoreshop_order')->where($where)->order($order)->count();
		$title = array('订单号','下单人','商品名称','规格数量','支付积分','支付金额','支付方式','姓名','电话','收货地址','配送方式','配送/提货时间','快递信息','客户留言','后台备注','下单时间','状态','备注','其他');
		$data = [];
		foreach($list as $k=>$vo){
			$member = Db::name('member')->where('id',$vo['mid'])->find();
			$oglist = Db::name('scoreshop_order_goods')->where('orderid',$vo['id'])->select()->toArray();
			$xm=array();
			foreach($oglist as $og){
				$xm[] = $og['name']." × ".$og['num']."";
			}
			$status='';
			if($vo['status']==0){
				$status = '未支付';
			}elseif($vo['status']==2){
				$status = '已发货';
			}elseif($vo['status']==1){
				$status = '已支付';
			}elseif($vo['status']==3){
				$status = '已收货';
			}elseif($vo['status']==4){
				$status = '已关闭';
			}
			$otherinfo = '';
			if(getcustom('scoreshop_otheradmin_buy')){
                //查询是否其他账号购买
                if($vo['othermid']){
                	$appname = '';
                    $admin_user = Db::name('admin_user')->where('aid',$vo['otheraid'])->where('isadmin','>',0)->where('bid',0)->field('un as name')->find();
                    if($admin_user && !empty($admin_user['name'])){
                        $appname = $admin_user['name'];
                    }
                    $otherinfo = '来自平台:ID'.$vo['otheraid'].' '.$appname." \n\r 用户:ID".$vo['othermid'].'兑换';
                }
                $list[$k]['otherinfo'] = $otherinfo;
            }
			$data[$k] = [
				' '.$vo['ordernum'],
				$member['nickname'],
				$vo['title'],
				implode("\r\n",$xm),
                $vo['totalscore'],
				$vo['totalprice'],
				$vo['paytype'],
				$vo['linkman'],
				$vo['tel'],
				$vo['area'].' '.$vo['address'],
				$vo['freight_text'],
				$vo['freight_time'],
				($vo['express_com'] ? $vo['express_com'].'('.$vo['express_no'].')':''),
				$vo['message'],
				$vo['remark'],
				date('Y-m-d H:i:s',$vo['createtime']),
				$status,
                '',
                $otherinfo
			];
            //配送自定义表单
            $vo['formdata'] = \app\models\Freight::getformdata($vo['id'],'scoreshop_order');
            if($vo['formdata']) {
                foreach ($vo['formdata'] as $formdata) {
                    if($formdata[2] != 'upload') {
                        if($formdata[0] == '备注') {
                            $data[$k][17] = $formdata[1];
                        } else {
                            $data[$k][18] .= $formdata[0].':'.$formdata[1]."\r\n";
                        }
                    }
                }
            }
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//订单详情
	public function getdetail(){
		$orderid = input('post.orderid');
		$order = Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->find();
		if(!$order) return json(['status'=>1,'msg'=>'订单不存在']);
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if($order['coupon_rid']){
			$couponrecord = Db::name('coupon_record')->where('id',$order['coupon_rid'])->find();
		}else{
			$couponrecord = false;
		}
        $score_weishu = 0;
        if(getcustom('score_weishu')){
            $score_weishu = Db::name('admin_set')->where('aid',aid)->value('score_weishu');
            $score_weishu = $score_weishu?$score_weishu:0;
        }
        $order['totalscore'] = dd_money_format($order['totalscore'],$score_weishu);
		$oglist = Db::name('scoreshop_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
		$member = Db::name('member')->field('id,nickname,headimg,realname,tel,wxopenid,unionid')->where('id',$order['mid'])->find();
		if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];
		$comdata = array();
		$comdata['parent1'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
		$comdata['parent2'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
		$comdata['parent3'] = ['mid'=>'','nickname'=>'','headimg'=>'','money'=>0,'score'=>0];
		foreach($oglist as $v){
			if($v['parent1']){
				$parent1 = Db::name('member')->where('id',$v['parent1'])->find();
				$comdata['parent1']['mid'] = $v['parent1'];
				$comdata['parent1']['nickname'] = $parent1['nickname'];
				$comdata['parent1']['headimg'] = $parent1['headimg'];
				$comdata['parent1']['money'] += $v['parent1commission'];
				$comdata['parent1']['score'] += $v['parent1score'];
			}
			if($v['parent2']){
				$parent2 = Db::name('member')->where('id',$v['parent2'])->find();
				$comdata['parent2']['mid'] = $v['parent2'];
				$comdata['parent2']['nickname'] = $parent2['nickname'];
				$comdata['parent2']['headimg'] = $parent2['headimg'];
				$comdata['parent2']['money'] += $v['parent2commission'];
				$comdata['parent2']['score'] += $v['parent2score'];
			}
			if($v['parent3']){
				$parent3 = Db::name('member')->where('id',$v['parent3'])->find();
				$comdata['parent3']['mid'] = $v['parent3'];
				$comdata['parent3']['nickname'] = $parent3['nickname'];
				$comdata['parent3']['headimg'] = $parent3['headimg'];
				$comdata['parent3']['money'] += $v['parent3commission'];
				$comdata['parent3']['score'] += $v['parent3score'];
			}
		}
		$order['formdata'] = \app\models\Freight::getformdata($order['id'],'scoreshop_order');

		$miandanst = Db::name('admin_set')->where('aid',aid)->value('miandanst');
		if($order['bid']==0 && $miandanst==1 && in_array('wx',$this->platform) && ($member['wxopenid'] || $member['unionid'])){ //可以使用小程序物流助手发货
			$canmiandan = 1;
		}else{
			$canmiandan = 0;
		}
		return json(['order'=>$order,'couponrecord'=>$couponrecord,'oglist'=>$oglist,'member'=>$member,'comdata'=>$comdata,'canmiandan'=>$canmiandan]);
	}
	
	//设置备注
	public function setremark(){
		$orderid = input('post.orderid/d');
		$content = input('post.content');
		if(bid == 0){
			Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->update(['remark'=>$content]);
		}else{
			Db::name('scoreshop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['remark'=>$content]);
		}
		\app\commons\System::plog('积分商城订单设置备注'.$orderid);
		return json(['status'=>1,'msg'=>'设置完成']);
	}
	//改价格
	public function changeprice(){
		$orderid = input('post.orderid/d');
		$newprice = input('post.newprice/f');
		$newordernum = date('ymdHis').rand(100000,999999);
		if(bid == 0){
			Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>$newordernum]);
			Db::name('scoreshop_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['ordernum'=>$newordernum]);
		}else{
			Db::name('scoreshop_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['totalprice'=>$newprice,'ordernum'=>$newordernum]);
			Db::name('scoreshop_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$orderid)->update(['ordernum'=>$newordernum]);
		}
		\app\commons\System::plog('积分商城订单改价格'.$orderid);
		return json(['status'=>1,'msg'=>'修改完成']);
	}
	//关闭订单
	public function closeOrder(){
		$orderid = input('post.orderid/d');
		$order = Db::name('scoreshop_order')->where('id',$orderid)->where('aid',aid)->find();
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if(!$order || $order['status']!=0){
			return json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
		}
		//加库存
		$oglist = Db::name('scoreshop_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
		foreach($oglist as $og){
			Db::name('scoreshop_product')->where('aid',aid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
            if($og['ggid'])Db::name('scoreshop_guige')->where('aid',aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("sales-".$og['num'])]);
		}
		
		//优惠券抵扣的返还
		if($order['coupon_rid'] > 0){
			Db::name('coupon_record')->where('aid',aid)->where(['mid'=>$order['mid'],'id'=>$order['coupon_rid']])->update(['status'=>0,'usetime'=>'']);
		}
		$rs = Db::name('scoreshop_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>4]);
		Db::name('scoreshop_order_goods')->where('orderid',$orderid)->where('aid',aid)->update(['status'=>4]);
		\app\commons\System::plog('积分商城订单关闭订单'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//改为已支付
	public function ispay(){
		if(bid > 0) showmsg('无权限操作');
		$orderid = input('post.orderid/d');
		$order = Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->find();
		if(!$order){
			return json(['status'=>0,'msg'=>'订单不存在']);
		}
		Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->update(['status'=>1,'paytime'=>time(),'paytype'=>'后台支付']);
		Db::name('scoreshop_order_goods')->where('orderid',$orderid)->where('aid',aid)->update(['status'=>1]);
		//奖励积分
		if($order['givescore'] > 0){
			\app\commons\Member::addscore(aid,$order['mid'],$order['givescore'],'购买产品奖励'.t('积分'));
		}
		\app\commons\System::plog('积分商城订单改为已支付'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//发货
	public function sendExpress(){
		$orderid = input('post.orderid/d');
		$order = Db::name('scoreshop_order')->where('id',$orderid)->where('aid',aid)->find();
		if(!$order){
			return json(['status'=>0,'msg'=>'订单不存在']);
		}
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if($order['freight_type']==10){
			$pic = input('post.pic');
			$fhname = input('post.fhname');
			$fhaddress = input('post.fhaddress');
			$shname = input('post.shname');
			$shaddress = input('post.shaddress');
			$remark = input('post.remark');
			$data = [];
			$data['aid'] = aid;
			$data['pic'] = $pic;
			$data['fhname'] = $fhname;
			$data['fhaddress'] = $fhaddress;
			$data['shname'] = $shname;
			$data['shaddress'] = $shaddress;
			$data['remark'] = $remark;
			$data['createtime'] = time();
			$id = Db::name('freight_type10_record')->insertGetId($data);
			$express_com = '货运托运';
			$express_no = $id;
		}else{
			$express_com = input('post.express_com');
			$express_no = input('post.express_no');
			if(empty($express_no)){
				return json(['status'=>0,'msg'=>'请填写单号']);
			}
		}
		if($order['status']!=1){ //修改物流信息
			Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no]);
			return json(['status'=>1,'msg'=>'操作成功']);
		}

		Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no,'send_time'=>time(),'status'=>2]);
		Db::name('scoreshop_order_goods')->where('orderid',$orderid)->where('aid',aid)->update(['status'=>2]);

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'scoreshop',['express_com'=>$express_com,'express_no'=>$express_no]);
        }
		
		//订单发货通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单已发货';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = $express_com;
		$tmplcontent['keyword3'] = $express_no;
		$tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
        $tmplcontentNew = [];
        $tmplcontentNew['thing4'] = $order['title'];//商品名称
        $tmplcontentNew['thing13'] = $express_com;//快递公司
        $tmplcontentNew['character_string14'] = $express_no;//快递单号
        $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['thing7'] = $express_com;
		$tmplcontent['character_string4'] = $express_no;
		$tmplcontent['thing11'] = $order['address'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['thing29'] = $order['title'];
		$tmplcontentnew['thing1'] = $express_com;
		$tmplcontentnew['character_string2'] = $express_no;
		$tmplcontentnew['thing9'] = $order['address'];
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\commons\Sms::send(aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>$express_com,'express_no'=>$express_no]);
		
		\app\commons\System::plog('积分商城订单发货'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//配送
	public function peisong(){
		$orderid = input('post.orderid/d');
		$psid = input('post.psid/d');
		
		$order = Db::name('scoreshop_order')->where('id',$orderid)->where('aid',aid)->find();
		if(!$order){
			return json(['status'=>0,'msg'=>'订单不存在']);
		}
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');

		$psorderdata = [];
		$psorderdata['aid'] = aid;
		$psorderdata['psid'] = $psid;
		$psorderdata['orderid'] = $order['id'];
		$psorderdata['ordernum'] = $order['ordernum'];
		$psorderdata['createtime'] = time();
		$psorderdata['status'] = 0;
		$psorderdata['type'] = 'scoreshop_order';
		$psorderdata['ticheng'] = Db::name('peisong_set')->where('aid',aid)->value('ticheng');
		$psorderid = Db::name('peisong_order')->insertGetId($psorderdata);
		Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->update(['express_com'=>'商家配送','express_no'=>$psorderid,'send_time'=>time(),'status'=>2]);
		Db::name('scoreshop_order_goods')->where('orderid',$orderid)->where('aid',aid)->update(['status'=>2]);

        //发货信息录入 微信小程序+微信支付
        if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
            \app\commons\Order::wxShipping($order['aid'],$order,'scoreshop');
        }

		//新配送订单通知
		$psmid = Db::name('peisong_user')->where('id',$psid)->value('mid');
		$tmplcontent = [];
		$tmplcontent['first'] = '您有新的订单待配送，请及时配送';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['keyword1'] = $order['linkman'];
		$tmplcontent['keyword2'] = $order['tel'];
		$tmplcontent['keyword3'] = $order['area'] .' '. $order['address'];
		$tmplcontent['keyword4'] = $order['title'];
		$tmplcontent['keyword5'] = date('Y-m-d H:i',$order['paytime']);
        if($order['bid']>0){
            $business = Db::name('business')->field('name,address,tel,logo,longitude,latitude')->where('id',$order['bid'])->find();
        }else{
            $business = Db::name('admin_set')->field('name,address,tel,logo,longitude,latitude')->where('aid',aid)->find();
        }
        $tempconNew = [];
        $tempconNew['character_string1'] = $order['ordernum'];//订单编号
        $tempconNew['thing16'] = $business['name'];//门店名称
        $tempconNew['thing8'] = $order['title'];//商品名称
        $tempconNew['thing5'] = $order['address']?$order['address']:'无';//客户地址
        $tempconNew['time2'] = date('Y-m-d H:i',$order['paytime']);//订单时间
		\app\commons\Wechat::sendtmpl(aid,$psmid,'tmpl_peisongorder',$tmplcontent,m_url('pages/peisong/orderlist'),$tempconNew);
		
		//订单发货通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单已分派配送人员进行配送';
		$tmplcontent['remark'] = '请点击查看详情~';
		$tmplcontent['keyword1'] = $order['title'];
		$tmplcontent['keyword2'] = '商家配送';
		$tmplcontent['keyword3'] = '';
		$tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
        $tmplcontentNew = [];
        $tmplcontentNew['thing4'] = $order['title'];//商品名称
        $tmplcontentNew['thing13'] = '商家配送';//快递公司
        $tmplcontentNew['character_string14'] = '';//快递单号
        $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing2'] = $order['title'];
		$tmplcontent['thing7'] = '商家配送';
		$tmplcontent['character_string4'] = '';
		$tmplcontent['thing11'] = $order['address'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['thing29'] = $order['title'];
		$tmplcontentnew['thing1'] = '商家配送';
		$tmplcontentnew['character_string2'] = '';
		$tmplcontentnew['thing9'] = $order['address'];
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\commons\Sms::send(aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>'商家配送','express_no'=>'']);
		\app\commons\System::plog('积分商城订单配送'.$orderid);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//批量发货
	public function plfh(){
		$express_com = input('post.plfh_express');
		$file = input('post.plfh_file');
		$exceldata = $this->import_excel($file);
		//dump($exceldata);
		// $countnum = count($exceldata);
		$countnum = 0;
		$successnum = 0;
		$errornum = 0;
		foreach($exceldata as $v){
			$ordernum = trim($v[0]); 
			//$express_com = $v[1];
			//$express_no = $v[2];
			$express_no = $v[1];
			if(!$ordernum || !$express_no){
				continue;
			}
			$countnum++;
			
			$order = Db::name('scoreshop_order')->where('aid',aid)->where('ordernum',$ordernum)->find();
			if(!$order || $order['status'] != 1 && $order['status'] != 2){
				$errornum++;
				continue;
			}
			if(bid != 0 && $order['bid']!=bid){
				$errornum++;
				continue;
			}
			$orderid = $order['id'];
			Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->update(['express_com'=>$express_com,'express_no'=>$express_no,'send_time'=>time(),'status'=>2]);
			Db::name('scoreshop_order_goods')->where('orderid',$orderid)->where('aid',aid)->update(['status'=>2]);

            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping($order['aid'],$order,'scoreshop',['express_com'=>$express_com,'express_no'=>$express_no]);
            }
			
			//订单发货通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的订单已发货';
			$tmplcontent['remark'] = '请点击查看详情~';
			$tmplcontent['keyword1'] = $order['title'];
			$tmplcontent['keyword2'] = $express_com;
			$tmplcontent['keyword3'] = $express_no;
			$tmplcontent['keyword4'] = $order['linkman'].' '.$order['tel'];
            $tmplcontentNew = [];
            $tmplcontentNew['thing4'] = $order['title'];//商品名称
            $tmplcontentNew['thing13'] = $express_com;//快递公司
            $tmplcontentNew['character_string14'] = $express_no;//快递单号
            $tmplcontentNew['thing16'] = $order['linkman'].' '.$order['tel'];//收货人
			\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['thing2'] = $order['title'];
			$tmplcontent['thing7'] = $express_com;
			$tmplcontent['character_string4'] = $express_no;
			$tmplcontent['thing11'] = $order['address'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['thing29'] = $order['title'];
			$tmplcontentnew['thing1'] = $express_com;
			$tmplcontentnew['character_string2'] = $express_no;
			$tmplcontentnew['thing9'] = $order['address'];
			\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_orderfahuo',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $order['tel'];
			}
			$rs = \app\commons\Sms::send(aid,$tel,'tmpl_orderfahuo',['ordernum'=>$order['ordernum'],'express_com'=>$express_com,'express_no'=>$express_no]);
			$successnum++;
		}
		\app\commons\System::plog('积分商城订单批量发货');
		return json(['status'=>1,'msg'=>'共导入 '.$countnum.' 条数据，成功发货 '.$successnum.' 条，失败 '.$errornum.' 条']);
	}
	//查物流
	public function getExpress(){
		$orderid = input('post.orderid/d');
		$order = Db::name('scoreshop_order')->where('id',$orderid)->where('aid',aid)->find();
		if($order['freight_type'] == '10'){
			$data = Db::name('freight_type10_record')->where('id',$order['express_no'])->find();
			return json(['status'=>1,'data'=>$data]);
		}
		if($order['express_com'] == '顺丰速运'){
			$totel = $order['tel'];
			$order['express_no'] = $order['express_no'].":".substr($totel,-4);
		}
		if(empty($order['express_no'])){
			$list[] = [
                "time"=>"",
                "status"=>"物流单号不能为空",
                "context"=>"物流单号不能为空"
            ];
			return json(['status'=>1,'data'=>$list]);
		}
            
		$list = \app\commons\Common::getwuliu($order['express_no'],$order['express_com'], '', aid);
		return json(['status'=>1,'data'=>$list]);
	}
	//退款审核
	public function refundCheck(){
		$orderid = input('post.orderid/d');
		$st = input('post.st/d');
		$remark = input('post.remark');
		$order = Db::name('scoreshop_order')->where('id',$orderid)->where('aid',aid)->find();
		if(!$order){
			return json(['status'=>0,'msg'=>'订单不存在']);
		}
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if($st==2){
			Db::name('scoreshop_order')->where('id',$orderid)->where('aid',aid)->update(['refund_status'=>3,'refund_checkremark'=>$remark]);
			
			//退款申请驳回通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的退款申请被商家驳回，可与商家协商沟通。';
			$tmplcontent['remark'] = $remark.'，请点击查看详情~';
			$tmplcontent['orderProductPrice'] = $order['refund_money'];
			$tmplcontent['orderProductName'] = $order['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
			\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount3'] = $order['refund_money'];
			$tmplcontent['thing2'] = $order['title'];
			$tmplcontent['character_string1'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing8'] = $order['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
			\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuierror',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);
			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $order['tel'];
			}
			$rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuierror',['ordernum'=>$order['ordernum'],'reason'=>$remark]);
			\app\commons\System::plog('积分商城订单退款驳回'.$orderid);
			return json(['status'=>1,'msg'=>'退款已驳回']);
		}elseif($st == 1){
			if($order['status']!=1 && $order['status']!=2){
				return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
			}

            if($order['refund_money'] > 0) {
                $rs = \app\commons\Order::refund($order,$order['refund_money'],$order['refund_reason']);
                if($rs['status']==0){
                    return json(['status'=>0,'msg'=>$rs['msg'] ? $rs['msg'] : 'error']);
                }
            }
			
			Db::name('scoreshop_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>4,'refund_status'=>2,'refund_checkremark'=>$remark]);
			Db::name('scoreshop_order_goods')->where('orderid',$orderid)->where('aid',aid)->update(['status'=>4]);
            //退款减去商户销量
            $refund_num = Db::name('scoreshop_order_goods')->where('orderid',$orderid)->sum('num');
            \app\models\Payorder::addSales($orderid,'scoreshop',$order['aid'],$order['bid'],-$refund_num);
            //积分返还
            if($order['totalscore'] > 0){
                $aid2 = aid;$mid2 = $order['mid'];
                $remark = '订单退款返还';
                $addscore_params = [];//其他参数
                if(getcustom('scoreshop_otheradmin_buy')){
                    //如果扣除的是其他平台用积分
                    if($order['othermid']){
                        $aid2 = $order['otheraid'];$mid2 = $order['othermid'];
                        $appinfo = Db::name('admin_setapp_wx')->where('aid',aid)->field('id,nickname')->find();
                        if($appinfo && !empty($appinfo['nickname'])){
                            $remark = $appinfo['nickname'].'订单'.$order['ordernum'].'退款返还';
                        }else{
                            $set = Db::name('admin_set')->where('aid',aid)->field('name')->find();
                            if($set && !empty($set['name'])){
                                $remark = $set['name'].'订单'.$order['ordernum'].'退款返还';
                            }
                        }
                        $addscore_params['optaid'] = aid;
                    }
                }
                \app\commons\Member::addscore($aid2,$mid2,$order['totalscore'],$remark,'',0,0,1,$addscore_params);
            }
            //扣除消费赠送积分
            \app\commons\Member::decscorein(aid,'scoreshop',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
			//退款退还佣金
			if(getcustom('commission_orderrefund_deduct')){
				\app\commons\Fenxiao::refundFenxiao(aid,$order['id'],'scoreshop');
				\app\commons\Order::refundFenhongDeduct($order,'scoreshop');
			}

			
			//退款成功通知
			$tmplcontent = [];
            $tmplcontent['first'] = '您的订单已经完成退款，';
            if ($order['refund_money']) {
                $tmplcontent['first'] .= '¥'.$order['refund_money'].' ';
            }
            if ($order['totalscore']) {
                $tmplcontent['first'] .= $order['totalscore']. t('积分') . '，已经退回您的付款账户，请留意查收。';
            }
			$tmplcontent['remark'] = $remark.'，请点击查看详情~';
			$tmplcontent['orderProductPrice'] = $order['refund_money'];
			$tmplcontent['orderProductName'] = $order['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
			\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount6'] = $order['refund_money'];
			$tmplcontent['thing3'] = $order['title'];
			$tmplcontent['character_string2'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing6'] = $order['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
			\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $order['tel'];
			}
			$rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$order['refund_money']]);
			
			\app\commons\System::plog('积分商城订单退款审核通过并退款'.$orderid);
			return json(['status'=>1,'msg'=>'已退款成功']);
		}
	}
	//退款
	public function refund(){
		$orderid = input('post.orderid/d');
		$reason = input('post.reason');
		$order = Db::name('scoreshop_order')->where('id',$orderid)->where('aid',aid)->find();
		if(!$order){
			return json(['status'=>0,'msg'=>'订单不存在']);
		}
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');

		$refund_money = $order['totalprice'];
		if($order['status']!=1 && $order['status']!=2){
			return json(['status'=>0,'msg'=>'该订单状态不允许退款']);
		}

        if($refund_money > 0) {
            $rs = \app\commons\Order::refund($order,$refund_money,$reason);
            if($rs['status']==0){
                return json(['status'=>0,'msg'=>$rs['msg']]);
            }
        }

		Db::name('scoreshop_order')->where('id',$orderid)->where('aid',aid)->update(['status'=>4,'refund_status'=>2,'refund_money'=>$refund_money,'refund_reason'=>$reason]);
		Db::name('scoreshop_order_goods')->where('orderid',$orderid)->where('aid',aid)->update(['status'=>4]);

        //退款减去商户销量
        $refund_num = Db::name('scoreshop_order_goods')->where('orderid',$orderid)->sum('num');
        \app\models\Payorder::addSales($orderid,'scoreshop',$order['aid'],$order['bid'],-$refund_num);
        //加库存减销量
        $order_goods = Db::name('scoreshop_order_goods')->where('orderid',$orderid)->where('aid',aid)->select()->toArray();
        foreach ($order_goods as $item) {
            Db::name('scoreshop_product')->where('aid',aid)->where('id',$item['proid'])->update(['stock'=>Db::raw("stock+".$item['num']),'sales'=>Db::raw("sales-".$item['num'])]);
        }

        //积分返还
        if($order['totalscore'] > 0){
        	$aid2 = aid;$mid2 = $order['mid'];
        	$remark = '订单退款返还';
        	$addscore_params = [];//其他参数
            if(getcustom('scoreshop_otheradmin_buy')){
                //如果扣除的是其他平台用积分
                if($order['othermid']){
                    $aid2 = $order['otheraid'];$mid2 = $order['othermid'];
                    $appinfo = Db::name('admin_setapp_wx')->where('aid',aid)->field('id,nickname')->find();
                    if($appinfo && !empty($appinfo['nickname'])){
                        $remark = $appinfo['nickname'].'订单'.$order['ordernum'].'退款返还';
                    }else{
                        $set = Db::name('admin_set')->where('aid',aid)->field('name')->find();
                        if($set && !empty($set['name'])){
                            $remark = $set['name'].'订单'.$order['ordernum'].'退款返还';
                        }
                    }
                    $addscore_params['optaid'] = aid;
                }
            }
            \app\commons\Member::addscore($aid2,$mid2,$order['totalscore'],$remark,'',0,0,1,$addscore_params);
        }
        //扣除消费赠送积分
        \app\commons\Member::decscorein(aid,'scoreshop',$order['id'],$order['ordernum'],'订单退款扣除消费赠送');
		//退款退还佣金
		if(getcustom('commission_orderrefund_deduct')){
			\app\commons\Fenxiao::refundFenxiao(aid,$order['id'],'scoreshop');
			\app\commons\Order::refundFenhongDeduct($order,'scoreshop');
		}

		//退款成功通知
		$tmplcontent = [];
		$tmplcontent['first'] = '您的订单已经完成退款，¥'.$refund_money.'已经退回您的付款账户，请留意查收。';
		$tmplcontent['remark'] = $reason.'，请点击查看详情~';
		$tmplcontent['orderProductPrice'] = $refund_money;
		$tmplcontent['orderProductName'] = $order['title'];
		$tmplcontent['orderName'] = $order['ordernum'];
        $tmplcontentNew = [];
        $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
        $tmplcontentNew['thing2'] = $order['title'];//商品名称
        $tmplcontentNew['amount3'] = $refund_money;//退款金额
		\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['amount6'] = $refund_money;
		$tmplcontent['thing3'] = $order['title'];
		$tmplcontent['character_string2'] = $order['ordernum'];
		
		$tmplcontentnew = [];
		$tmplcontentnew['amount3'] = $refund_money;
		$tmplcontentnew['thing6'] = $order['title'];
		$tmplcontentnew['character_string4'] = $order['ordernum'];
		\app\commons\Wechat::sendwxtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

		//短信通知
		$member = Db::name('member')->where('id',$order['mid'])->find();
		if($member['tel']){
			$tel = $member['tel'];
		}else{
			$tel = $order['tel'];
		}
		$rs = \app\commons\Sms::send(aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$refund_money]);
		
		\app\commons\System::plog('积分商城订单退款'.$orderid);
		return json(['status'=>1,'msg'=>'已退款成功']);
	}
    //核销并确认收货
    function orderHexiao(){
        $post = input('post.');
        $orderid = intval($post['orderid']);
        $order = Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->find();
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
        if(!$order || !in_array($order['status'], [1,2]) || $order['freight_type'] != 1){
            return json(['status'=>0,'msg'=>'订单状态不符合核销收货要求']);
        }
        try {
            Db::startTrans();
            $data = array();
            $data['aid'] = aid;
            $data['bid'] = $order['bid'];
            $data['uid'] = $this->uid;
            $data['mid'] = $order['mid'];
            $data['orderid'] = $order['id'];
            $data['ordernum'] = $order['ordernum'];
            $data['title'] = $order['title'];
            $data['type'] = 'scoreshop';
            $data['createtime'] = time();
            $data['remark'] = '核销员['.$this->user['un'].']核销';
            $data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
            Db::name('hexiao_order')->insert($data);

            $rs = \app\commons\Order::collect($order, 'scoreshop', $this->user['mid']);
            if($rs['status']==0) return $rs;
            Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
            Db::name('scoreshop_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);
            \app\commons\Member::uplv(aid,$order['mid']);
            //发货信息录入 微信小程序+微信支付
            if($order['platform'] == 'wx' && $order['paytypeid'] == 2){
                \app\commons\Order::wxShipping($order['aid'],$order,'scoreshop');
            }
            Db::commit();
            \app\commons\System::plog('积分商城订单核销确认收货'.$orderid);
            return json(['status'=>1,'msg'=>'核销成功']);
        } catch (\Exception $e) {
            Log::write([
                'file' => __FILE__ . ' L' . __LINE__,
                'function' => __FUNCTION__,
                'error' => $e->getMessage(),
            ]);
            Db::rollback();
            return json(['status'=>0,'msg'=>'系统繁忙','error'=>$e->getMessage()]);
        }
    }
	function orderCollect(){ //确认收货
		$post = input('post.');
		$orderid = intval($post['orderid']);
		$order = Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->find();
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		if(!$order || ($order['status']!=2)){
			$return = ['status'=>0,'msg'=>'订单状态不符合收货要求'];
		}else{
            $rs = \app\commons\Order::collect($order, 'scoreshop', $this->user['mid']);
            if($rs['status']==0) return $rs;
			Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
			Db::name('scoreshop_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['status'=>3,'endtime'=>time()]);
			\app\commons\Member::uplv(aid,$order['mid']);
			\app\commons\System::plog('积分商城订单确认收货'.$orderid);
			$return = ['status'=>1,'msg'=>'确认收货成功'];
		}
		return json($return);
	}
	//打印小票
	public function wifiprint(){
		$id = input('post.id/d');
		$rs = \app\commons\Wifiprint::print(aid,'shop',$id,0);
		return $rs;
	}
	//删除
	public function del(){
		$id = input('post.id/d');
		if(bid == 0){
			Db::name('scoreshop_order')->where('aid',aid)->where('id',$id)->delete();
			Db::name('scoreshop_order_goods')->where('aid',aid)->where('orderid',$id)->delete();
		}else{
			Db::name('scoreshop_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->delete();
			Db::name('scoreshop_order_goods')->where('aid',aid)->where('bid',bid)->where('orderid',$id)->delete();
		}
		\app\commons\System::plog('积分商城订单删除'.$id);
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//编辑
	public function edit(){
		$orderid = input('param.id/d');
		$info = Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->find();
		if(!$info){
			return json(['status'=>0,'msg'=>'订单不存在']);
		}
		if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');

		$order_goods = Db::name('scoreshop_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
		foreach($order_goods as $k=>$v){
			$order_goods[$k]['lvprice'] = Db::name('scoreshop_product')->where('id',$v['proid'])->value('lvprice'); //是否开启会员价
		}
		$member = Db::name('member')->where('id',$info['mid'])->find();
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
		if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$discount = $userlevel['discount']*0.1; //会员折扣
		}else{
			$discount = 1;
		}

		if(request()->isAjax()){
			$postinfo = input('post.info/a');
			Db::name('scoreshop_order')->where('id',$orderid)->update($postinfo);
			$order = Db::name('scoreshop_order')->where('id',$orderid)->find();
			$goods_id = input('post.goods_id/a');
			$goods_ggname = input('post.goods_ggname/a');
			$goods_sell_price = input('post.goods_sell_price/a');
			$goods_num = input('post.goods_num/a');
			foreach($goods_id as $k=>$ogid){
				$oginfo = Db::name('scoreshop_order_goods')->where('id',$ogid)->find();
				$ogdata = [];
				$ogdata['ggname'] = $goods_ggname[$k];
				$ogdata['sell_price'] = $goods_sell_price[$k];
				$ogdata['num'] = $goods_num[$k];
				$ogdata['totalprice'] = $ogdata['sell_price'] * $ogdata['num'];
				
				$product = Db::name('scoreshop_product')->where('id',$oginfo['proid'])->find();
				$ogtotalprice = $ogdata['totalprice'];
				$commissiontype = Db::name('admin_set')->where('aid',aid)->value('commissiontype');
				if($commissiontype == 1){
					$allgoodsprice = $order['goodsprice'] - $order['disprice'];
					$couponmoney = $order['couponmoney'];
					$scoredk = $order['scoredk'];
					$disprice = 0;
					$ogcouponmoney = 0;
					$ogscoredk = 0;
					if($product['lvprice']==0 && $userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){ //未开启会员价
						$disprice = $ogtotalprice * (1 - $userlevel['discount'] * 0.1);
						$ogtotalprice = $ogtotalprice - $disprice;
					}
					if($couponmoney){
						$ogcouponmoney = $ogtotalprice / $allgoodsprice * $couponmoney;
					}
					if($scoredk){
						$ogscoredk = $ogtotalprice / $allgoodsprice * $scoredk;
					}
					$ogtotalprice = round($ogtotalprice - $ogcouponmoney - $ogscoredk,2);
					if($ogtotalprice < 0) $ogtotalprice = 0;
				}
				$agleveldata = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
				
				if($product['commissionset']!=-1){
					if($member['pid']){
						$parent1 = Db::name('member')->where('aid',aid)->where('id',$member['pid'])->find();
						
						if($parent1){
							$agleveldata1 = Db::name('member_level')->where('aid',aid)->where('id',$parent1['levelid'])->find();
							if($agleveldata1['can_agent']!=0){
								$ogdata['parent1'] = $parent1['id'];
							}
						}
						//return json(['status'=>0,'msg'=>'11','data'=>$parent1,'data2'=>$agleveldata1]);
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
					if($product['commissionset']==1){//按比例
						$commissiondata = json_decode($product['commissiondata1'],true);
						if($commissiondata){
							$ogdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $ogtotalprice * 0.01;
							$ogdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $ogtotalprice * 0.01;
							$ogdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $ogtotalprice * 0.01;
						}
					}elseif($product['commissionset']==2){//按固定金额
						$commissiondata = json_decode($product['commissiondata2'],true);
						if($commissiondata){
							$ogdata['parent1commission'] = $commissiondata[$agleveldata1['id']]['commission1'] * $ogdata['num'];
							$ogdata['parent2commission'] = $commissiondata[$agleveldata2['id']]['commission2'] * $ogdata['num'];
							$ogdata['parent3commission'] = $commissiondata[$agleveldata3['id']]['commission3'] * $ogdata['num'];
						}
					}else{
						$ogdata['parent1commission'] = $agleveldata1['commission1'] * $ogtotalprice * 0.01;
						$ogdata['parent2commission'] = $agleveldata2['commission2'] * $ogtotalprice * 0.01;
						$ogdata['parent3commission'] = $agleveldata3['commission3'] * $ogtotalprice * 0.01;
					}
				}

				Db::name('scoreshop_order_goods')->where('aid',aid)->where('id',$ogid)->update($ogdata);
			}
			
			$newordernum = date('ymdHis').rand(100000,999999);
			Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->update(['ordernum'=>$newordernum]);
			Db::name('scoreshop_order_goods')->where('aid',aid)->where('orderid',$orderid)->update(['ordernum'=>$newordernum]);
			\app\commons\System::plog('积分商城订单编辑'.$orderid);
			return json(['status'=>1,'msg'=>'修改成功']);
		}
		View::assign('info',$info);
		View::assign('order_goods',$order_goods);
		View::assign('discount',$discount);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
		return View::fetch();
	}
	//送货单
	public function shd(){
		$set = Db::name('admin_set')->where('aid',aid)->find();
		$orderid = input('param.id/d');
		$info = Db::name('scoreshop_order')->where('aid',aid)->where('id',$orderid)->find();
		if(!$info){
			return json(['status'=>0,'msg'=>'订单不存在']);
		}
		if(getcustom('business_scoreshop')){
			$bname = Db::name('business')->where('id',$info['bid'])->value('name');
			if($bname){
				$set['name'] = $bname;
			}
			
		}
		
		if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');		

		$order_goods = Db::name('scoreshop_order_goods')->where('aid',aid)->where('orderid',$orderid)->select()->toArray();
		
		
		$member = Db::name('member')->where('id',$info['mid'])->find();
		$userlevel = Db::name('member_level')->where('aid',aid)->where('id',$member['levelid'])->find();
		if($userlevel && $userlevel['discount']>0 && $userlevel['discount']<10){
			$discount = $userlevel['discount']*0.1; //会员折扣
		}else{
			$discount = 1;
		}
		$order_goods2 = [];
		if(count($order_goods) < 10){
    		for($i=0;$i<10;$i++){
    			$order_goods2[] = $order_goods[$i];
    		}
		}else{
		    $order_goods2 = $order_goods;
		}
		$order_goods2[] = ['type'=>'yf'];
        $order_goods2[] = ['type'=>'totalscore'];
		$order_goods2[] = ['type'=>'totalprice'];
		$order_goods2[] = ['type'=>'totalprice2'];
		$order_goods3 = array_chunk($order_goods2,13);
		$info['totalprice2'] = num_to_rmb($info['totalprice']);
		View::assign('set',$set);
		View::assign('info',$info);
		View::assign('order_goods3',$order_goods3);
		View::assign('discount',$discount);
		View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
		return View::fetch();
	}
	//订单统计
	public function tongji(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'totalprice desc';
			}
			$where = [];
			$where[] = ['og.aid','=',aid];
			$where[] = ['og.bid','=',bid];
			$where[] = ['og.status','in','1,2,3'];
			if($this->mdid){
				$where[] = ['mdid','=',$this->mdid];
			}
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['og.createtime','>=',strtotime($ctime[0])];
				$where[] = ['og.createtime','<',strtotime($ctime[1]) + 86400];
			}
			if(input('param.paytime') ){
				$ctime = explode(' ~ ',input('param.paytime'));
				$where[] = ['scoreshop_order.paytime','>=',strtotime($ctime[0])];
				$where[] = ['scoreshop_order.paytime','<',strtotime($ctime[1]) + 86400];
			}
			if(input('param.proname')){
				$where[] = ['og.name','like','%'.input('param.proname').'%'];
			}
			if(input('param.cid')){
				$where[] = ['og.cid','=',input('param.cid')];
			}
			if(input('param.type')==2){
				$count = 0 + Db::name('scoreshop_order_goods')->alias('og')->join('scoreshop_order','scoreshop_order.id=og.orderid')->field('og.proid,og.name,sum(og.num) num,sum(og.money_price) totalprice')->where($where)->group('ggid')->count();
				$list = Db::name('scoreshop_order_goods')->alias('og')->join('scoreshop_order','scoreshop_order.id=og.orderid')->field('og.proid,og.name,og.pic,sum(og.num) num,sum(og.money_price) totalprice,sum(og.money_price)/sum(og.num) as avgprice')->where($where)->group('ggid')->page($page,$limit)->order($order)->select()->toArray();
			}else{
				$count = 0 + Db::name('scoreshop_order_goods')->alias('og')->join('scoreshop_order','scoreshop_order.id=og.orderid')->field('og.proid,og.name,sum(og.num) num,sum(og.money_price) totalprice')->where($where)->group('proid')->count();
				$list = Db::name('scoreshop_order_goods')->alias('og')->join('scoreshop_order','scoreshop_order.id=og.orderid')->field('og.proid,og.name,og.pic,sum(og.num) num,sum(og.money_price) totalprice,sum(og.money_price)/sum(og.num) as avgprice')->where($where)->group('proid')->page($page,$limit)->order($order)->select()->toArray();
			}
			foreach($list as $k=>$v){
				$list[$k]['ph'] = ($k+1) + ($page-1)*$limit;
				$list[$k]['avgprice'] = number_format($v['avgprice'],2,'.','');
			}

			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		return View::fetch();
	}
	//导出
	public function tjexcel(){
		set_time_limit(0);
		ini_set('memory_limit', '2000M');
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'totalprice desc';
		}
		$where = [];
		$where[] = ['og.aid','=',aid];
		$where[] = ['og.bid','=',bid];
		$where[] = ['og.status','in','1,2,3'];
		if($this->mdid){
			$where[] = ['mdid','=',$this->mdid];
		}
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['og.createtime','>=',strtotime($ctime[0])];
			$where[] = ['og.createtime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('param.paytime') ){
			$ctime = explode(' ~ ',input('param.paytime'));
			$where[] = ['scoreshop_order.paytime','>=',strtotime($ctime[0])];
			$where[] = ['scoreshop_order.paytime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('param.proname')){
			$where[] = ['og.name','like','%'.input('param.proname').'%'];
		}
		if(input('param.cid')){
			$where[] = ['og.cid','=',input('param.cid')];
		}
		if(input('param.type')==2){
			$list = Db::name('scoreshop_order_goods')->alias('og')->join('scoreshop_order','scoreshop_order.id=og.orderid')->field('og.proid,og.name,og.pic,sum(og.num) num,sum(og.totalprice) totalprice,sum(og.totalprice)/sum(og.num) as avgprice')->where($where)->group('ggid')->order($order)->select()->toArray();
		}else{
			$list = Db::name('scoreshop_order_goods')->alias('og')->join('scoreshop_order','scoreshop_order.id=og.orderid')->field('og.proid,og.name,og.pic,sum(og.num) num,sum(og.totalprice) totalprice,sum(og.totalprice)/sum(og.num) as avgprice')->where($where)->group('proid')->order($order)->select()->toArray();
		}
		foreach($list as $k=>$v){
			$list[$k]['ph'] = ($k+1);
			$list[$k]['avgprice'] = number_format($v['avgprice'],2,'.','');
		}
		if(input('param.type')==2){
			$title = array('排名','商品名称','商品规格','销售数量','销售金额','平均单价');
			$data = [];
			foreach($list as $k=>$vo){
				$data[] = [
					$vo['ph'],
					$vo['name'],
					$vo['ggname'],
					$vo['num'],
					$vo['totalprice'],
					$vo['avgprice'],
				]; 
			}
		}else{
			$title = array('排名','商品名称','销售数量','销售金额','平均单价');
			$data = [];
			foreach($list as $k=>$vo){
				$data[] = [
					$vo['ph'],
					$vo['name'],
					$vo['num'],
					$vo['totalprice'],
					$vo['avgprice'],
				]; 
			}
		}
		$this->export_excel($title,$data);
	}
	
	//红包记录
    public function hongbaoRecord(){
        if(getcustom('scoreshop_wx_hongbao')){
            if(request()->isAjax()){
                $page = input('param.page');
                $limit = input('param.limit');
                if(input('param.field') && input('param.order')){
                    $order = input('param.field').' '.input('param.order');
                }else{
                    $order = 'id desc';
                }
                $where = [];
                $where[] = ['aid','=',aid];
                $where[] = ['type','=',1];
                if(bid==0){
                    if(input('param.bid')){
                        $where[] = ['bid','=',input('param.bid')];
                    }elseif(input('param.showtype')==2){
                        $where[] = ['bid','<>',0];
                    }elseif(input('param.showtype')=='all'){
                        $where[] = ['bid','>=',0];
                    }else{
                        $where[] = ['bid','=',0];
                    }
                }else{
                    $where[] = ['bid','=',bid];
                }
                if($this->mdid){
                    $where[] = ['mdid','=',$this->mdid];
                }
                if(input('?param.ogid')){
                    if(input('param.ogid')==''){
    
                    }else{
                        $ids = Db::name('scoreshop_order_goods')->where('id','in',input('param.ogid'))->column('orderid');
                        $where[] = ['id','in',$ids];
                    }
                }
                if(input('param.orderid')) $where[] = ['id','=',input('param.orderid')];
                if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
                if(input('param.proname')) $where[] = ['proname','like','%'.input('param.proname').'%'];
                if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
                if(input('param.linkman')) $where[] = ['linkman','like','%'.input('param.linkman').'%'];
                if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
                if(input('param.ctime') ){
                    $ctime = explode(' ~ ',input('param.ctime'));
                    $where[] = ['createtime','>=',strtotime($ctime[0])];
                    $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
                }
                if(input('param.sendtime') ){
                    $sendtime = explode(' ~ ',input('param.sendtime'));
                    $where[] = ['send_time','>=',strtotime($sendtime[0])];
                    $where[] = ['send_time','<',strtotime($sendtime[1]) + 86400];
                }
                if(input('?param.status') && input('param.status')!==''){
                    if(input('param.status') == 5){
                        $where[] = ['refund_status','=',1];
                    }elseif(input('param.status') == 6){
                        $where[] = ['refund_status','=',2];
                    }elseif(input('param.status') == 7){
                        $where[] = ['refund_status','=',3];
                    }else{
                        $where[] = ['status','=',input('param.status')];
                    }
                }
                $count = 0 + Db::name('scoreshop_order')->where($where)->count();
                //echo M()->_sql();
                $list = Db::name('scoreshop_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
    
                foreach($list as $k=>$vo){
                    $member = Db::name('member')->where('id',$vo['mid'])->find();
                    $oglist = Db::name('scoreshop_order_goods')->where('aid',aid)->where('orderid',$vo['id'])->select()->toArray();
                    $goodsdata=array();
                    foreach($oglist as $og){
                       
                        $goodsdata[] = '<div style="font-size:12px;float:left;clear:both;margin:1px 0">'.
                            '<img src="'.$og['pic'].'" style="max-width:60px;float:left">'.
                            '<div style="float: left;width:160px;margin-left: 10px;white-space:normal;line-height:16px;">'.
                            '<div style="width:100%;min-height:25px;max-height:32px;overflow:hidden">'.$og['name'].'</div>'.
                            ($og['ggname'] ? '<div style="padding-top:0px;color:#f60"><span style="color:#666">规格：'.$og['ggname'].'</span></div>' : '<div style="padding-top:0px;color:#f60"><span style="color:#888">价值：￥'.$og['sell_price'].'</span></div>').
                            '<div style="padding-top:0px;color:#f60;">'.($og['money_price']>0?'￥'+$og['money_price'].'+':'').$og['score_price'].t('积分').' × '.$og['num'].'</div>'.
                            '</div>'.
                            '</div>';
                    }
                   
                    $list[$k]['goodsdata'] = implode('',$goodsdata);
                    $list[$k]['nickname'] = $member['nickname'];
                    $list[$k]['headimg'] = $member['headimg'];
                    $list[$k]['m_remark'] = $member['remark'];
                    $list[$k]['platform'] = getplatformname($vo['platform']);
                    $list[$k]['send_time'] = $vo['send_time']?date('Y-m-d H:i:s',$vo['send_time']):'';
                    if($vo['bid'] > 0){
                        $list[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$vo['bid'])->value('name');
                    }else{
                        $list[$k]['bname'] = '平台自营';
                    }
                }
                return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
            }
            $peisong_set = Db::name('peisong_set')->where('aid',aid)->find();
            if($peisong_set['status']==1 && bid>0 && $peisong_set['businessst']==0 && $peisong_set['make_status']==0) $peisong_set['status'] = 0;
            View::assign('peisong_set',$peisong_set);
            View::assign('express_data',express_data(['aid'=>aid,'bid'=>bid]));
            return View::fetch();
        }
    }
    //导出
    public function hongbaoexcel(){
        if(getcustom('scoreshop_wx_hongbao')){
            set_time_limit(0);
            ini_set('memory_limit', '2000M');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id desc';
            }
            $page = input('param.page');
            $limit = input('param.limit');
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['type','=',1];
            if(bid==0){
                if(input('param.bid')){
                    $where[] = ['bid','=',input('param.bid')];
                }elseif(input('param.showtype')==2){
                    $where[] = ['bid','<>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
                }else{
                    $where[] = ['bid','=',0];
                }
            }else{
                $where[] = ['bid','=',bid];
            }
            if($this->mdid){
                $where[] = ['mdid','=',$this->mdid];
            }
            if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
            if(input('param.proname')) $where[] = ['proname','like','%'.input('param.proname').'%'];
            if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
            if(input('param.linkman')) $where[] = ['linkman','like','%'.input('param.linkman').'%'];
            if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['createtime','>=',strtotime($ctime[0])];
                $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
            }
            if(input('param.sendtime') ){
                $sendtime = explode(' ~ ',input('param.sendtime'));
                $where[] = ['send_time','>=',strtotime($sendtime[0])];
                $where[] = ['send_time','<',strtotime($sendtime[1]) + 86400];
            }
            if(input('?param.status') && input('param.status')!==''){
                if(input('param.status') == 5){
                    $where[] = ['refund_status','=',1];
                }elseif(input('param.status') == 6){
                    $where[] = ['refund_status','=',2];
                }elseif(input('param.status') == 7){
                    $where[] = ['refund_status','=',3];
                }else{
                    $where[] = ['status','=',input('param.status')];
                }
            }
            $list = Db::name('scoreshop_order')->where($where)->order($order)->page($page,$limit)->select()->toArray();
            $count = Db::name('scoreshop_order')->where($where)->order($order)->count();
            $title = array('订单号','下单人','商品名称','规格数量','支付积分','支付金额','支付方式','姓名','电话','下单时间','发放时间','状态','备注','发放备注','其他');
            $data = [];
            foreach($list as $k=>$vo){
                $member = Db::name('member')->where('id',$vo['mid'])->find();
                $oglist = Db::name('scoreshop_order_goods')->where('orderid',$vo['id'])->select()->toArray();
                $xm=array();
                foreach($oglist as $og){
                    $xm[] = $og['name']." × ".$og['num']."";
                }
                $status='';
                if($vo['status']==0){
                    $status = '未支付';
                }elseif($vo['status']==2){
                    $status = '发放未成功';
                }elseif($vo['status']==1){
                    $status = '待发放';
                }elseif($vo['status']==3){
                    $status = '发放成功';
                }elseif($vo['status']==4){
                    $status = '已关闭';
                }
           
                $data[$k] = [
                    ' '.$vo['ordernum'],
                    $member['nickname'],
                    $vo['title'],
                    implode("\r\n",$xm),
                    $vo['totalscore'],
                    $vo['totalmoney'],
                    $vo['paytype'],
                    $vo['linkman'],
                    $vo['tel'],
                    date('Y-m-d H:i:s',$vo['createtime']),
                    date('Y-m-d H:i:s',$vo['send_time']),
                    $status,
                    $vo['remark'],
                    $vo['send_remark'],
                    ''
                ];
                //配送自定义表单
                $vo['formdata'] = \app\models\Freight::getformdata($vo['id'],'scoreshop_order');
                if($vo['formdata']) {
                    foreach ($vo['formdata'] as $formdata) {
                        if($formdata[2] != 'upload') {
                            if($formdata[0] == '备注') {
                                $data[$k][15] = $formdata[1];
                            } else {
                                $data[$k][16] .= $formdata[0].':'.$formdata[1]."\r\n";
                            }
                        }
                    }
                }
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
            $this->export_excel($title,$data);
        }
    }
    //发放
    public function sendHongbao(){
        if(getcustom('scoreshop_wx_hongbao')){
            $orderid = input('param.orderid');
            $order = Db::name('scoreshop_order')->where('id',$orderid)->find();
            if($order['freight_type']==3){
                $og = Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->find();
                if($og['type'] ==1){
                    $hb_scoreshop_product = Db::name('scoreshop_product')->where('id',$og['proid'])->field('hongbao_money,scene_id')->find();
                    if($hb_scoreshop_product['hongbao_money'] > 0){
                        $money =  dd_money_format($hb_scoreshop_product['hongbao_money'],2);
                        $rs = \app\commons\Wxpay::sendredpackage($order['aid'],$order['mid'],$order['platform'],$money,mb_substr($order['title'],0,10),'微信红包','恭喜发财','微信红包',$hb_scoreshop_product['scene_id']);
                        if($rs['status']==0){ //发放失败
                            Db::name('scoreshop_order')->where('id',$order['id'])->update(['send_remark'=>$rs['msg'],'send_time' => time(),'status' => 2]);
                            return json(['code'=>0,'msg'=>$rs['msg']]);
                        }else{
                            //修改订单状态
                            Db::name('scoreshop_order')->where('id',$order['id'])->update(['status'=>3,'send_time'=>time(),'send_remark'=>'红包发放成功']);
                            Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->update(['status'=>3]);
                        }
                    }
                }
            }
            return json(['code'=>0,'msg'=>'发放成功']);
        }
    }
}
