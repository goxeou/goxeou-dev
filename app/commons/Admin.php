<?php



namespace app\commons;

use think\facade\Db;

class Admin
{

    public static function addcredit1($aid,$mid,$credit1,$remark,$type=0){
        if($credit1==0) return ;
        $admin['credit1'] = Db::name('admin_credit1log')->where('aid',$aid)->sum('credit1');
        if($credit1 < 0 && $admin['credit1'] < $credit1*-1) return ['status'=>0,'msg'=>t('资金池').'不足'];
        $after = $admin['credit1'] + $credit1;
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['credit1'] = $credit1;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('admin_credit1log')->insert($data);
        
        return ['status'=>1,'msg'=>''];
    }
     public static function addcredit2($aid,$mid,$credit2,$remark,$type=0){
        if($credit2==0) return ;
        $admin['credit2'] = Db::name('admin_credit2log')->where('aid',$aid)->sum('credit2');
        if($credit2 < 0 && $admin['credit2'] < $credit2*-1) return ['status'=>0,'msg'=>t('慈善基金会').'不足'];
        $after = $admin['credit2'] + $credit2;
        $data = [];
        $data['aid'] = $aid;
        $data['mid'] = $mid;
        $data['credit2'] = $credit2;
        $data['after'] = $after;
        $data['createtime'] = time();
        $data['remark'] = $remark;
        Db::name('admin_credit2log')->insert($data);
        
        return ['status'=>1,'msg'=>''];
    }
    //加余额
    public static function addmoney($aid,$money,$remark,$paytype=''){
        }
}