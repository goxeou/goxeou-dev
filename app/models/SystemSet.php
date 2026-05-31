<?php


namespace app\models;
use think\facade\Db;
class SystemSet
{
    //固定头部检索更新为组件
    static function initLocationPage(){
        //设置了定位模式的
        if(getcustom('show_location')){
            $list = Db::name('admin_set')->where('mode','in',[2,3])->select()->toArray();
            foreach ($list as $k=>$set){
                //主页
                $mainpage = Db::name('designerpage')->where('aid',$set['aid'])->where('ishome',1)->where('bid',0)->order('id desc')->find();
                if(empty($mainpage)){
                    continue;
                }
                $pagecontent = json_decode($mainpage['content'],true);
                $tempArr = array_column($pagecontent,'temp');
                if(in_array('location',$tempArr)){
                    continue;
                }
                $locationpage = self::locationpage();
                $locationpage['params']['showicon'] = 0;
                //menulist
                if($set['location_menu_list']){
                    $menulist = json_decode($set['location_menu_list'],true);
                    $dataitem = [];
                    foreach ($menulist as $mk=>$mv){
                        $dataitem[] = [
                            "id"=> "L000000000000".($mk+1),
                            "imgurl"=>$mv['icon'],
                            "hrefurl"=>$mv['url'],
                            "hrefname"=>''
                        ];
                    }
                    if($dataitem){
                        $locationpage['params']['showicon'] = 1;
                        $locationpage['data'] = $dataitem;
                    }
                }
                $pagecontent = array_merge([$locationpage],$pagecontent);
                Db::name('designerpage')->where('id',$mainpage['id'])->update(['content'=>json_encode($pagecontent)]);
            }
        }
    }
    static function locationpage(){
        return [
            "id"=>"M1680489055223628282",
            "temp"=>"location",
            "params"=> [
                "style"=> "1",
                "bgcolor"=> "#FFFFFF",
                "borderradius"=> 0,
                "bordercolor"=> "#F4F4F4",
                "color"=> "#333333",
                "showlevel"=> "2",
                "showsearch"=> "1",
                "bid"=> "0",
                "margin_x"=> 0,
                "margin_y"=> 0,
                "padding_x"=> 10,
                "padding_y"=> 5,
                "quanxian"=> [
                    "all"=> true
                ],
                "platform"=> [
                    "all"=> true
                ],
                "mendian"=> [
                    "all"=> true
                ],
                "mendian_sort"=> "sort",
                "showicon"=>"1",
                "hrefurl"=> "/pages/shop/search",
                "hrefname"=> "基础功能>商品搜索",
                "placeholder"=> "输入关键字搜索商品"
            ],
            "data"=> [
                [
                    "id"=> "L0000000000001",
                    "imgurl"=>PRE_URL."/static/img/cart_64.png",
                    "hrefurl"=> "/pages/shop/cart",
                    "hrefname"=> "基础功能>购物车"
                ],
                [
                    "id"=> "L0000000000002",
                    "imgurl"=> PRE_URL."/static/img/message_64.png",
                    "hrefurl"=> "/pages/kefu/index",
                    "hrefname"=> "基础功能>在线咨询"
                ]
            ],
            "other"=>"",
            "content"=> ""
        ];
    }
}