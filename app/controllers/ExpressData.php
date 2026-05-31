<?php


// +----------------------------------------------------------------------
// | 系统 快递设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ExpressData extends Common
{
    public function initialize(){
        parent::initialize();
    }
    public function index(){
        $info = Db::name('express_data')->where('aid',aid)->where('bid',bid)->find();
        if(!$info){
            $info = ['id'=>0,'express_data'=>''];
        }

        $express_ldata = [];//左侧数据
        $express_rdata = [];//右侧数据key
        if($info['express_data']){
            $express_data = json_decode($info['express_data'],true);
            foreach($express_data as $k=>$v){
                $express_ldata[] = [
                    'title'=>$k,
                    'value'=>$k
                ];
                $express_rdata[] = $k;
            }
        }

        $express_data = express_data();
        if($express_data){
            foreach($express_data as $ek=>$ev){
                if(!in_array($ek,$express_rdata)){
                    $express_ldata[] = [
                        'title'=>$ek,
                        'value'=>$ek
                    ];
                }
            }
        }

        $express_ldata = $express_ldata?json_encode($express_ldata):'';
        View::assign('express_ldata',$express_ldata);

        $express_rdata = $express_rdata?json_encode($express_rdata):'';
        View::assign('express_rdata',$express_rdata);
        View::assign('info',$info);
        return View::fetch();
    }
    public function save(){
        $info = input('post.info/a');
        $oldinfo = Db::name('express_data')->where('aid',aid)->where('bid',bid)->field('id')->find();

        if($info['express_data']){
            $express_data = express_data();
            $info['express_data'] = json_decode($info['express_data'],true);
            foreach($info['express_data'] as $k=>&$v){
                $v = $express_data[$k]??'';
            }
            $info['express_data'] = json_encode($info['express_data']);
        }

        if($oldinfo){
            $info['id'] = $oldinfo['id'];
        }
        if($info['id']){
            $info['updatetime'] = time();
            $sql = Db::name('express_data')->where('aid',aid)->where('bid',bid)->update($info);
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['createtime'] = time();
            $sql = Db::name('express_data')->insert($info);
        }
        if(!$sql){
            return json(['status'=>0,'msg'=>'保存失败']);
        }
        \app\commons\System::plog('系统快递设置');
        return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
    }
}