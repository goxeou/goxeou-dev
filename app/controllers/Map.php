<?php


// +----------------------------------------------------------------------
// | 地图
// +----------------------------------------------------------------------
namespace app\controllers;


class Map extends Common
{
    //地图搜索
    public function searchFormMap(){
        if(request()->isPost()){
            $keyword = input('post.keywords');
            $lat = input('post.lat');
            $lng = input('post.lng');

            if(empty($keyword) || empty($lat) || empty($lng)){
                return json(['status' => 0, 'msg' => '参数错误']);
            }
            $mapqq = new \app\commons\MapQQ();
            $results = $mapqq->searchNearbyPlace($keyword,['type'=>'city','lat'=>$lat,'lng'=>$lng],1000,1);
            if($results['status'] == 1){
                if(empty($results['data']) && isset($results['cluster'])){
                    return json(['status' => 0, 'msg' => '请输入详细地址']);
                }
                return json(['status' => 1, 'data' => $results['data']]);
            }
            $msg = isset($results['message']) ? $results['message'] : $results['msg'];
            return json(['status' => 0, 'msg' => $msg]);
        }
    }
}