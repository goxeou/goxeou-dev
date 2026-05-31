<?php


// +----------------------------------------------------------------------
// | 门店管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Mendian extends Common
{
	//列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			$count = 0 + Db::name('mendian')->where($where)->count();
			$data = Db::name('mendian')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
        return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('mendian')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
			}else{
			$info = array('id'=>'');
            }
		
		$mendian_upgrade=false;
		View::assign('mendian_upgrade',$mendian_upgrade);
		View::assign('bid',bid);
		View::assign('info',$info);
        return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');

		if($info['latitude']){
			//通过坐标获取省市区
            $mapqq = new \app\commons\MapQQ();
            $address = $mapqq->locationToAddress($info['latitude'],$info['longitude']);
            if($address['status'] == 1){
                $info['area'] = $address['area'];
                $info['province'] = $address['province'];
                $info['city']     = $address['city'];
                $info['district'] = $address['district'];
            }
		}
		if(getcustom('mendian_hexiao_givemoney')){
			if(bid>0){
				unset($info['business_canuse']);
			}
		}
		if($info['id']){
			Db::name('mendian')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑门店'.$info['id']);
		}else{
            $info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('mendian')->insertGetId($info);
			\app\commons\System::plog('添加门店'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('mendian')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('修改门店状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('mendian')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除门店'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//列表
    public function chooseindex(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			if(bid==0){
				$bids = Db::name('business')->where('aid',aid)->where('isplatform_auth',1)->where('status',1)->column('id');	
				array_push($bids,bid);
				$where[] = ['bid','in',$bids];
			}else{
				$where[] = ['bid','=',bid];
			}
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			$count = 0 + Db::name('mendian')->where($where)->count();
			$data = Db::name('mendian')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}

		return View::fetch();
    }

	
	//门店列表2
	public function index2(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			if(input('param.name')) $where[] = ['name|xqname|tel','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			if(input('param.levelid')) $where[] = ['levelid','=',input('param.levelid')];
			if(input('param.groupid')) $where[] = ['groupid','=',input('param.groupid')];


			$count = 0 + Db::name('mendian')->where($where)->count();
			$data = Db::name('mendian')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$level = Db::name('mendian_level')->where('aid',aid)->column('name','id');
			$group = Db::name('mendian_group')->where('aid',aid)->column('name','id');
			foreach($data as &$d){
				$member = Db::name('member')->where('id',$d['mid'])->find();
				$d['nickname'] = $member['nickname'];
				$d['tynum'] =0 + Db::name('member')->where('aid',aid)->where('pid',$d['mid'])->count();
				$d['levelname'] = $level[$d['levelid']]?$level[$d['levelid']]:'';
				$d['groupname'] = $group[$d['groupid']]?$group[$d['groupid']]:'';
				$pmendia='';
				$tjtotal=0;
				if($d['pid']>0){
					$pmendian =  Db::name('mendian')->field('name')->where('aid',aid)->where('mid',$d['pid'])->find();
				}
				$tjtotal =0+ Db::name('mendian')->field('name')->where('aid',aid)->where('pid','<>',0)->where('pid',$d['mid'])->count();
				$d['parentname'] = $pmendian['name']?$pmendian['name']:'暂无上级';
				$d['tjtotal'] = $tjtotal;
				

				//打款金额
				$d['dkmoney'] =  Db::name('mendian_withdrawlog')->where('aid',aid)->where('mdid',$d['id'])->sum('txmoney');
				if($d['mid']){
					$member = Db::name('member')->field('headimg')->where('aid',aid)->where('id',$d['mid'])->find();
					$d['pic'] = $member['headimg'];
				}

			}		
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$levelArr = Db::name('mendian_level')->where('aid',aid)->order('sort,id')->column('name','id');
		$groupArr = Db::name('mendian_group')->where('aid',aid)->order('sort,id')->column('name','id');
		View::assign('levelarr',$levelArr);
		View::assign('grouparr',$groupArr);
		return View::fetch();
	}
    public function index23(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			$where[] = ['type','=',1];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			$count = 0 + Db::name('mendian')->where($where)->count();
			$data = Db::name('mendian')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				
				if($v['mid']){
					$member = Db::name('member')->where('id',$v['mid'])->find();
					$data[$k]['nickname'] = $member['nickname'];
					$data[$k]['headimg'] = $member['headimg'];
				}else{
					$data[$k]['nickname'] = '';
					$data[$k]['headimg'] = '';
				}
			}
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
        return View::fetch();
    }
//编辑
	public function edit2(){
		if(input('param.id')){
			$info = Db::name('mendian')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
			if($info){
				$uinfo = Db::name('admin_user')->where('aid',aid)->where('mdid',$info['id'])->find();
			}else{
				$uinfo = [];
			}
		}else{
			$info = array('id'=>'');
			$uinfo = [];
        }
        View::assign('uinfo',$uinfo);
		View::assign('bid',bid);
		View::assign('info',$info);
        return View::fetch();
	}
	public function save2(){
		$info = input('post.info/a');
    	$uinfo = input('post.uinfo/a');
        $member = Db::name('member')->where('id',$info['mid'])->find();
	    if(empty($member)){
			return json(['status'=>0,'msg'=>'该会员不存在']);
		}
    	$hasbusiness = Db::name('mendian')->where('mid',$info['mid'])->find();
		if($hasbusiness && $hasbusiness['id']!= $info['id']){
			return json(['status'=>0,'msg'=>'该会员已绑定过门店']);
		}
		$hasun = Db::name('admin_user')->where('id','<>',$uinfo['id'])->where('un',$uinfo['un'])->find();
		if($hasun){
			return json(['status'=>0,'msg'=>'该账号已存在']);
		}
		
		if($info['latitude']){
			//通过坐标获取省市区
			$url = 'https://apis.map.qq.com/ws/geocoder/v1/?key=ABLBZ-4BIKU-GFTVB-BK7IK-OLQ35-QCBFF&location='.$info['latitude'].','.$info['longitude'];
			$res = json_decode(request_get($url),true);
			//dump($res);
			if($res && $res['status']==0){
				$address_component = $res['result']['address_component'];
				$info['area'] = $address_component['province'].','.$address_component['city'].','.$address_component['district'];
					$info['province'] = $address_component['province'];
					$info['city']     = $address_component['city'];
					$info['district'] = $address_component['district'];
			}
		}
		if($info['id']){
			Db::name('mendian')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
			
			$hasun = Db::name('admin_user')->where('mdid',$info['id'])->find();
			if ($hasun) {
			   	if($uinfo['pwd']!=''){
    				$uinfo['pwd'] = md5($uinfo['pwd']);
    			}else{
    				unset($uinfo['pwd']);
    			}
    			$uinfo['mid'] = $info['mid'];
    			Db::name('admin_user')->where('aid',aid)->where('id',$uinfo['id'])->update($uinfo);
			}else {
			    $uinfo['aid'] = aid;
    	     	$uinfo['mdid'] = $info['id'];
    	     	$uinfo['un'] = $uinfo['un'];
    			$uinfo['pwd'] = md5($uinfo['pwd']);
    			$uinfo['auth_type'] = 0;
    			$uinfo['auth_data'] = '{"1":"ShopOrder\/index,ShopOrder\/*"}';
    			$uinfo['wxauth_data'] = '{"2":"order"}';
    			$uinfo['notice_auth_data'] = '["tmpl_orderconfirm","tmpl_orderpay"]';
    			$uinfo['hexiao_auth_data'] = '["shop"]';
    			$uinfo['createtime'] = time();
    			$uinfo['mid'] = $info['mid'];
    			$uinfo['random_str'] = random(16);
    			Db::name('admin_user')->insert($uinfo);
			}
			Db::name('member')->where('aid',aid)->where('mdid',$info['id'])->update(['mdid'=>0]);
			Db::name('member')->where('aid',aid)->where('id',$info['mid'])->update(['mdid'=>$info['id']]);
			\app\commons\System::plog('编辑实体门店'.$info['id']);
		}else{
            $info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('mendian')->insertGetId($info);
			Db::name('member')->where('aid',aid)->where('id',$info['mid'])->update(['mdid'=>$id]);
			$uinfo['aid'] = aid;
	     	$uinfo['mdid'] = $id;
			$uinfo['pwd'] = md5($uinfo['pwd']);
			$uinfo['auth_data'] = '{"1":"ShopOrder\/index,ShopOrder\/*","41":"ScoreshopOrder\/index,ScoreshopOrder\/*"}';
			$uinfo['wxauth_data'] = '{"2":"order"}';
			$uinfo['notice_auth_data'] = '["tmpl_orderconfirm","tmpl_orderpay"]';
			$uinfo['hexiao_auth_data'] = '["shop"]';
			$uinfo['createtime'] = time();
			$uinfo['mid'] = $info['mid'];
			$uinfo['random_str'] = random(16);
			 Db::name('admin_user')->insert($uinfo);
			
			\app\commons\System::plog('添加实体门店'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}


	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		$mendian = Db::name('mendian')->where('aid',aid)->where('id',$id)->find();
		if(!$mendian) return json(['status'=>0,'msg'=>'信息不存在']);
	
		if($st == 1){
			Db::name('mendian')->where('aid',aid)->where('id',$id)->update(['check_status'=>$st,'status'=>1]);
			if(!getcustom('mendian_upgrade')){
				$user = Db::name('admin_user')->where('aid',aid)->where('mdid',$id)->find();
				if($user){
					Db::name('admin_user')->where('aid',aid)->where('id',$user['id'])->update(['status'=>1]);
				}
			}
			//修改会员等级
			}else{
			Db::name('mendian')->where('aid',aid)->where('id',$id)->update(['check_status'=>$st,'reason'=>$reason]);
			if(!getcustom('mendian_upgrade')){
				$user = Db::name('admin_user')->where('aid',aid)->where('mdid',$id)->find();
				if($user){
					Db::name('admin_user')->where('aid',aid)->where('id',$user['id'])->update(['status'=>0]);
				}
			}
		}
		//审核结果通知
		$tmplcontent = [];
		$tmplcontent['first'] = ($st == 1 ? '恭喜您的申请入驻通过' : '抱歉您的提交未审核通过');
		$tmplcontent['remark'] = ($st == 1 ? '' : ($reason.'，')) .'请点击查看详情~';
		$tmplcontent['keyword1'] = $mendian['name'].'申请';
		$tmplcontent['keyword2'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontent['keyword3'] = date('Y年m月d日 H:i');
        $tempconNew = [];
        $tempconNew['thing9'] = $mendian['name'].'申请';
        $tempconNew['thing2'] = ($st == 1 ? '已通过' : '未通过');
        $tempconNew['time3'] = date('Y年m月d日 H:i');
		$rs = \app\commons\Wechat::sendtmpl(aid,$mendian['mid'],'tmpl_shenhe',$tmplcontent,m_url('activity/yuyue/apply'),$tempconNew);
		//订阅消息
		$tmplcontent = [];
		$tmplcontent['thing8'] = $mendian['name'].'申请';
		$tmplcontent['phrase2'] = ($st == 1 ? '已通过' : '未通过');
		$tmplcontent['thing4'] = $st == 1?'您的申请未通过':'您的申请已通过';
		$rs = \app\commons\Wechat::sendwxtmpl(aid,$mendian['mid'],'tmpl_shenhe',$tmplcontent,'activity/yuyue/apply','');
		return json(['status'=>1,'msg'=>'操作成功']);
	}



	public function choosemendian(){
        if(request()->isPost()){
            $id = input('param.id/d');
            $mendian = Db::name('mendian')->where('aid',aid)->where('id',$id)->find();
            return json(['status'=>1,'data'=>$mendian]);
        }
        return View::fetch();
    }

	public function getpaycode(){
		if(request()->isPost()){
            $id = input('param.id/d');
			$bid = input('param.bid/d');
			$type = input('param.type');
			$set = 	Db::name('admin_set')->field('paycode_bgpic')->where('aid',aid)->find();
			$page = 'pages/maidan/pay';
			$scene = 'mdid_'.$id.'-'.'bid_'.$bid;
			//var_dump($scene);
			$mendian = Db::name('mendian')->field('paycodepic')->where('aid',aid)->where('id',$id)->update(['paycodepic'=>$posterurl]);
			if(!$mendian['paycodepic']){
				$posterurl = $this->_getposter(aid,$type,$set['paycode_bgpic'],$page,$scene);
				if($posterurl){
					Db::name('mendian')->where('aid',aid)->where('id',$id)->update(['paycodepic'=>$posterurl]);
				}
			}else{
				$posterurl = $mendian['paycodepic'];
			}
            return json(['status'=>1,'paycodepic'=>$posterurl]);
        }
	}

	//生成付款码
	function _getposter($aid,$type,$poster_bg,$page,$scene){
		set_time_limit(0);
		@ini_set('memory_limit', -1);
		if(strpos($poster_bg,'http') ===false){
			$poster_bg = PRE_URL.$poster_bg;
		}
		$bg = imagecreatefromstring(request_get($poster_bg));
		if($bg){
			$bgwidth = imagesx($bg);
			$bgheight = imagesy($bg);
			if($bgheight/$bgwidth > 1.92) $bgheight = floor($bgwidth * 1.92);
			$target = imagecreatetruecolor($bgwidth, $bgheight);
			imagecopy($target, $bg, 0, 0, 0, 0,$bgwidth,$bgheight);
			imagedestroy($bg);
		}else{
			$bgwidth = 680;
			$bgheight = 1080;
			$target = imagecreatetruecolor(680, 1080);
			imagefill($target,0,0,imagecolorallocate($target, 255, 255, 255));
		}
		$huansuan = $bgwidth/340;
		//$bgwidth = imagesx($bg);
		//$bgheight = imagesy($bg);
		$font = ROOT_PATH."static/fonts/msyh.ttf";
		
		$qrwidth = $bgwidth/2;
		$qrleft = $bgwidth/4;
		$qrtop = $bgheight/2 - $qrwidth/2;
		if ($type == 'qrwx'){
			$data = array();
			$data['scene'] = $scene;
			$data['page'] = ltrim($page,'/');
            $errmsg = \app\commons\Wechat::getQRCode($aid,'wx',$data['page'],$data['scene'],bid,false);
            $res = $errmsg['buffer'];//图片 Buffer

            if($errmsg['status'] != 1){
                if($errmsg['errcode'] == 41030){
                    echojson(array('status'=>0,'msg'=>'小程序发布后才能生成分享海报'));
                }else{
                    echojson(array('status'=>0,'msg'=>$errmsg['errmsg']));
                }
            }
            $img = imagecreatefromstring($res);
            imagecopyresampled($target, $img, $qrleft, $qrtop, 0, 0,$qrwidth,$qrwidth,imagesx($img), imagesy($img));
		} else if ($type == 'qrmp') {
			$qrcode = createqrcode(PRE_URL .'/h5/'.$aid.'.html#'.$page.'?scene='.$scene.'&t='.time());
			$img = imagecreatefromstring(request_get($qrcode));
			imagecopyresampled($target, $img, $qrleft, $qrtop, 0, 0, $qrwidth,$qrwidth,imagesx($img), imagesy($img));
		}
	
		$url = '/upload/'.$aid.'/'.date('Ym/d_His').rand(1000000,9999999).'.jpg';
		$filepath = ROOT_PATH.ltrim($url,'/');
		mk_dir(dirname($filepath));
		imagejpeg($target,$filepath,100);
		return PRE_URL.$url;
	}


    public function editGroup(){
        }

	public function editLevel(){
        }
	//加佣金
	public function addcommission(){
		$id = input('post.id/d');
		$commission = floatval(input('post.commission'));
		$remark = input('post.remark');
        $actionname = '增加';
		if($commission == 0){
			return json(['status'=>0,'msg'=>'请输入'.t('佣金').'金额']);
		}
        if($commission < 0) $actionname = '扣除';
		$rs = \app\commons\Mendian::addmoney(aid,$id,$commission,t('后台修改').'：'.$remark,0);
		\app\commons\System::plog('给门店'.$id.$actionname.'佣金'.$commission);
		if($rs['status']==0) return json($rs);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//导出
	public function excel(){
		set_time_limit(0);
		ini_set('memory_limit', '2000M');
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'sort desc,id';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		if(input('param.name')) $where[] = ['name|xqname|tel','like','%'.input('param.name').'%'];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
		if(input('param.levelid')) $where[] = ['levelid','=',input('param.levelid')];
		if(input('param.groupid')) $where[] = ['groupid','=',input('param.groupid')];
		$data = Db::name('mendian')->where($where)->order($order)->select()->toArray();
		
		$title = array('ID','小区名称',t('门店').'名称',t('门店').'等级',t('门店').'分组','可提现佣金','上级'.t('门店'),'当前团员数量','电话','地址','申请时间','审核状态');
		$level = Db::name('mendian_level')->where('aid',aid)->column('name','id');
		$group = Db::name('mendian_group')->where('aid',aid)->column('name','id');
		$newdata = [];
		foreach($data as $k=>$vo){

			$tynum =0 + Db::name('member')->where('aid',aid)->where('mdid',$vo['id'])->count();
			$levelname = $level[$vo['levelid']]?$level[$vo['levelid']]:'';
			$groupname = $group[$vo['groupid']]?$group[$vo['groupid']]:'';
			$tjtotal=0;
			if($vo['pid']>0){
				$pmendian =  Db::name('mendian')->field('name')->where('aid',aid)->where('mid',$vo['pid'])->find();
				$tjtotal = Db::name('mendian')->field('name')->where('aid',aid)->where('pid',$vo['mid'])->count();
			}
			$parentname = $pmendian['name']?$pmendian['name']:'暂无上级';

				
			$status='';
			if($vo['check_status']==0){
				$status = '未审核';
			}elseif($vo['status']==2){
				$status = '已驳回';
			}elseif($vo['status']==1){
				$status = '已通过';
			}
			$newdata[] = [
				$vo['id'],
				$vo['xqname'],
				$vo['name'],
				$levelname,
				$groupname,
				$vo['money'],
				$parentname,
				$tjtotal,
				$vo['tel'],
				$vo['address'],
				date('Y-m-d H:i:s',$vo['createtime']),
				$status,
			];
		
		}
		$this->export_excel($title,$newdata);
	}
	

}
