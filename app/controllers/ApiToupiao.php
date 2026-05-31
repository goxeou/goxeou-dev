<?php


namespace app\controllers;
use think\facade\Db;
class ApiToupiao extends ApiCommon
{
	public function index(){
		$id = input('param.id/d');
		$info = Db::name('toupiao')->where('id',$id)->find();
		if(!$info) return json(['status'=>-4,'msg'=>'活动不存在']);

		$info['joinnum'] = Db::name('toupiao_join')->where('hid',$id)->where('status',1)->count();
		$info['helpnum'] = Db::name('toupiao_join')->where('hid',$id)->where('status',1)->sum('helpnum');
		$info['color1rgb'] = implode(',',array_values(hex2rgb($info['color1'])));
		$info['color2rgb'] = implode(',',array_values(hex2rgb($info['color2'])));
		$info['readcount'] = $info['readcount'] + 1;
		
		$where = [];
		$where[] = ['hid','=',$id];
		$where[] = ['status','=',1];
		if(input('param.keyword')){
			$where[] = ['name|number','like','%'.input('param.keyword').'%'];
		}
		$datalist = Db::name('toupiao_join')->where($where)->order(Db::raw('rand()'))->select()->toArray();
		Db::name('toupiao')->where('id',$id)->inc('readcount')->update();
		return $this->json(['status'=>1,'info'=>$info,'nowtime'=>time(),'datalist'=>$datalist]);
	}
	public function detail(){
		$id = input('param.id/d');
		$detail = Db::name('toupiao_join')->where('id',$id)->where('aid',aid)->where('status',1)->find();
		if(!$detail) return $this->json(['status'=>0,'msg'=>'选手不存在']);
		Db::name('toupiao_join')->where('id',$id)->inc('readcount')->update();
		$detail['readcount']++;
		$detail['createtime'] = date('Y-m-d',$detail['createtime']);
		$detail['mingci'] = 1 + Db::name('toupiao_join')->where('aid',aid)->where('status',1)->where('hid',$detail['hid'])->where("helpnum>{$detail['helpnum']} or (helpnum={$detail['helpnum']} and id < {$detail['id']})")->count();

		$detail['pics'] = $detail['pics'] ? explode(',',$detail['pics']) : [];
        $info = Db::name('toupiao')->where('id',$detail['hid'])->find();
		$info['color1rgb'] = implode(',',array_values(hex2rgb($info['color1'])));
		$info['color2rgb'] = implode(',',array_values(hex2rgb($info['color2'])));

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['detail'] = $detail;
		$rdata['info'] = $info;
		$rdata['nowtime'] = time();
		return $this->json($rdata);
	}
    //排行榜
	public function phb(){
		$id = input('param.id/d');
		$info = Db::name('toupiao')->where('id',$id)->find();
		if(!$info) return json(['status'=>-4,'msg'=>'活动不存在']);
		
		$pagenum = input('param.pagenum') ? input('param.pagenum') : 1;
		
		$where = [];
		$where[] = ['hid','=',$id];
		$where[] = ['status','=',1];
		if(input('param.keyword')){
			$where[] = ['name|number','like','%'.input('param.keyword').'%'];
		}
		$datalist = Db::name('toupiao_join')->where($where)->order('helpnum desc,id')->page($pagenum,20)->select()->toArray();

		$info['joinnum'] = Db::name('toupiao_join')->where('hid',$id)->where('status',1)->count();
		$info['helpnum'] = Db::name('toupiao_join')->where('hid',$id)->where('status',1)->sum('helpnum');
		$info['color1rgb'] = implode(',',array_values(hex2rgb($info['color1'])));
		$info['color2rgb'] = implode(',',array_values(hex2rgb($info['color2'])));
		$info['readcount'] = $info['readcount'] + 1;
		
		//Db::name('toupiao')->where('id',$id)->inc('readcount')->update();
		return $this->json(['status'=>1,'info'=>$info,'nowtime'=>time(),'datalist'=>$datalist]);
	}
	//报名
	public function baoming(){
		$this->checklogin();
		$hid = input('param.id');
		$toupiao = Db::name('toupiao')->where('id',$hid)->find();
        if(empty($toupiao)) return json(['status'=>0,'msg'=>'活动不存在']);
		if($toupiao['canapply'] == 0) return json(['status'=>0,'msg'=>'未开启报名']);
        $gettj = explode(',',$toupiao['gettj']);
        $member = Db::name('member')->where('aid',aid)->where('id',$this->mid)->find();
        if(!in_array('-1',$gettj) && !in_array($member['levelid'],$gettj)){ //不是所有人
            if(in_array('0',$gettj)){ //关注用户才能领
                if($member['subscribe']!=1){
                    $appinfo = \app\commons\System::appinfo(aid,'mp');
                    return $this->json(['status'=>0,'msg'=>'请先关注'.$appinfo['nickname'].'公众号']);
                }
            }else{
                return $this->json(['status'=>0,'msg'=>'您没有参与权限']);
            }
        }
		if(request()->isPost()){
			$info = Db::name('toupiao_join')->where('aid',aid)->where('hid',$hid)->where('mid',mid)->find();
			$postinfo = input('post.');
            unset($postinfo['id']);
            //验证范围
            unset($postinfo['latitude'],$postinfo['longitude']);
			if($info){
				if($info['status']==2){
					$postinfo['status'] = 0;
				}
				Db::name('toupiao_join')->where('id',$info['id'])->update($postinfo);
				if($info['status']==2){
					return $this->json(['status'=>1,'msg'=>'提交成功,请等待审核']);
				}else{
					return $this->json(['status'=>1,'msg'=>'修改成功']);
				}
			}else{
				$postinfo['aid'] = aid;
				$postinfo['createtime'] = time();
				$postinfo['mid'] = mid;
				$postinfo['hid'] = $hid;
				if($toupiao['apply_check']==1){
					$postinfo['status'] = 0;
				}else{
					$postinfo['status'] = 1;
					$lastnumber =Db::name('toupiao_join')->where('aid',aid)->where('hid',$toupiao['id'])->order('number desc')->value('number');
					$thisnumber = intval($lastnumber) + 1;
					if($thisnumber < 100) $thisnumber = sprintf("%03d",$thisnumber);
					$postinfo['number'] = $thisnumber;
				}
				Db::name('toupiao_join')->insert($postinfo);
				if($toupiao['apply_check']==1){
					return $this->json(['status'=>1,'msg'=>'提交成功,请等待审核']);
				}else{
					return $this->json(['status'=>1,'msg'=>'报名成功']);
				}
			}
		}
		$rdata = [];
		$rdata['status'] = 1;
		$rdata['toupiao'] = $toupiao;
		$rdata['info'] = Db::name('toupiao_join')->where('aid',aid)->where('hid',$hid)->where('mid',mid)->find();
		//if($rdata['info']['status'] == 1){
		//	return $this->json(['status'=>-4,'msg'=>'您已报名参与过了']);
		//}
		if(!$rdata['info']) $rdata['info'] = [];
		return $this->json($rdata);
	}
    //投票动作
	public function toupiao(){
		$this->checklogin();
		$id = input('param.id/d');
		$join = Db::name('toupiao_join')->where('id',$id)->where('aid',aid)->find();
		if(!$join) return json(['status'=>0,'msg'=>'选手不存在']);
		$hid = $join['hid'];
		$info = Db::name('toupiao')->where('id',$hid)->find();
		if(!$info) return json(['status'=>0,'msg'=>'活动不存在']);
		if($info['status'] == 0) return json(['status'=>0,'msg'=>'活动未开启']);
		if($info['starttime'] > time()) return json(['status'=>0,'msg'=>'活动未开始']);
		if($info['endtime'] < time()) return json(['status'=>0,'msg'=>'活动已结束']);
        $starttime = strtotime(date('Y-m-d'));
        $endtime = $starttime + 86400;
        $daycount = Db::name('toupiao_help')->where('aid', aid)->where('hid', $hid)->where('mid', mid)->where('createtime', '>=', $starttime)->count();
        if ($daycount >= $info['per_daycount']) return $this->json(['status' => 0, 'msg' => '每天最多只能投' . $info['per_daycount'] . '次']);
        if ($info['per_allcount'] > 0) {
            $allcount = Db::name('toupiao_help')->where('aid', aid)->where('hid', $hid)->where('mid', mid)->count();
            if ($allcount >= $info['per_allcount']) return $this->json(['status' => 0, 'msg' => '每人最多只能投' . $info['per_allcount'] . '次']);
        }
        $helpNum = 1;
        $hashelp = Db::name('toupiao_help')->where('aid', aid)->where('joinid', $id)->where('mid', mid)->where('createtime', '>=', $starttime)->count('id');
//            if ($hashelp) return $this->json(['status' => 0, 'msg' => '您今天已经帮TA投过了']);
        if($hashelp>=$helpNum){
            $msg = '您对该选手的投票次数已用完';
            return $this->json(['status'=>0,'msg'=>$msg]);
        }
        $needPay = 0;
        if(request()->isPost()) {
            if (isset($info['toupiaotj'])) {
                $toupiaotj = explode(',', $info['toupiaotj']);
                $member = Db::name('member')->where('aid', aid)->where('id', $this->mid)->find();
                if (!in_array('-1', $toupiaotj) && !in_array($member['levelid'], $toupiaotj)) { //不是所有人
                    if (in_array('0', $toupiaotj)) { //关注用户
                        if ($member['subscribe'] != 1) {
                            $appinfo = \app\commons\System::appinfo(aid, 'mp');
                            return $this->json(['status' => 0, 'msg' => '请先关注' . $appinfo['nickname'] . '公众号']);
                        }
                    } else {
                        return $this->json(['status' => 0, 'msg' => '您没有参与权限']);
                    }
                }
            }
            $tel = '';
            if ($info['help_check'] == 1) { //图形验证码
                $captcha = trim(input('post.captcha'));
                if (strtolower($captcha) != strtolower(cache($this->sessionid . '_captcha'))) {
                    return $this->json(['status' => 0, 'msg' => '验证码错误']);
                }
            }
            if ($info['help_check'] == 2) { //短信验证码
                $smscode = input('post.smscode');
                $tel = input('post.smstel');
                if (md5($tel . '-' . $smscode) != cache($this->sessionid . '_smscode') || cache($this->sessionid . '_smscodetimes') > 5) {
                    cache($this->sessionid . '_smscodetimes', cache($this->sessionid . '_smscodetimes') + 1);
                    return $this->json(['status' => 0, 'msg' => '短信验证码错误']);
                }
            }

            $data = [];
            $data['aid'] = aid;
            $data['mid'] = mid;
            $data['headimg'] = $this->member['headimg'];
            $data['nickname'] = $this->member['nickname'];
            $data['tel'] = $tel;
            $data['hid'] = $hid;
            $data['joinid'] = $id;
            $data['createtime'] = time();
            Db::name('toupiao_help')->insert($data);
            Db::name('toupiao_join')->where('id', $id)->inc('helpnum')->inc('readcount')->update();
            //消耗积分的 需要扣除积分
            return json(['status' => 1, 'msg' => '投票成功']);
        }else{
            $todayTimeS = strtotime(date('Y-m-d 00:00:00',time()));
            $isconfirm = 1;//是否已确认，0未确认需要弹窗提示确认
            return $this->json(['status'=>1,'isconfirm'=>$isconfirm,'msg'=>$msg]);
        }
	}
	
}