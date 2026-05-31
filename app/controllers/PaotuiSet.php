<?php


//custom_file(paotui)
// +----------------------------------------------------------------------
// | 跑腿设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class PaotuiSet extends Common
{
    public function initialize(){
        parent::initialize();
        if(bid > 0) showmsg('无访问权限');
    }
    //
    public function index(){
        $area = '';
        $info = Db::name('paotui_set')->where('aid',aid)->find();
        if($info['area']){
            $area = $info['area'];
        }else{

            $set = Db::name('admin_set')->where('aid',aid)->find();
            $info = ['id'=>'','pic'=>$set['logo']];
        }
        View::assign('info',$info);
        View::assign('area',$area);
        return View::fetch();
    }
    //保存
    public function save(){

        $info   = input('post.info/a');
        $citys  = input('post.citys/a');

        $area     = [];
        $province = '';
        $city     = '';

        if($citys){
            //分成各省数组
            $citys_arr = array_filter(explode(';',$citys[0]));
            if($citys_arr){
                foreach($citys_arr as $cav){
                    //各省李数据分组
                    $cav_arr = array_filter(explode('-',$cav));

                    if($cav_arr){
                        array_push($area,$cav_arr);
                        $province .= $province?','.$cav_arr[0]:$cav_arr[0];
                        $city     .= $city?','.$cav_arr[1]:$cav_arr[1];
                    }
                }
                unset($cav);
            }
        }
        $info['area']     = json_encode($area);
        $info['province'] = $province;
        $info['city']     = $city;
        if(!$info['pic']){
            return json(['status'=>0,'msg'=>'物品图片不能为空']);
        }
        if($info['id']){
            Db::name('paotui_set')->where('aid',aid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog('修改跑腿设置'.$info['id']);
        }else{
            $info['aid'] = aid;
            $info['createtime'] = time();
            $id = Db::name('paotui_set')->insertGetId($info);
            \app\commons\System::plog('添加跑腿设置'.$id);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
}