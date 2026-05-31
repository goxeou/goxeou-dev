<?php


// +----------------------------------------------------------------------
// | 微信红包
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

/**
 * Class Hongbao
 * @package app\controllers
 * @deprecated
 */
class Hongbao extends Common
{	
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
	//活动列表
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
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			$count = 0 + Db::name('hongbao')->where($where)->count();
			$data = Db::name('hongbao')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('hongbao')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$sendname = Db::name('admin_set')->where('aid',aid)->value('name');
			$info = array(
				'id'=>'',
				'name'=>'微信红包活动开始啦',
				'sendname'=>$sendname,
				'wishing'=>'恭喜发财',
				'minmoney'=>'1',
				'maxmoney'=>'2',
				'totalmoney'=>'100',
				'pernum'=>1,
				'starttime'=>time(),
				'endtime'=>time()+86400,
				'status'=>1,
				'sharepic'=>'',
				'gettj'=>'-1',
			);
		}
		$info['gettj'] = explode(',',$info['gettj']);
		View::assign('info',$info);
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
		View::assign('memberlevel',$memberlevel);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$info['starttime'] = strtotime($info['starttime']);
		$info['endtime'] = strtotime($info['endtime']);
		$info['gettj'] = implode(',',$info['gettj']);
		if($info['id']){
			$info['updatetime'] = time();
			Db::name('hongbao')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('修改红包活动'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('hongbao')->insertGetId($info);
			\app\commons\System::plog('添加红包活动'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('hongbao')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除红包活动'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//领取记录
	public function record(){
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
			if(input('param.hid')){
				$where[] = ['hid','=',input('param.hid/d')];
			}
			if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
			if(input('param.nickname')) $where[] = ['nickname','like','%'.input('param.nickname').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('hongbao_record')->where($where)->count();
			$data = Db::name('hongbao_record')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//领取记录导出
	public function recordexcel(){
		$where = [];
		$where[] = ['aid','=',aid];
		if(input('param.hid')){
			$where[] = ['hid','=',input('param.hid/d')];
		}
		$list = Db::name('hongbao_record')->where($where)->order($order)->select()->toArray();
		
		$title = array();
		$title[] = '序号';
		$title[] = '活动ID';
		$title[] = '活动名称';
		$title[] = t('会员').'ID';
		$title[] = '昵称';
		$title[] = '金额';
		$title[] = '领取时间';
		$title[] = '状态';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['id'];
			$tdata[] = $v['hid'];
			$tdata[] = $v['name'];
			$tdata[] = $v['mid'];
			$tdata[] = $v['nickname'];
			$tdata[] = $v['money'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$status = '';
			if($v['status']==1){
				$status = '成功';
			}elseif($v['status']==2){
				$status = '失败';
			}elseif($v['status']==0){
				$status = '未发放';
			}
			$tdata[] = $status;
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
		$this->export_excel($title,$data);
	}
	//删除
	public function recorddel(){
		$ids = input('post.ids/a');
		Db::name('hongbao_record')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除红包记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//抽奖码
	public function codelist(){
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
			if(input('param.hid')){
				$where[] = ['hid','=',input('param.hid/d')];
			}
			if(input('param.code')) $where[] = ['code','=',input('param.code')];
			if(input('param.nickname')) $where[] = ['nickname','like','%'.input('param.nickname').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('hongbao_codelist')->where($where)->count();
			$data = Db::name('hongbao_codelist')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$data[$k]['name'] = Db::name('hongbao')->where('id',$v['hid'])->value('name');
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//抽奖码导出
	public function codelistexcel(){
		$where = [];
		$where[] = ['aid','=',aid];
		if(input('param.hid')){
			$where[] = ['hid','=',input('param.hid/d')];
		}
		$list = Db::name('hongbao_codelist')->where($where)->order($order)->select()->toArray();
		
		$title = array();
		$title[] = '序号';
		$title[] = '活动ID';
		$title[] = '活动名称';
		$title[] = '抽奖码';
		$title[] = '使用人';
		$title[] = '抽奖金额';
		$title[] = '抽奖时间';
		$title[] = '状态';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['id'];
			$tdata[] = $v['hid'];
			$tdata[] = $v['name'];
			$tdata[] = $v['code'];
			$tdata[] = $v['nickname'];
			$tdata[] = $v['money'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$status = '';
			if($v['status']==1){
				$status = '已使用';
			}elseif($v['status']==0){
				$status = '未使用';
			}
			$tdata[] = $status;
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
		$this->export_excel($title,$data);
	}
	//删除
	public function codelistdel(){
		$ids = input('post.ids/a');
		Db::name('hongbao_codelist')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除红包抽奖码'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//生成抽奖码
	public function makecode(){
		$hid = input('post.hid');
		$makecount = input('post.makecount/d');
		$codelength = input('post.codelength/d');
		$codetype = input('post.codetype');
		if($makecount < 1 || $makecount > 5000){
			return json(['status'=>0,'msg'=>'每次生成数量须在5000以内']);
		}
		if($codelength < 1 || $codelength > 10){
			return json(['status'=>0,'msg'=>'抽奖码长度须小于10']);
		}
		$data = [];
		$data['aid'] = aid;
		$data['hid'] = $hid;
		$data['createtime'] = time();
		for($i=0;$i<$makecount;$i++){
            $randstr = make_rand_code($codetype, $codelength);
			$data['code'] = $randstr;
			Db::name('hongbao_codelist')->insert($data);
		}
		\app\commons\System::plog('生成红包活动抽奖码'.$hid);
		return json(['status'=>1,'msg'=>'生成完成']);
	}
}