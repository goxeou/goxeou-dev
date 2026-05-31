<?php


namespace app\controllers;
use think\facade\Db;
class ApiAdminScore extends ApiAdmin
{
    public function initialize(){
        parent::initialize();
        if(bid > 0){
            $bset = Db::name('business_sysset')->where('aid',aid)->find();
            if($bset['business_selfscore'] != 1){
                echojson(['status'=>-4,'msg'=>'无权限操作']);
            }
        }
    }

    //商户个人互转积分
    public function businessMemberTransfer()
    {
        }

}