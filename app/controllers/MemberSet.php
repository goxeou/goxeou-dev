<?php
//custom_file(member_set)
// +----------------------------------------------------------------------
// | 资料自定义
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class MemberSet extends Common
{
    public function initialize(){
        parent::initialize();
        if(!getcustom('member_set')) showmsg('无访问权限');
        if(bid > 0) showmsg('无访问权限');
    }
    //列表
    public function index(){

        $info = Db::name('member_set')->where('aid',aid)->find();
        if(empty($info)){
            $info = [
                'id'=>'',
                'content' => ''
            ];
        }
        View::assign('info',$info);
        return View::fetch();

    }
    //保存
    public function save(){
        $info = input('post.info/a');
        $datatype = input('post.datatype/a');
        $dataval1 = input('post.dataval1/a');
        $dataval2 = input('post.dataval2/a');
        $dataval3 = input('post.dataval3/a');
        $dataval4 = input('post.dataval4/a');
        $dataval5 = input('post.dataval5/a');
        $dataval_query = input('post.dataval_query/a');
        $dhdata = array();
        foreach($datatype as $k=>$v){
            if($dataval3[$k]!=1) $dataval3[$k] = 0;
            $dhdata[] = [
            'key'=>$v,
            'val1'=>$dataval1[$k],
            'val2'=>$dataval2[$k],
            'val3'=>$dataval3[$k],
            'val4'=>$dataval4[$k],
            'val5'=>($dataval5 ? $dataval5[$k] : ''),
            'query'=>($dataval_query[$k] ? $dataval_query[$k] : '0'),
            ];
        }
        $info['content'] = $dhdata?json_encode($dhdata,JSON_UNESCAPED_UNICODE):'';
        if($info['id']){
            Db::name('member_set')->where('aid',aid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog('编辑资料自定义'.$info['id']);
        }else{
            $info['aid'] = aid;
            $info['createtime'] = time();
            $id = Db::name('member_set')->insertGetId($info);
            \app\commons\System::plog('添加资料自定义'.$id);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        $del_ids = [];
        $dellist = Db::name('member_set')->where('id','in',$ids)->where('is_del',0)->where('aid',aid)->select->toArray();
        if(!$dellist){
            return json(['status'=>0,'msg'=>'删除失败，没有可删除的数据']);
        }
        foreach($dellist as $dv){
            $del = Db::name('member_set')->where('id',$dv['id'])->update(['is_del'=>1]);
            if($del){
                array_push($del_ids,$dv['id']);
            }
        }
        if(!$del_ids){
            return json(['status'=>0,'msg'=>'删除失败']);
        }
        \app\commons\System::plog('删除资料自定义'.implode(',',$del_ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
}
