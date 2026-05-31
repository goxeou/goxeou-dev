<?php


//管理员中心 - 订单管理
namespace app\controllers;
use think\facade\Db;
class ApiAdminRestaurantQueue extends ApiAdmin
{
    public function index(){
        if(bid!=0){
            $business = Db::name('business')->where('aid',aid)->where('id',bid)->field('id,name,logo,content,pics,desc,tel,address,sales,start_hours,end_hours')->find();
            $business['pic'] = explode(',',$business['pics'])[0];
        }else{
            $business = Db::name('admin_set')->where('aid',aid)->field('id,name,logo,desc,tel,address')->find();
        }
        $queue_set = Db::name('restaurant_queue_sysset')->where('aid',aid)->where('bid',bid)->find();
        if($queue_set['status']==0){
            return $this->json(['status'=>0,'msg'=>'未开启排队']);
        }

        $clist = Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',bid)->where('status',1)->order('sort desc,id')->select()->toArray();
        foreach($clist as $k=>$v){
            $clist[$k]['waitnum'] = Db::name('restaurant_queue')->where('aid',aid)->where('cid',$v['id'])->where('status',0)->count();
            if($clist[$k]['waitnum'] == 0){
                $clist[$k]['need_minute'] = '--';
            }else{
                $clist[$k]['need_minute'] = $clist[$k]['waitnum'] * $v['per_minute'];
            }
        }
        $lastqueue = Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('status',1)->order('call_time desc')->find();

        //我的排队
        $myqueue = Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('mid',mid)->where('date',date('Y-m-d'))->where('status',0)->order('id desc')->find();
        if($myqueue){
            $category = Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',bid)->where('id',$myqueue['cid'])->find();
            $myqueue['beforenum'] = Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('id','<',$myqueue['id'])->where('date',date('Y-m-d'))->where('status',0)->count();
            $myqueue['need_minute'] = ($myqueue['beforenum']+1) * $category['per_minute'];
            $myqueue['cname'] = $category['name'];
        }else{
            $myjustqueue = Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('mid',mid)->where('status',1)->where('call_time','>',time()-60)->order('call_time desc')->find();
            if($myjustqueue){
                $category = Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',bid)->where('id',$myjustqueue['cid'])->find();
                $myjustqueue['cname'] = $category['name'];
            }
        }
        $is_show_quhao = false;
        $config = include(ROOT_PATH.'config.php');
		$authtoken = $config['authtoken'];


        $rdata = [];
        $rdata['status'] = 1;
        $rdata['business'] = $business;
        $rdata['lastQueue'] = $lastqueue;
        $rdata['clist'] = $clist;
        $rdata['myqueue'] = $myqueue;
        $rdata['is_show_quhao'] = $is_show_quhao;
        $rdata['myjustqueue'] = $myjustqueue;
		$rdata['token'] = md5(md5($authtoken.aid.bid));
        return $this->json($rdata);
    }

    public function categoryList()
    {
        $clist = Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',bid)->order('sort desc,id')->select()->toArray();

        $queue_set = Db::name('restaurant_queue_sysset')->where('aid',aid)->where('bid',bid)->find();

        $rdata['datalist'] = $clist;
        $rdata['set'] = $queue_set;
        return $this->json($rdata);
    }

    //编辑
    public function categoryEdit(){
        if(input('param.id')){
            $info = Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
        }else{
            $info = ['id'=>'','status'=>1, 'seat_min' => 1,'seat_max' =>1,'per_minute'=>1,'call_text' => '请[排队号]号顾客就餐', 'sort' => 0];
        }

        $rdata = [];
        $rdata['info'] = $info;
        return $this->json($rdata);
    }

    //编辑
    public function categorySave(){
        if(input('post.id')) $cate = Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
        $info = input('post.info/a');
        $data = array();
        $data['name'] = $info['name'];
        $data['code'] = $info['code'];
        $data['seat_min'] = $info['seat_min'];
        $data['seat_max'] = $info['seat_max'];
        $data['per_minute'] = $info['per_minute'];
        $data['call_text'] = $info['call_text'];
        $data['sort'] = $info['sort'];
        $data['status'] = $info['status'];

        if($cate){
            $data['update_time'] = time();
            Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',bid)->where('id',$cate['id'])->update($data);
            $id = $cate['id'];
            \app\commons\System::plog('餐饮排队队列编辑'.$id);
        }else{
            $data['aid'] = aid;
            $data['bid'] = bid;
            $data['create_time'] = time();
            $id = Db::name('restaurant_queue_category')->insertGetId($data);
            \app\commons\System::plog('餐饮排队队列编辑'.$id);
        }

        return json(['status'=>1,'msg'=>'操作成功']);
    }

    //删除
    public function categoryDel(){
        $id = input('post.id/d');
        Db::name('restaurant_queue_category')->where(['aid'=>aid,'bid'=>bid,'id'=>$id])->delete();
        Db::name('restaurant_queue')->where(['aid'=>aid,'bid'=>bid])->where('cid', $id)->delete();
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }

    public function setst()
    {
        $st = input('param.st/d');
        $queue_set = Db::name('restaurant_queue_sysset')->where('aid',aid)->where('bid',bid)->update(['status' => $st]);
        return $this->json(['status'=>1,'msg'=>'操作成功']);
    }

    //叫号
    public function jiaohao(){

        $cid = input('param.cid/d');//队列分类id
        $id = input('param.id/d');//队列id
        //当前分类的下一桌
        if($cid) {
            $queue = Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('cid',$cid)
                ->where('status',0)->order('create_time asc')->find();
        }
        //重复叫某号
        if($id) {
            $queue = Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
        }

        if($queue) {
            $update = ['status' => 1, 'call_time' => time()];
            Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('id',$queue['id'])->update($update);
        }
        return json(['status'=>1,'msg'=>'操作成功','queue'=>$queue]);
    }

    //过号
    public function guohao(){
        $id = input('param.id/d');//队列id
        if(!$id) {
            return json(['status'=>0,'msg'=>'参数错误']);
        }
        $queue = Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
        if($queue) {
            $update = ['status' => 2, 'expiry_time' => time()];
            Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('id',$queue['id'])->update($update);
        }
        return json(['status'=>1,'msg'=>'操作成功']);
    }
    public function quhao(){
        }
}