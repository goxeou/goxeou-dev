<?php


// +----------------------------------------------------------------------
// | 投票活动
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class Toupiao extends Common
{
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
			if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];
			if(input('param.group_id')) $where[] = ['group_id','=',$_GET['group_id']];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
            $groupArr = Db::name('toupiao_group')->where('aid',aid)->column('name','id');
			$count = 0 + Db::name('toupiao')->where($where)->count();
			$data = Db::name('toupiao')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				//if($v['bid'] > 0){
				//	$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				//}else{
				//	$data[$k]['bname'] = '平台自营';
				//}
                $groupname = '';
                if($v['group_id']>0 && isset($groupArr[$v['group_id']])){
                    $groupname = $groupArr[$v['group_id']];
                }
                $data[$k]['group_name'] = $groupname;
				$data[$k]['joinnum'] = Db::name('toupiao_join')->where('aid',aid)->where('hid',$v['id'])->where('status',1)->count();
				$data[$k]['helpnum'] = Db::name('toupiao_join')->where('aid',aid)->where('hid',$v['id'])->where('status',1)->sum('helpnum');
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
        $grouplist = Db::name('toupiao_group')->where('aid',aid)->order('sort desc,id desc')->select()->toArray();
        View::assign('grouplist',$grouplist);
		return View::fetch();
    }
	//编辑活动
	public function edit(){
		if(input('param.id')){
			$info = Db::name('toupiao')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('活动不存在');
		}else{
			$info = [];
			$info['canapply'] = 1;
			$info['checkapply'] = 1;
			$info['status'] = 1;
			$info['help_check'] = 0;
			$info['starttime'] = time();
			$info['endtime'] = time() + 7*86400;
			$info['helptext'] = '投TA一票';
			$info['gettj'] = '-1';
			$info['color1'] = '#FD4A46';
			$info['color2'] = '#1C83FF';
			$info['per_daycount'] = 1;
			$info['per_allcount'] = 0;
			$info['listtype'] = 0;
            }
        $info['gettj'] = explode(',',$info['gettj']);
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ?? 0;
        $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        $grouplist = Db::name('toupiao_group')->where('aid',aid)->order('sort desc,id desc')->select()->toArray();
        View::assign('grouplist',$grouplist);
        View::assign('memberlevel',$memberlevel);
		View::assign('info',$info);
		return View::fetch();
	}
	//保存活动
	public function save(){
		if(input('post.id')){
			$toupiao = Db::name('toupiao')->where('aid',aid)->where('id',input('post.id/d'))->find();
			if(!$toupiao) showmsg('活动不存在');
		}
		
		$info = input('post.info/a');
		$data = [];
        $data['gettj'] = $info['gettj']?implode(',',$info['gettj']):'-1';
		$data['name'] = $info['name'];
		$data['group_id'] = $info['group_id']??0;
		$data['banner'] = $info['banner'];
		$data['color1'] = $info['color1'];
		$data['color2'] = $info['color2'];
		$data['listtype'] = $info['listtype'];
		$data['helptext'] = $info['helptext'];
		$data['canapply'] = $info['canapply'];
		$data['apply_check'] = $info['apply_check'];
		$data['help_check'] = $info['help_check'];
		$data['starttime'] = strtotime($info['starttime']);
		$data['endtime'] = strtotime($info['endtime']);
		$data['sort'] = $info['sort'];
		$data['guize'] = $info['guize'];
		$data['sharetitle'] = $info['sharetitle'];
		$data['sharepic'] = $info['sharepic'];
		$data['sharedesc'] = $info['sharedesc'];
		$data['sharelink'] = $info['sharelink'];
		$data['per_daycount'] = $info['per_daycount'];
		$data['per_allcount'] = $info['per_allcount'];
		$data['jump_url'] = $info['jump_url'];
		if($toupiao){
			Db::name('toupiao')->where('aid',aid)->where('id',$toupiao['id'])->update($data);
			$proid = $toupiao['id'];
			\app\commons\System::plog('编辑投票活动'.$proid);
		}else{
			$data['aid'] = aid;
			$data['createtime'] = time();
			$proid = Db::name('toupiao')->insertGetId($data);
			\app\commons\System::plog('添加投票活动'.$proid);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		Db::name('toupiao')->where($where)->update(['status'=>$st]);

		\app\commons\System::plog('投票活动改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		Db::name('toupiao')->where($where)->delete();

		\app\commons\System::plog('删除投票活动'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//选手列表
	public function joinlist(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order').',id';
			}else{
				$order = 'id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(input('param.hid')){
				$where[] = ['hid','=',input('param.hid')];
			}
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			$toupiaoArr = Db::name('toupiao')->where('aid',aid)->order('sort desc,id desc')->column('name','id');

			$count = 0 + Db::name('toupiao_join')->where($where)->count();
			$data = Db::name('toupiao_join')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$sql = Db::getlastsql();
			foreach($data as $k=>$v){
				$data[$k]['hdname'] = $toupiaoArr[$v['hid']];
				$data[$k]['mingci'] = 1 + Db::name('toupiao_join')->where('aid',aid)->where('status',1)->where('hid',$v['hid'])->where("helpnum>{$v['helpnum']} or (helpnum={$v['helpnum']} and id < {$v['id']})")->count();
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'$sql'=>$sql]);
		}
		$toupiaoArr = Db::name('toupiao')->where('aid',aid)->order('sort desc,id desc')->column('name','id');

		View::assign('toupiaoArr',$toupiaoArr);
		return View::fetch();
	}
	//选手编辑
	public function joinedit(){
		if(request()->isPost()){
			$info = input('post.info/a');
			if(input('param.id')){
				$join = Db::name('toupiao_join')->where('aid',aid)->where('id',input('param.id'))->find();
				if(!$join) showmsg('选手不存在');
			}

			$data = [];
			$data['hid'] = $info['hid'];
			$data['name'] = $info['name'];
			$data['pic'] = $info['pic'];
			$data['pics'] = $info['pics'];
			$data['detail_txt'] = $info['detail_txt'];
			$data['detail'] = $info['detail'];
			$data['status'] = $info['status'];
			$data['reason'] = $info['reason'];
			$data['number'] = $info['number'];
            if($join){
				if($data['status'] == 1 && !$join['number']){
					$lastnumber =Db::name('toupiao_join')->where('aid',aid)->where('hid',$data['hid'])->order('number desc')->value('number');
					$thisnumber = intval($lastnumber) + 1;
					if($thisnumber < 100) $thisnumber = sprintf("%03d",$thisnumber);
					$data['number'] = $thisnumber;
				}
				Db::name('toupiao_join')->where('aid',aid)->where('id',$join['id'])->update($data);
				\app\commons\System::plog('编辑投票选手'.$join['id']);
			}else{
				$lastnumber =Db::name('toupiao_join')->where('aid',aid)->where('hid',$data['hid'])->order('number desc')->value('number');
				$thisnumber = intval($lastnumber) + 1;
				if($thisnumber < 100) $thisnumber = sprintf("%03d",$thisnumber);
				$data['aid'] = aid;
				$data['createtime'] = time();
				$data['number'] = $thisnumber;
				$id = Db::name('toupiao_join')->insertGetId($data);
				\app\commons\System::plog('添加投票选手'.$id);
			}
			return json(['status'=>1,'msg'=>'操作成功']);

		}
		if(input('param.id')){
			$info = Db::name('toupiao_join')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('选手不存在');
            }else{
			$info = [];
			if(input('param.hid')){
				$info['hid'] = input('param.hid/d');
                }
			$info['status'] = 1;
			$toupiaolist = Db::name('toupiao')->where('aid',aid)->order('sort desc,id desc')->select()->toArray();

            View::assign('toupiaolist',$toupiaolist);
		}
		View::assign('info',$info);
		return View::fetch();
	}
	//删除
	public function joinlistdel(){
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		Db::name('toupiao_join')->where($where)->delete();
		\app\commons\System::plog('删除投票选手'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//投票记录
	public function helplist(){
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
			if(input('param.hid')){
				$where[] = ['hid','=',input('param.hid')];
			}
			if(input('param.joinid')){
				$where[] = ['joinid','=',input('param.joinid')];
			}
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}

			$toupiaoArr = Db::name('toupiao')->where('aid',aid)->order('sort desc,id desc')->column('name','id');
			$joinArr = Db::name('toupiao_join')->where('aid',aid)->order('sort desc,id desc')->column('name,number','id');

			$count = 0 + Db::name('toupiao_help')->where($where)->count();
			$data = Db::name('toupiao_help')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$data[$k]['hdname'] = $toupiaoArr[$v['hid']];
				$data[$k]['joinname'] = $joinArr[$v['joinid']]['name'];
				$data[$k]['number'] = $joinArr[$v['joinid']]['number'];
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'$joinArr'=>$joinArr]);
		}
		if(input('param.hid')){
			$joinArr = Db::name('toupiao_join')->where('aid',aid)->where('hid',input('param.hid'))->order('sort desc,id desc')->column('name','id');
		}else{
			$toupiaoArr = Db::name('toupiao')->where('aid',aid)->order('sort desc,id desc')->column('name','id');
		}
		View::assign('joinArr',$joinArr);
		View::assign('toupiaoArr',$toupiaoArr);
		return View::fetch();
	}
	public function helplistdel(){
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		Db::name('toupiao_help')->where($where)->delete();
		\app\commons\System::plog('删除投票记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	public function getjoinlist(){
		$hid = input('param.hid');
		$joinlist = Db::name('toupiao_join')->where('aid',aid)->where('hid',$hid)->order('sort desc,id desc')->field('id,name')->select()->toArray();
		return json(['status'=>1,'data'=>$joinlist]);
	}
	//导出投票记录
	public function helpexcel(){
		set_time_limit(0);
		ini_set('memory_limit', '2000M');
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'id desc';
		}
        $page = input('param.page');
        $limit = input('param.limit');
		$where = array();
		$where[] = ['aid','=',aid];
		if(input('param.hid')){
			$where[] = ['hid','=',input('param.hid')];
		}
		if(input('param.joinid')){
			$where[] = ['joinid','=',input('param.joinid')];
		}
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['createtime','>=',strtotime($ctime[0])];
			$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
		}

		$toupiaoArr = Db::name('toupiao')->where('aid',aid)->order('sort desc,id desc')->column('name','id');
		$joinArr = Db::name('toupiao_join')->where('aid',aid)->order('sort desc,id desc')->column('name','id');

		$list = Db::name('toupiao_help')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('toupiao_help')->where($where)->order($order)->count();
		$title = array('ID','所属活动','选手名称','投票人','投票时间');
		$data = [];
		foreach($list as $k=>$vo){
			$data[$k] = [
				$vo['id'],
				$toupiaoArr[$vo['hid']],
				$joinArr[$vo['joinid']],
				$vo['nickname'],
				date('Y-m-d H:i:s',$vo['createtime']),
			];
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}

	//加票
	public function addpiao(){
		$id = input('param.id/d');
		$num = input('param.num/d');
		Db::name('toupiao_join')->where('aid',aid)->where('id',$id)->inc('helpnum',$num)->inc('readcount',$num)->update();
		\app\commons\System::plog('给选手ID:'.$id.' 增加票数'.$num);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
}
