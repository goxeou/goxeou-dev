<?php
// +----------------------------------------------------------------------
// | 充值礼包 custom_file(yx_gift_pack)
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class GiftPack extends Common
{
    public function initialize()
    {
        parent::initialize(); 
    }

    //列表
	public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			$count = 0 + Db::name('gift_pack')->where($where)->count();
			$data = Db::name('gift_pack')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}

	//编辑
	public function edit(){

		if(input('param.id')){
			$info = Db::name('gift_pack')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}

		if($info && $info['coupon_ids']){
			$coupon_ids = explode(',', $info['coupon_ids']);
			$couponList = Db::name('coupon')->where('aid',aid)->where('bid', 0)->whereIn('id', $coupon_ids)->select()->toArray();
		}else{
			$couponList = [];
			$coupon_nums = [];
		}
		View::assign('couponList',$couponList);
		View::assign('info',$info);
        return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('gift_pack')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑礼包'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('gift_pack')->insertGetId($info);
			\app\commons\System::plog('添加礼包'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('gift_pack')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		Db::name('gift_pack_record')->where('aid',aid)->where('bid',bid)->where('couponid','in',$ids)->delete();
		\app\commons\System::plog('删除礼包'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//购买礼包数据
	public function orderlist(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'gift_pack_order.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'gift_pack_order.id desc';
			}
			$where = [];
			$where[] = ['gift_pack_order.aid','=',aid];
			if(input('param.bid') == 'all'){
			
			}else{
                $where[] = ['gift_pack_order.bid','=',bid];
			}
			$where[] = ['gift_pack_order.status','=',1];
			if(input('param.id/d')) $where[] = ['gift_pack_order.couponid','=',input('param.id/d')];
			if(input('param.recordmid')) $where[] = ['gift_pack_order.mid','=',input('param.recordmid/d')];

			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.input('param.nickname').'%'];
			$count = 0 + Db::name('gift_pack_order')->alias('gift_pack_order')->join('member member','gift_pack_order.mid=member.id')->where($where)->count();
			$data = Db::name('gift_pack_order')->alias('gift_pack_order')->field('gift_pack_order.*,member.nickname,member.headimg')->join('member member','gift_pack_order.mid=member.id')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as &$d){
				$d['couponcount'] = 0+Db::name('coupon_record')->where('mid',$d['mid'])->where('aid',aid)->where('status',0)->count();
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$coupon = [];
		if(input('param.id/d')){
			$coupon = Db::name('coupon')->where('id',input('param.id/d'))->find();
		}
		View::assign('coupon',$coupon);
		return View::fetch();
	}
	

	//订单详情
	public function getdetail(){
		$orderid = input('post.orderid');
		$order = Db::name('gift_pack_order')->where('aid',aid)->where('id',$orderid)->find();
		if(!$order) return json(['status'=>1,'msg'=>'订单不存在']);
		if(bid != 0 && $order['bid']!=bid) showmsg('无权限操作');
		
		$member = Db::name('member')->field('id,nickname,headimg,realname,tel,wxopenid,unionid')->where('id',$order['mid'])->find();
		if(!$member) $member = ['id'=>$order['mid'],'nickname'=>'','headimg'=>''];
		$comdata = array();
		if($order['parent1']){
			$parent1 = Db::name('member')->where('id',$order['parent1'])->find();
			$comdata['parent1']['mid'] = $order['parent1'];
			$comdata['parent1']['nickname'] = $parent1['nickname'];
			$comdata['parent1']['headimg'] = $parent1['headimg'];
			$comdata['parent1']['money'] += $order['parent1commission'];
		}
		$gift = Db::name('gift_pack')->where('id',$order['giftid'])->find();
		$order['pic'] = $gift['pic'];
		$couponids = explode(',',$gift['coupon_ids']);
		$couponlist = Db::name('coupon')->where('id','in',$couponids)->select()->toArray();

		return json(['order'=>$order,'member'=>$member,'couponlist'=>$couponlist,'comdata'=>$comdata]);
	}



	//领取数据导出
	public function recordexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'coupon_record.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'coupon_record.id desc';
		}
		$where = [];
		$where[] = ['coupon_record.aid','=',aid];
		$where[] = ['coupon_record.bid','=',bid];
		$where[] = ['coupon_record.couponid','=',input('param.couponid/d')];
		if(input('param.recordmid')) $where[] = ['coupon_record.mid','=',input('param.recordmid/d')];
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.input('param.nickname').'%'];
		$list = Db::name('coupon_record')->alias('coupon_record')->field('coupon_record.*,member.nickname,member.headimg')->join('member member','coupon_record.mid=member.id')->where($where)->order($order)->select()->toArray();
		
		$title = array();
		$title[] = '序号';
		$title[] = t('优惠券').'名称';
		$title[] = '领取人昵称';
		$title[] = '领取时间';
		$title[] = '到期时间';
		if($list[0]['type'] == 3)
		    $title[] = '总次数/已使用';
		$title[] = '使用时间';
		$title[] = '状态';
        $title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['id'];
			$tdata[] = $v['couponname'];
			$tdata[] = $v['nickname'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = date('Y-m-d H:i:s',$v['endtime']);
            if($list[0]['type'] == 3)
                $tdata[] = $v['limit_count'] . ' / ' . $v['used_count'];
            $tdata[] = ($v['status']==1 ? date('Y-m-d H:i:s',$v['usetime']) : '');
			$status = '';
			if($v['status']==0){
				if($v['endtime'] < time()){
					$status = '已过期';
				}else{
					$status = '未使用';
				}
			}elseif($v['status']==1){
				$status = '已使用';
			}
			$tdata[] = $status;
            $tdata[] = $v['remark'];
			$data[] = $tdata;
		}
		$this->export_excel($title,$data);
	}
	//改状态
	public function recordsetst(){
		$ids = input('post.ids/a');
		$st = input('post.st/d');//0未使用，1已使用
		$rlist = Db::name('coupon_record')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->select()->toArray();
		foreach($rlist as $k=>$v){
			if($v['type']==3){
				return json(['status'=>0,'msg'=>'计次券不能修改状态']);
			}
			Db::name('coupon_record')->where('aid',aid)->where('bid',bid)->where('id',$v['id'])->update(['status'=>$st]);
			\app\commons\Wechat::updatemembercard(aid,$v['mid']);
            if($st == 1){
                \app\commons\Coupon::useCoupon(aid,$v['id'],'hexiao');
            }
		}
		\app\commons\System::plog('修改'.t('优惠券').'领取记录状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//计次券减一次
	public function decCouponOne(){
		$rid = input('post.id/d');
		$cInfo = Db::name('coupon_record')->where('aid',aid)->where('bid',bid)->where('id',$rid)->where('type',3)->where('status',0)->find();
		if (!$cInfo) {
			return json(['status'=>0,'msg'=>'没有找到次券信息']);
		}
		if ($cInfo['used_count'] >= $cInfo['limit_count']) {
			return json(['status'=>0,'msg'=>'已使用全部次数']);
		}
		Db::name('coupon_record')->where('aid',aid)->where('bid',bid)->where('id',$rid)->inc('used_count')->update();
		$data['aid'] = $cInfo['aid'];
		$data['bid'] = bid;
		$data['uid'] = $this->uid;
		$data['mid'] = $cInfo['mid'];
		$data['orderid'] = $cInfo['id'];
		$data['ordernum'] = date('YmdHis');
		$data['title'] = $cInfo['couponname'];
		$data['type'] = 'coupon';
		$data['createtime'] = time();
		$user = Db::name('admin_user')->where('id',$this->uid)->find();
		$data['remark'] = '管理员['.$user['un'].']核销';
		$data['mdid']   = empty($this->user['mdid'])?0:$this->user['mdid'];
		Db::name('hexiao_order')->insert($data);
		if($cInfo['used_count']+1>=$cInfo['limit_count']){
			Db::name('coupon_record')->where('id',$rid)->update(['status'=>1,'usetime'=>time()]);
			\app\commons\Wechat::updatemembercard(aid,$cInfo['mid']);
		}
		\app\commons\System::plog('计次券减一次'.$rid);
		return json(['status'=>1,'msg'=>'核销成功']);
	}
	//核销记录
	public function hexiaorecord(){
		$orderid = input('param.crid/d');
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
			$where[] = ['bid','=',bid];
			$where[] = ['orderid','=',$orderid];
			$where[] = ['type','=','coupon'];
			$count = Db::name('hexiao_order')->where($where)->count();
			$data = Db::name('hexiao_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			if(!$data) $data = [];
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//删除
	public function recorddel(){
		$ids = input('post.ids/a');
		Db::name('coupon_record')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除'.t('优惠券').'领取记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    public function delay(){
        if(getcustom('coupon_delay')){
            //延期
            $id = input('post.id/d');
            $val = input('post.val/d');
            if($val < 1) return json(['status'=>0,'msg'=>'请输入正确的天数']);
            Db::name('coupon_record')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['endtime'=>Db::raw("endtime+".$val*86400)]);
            \app\commons\System::plog(t('优惠券').'延期'.$val.'天'.$id);
            return json(['status'=>1,'msg'=>'延期成功']);
        }
    }
	//发送优惠券
	public function sendcp(){
		$cpid = input('param.cpid/d');
		$coupon = Db::name('coupon')->where('aid',aid)->where('bid',bid)->where('id',$cpid)->find();
		if(!$coupon) showmsg(t('优惠券').'不存在');

        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $levelList = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->select()->toArray();
		$levelArr = array();
		foreach($levelList as $v){
			$levelArr[$v['id']] = $v['name']; 
		}

		View::assign('coupon',$coupon);
		View::assign('levelArr',$levelArr);
		return View::fetch();
	}
	//发送
	public function send(){
		$page = input('param.pagenum');
		$limit = input('param.pagelimit');
		$persendnum = input('param.persendnum/d');
		if(!$persendnum || $persendnum <=0) return json(['status'=>0,'msg'=>'请输入正确的发送数量']);
		$datawhere = input('post.datawhere/a');
		if($datawhere['field'] && $datawhere['order']){
			$order = $datawhere['field'].' '.$datawhere['order'];
		}else{
			$order = 'id desc';
		}
		if(input('post.sendtype') == "0"){
			$where = "id in(".implode(',',$_POST['ids']).")";
		}elseif(input('post.sendtype') == '1'){
			$where = array();
			$where[] = ['aid','=',aid];
			if($datawhere['pid']) $where[] = ['pid','=',$datawhere['pid']];
			if($datawhere['nickname']) $where[] = ['nickname','like','%'.$datawhere['nickname'].'%'];
			if($datawhere['realname']) $where[] = ['realname','like','%'.$datawhere['realname'].'%'];
			if($datawhere['levelid']) $where[] = ['levelid','=',$datawhere['levelid']];
			if($datawhere['ctime']){
				$ctime = explode(' ~ ',$datawhere['ctime']);
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
		}else{
			return json(['status'=>0,'msg'=>'参数错误']);
		}
		$cpid = input('post.cpid');
		$datalist = Db::name('member')->where($where)->page($page,$limit)->order($order)->select()->toArray();
		$sucnum = 0;
		$errnum = 0;
		foreach($datalist as $k=>$member){
			for($i=0;$i<$persendnum;$i++){
				$rs = \app\commons\Coupon::send(aid,$member['id'],$cpid);
				//dump($rs);exit;
				if($rs['status']==0){
					$errnum++;
				}else{
					$sucnum++;
				}
			}
            if(getcustom('sms_temp_coupon_get')){
                $tel = Db::name('member')->where('aid',aid)->where('id',$member['id'])->value('tel');
                $coupon = db('coupon')->where('aid',aid)->where('id',$cpid)->field('name,bid')->find();
                if($coupon['bid'] == 0){
                    $bname = Db::name('admin_set')->where('aid',aid)->value('name');
                }else{
                    $bname = Db::name('business')->where('id',bid)->value('name');
                }
                if($tel){
                    $rs = \app\commons\Sms::send(aid,$tel,'tmpl_coupon_get',['bname'=>$bname,'num'=>$persendnum,'coupon_name' => $coupon['name']]);
                }
            }
            if(getcustom('coupon_xianxia_buy')){
                \app\commons\Member::xianxiaUpLevel($member,$persendnum,0);
            }
		}
		\app\commons\System::plog('发送'.t('优惠券').$cpid);
		if($sucnum==0){
            return json(['status'=>0,'msg'=>$rs['msg'],'errnum'=>$errnum,'url'=>(string)url('Coupon/record',['id'=>$cpid])]);
        }
		return json(['status'=>1,'msg'=>'发送完成','sucnum'=>$sucnum,'url'=>(string)url('Coupon/record',['id'=>$cpid])]);
	}

	public function choosecoupon(){
		if(request()->isPost()){
			$id = input('param.id/d');
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['id','=',$id];
            if(getcustom('payaftergive_coupon_all') && input('param.module')=='payaftergive') {
                if (bid > 0) {
                    $where[] = ['bid', '=', bid];
                }
            }else{
                $where[] = ['bid', '=', bid];
            }
			$coupon = Db::name('coupon')->where($where)->find();
			return json(['status'=>1,'data'=>$coupon]);
		}
		$type = input('param.type');
        View::assign('type',$type);
        View::assign('module',input('param.module',''));//来源模块
		return View::fetch();
	}

    //复制
    public function copy(){
        $info = Db::name('coupon')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
        if(!$info) return json(['status'=>0,'msg'=>t('优惠券').'不存在,请重新选择']);
        $data = $info;
        $data['name'] = '复制-'.$data['name'];
        unset($data['id']);
        $data['getnum'] = 0;

        $newproid = Db::name('coupon')->insertGetId($data);

        \app\commons\System::plog(t('优惠券').'复制'.$newproid);
        return json(['status'=>1,'msg'=>'复制成功','objid'=>$newproid]);
    }

	public function creategiveqr(){

		set_time_limit(0);
		ini_set('memory_limit', '2000M');

		if(input('param.field') && input('param.order')){
			$order = 'coupon_record.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'coupon_record.id desc';
		}
		$where = [];
		$where[] = ['coupon_record.aid','=',aid];
		$where[] = ['coupon_record.bid','=',bid];
		$where[] = ['coupon_record.couponid','=',input('param.couponid/d')];
		if(input('param.recordmid')) $where[] = ['coupon_record.mid','=',input('param.recordmid/d')];
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.input('param.nickname').'%'];
		$list = Db::name('coupon_record')->alias('coupon_record')->field('coupon_record.*,member.nickname,member.headimg')->join('member member','coupon_record.mid=member.id')->where($where)->order($order)->select()->toArray();
		
		
		$dir = 'upload/temp/'.date('Ym').'/'.date('d_His').rand(1000000,9999999);
		if(!is_dir(ROOT_PATH.$dir)) mk_dir(ROOT_PATH.$dir);
		$zippath = ROOT_PATH.$dir.'.zip';

		foreach($list as $record){
			$page = 'pagesExt/coupon/coupondetail?id='.$record['couponid'].'&pid='.$record['mid'].'&rid='.$record['id'];
			$data = array();
			$data['page'] = $page;
            $errmsg = \app\commons\Wechat::getQRCode(aid,'wx',$data['page'],[],bid,false);
            $res = $errmsg['buffer'];//图片 Buffer
			if($errmsg['status'] != 1){
                if($errmsg['errcode'] == 41030){
                    return json(array('status'=>0,'msg'=>'小程序发布后才能生成分享海报'));
                }else{
                    return json(['status'=>0,'msg'=>$errmsg['msg'],'rs'=>$errmsg['rs'],'data'=>$data]);
                }
			}

			file_put_contents(ROOT_PATH.$dir.'/'.$record['id'].'.jpg',$res);
		}
		$myfile = fopen($zippath, "w");
		fclose($myfile);
		\app\commons\File::add_file_to_zip(ROOT_PATH.$dir,$zippath,uniqid());
		\app\commons\File::remove_dir(ROOT_PATH.$dir);
		$url = PRE_URL.'/'.$dir.'.zip';
		//var_dump($url);
		return json(['status'=>1,'msg'=>'打包成功','url'=>$url]);
	}

	public function creategiveqr2(){
		if(input('param.field') && input('param.order')){
			$order = 'coupon_record.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'coupon_record.id';
		}
		$where = [];
		$where[] = ['coupon_record.aid','=',aid];
		$where[] = ['coupon_record.bid','=',bid];
		$where[] = ['coupon_record.couponid','=',input('param.couponid/d')];
		$where[] = Db::raw('from_mid is null');
		if(input('param.recordmid')) $where[] = ['coupon_record.mid','=',input('param.recordmid/d')];
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.input('param.nickname').'%'];
		$record = Db::name('coupon_record')->alias('coupon_record')->field('coupon_record.*,member.nickname,member.headimg')->join('member member','coupon_record.mid=member.id')->where($where)->order($order)->find();
		//var_dump($record);

		if(!$record) return json(['status'=>0,'msg'=>'未找到可转赠的记录']);
		
		$page = 'pagesExt/coupon/coupondetail?id='.$record['couponid'].'&pid='.$record['mid'].'&rid=all'.$record['id'];
		$rs = \app\commons\Wechat::getQRCode(aid,'wx',$page);
		$rs['page'] = $page;
		return json($rs);
	}
    public function set(){
        if(request()->isPost()){
            $info = input('post.info/a');
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['expire_rules'] = implode(',',$info['expire_rules']);
            Db::name('coupon_set')->where('aid',aid)->where('bid',bid)->update($info);
            \app\commons\System::plog(t('优惠券').'设置');
            return json(['status'=>1,'msg'=>'保存成功','url'=>true]);
        }
        $info = Db::name('coupon_set')->where('aid',aid)->where('bid',bid)->find();
        $info['expire_rules'] = explode(',',$info['expire_rules']);
        View::assign('info',$info);
        return View::fetch();
    }
    public function sendNotice(){
        \app\commons\Coupon::auto_expire_notice();
    }
}