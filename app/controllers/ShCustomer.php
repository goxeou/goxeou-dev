<?php
// +----------------------------------------------------------------------
// | custom_file(customer) 首衡-客户管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class ShCustomer extends Common
{
    //列表 管理员可以看到所有的 商户可以看同步给多商户和自己创建的
    public function index(){
        $page = input('param.page',1);
        $limit = input('param.limit',4);
        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order');
        }else{
            $order = 'sort desc,id';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        $bid = bid;
        if(bid>0){
            $where[] = Db::raw("bid={$bid} OR find_in_set({$bid},`ext_bids`)");
        }
        $search = [];
        if(input('param.name')){
            $search[] = ['name|number|remark|tel','like','%'.input('param.name').'%'];
        }
        if(input('param.id/d')){
            $search[] = ['id','=',input('param.id/d')];
        }
        if($search){
            $where = array_merge($where,$search);
        }else{
            $where[] = ['pid','=',0];
        }
        $data = [];
        $searchData = [];
        $pageCids = [];
        $list = Db::name('sh_customer')->where($where)->paginate(['page'=>$page,'list_rows'=>10]);
        $cate0 = $list->items();
        foreach($cate0 as $c0){
            $c0['isedit'] = 0;
            if(bid==0 || $c0['bid']==bid){
                $c0['isedit'] = 1;
            }
            if($c0['pid']==0){
                $cate1 = Db::name('sh_customer')->where($search)->where('pid',$c0['id'])->order($order)->select()->toArray();
                foreach($cate1 as $k1=>&$c1){
                    $pageCids[] = $c1['id'];
                    $c1['isedit'] = 0;
                    if(bid==0 || $c1['bid']==bid){
                        $c1['isedit'] = 1;
                    }
                    if($k1 < count($cate1)-1){
                        $c1['name'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;├ </span>'.$c1['name'];
                    }else{
                        $c1['name'] = '<span style="color:#aaa">&nbsp;&nbsp;&nbsp;&nbsp;└ </span>'.$c1['name'];
                    }
                }
                $c0['child'] = $cate1;
                $data[] = $c0;
            }else{
                $searchData[$c0['id']] = $c0;
            }
        }
        foreach ($searchData as $sk=>$sv){
            if(!in_array($sk,$pageCids)){
                if($sv['pid']){
                    $pname = Db::name('sh_customer')->where('id',$sv['pid'])->value('name');
                    if ($pname){
                        $sv['name'] = $pname.'├'.$sv['name'];
                    }
                }
                $data[] = $sv;
            }
        }
        $showpage = $list->render();
        if(strpos($_SERVER['REQUEST_URI'],'business.php')!==false){
            $burl = PRE_URL.'/business.php?s=ShCustomer/index';
            $showpage = str_replace('/business.php?page=',$burl.'/page/',$showpage);
        }else{
            $showpage = str_replace('/?page=',url('ShCustomer/index').'/page/',$showpage);
        }

        $where = input('param.');
        $param = [];
        if($where){
            foreach ($where as $k=>$value){
                if(in_array($k,['id','name'])){
                    $param[] = $k.'/'.$value;
                }
            }
        }
        View::assign('data',$data);
        View::assign('page',$showpage);
        View::assign('paramstr',implode('/',$param));
        View::assign('datawhere',$where);
        return View::fetch();
    }
    //编辑
    public function edit(){
        if(input('param.id')){
            $where = [];
            $where[] = ['aid','=',aid];
            if(bid>0){
                $where[] = ['bid','=',bid];
            }
            $info = Db::name('sh_customer')->where($where)->where('id',input('param.id/d'))->find();
            if(getcustom('customer_peisonguser')){
                $peisong_uname = '';
                if($info['peisong_uid']){
                    $peisong_uname = Db::name('peisong_user')->where('aid',aid)->where('id',$info['peisong_uid'])->value('realname');
                }
                $info['peisong_uname'] = $peisong_uname??'';
            }
            $info['ext_bids'] = $info['ext_bids']?explode(',',$info['ext_bids']):[];
        }else {
            $info = array('id' => '');
        }
        $businesslist = [];
        $where = [];
        $where[] = ['aid','=',aid];
        $bid = bid;
        if(bid>0){
            $where[] = Db::raw("bid={$bid} OR find_in_set({$bid},`ext_bids`)");
        }else{
            $businesslist = Db::name('business')->where('aid',aid)->select()->toArray();
        }
        $where[] = ['pid','=',0];
        $customerlist = Db::name('sh_customer')->where($where)->order('sort desc,id desc')->select()->toArray();
        View::assign('businesslist',$businesslist);
        View::assign('bid',bid);
        View::assign('info',$info);
        View::assign('customerlist',$customerlist);
        return View::fetch();
    }
    public function getDetail(){
        $where = [];
        $where[] = ['aid','=',aid];
        $bid = bid;
        if(bid>0){
            $where[] = Db::raw("bid={$bid} OR find_in_set({$bid},`ext_bids`)");
        }
        $info = Db::name('sh_customer')->where($where)->where('id',input('param.id/d'))->find();
        if(getcustom('customer_peisonguser')){
            $peisong_uname = '';
            if($info['peisong_uid']){
                $peisong_uname = Db::name('peisong_user')->where('aid',aid)->where('id',$info['peisong_uid'])->value('realname');
            }
            $info['peisong_uname'] = $peisong_uname??'';
        }
        return json(['status'=>1,'data'=>$info]);
    }
    //保存
    public function save(){
        $info = input('post.info/a');
        if($info['pid']>0 || bid>0){
            $info['ext_bids'] = '';
        }
        $where = [];
        $where[] = ['aid','=',aid];
        if(bid>0){
            $where[] = ['bid','=',bid];
        }
        if($info['id']){
            $where[] = ['id','=',$info['id']];
            $info['updatetime'] = time();
            $res = Db::name('sh_customer')->where($where)->update($info);
            //如果是父级账号 且绑定了会员，则下面的子客户未绑定账号的 使用父级绑定
            if(empty($info['pid']) && $info['mid']){
                Db::name('sh_customer')->where('pid',$info['id'])->where('mid',0)->update(['mid'=>$info['mid']]);
            }
            if($res){
                \app\commons\System::plog('编辑客户'.$info['id']);
            }else{
                return json(['status'=>0,'msg'=>'不可修改该记录']);
            }
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['createtime'] = time();
            $id = Db::name('sh_customer')->insertGetId($info);
            \app\commons\System::plog('添加客户'.$id);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
    public function excel(){
        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order');
        }else{
            $order = 'sort desc,id';
        }
        $page = input('param.page');
        $limit = input('param.limit');
        $title = array();
        $title[] = 'ID';
        $title[] = '姓名';
        $title[] = '电话';
        $title[] = '地址';
        $title[] = '客户编号';
        $title[] = '备注';
        $where = [];
        $where[] = ['aid','=',aid];
        $bid = bid;
        if(bid>0){
            $where[] = Db::raw("bid={$bid} OR find_in_set({$bid},`ext_bids`)");
        }
        $search = [];
        $search[] = ['aid','=',aid];
        if(input('param.name')){
            $search[] = ['name|number|remark|tel','like','%'.input('param.name').'%'];
        }
        if(input('param.id/d')){
            $search[] = ['id','=',input('param.id/d')];
        }
        if($search){
            $where = array_merge($where,$search);
        }else{
            $where[] = ['pid','=',0];
        }
        $data = [];
        $searchData = [];
        $pageCids = [];
        $fileds = 'id,name,tel,address,number,remark';
        $cate0 = Db::name('sh_customer')->where($where)->order($order)->field($fileds)->page($page,$limit)->select()->toArray();
        $count = Db::name('sh_customer')->where($where)->order($order)->field($fileds)->count();
        foreach($cate0 as $c0){
            $c0['isedit'] = 0;
            if(bid==0 || $c0['bid']==bid){
                $c0['isedit'] = 1;
            }
            if($c0['pid']==0){
                $data[] = $c0;
                $cate1 = Db::name('sh_customer')->where($search)->where('pid',$c0['id'])->field($fileds)->order($order)->select()->toArray();
                foreach($cate1 as $k1=>$c1){
                    $pageCids[] = $c1['id'];
                    $c1['isedit'] = 0;
                    if(bid==0 || $c1['bid']==bid){
                        $c1['isedit'] = 1;
                    }
                    $c1['name'] = ' ├'.$c1['name'];
                    $data[] = $c1;
                }
            }else{
                $searchData[$c0['id']] = $c0;
            }
        }
        foreach ($searchData as $sk=>$sv){
            if(!in_array($sk,$pageCids)){
                if($sv['pid']){
                    $pname = Db::name('sh_customer')->where('id',$sv['pid'])->value('name');
                    if ($pname){
                        $sv['name'] = $pname.'├'.$sv['name'];
                    }
                }
                $data[] = $sv;
            }
        }
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
        \app\commons\System::plog('导出客户信息');
        $this->export_excel($title,$data);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        if(!$ids) $ids = array(input('post.id/d'));
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['id','in',$ids];
        if(bid>0){
            $where[] = ['bid','=',bid];
        }
        $res  = Db::name('sh_customer')->where($where)->delete();
        if($res){
            \app\commons\System::plog('删除客户'.implode(',',$ids));
            return json(['status'=>1,'msg'=>'删除成功']);
        }else{
            return json(['status'=>0,'msg'=>'无法删除该记录']);
        }
    }
}
