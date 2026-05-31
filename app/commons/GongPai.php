<?php

namespace app\commons;

use think\facade\Db;
use think\facade\Log;
class GongPai
{
    public function checkpai($aid,$mid,$gongpai,$totalprice){
		$member = Db::name('member')->where('id',$mid)->find();
		if ($gongpai==1) {
            $buycount = 0 + Db::name('shop_order_goods')->where('gongpai',$gongpai)->where('status','in','1,2,3')->where('mid',$mid)->count();
            $gongpaicount  = 0 + Db::name('member_gongpai')->where('aid',$aid)->where('mid',$mid)->count();
            $count = $buycount*2 - $gongpaicount; //可排位3次
         
            \think\facade\Log::write('---'.$mid.'自己购买---'.$buycount);
            \think\facade\Log::write('---'.$mid.'已经公排---'.$gongpaicount);
            if ($count > 0 ) {
                $downmids = \app\commons\Member::getdownmids($aid,$mid,1);
                $ordercount = 0 + Db::name('shop_order_goods')->where('gongpai',$gongpai)->where('status','in','1,2,3')->where('mid','in',$downmids)->count();
                \think\facade\Log::write('---'.$mid.'推荐购买---'.$ordercount);
                $total = floor(($ordercount - $gongpaicount*2) / 2);
                if ($total > 0) {
                    if ($total > $count){
                        $total = $count;
                    } 
                    for ($i = 0; $i < $total; $i++) {
                        \app\commons\GongPai::getGongpai($aid,$mid,'member_gongpai',3,$totalprice);
                    }
                }
            }
		}elseif ($gongpai==2) {
		    $buycount = 0 + Db::name('shop_order_goods')->where('gongpai',$gongpai)->where('status','in','1,2,3')->where('mid',$mid)->count();
            $gongpaicount  = 0 + Db::name('member_gongpai_two')->where('aid',$aid)->where('mid',$mid)->count();
           
            \think\facade\Log::write('---'.$mid.'--自己购买---'.$buycount);
            \think\facade\Log::write('---'.$mid.'--已经公排---'.$gongpaicount);
            \app\commons\GongPai::getGongpai($aid,$mid,'member_gongpai_two',2,$totalprice);
		}
	}
    public static function getGongpai($aid,$mid,$member_gongpai='member_gongpai',$total=3,$totalprice)
    {   
        $gongpai = Db::name($member_gongpai)->where('aid',$aid)->where('pid',0)->find();
        $pid = 0;
        if ($gongpai) {
            $pid = self::get_node_pid($gongpai['id'],$member_gongpai,$total);
        }
        $update = [];
        $update['aid'] = $aid;
        $update['mid'] = $mid;
        $update['pid'] = $pid;
        $parent = Db::name($member_gongpai)->where('aid',$aid)->where('id',$pid)->find();
        $path_arr = [];
		if($parent['pid']) {
			$path_arr[] = $parent['pid'];
			$path_arr[] = $parent['id'];
		} elseif($parent) {
	        $path_arr[] = $parent['id'];
		}
		if ($path_arr) {
		    $update['path'] = implode(',',$path_arr);
		}
		$update['createtime'] = time();
        Db::name($member_gongpai)->insert($update);
        if ($path_arr) {
    		foreach($path_arr as $k=>$v){
    			$downcountall = Db::name($member_gongpai)->where('aid',$aid)->where('find_in_set('.$v.',path)')->count();
    			if (($total==3 && $downcountall >= 12) || ($total==2 && $downcountall >= 6)) {
    			   Db::name($member_gongpai)->where('aid',$aid)->where('id',$v)->update(['status'=>1,'endtime'=>time()]);
    			}
    		}
    		$set = Db::name('admin_set')->where('aid',$aid)->find();
    		$pai_2 = $set['pai_2'];	$pai_3 = $set['pai_3'];
    		if ($total==2) {
    		    $pai_2 = $set['pai2_2'];
    		    $pai_3 = $set['pai2_3'];
    		}
    		\think\facade\Log::write('---'.$mid.'--第一批---'.$parent['pid']);
    		if ($parent['pid'] && $pai_2>0) {
    		    $parent1score = round($totalprice*$pai_2*0.01,2);
    		    \app\commons\Member::addscore($aid,$parent['pid'],$parent1score,'一级排位奖励');
    		}
    		$userslist = Db::name($member_gongpai)->where('aid',$aid)->where('pid',$parent['pid'])->select()->toArray();
    		foreach($userslist as $key=>$parent){
    		    $parent1score = round($totalprice*$pai_3*0.01,2);
    		    \app\commons\Member::addscore($aid,$parent['mid'],$parent1score,'二级排位奖励');
    		}
		}
  
    }
  	public function get_node_pid($mid,$member_gongpai,$total) {
		$layer = 1;
		$not_found = true;
		$uids = [];
		$pid = 0;
		while($not_found) {
			if($layer == 1) {
				$pids = [$mid];
			} else {
				$pids = $uids[$layer - 1];
			}
			$pids_str = implode(",", $pids);
			$users = Db::name($member_gongpai)->where("pid in ({$pids_str})")->field("id")->order(Db::raw('field(pid,'.$pids_str.')'))->select()->toArray();
			$uids[$layer] = array_column($users, 'id');
			$count_layer = count($uids[$layer]);
			$count_full = pow($total, $layer);
			if($count_layer == $count_full) {
				$layer += 1;
			} else {
				if($pids) {
					// $pids_arr = implode(",", $pids);
					$duan_arr = Db::name($member_gongpai)->alias('m')->where('m.id','in',$pids)->field('count(o.id) total,m.id')->leftJoin('member_gongpai o','o.pid=m.id')->order('total,m.id')->group('m.id')->find();
					$pid = $duan_arr['id'];
				}
				$not_found = false;
			}
		}
		return $pid;
	}
   
}