<?php


//签到
namespace app\controllers;
use think\facade\Db;
class ApiSign extends ApiCommon
{
	public function initialize(){
		parent::initialize();
		$this->checklogin();
	}
	//签到页面
	public function index(){
		$signset = Db::name('signset')->where('aid',aid)->order('id desc')->find();
		$userinfo = Db::name('member')->field('id,nickname,headimg,sex,realname,tel,score,signtimes,signtimeslx,signtime,signdate')->where('id',mid)->find();
		if($this->member['signdate'] == date('Y-m-d')){
			$hassign = 1;
		}else{
			$hassign = 0;
		}
        $rdata = [];
		$rdata['signset'] = $signset;
		$rdata['userinfo'] = $userinfo;
		$rdata['hassign'] = $hassign;
        $rdata['today'] = date('Y-m-d');
        $rdata['selectedDate'] = [];
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['mid','=',mid];
        $sign_record = Db::name('sign_record')->where($where)->order('createtime','desc')->limit(60)->column('signdate');
        if($sign_record) {
            foreach ($sign_record as $date){
                $rdata['selectedDate'][]=['date'=>$date,'info'=>'已签'];
            }
            //{date: '2022-08-02', info: '已签'}
        }
        if($signset['display'] == 1){
        	$list = Db::name('member')->where('aid',aid)->where('signtime','>=',strtotime("yesterday"))->where('signtimeslx','>',0)->field('id,nickname,headimg,signtimes,signtimeslx,signtime')->limit(10)->order('signtimeslx desc')->select()->toArray();
            $rdata['list'] = $list;
        }
		return $this->json($rdata);
	}
    //排名数据
    public function getPaiming(){
        $pagenum = input('post.pagenum');
        if(!$pagenum) $pagenum = 1;
        $pernum = 10;

        $rdata=[];
        $rdata['datalist']=[];
        $signset = Db::name('signset')->where('aid',aid)->order('id desc')->find();
        if($signset['display'] == 1){
            $list = Db::name('member')->where('aid',aid)->where('signtime','>=',strtotime("yesterday"))->where('signtimeslx','>',0)
            ->field('id,nickname,headimg,signtimes,signtimeslx,signtime')->page($pagenum,$pernum)->order('signtimeslx desc')->select()->toArray();
            $rdata['datalist'] = $list;
        }else{
            return $this->json(['status'=>0,'msg'=>'未开启排名数据']);
        }
        $rdata['status'] = 1;
        return $this->json($rdata);
    }

	public function signin(){
        Db::startTrans();
        try {
            if(!$this->member) return $this->json(['status'=>0,'msg'=>'获取用户信息失败,请重新进入']);
            $signset = Db::name('signset')->where('aid',aid)->order('id desc')->find();
            if(!$signset || $signset['status']==0) return $this->json(['status'=>0,'msg'=>'签到活动尚未开启']);
            $this->member = Db::name('member')->lock(true)->where('aid',aid)->where('id',mid)->find();
            if($this->member['signdate'] == date('Y-m-d')) return $this->json(['status'=>0,'msg'=>'您今天已经签到过了']);
            $score = $signset['score'];
            $signtimes = $this->member['signtimes'] + 1;
            if($this->member['signdate'] == date('Y-m-d',(time()-86400))){
                $signtimeslx = $this->member['signtimeslx'] + 1;
            }else{
                $signtimeslx = 1;
            }
            $lxqdset = json_decode($signset['lxqdset'],true);
            $remark = '签到';
            $lxqd_coupon_id = 0;
            if($lxqdset){
                foreach($lxqdset as $k=>$v){
                    if($v['score']!=='' && $v['score']!==null && $signtimeslx >= $v['days']){
                        $score = $v['score'];
                        $remark = '连续签到';
                    }
                    //赠送优惠券
                    if($v['coupon_id']!=='' && $v['coupon_id']!==null && $signtimeslx >= $v['days']){
                        \app\commons\Coupon::send(aid,mid,$v['coupon_id']);
                        $lxqd_coupon_id = $v['coupon_id'];
                    }
                }
            }
            $score2 = 0;
            $lxzsset = json_decode($signset['lxzsset'],true);
            $lxzs_coupon_id = 0;
            if($lxzsset){
                foreach($lxzsset as $k=>$v){
                    if($v['score']!=='' && $v['score']!==null && $signtimeslx == $v['days']){
                        $score2 = $v['score'];
                        $remark .= ' + 赠送';
                    }
                    //赠送优惠券
                    if($v['coupon_id']!=='' && $v['coupon_id']!==null && $signtimeslx == $v['days']){
                        \app\commons\Coupon::send(aid,mid,$v['coupon_id']);
                        $lxzs_coupon_id = $v['coupon_id'];
                    }
                }
            }

            $totalscore = $score + $score2;
            $score_weishu = 0;
            $totalscore = dd_money_format($totalscore,$score_weishu);
           
                $sdata = array();
                $sdata['aid'] = aid;
                $sdata['mid'] = mid;
                $sdata['score'] = $totalscore;
                $sdata['nickname'] = $this->member['nickname'];
                $sdata['headimg'] = $this->member['headimg'];
                $sdata['signdate'] = date('Y-m-d');
                $sdata['signtimes'] = $signtimes;
                $sdata['signtimeslx'] = $signtimeslx;
                $sdata['remark'] = $remark;
                $sdata['lxqd_coupon_id'] = $lxqd_coupon_id;
                $sdata['lxzs_coupon_id'] = $lxzs_coupon_id;
                $sdata['createtime'] = time();
                Db::name('sign_record')->insert($sdata);
                $mdata = array();
                $mdata['signdate'] = date('Y-m-d');
                $mdata['signtime'] = time();
                $mdata['signtimes'] = $signtimes;
                $mdata['signtimeslx'] = $signtimeslx;
                //$condition  =1 无条件补签 2赠送补签天数根据天数补签
            Db::name('member')->where('aid',aid)->where('id',mid)->update($mdata);
            if($totalscore > 0){
                \app\commons\Member::addscore(aid,mid,$totalscore,'签到赠'.t('积分'));
            }
                
         
            Db::commit();
            return $this->json(['status'=>1,'msg'=>'签到成功','scoreadd'=>$totalscore]);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return $this->json(['status'=>0,'msg'=>'签到失败，请稍后再试']);
        }
	}

