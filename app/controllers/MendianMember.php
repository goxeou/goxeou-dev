<?php


// +----------------------------------------------------------------------
// |门店取货员
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class MendianMember extends Common
{
    public function initialize(){
        parent::initialize();
        if(bid > 0) showmsg('无访问权限');
    }
    //列表
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
            if($this->mdid){
                $where[] = ['mdid','=',$this->mdid];
            }
            $where[] = ['aid','=',aid];
            $where[] = ['is_del','=',0];
            if(input('param.realname')) $where[] = ['realname','like','%'.input('param.realname').'%'];
            if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];

            $count = 0 + Db::name('mendian_member')->where($where)->count();

            $data = Db::name('mendian_member')->where($where)->page($page,$limit)->order($order)->select()->toArray();

            foreach($data as&$v){
                $pszCount = Db::name('xixie_order')->where('psid',$v['id'])->where('buy_type',1)->where('aid',aid)->where('status','in','2,3,4')->count();
                $ywcCount = Db::name('xixie_order')->where('psid',$v['id'])->where('buy_type',1)->where('status',5)->where('aid',aid)->count();
                $v['pszCount'] = $pszCount;
                $v['ywcCount'] = $ywcCount;
            }
            unset($v);
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        View::assign('mdid',$this->mdid?$this->mdid:0);
        return View::fetch();
    }
    //编辑
    public function edit(){
        if(!$this->mdid){
            return json(['status'=>0,'msg'=>'取货人员操作只能门店人员管理人员进行操作']);
        }
        if(input('param.id')){
            $info = Db::name('mendian_member')->where('id',input('param.id/d'))->where('mdid',$this->mdid)->where('aid',aid)->where('is_del',0)->find();
        }else{
            $info = array('id'=>'');
        }
        View::assign('info',$info);
        return View::fetch();
    }
    public function save(){
        $info = input('post.info/a');
        if(!$this->mdid){
            return json(['status'=>0,'msg'=>'取货人员操作只能门店人员管理人员进行操作']);
        }

        if($info['pwd']){
            $info['pwd'] = md5($info['pwd']);
        }else{
            unset($info['pwd']);
        }

        if(empty($info['tel'])){
            return json(['status'=>0,'msg'=>'请填写手机号']);
        }
        $info['realname'] = trim($info['realname']);
        $info['tel']      = trim($info['tel']);

        //查询手机号是否冲突
        $tel = Db::name('mendian_member')->where('tel',$info['tel'])->where('aid',aid)->where('is_del',0)->field('id,mdid')->find();
        if($info['id']){
            if($tel){
                if($tel['mdid'] != $this->mdid){
                    return json(['status'=>0,'msg'=>'添加失败，此手机号已在其他门店中使用,请更换其他手机号']);
                }else{
                    if($tel['id'] != $info['id']){
                        return json(['status'=>0,'msg'=>'添加失败，此手机号门店内已添加过']);
                    }
                }
            }
            Db::name('mendian_member')->where('aid',aid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog('编辑门店取货员'.$info['id']);
        }else{
            if($tel){
                if($tel['mdid'] != $this->mdid){
                    return json(['status'=>0,'msg'=>'添加失败，此手机号已在其他门店中使用']);
                }else{
                    return json(['status'=>0,'msg'=>'添加失败，此手机号门店内已添加过']);
                }
            }
            $info['mdid'] = $this->mdid;
            $info['aid']  = aid;
            $info['createtime'] = time();
            $id = Db::name('mendian_member')->insertGetId($info);
            \app\commons\System::plog('添加门店取货员'.$id);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
    //改状态
    public function setst(){
        if(!$this->mdid){
            return json(['status'=>0,'msg'=>'取货人员操作只能门店人员管理人员进行操作']);
        }
        $st = input('post.st/d');
        $ids = input('post.ids/a');
        Db::name('mendian_member')->where('mdid',$this->mdid)->where('aid',aid)->where('id','in',$ids)->where('is_del',0)->update(['status'=>$st]);
        \app\commons\System::plog('门店取货员改状态'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'操作成功']);
    }
    //删除
    public function del(){
        if(!$this->mdid){
            return json(['status'=>0,'msg'=>'取货人员操作只能门店人员管理人员进行操作']);
        }
        $ids = input('post.ids/a');
        Db::name('mendian_member')->where('mdid',$this->mdid)->where('aid',aid)->where('id','in',$ids)->update(['is_del'=>1]);
        \app\commons\System::plog('门店取货员删除'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
}
