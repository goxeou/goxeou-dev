<?php


// +----------------------------------------------------------------------
// | 预约服务 商品评价
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class YuyueComment extends Common
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
			$count = 0 + Db::name('yuyue_comment')->where($where)->count();
			$data = Db::name('yuyue_comment')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
        $this->defaultSet();
		return View::fetch();
    }
	//评价审核
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		$list = Db::name('yuyue_comment')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->select()->toArray();
		foreach($list as $v){
			Db::name('yuyue_comment')->where('aid',aid)->where('bid',bid)->where('id',$v['id'])->update(['status'=>$st]);
			$proComment = Db::name('yuyue_comment')->where('aid',aid)->where('bid',bid)->where('proid',$v['proid'])->where('status',1)->avg('score');
			$comment_num = Db::name('yuyue_comment')->where('aid',aid)->where('bid',bid)->where('proid',$v['proid'])->where('status',1)->count();
			if($comment_num==0) $proComment = 5;
			$haonum = Db::name('yuyue_comment')->where('aid',aid)->where('bid',bid)->where('proid',$v['proid'])->where('status',1)->where('score','>',3)->count(); //好评数
			if($comment_num > 0){
				$haopercent = $haonum/$comment_num*100;
			}else{
				$haopercent = 100;
			}
			Db::name('yuyue_product')->where('aid',aid)->where('bid',bid)->where('id',$v['proid'])->update(['comment_score'=>$proComment,'comment_num'=>$comment_num,'comment_haopercent'=>$haopercent]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//评价详情
	public function getdetail(){
		$detail= Db::name('yuyue_comment')->where('aid',aid)->where('bid',bid)->where('id',$_POST['id'])->find();
		if($detail['content_pic']) $detail['content_pic'] = explode(',',$detail['content_pic']);
		$member = Db::name('member')->where('aid',aid)->where('id',$detail['mid'])->find();
		if(!$member) $member = ['nickname'=>$detail['nickname'],'headimg'=>$detail['headimg']];
		return json(['status'=>1,'detail'=>$detail,'member'=>$member]);
	}
	//评价回复
	public function reply(){
		$id = input('post.id/d');
		Db::name('yuyue_comment')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['reply_content'=>$_POST['content'],'reply_time'=>time()]);
		\app\commons\System::plog('预约服务商品评价回复'.$id);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('yuyue_comment')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('预约服务商品评价删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

    public function edit()
    {
        }

    //保存
    public function save(){
        }
    function defaultSet(){
        $set = Db::name('yuyue_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('yuyue_set')->insert(['aid'=>aid,'bid' => bid]);
        }
    }
}
