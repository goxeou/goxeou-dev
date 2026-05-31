<?php


// +----------------------------------------------------------------------
// | 文章评论列表
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ArticlePinglun extends Common
{
    public function initialize(){
        parent::initialize();
        $this->defaultSet();
    }
	//晒图列表
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
			if(input('?param.st')) $where[] = ['status','=',input('param.st')];
			if(input('param.content')) $where[] = ['content','like','%'.input('param.content').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('article_pinglun')->where($where)->count();
			$datalist = Db::name('article_pinglun')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($datalist as $k=>$v){
				$v['title'] = Db::name('article')->where('id',$v['sid'])->value('name');
				$v['content'] = nl2br(getshowcontent($v['content']));
				$v['replycount'] = Db::name('article_pinglun_reply')->where('pid',$v['id'])->count();
				$datalist[$k] = $v;
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$datalist]);
		}
		$set = Db::name('admin_set')->where('aid',aid)->find();
		View::assign('set',$set);
		return View::fetch();
    }
	//审核
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		$score = input('post.givescore/d');
		$list = Db::name('article_pinglun')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->select()->toArray();
		foreach($list as $v){
			Db::name('article_pinglun')->where('aid',aid)->where('bid',bid)->where('id',$v['id'])->update(['status'=>$st,'score'=>$score]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('article_pinglun')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    function defaultSet(){
        $set = Db::name('article_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('article_set')->insert(['aid'=>aid,'bid'=>bid]);
        }
    }
}
