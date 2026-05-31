<?php


// +----------------------------------------------------------------------
// | 课程-题库管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class KechengTiku extends Common
{
    public function initialize(){
        parent::initialize();
        $this->defaultSet();
    }
	//题库列表
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
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['bid','<>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
				}else{
					$where[] = ['bid','=',0];
				}
			}else{
				$where[] = ['bid','=',bid];
			}
			if(input('param.kcid')) $where[] = ['kcid','=',input('param.kcid')];
			if(input('param.name')) $where[] = ['title','like','%'.$_GET['name'].'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];

			$count = 0 + Db::name('Kecheng_tiku')->where($where)->count();
			$data = Db::name('Kecheng_tiku')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$clist = Db::name ('kecheng_list')->where('aid',aid)->where('bid',bid)->select()->toArray();
			$cdata = array();
			$optionarr = ['A','B','C','D','E','F','G'];
			foreach($clist as $c){
				$cdata[$c['id']] = $c['name'];
			}
			foreach($data as $k=>$v){
				$data[$k]['kcname'] = $cdata[$v['kcid']];
				if($v['bid'] > 0){
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['bname'] = '平台自营';
				}
				if($v['type']==1){
					$right_option = explode(',',$v['right_option']);
					$right_options = array();
					foreach($right_option as $d){
						$right_options[] = $optionarr[$d];
					}
					$data[$k]['right_option'] = implode(',',$right_options);
				}
				if($v['type']==1) $data[$k]['tname'] = '选择题';
				if($v['type']==2) $data[$k]['tname'] = '填空题';

				}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$kcid = input('param.kcid');
		View::assign('kcid',$kcid);
		View::assign('sysset',$sysset);
		View::assign('systimeset',$systimeset);

		return View::fetch();
    }
	//编辑题库
	public function edit(){
		if(input('param.id')){
			$info = Db::name('kecheng_tiku')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('题库不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
		}else{
			$info = ['kcid'=>input('param.kcid'),'score'=>10];
		}

		$optionarr = ['A','B','C','D','E','F','G'];
		//课程列表
		$kclist = Db::name('kecheng_list')->Field('id,name')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->select()->toArray(); 
		$kcid = input('param.kcid');
        $info['cid'] = explode(',',$info['cid']);
		if($info['type']==1 && $info['option_group']){
			$option = json_decode($info['option_group'],true);
			View::assign('option',$option);
		}
		View::assign('kclist',$kclist);
		View::assign('kcid',$kcid);
		View::assign('info',$info);
		View::assign('optionarr',$optionarr);
		return View::fetch();
	}
	//保存题库
	public function save(){
		if(input('post.id')){
			$tiku = Db::name('kecheng_tiku')->where('aid',aid)->where('id',input('post.id/d'))->find();
			if(!$tiku) showmsg('题库不存在');
			if(bid != 0 && $tiku['bid']!=bid) showmsg('无权限操作');
		}
		$info = input('post.info/a');
		$options = input('post.option/a');
		$rightOption = $optionsArr = [];
		//var_dump(jsonEncode($option));die;
		$data = array();
		$data['title'] = $info['title'];
		$data['type'] = $info['type'];
		$data['score'] = $info['score'];
		$data['status'] = $info['status'];
		$data['kcid'] = $info['kcid'];
		$data['jiexi'] = $info['jiexi'];
		if($info['type']==1){
			if($options && is_array($options)){
				foreach ($options as $key=>$row){
					if(!$row['value']){
						continue;
					}
					if(isset($row['key']) && $row['key']==1){
						$rightOption[] = $key;
					}
					$optionsArr[] = $row['value'];
				}
			}

			if(empty($rightOption)){
				 return json(['status'=>0,'msg'=>'请设置一个正确答案']);
			}
			$data['option_group'] = json_encode($optionsArr);
			$data['right_option'] = implode(',',$rightOption);
		}else{
			$data['right_option'] = $info['right_option'];
		}
		if(!$tiku) $data['createtime'] = time();
		if($tiku){
			Db::name('kecheng_tiku')->where('aid',aid)->where('id',$tiku['id'])->update($data);
			$proid = $tiku['id'];
			\app\commons\System::plog('题库编辑'.$proid);
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
			$proid = Db::name('kecheng_tiku')->insertGetId($data);
			\app\commons\System::plog('题库编辑'.$proid);
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
		if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		Db::name('kecheng_tiku')->where($where)->update(['status'=>$st]);
		\app\commons\System::plog('题库编辑'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('kecheng_tiku')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	
	//删除
	public function del(){
		$ids = input('post.ids/a');
		if(!$ids) $ids = array(input('post.id/d'));
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		$prolist = Db::name('kecheng_tiku')->where($where)->select();
		foreach($prolist as $pro){
			Db::name('kecheng_tiku')->where('id',$pro['id'])->delete();
		}
		\app\commons\System::plog('删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//导入题库
	public function importexcel(){
		set_time_limit(0);
		ini_set('memory_limit',-1);
		$file = input('post.file');
		if(!$file) return json(['status'=>0,'msg'=>'请上传excel文件']);
		$exceldata = $this->import_excel($file);
	
		$insertnum = 0;
		$updatenum = 0;
		foreach($exceldata as $data){
			if($data[0]){
				$indata = [];
				$indata['aid'] = aid;
				$indata['bid'] = bid;
				$indata['kcid'] = $data[0];
				$indata['title'] = $data[1];
				$option_group =json_encode([$data[2],$data[3],$data[4],$data[5]]);
				$indata['option_group'] = $option_group;
				$indata['jiexi'] = $data[8];
				$indata['score'] = $data[7];
				$indata['right_option'] = $data[6];
				$indata['type'] =1;
				$indata['createtime'] = time();
				Db::name('kecheng_tiku')->insert($indata);
				$insertnum++;
			}
		}
		\app\commons\System::plog('导入题库');
		return json(['status'=>1,'msg'=>'成功导入'.$insertnum.'条数据']);
	}

    function defaultSet(){
        $set = Db::name('kecheng_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('kecheng_sysset')->insert(['aid'=>aid]);
        }
    }

    public function getChapter(){
        $id   = input('?id')?input('id'):0;
        $list = Db::name('kecheng_chapter')->where('kcid',$id)->where('status',1)->where('aid',aid)->field('id,name')->select()->toArray();
        return json(['status'=>1,'data'=>$list]);
    }
}
