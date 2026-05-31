<?php
// +----------------------------------------------------------------------
// | custom_file(fenhong_jiaquan_bylevel) 等级门店收款汇总
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class MendianShopOrder extends Common
{
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
            $where[] = ['bid','=',$this->bid];
            if(input('param.mid')){
                $where[] = ['mid','=',input('param.mid')];
            }
            if(input('param.name')) $where[] = ['remark','like','%'.$_GET['name'].'%'];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['createtime','>=',strtotime($ctime[0])];
                $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
            }
            $count = 0 + Db::name('mendian_shop_order')->where($where)->count();
            $data = Db::name('mendian_shop_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach ($data as $k=>$v){
                $data[$k]['createtime'] = date('Y-m-d H:i',$v['createtime']);
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        $where = input('param.',['time'=>time()]);
        View::assign('wheredata',json_encode($where));
        return View::fetch();
    }
    //编辑
    public function edit(){
        if(input('param.id')){
            $info = Db::name('mendian_shop_order')->where('aid',aid)->where('bid',$this->bid)->where('id',input('param.id/d'))->find();
        }else{
            $info = array('id'=>0,'date'=>date('Ymd',strtotime(date('Y-m-d 00:00:00',time()))-10));
        }
        $adminset = Db::name('admin_set')->where('aid',aid)->field('id,fenhong_jqjs_time')->find();
        View::assign('adminset',$adminset);
        View::assign('info',$info);
        return View::fetch();
    }
    //保存
    public function save(){
        $info = input('post.info/a');
        if($info['id']){
            $exist = Db::name('mendian_shop_order')->where('aid',aid)->where('bid',$this->bid)->where('id',$info['id'])->find();
            if($exist['status']==1){
                return json(['status'=>0,'msg'=>'已结算数据不可修改']);
            }
            Db::name('mendian_shop_order')->where('aid',aid)->where('bid',$this->bid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog('修改门店收款'.$info['id']);
        }else{
            $date = date('Ymd',strtotime(date('Y-m-d 00:00:00',time()))-10);
            $exist = Db::name('mendian_shop_order')->where('aid',aid)->where('bid',$this->bid)->where('date',$date)->count();
            if($exist){
                return json(['status'=>0,'msg'=>'该天数据已经存在']);
            }
            $info['aid'] = aid;
            $info['bid'] = $this->bid;
            $info['date'] = $date;
            $info['createtime'] = time();
            $id = Db::name('mendian_shop_order')->insertGetId($info);
            \app\commons\System::plog('添加门店收款'.$id);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }

	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('mendian_shop_order')->where('aid',aid)->where('bid',$this->bid)->where('id','in',$ids)->where('status',0)->delete();
		\app\commons\System::plog('删除门店收款记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}