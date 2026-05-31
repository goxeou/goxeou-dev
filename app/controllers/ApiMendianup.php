<?php
// +----------------------------------------------------------------------
// | 门店申请     custom_file(mendian_apply)
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\Db;
class ApiMendianup extends ApiCommon{
	public function initialize(){
		parent::initialize();
		$this->checklogin();
	}
	//门店申请 20240115
	public function apply(){
		$mendian_up=false;
		if(getcustom('mendian_upgrade')){
			$admin = Db::name('admin')->where('id',aid)->field('mendian_upgrade_status')->find();
			if($admin['mendian_upgrade_status']==1){
				$mendian_set = Db::name('mendian_sysset')->where('aid',aid)->field('apply_status')->find();
				$mendian_up = true;
				if(!$mendian_set['apply_status']){
					return $this->json(['status'=>2,'msg'=>'未开启门店申请','tourl'=>'pages/index/index']);		
				}
			}
		}
	
		if(request()->isPost()){
			$formdata = input('post.info/a');

			//查看是否已经存在此账号
			if(!getcustom('mendian_upgrade')){
				$user = Db::name('admin_user')->where('aid',aid)->where('un',$formdata['un'])->find();
				if($user['mid'] && $user['mid']!=mid){
					return $this->json(['status'=>0,'msg'=>'此登录账号已存在']);	
				}
				if(!$formdata['name'] || !$formdata['tel'] || !$formdata['un']){
					return $this->json(['status'=>0,'msg'=>'请将信息填写完整']);	
				}
			}
			
			if(getcustom('mendian_upgrade')){
				if(!$formdata['name'] || !$formdata['tel'] || !$formdata['xqname']){
					return $this->json(['status'=>0,'msg'=>'请将信息填写完整']);	
				}
				if($formdata['citys']=='请选择所在地'){
					return $this->json(['status'=>0,'msg'=>'请选择所在地']);	
				}
			}
			$area = explode(',', $formdata['citys']);
			$info = [];
			$info['aid'] = aid;
			$info['bid'] = $formdata['bid']?$formdata['bid']:0;
			$info['name'] = $formdata['name'];
			$info['tel'] = $formdata['tel'];
			$info['province'] = $area[0];
			$info['city'] = $area[1];
			$info['district'] = $area[2];
		
			$info['latitude'] = $formdata['latitude'];
			$info['longitude'] = $formdata['longitude'];
			$info['pic'] = $formdata['pic'];
			$info['pics'] =$formdata['pics'];
			$info['address'] = $formdata['address'];
			$info['subname'] = $formdata['subname'];
			if(getcustom('mendian_upgrade')){
				$info['xqname'] = $formdata['xqname'];
				$info['street'] = $area[3];
			}
			$info['status'] = 0;
			$info['check_status'] = 0;
			$info['createtime'] = time();
			$info['mid'] = mid;
			if(getcustom('mendian_upgrade')){
				$level =  Db::name('mendian_level')->where('aid',aid)->where('isdefault',1)->find();
				$info['levelid'] = $level['id'];
				$group =  Db::name('mendian_group')->where('aid',aid)->where('isdefault',1)->find();
				$info['groupid'] = $group['id'];
				if($this->member['pid']){
					$pmendian = Db::name('mendian')->where('mid',$this->member['pid'])->where('aid',aid)->where('status',1)->find();
					if($pmendian){
						$info['pid'] = $this->member['pid'];
					}
				}
			}
			$ordernum = \app\commons\Common::generateOrderNo(aid);
			if($formdata['id']){
				Db::name('mendian')->where('aid',aid)->where('bid',$info['bid'])->where('id',$formdata['id'])->update($info);
			}else{
				$mdid = Db::name('mendian')->insertGetId($info);
			}
			if(!getcustom('mendian_upgrade')){
				$uinfo = [];
				$uinfo['aid'] = aid;
				$uinfo['mid'] = mid;
				$uinfo['mdid'] = $mdid;
				$uinfo['un'] = $formdata['un'];;
				//$uinfo['auth_type'] = 1;
				$uinfo['pwd'] = md5($formdata['pwd']);
				$uinfo['createtime'] = time();
				if($user){
					$uinfo['isadmin'] = $user['isadmin'];
				}else{
					$uinfo['isadmin'] = 0;
				}
				$uinfo['random_str'] = random(16);
				$uinfo['status'] = 0;
				$uinfo['hexiao_auth_data'] = '{"1":"shop","2":"collage","3":"lucky_collage","4":"cycle","5":"kanjia","6":"seckill","7":"yuyue","8":"scoreshop","9":"coupon","10":"choujiang","11":"restaurant_shop","12":"restaurant_takeaway","13":"tuangou"}';
				$uinfo['wxauth_data']  = '{"2":"order","3":"finance"}';
				if($user){
					Db::name('admin_user')->where('aid',aid)->where('id',$user['id'])->update($uinfo);
				}else{
					$id = Db::name('admin_user')->insertGetId($uinfo);
				}
				
			}
			//门店入驻成功给管理员发通知
			$tmplcontent = [];
			$tmplcontent['first'] = '有门店申请成功';
			$tmplcontent['remark'] = '请登录后台，查看申请详情~';
			$tmplcontent['keyword1'] = '门店申请';
			$tmplcontent['keyword2'] = date('Y-m-d H:i');
			$tempconNew = [];
			$tempconNew['thing3'] = '门店申请';//报名名称
			$tempconNew['time5'] = date('Y-m-d H:i');//申请时间
			\app\commons\Wechat::sendhttmpl(aid,$formdata['bid'],'tmpl_formsub',$tmplcontent,'',0,$tempconNew);
			return $this->json(['status'=>1,'msg'=>'提交成功,请等待审核','tourl'=>$set['apply_url']?$set['apply_url']:'apply']);
		}
		if(mid>0){
			$mendian = Db::name('mendian')->where('aid',aid)->where('mid',mid)->find();
			if($mendian && $mendian['check_status']==1){
				//var_dump($info);
				$tourl = '/admin/index/index';
				if(getcustom('mendian_upgrade')){
					$tourl='/pagesA/mendiancenter/my';
				}
				return $this->json(['status'=>2,'msg'=>'您已成功入驻','tourl'=>$tourl]); 
			}
		}
	

		$rdata = []; 
        $rdata['title'] = '申请入驻';
		$rdata['info'] = $mendian?$mendian:[];
		$rdata['mendian_up'] = $mendian_up;
		return $this->json($rdata);
	}
	public function addhexiaouser(){
		if(request()->isPost()){
			$formdata = input('post.info/a');
			$mdid = input('post.mdid');
			$mendian = Db::name('mendian')->where('id',$mdid)->where('aid',aid)->find();
			if(!$mendian){
				return $this->json(['status'=>0,'msg'=>'门店不存在']);
			}
			$set = Db::name('mendian_sysset')->where('aid',aid)->find();
			if(!$set['addhxuser_status']){
				return $this->json(['status'=>0,'msg'=>'未开启权限']);
			}
			$hexiaouser = Db::name('mendian_hexiaouser')->where('aid',aid)->where('mdid',$mdid)->where('mid',mid)->find();
			if($hexiaouser){
				return $this->json(['status'=>0,'msg'=>'您的账号已添加核销员']);
			}
			$info = [];
			$info['aid'] = aid;
			$info['name'] = $formdata['name'];
			$info['tel'] = $formdata['tel'];
			$info['mid'] =mid;
			$info['createtime'] = time();
			$info['mdid'] = $mdid;
			$info['nickname'] = $this->member['nickname'];
			$info['headimg'] = $this->member['headimg'];

			Db::name('mendian_hexiaouser')->insertGetId($info);
			return $this->json(['status'=>1,'msg'=>'提交成功']);
		}
	}
	//商城订单
	public function shoporder(){
		$st = input('param.st');
		if(!input('?param.st') || $st === ''){
			$st = 'all';
		}
		//查看是否有核销权限
		$mendian =  Db::name('mendian')->where('mid',mid)->where('aid',aid)->where('status',1)->find();
		$hxuser =  Db::name('mendian_hexiaouser')->where('mid',mid)->where('aid',aid)->find();
		//查看是否登录管理员
		$uid = cache($this->sessionid.'_uid');
		if(!$uid){
			$uid = Db::name('admin_user')->where('aid',aid)->where('mid',mid)->value('id');
		}
		$this->user = Db::name('admin_user')->where('id',$uid)->where('status',1)->find();
		if($this->user['groupid']){
			$group = Db::name('admin_user_group')->where('id',$this->user['groupid'])->find();
			$this->user['hexiao_auth_data'] = $group['hexiao_auth_data'];
		}
		$wxauth_data = json_decode($this->user['wxauth_data'],true);
		if(!$mendian && !$hxuser && !$wxauth_data['product']){
			return $this->json(['status'=>0,'msg'=>'暂无核销权限']);
		}

		//$hxmid = input('param.hxmid');
		$where = [];
		$where[] = ['aid','=',aid];
		if($mendian['id']){
			$where[] = ['mdid','=',$mendian['id']];
		}elseif(!$mendian['id'] && $hxuser['mdid']){
			$where[] = ['mdid','=',$hxuser['mdid']];
		}else{
			//$where[] = ['mdid','=',0];
		}
	
		$where[] = ['status','=',1];
		if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
		$pernum = 10;
		$pagenum = input('post.pagenum');
		if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('shop_order')->where($where);
        if($orderids){
            $datalist->where(function ($query) use ($orderids,$keywords){
                $query->whereIn('id',$orderids)->whereOr('ordernum|title','like','%'.$keywords.'%');
            });
        }
        $datalist = $datalist->page($pagenum,$pernum)->order('id desc')->select()->toArray();
		if(!$datalist) $datalist = array();
		foreach($datalist as $key=>$v){
            $datalist[$key]['prolist'] = [];
			$prolist = Db::name('shop_order_goods')->where('orderid',$v['id'])->select()->toArray();
            if($prolist) $datalist[$key]['prolist'] = $prolist;
			$datalist[$key]['procount'] = Db::name('shop_order_goods')->where('orderid',$v['id'])->sum('num');
			$datalist[$key]['member'] = Db::name('member')->field('id,headimg,nickname')->where('id',$v['mid'])->find();
			if(!$datalist[$key]['member']) $datalist[$key]['member'] = [];
			$datalist[$key]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
			$datalist[$key]['checked'] = true;
		}

		$rdata = [];
		$rdata['datalist'] = $datalist;
		$rdata['codtxt'] = Db::name('shop_sysset')->where('aid',aid)->value('codtxt');
		$rdata['st'] = $st;
		return $this->json($rdata);
	}   
	
