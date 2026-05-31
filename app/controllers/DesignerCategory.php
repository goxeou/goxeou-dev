<?php


//custom_file(design_cat)
// +----------------------------------------------------------------------
// | 设计分类
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class DesignerCategory extends Common
{
    public $showtype = false;//是否显示类型
    public function initialize(){
        parent::initialize();
        if(getcustom('design_template_cat_create')){
            if(aid !=1 && !bid &&( $this->auth_data == 'all' || in_array('AddTemplateCat',$this->auth_data))){
                $this->showtype = true;
            }
        }
    }
    //分组列表
    public function index(){
        $type =  input('param.type');
        if(!$this->showtype){
            $type = $type??0;
        }
        $into =  input('param.into',0);
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
            $where[] = ['bid','=',bid];
            $where[] = ['is_del','=',0];
            if(!$this->showtype){
                $where[] = ['type','=',$type];
            }else{
                if(input('?param.type') && $type!==''){
                    $where[] = ['type','=',$type];
                }
            }

            if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
            $count = 0 + Db::name('designerpage_category')->where($where)->count();
            $data = Db::name('designerpage_category')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            if($data){
                foreach($data as &$dv){
                    $dv['into'] = $into;
                }
                unset($dv);
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }

        $typeparam = '';
        if(getcustom('design_template')){
            $typeparam  = "&type=".$type.'&into='.$into;
        }

        View::assign('type',$type);
        View::assign('into',$into);
        View::assign('typeparam',$typeparam);
        View::assign('showtype',$this->showtype);
        return View::fetch();
    }
    //编辑
    public function edit(){
        $type =  input('param.type')?input('type/d'):0;
        $into =  input('param.into')?input('into/d'):0;
        if(input('param.id')){
            $where = [];
            $where[] = ['id','=',input('param.id/d')];
            if(!$this->showtype){
                $where[] = ['type','=',$type];
            }
            $where[] = ['is_del','=',0];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            $info = Db::name('designerpage_category')->where($where)->find();
        }else{
            $info = array('id'=>'','type'=>$type);
        }

        View::assign('type',$type);
        View::assign('into',$into);

        $typeparam  = "&type=".$type.'&into='.$into;
        View::assign('typeparam',$typeparam);

        View::assign('info',$info);
        View::assign('aid',aid);
        View::assign('bid',bid);

        View::assign('showtype',$this->showtype);
        return View::fetch();
    }
    //保存
    public function save(){
        $type =  input('param.type')?input('type/d'):0;
        $into =  input('param.into')?input('into/d'):0;

        $info = input('post.info/a');
        if(aid != 1 && !$this->showtype && $info['type'] == 1){
            return json(['status'=>0,'msg'=>'无添加模板库类型权限']);
        }
        if($info['id']){
            unset($info['type']);
            $where = [];
            $where[] = ['id','=',$info['id']];
            $where[] = ['is_del','=',0];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            $save = Db::name('designerpage_category')->where($where)->update($info);
            \app\commons\System::plog('编辑页面分类'.$info['id']);
        }else{
            $info['type'] = $info['type']?$info['type']:0;
            $info['bid'] = bid;
            $info['aid'] = aid;
            $info['createtime'] = time();
            $id = Db::name('designerpage_category')->insertGetId($info);
            if(!$id){
                return json(['status'=>0,'msg'=>'操作失败']);
            }
            \app\commons\System::plog('添加页面分类'.$id);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        $type =  input('param.type')?input('type/d'):0;
        if(getcustom('design_template')){
            if($type ==1 && $this->user['isadmin'] != 2){
                return json(['status'=>0,'msg'=>'无操作权限']);
            }
        }
        $del = Db::name('designerpage_category')->where('id','in',$ids)->where('type',$type)->where('aid',aid)->where('bid',bid)->update(['is_del'=>1]);
        if($del){
            \app\commons\System::plog('页面分类删除'.implode(',',$ids));
            return json(['status'=>1,'msg'=>'删除成功']);
        }else{
            return json(['status'=>0,'msg'=>'删除失败']);
        }
    }
}