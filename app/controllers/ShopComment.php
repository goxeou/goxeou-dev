<?php


// +----------------------------------------------------------------------
// | 商城 商品评价
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ShopComment extends Common
{
	//评价列表
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
			if(input('param.content')) $where[] = ['content','like','%'.input('param.content').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			//dump($where);
			$count = 0 + Db::name('shop_comment')->where($where)->count();
			$data = Db::name('shop_comment')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//评价审核
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		$list = Db::name('shop_comment')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->select()->toArray();
		foreach($list as $v){
			Db::name('shop_comment')->where('aid',aid)->where('bid',bid)->where('id',$v['id'])->update(['status'=>$st]);
			$proComment = Db::name('shop_comment')->where('aid',aid)->where('bid',bid)->where('proid',$v['proid'])->where('status',1)->avg('score');
			$comment_num = Db::name('shop_comment')->where('aid',aid)->where('bid',bid)->where('proid',$v['proid'])->where('status',1)->count();
			if($comment_num==0) $proComment = 5;
			$haonum = Db::name('shop_comment')->where('aid',aid)->where('bid',bid)->where('proid',$v['proid'])->where('status',1)->where('score','>',3)->count(); //好评数
			if($comment_num > 0){
				$haopercent = $haonum/$comment_num*100;
			}else{
				$haopercent = 100;
			}
			Db::name('shop_product')->where('aid',aid)->where('bid',bid)->where('id',$v['proid'])->update(['comment_score'=>$proComment,'comment_num'=>$comment_num,'comment_haopercent'=>$haopercent]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//评价详情
	public function getdetail(){
		$detail= Db::name('shop_comment')->where('aid',aid)->where('bid',bid)->where('id',$_POST['id'])->find();
		if($detail['content_pic']) $detail['content_pic'] = explode(',',$detail['content_pic']);
		$member = Db::name('member')->where('aid',aid)->where('id',$detail['mid'])->find();
		if(!$member) $member = ['nickname'=>$detail['nickname'],'headimg'=>$detail['headimg']];
		return json(['status'=>1,'detail'=>$detail,'member'=>$member]);
	}
	//评价回复
	public function reply(){
		$id = input('post.id/d');
		Db::name('shop_comment')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['reply_content'=>$_POST['content'],'reply_time'=>time()]);
		\app\commons\System::plog('商城商品评价回复'.$id);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('shop_comment')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('商城商品评价删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	
    public function edit(){
		if(input('param.id')){
			$info = Db::name('shop_comment')->where('aid',aid)->where('id',input('param.id/d'))->find();
			$info['createtime'] = date('Y-m-d H:i:s',$info['createtime']);
		}else{
			$info = [];
			$info['createtime'] = date('Y-m-d H:i:s');
		}
		View::assign('info',$info);
		return View::fetch();
    }
    //保存
    public function save(){
		if(input('post.id')) $detail = Db::name('shop_comment')->where('aid',aid)->where('id',input('post.id/d'))->find();
		$info = input('post.info/a');
		if(empty($info['proid'])){
			return json(['status'=>0,'msg'=>'请选择商品']);
		}
		$data = array();
		$data['proid'] = $info['proid'];
		$data['proname'] = $info['proname'];
		$data['propic'] = $info['propic'];
		$data['ggid'] = $info['ggid'];
		$data['ggname'] = $info['ggname'];
		$data['score'] = $info['score'];
		$data['content'] = $info['content'];
		$data['content_pic'] = $info['content_pic'];
		$data['headimg'] = $info['headimg'] ? $info['headimg'] : PRE_URL.'/static/img/touxiang.png';
		$data['nickname'] = $info['nickname'];

		if(!$info['createtime']) $data['createtime'] = time();
		else $data['createtime'] = strtotime($info['createtime']);

		if($detail){
			Db::name('shop_comment')->where('aid',aid)->where('id',$detail['id'])->update($data);
			$proid = $detail['id'];
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
			$id = Db::name('shop_comment')->insertGetId($data);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
}
