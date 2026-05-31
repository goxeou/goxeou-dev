<?php


namespace app\models;
use think\facade\Db;
class Score
{
    /*
     * $module 模块，artice,luntan,shortvideo,channels_reservation_live
     * $id 记录id
     * $type 赠送类型 read 阅读赠送，add发布赠送【审核通过】
     * 短视频奖励已更换为ShortVideoPlayAward统一处理
     * channels_reservation_live  预约直播奖励已更换为ApiWxChannelsLive(controller)处理
     */
	static function extGiveScore($aid,$mid,$module,$id,$type){
        return true;
	}
}