    // 补卡
    public function signForget(){
         
        }

  
	public function signrecord(){
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$datalist = Db::name('sign_record')->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
        $score_weishu = 0;
        foreach($datalist as $k=>$v){
            $datalist[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
            $datalist[$k]['score'] = dd_money_format($v['score'],$score_weishu);
            }
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$datalist]);
		}
		$rdata = [];
		$rdata['datalist'] = $datalist;
		return $this->json($rdata);
	}


	//签到支付页
	public function createorder(){
		if(request()->isPost()){
            
            $time = input('post.time');
            $forget = input('post.forget');

			$set = Db::name('signset')->where('aid',aid)->find();
			if(!$this->member) return $this->json(['status'=>0,'msg'=>'获取用户信息失败,请重新进入']);
            $signset = Db::name('signset')->where('aid',aid)->order('id desc')->find();
            if(!$signset || $signset['status']==0) return $this->json(['status'=>0,'msg'=>'签到活动尚未开启']);
            $this->member = Db::name('member')->lock(true)->where('aid',aid)->where('id',mid)->find();
            if($this->member['signdate'] == date('Y-m-d') && $forget != 1) return $this->json(['status'=>0,'msg'=>'您今天已经签到过了']);
			$orderdata = [];
			$orderdata['aid'] = aid;
			$orderdata['mid'] = mid;
			$orderdata['status'] = 0;
			$orderdata['createtime'] = time();
			$ordernum = \app\commons\Common::generateOrderNo(aid);
			$orderdata['ordernum'] = $ordernum;
			$orderdata['price'] = $set['payprice'];
			$orderdata['title'] = '签到支付';
			$orderid = Db::name('sign_order')->insertGetId($orderdata);
			$payorderid = \app\models\Payorder::createorder(aid,0,mid,'sign',$orderid,$ordernum,'签到支付',$set['payprice'],0);
			return $this->json(['status'=>1,'payorderid'=>$payorderid,'msg'=>'提交成功']);
		}
		$rdata = []; 
		$rdata['set'] = $set;
		return $this->json($rdata);
	}
	public function getpmlist(){
		$bonus = Db::name('sign_bonus')->where('aid',aid)->where('status',1)->order('id desc')->find();
		$pmlist = Db::name('sign_kaijianglog')->where('aid',aid)->where('bonusid',$bonus['id'])->order('money desc')->limit(10)->select()->toArray();
		foreach($pmlist as &$p){
			if($p['mid']>0){
				$member = Db::name('member')->field('nickname,headimg')->where('aid',aid)->where('id',$p['mid'])->find();
				$p['headimg'] = $member['headimg'];
				$hiddenName = $this->getnewname($member['nickname']); 
				$p['nickname'] = $hiddenName;
			}
		}
		//查看自己的奖金
		$userinfo = Db::name('sign_kaijianglog')->where('aid',aid)->where('bonusid',$bonus['id'])->where('mid',mid)->find();
		if($userinfo){
			$userinfo['nickname'] = $this->member['nickname'];
			$userinfo['headimg'] = $this->member['headimg'];
		}else{
			$userinfo = [];
		}
	

		$bonuscount = Db::name('sign_bonus')->where('aid',aid)->where('status',1)->count();
		if(request()->isPost()){
			return $this->json(['status'=>1,'data'=>$pmlist,'bonuscount'=>$bonuscount,'userinfo'=>$userinfo]);
		}
	}


	/*替换，自己需要的字符串
	注意：红色部分根据自己需要修改成自己的长度会起始位置
	*/
	function getnewname($name){
		// 计算字符串长度，无论汉字还是英文字符全部为1
		$length = mb_strlen($name,'utf-8');

		// 截取前面字符串
		$firstStr1 = mb_substr($name,0,intval(ceil($length/4)),'utf-8');
		// 截取中间字符串代码
		$firstStr = mb_substr($name,intval(ceil($length/4)),intval(floor($length/2)),'utf-8');
		// 截取剩余字符串
		$firstStr2 = mb_substr($name,intval(ceil($length/4)) + intval(floor($length/2)), intval(floor(($length+1)/2 - 1)),'utf-8');

		return $firstStr1 . str_repeat("*",mb_strlen($firstStr,'utf-8')) . $firstStr2;
	}

}