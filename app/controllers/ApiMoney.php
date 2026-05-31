<?php


namespace app\controllers;
use think\facade\Db;
class ApiMoney extends ApiCommon
{
	public function initialize(){
		parent::initialize();
		$this->checklogin();
	}

	//余额充值
	public function recharge(){
		$canrecharge = Db::name('admin_set')->where('aid',aid)->value('recharge');
		$giveset = Db::name('recharge_giveset')->where('aid',aid)->find();
		if($giveset && $giveset['status']==1){
            $givedata = json_decode($giveset['givedata'],true);
            foreach ($givedata as $k => $item){
                if($item['money'] <= 0 || empty($item['money'])){
                    //充值金额非法
                    unset($givedata[$k]);
                }
            }
		}else{
			$givedata = [];
		}
		if(request()->isPost()){
			if($canrecharge == 0) return $this->json(['status'=>0,'msg'=>t('余额').'充值功能未启用']);
			$money = input('post.money');
			if($money>0){
				$ordernum = date('ymdHis').aid.rand(1000,9999);
				//增加消费记录
				$orderdata = [];
				$orderdata['aid'] = aid;
				$orderdata['mid'] = mid;
				$orderdata['createtime']= time();
				$orderdata['money'] = $money;
				$orderdata['ordernum'] = $ordernum;
				$orderid = Db::name('recharge_order')->insertGetId($orderdata);
				$payorderid = \app\models\Payorder::createorder(aid,0,$orderdata['mid'],'recharge',$orderid,$ordernum,t('余额').'充值',$money);

				return $this->json(['status'=>1,'msg'=>'提交成功','orderid'=>$orderid,'payorderid'=>$payorderid]);
			}else{
				return $this->json(['status'=>0,'msg'=>'充值金额必须大于0']);
			}
		}
		$userinfo = [];
		$userinfo['money'] = $this->member['money'];
        $moeny_weishu = 2;
        $userinfo['money'] = dd_money_format($userinfo['money'],$moeny_weishu);
		$set = Db::name('admin_set')->where('aid',aid)->find();
		$rdata = [];
		$rdata['canrecharge'] = $canrecharge;
		$rdata['giveset'] = $givedata;
		$rdata['shuoming'] = $giveset['shuoming'];
		$rdata['caninput'] = $giveset ? $giveset['caninput'] : 1;
		$rdata['userinfo'] = $userinfo;
		$rdata['transfer'] = $set['money_transfer'] ? true : false;
		return $this->json($rdata);
	}
    public function rechargeToMember()
    {
        if(getcustom('money_transfer') || getcustom('money_friend_transfer')) {
            $mid = input('param.mid/d',0);
            //给他人充值 转账
            //        $info = Db::name('member_recharge')->where('aid', aid)->where('mid', mid)->find();
//        if(empty($info)) {
//            return $this->json(['status'=>0,'msg'=>'您无此权限操作']);
//        }
            $set = Db::name('admin_set')->where('aid',aid)->find();
            if($set['money_transfer'] != 1) {
                return $this->json(['status'=>0,'msg'=>'未开启此功能']);
            }
            if(request()->isPost()){
                $mobile = input('post.mobile');
                $mid = input('post.mid/d');
                $money = input('post.money/f');
                if ($money < 0.01){
                    return $this->json(['status'=>0,'msg'=>'请输入正确的金额，最小金额为：0.01']);
                }
                if($this->sysset['transfer_rate']>0 ){
            	    $transfer_rate = $this->sysset['transfer_rate'];
    		    	if($money%$transfer_rate!=0)return $this->json(['status'=>0,'msg'=>'金额需是'.$transfer_rate.'的倍数']);
    			}
                if (input('?post.mobile') && !empty($mobile)) {
                    $member = Db::name('member')->where('aid', aid)->where('tel', $mobile)->find();
                }
                if (input('?post.mid') && $mid > 0) {
                    $member = Db::name('member')->where('aid', aid)->where('id', $mid)->find();
                }
                if(!$member) return $this->json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
                $user_id = $member['id'];

                if ($user_id == mid) {
                    return $this->json(['status'=>0,'msg'=>'不能转账给自己']);
                }
                if($set['transfer_range'] == 1) {
                    //所有上下级
                    $isparent = false;
                    if(in_array($user_id,explode(',',$this->member['path']))){
                        $isparent = true;
                    }
                    if(!$isparent){
                        if(!in_array(mid,explode(',',$member['path']))){
                            return $this->json(['status'=>0,'msg'=>'仅限转账给上下级'.t('会员')]);
                        }
                    }
                }
                
                
                $money_transfer_fee = round($money*$set['money_transfer_fee']*0.01,2);
    			if ($this->sysset['transfer_fee_type']==1) {
    			   $tomoney = round($money - $money_transfer_fee,2);
    			} else {
    			   $tomoney = $money;
    			   $money = round($money + $money_transfer_fee,2);
    			}
    		
                if ($money > $this->member['money']){
                    return $this->json(['status'=>0,'msg'=>'您的'.t('余额').'不足']);
                }
                //验证支付密码
                $pwd_check = $set['transfer_pwd'];
                if($pwd_check){
                    if(!$this->member['paypwd']){
                        return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
                    }
                    $pay_pwd = input('paypwd')?:'';
                    if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )){
                        return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
                    }
                }
                $midMsg = sprintf("转账给：%s",$member['nickname']);
                $toMidMsg = sprintf("来自%s的转账", $this->member["nickname"]);
                if ($set['transfer_range'] == 2) {
                    $midMsg = sprintf("转给：%s",$member['nickname']);
                    $toMidMsg = sprintf("来自%s的转账", $this->member["nickname"]);
                }
				$rs = \app\commons\Member::addmoney(aid,mid,$money * -1, $midMsg);
                if ($rs['status'] == 1) {
                    \app\commons\Member::addmoney(aid,$user_id,$tomoney,$toMidMsg,$this->mid);
                }else{
					 return $this->json(['status'=>0, 'msg' => '转账失败']);
				}
                return $this->json(['status'=>1, 'msg' => '转账成功', 'url'=>'/pages/my/usercenter']);
            }
            $tomember = [];
            if($mid){
                $tomember = Db::name('member')->where('aid',aid)->where('id',$mid)->field('id,money,nickname,headimg')->find();
            }
            if($this->member['paypwd']=='') {
    			$rdata['haspwd'] = 1;
    		} else {
    			$rdata['haspwd'] = 0;
    		}
            $rdata['paycheck'] = $set['transfer_pwd'] ? true : false;
            $rdata['status'] = 1;
            $rdata['mymoney'] = $this->member['money'];
            $rdata['moneyList'] = [];//可选金额列表
            $rdata['tomember'] = $tomember?$tomember:['nickname'=>''];//转给谁
           
            $rdata['transfer_type'] = $set['transfer_type'] ? explode(',',$set['transfer_type']) : [];
            $rdata['money_transfer_fee'] =$set['money_transfer_fee'];
            return $this->json($rdata);
        }
    }
  
}