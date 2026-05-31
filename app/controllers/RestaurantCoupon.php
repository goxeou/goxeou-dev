<?php


// +----------------------------------------------------------------------
// | 优惠券
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class RestaurantCoupon extends Common
{
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
			$type[] = 5;
			$where[] = ['type','in',$type];
		
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			$count = 0 + Db::name('coupon')->where($where)->count();
			$data = Db::name('coupon')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				if(strtotime($v['starttime']) > time()){
					$data[$k]['status'] = '<button class="layui-btn layui-btn-sm" style="background-color:#888">未开始</button>';
				}elseif(strtotime($v['endtime']) < time()){
					$data[$k]['status'] = '<button class="layui-btn layui-btn-sm layui-btn-disabled">已结束</button>';
				}else{
					$data[$k]['status'] = '<button class="layui-btn layui-btn-sm" style="background-color:#5FB878">进行中</button>';
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//编辑
	public function edit(){

		if(input('param.id')){
			$info = Db::name('coupon')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'','perlimit'=>1,'yxqtype'=>1,'yxqtime'=>date('Y-m-d 00:00:00').' ~ '.date('Y-m-d 00:00:00',time()+7*86400),'starttime'=>date('Y-m-d 00:00:00'),'endtime'=>date('Y-m-d 00:00:00',time()+7*86400),'gettj'=>'-1','sort'=>0,'fwtype'=>0);
		}
		$info['gettj'] = explode(',',$info['gettj']);
		View::assign('info',$info);
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
		View::assign('memberlevel',$memberlevel);

		$categorydata = array();
		if($info && $info['categoryids']){
			$categorydata = Db::name('restaurant_product_category')->where('aid',aid)->where('id','in',$info['categoryids'])->order('sort desc,id')->select()->toArray();
		}
		View::assign('categorydata',$categorydata);
		$productdata = array();
		if($info && $info['productids']){
			$productdata = Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id','in',$info['productids'])->order('sort desc,id')->select()->toArray();
		}
		$productdata2 = array();
		if($info && $info['buyproids']){
			$productdata2 = Db::name('restaurant_product')->where('aid',aid)->where('bid',bid)->where('id','in',$info['buyproids'])->order('sort desc,id')->select()->toArray();
		}
        View::assign('productdata',$productdata);
		View::assign('productdata2',$productdata2);
        $config = include(ROOT_PATH.'/config.php');
        if(isset($config['restaurant'])) {
            View::assign('restaurant',true);
        }
        $show_coupon_type = false;
        View::assign('show_coupon_type',$show_coupon_type);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$info['gettj'] = implode(',',$info['gettj']);
		if($info['paygive_scene']){
			$info['paygive_scene'] = implode(',',$info['paygive_scene']);
		}else{
			$info['paygive_scene'] = '';
		}
        //优惠券有效期
        if($info['yxqtype'] == 2) {
            $info['yxqdate'] = input('post.yxqdate2');
        }
        if($info['yxqtype'] == 3) {
            $info['yxqdate'] = input('post.yxqdate3');
        }
		if($info['id']){
			Db::name('coupon')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑'.t('优惠券').$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('coupon')->insertGetId($info);
			\app\commons\System::plog('添加'.t('优惠券').$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('coupon')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除'.t('优惠券').implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//领取数据
	public function record(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'coupon_record.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'coupon_record.id desc';
			}
			$where = [];
			$where[] = ['coupon_record.aid','=',aid];
			$where[] = ['coupon_record.bid','=',bid];
			$where[] = ['coupon_record.couponid','=',input('param.id/d')];
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.input('param.nickname').'%'];
			if(input('param.recordmid')) $where[] = ['coupon_record.mid','=',input('param.recordmid/d')];

			$count = 0 + Db::name('coupon_record')->alias('coupon_record')->join('member member','coupon_record.mid=member.id')->where($where)->count();
			$data = Db::name('coupon_record')->alias('coupon_record')->field('coupon_record.*,member.nickname,member.headimg')->join('member member','coupon_record.mid=member.id')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$coupon = [];
		if(input('param.id/d')){
			$coupon = Db::name('coupon')->where('id',input('param.id/d'))->find();
		}
		View::assign('coupon',$coupon);
		return View::fetch();
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
		$where[] = ['coupon_record.couponid','=',input('param.id/d')];
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.input('param.nickname').'%'];
		if(input('param.recordmid')) $where[] = ['coupon_record.mid','=',input('param.recordmid/d')];
		$list = Db::name('coupon_record')->alias('coupon_record')->field('coupon_record.*,member.nickname,member.headimg')->join('member member','coupon_record.mid=member.id')->where($where)->order($order)->select()->toArray();
		
		$title = array();
		$title[] = '序号';
		$title[] = t('优惠券').'名称';
		$title[] = '领取人昵称';
		$title[] = '领取时间';
		$title[] = '到期时间';
		$title[] = '使用时间';
		$title[] = '状态';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['id'];
			$tdata[] = $v['couponname'];
			$tdata[] = $v['nickname'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = date('Y-m-d H:i:s',$v['endtime']);
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
			$data[] = $tdata;
		}
		$this->export_excel($title,$data);
	}
	//改状态
	public function recordsetst(){
		$ids = input('post.ids/a');
		$st = input('post.st/d');
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
		$data['mdid']   = empty($user['mdid'])?0:$user['mdid'];
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
        }
	//发送优惠券
	public function sendcp(){
		$cpid = input('param.cpid/d');
		$coupon = Db::name('coupon')->where('aid',aid)->where('bid',bid)->where('id',$cpid)->find();
		if(!$coupon) showmsg(t('优惠券').'不存在');
		
		$levelList = Db::name('member_level')->where('aid',aid)->select()->toArray();
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
				if($rs['status']==0){
					$errnum++;
				}else{
					$sucnum++;
				}
			}
		}
		\app\commons\System::plog('发送'.t('优惠券').$cpid);
		return json(['status'=>1,'msg'=>'发送完成','url'=>(string)url('Coupon/record',['id'=>$cpid])]);
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

	public function choosecoupon(){
		if(request()->isPost()){
			$id = input('param.id/d');
			$coupon = Db::name('coupon')->where('aid',aid)->where('bid',bid)->where('type',5)->where('id',$id)->find();
			return json(['status'=>1,'data'=>$coupon]);
		}
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
    //创建优惠券记录
    public function createQrcodeRecord(){
        }
    public function qrcodeRecord(){
        }
    //领取数据导出
    public function qrcoderecordexcel(){
        }
}