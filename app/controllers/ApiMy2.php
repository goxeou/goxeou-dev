<?php
namespace app\controllers;
use think\facade\Db;

class ApiMy2 extends ApiCommon {
	public function initialize() {
		parent::initialize();
		$this->checklogin();
	}
	
	public function loginsub(){
     	$info =  input('param.info');
     	ll(input('param.'),'param');
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','=',$info['id']];
		$member = Db::name('member')->where($where)->find();
		if(!$member){
			return $this->json(['status'=>0,'msg'=>'账号错误']);
		}
		if($member['checkst'] == 0) return $this->json(['status'=>0,'msg'=>'账号审核中']);
		if($member['checkst'] == 2) return $this->json(['status'=>0,'msg'=>'账号审核未通过,驳回原因:'.$member['checkreason']]);
		
		ll($this->sessionid,'sessionid');
		
		
		
		cache($this->sessionid.'_mid',$member['id'],7*86400);

        Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
            'mid' => $member['id'],
            'login_time' => time()
        ]);
        $userinfo = Db::name('member')->where('id',$member['id'])->field('id,headimg,nickname')->find();
		return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$member['id'],'userinfo'=>$userinfo,'session_id'=>$this->sessionid]);
	
	
	}
	
		//编辑手机号
	public function loginlist(){
		$userinfo = Db::name('member')->where('id',mid)->field('id,headimg,nickname,realname,tel,usercard,weixin,aliaccount,aliaccountname,bankname,bankaddress,bankcarduser,bankcardnum,sex,province,city,birthday')->find();
		$info =  input('post.info');
		$infoArr = [];
		if ($info) {
		    foreach($info as $k=>$v){
		        $var = Db::name('member')->where('id',$v['id'])->field('id,headimg,nickname,realname,tel')->find();
		        if ($var) {
		           $var['session_id'] = $v['session_id'];
		           $infoArr[] = $var;
		        }
		        if (count($infoArr) >= 5) {
		             break;
		        }
    		}
		}
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['userinfo'] = $userinfo;
		$rdata['infoArr'] = $infoArr;
		return $this->json($rdata);
	}
	
	
	public function memberSearch(){
        $keyword = input('post.keyword');
    	$parent = Db::name('member')->where('aid', aid)->where('id', mid)->field('id,nickname,levelid,tel,headimg')->find();
	    $parent['deep'] = 0;
        $clist = \app\commons\Member::getteammids3(aid,mid);
        $clist = array_merge([$parent],$clist);
        $levelArr = Db::name('member_level')->where('aid',aid)->order('sort,id')->column('*','id');
        $newclist = [];
    	foreach ($clist as $key => $val) {
    	     if ($levelArr[$val['levelid']]['can_agent']>0) {
    		    if (preg_match($keyword, $val['realname']) || preg_match($keyword, $val['nickname']) || preg_match($keyword, $val['tel'])) {
    		         $text = '【'.$val['id'].'】';
    		         $level = $val['deep']+1;
    		         $val['nickname'] = ($val['realname']?$val['realname']:$val['nickname']).$text.'('.$level.'级下线)';
    		         $newclist[] = $val;
    		    }
    	     }
		}
		$rdata = [];
 		$rdata['clist'] = $newclist;
		ll($keyword,'auto_day');
		return $this->json($rdata);
		
	}
	
	public function apply(){
		$this->checklogin();
		$aid = aid;
		if(request()->isPost()){
			$formdata = input('post.info/a');
			$levelinfo = Db::name('member_level')->where('id',$this->member['levelid'])->find();
			if ($levelinfo['is_reg_down']!=1) {
			    return $this->json(['status'=>0,'msg'=>'您暂无权限']);
			} 
			$hasun = Db::name('member')->where('aid',aid)->where('tel',$formdata['tel'])->find();
			if($hasun){
				return $this->json(['status'=>0,'msg'=>'该手机号已存在']);
			}
			$data = [];
			$data['aid'] = aid;
			$data['tel'] = $formdata['tel'];
			$data['realname'] = $formdata['realname'];
			$data['pwd'] = md5(substr($formdata['tel'],-6));
			$data['nickname'] = $formdata['nickname']?$formdata['nickname'] :substr($formdata['tel'],0,3).'****'.substr($formdata['tel'],-4);
			$data['sex'] = 3;
			$data['headimg'] = PRE_URL.'/static/img/touxiang.png';
			$data['createtime'] = time();
            $data['last_visittime'] = time();
	    	$data['pid'] = mid;
			$data['platform'] = platform;
			$_pid = $formdata['agentid'];
			$_parent = Db::name('member')->where('aid',aid)->where('id',$_pid)->find();
			if (!$_parent)  return $this->json(['status'=>0,'msg'=>'节点用户不存在']);
			$uplv = Db::name('member_level')->where('aid',aid)->where('id',$_parent['levelid'])->find();
            //等级是否有分销权限
            if($_parent && $uplv['can_agent']==0){
                return $this->json(['status'=>0,'msg'=>'节点用户无分销权限']);
            }
			$data['_pid'] = $_pid;
			$mid = \app\models\Member::add(aid,$data);
			return $this->json(['status'=>1,'msg'=>'提交成功']);
		}
    
	    $parent = Db::name('member')->where('aid', aid)->where('id', mid)->field('id,tel,levelid,nickname,headimg')->find();
	    $parent['deep'] = 0;
        $clist = \app\commons\Member::getteammids3(aid,mid);
        $clist = array_merge([$parent],$clist);
        $levelArr = Db::name('member_level')->where('aid',aid)->order('sort,id')->column('*','id');
        $newclist = [];
    	foreach ($clist as $key => $val) {
    	    if ($levelArr[$val['levelid']]['can_agent']>0) {
    	        $text = '【'.$val['id'].'】';
    		    $level = $val['deep']+1;
    		    $val['nickname'] = ($val['realname']?$val['realname']:$val['nickname']).$text.'('.$level.'级下线)';
    	        $newclist[] = $val;
    	    }
		}
		$rdata = [];
 		$rdata['clist'] = $newclist;
		$rdata['parent'] = $parent;
		return $this->json($rdata);
	}
	public function getacc2(){
      $acc1 = make_rand_code(2, 1);
      $acc2 = make_rand_code(1, 6);
      $acc =$acc1.$acc2 ;
      $hasun = Db::name('member')->where('aid',aid)->where('acc',$acc)->find();
      if (!$hasun) {
           return $acc;
      }else {
            self::getacc2();
      }
	}
	public function getacc(){
        $acc = self::getacc2();
        return $this->json(['status'=>1,'acc'=>$acc]);
     
	}
	
	
	public function loginlist(){
	    $tel = $this->member['tel'];
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['tel','=',$tel];
// 		$where[] = ['id','<>',mid];
		$datalist = Db::name('member')->field("id,tel,realname,nickname,headimg,acc")->where($where)->order('id desc')->select()->toArray();
    	if(request()->isPost()){
    	    $formdata = input('post.info/a');
    	    $member = Db::name('member')->where('aid',aid)->where('id',$formdata['id'])->find();
		    if($member && $member['tel']==$tel){
		        cache($this->sessionid.'_mid',$member['id'],7*86400);
                Db::name('session')->where('aid',aid)->where('session_id',$this->sessionid)->update([
                    'mid' => $member['id'],
                    'login_time' => time()
                ]);
		        return $this->json(['status'=>1,'msg'=>'登录成功','mid'=>$member['id'],'session_id'=>$this->sessionid]);
		    }else {
		        return $this->json(['status'=>0,'msg'=>'账号异常']);
		    }
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	
	
	
	
	
	
	
	
	
	
	
	//入驻申请
	public function apply(){
		$this->checklogin();
		if(request()->isPost()){
			$formdata = input('post.info/a');
			$info = [];
			if ($formdata['status']==1) {
			    $info['pics'] = $formdata['pics'];
			   	$info['status'] = 2;
		    	$info['createtime'] = time();
			}else {
    			$info['aid'] = aid;
    			$info['mid'] = mid;
    			$info['form0'] = $formdata['form0'];
    			$info['form1'] = $formdata['form1'];
    			$info['form2'] = $formdata['form2'];
    			$info['form3'] = $formdata['form3'];
    			$info['form4'] = $formdata['form4'];
    			$info['form5'] = $formdata['form5'];
    			$info['form6'] = $formdata['form6'];
    			$info['form7'] = $formdata['form7'];
    			$info['form8'] = $formdata['form8'];
    			$info['form9'] = $formdata['form9'];
    			$info['form10'] = $formdata['form10'];
    			$info['form11'] = $formdata['form11'];
    			$info['form12'] = $formdata['form12'];
    			$info['form13'] = $formdata['form13'];
    			$info['form14'] = $formdata['form14'];
    			$info['pics'] = $formdata['pics'];
    			$info['status'] = 1;
		    	$info['createtime'] = time();
			}
			if($formdata['id']){
				Db::name('member_apply')->where('aid',aid)->where('mid',mid)->where('id',$formdata['id'])->update($info);
			}else{
				$bid = Db::name('member_apply')->insertGetId($info);
			}
			if ($formdata['status']==1) {
			    return $this->json(['status'=>2,'msg'=>'提交成功,请等待审核']);
			} else {
			    return $this->json(['status'=>1,'msg'=>'提交成功,请上传支付凭证']);
			}
		}
		$info = Db::name('member_apply')->where('aid',aid)->where('mid',mid)->find();
		if($info && $info['status']==4){
			return $this->json(['status'=>2,'msg'=>'您已成功申请']); 
		}
		$clist = Db::name('business_category')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$rdata = [];
        $rdata['title'] = '申请入驻';
		$rdata['clist'] = $clist;
		$rdata['info'] = $info ? $info : [];
		return $this->json($rdata);
	}
	
	public function bank(){
		$type = input('param.type');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		$datalist = Db::name('member_bank')->where($where)->order('id desc')->select()->toArray();
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function bankadd(){
		$type = input('param.type');
		if(request()->isPost()){
			$post = input('post.');
			if(!preg_match("/^1[3456789]\d{9}$/", $post['phoneNo'])){
				return $this->json(['status'=>0,'msg'=>'手机号格式错误']);
			}
			$data = array();
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['userName'] = $post['userName'];
			$data['certificateNo'] = $post['certificateNo'];
			$data['phoneNo'] = $post['phoneNo'];
			$data['cardNo'] = $post['cardNo'];
			$data['creditFlag'] = $post['creditFlag'];
			$data['checkNo'] = $post['checkNo'];
			$data['checkExpiry'] = str_replace('-','',$post['checkExpiry']);
			$data['createtime'] = time();
			
			ll($data,'auto_day');
			
			
			if($post['id']){
				Db::name('member_bank')->where('id',$post['id'])->update($data);
			}else{
				Db::name('member_bank')->insert($data);
			}
			return $this->json(['status'=>1,'msg'=>'保存成功']);
		}
		if(input('param.id')){
			$id = input('param.id/d');
			$bank = Db::name('member_bank')->where('aid',aid)->where('mid',mid)->where('id',$id)->find();
    		require_once (ROOT_PATH. '/app/common/api/sdpay.class.php');
    		$config = include(ROOT_PATH.'/app/common/api/config.php');
          	$aop = new \sdpay();
            $notify_url = PRE_URL.'/notify.php';
            $apiMap = array( 
                'method' => 'sandPay.fundPay.queryBindInfo',
                'url'    => '/fundPay/queryBindInfo',
                'productId' => '00000018',
                'custom'   => false  
            );
            $body = array(
                'applyNo'=>$bank['applyNo'],
            );
            $ret = $aop->request($apiMap,$body);
            $verifyFlag = $aop->verify($ret['data'], $ret['sign']);  
            $data=json_decode($ret['data'],true);
            
            
			if ($data['body']['sdMsgNo']) {
                Db::name('member_bank')->where('id',$id)->update(['sdMsgNo'=>$data['body']['sdMsgNo']]);
			}
		
		}else{
			$bank = [];
		}
		return $this->json(['status'=>1,'data'=>$bank]);
	}
	//删除地址
	public function bankdel(){
		$id = input('param.id/d');
		$rs = Db::name('member_bank')->where('aid',aid)->where('mid',mid)->where('id',$id)->delete();
		if($rs){
			return $this->json(['status'=>1,'msg'=>'删除成功']);
		}else{
			return $this->json(['status'=>0,'msg'=>'删除失败']);
		}
	}
	
	
	
	
	
	
	
	
	public function getbanklist() {
		$userinfo = Db::name('member')->where('id',mid)->find();
	
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['bankList'] =Db::name('member_bank')->where('aid',aid)->where('mid',mid)->select()->toArray();
		return $this->json($rdata);
	}
	//商品海报

	//获取表单类型
	public function getcategory(){
		$this->checklogin();
		$post = input('post.');
		$where[] = ['aid','=',aid];
		$where[] = ['id','=',1];
		$form = Db::name('member_set')->where($where)->find();
		$form['content'] =  json_decode($form['content'],true);
		$info = Db::name('member_set_log')->where('formid',$form['id'])->where('mid',mid)->find();
		return $this->json(['status'=>1,'data'=>$form,'info'=>$info]);
	}
	public function statistics(){
        $set = Db::name('admin_set')->where('aid', aid)->field('credit1_money')->find();
        $list = Db::name('member_credit1_uplog')->where('aid',aid)->order('createtime asc')->limit(10)->select()->toArray();
        $categories=[];
        $data=[];
       	foreach($list as $key=>$v){
			$categories[] = date("m-d", $v['createtime']);
			$data[] =  $v['after'];
		}
       
        $line = [];
		$line['categories'] = $categories;
		$line['series'] =[['name'=>t('credit1'),'data'=>$data]];
		
		$rdata = [];
		$rdata['title'] = '近10周趋势图';
		$rdata['line'] = $line;
		$rdata['credit1_money'] = $set['credit1_money'];
        return $this->json($rdata);
	}

	public function out($aid,$mid,$credit,$s=0) {
		$out = Db::name('member_'.$credit.'log')->where('aid',$aid)->where('mid',$mid)->where($credit,'<',0)->sum($credit);
		return $out;
	}
	public function getuserinfo() {
	    
		$userinfo = Db::name('member')->where('id',mid)->find();
		$credits= [];
		$credits['credit1']= array('in' =>$userinfo['credit1'],'out' =>self::out(aid,mid,'credit1'));
		$credits['credit2']= array('in' =>$userinfo['credit2'],'out' =>self::out(aid,mid,'credit2'));
		$credits['credit3']= array('in' =>$userinfo['credit3'],'out' =>self::out(aid,mid,'credit3'));
		$credits['credit4']= array('in' =>$userinfo['credit4'],'out' =>self::out(aid,mid,'credit4'));
		$credits['score']= array('in' =>$userinfo['score'],'out' =>self::out(aid,mid,'score'));
		$credits['credit5']= array('in' =>$userinfo['credit5'],'out' =>self::out(aid,mid,'credit5'));
		$parent = Db::name('member')->field('id,tel,nickname')->where('id',$userinfo['pid'])->find();
		$rdata = [];
		$set = Db::name('admin_set')->where('aid',aid)->field('name')->find();
		$rdata['set'] = $set;
		$rdata['credits'] = $credits;
		$rdata['userinfo'] = $userinfo;
		return $this->json($rdata);
	}
	public function score_log(){
		$st = input('param.st');
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
		$pernum = 20;
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		if($st == 1)  $where[] = ['score','>',0];
		if($st == 2)  $where[] = ['score','<',0];
		$datalist = Db::name('member_scorelog')->field("id,score,`after`,from_unixtime(createtime) createtime,remark")->where($where)->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = [];
		foreach($datalist as $k=>$v){
			if(strpos($v['remark'],'商家充值，') === 0){
				$datalist[$k]['remark'] = '商家充值';
			}
		}
		if($pagenum == 1){
		    $userinfo = Db::name('member')->where('aid',aid)->where('id',mid)->field('score')->find();
		//	$canwithdraw = Db::name('admin_set')->where('aid',aid)->value('credit3_withdraw');
			$userinfo['scorein']= Db::name('member_scorelog')->where('aid',aid)->where('mid',mid)->where('score','>',0)->sum('score');
			$userinfo['scoreout']= Db::name('member_scorelog')->where('aid',aid)->where('mid',mid)->where('score','<',0)->sum('score');
			$score_transfer = Db::name('admin_set')->where('aid',aid)->value('score_transfer');
		}
		return $this->json(['status'=>1,'data'=>$datalist,'canwithdraw'=>$canwithdraw,'userinfo'=>$userinfo,'score_transfer'=>$score_transfer]);
	}

	/**********************************************************************************/
	/**************************************转增********************************************/
	//积分转送
    public function scoreTransfer()
    {
        $set = Db::name('admin_set')->where('aid',aid)->find();
        if($set['score_transfer'] != 1) {
            return $this->json(['status'=>0,'msg'=>'未开启此功能']);
        }
        if(request()->isPost()){
            $mobile = input('post.mobile');
            $mid = input('post.mid/d');
            $score = input('post.score/f');
            if ($score < 0.01){
                return $this->json(['status'=>0,'msg'=>'请输入正确的金额，最小金额为：0.01']);
            }
            // if ($score%100!=0){
            //     return $this->json(['status'=>0,'msg'=>'金额必须输入100的倍数']);
            // }
            
            if (input('?post.mobile')) {
                $member = Db::name('member')->where('aid', aid)->where('tel', $mobile)->find();
            }
            if (input('?post.mid')) {
                $member = Db::name('member')->where('aid', aid)->where('id', $mid)->find();
            }
            if(!$member) return $this->json(['status'=>0,'msg'=>'未找到该'.t('会员')]);
            $user_id = $member['id'];

            if ($user_id == mid) {
                return $this->json(['status'=>0,'msg'=>'不能转账给自己']);
            }
            if($set['score_transfer_range'] == 1) {
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
            $toscore = round($score*(100+$set['score_transfer_fee'])*0.01,2);
            if ($toscore > $this->member['score']){
                return $this->json(['status'=>0,'msg'=>'您的'.t('积分').'不足']);
            }
            //验证支付密码
            $pwd_check = $set['score_transfer_pwd'];
            if($pwd_check){
                if(!$this->member['paypwd']){
                    return $this->json(['status'=>0,'msg'=>'请先设置支付密码','set_paypwd'=>1]);
                }
                $pay_pwd = input('paypwd')?:'';
                if(!\app\commons\Member::checkPayPwd($this->member,$pay_pwd )){
                    return $this->json(['status'=>0,'msg'=>'支付密码输入错误']);
                }
            }
 
			$rs = \app\commons\Member::addscore(aid,mid,$toscore * -1, sprintf("转账给：%s",$member['nickname']));
            if ($rs['status'] == 1) {
                \app\commons\Member::addscore(aid,$user_id,$score,sprintf("来自%s的转账", $this->member["nickname"]));
            }else{
				 return $this->json(['status'=>0, 'msg' => '转账失败']);
			}
            return $this->json(['status'=>1, 'msg' => '转账成功', 'url'=>m_url('my/usercenter').'/aid/'.aid]);
        }
        if($this->member['paypwd']==''){
			$userinfo['haspwd'] = 1;
		}else{
			$userinfo['haspwd'] = 0;
		}
		$rdata['userinfo'] = $userinfo;
        $rdata['paycheck'] = $set['score_transfer_pwd'] ? true : false;
        $rdata['paytype'] = $set['score_transfer_type'];
        $rdata['score_transfer_fee'] = $set['score_transfer_fee'];
        $rdata['status'] = 1;
        $rdata['myscore'] = $this->member['score'];
        $rdata['scoreList'] = [];//可选金额列表
        return $this->json($rdata);
        
    }

}