	//改为核销
    function hexiao(){
		$type = 'shop';
		//查看是否有核销权限
		$mendian =  Db::name('mendian')->where('mid',mid)->where('aid',aid)->where('status',1)->find();
		$hxuser =  Db::name('mendian_hexiaouser')->where('mid',mid)->where('aid',aid)->find();

		//查看是否登录管理员
		$uid = cache($this->sessionid.'_uid');
		if(!$uid){
			$uid = Db::name('admin_user')->where('aid',aid)->where('mid',mid)->value('id');
		}
		$this->user = Db::name('admin_user')->where('id',$uid)->where('status',1)->find();
		if($this->user['groupid']){
			$group = Db::name('admin_user_group')->where('id',$this->user['groupid'])->find();
			$this->user['hexiao_auth_data'] = $group['hexiao_auth_data'];
		}
		$wxauth_data = json_decode($this->user['wxauth_data'],true);
		if(!$mendian && !$hxuser && !$wxauth_data['product']){
			return $this->json(['status'=>0,'msg'=>'暂无核销权限']);
		}

		if($mendian['id']){
			$mdid = $mendian['id'];
		}elseif(!$mendian['id'] && $hxuser['mdid']){
			$mdid = $hxuser['mdid'];
			$mendian =  Db::name('mendian')->where('id',$mdid)->where('aid',aid)->find();
		}

        $orderids = input('post.orderids');
		foreach($orderids as $orderid){
			$order = Db::name('shop_order')->where(['aid'=>aid,'id'=>$orderid])->find();
			if($mdid!= 0 && $mdid!=$order['mdid']){
				continue;
				//return $this->json(['status'=>0,'msg'=>'您没有该门店核销权限']);
			}
			if($order['status']==3) continue;
			$data = array();
            $data['aid'] = aid;
            $data['bid'] = bid;
            $data['uid'] = 0;
            $data['mid'] = $order['mid'];
            $data['orderid'] = $order['id'];
            $data['ordernum'] = $order['ordernum'];
            $data['title'] = $order['title'];
            $data['type'] = 'shop';
            $data['createtime'] = time();
			if($mendian){
				$remark ='核销员['.$mendian['name'].']核销';
			}elseif(!$mendian['id'] && $hxuser['mdid']){
				$remark ='核销员['.$hxuser['name'].']核销';
			}elseif($uid && $wxauth_data['product']){
				$remark ='管理员['.$this->user['un'].']核销';
			}
		
            $data['remark'] = $remark;
            $data['mdid']   = $mdid;
			$data['hxmid']   = mid;
			//var_dump($data);
			Db::name('hexiao_order')->insert($data);
            $remark = $order['remark'] ? $order['remark'].' '.$data['remark'] : $data['remark'];

            $rs = \app\commons\Order::collect($order,$type);
            if($rs['status']==0) return $this->json($rs);
	
            db('shop_order')->where(['aid'=>aid,'id'=>$orderid])->update(['status'=>3,'collect_time'=>time(),'remark'=>$remark]);
			Db::name('shop_order_goods')->where(['aid'=>aid,'orderid'=>$order['id']])->update(['status'=>3,'endtime'=>time()]);
			\app\commons\Member::uplv(aid,$order['mid']);
    
		}
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }
	public function getmendian(){
		$mdid = input('param.mdid');
		$mendian =  Db::name('mendian')->where('id',$mdid)->where('aid',aid)->find();
		$member =   Db::name('member')->where('id',$mendian['mid'])->field('headimg')->find();
		$mendian['headimg'] = $member['headimg'];
		if($mendian){
			return $this->json(['status'=>1,'mendian'=>$mendian]);
		}else{
			return $this->json(['status'=>0,'msg'=>'获取失败']);
		}
	
	}
	public function getmendiancity(){
	
		$letterList =   Db::name('mendian')->where('aid',aid)->where('status',1)->group('city')->column('city');
		$zimu = [];
		foreach($letterList as $l){
			$zimu[] = $this->getFirstCharter($l);
		}

		$nameList =   Db::name('mendian')->field('city')->where('aid',aid)->where('status',1)->group('city')->select()->toArray();
		foreach($nameList as &$v){
			$v['zimu'] = $this->getFirstCharter($v['city']);
		}

		$rdata = [];
		$rdata['status'] = 1;
		$rdata['letterlist'] = $zimu;
		$rdata['namelist'] = $nameList;
		return $this->json(['status'=>1,'data'=>$rdata]);
	}


