<?php


// +----------------------------------------------------------------------
// | 短视频评论列表
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ShortvideoComment extends Common
{
	//评论列表
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
			$where['bid'] = ['bid','=',bid];
			if(input('param.vid')) $where[] = ['vid','=',input('param.vid')];
			if(input('?param.st')) $where[] = ['status','=',input('param.st')];
			if(input('param.content')) $where[] = ['content','like','%'.input('param.content').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('shortvideo_comment')->where($where)->count();
			$datalist = Db::name('shortvideo_comment')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($datalist as $k=>$v){
				$v['title'] = Db::name('shortvideo')->where('id',$v['vid'])->value('name');
				$v['content'] = nl2br(getshowcontent($v['content']));
				$v['replycount'] = Db::name('shortvideo_comment_reply')->where('pid',$v['id'])->count();
				$datalist[$k] = $v;
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$datalist]);
		}
        $this->defaultSet();
		return View::fetch();
    }
	//审核
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		$score = input('post.givescore/d');
		$list = Db::name('shortvideo_comment')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->select()->toArray();
		foreach($list as $v){
			Db::name('shortvideo_comment')->where('aid',aid)->where('bid',bid)->where('id',$v['id'])->update(['status'=>$st,'score'=>$score]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('shortvideo_comment')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	public function edit(){
		}
    public function save(){

        $info = input('post.info/a');

        $id   = $info['id'];
        unset($info['id']);

        if($id){
        	return json(['status'=>0,'msg'=>'评论暂不支持修改']);
            $data = [];
            $data = $info;
            $data['content']    = json_encode($data_index);
            $data['updatetime'] = time();
            $up = Db::name('shortvideo_comment')->where('id',$id)->where('aid',aid)->where('bid',bid)->update($data);
            if(!$up){
                return json(['status'=>0,'msg'=>'保存失败']);
            }
        }else{
            $data = [];
            $data = $info;
            $data['aid']        = aid;
            $data['bid']        = bid;
            $data['status']     = 1;
            $data['createtime'] = time();
            $insert = Db::name('shortvideo_comment')->insert($data);
            if(!$insert){
                return json(['status'=>0,'msg'=>'保存失败']);
            }
        }
        \app\commons\System::plog('编辑礼包');
        return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
    }
    function defaultSet(){
        $set = Db::name('shortvideo_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('shortvideo_sysset')->insert(['aid'=>aid]);
        }
    }
}
