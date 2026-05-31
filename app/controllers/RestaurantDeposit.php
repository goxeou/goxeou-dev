<?php


//寄存记录
namespace app\controllers;

use app\models\RestaurantQueueModel;
use think\facade\Db;
use think\facade\View;

class RestaurantDeposit extends Common
{

    public function initialize(){
        parent::initialize();
    }

    //寄存记录
    public function index()
    {
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
            if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
            if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
            if(input('param.linkman')) $where[] = ['linkman','like','%'.input('param.linkman').'%'];
            if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
            if(input('param.createtime') ){
                $ctime = explode(' ~ ',input('param.createtime'));
                $where[] = ['createtime','>=',strtotime($ctime[0])];
                $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
            }
            if(input('?param.status') && input('param.status')!==''){
                $where[] = ['status','=',input('param.status')];
            }
            $count = 0 + Db::name('restaurant_deposit_order')->where($where)->count();
			$data = Db::name('restaurant_deposit_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$member = Db::name('member')->where('id',$v['mid'])->find();
				if($member){
					$data[$k]['headimg'] = $member['headimg'];
					$data[$k]['nickname'] = $member['nickname'];
				}else{
					$data[$k]['headimg'] = '';
					$data[$k]['nickname'] = '';
				}
			}

            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        $this->defaultSet();
        return View::fetch();
    }
    //寄存记录
    public function log()
    {
        if(request()->isAjax()){
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id desc';
            }
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            if(input('param.id')) $where[] = ['order_id','=',input('param.id')];
            if(input('param.createtime') ){
                $ctime = explode(' ~ ',input('param.createtime'));
                $where[] = ['createtime','>=',strtotime($ctime[0])];
                $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
            }
            if(input('?param.status') && input('param.status')!==''){
                $where[] = ['status','=',input('param.status')];
            }
            if(input('?param.type') && input('param.type')!==''){
                $where[] = ['type','=',input('param.type')];
            }
            $count = 0 + Db::name('restaurant_deposit_order_log')->where($where)->count();
            $data = Db::name('restaurant_deposit_order_log')->where($where)->page(1,100)->order($order)->select()->toArray();

            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
	//改状态
	public function setst(){
		$id = input('post.id/d');
		$st = input('post.st/d');
		$order = Db::name('restaurant_deposit_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
		if(empty($order)) {
            return json(['status'=>0,'msg'=>'数据不存在']);
        }
		Db::name('restaurant_deposit_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['status'=>$st]);
		if($st == 1){
		    //审核通过
            $log = [
                'aid'=>$order['aid'],
                'bid'=>$order['bid'],
                'order_id' => $order['id'],
                'mid' => $order['mid'],
                'num' => $order['num'],
                'type'=>0,//0存入
                'createtime' => time(),
                'platform' => 'admin',
                'remark' => '审核通过',
                'waiter_id' => uid
            ];
            \db('restaurant_deposit_order_log')->insert($log);
        }
		if($st == 2){
            //取走
            $log = [
                'aid'=>$order['aid'],
                'bid'=>$order['bid'],
                'order_id' => $order['id'],
                'mid' => $order['mid'],
                'num' => $order['num'],
                'type'=>1,//0存入
                'createtime' => time(),
                'platform' => 'admin',
                'remark' => '后台取走',
                'waiter_id' => uid
            ];
            \db('restaurant_deposit_order_log')->insert($log);
			Db::name('restaurant_deposit_order')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['takeout_time'=>time()]);
		}
        return json(['status'=>1,'msg'=>'操作成功']);
	}

    //设置备注
    public function setRemark(){
        $orderid = input('post.orderid/d');
        $content = input('post.content');
        Db::name('restaurant_deposit_order')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['remark'=>$content]);
        return json(['status'=>1,'msg'=>'设置完成']);
    }
    //删除
    public function del(){
		$ids = input('post.ids/a');
        Db::name('restaurant_deposit_order')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('寄存记录删除'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }

	//添加寄存
	public function edit(){
		if(request()->isAjax()){
			$info = input('post.info/a');
			if($info['id']){
				Db::name('restaurant_deposit_order')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
				\app\commons\System::plog('修改寄存记录'.$info['id']);
			}else{
				$info['aid'] = aid;
				$info['bid'] = bid;
				$info['status'] = 1;
				$info['createtime'] = time();
				$id = Db::name('restaurant_deposit_order')->insertGetId($info);
				\app\commons\System::plog('添加寄存'.$id);
                $log = [
                    'aid'=>$info['aid'],
                    'bid'=>$info['bid'],
                    'order_id' => $id,
                    'mid' => $info['mid'],
                    'num' => $info['num'],
                    'type'=>0,//0存入
                    'createtime' => time(),
                    'platform' => 'admin',
                    'remark' => '后台存入',
                    'waiter_id' => uid
                ];
                \db('restaurant_deposit_order_log')->insert($log);
			}
			return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
		}
		if(input('param.id')){
			$info = Db::name('restaurant_deposit_order')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		View::assign('info',$info);
        $this->defaultSet();
		return View::fetch();
	}

	public function set(){
		if(request()->isAjax()){
			$info = input('post.info/a');
			Db::name('restaurant_deposit_sysset')->where('aid',aid)->where('bid',bid)->update($info);
			return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
		}
		$info = Db::name('restaurant_deposit_sysset')->where('aid',aid)->where('bid',bid)->find();
		if(!$info){
			Db::name('restaurant_deposit_sysset')->insert(['aid'=>aid,'bid'=>bid]);
			$info = Db::name('restaurant_takeaway_sysset')->where('aid',aid)->where('bid',bid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
	}
    function defaultSet(){
        $set = Db::name('restaurant_deposit_sysset')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('restaurant_deposit_sysset')->insert(['aid'=>aid,'bid'=>bid]);
        }
    }
}