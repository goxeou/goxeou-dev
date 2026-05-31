<?php


namespace app\models;
use think\facade\Db;
class Counpon
{
    /*
     * 过期优惠券结算
     */
	static function couponExpire(){
        return true;
	}
}