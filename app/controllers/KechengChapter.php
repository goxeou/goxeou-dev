<?php


// +----------------------------------------------------------------------
// | 知识付费-章节管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class KechengChapter extends Common
{
    public function initialize(){
        parent::initialize();
        $this->defaultSet();
    }
	//章节列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(getcustom('kecheng_lecturer')){
				//是否关联章节 1：关联章节 2：不关联章节（不关联章节默认创建一个章节）
				$where[] = ['chaptertype','=',1];
			}
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
			if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];

			$count = 0 + Db::name('kecheng_chapter')->where($where)->count();
			$data = Db::name('kecheng_chapter')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$typearr = ['1'=>'图文','2'=>'音频','3'=>'视频'];
			foreach($data as $k=>$v){
				$cate = Db::name('kecheng_list')->Field('id,name')->where('aid',aid)->where('id',$v['kcid'])->find(); 
				$data[$k]['kcname'] = $cate['name'];
				$data[$k]['zjtype'] = $typearr[$v['kctype']];
				 
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('kecheng_list')->Field('id,name')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->select()->toArray(); 

		$kcid = input('param.kcid');
		View::assign('kcid',$kcid);
		View::assign('clist',$clist);
		View::assign('sysset',$sysset);
		View::assign('systimeset',$systimeset);
		return View::fetch();
    }
	//编辑章节
	public function edit(){
		if(input('param.id')){
			$info = Db::name('kecheng_chapter')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('课程不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
		}else{
			$info = ['kcid'=>input('param.kcid')];
		}

		//课程列表
		$kclist = Db::name('kecheng_list')->Field('id,name')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->select()->toArray(); 
		View::assign('kclist',$kclist);
		View::assign('info',$info);
		return View::fetch();
	}
	//保存课程章节
	public function save(){
		if(input('post.id')){
			$product = Db::name('kecheng_chapter')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
			if(!$product) showmsg('章节不存在');
			if(bid != 0 && $product['bid']!=bid) showmsg('无权限操作');
			if(getcustom('kecheng_lecturer')){
				if($product['chaptertype'] == 2){
					return json(['status'=>0,'msg'=>'非关联章节不能单独编辑，请到课程中进行编辑']);
				}
	        }
		}
		$info = input('post.info/a');
		$info['detail'] = \app\commons\Common::geteditorcontent($info['detail']);
		$data = array();
		$data['name'] = $info['name'];
		$data['pic'] = $info['pic'];
		$data['kcid'] = $info['kcid'];
		$data['sort'] = $info['sort'];
		$data['status'] = $info['status'];
		$data['detail'] = $info['detail'];
        $data['jumpurl'] = $info['jumpurl'];
		if(!$product) $data['createtime'] = time();
		$data['kctype'] = $info['kctype'];
		if($data['kctype']==1) $data['video_duration'] = '';
		if($data['kctype']==2) $data['video_duration'] = $info['voice_duration'];
		if($data['kctype']==3) $data['video_duration'] = $info['video_duration'];
		if($data['kctype']==1){
			$data['voice_url'] = '';
			$data['video_url'] = '';
		}
		$data['ismianfei'] = $info['ismianfei'];

    if ($data['ismianfei'] == 1 && getcustom('video_free_time')) {
      $data['mianfei_unit'] = $info['mianfei_unit'];
      $mianfei_time = intval($info['mianfei_time']);
      $max_time = intval($info['video_duration']);
      if (!$info['mianfei_time'] || $mianfei_time > $max_time || ($data['mianfei_unit'] == 2 && $info['mianfei_time'] * 60 > $max_time)) {
        $data['mianfei_time'] = $max_time;
        $data['mianfei_unit'] = 1;
      } else {
        $data['mianfei_time'] = $mianfei_time;
      }
    }

    if($data['kctype']==2){
			$data['voice_url'] = $info['voice_url'];
			$data['video_url'] = '';
		}
		if($data['kctype']==3){
			$data['voice_url'] = '';
			$data['video_url'] = $info['video_url'];
		}
		$data['isjinzhi'] = $info['isjinzhi'];
		if($product){
			Db::name('kecheng_chapter')->where('aid',aid)->where('id',$product['id'])->update($data);
			$proid = $product['id'];
			\app\commons\System::plog('章节内容编辑'.$proid);
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
			$proid = Db::name('kecheng_chapter')->insertGetId($data);
			\app\commons\System::plog('章节内容编辑'.$proid);
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
		Db::name('kecheng_chapter')->where($where)->update(['status'=>$st]);
		\app\commons\System::plog('课程章节编辑'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('kecheng_chapter')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
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
		$prolist = Db::name('kecheng_chapter')->where($where)->select();
		foreach($prolist as $pro){
			Db::name('kecheng_chapter')->where('id',$pro['id'])->delete();
		}
		\app\commons\System::plog('课程章节删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    function defaultSet(){
        $set = Db::name('kecheng_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('kecheng_sysset')->insert(['aid'=>aid]);
        }
    }
}
