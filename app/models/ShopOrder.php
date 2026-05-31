<?php


namespace app\models;
use think\facade\Db;
use think\facade\Log;
class ShopOrder
{
    //从自定义字段中 同步订单的备注
	static function checkOrderMessage($orderid,$orderinfo=[]){
	    if($orderinfo){
	        $orderid = $orderinfo['id'];
        }else if($orderid){
	        $orderinfo = Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->find();
        }else{
	        return '';
        }
	    $message = $orderinfo['message'];
        if(empty($orderinfo['message'])){
            $formdata = Db::name('freight_formdata')->where('aid',aid)->where('orderid',$orderinfo['id'])->order('id desc')->where('type','shop_order')->find();
            if($formdata){
                for ($i=0;$i<=30;$i++){
                    $field = $formdata['form'.$i];
                    if(!$field){
                        continue;
                    }
                    $fieldArr = explode('^_^',$field);
                    if(!$fieldArr || $fieldArr[2]=='upload'){
                        continue;
                    }
                    if(strpos($fieldArr[0],'备注')!==false){
                        $message = $orderinfo['message'] = $fieldArr[1]??'';
                        break;
                    }
                }
                //更新到订单，下次不再查询
                if($message){
                    Db::name('shop_order')->where('aid',aid)->where('id',$orderid)->update(['message'=>$message]);
                }
            }
        }
        return empty($message)?'':$message;
    }

    //视力档案
    static function getGlassRecordRow($ordergoods = []){
	    return '';
    }

    static function checkReturnComponent($aid,$bid=0){
        $status = false;
        return $status;
    }
}