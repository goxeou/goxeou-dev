<?php
namespace app\controllers;
use app\BaseController;
use think\facade\View;
use think\facade\Db;

class Livereturn extends BaseController {
   //https://app.zjxyyx168.com/?s=/Livereturn/pushreturn
    /**
     * 推流直播回调
    */
    public function pushreturn(){
        $param = input('param.');
        ll($param,'pushreturn');
        if(!empty($param)){
            $room = $param['stream_id'];
            $data['starttime']=time();
            $data['status']= 1;
            $result = Db::name('lives')->where(['room'=>$room])->update($data);
            if($result){
                $live = Db::name('lives')->where(['room'=>$room])->find();
                $insert['mid']= $live['mid'];
                $insert['aid']= $live['aid'];
                $insert['starttime']=time();
                $insert['room']=$room;
                Db::name('live_record')->insert($insert);
            }
        }
    }

    /**
     * 直播录制回调
     */
    public function transcribeReturn(){
        $param = input('param.');
        ll($param,'transcribeReturn');
        if(!empty($param)){
            $liveRoom = Db::name('lives')->where('room',$param['stream_id'])->find();
            if($liveRoom){
                $userId = Db::name('member')->where('id',$liveRoom['mid'])->value('id');
                $userId = !empty($userId) ? $userId : 0;
            }else{
                $userId = 0;
                $liveRoom = [];
            }
            $data['user_id'] = $userId;
            $data['aid']= $liveRoom['aid'];
            $data['shop_id'] = $liveRoom['shop_id'];
            $data['stream_id'] = $param['stream_id'];
            $data['start_time']= $param['start_time'];
            $data['end_time']= $param['end_time'];
            $data['video_url']= $param['video_url'];
            $data['duration']= $param['duration'];
            $result = Db::name('live_transcribe')->insert($data);
        }
    }



    /**
     * 断流直播回调
     */
    public function breakpushreturn(){
        $param = input('param.');
        $room = $param['channel_id'];
        if(!empty($param)){
            $rooms = Db::name('lives')->where(['room'=>$room])->find();
            $data['status']=2; 
            $data['endtime']=time();
            $result = Db::name('lives')->where(['room'=>$room])->update($data);
            if($result){
                $live = Db::name('lives')->where(['room'=>$room])->find();
                
                ll($live,'$live');
                
                
                $update['endtime']=time();
                $record = Db::name('live_record')->where(['mid'=>$live['mid'],'room'=>$room])->order('id desc')->find();
                if($record){
                    Db::name('live_record')->where(['id'=>$record['id']])->update($update);
                    Db::name('kefu_message')->where('room',$room)->delete();
                }
            }
        }
    }

}