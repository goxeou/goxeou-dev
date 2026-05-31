<?php
// +----------------------------------------------------------------------
// | 门店中心     custom_file(mendian_upgrade)
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\Db;
class ApiScoreSiteCenter extends ApiCommon
{
	public $mendian;
	public function initialize(){
		parent::initialize();
		$this->checklogin();
		if(!$this->member){
			echojson(['status'=>-4,'msg'=>'请先登录'.t('站点').'绑定的用户','url'=>'/pages/index/login?frompage=/pagesExt/score_site/my']);
		}
		if(!$this->mendian ){
			$mendian = Db::name('score_site')->where('aid',aid)->where('mid',mid)->find();
			if(!$mendian){
				echojson(['status'=>-4,'msg'=>'请先申请'.t('门店'),'url'=>'/pagesExt/score_site/apply']);
			}else{
				$this->mendian = $mendian;
			}
		}
		//查看状态
		if($mendian){
			if($mendian['status']!=1){
				echojson(['status'=>-4,'msg'=>'门店审核未通过','url'=>'/pagesExt/score_site/apply']);
			}
		}
	}
	//我的
	public function my(){
		$mendian = Db::name('score_site')->where('id',$this->mendian['id'])->find();
		$member = Db::name('member')->where('id',$mendian['mid'])->find();
		$mendian['headimg'] = $member['headimg'];
		$mendian['nickname'] = $member['nickname'];
		$mendian['totalmoney'] =Db::name('score_site_moneylog')->where('mdid',$this->mendian['id'])->where('aid',aid)->where('fromid','>',0)->sum('money');
		$mendian['totalmoney']  = number_format($mendian['totalmoney'],2);

		
		$mendian['totalnum'] =0+Db::name('score_site_moneylog')->where('mdid',$this->mendian['id'])->where('fromid','>',0)->where('aid',aid)->count();
		
			
		$mendian['membernum'] =0+Db::name('score_site_moneylog')->where('mdid',$this->mendian['id'])->where('aid',aid)->where('fromid','>',0)->group('fromid')->count();
		
	
		$level = Db::name('mendian_level')->where('id',$this->mendian['levelid'])->find();
		$mendian['levelname'] = $level['name'];
		
		//今日订单
	    $today_start = strtotime(date('Y-m-d').' 00:00:01');
        $today_end = strtotime(date('Y-m-d').' 23:59:59');
	
		$mendian['daytotalnum'] =0+Db::name('score_site_moneylog')->where('mdid',$this->mendian['id'])->where('aid',aid)->where('fromid','>',0)->where('createtime','between',[$today_start,$today_end])->count();

		
		$mendian['paymembernum'] =0+Db::name('score_site_moneylog')->where('mdid',$this->mendian['id'])->where('aid',aid)->where('createtime','between',[$today_start,$today_end])->where('fromid','>',0)->group('fromid')->count();

		$mendian['daypaymoney'] =Db::name('score_site_moneylog')->where('mdid',$this->mendian['id'])->where('aid',aid)->where('createtime','between',[$today_start,$today_end])->where('fromid','>',0)->sum('money');
		$mendian['daypaymoney']  = number_format($mendian['daypaymoney'],2);

	
	
		return $this->json(['status'=>1,'mendian'=>$mendian]);
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

	//设置店铺信息
	public function setinfo(){
		if(request()->isPost()){
			$postinfo = input('post.info/a');
			$info = [];
			$info['tel'] = $postinfo['tel'];
			$info['logo'] = $postinfo['logo'];
			$info['desc'] = $postinfo['desc'];
			$info['pics'] = $postinfo['pics'];
			if(isset($postinfo['content'])) $info['content'] = $postinfo['content'];
            if(isset($postinfo['end_buy_status'])) $info['end_buy_status'] = $postinfo['end_buy_status'];
			$info['address'] = $postinfo['address'];
			$info['longitude'] = $postinfo['longitude'];
			$info['latitude'] = $postinfo['latitude'];
			$info['weixin'] = $postinfo['weixin'];
			$info['aliaccount'] = $postinfo['aliaccount'];
			$info['bankname'] = $postinfo['bankname'];
			$info['bankcarduser'] = $postinfo['bankcarduser'];
			$info['bankcardnum'] = $postinfo['bankcardnum'];
			
			$info['start_hours'] = $postinfo['start_hours'];
			$info['end_hours'] = $postinfo['end_hours'];
		
			$info['video'] = $postinfo['video'];
			
			
			$info['is_open'] = 1;
			$info['autocollecthour'] = $postinfo['autocollecthour'];
		
			db('score_site')->where(['aid'=>aid,'id'=>$this->mendian['id']])->update($info);
			\app\commons\System::plog('系统设置');
			return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
		}
		$info = Db::name('score_site')->where('aid',aid)->where('id',$this->mendian['id'])->find();
		$rdata = [];
		$rdata['info'] = $info;
		return $this->json($rdata);
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
	
	
		//门店积分提现记录
	public function moneylog(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mdid','=',$this->mendian['id']];
		$datalist = Db::name('score_site_moneylog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	
	
	
	//门店积分提现记录
	public function withdrawlog(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mdid','=',$this->mendian['id']];
		$datalist = Db::name('score_site_withdrawlog')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function withdraw(){
		$set = Db::name('admin_set')->where(['aid'=>aid])->field('withdrawmin,withdraw_weixin,withdraw_aliaccount,withdraw_bankcard,withdraw_autotransfer')->find();
		$mendian = Db::name('score_site')->where('id',$this->mendian['id'])->find();
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
				return $this->json(['status'=>0,'msg'=>'可提现积分不足']);
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
			$record['money'] =dd_money_format( $money*$set['withdrawfee'],2);
			$record['txmoney'] = $money;
			$record['ordernum'] = $ordernum;
			$record['paytype'] = $post['paytype'];
			if(empty($mendian['bid']) && ($post['paytype']=='微信' || $post['paytype']=='微信钱包')){
				if($set['commission_autotransfer']==1){
				
					\app\commons\Mendian::ScoreSite(aid,$mendian['id'],-$money,'积分提现');
					$mid = $this->user['mid'];
					if(!$mid) return json(['status'=>0,'msg'=>'未绑定微信']);
					$rs = \app\commons\Wxpay::transfers(aid,$mid,$record['money'],$record['ordernum'],'','积分提现');
					if($rs['status']==0){
						\app\commons\Mendian::ScoreSite(aid,$mendian['id'],$money,'积分提现失败返还');
						return json(['status'=>0,'msg'=>$rs['msg']]);
					}else{
						$record['weixin'] = t('会员').'ID：'.$mid;
						$record['status'] = 3;
						$record['paytime'] = time();
						$record['paynum'] = $rs['resp']['payment_no'];
						$id = db('score_site_withdrawlog')->insertGetId($record);

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
			$recordid = db('score_site_withdrawlog')->insertGetId($record);

			\app\commons\ScoreSite::addmoney(aid,$mendian['id'],-$money,'积分提现');

			return $this->json(['status'=>1,'msg'=>'提交成功,请等待打款']);
		}
		$userinfo = db('score_site')->where(['id'=>$mendian['id']])->field('id,money,weixin,aliaccount,aliaccountname,bankname,bankcarduser,bankcardnum')->find();

		$rdata = [];
		$rdata['userinfo'] = $userinfo;
		$rdata['sysset'] = $set;
		return $this->json($rdata);
	}
	//积分转积分
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
            \app\commons\Mendian::addmoney(aid,$mendian['id'],-$money,'积分转'.t('积分'));
            \app\commons\Member::addmoney(aid,$this->mendian['mid'],$money,t('积分').'转'.t('积分'));
            Db::commit();
        }catch(Exception $e){
            Db::rollback();
        }
		
		return $this->json(['status'=>1,'msg'=>'转入成功']);
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
			Db::name('score_site')->where('id',$this->mendian['id'])->update($data);
			return $this->json(['status'=>1,'msg'=>'保存成功']);
		}
		$info = Db::name('score_site')->field('id,weixin,aliaccountname,aliaccount,bankname,bankcarduser,bankcardnum')->where(['id'=>$this->mendian['id']])->find();
		return $this->json(['status'=>1,'info'=>$info]);
	}


	public function getqrcode(){
		$field = platform.'qrcode';
		if(platform=='h5'){
			$wxthqrcode = createqrcode(m_url('pagesExt/score_site/rechargeToMember?mdid='.$this->mendian['id']));
		}else{
			$wxthqrcode = \app\commons\Wechat::getQRCode(aid,platform,'pagesExt/score_site/index',['mdid'=>$this->mendian['id']]);
		}
		$mendian =  Db::name('score_site')->field($field)->where('id',$this->mendian['id'])->where('aid',aid)->find();
		if(!$mendian[$field]){
			Db::name('score_site')->where('id',$mendian['id'])->where('aid',aid)->update([$field=>$wxthqrcode]);
		}

		$rdata = [];
		$rdata['status']=1;
		if($wxthqrcode['msg'] && $rdata['status']===0){
			$rdata['status']=0;
			$rdata['msg'] = $wxthqrcode['msg'];
		}else{
			$wxthqrcode = $wxthqrcode;
		}
		
		$rdata['url'] = $wxthqrcode;
		$rdata['showthqrcode'] = true;
		return $this->json($rdata);
	

	}

}