<?php


// +----------------------------------------------------------------------
// | 系统 送货单设置仅多商户
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ShdSet extends Common
{
    public function initialize(){
        parent::initialize();
    }
    public function index(){
        $field = 'shipping_pagetitle,shipping_pagenum,shipping_linenum';
        if(getcustom('shd_print') ||  getcustom('shop_shd_print2')){
            $field .= ',printmoney,printlian';
        }
        if(bid>0){
            $info = Db::name('business')->where('id',bid)->field($field)->find();
        }else{
            $info = Db::name('shop_sysset')->where('aid',aid)->field($field)->find();
        }
        View::assign('info',$info);
        return View::fetch();
    }
    public function save(){
        $info = input('post.info/a');
        if(bid>0){
            $count = Db::name('business')->where('id',bid)->count('id');
            if(!$count){
                 return json(['status'=>0,'msg'=>'商户不存在']);
            }
        }else{
            $count = Db::name('shop_sysset')->where('aid',aid)->count('id');
            if(!$count){
                 return json(['status'=>0,'msg'=>'商城系统设置不存在']);
            }
        }
        
        $data = [];
        $data['shipping_pagetitle'] = $info['shipping_pagetitle']?$info['shipping_pagetitle']:'';
        $data['shipping_pagenum']   = $info['shipping_pagenum']?$info['shipping_pagenum']:'';
        $data['shipping_linenum']   = $info['shipping_linenum']?$info['shipping_linenum']:'';

        if(getcustom('shd_print') ||  getcustom('shop_shd_print2')){
            $data['printmoney']     = $info['printmoney']?$info['printmoney']:'';
            $data['printlian']      = $info['printlian']?$info['printlian']:'';
        }
        if(bid>0){
            $sql = Db::name('business')->where('id',bid)->update($data);
        }else{
            $sql = Db::name('shop_sysset')->where('aid',aid)->update($data);
        }
        \app\commons\System::plog('系统送货单设置');
        return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
    }
}