	//php获取中文字符拼音首字母
	function getFirstCharter($str){
		if(empty($str)){return '';}
		if(is_numeric($str{0})) return $str{0};// 如果是数字开头 则返回数字
		$fchar=ord($str{0});
		if($fchar>=ord('A')&&$fchar<=ord('z')) return strtoupper($str{0}); //如果是字母则返回字母的大写
		$s1=iconv('UTF-8','gb2312',$str);
		$s2=iconv('gb2312','UTF-8',$s1);
		$s=$s2==$str?$s1:$str;
		$asc=ord($s{0})*256+ord($s{1})-65536;
		if($asc>=-20319&&$asc<=-20284) return 'A';//这些都是汉字
		if($asc>=-20283&&$asc<=-19776) return 'B';
		if($asc>=-19775&&$asc<=-19219) return 'C';
		if($asc>=-19218&&$asc<=-18711) return 'D';
		if($asc>=-18710&&$asc<=-18527) return 'E';
		if($asc>=-18526&&$asc<=-18240) return 'F';
		if($asc>=-18239&&$asc<=-17923) return 'G';
		if($asc>=-17922&&$asc<=-17418) return 'H';
		if($asc>=-17417&&$asc<=-16475) return 'J';
		if($asc>=-16474&&$asc<=-16213) return 'K';
		if($asc>=-16212&&$asc<=-15641) return 'L';
		if($asc>=-15640&&$asc<=-15166) return 'M';
		if($asc>=-15165&&$asc<=-14923) return 'N';
		if($asc>=-14922&&$asc<=-14915) return 'O';
		if($asc>=-14914&&$asc<=-14631) return 'P';
		if($asc>=-14630&&$asc<=-14150) return 'Q';
		if($asc>=-14149&&$asc<=-14091) return 'R';
		if($asc>=-14090&&$asc<=-13319) return 'S';
		if($asc>=-13318&&$asc<=-12839) return 'T';
		if($asc>=-12838&&$asc<=-12557) return 'W';
		if($asc>=-12556&&$asc<=-11848) return 'X';
		if($asc>=-11847&&$asc<=-11056) return 'Y';
		if($asc>=-11055&&$asc<=-10247) return 'Z';
		return null;
	}
}