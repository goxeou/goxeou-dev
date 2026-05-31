<?php


// +----------------------------------------------------------------------
// | 预定设置
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class RestaurantBookingSet extends Common
{
    public function initialize(){
		parent::initialize();
		//if(bid > 0) showmsg('无访问权限');
	}
    public function index(){
		$info = Db::name('restaurant_booking_sysset')->where('aid',aid)->where('bid',bid)->find();
		if(!$info){
            $default = [
                'aid'=>aid,
                'bid'=>bid,
                'prehour'=>2,
                'timedata'=>'[{"day":"1","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"1","hour":"18","minute":"0","hour2":"18","minute2":"30"},{"day":"2","hour":"12","minute":"0","hour2":"12","minute2":"30"},{"day":"2","hour":"18","minute":"0","hour2":"18","minute2":"30"}]',
            ];
			Db::name('restaurant_booking_sysset')->insert($default);
			$info = Db::name('restaurant_booking_sysset')->where('aid',aid)->where('bid',bid)->find();
		}
        View::assign('info',$info);
		return View::fetch();
    }
	public function save(){
		$info = input('post.info/a');

        $timeday = input('post.timeday/a');
        $timehour = input('post.timehour/a');
        $timeminute = input('post.timeminute/a');
        $timehour2 = input('post.timehour2/a');
        $timeminute2 = input('post.timeminute2/a');
        $timedata = [];
        foreach($timeday as $k=>$v){
            $timedata[] = ['day'=>$v,'hour'=>$timehour[$k],'minute'=>$timeminute[$k],'hour2'=>$timehour2[$k],'minute2'=>$timeminute2[$k]];
        }
        $info['timedata'] = json_encode($timedata);
        Db::name('restaurant_booking_sysset')->where('aid',aid)->where('bid',bid)->update($info);
		return json(['status'=>1,'msg'=>'保存成功','url'=>(string)url('index')]);
	}
}