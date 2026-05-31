<?php


// +----------------------------------------------------------------------
// | 自动执行  每分钟执行一次 crontab -e 加入 */1 * * * * curl https://域名/?s=/ApiAuto/index/key/配置文件中的authtoken
// +----------------------------------------------------------------------
namespace app\controllers;
use app\BaseController;
use think\facade\Db;
use think\facade\Log;

class ApiAuto extends BaseController
{
    // https://goxeou.12fz.com/?s=/ApiAuto/login/key/4  //分红
    public function login() //每月分红 
    {
        $aid = input('param.key');
        define('aid',$aid);
        $order = Db::name('shop_order')->where('aid', $aid)->where('id', 55)->find();
       
       
       


       
       
       
        $result = \app\commons\Wechat::getUserInfo('ogeQF6izvKzsCgzBi0yUzZ0uAiUE','89_K4rA5O7VaDJdMPt4-QADmgHGFFyNMjCFC2sJjuEnKcdEKO2mW1WNW4pOTu6raj3O1twmNfa-X-a8BBNbUurgCR-W74CNc-Aw1Iw5UbOQJZQ');
        
        
        ll($result,'$result$result$result$result$result');
        
        
        
    //      $params = [
    //         'id' => '4d358f0015319fb76a227e243e3bb992',           // 订单ID
    //         'expressName' => '1',   // 退款快递名称
    //         'expressCode' => '333',   // 退款快递单号
    //         'statusDesc' => '1'   // 退款状态描述
    //     ];
	   // $request = \app\commons\Aaa::sendpost($params,'/app/client/update/refund');
	   
    }
    
    
   
    
    
    
       // https://goxeou.12fz.com/?s=/ApiAuto/submitOrder/key/4  //分红
    public function submitOrder() //每月分红 
    {
        $aid = input('param.key');
        define('aid',$aid);
        $order = Db::name('shop_order')->where('aid', $aid)->where('id', 55)->find();
        $res = \app\commons\Aaa::submitOrder($aid, $order);
        ll($res,'$res$res$res');
    }
    
    
    
    
    
    // 	https://241016.fqywl.com/?s=/ApiAuto/monthfenhong/key/4  //分红
    public function monthfenhong() //每月分红 
    {
        $aid = input('param.key');
        define('aid',$aid);
        $set = Db::name('admin_set')->where('aid', $aid)->find();
        try {
            Db::startTrans();
            $times =  getTime('今天');
        	$dayStart = $times[0];
        	$dayEnd = $times[1];
        	$where = [];
        	$where[] = ['aid', '=', $aid];
        	$where[] = ['status', 'in', '1,2,3'];
            $where[] = ['paytime', 'between', [$dayStart,$dayEnd]];//
            $totalprice = 0 + Db::name('shop_order_goods')->where($where)->where('isjia', 0)->sum('totalprice');
            Db::name('shop_order_goods')->where('aid', $aid)->where($where)->where('isjia', 0)->update(['isjia' => 1]);
            $fenhongprice = round($totalprice * $set['team_rate'] * 0.01, 6);
            
            echo '总分红金额--'.$fenhongprice;
            if ($set['team_lvids']!='0' && $set['team_lvids']!='') {
                $team_lvids = explode(',',str_replace('，',',',$set['team_lvids']));
                $memberlist = Db::name('member')->where('aid',$aid)->where('levelid','in',$team_lvids)->select()->toArray();
            }
            echo '总分红人数--'.count($memberlist);
            if ($fenhongprice > 0 && $memberlist) {
                $update_array = [];
                $commission_log_array = [];
                $score_log_array = [];
                $fhmoney = round($fenhongprice / count($memberlist), 2);
                foreach ($memberlist as $val2) {
                    $commission = $fhmoney;
                    if ($commission <= 0) {
                        continue;
                    }
                    $score = 0;
    				$oldcommission = $commission;
    				if ($set['commission2scorepercent'] > 0) {
            			$score = round($commission * $set['commission2scorepercent']*0.01,2);
            			$commission = $oldcommission - $score;
    				}
                    $update = [];
                    $update['id'] = $val2['id'];
                    $update['score'] =  $val2['score'] + $score;
                    $update['commission'] = $val2['commission'] + $commission;
                    $update['totalcommission'] = $val2['totalcommission'] + $oldcommission;
                    $update_array[] = $update;
                    $remark = date("Y-m-d", time()) . '加权分红';
                    if ($score>0) {
    				    $score_log = [];
        				$score_log['aid'] = $aid;
        				$score_log['mid'] = $val2['id'];
        				$score_log['score'] =  $score;
        				$score_log['after'] = $val2['score'] + $score;
        				$score_log['createtime'] = time();
        				$score_log['remark'] = $remark;
        				$score_log_array[] = $score_log;
    				}
                    $commission_log = [];
                    $commission_log['aid'] = $aid;
                    $commission_log['mid'] = $val2['id'];
                    $commission_log['commission'] = $commission;
                    $commission_log['after'] = $val2['commission'] + $commission;
                    $commission_log['createtime'] = time();
                    $commission_log['remark'] = $remark;
                    $commission_log['addtotal'] = 1;
                    $commission_log_array[] = $commission_log;
                }
                if (!empty($update_array)) {
                    $model = new \app\models\Member();
                    $model->saveAll($update_array);
                }
                if (!empty($commission_log_array)) {
                    Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
                }
                if (!empty($score_log_array)) {
                    Db::name('member_scorelog')->limit(100)->insertAll($score_log_array);
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Log::write(['file' => __FILE__ . ' L' . __LINE__, 'function' => __FUNCTION__, 'error' => $e->getMessage()]);
            Db::rollback();
        }
        echo 'successful';
    }
     // https://241016.fqywl.com/?s=/ApiAuto/uplog/key/4  //涨价
    public function uplog(){
        $aid = input('param.key');
        define('aid',$aid);
        $times =  getTime('今天');
    	$dayStart = $times[0];
    	$dayEnd = $times[1];
       	$set = Db::name('admin_set')->where('aid',$aid)->find();
        $where = [];
    	$where[] = ['aid', '=', $aid];
        $where[] = ['createtime', 'between', [$dayStart,$dayEnd]];//
	    $total = 0 + Db::name('admin_credit1log')->where($where)->sum('credit1');
	    $credit = Db::name('member')->where('aid',$aid)->sum('credit1');
	    if ($total>0 && $credit>0) {
	        $credit1_max = round($set['credit1_money']*$set['tongji_1']*0.01,4);
	        $up_credit1 = round($total/$credit,4);
    		if ($up_credit1 > $credit1_max){
    		    $up_credit1 = $credit1_max;
    		} 
    		if ($up_credit1>0) {
    			$after = $set['credit1_money'] + $up_credit1;
    			$data = [];
    			$data['aid'] = $aid;
    			$data['now_money'] = $set['credit1_money'];
    			$data['credit1'] = $credit;
    			$data['credit1_money'] = $up_credit1;
    			$data['after'] = $after;
    			$data['totalprice'] = $total;
    			$data['createtime'] = time();
    			$data['remark'] =  date("Y-m-d").'涨价';
    			Db::name('member_up_credit1log')->insert($data);
    			Db::name('admin_set')->where('aid',$aid)->update(['credit1_money'=>$after]);
    		}
	    }
	   // admin_credit1_uplog 
	  
	}
         
           
           
           
           
           
           
           
    //https://base.xcx66.top/?s=/ApiAuto/cece/key/4
	public function cece(){
        $aid = input('param.key');
        define('aid',$aid);
        for ($i = 1; $i < 13; $i++) {
            $mid = $i;
            //\app\common\GongPai::getGongpai($aid,$mid,'member_gongpai');
        }
        // \app\common\GongPai::getGongpai($aid,1,'member_gongpai');
        
        
        $orderid = 4;
        $order = Db::name('shop_order')->where('id',$orderid)->find();
		$member = Db::name('member')->where('id',$order['mid'])->find();
		$aid = $order['aid'];
		$mid = $order['mid'];
	    $oglist = Db::name('shop_order_goods')->where('orderid',$orderid)->select()->toArray();
	    foreach($oglist as $og){
			$product = Db::name('shop_product')->where('id',$og['proid'])->find();
			if ($product['gongpai']==1) {
    	     	\app\commons\GongPai::checkpai($aid,$member['id'],$product['gongpai']);
			     //上级是否满足
			    if ($member['pid']) { //上级是否满足
			        \app\commons\GongPai::checkpai($aid,$member['pid'],$product['gongpai']);
	            }
			}
        }
	}
   
          
//         //	https://base.xcx66.top/?s=/ApiAuto/cece/key/4
	public function cece2(){
        $aid = input('param.key');
        define('aid',$aid);
        $season = ceil(4/3);
        for ($i = 1; $i < 13; $i++) {
            $mid = $i;
            \app\plugin\GongPai::getGongpai($aid,$mid,'member_gongpai');
        }
        
        
        
        
// 		$aid = 4;
// 		$datalist = Db::name('bd_core_template')->where('1=1')->select()->toArray();
// 		foreach($datalist as $key=>$v){
// 			$datalist[$key]['pics'] = json_decode($v['pics'],true);
// 				$datalist[$key]['data'] = json_decode($v['data'],true);
// 		}
	
		
	
//         $file_url = 'https://www.diandashop.com/index/install2/checkdomain.html';  // PHP文件的URL
//      	$url1 = 'https://www.diandashop.com/index/install2/checkdomain.html?domain=www.diandashop.com';
//         $data1 = array();
// 		$data1['domain'] = 'www.diandashop.com';
// 		$res = curl_form_post($url1,$data1);	
//         ll($res,'auto_day');
    
    }
    
    
        
// //         //	https://base.xcx66.top/?s=/ApiAuto/cece/key/4
// 	public function cece(){
//         $aid = input('param.key');
//         define('aid',$aid);
//         $season = ceil(4/3);
// 		$aid = 4;
	
//         $file_url = 'https://www.diandashop.com/index/install2/checkdomain.html';  // PHP文件的URL
//      	$url1 = 'https://www.diandashop.com/index/install2/checkdomain.html?domain=www.diandashop.com';
//         $data1 = array();
// 		$data1['domain'] = 'www.diandashop.com';
// 		$res = curl_form_post($url1,$data1);	
//         ll($res,'auto_day');
    
//     }
    
    
    
    
    
      //	https:///?s=/ApiAuto/fhdianfenhong/key/4
    public function fhdianfenhong() //每天分红 
    {
        $aid = input('param.key');
        define('aid',$aid);
        $set = Db::name('admin_set')->where('aid', $aid)->find();
        $times =  getTime('今天');
    	$dayStart = $times[0];
    	$dayEnd = $times[1];
    	$levellist = Db::name('member_level')->where('aid',$aid)->column('*','id');
      //  $memberlist = Db::name('member')->where('aid',$aid)->where('credit1','>',0)->field('id,aid,credit1,levelid,buymoney,score,commission,totalcommission')->where('buymoney','>',0)->where('totalcommission','<',Db::raw('buymoney*2'))->select()->toArray();
        $memberlist = Db::name('member')->where('aid',$aid)->where('credit1','>',0)->field('id,aid,credit1,levelid,buymoney,score,commission,totalcommission')->select()->toArray();
        $fhdian = Db::name('member')->where('aid',$aid)->where('credit1','>',0)->sum('credit1');
      
        try {
            Db::startTrans();
            $remark = date("Y-m-d") . t('资金池') .'加权分红';
            $fenhongprice = 0 + Db::name('admin_credit1log')->where('aid',$aid)->sum('credit1');
            echo '<br>资金池剩余--'.$fenhongprice;
            if ($fenhongprice > 0 && $fhdian>0) {
                \app\commons\Admin::addcredit1($aid,0,$fenhongprice*-1,$remark);
                $update_array = [];
                $commission_log_array = [];
                $score_log_array = [];
                $fhmoney = round($fenhongprice / $fhdian, 8);
                echo '<br>单个分红补贴--'.$fhmoney;
               	foreach($memberarray as $val2 ) {
				    $commission =  round($fhmoney*$val2['credit1'],2);
    				if ($commission <= 0) continue;
    				$update = [];
    				$update['id'] = $val2['id'];
    				$score = 0;
    				$oldcommission = $commission;
    				if ($set['commission2scorepercent'] > 0) {
            			$score = round($commission * $set['commission2scorepercent']*0.01,2);
            			$commission = $oldcommission - $score;
    				}
    				$update['score'] =  $val2['score'] + $score;
    				$update['commission'] =  $val2['commission'] + $commission;
    				$update['totalcommission'] = $val2['totalcommission'] + $oldcommission;
    				$update_array[] = $update;
    				if ($score>0) {
    				    $score_log = [];
        				$score_log['aid'] = $aid;
        				$score_log['mid'] = $val2['id'];
        				$score_log['score'] =  $score;
        				$score_log['after'] = $val2['score'] + $score;
        				$score_log['createtime'] = time();
        				$score_log['remark'] = $remark;
        				$score_log_array[] = $score_log;
    				}
    				$commission_log = [];
    				$commission_log['aid'] = $aid;
    				$commission_log['mid'] = $val2['id'];
    				$commission_log['commission'] =  $commission;
    				$commission_log['after'] = $val2['commission'] + $commission;
    				$commission_log['createtime'] = time();
    				$commission_log['remark'] = $remark;
    				$commission_log['addtotal'] = 1;
    				$commission_log_array[] = $commission_log;
				}
			
                if (!empty($update_array)) {
                    $model = new \app\models\Member();
                    $model->saveAll($update_array);
                }
                if (!empty($commission_log_array)) {
                    Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
                }
                if (!empty($score_log_array)) {
                    Db::name('member_scorelog')->limit(100)->insertAll($score_log_array);
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Log::write(['file' => __FILE__ . ' L' . __LINE__, 'function' => __FUNCTION__, 'error' => $e->getMessage()]);
            Db::rollback();
        }
        echo '<br>::分红成功';
    }
       public static function amoney3(){
	    $aid = input('param.key');
    	define('aid',$aid);
	    set_time_limit(0);
		ini_set('memory_limit', -1);
		$levellist = Db::name('member_team_level')->where('aid',$aid)->column('*','id');
       	$starttime = getTime('昨天');

		$where = [];
		$where[] = ['og.aid','=',$aid];
		$where[] = ['og.status','in','1,2,3'];
		$where[] = ['og.tongji_1','>',0];
        $where[] = ['og.paytime','between',[$starttime[0],$starttime[1]]];
        $fields = 'member.id,member.aid,member.pid,member.path,member.teamlevel,sum(og.num) totalnum,sum(og.totalprice) totalprice';
		$orderArray = Db::name('shop_order_goods')->alias('og')->field($fields)->join('member member','member.id=og.mid')->where($where)->group('og.mid')->select()->toArray();
		$midteamfhArr = [];
		foreach ($orderArray as $key =>  $val) {
		    $pids = $val['path'];
		    if ($val['path']) {
		        if($pids){
					$parentList = explode(',',$pids);
					$parentList = array_reverse($parentList);//父级从近到远，上一级，上二级，上三级。
					foreach($parentList as $k=>$pid){
					    if ($k==0 && $pid) {
					        $midteamfhArr[$pid]['aid'] = $aid;
					        $midteamfhArr[$pid]['mid'] = $pid;
					        $midteamfhArr[$pid]['totalnum'] = $midteamfhArr[$pid]['totalnum'] + $val['totalnum'];
                            $midteamfhArr[$pid]['totalprice'] = $midteamfhArr[$pid]['totalprice'] + $val['totalprice'];
					    }
					    if ($pid) {
					        $midteamfhArr[$pid]['aid'] = $aid;
					        $midteamfhArr[$pid]['mid'] = $pid;
					        $midteamfhArr[$pid]['total_num'] = $midteamfhArr[$pid]['total_num'] + $val['totalnum'];
                            $midteamfhArr[$pid]['total_price'] = $midteamfhArr[$pid]['total_price'] + $val['totalprice'];
					    }
            		}
		        }
		    }
		}
		$memberArray = Db::name('member')->where('aid',$aid)->field('id,aid,pid,path,commission,totalcommission,score,teamlevel,levelid')->select()->toArray();
		$memberList = [];
		foreach ($memberArray as $member) {
		   $memberList[$member['id']] = $member;
		}
        try { 
			Db::startTrans();
            if ( $midteamfhArr) {
        		$yeji_record_array = [];
             	foreach ($midteamfhArr as $mid => $record) {
             	    $member = $memberList[$val2['mid']];
             	    $leveldata = $levellist[$member['teamlevel']];
        		    $fenhongbl = $leveldata['rate1']>0?$leveldata['rate1']:0;
        			$record_array = $record;
        			$record_array['fenhongbl'] = $fenhongbl;
        			$record_array['createtime'] = time();
    				$record_array['commission'] = round($record['totalnum']*$fenhongbl,2);
    				$record_array['totalcommission'] = round($record['totalnum']*$fenhongbl,2);
    				$record_array['remark'] = '';
        			$record_array['createtime'] = time();
        			$yeji_record_array[$mid] = $record_array;
        	    }
        		if(!empty($yeji_record_array)) {
                    foreach ($yeji_record_array as $val2) {
            		     $member = $memberList[$val2['mid']];
        	             if (!$member || !$member['pid'])  continue; 
        	             $parentdata = $yeji_record_array[$member['pid']];
        			     if ($parentdata) {
        			         $commissionrate = round($parentdata['fenhongbl'] - $val2['fenhongbl'],2);
        			         if ($commissionrate>0) {
        			             $commission = round($val2['total_num']*$commissionrate,2);
        			             $parentdata['totalcommission'] = round($parentdata['totalcommission']+$commission,2);
        			             $parentdata['remark'] = $parentdata['remark'].'ID:'.$val2['mid'].' 每单:'.$val2['fenhongbl'].' 台数:'.$val2['totalnum'].' 极差:'.$commissionrate.' -- ';
        			             $yeji_record_array[$member['pid']] = $parentdata;
        			         }
        			     }
        		    }
        		    $model = new \app\models\MemberYejiRecord();
        			$model->saveAll($yeji_record_array);
        			$remark =  date('Y-m-d').'~代理分红~';
        			$update_array = [];
            		$commission_log_array = [];
        		
        			foreach ($yeji_record_array  as $key => $record) {
            			$member = $memberList[$record['mid']];
            			$commission =  $record['totalcommission'];
            			if ($commission <= 0) continue; 
        				$update = [];
        				$update['id'] = $member['id'];
        				$update['commission'] =  $member['commission'] + $commission;
        				$update['totalcommission'] =  $member['totalcommission'] + $commission;
        				$update_array[] = $update;
        				$commission_log = [];
        				$commission_log['aid'] = $aid;
        				$commission_log['mid'] = $member['id'];
        				$commission_log['commission'] =  $commission;
        				$commission_log['after'] = $member['commission'] + $commission;
        				$commission_log['createtime'] = time();
        				$commission_log['remark'] = $remark;
        				$commission_log['addtotal'] = 1;
        				$commission_log_array[] = $commission_log;
        			}
        			if(!empty($update_array)) {
        				$model = new \app\models\Member();
        				$model->saveAll($update_array);
        			}
        			if(!empty($commission_log_array)) {
        				Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
        			}
        			$memberArray = Db::name('member')->where('aid',$aid)->field('id,aid,pid,path,commission,totalcommission,score,teamlevel,levelid')->select()->toArray();
            		$memberList = [];
            		foreach ($memberArray as $member) {
            		   $memberList[$member['id']] = $member;
            		}
            		$remark =  date('Y-m-d').'~代理分红平级~';
            		$update_array = [];
            		$commission_log_array = [];
                    foreach ($yeji_record_array  as $key => $record) {
            			$member = $memberList[$record['mid']];
            			$parent = $memberList[$member['pid']];
            			if ($parent) {
            			    $leveldata = $levellist[$parent['teamlevel']];
            			    if ($leveldata['rate2']>0 && $parent['teamlevel']==$member['teamlevel']) {
            			       	$commission =  round($record['totalcommission']*$leveldata['rate2']*0.01,2);
                    			if ($commission <= 0) continue; 
                				$update = [];
                				$update['id'] = $parent['id'];
                				$update['commission'] =  $parent['commission'] + $commission;
                				$update['totalcommission'] =  $parent['totalcommission'] + $commission;
                				$update_array[] = $update;
                				$commission_log = [];
                				$commission_log['aid'] = $aid;
                				$commission_log['mid'] = $parent['id'];
                				$commission_log['commission'] =  $commission;
                				$commission_log['after'] = $parent['commission'] + $commission;
                				$commission_log['createtime'] = time();
                				$commission_log['remark'] = $remark;
                				$commission_log['addtotal'] = 1;
                				$commission_log_array[] = $commission_log;
                				
                				$memberList[parent['id']]['commission'] = $memberList[parent['id']]['commission'] + $commission;;
                				$memberList[parent['id']]['totalcommission'] = $memberList[parent['id']]['totalcommission'] + $commission;;
                				
            			    }
            			   
            			}
        			}
        			if(!empty($update_array)) {
        				$model = new \app\models\Member();
        				$model->saveAll($update_array);
        			}
        			if(!empty($commission_log_array)) {
        				Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
        			}
        		}
            }
			Db::commit();
		}
		catch (\Exception $e) {
			Log::write([
	                'file' => __FILE__ . ' L' . __LINE__,
	                'function' => __FUNCTION__,
	                'error' => $e->getMessage(),
	            ]);
			Db::rollback();
		
		}
		echo('successful');
    
    }
    
    
 
    
//     public function fhdianfenhong() //每天分红 
//     {
//         $aid = input('param.key');
//         define('aid',$aid);
//         $set = Db::name('admin_set')->where('aid', $aid)->find();
//         $times =  getTime('今天');
//     	$dayStart = $times[0];
//     	$dayEnd = $times[1];
//     	$levellist = Db::name('member_level')->where('aid',$aid)->column('*','id');
//       //  $memberlist = Db::name('member')->where('aid',$aid)->where('credit1','>',0)->field('id,aid,credit1,levelid,buymoney,score,commission,totalcommission')->where('buymoney','>',0)->where('totalcommission','<',Db::raw('buymoney*2'))->select()->toArray();
//         $memberlist = Db::name('member')->where('aid',$aid)->field('id,aid,levelid,buymoney,score,commission,totalcommission')->select()->toArray();
//         $fhdian =  0;
//         $memberarray = [];
//         foreach($memberlist as $key=>$val){
//             $leveldata = $levellist[$val['levelid']];
// 			$val['fhdian'] = $leveldata['up_fhnum'];
// 			if($val['fhdian']>0){
// 			    $fhdian+=$val['fhdian'];
// 			    $memberarray[] = $val;
// 			}
// 		}
//         try {
//             Db::startTrans();
//             $remark = date("Y-m-d") . t('资金池') .'加权分红';
//             $fenhongprice = 0 + Db::name('admin_credit1log')->where('aid',$aid)->sum('credit1');
//             echo '<br>资金池剩余--'.$fenhongprice;
//             if ($fenhongprice > 0 && $fhdian>0) {
//                 \app\commons\Admin::addcredit1($aid,0,$fenhongprice*-1,$remark);
//                 $update_array = [];
//                 $commission_log_array = [];
//                 $credit1_log_array = [];
//                 $score_log_array = [];
//                 $fhmoney = round($fenhongprice / $fhdian, 8);
//                 echo '<br>单个分红补贴--'.$fhmoney;
//               	foreach($memberarray as $val2 ) {
//               	    $maxcommission = $val2['buymoney']*2 - $val2['totalcommission'];
// 				    $commission =  round($fhmoney*$val2['fhdian'],2);
//     				if ($commission > $val2['credit1']) $commission = $val2['credit1'];
//     				if ($commission > $maxcommission) $commission = $maxcommission;
//     				if ($commission <= 0) continue;
//     				$update = [];
//     				$update['id'] = $val2['id'];
//     				$update['credit1'] =  $val2['credit1'] - $commission;
//     				$score = 0;
//     				$oldcommission = $commission;
//     				if ($set['commission2scorepercent'] > 0) {
//             			$score = round($commission * $set['commission2scorepercent']*0.01,2);
//             			$commission = $oldcommission - $score;
//     				}
//     				$update['score'] =  $val2['score'] + $score;
//     				$update['commission'] =  $val2['commission'] + $commission;
//     				$update['totalcommission'] = $val2['totalcommission'] + $oldcommission;
//     				$update_array[] = $update;
    			
//     				$credit1_log = [];
//     				$credit1_log['aid'] = $aid;
//     				$credit1_log['mid'] = $val2['id'];
//     				$credit1_log['credit1'] =  $oldcommission*-1;
//     				$credit1_log['after'] = $val2['credit1'] - $oldcommission;
//     				$credit1_log['createtime'] = time();
//     				$credit1_log['remark'] = $remark;
//     				$credit1_log_array[] = $credit1_log;
//     				if ($score>0) {
//     				    $score_log = [];
//         				$score_log['aid'] = $aid;
//         				$score_log['mid'] = $val2['id'];
//         				$score_log['score'] =  $score;
//         				$score_log['after'] = $val2['score'] + $score;
//         				$score_log['createtime'] = time();
//         				$score_log['remark'] = $remark;
//         				$score_log_array[] = $score_log;
//     				}
//     				$commission_log = [];
//     				$commission_log['aid'] = $aid;
//     				$commission_log['mid'] = $val2['id'];
//     				$commission_log['commission'] =  $commission;
//     				$commission_log['after'] = $val2['commission'] + $commission;
//     				$commission_log['createtime'] = time();
//     				$commission_log['remark'] = $remark;
//     				$commission_log['addtotal'] = 1;
//     				$commission_log_array[] = $commission_log;
// 				}
			
//                 if (!empty($update_array)) {
//                     $model = new \app\models\Member();
//                     $model->saveAll($update_array);
//                 }
//                 if (!empty($commission_log_array)) {
//                     Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
//                 }
//                 if (!empty($credit1_log_array)) {
//                     Db::name('member_credit1log')->limit(100)->insertAll($credit1_log_array);
//                 }
//                 if (!empty($score_log_array)) {
//                     Db::name('member_scorelog')->limit(100)->insertAll($score_log_array);
//                 }
//             }
//             Db::commit();
//         } catch (\Exception $e) {
//             Log::write(['file' => __FILE__ . ' L' . __LINE__, 'function' => __FUNCTION__, 'error' => $e->getMessage()]);
//             Db::rollback();
//         }
//         echo '<br>::分红成功';
//     }
     
       //	https:///?s=/ApiAuto/ceceshi/key/4
	public function ceceshi(){
        $aid = input('param.key');
        define('aid',$aid);
        
        $where = [];
    	$where[] = ['aid', '=', $aid];
    	$where[] = ['status', 'in', '1,2,3'];
        //$where[] = ['paytime', 'between', [$dayStart,$dayEnd]];//
        $totalprice = 0 + Db::name('shop_order_goods')->where('status', 'in', '1,2,3')->sum('totalprice');
        $buymoneyprice = 1000025;
        $score_num = intval($totalprice/$buymoneyprice);
        var_dump($score_num);
        
	    ll($score_num,'$score_num');
      
	}
     //https:///?s=/ApiAuto/amoney/key/4
        //https:///?s=/ApiAuto/amoney/key/4
    public static function amoney(){
	    $aid = input('param.key');
    	define('aid',$aid);
	    set_time_limit(0);
		ini_set('memory_limit', -1);
       	$starttime = getTime('上月');
		$where = [];
		$where[] = ['og.aid','=',$aid];
		$where[] = ['og.status','in','1,2,3'];
        $where[] = ['og.paytime','between',[$starttime[0],$starttime[1]]];
        $fields = 'member.id,member.aid,member.pid,member.path,sum(og.pv) totalnum,sum(og.totalprice) totalprice';
		$orderArray = Db::name('shop_order_goods')->alias('og')->field($fields)->join('member member','member.id=og.mid')->where($where)->group('og.mid')->select()->toArray();
		$midteamfhArr = [];
		foreach ($orderArray as $key =>  $val) {
		    $pids = $val['path'];
		    if ($val['path']) {
		        if($pids){
					$parentList = explode(',',$pids);
					$parentList = array_reverse($parentList);//父级从近到远，上一级，上二级，上三级。
					foreach($parentList as $k=>$pid){
					    if ($k==0 && $pid) {
					        $midteamfhArr[$pid]['aid'] = $aid;
					        $midteamfhArr[$pid]['mid'] = $pid;
					        $midteamfhArr[$pid]['totalnum'] = $midteamfhArr[$pid]['totalnum'] + $val['totalnum'];
                            $midteamfhArr[$pid]['totalprice'] = $midteamfhArr[$pid]['totalprice'] + $val['totalprice'];
					    }
					    if ($pid) {
					        $midteamfhArr[$pid]['aid'] = $aid;
					        $midteamfhArr[$pid]['mid'] = $pid;
					        $midteamfhArr[$pid]['total_num'] = $midteamfhArr[$pid]['total_num'] + $val['totalnum'];
                            $midteamfhArr[$pid]['total_price'] = $midteamfhArr[$pid]['total_price'] + $val['totalprice'];
					    }
            		}
		        }
		    }
		}
     	$giveset = Db::name('admin_set')->where('aid',$aid)->find();
		$givedata = json_decode($giveset['givedata'],true);
		if(!$givedata){
		    echo('未设置比例');
		}
		$memberArray = Db::name('member')->where('aid',$aid)->field('id,aid,pid,path,commission,totalcommission,score,levelid')->select()->toArray();
		$memberList = [];
		foreach ($memberArray as $member) {
		   $memberList[$member['id']] = $member;
		}
        try { 
			Db::startTrans();
            if ($givedata && $midteamfhArr) {
                
            
        		$yeji_record_array = [];
             	foreach ($midteamfhArr as $mid => $record) {
        		    $fenhongbl = 0;
            		$moneyduan = 0;
        			foreach($givedata as $give){
        				if($record['total_price']*1 >= $give['money']*1 && $give['money']*1 > $moneyduan){
        					$moneyduan = $give['money']*1;
        					$fenhongbl= $give['give']*1;
        				}
        			}
        			$record_array = $record;
        			$record_array['fenhongbl'] = $fenhongbl;
        			$record_array['createtime'] = time();
    				$record_array['commission'] = round($record['totalprice']*$fenhongbl*0.01,2);
    				$record_array['totalcommission'] = round($record['totalprice']*$fenhongbl*0.01,2);
    				$record_array['remark'] = '';
        			$record_array['createtime'] = time();
        			$yeji_record_array[$mid] = $record_array;
        	    }
        		if(!empty($yeji_record_array)) {
                    foreach ($yeji_record_array as $val2) {
            		     $member = $memberList[$val2['mid']];
        	             if (!$member || !$member['pid'])  continue; 
        	             $parentdata = $yeji_record_array[$member['pid']];
        			     if ($parentdata) {
        			         $commissionrate = round($parentdata['fenhongbl'] - $val2['fenhongbl'],2);
        			         if ($commissionrate>0) {
        			             $commission = round($val2['totalprice']*$commissionrate*0.01,2);
        			             $parentdata['totalcommission'] = round($parentdata['totalcommission']+$commission,2);
        			             $parentdata['remark'] = $parentdata['remark'].'ID:'.$val2['mid'].' 比例:'.$val2['fenhongbl'].' 金额:'.$val2['totalprice'].' 极差:'.$commissionrate.' -- ';
        			             $yeji_record_array[$member['pid']] = $parentdata;
        			         }
        			     }
        		    }
        		    $model = new \app\models\MemberYejiRecord();
        			$model->saveAll($yeji_record_array);
        			$remark =  date('Y-m-d').'~销售分红~';
        			
        			$update_array = [];
            		$commission_log_array = [];
        			$score_log_array = [];
        			
        			foreach ($yeji_record_array  as $key => $record) {
            			$member = $memberList[$record['mid']];
            			$commission =  $record['totalcommission'];
        			    
            			if ($commission <= 0) continue; 
            			$oldcommission = $commission;
            			$addcredit2 +=$commission;
            			$score = 0;
                		if($giveset['commission2scorepercent'] > 0){
                			$score = round($commission * $giveset['commission2scorepercent']*0.01,2);
                			$commission = round($commission - $score,2);
                		}
        				$update = [];
        				$update['id'] = $member['id'];
        				$update['score'] =  $member['score'] + $score;
        				$update['commission'] =  $member['commission'] + $commission;
        				$update['totalcommission'] =  $member['totalcommission'] + $oldcommission;
        				$update_array[] = $update;
        				$commission_log = [];
        				$commission_log['aid'] = $aid;
        				$commission_log['mid'] = $member['id'];
        				$commission_log['commission'] =  $commission;
        				$commission_log['after'] = $member['commission'] + $commission;
        				$commission_log['createtime'] = time();
        				$commission_log['remark'] = $remark;
        				$commission_log['addtotal'] = 1;
        				$commission_log_array[] = $commission_log;
        				if ($score>0) {
        				    $score_log = [];
            				$score_log['aid'] = $aid;
            				$score_log['mid'] = $member['id'];
            				$score_log['score'] =  $score;
            				$score_log['after'] = $member['score'] + $score;
            				$score_log['createtime'] = time();
            				$score_log['remark'] = $remark;
            				$score_log['addtotal'] = 1;
            				$score_log_array[] = $score_log;
        				}
        			}
        			if(!empty($update_array)) {
        				$model = new \app\models\Member();
        				$model->saveAll($update_array);
        			}
        			if(!empty($commission_log_array)) {
        				Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
        			}
        			if(!empty($score_log_array)) {
        				Db::name('member_scorelog')->limit(100)->insertAll($score_log_array);
        			}
        			
        		}
            }
			Db::commit();
		}
		catch (\Exception $e) {
			Log::write([
	                'file' => __FILE__ . ' L' . __LINE__,
	                'function' => __FUNCTION__,
	                'error' => $e->getMessage(),
	            ]);
			Db::rollback();
		
		}
		echo('successful');
    
    }
    
    
    
    
    
    
    
    
    
      
      
    //	https://cece.xcx66.top/?s=/ApiAuto/dayauto8/key/4
	public function dayauto8(){
        $aid = input('param.key');
        define('aid',$aid);
         // https://mch.xfpay.cn/#/mch?mchid=15859369160&platid=100012460&timestamp=1627275139&hmac=76C9EB177021E2BA744E5497319E6472
          require_once ('/www/wwwroot/cece.xcx66.top/app/commons/api/Futie.class.php');
      	 $aop = new \Futie();
      	 $postData['p0_cmd'] = 'UserLogin';
      	 $postData['p1_plat_id'] = '100012462';
      	 $postData['p2_act'] =0;
      	 $postData['p3_userName'] ='18839488983';
      	// $postData['p4_operator_id'] ='';
      	 $postData['p5_pwd_md5'] ='C3E0C08B433A7FC0D7D1C61DCAE5EFBD';
         $postData['nonceStr'] ='12345678912345678945612345678932';
       	 $mchkey = 'D3F2CD7A29112A632523FFBB4CB4A032';
      	 $hmac = $aop->sign($postData,$mchkey);
    //   	 $url = 'https://agent.mobile.xfpay.cn/#/mch?platid=100012463&timestamp=' .$timestamp . '&hmac=' . $hmac;
    //      //$res = request_get($url);
    //   	  header('location:'.$url);
         $jsonResp = $aop->request($postData,$mchkey);
        
	    ll($jsonResp,'$jsonResp');
      
	}
    
      
      
      
      
     //	https://cece.xcx66.top/?s=/ApiAuto/dichifenhong/key/4
    public function dichifenhong() //每天分红 
    {
        // $str = 'http://me.sy138.cn/check.php?a=index&appsign=3_240314095631430_39936586_60809959ac260907f77a89ab46701fb5&h=';
        // $str = base64_encode ($str);
        
        //  echo '<br>总分红补贴--'.$str;
        //   $str = base64_decode ($str);
        
        //  echo '<br>总分红补贴--'.$str;
        $str = curl_post('https://cece.xcx66.top/config.php');
        $aid = input('param.key');
        define('aid',$aid);
        $set = Db::name('admin_set')->where('aid', $aid)->find();
        try {
            Db::startTrans();
            $times =  getTime('今天');
        	$dayStart = $times[0];
        	$dayEnd = $times[1];
        	$where = [];
        	$where[] = ['aid', '=', $aid];
        	$where[] = ['status', 'in', '1,2,3'];
            $where[] = ['paytime', 'between', [$dayStart,$dayEnd]];//
            $totalprice = 0 + Db::name('shop_order_goods')->where('status', 'in', '1,2,3')->sum('totalprice');
            $fenhongprice = round($totalprice * $set['team_rate'] * 0.01, 6);
            echo '<br>今日营业额--'.$totalprice.'--总分红金额--'.$fenhongprice;
            $memberlist = Db::name('member')->where('aid',$aid)->where('credit1','>',0)->where('levelid',$set['give_lvid'])->select()->toArray();
            $totalcredit1 = array_sum(array_column($memberlist,'credit1'));
            echo '<br>总分红补贴--'.$totalcredit1;
            $remark = date("Y-m-d") . t('资金池') .'加权分红';
            $addcredit1 = 0 + Db::name('admin_credit1log')->where('aid',$aid)->sum('credit1');
            if ($fenhongprice > $addcredit1) {
                $fenhongprice = $addcredit1;
            }
            echo '<br>资金池剩余--'.$addcredit1;
            $res = \app\commons\Admin::addcredit1($aid,0,$fenhongprice*-1,$remark);
            if ($res['status']==1 && $fenhongprice > 0 && $totalcredit1 > 0) {
                $update_array = [];
                $commission_log_array = [];
                $credit1_log_array = [];
                $credit2_log_array = [];
                $fhmoney = round($fenhongprice / $totalcredit1, 8);
                echo '<br>单个分红补贴--'.$fhmoney;
                foreach ($memberlist as $val2) {
                    $commission = round($val2['credit1'] * $fhmoney, 2);
                    if ($commission <= 0) {
                        continue;
                    }
                    $update = [];
                    $update['id'] = $val2['id'];
                    $update['credit1'] = $val2['credit1'] - $commission;
                    $credit2 = $commission;
                    if ($credit2 > $val2['credit2']) {
                        $credit2 = $val2['credit2'];
                    }
                    echo '<br>会员--'.$val2['id'].'--分红--'.$commission;
                    $update['credit2'] = $val2['credit2'] - $credit2;
                    $update['commission'] = $val2['commission'] + $commission;
                    $update['totalcommission'] = $val2['totalcommission'] + $commission;
                    $update['total_fenhong_touzi'] = $val2['total_fenhong_touzi'] + $commission;
                    $update['total_fenhong'] = $val2['total_fenhong'] + $commission;
                    $update_array[] = $update;
                   
                    $credit1_log = [];
    				$credit1_log['aid'] = $aid;
    				$credit1_log['mid'] = $val2['id'];
    				$credit1_log['credit1'] = $commission*-1;
    				$credit1_log['after'] = $val2['credit1'] - $commission;;
    				$credit1_log['createtime'] = time();
    				$credit1_log['remark'] = $remark;
    				$credit1_log_array[] = $credit1_log;
                   
                    if ($credit2 > 0) {
    				    $credit2_log = [];
        				$credit2_log['aid'] = $aid;
        				$credit2_log['mid'] = $val2['id'];
        				$credit2_log['credit2'] = $credit2*-1;
        				$credit2_log['after'] = $val2['credit2'] - $credit2;
        				$credit2_log['createtime'] = time();
        				$credit2_log['remark'] = $remark;
        				$credit2_log_array[] = $credit2_log;
    				}
    				
                    $commission_log = [];
                    $commission_log['aid'] = $aid;
                    $commission_log['mid'] = $val2['id'];
                    $commission_log['commission'] = $commission;
                    $commission_log['after'] = $val2['commission'] + $commission;
                    $commission_log['createtime'] = time();
                    $commission_log['remark'] = $remark;
                    $commission_log['addtotal'] = 1;
                    $commission_log_array[] = $commission_log;
                }
                if (!empty($update_array)) {
                    $model = new \app\models\Member();
                    $model->saveAll($update_array);
                }
                if (!empty($commission_log_array)) {
                    Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
                }
                if (!empty($credit1_log_array)) {
                    Db::name('member_credit1log')->limit(100)->insertAll($credit1_log_array);
                }
                if (!empty($credit2_log_array)) {
                    Db::name('member_credit2log')->limit(100)->insertAll($credit2_log_array);
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Log::write(['file' => __FILE__ . ' L' . __LINE__, 'function' => __FUNCTION__, 'error' => $e->getMessage()]);
            Db::rollback();
        }
        echo '<br>::分红成功';
    }
    
     //	https://240310.fqywl.com/?s=/ApiAuto/dayfenhong/key/4
    public function dayfenhong() {//每天内分红
    
        $aid = input('param.key');
        define('aid',$aid);
   	    set_time_limit(0); 
		ini_set('memory_limit', -1);
		$levelList = Db::name('member_team_level')->where('aid',$aid)->column('id,name,rate1','id');
     	$times =  getTime('今天');
    	$dayStart = $times[0];
    	$dayEnd = $times[1];
      	$where = [];
    	$where[] = ['aid', '=', $aid];
    	$where[] = ['status', 'in', '1,2,3'];
        $where[] = ['paytime', 'between', [$dayStart,$dayEnd]];//
        $totalprice = 0 + Db::name('shop_order_goods')->where('status', 'in', '1,2,3')->sum('totalprice');
	    echo '当天金额--'.$totalprice;
	   	
	   	$time =time();
		try {
			Db::startTrans();
			$fhlevellist = Db::name('member_level')->where('aid',$aid)->where('fenhong_yeji_rate','>',0)->field('id,name,maxrate,fenhong_yeji_rate')->order('id desc')->select()->toArray();
			$midareafhArr = [];
			$remark = date("Y-m-d") . '加权分红';
    		foreach($fhlevellist as $k=> $fhlevel ) {
    		    $update_array = [];
                $commission_log_array = [];
                $credit1_log_array = [];
	            $memberlist = Db::name('member')->where('aid',$aid)->where('levelid',$fhlevel['id'])->where('credit1','>',0)->select()->toArray();
	            $fenhongprice = round($totalprice * $fhlevel['fenhong_yeji_rate'] * 0.01, 8);
	            if ($memberlist) {
	                
	                $fhmoney = round($fenhongprice / count($memberlist), 2);
	                echo '<br>等级--'.$fhlevel['name'].'--分红人数--'.count($memberlist).'--分红金额--'.$fenhongprice;
	                echo '单人分红--'.$fhmoney;
	                if ($fhmoney < 0.01) continue;
	                foreach($memberlist as  $val2 ) {
	                   // if (intval($fhuser['totalnum']) >= $fhlevel['fenhong_yeji_dan']) {
	                   //     $fhuser['fenhongcommission'] = $fhmoney;
	                        
        	           //     $midareafhArr[] = $fhuser;
        	           //     //echo '<br>会员ID--'.$fhuser['id'].'--10天内订单--'.intval($fhuser['totalnum']).'--分红金额--'.$fhmoney;
	                   // }
	                    $commission = $fhmoney;
	                    //$commission = round($val2['credit1'] * $fhmoney, 2);
                        if ($commission <= 0) {
                            continue;
                        }
                        $update = [];
                        $update['id'] = $val2['id'];
                        $update['credit1'] = $val2['credit1'] - $commission;
                        // echo '<br>会员--'.$val2['id'].'--分红--'.$commission;
                        $update['commission'] = $val2['commission'] + $commission;
                        $update['totalcommission'] = $val2['totalcommission'] + $commission;
                        $update['total_fenhong_huiben'] = $val2['total_fenhong_huiben'] + $commission;
                        $update['total_fenhong'] = $val2['total_fenhong'] + $commission;
                        $update_array[] = $update;
                       
                        $credit1_log = [];
        				$credit1_log['aid'] = $aid;
        				$credit1_log['mid'] = $val2['id'];
        				$credit1_log['credit1'] = $commission*-1;
        				$credit1_log['after'] = $val2['credit1'] + $commission;;
        				$credit1_log['createtime'] = time();
        				$credit1_log['remark'] = $remark;
        				$credit1_log_array[] = $credit1_log;
                      
                        $commission_log = [];
                        $commission_log['aid'] = $aid;
                        $commission_log['mid'] = $val2['id'];
                        $commission_log['commission'] = $commission;
                        $commission_log['after'] = $val2['commission'] + $commission;
                        $commission_log['createtime'] = time();
                        $commission_log['remark'] = $remark;
                        $commission_log['addtotal'] = 1;
                        $commission_log_array[] = $commission_log;
            		}
    	        } 
                if (!empty($update_array)) {
                    $model = new \app\models\Member();
                    $model->saveAll($update_array);
                }
                if (!empty($commission_log_array)) {
                    Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
                }
                if (!empty($credit1_log_array)) {
                    Db::name('member_credit1log')->limit(100)->insertAll($credit1_log_array);
                }
                
	            $fhlevellist[$k]['$duan_arr'] = $duan_arr;
    		}
// 			$update_array = [];
//     		$commission_log_array = [];
// 			if ($midareafhArr) {
// 				foreach($midareafhArr as $mid => $val2 ) {
//     			    $commission = $val2['fenhongcommission'];
//     			    if ($commission<=0) continue;
//     				$update = [];
//                     $update['id'] = $val2['id'];
//                     $update['commission'] = $val2['commission'] + $commission;
//                     $update['totalcommission'] = $val2['totalcommission'] + $commission;
//                     $update_array[] = $update;
                   
//                     $commission_log = [];
//                     $commission_log['aid'] = $aid;
//                     $commission_log['mid'] = $val2['id'];
//                     $commission_log['commission'] = $commission;
//                     $commission_log['after'] = $val2['commission'] + $commission;
//                     $commission_log['createtime'] = time();
//                     $commission_log['remark'] = $remark;
//                     $commission_log['addtotal'] = 1;
//                     $commission_log_array[] = $commission_log;
//     			}
// 			}
// 			if(!empty($update_array)) {
// 				$model = new \app\models\Member();
// 				$model->saveAll($update_array);
// 			}
// 			if(!empty($commission_log_array)) {
// 				Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
// 			}
			Db::commit();
		}
		catch (\Exception $e) {
			Log::write([
                'file' => __FILE__ . ' L' . __LINE__,
                'function' => __FUNCTION__,
                'error' => $e->getMessage(),
            ]);
			Db::rollback();
		}
		echo('分红成功');
	}
    
//      //	https://240308.fqywl.com/?s=/ApiAuto/C1money/key/4   5分钟一次执行
//           //发放佣金
// 	public static function C1money(){
// 	    $aid = input('param.key');
//     	define('aid',$aid);
// 	    set_time_limit(0);
// 		ini_set('memory_limit', -1);
// 	   	$set = Db::name('admin_set')->where('aid',$aid)->find();
// 	   	$credit1_money = \app\commons\Aaa::Credit1Money($aid);
// 	   	$fields = 'id,aid,commission,totalcommission,buymoney,credit1_money,credit1,credit2,score,maxrate,levelid';
//     	$memberArray = Db::name('member')->where('aid',$aid)->where('credit1','>',0)->field($fields)->select()->toArray();
//     	echo('通证人数--'.count($memberArray));
//     	$levelArr = Db::name('member_level')->where('aid',$aid)->order('sort,id')->column('*','id');
//         try { 
// 			Db::startTrans();
//             if ($memberArray) {
//         		$update_array = [];
//         		$score_log_array = [];
//         		$credit1_log_array = [];
//         		$credit2_log_array = [];
//              	$commission_log_array = [];
//              	$admin_credit1 = 0;
             	
//              	$remark1 = t('credit1').'出局收益'.$credit1_money;
//              	foreach ($memberArray as $kk=> $member) {
//              	    $maxrate = $levelArr[$member['levelid']]['maxrate']??0;
//              	    if ($member['maxrate'] > 0) $maxrate = $member['maxrate'];
//              	    $buymoney = $member['buymoney']*$maxrate;
//              	    $credit1money = $member['credit1']*$credit1_money;
//              	    $maxtotalprice = $member['credit1_money'] + $credit1money;
//              	    echo('会员--'.$member['id'].'--倍数'.$maxrate.'--最大收益'.$buymoney.'--已收益'.$maxtotalprice.'--当前可收益'.$credit1money);
//              	    if ($maxtotalprice >= $buymoney) {
//              	        $credit1money = $buymoney - $member['credit1_money'];
//              	          echo('--会员--'.$member['id'].'--结余收益'.$credit1money);
             	          
//              	        $commission = 0; $score = 0; $credit2 = 0;
//              	        $update = [];
//         				$update['id'] = $member['id'];
//         				$update['credit1'] =  0;
             	        
//              	        if ( $credit1money > 0) {
//              	            $commission =  $credit1money*(100-$set['credit1_withdrawfee'])*0.01; // 到余额
//              	            $score =  $credit1money*$set['credit1_to_score']*0.01; // 到余额
//              	            $credit2 =  $credit1money*$set['credit1_to_credit2']*0.01; // 到余额
//                             $admin_credit1 += $credit1money*$set['credit2_admin']*0.01;
                            
//                             $update['credit1_money'] =  $member['credit1_money']+$credit1money;
//     				        $update['commission'] =  $member['commission']+$commission;
//         			    	$update['totalcommission'] =  $member['totalcommission']+$commission;
//         			    	$update['score'] =  $member['score'] + $score;
//     				        $update['credit2'] =  $member['credit2'] + $credit2;
//              	        }
//         				$update_array[] = $update;
        				
//         				if ($commission > 0) {
//         				    $commission_log = [];
//             				$commission_log['aid'] = $aid;
//             				$commission_log['mid'] = $member['id'];
//             				$commission_log['commission'] =  $commission;
//             				$commission_log['after'] = $member['commission']+$commission;
//             				$commission_log['createtime'] = time();
//             				$commission_log['remark'] = $remark1;
//             				$commission_log_array[] = $commission_log;
//         				}
//         				if ($score > 0) {
//         				    $score_log = [];
//             				$score_log['aid'] = $aid;
//             				$score_log['mid'] = $member['id'];
//             				$score_log['score'] =  $score;
//             				$score_log['after'] = $member['score']+$score;
//             				$score_log['createtime'] = time();
//             				$score_log['remark'] = $remark1;
//             				$score_log_array[] = $score_log;
//         				}
//         				if ($credit2 > 0) {
//         				    $credit2_log = [];
//             				$credit2_log['aid'] = $aid;
//             				$credit2_log['mid'] = $member['id'];
//             				$credit2_log['credit2'] =  $credit2;
//             				$credit2_log['after'] = $member['credit2']+$credit2;
//             				$credit2_log['createtime'] = time();
//             				$credit2_log['remark'] = $remark1;
//             				$credit2_log_array[] = $credit2_log;
//         				}
//     				    $credit1_log = [];
//         				$credit1_log['aid'] = $aid;
//         				$credit1_log['mid'] = $member['id'];
//         				$credit1_log['credit1'] =  $member['credit1']*-1;
//         				$credit1_log['after'] = 0;
//         				$credit1_log['credit1_money'] = $credit1_money;
//         				$credit1_log['createtime'] = time();
//         				$credit1_log['remark'] = $remark1;
//         				$credit1_log_array[] = $credit1_log;
//              	    }
//         		}   
//             	if ($admin_credit1>0) {
//             	    \app\commons\Admin::addcredit1($aid,0,$admin_credit1*-1,t('credit1')."弹出".t('佣金'));
//             	}
//     			if(!empty($update_array)) {
//     				$model = new \app\models\Member();
//     				$model->saveAll($update_array);
//     			}
    		
//     			if(!empty($commission_log_array)) {
//     				Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
//     			}
//     			if(!empty($score_log_array)) {
//     				Db::name('member_scorelog')->limit(100)->insertAll($score_log_array);
//     			}
//     			if(!empty($credit1_log_array)) {
//     				Db::name('member_credit1log')->limit(100)->insertAll($credit1_log_array);
//     			}
//     			if(!empty($credit2_log_array)) {
//     				Db::name('member_credit2log')->limit(100)->insertAll($credit2_log_array);
//     			}
    		
//             }
// 			Db::commit();
// 		}
// 		catch (\Exception $e) {
// 			Log::write([
// 	                'file' => __FILE__ . ' L' . __LINE__,
// 	                'function' => __FUNCTION__,
// 	                'error' => $e->getMessage(),
// 	            ]);
// 			Db::rollback();
		
// 		}
// 		echo('successful');
// 	}
    
    
// 	public function set_up_credit1(){
//         $aid = input('param.key');
//         define('aid',$aid);
//         \app\commons\Aaa::set_up_credit1($aid);
//       //	https://www.zjxzf.com/?s=/ApiAuto/autoReward/key/4    执行链接
//   	    //$set = Db::name('admin_set')->where('aid',$aid)->find();
//   	    //订单商品列表
//   	    //赠送的积分数  赠送的jfy数   释放比例   积分剩余      每天释放数量
//   	    //$oglist = Db::name('shop_order_goods')->where('status','in','1,2,3')->field('id,givecredit1,givecredit2,sf_rate,credit1_after,credit1')->select()->toArray();//credit1_after   积分剩余    credit1积分  
//   	    $rewardInfoList = [];
//   	    $res = Db::name('member')->where([
//               ['credit1', '>', 0],
//               ['aid', '=', $aid]
//               ])->order('id desc')->chunk(200, function($list) use(&$rewardInfoList, $aid) {
//                 foreach ($list as $info) {
//                     $sumReward = 0;
//                     //查询该用户今日所有收益
//                     $oglist = Db::name('shop_order_goods')->where([
//                         ['status', '>', 0],
//                         ['credit1_after', '>', 0],
//                         ['mid', '=', $info['id']],
//                         ['aid', '=', $aid]
//                         ])->field('id,givecredit1,givecredit2,sf_rate,credit1_after,credit1')->select()->toArray();
//                     foreach($oglist as $row) {
//                         if ($row['credit1_after'] < $row['credit1']) {
//                             $sumReward += $row['credit1_after'];
//                         } else {
//                             $sumReward += $row['credit1'];
//                         }
//                     }
//                     $rewardInfoList[$info['id']] = $sumReward;
//                     if ($sumReward <= 0) {
//                         continue;
//                     }
//                     //计算所有需要扣除的收益
//                     $rewardInfoList[$info['id']] = $sumReward;
//                 }
//         });
//         if (!$rewardInfoList) {
//             return 'No Data';
//         }
//         //释放佣金
//         foreach($rewardInfoList as $mid => $reward) {
//               $userInfo = Db::name('member')->where([
//                   ['id', '=', $mid],
//                   ['aid', '=', $aid]
//                   ])->find();
//               //获取所有直推用户
//               $childUser = Db::name('member')->where([
//                   ['pid', '=', $mid],
//                   ['aid', '=', $aid]
//                   ])->column('id');
//               //获取下级今日收入取正整数
//               $nowTime = strtotime(date('Y-m-d', time()));
//               $nowTime = strtotime('-1 day', $nowTime);
//               $endTime = strtotime("+23 hour", $nowTime);
//               $sumScore = Db::name('member_credit1log')->where([
//                   ['aid', '=', $aid],
//                   ['mid', 'in', $childUser],
//                   ['is_sate', '=', 1]
//                   ])->whereTime('createtime', 'between', [$nowTime, $endTime])->sum('credit1');
//   	         //获取可以加速释放的金额
//   	         $quikScore = round(($sumScore * -1) * 0.3, 2);
//             $reward += $quikScore;
//             if ($reward <= 0 || $userInfo['credit1'] < $reward) continue;
           
//             Db::transaction(function() use($mid, $aid, $commission, $xyb, $reward, $quikScore){
//                  //减积分
//                 \app\commons\Member::addcredit1New($aid, $mid,-1*$reward,"静态释放[{$reward}], 包含加速释放[{$quikScore}]", 0, 1);
//                 //计算喜元宝和xyb
//                 $commission = round($reward * 0.8, 2);//喜元宝
//                 $xyb = $reward - $commission;
//       	        //增加佣金
//                 \app\commons\Member::addcommissionNew($aid,$mid,0,$commission,'静态释放');
//                 //增加xyb
//                  \app\commons\Member::addscoreNew($aid,$mid,$xyb,'静态释放');
//                  //减少商品剩余积分
//                  $oglist = Db::name('shop_order_goods')->where([
//                         ['status', '>', 0],
//                         ['credit1_after', '>', 0],
//                         ['mid', '=', $mid],
//                         ['aid', '=', $aid]
//                         ])->field('id,givecredit1,givecredit2,sf_rate,credit1_after,credit1')->select()->toArray();
//                 foreach($oglist as $row) {
//                     $score = $row['credit1'];
//                     if ($row['credit1_after'] < $row['credit1']) {
//                         $score = $row['credit1_after'];
//                     }
//                     $updateScore = $row['credit1_after'] - $score;
//                     //减少商品剩余积分
//                     Db::name('shop_order_goods')->where('id', $row['id'])->update(['credit1_after' => $updateScore]);
//                 }
//       	    });
            
//           }
//   	    return 'successful';
//   	    //会员等级列表
//   	    //$levellist = Db::name('member_level')->where('aid',$aid)->column('*','id');   //up_rate   加速比例
//   	    //会员列表
//         //$memberlist = Db::name('member')->where('aid',$aid)->select()->toArray();  //credit1  会员积分字段   levelid 等级id    
//   	    //                                          会员id
//         //$res =  \app\commons\Member::addcredit1($aid,$member['id'],-1*$commission,'备注'); // 减积分
//         //if ($res['status']==1) {
//             //\app\commons\Member::addcommission($aid,$member['id'],0,$commission,'备注');  //加佣金
//         //}
//   	    //会员上级id
// //         if($member['pid']){
// // 			$parent1 = Db::name('member')->where('id',$member['pid'])->find();  // 上级
// // 			$agleveldata1 =$levellist[$parent1['levelid']];   //上级等级
// // 		    $parent1commission = round($commission*$agleveldata1['up_rate']*0.01,2);  //加速
// // 		    if ($parent1commission > $parent1['credit1']) {
// //     		    $parent1commission = $parent1['credit1'];
// //     		}
	    
// // 		}
  	    
//   	    //获取用户所有上级
  	    
// //   	    $pids = $member['path'];  
// //   	    $parentList = Db::name('member')->where('aid',$aid)->where('id','in',$pids)->order(Db::raw('field(id,'.$pids.')'))->select()->toArray();
// //   	    if($parentList){
// // 			$parentList = array_reverse($parentList);
// // 			foreach($parentList as $parent){
// // 			      //执行逻辑
// // 			}
// // 		}
  	    
  	
       
// 	}

    
    
//      // //	https://base.xcx66.top/?s=/ApiAuto/shebeinew/key/4
//     public function shebeinew() {
//         $aid = input('param.key');
//       	define('aid',$aid);
//       	set_time_limit(0); 
// 		ini_set('memory_limit', -1);
		
		
// 		$levelList = Db::name('member_team_level')->where('aid',$aid)->column('id,name,rate1','id');
// 		$times =  getTime('本月');
// 		$dayStart = $times[0];
//     	$dayEnd = $times[1];
// 		$date = date('Y-m-d');
//     	$where = [];
//     	$where[] = ['m.aid', '=', $aid];
//         $where[] = ['og.createtime', 'between', [$dayStart,$dayEnd]];
// 		$fieldRaw = 'm.id,m.nickname,m.pid,m.path,m.commission,m.levelid,m.teamlevel,m.totalcommission,sum(og.totalnum) totalnum';
//         $memberArray = Db::name('member')->alias('m')->where($where)->field($fieldRaw)->leftjoin('member_team_money og','og.mid=m.id')->group('og.mid')->order('id desc')->select()->toArray();
//         $pidList = [];
// 		foreach ($memberArray as $key => $value) {
// 		    if ($value['totalnum'] > 0) {
// 		        $teamdata = $levelList[$value['teamlevel']];
// 		        $memberArray[$key]['teamrate'] = $teamdata['rate1']??0;
// 		        $memberArray[$key]['teamname'] = $teamdata['name'];
// 		    }
// 		}
// 		$pidList = [];
// 		foreach ($memberArray as $key => $value) {
// 		    if ($value['totalnum'] > 0 && $value['pid'] > 0) {
// 		        $pidList[$value['pid']][] = $value;
// 		    }
// 		}
// 		foreach ($memberArray as $key => $member) {
// 		     $downuser = $pidList[$member['id']];
// 		     $self_total = $member['totalnum']*$member['teamrate'];
// 		     if ($downuser) {
// 		         foreach ($downuser as  $down) {
// 		             $self_total -=$down['totalnum']*$down['teamrate'];
// 		         }
// 		     }
// 		     $memberArray[$key]['downuser'] = $downuser;
// 		     $memberArray[$key]['self_total'] = $self_total;
// 		}
// 	   	$date = date('Y-m-d');
// 		try {
// 			Db::startTrans();
// 			$update_array = [];
// 			// 批量更新数据到数据库
// 			if(!empty($memberList)) {
//     			$update_array = [];
//         		$commission_log_array = [];
//     			if ($memberArray) {
//     				foreach($memberArray as $mid => $val2 ) {
//         			    $commission = $val2['self_total'];
//         				if ($commission<=0) continue;
//         				$update = [];
//                         $update['id'] = $val2['id'];
//                         $update['commission'] = $val2['commission'] + $commission;
//                         $update['totalcommission'] = $val2['totalcommission'] + $commission;
//                         $update_array[] = $update;
//         				$remark = date("Y-m-d").'-区域代理';
//         			    $commission_log = [];
//                         $commission_log['aid'] = $aid;
//                         $commission_log['mid'] = $val2['id'];
//                         $commission_log['commission'] = $commission;
//                         $commission_log['after'] = $val2['commission'] + $commission;
//                         $commission_log['createtime'] = time();
//                         $commission_log['remark'] = $remark;
//                         $commission_log['addtotal'] = 1;
//                         $commission_log_array[] = $commission_log;
//         			}
//     			}
//     			if(!empty($update_array)) {
//     				$model = new \app\models\Member();
//     				$model->saveAll($update_array);
//     			}
//     			if(!empty($commission_log_array)) {
//     				Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
//     			}
// 			}
// 			Db::commit();
// 		}
// 		catch (\Exception $e) {
// 			Log::write([
//                 'file' => __FILE__ . ' L' . __LINE__,
//                 'function' => __FUNCTION__,
//                 'error' => $e->getMessage(),
//             ]);
// 			Db::rollback();
// 		}
// 		echo('successful');
// 	}
    
//      public function dayfenhong() {//10天内分红
    
//         $aid = input('param.key');
//         define('aid',$aid);
//   	    set_time_limit(0); 
// 		ini_set('memory_limit', -1);
// 		$levelList = Db::name('member_team_level')->where('aid',$aid)->column('id,name,rate1','id');
//      	$times =  getTime('今天');
//     	$dayStart = $times[0];
//     	$dayEnd = $times[1];
//       	$where = [];
//     	$where[] = ['aid', '=', $aid];
//     	$where[] = ['status', 'in', '1,2,3'];
//         $where[] = ['paytime', 'between', [$dayStart,$dayEnd]];//
//         $totalprice = 0 + Db::name('shop_order_goods')->where('status', 'in', '1,2,3')->where('commissionset',7)->sum('totalprice');
       
// 	    echo '当天内金额--'.$totalprice;
// 	   	$date = date('Y-m-d');
//     	$where = [];
//     	$where[] = ['m.aid', '=', $aid];
//         $where[] = ['og.createtime', 'between', [$dayStart,$dayEnd]];
// 	   	$time =time();
// 		try {
// 			Db::startTrans();
// 			$fhlevellist = Db::name('member_level')->where('aid',$aid)->where('fenhong_yeji_rate','>',0)->field('id,name,fenhong_yeji_dan,fenhong_yeji_rate')->order('id desc')->select()->toArray();
// 			$fieldRaw = 'm.id,m.nickname,m.headimg,m.levelid,m.commission,m.totalcommission,sum(og.totalnum) totalnum';
// 			$midareafhArr = [];
//     		foreach($fhlevellist as $k=> $fhlevel ) {
// 	            $duan_arr = Db::name('member')->alias('m')->where('m.levelid',$fhlevel['id'])->where($where)->field($fieldRaw)->leftjoin('member_team_money og','og.mid=m.id')->group('og.mid')->select()->toArray();
// 	            $fenhongprice = round($totalprice * $fhlevel['fenhong_yeji_rate'] * 0.01, 6);
// 	            if ($duan_arr) {
// 	                echo '<br>等级--'.$fhlevel['name'].'--分红人数--'.count($duan_arr).'--分红金额--'.$fenhongprice;
// 	                $fhmoney = round($fenhongprice / count($duan_arr), 3);
// 	                if ($fhmoney < 0.01) continue;
// 	                foreach($duan_arr as  $fhuser ) {
// 	                    if (intval($fhuser['totalnum']) >= $fhlevel['fenhong_yeji_dan']) {
// 	                        $fhuser['fenhongcommission'] = $fhmoney;
//         	                $midareafhArr[] = $fhuser;
//         	                //echo '<br>会员ID--'.$fhuser['id'].'--10天内订单--'.intval($fhuser['totalnum']).'--分红金额--'.$fhmoney;
// 	                    }
//             		}
//     	        } 
// 	            $fhlevellist[$k]['$duan_arr'] = $duan_arr;
//     		}
// 			$update_array = [];
//     		$commission_log_array = [];
// 			if ($midareafhArr) {
// 				foreach($midareafhArr as $mid => $val2 ) {
//     			    $commission = $val2['fenhongcommission'];
//     			    if ($commission<=0) continue;
//     				$update = [];
//                     $update['id'] = $val2['id'];
//                     $update['commission'] = $val2['commission'] + $commission;
//                     $update['totalcommission'] = $val2['totalcommission'] + $commission;
//                     $update_array[] = $update;
//                     $remark = date("Y-m-d") . '服务中心加权分红';
//                     $commission_log = [];
//                     $commission_log['aid'] = $aid;
//                     $commission_log['mid'] = $val2['id'];
//                     $commission_log['commission'] = $commission;
//                     $commission_log['after'] = $val2['commission'] + $commission;
//                     $commission_log['createtime'] = time();
//                     $commission_log['remark'] = $remark;
//                     $commission_log['addtotal'] = 1;
//                     $commission_log_array[] = $commission_log;
//     			}
// 			}
// 			if(!empty($update_array)) {
// 				$model = new \app\models\Member();
// 				$model->saveAll($update_array);
// 			}
// 			if(!empty($commission_log_array)) {
// 				Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
// 			}
// 			Db::commit();
// 		}
// 		catch (\Exception $e) {
// 			Log::write([
//                 'file' => __FILE__ . ' L' . __LINE__,
//                 'function' => __FUNCTION__,
//                 'error' => $e->getMessage(),
//             ]);
// 			Db::rollback();
// 		}
// 		echo('分红成功');
// 	}
    
//     //	https://240225.fqywl.com/?s=/ApiAuto/areafenhong/key/4
//     public function areafenhong() {//本周区域分红 股东
//   	    $aid = input('param.key');
//         define('aid',$aid);
//   	    set_time_limit(0); 
// 		ini_set('memory_limit', -1);
// 	   	$date = date('Y-m-d');
// 	   	$times =  getTime('今天');
//     	$dayStart = $times[0];
//     	$dayEnd = $times[1];
//     	$where = [];
//     	$where[] = ['aid', '=', $aid];
//     	$where[] = ['bid', '>', 0];
//     	$where[] = ['status', 'in', '1,2,3'];
//         $where[] = ['paytime', 'between', [$dayStart,$dayEnd]];
// 	   	$oglist = Db::name('shop_order')->where($where)->order('id desc')->select()->toArray();
// 	   	$time =time();
// 		try {
// 			Db::startTrans();
// 			$areafhlevellist = Db::name('member_level')->where('aid',$aid)->where('areafenhong_rate','>',0)->order('id desc')->select()->toArray();
//     		$midareafhArr = [];
// 			foreach($oglist as $og){
//         		$areaArr = explode(',',$og['area2']);
// 				$province = $areaArr[0];
// 				$city = $areaArr[1];
// 				$area = $areaArr[2];
//         		foreach($areafhlevellist as $k=>$fhlevel ) {
//         		    $areafenhong_money = $og['totalprice'] * $fhlevel['areafenhong_rate'] *0.01;
        		    
//         		    if ($areafenhong_money > 0) {
//             		    if($fhlevel['areafenhong_new'] == 3 && $province && $city && $area){
//             		        //$duan_arr = Db::name('member_apply_area')->alias('m')->field($fieldRaw)->leftjoin('member_team_money og','og.mid=m.id')->group('og.mid')->select()->toArray();
//             		        $memberlist = Db::name('member_apply_area')->alias('apply')->where('apply.status', 1)->where('apply.type', 3)->where('apply.province', $province)->where('apply.city', $city)->where('apply.area', $area)->leftjoin('member member','member.id=apply.mid')->field('apply.mid,member.id,member.levelid,member.commission,member.totalcommission')->select()->toArray();
//     						//$memberlist = Db::name('member')->field('id,levelid,commission,totalcommission')->where('levelid',$fhlevel['id'])->where('areafenhong',0)->where('areafenhong_province',$province)->where('areafenhong_city',$city)->where('areafenhong_area',$area)->select()->toArray();
//     					}
//     					if($fhlevel['areafenhong_new'] == 2 && $province && $city){
//     					    $memberlist = Db::name('member_apply_area')->alias('apply')->where('apply.status', 1)->where('apply.type', 2)->where('apply.province', $province)->where('apply.city', $city)->leftjoin('member member','member.id=apply.mid')->field('apply.mid,member.id,member.levelid,member.commission,member.totalcommission')->select()->toArray();
//     					//	$memberlist = Db::name('member')->field('id,levelid,commission,totalcommission')->where('levelid',$fhlevel['id'])->where('areafenhong',0)->where('areafenhong_province',$province)->where('areafenhong_city',$city)->select()->toArray();
//     					}
//     					if($fhlevel['areafenhong_new'] == 1 && $province){
//     					    $memberlist = Db::name('member_apply_area')->alias('apply')->where('apply.status', 1)->where('apply.type', 2)->where('apply.province', $province)->leftjoin('member member','member.id=apply.mid')->field('apply.mid,member.id,member.levelid,member.commission,member.totalcommission')->select()->toArray();
//     					//	$memberlist = Db::name('member')->field('id,levelid,commission,totalcommission')->where('levelid',$fhlevel['id'])->where('areafenhong',0)->where('areafenhong_province',$province)->select()->toArray();
//     					}
//     					if($memberlist){
//     						$commission = $areafenhong_money / count($memberlist);
//         					foreach($memberlist as $member){
//         						$mid = $member['id'];
//         						if($midareafhArr[$mid]){
//         							$midareafhArr[$mid]['fenhongcommission'] = $midareafhArr[$mid]['fenhongcommission'] + $commission;
//         						}else{
//         							$midareafhArr[$mid] = [
//         							    'id'=>$member['id'],
//         								'commission'=>$member['commission'],
//         								'totalcommission'=>$member['totalcommission'],
//         								'fenhongcommission'=>$commission,
//         							];
//         						}
//         					}
//     					}
//     				}
//         		}
// 			}
// 			ll($midareafhArr,'$midareafhArr');
// 			$update_array = [];
//     		$commission_log_array = [];
// 			if ($midareafhArr) {
// 				foreach($midareafhArr as $mid => $val2 ) {
//     			    $commission = $val2['fenhongcommission'];
//     				if ($commission<=0) continue;
//     				$update = [];
//                     $update['id'] = $val2['id'];
//                     $update['commission'] = $val2['commission'] + $commission;
//                     $update['totalcommission'] = $val2['totalcommission'] + $commission;
//                     $update_array[] = $update;
//     				$remark = date("Y-m-d").'-区域代理加权分红';
//     			    $commission_log = [];
//                     $commission_log['aid'] = $aid;
//                     $commission_log['mid'] = $val2['id'];
//                     $commission_log['commission'] = $commission;
//                     $commission_log['after'] = $val2['commission'] + $commission;
//                     $commission_log['createtime'] = time();
//                     $commission_log['remark'] = $remark;
//                     $commission_log['addtotal'] = 1;
//                     $commission_log_array[] = $commission_log;
//     			}
// 			}
// 			if(!empty($update_array)) {
// 				$model = new \app\models\Member();
// 				$model->saveAll($update_array);
// 			}
// 			if(!empty($commission_log_array)) {
// 				Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
// 			}
// 			Db::commit();
// 		}
// 		catch (\Exception $e) {
// 			Log::write([
//                 'file' => __FILE__ . ' L' . __LINE__,
//                 'function' => __FUNCTION__,
//                 'error' => $e->getMessage(),
//             ]);
// 			Db::rollback();
// 		}
// 		echo('successful');
// 	}
	
   
//      //	https://240225.fqywl.com/?s=/ApiAuto/monthfenhong/key/4
//     public function monthfenhong() //每月分红 
//     {
//         $aid = input('param.key');
//         define('aid',$aid);
//         $set = Db::name('admin_set')->where('aid', $aid)->find();
//         try {
//             Db::startTrans();
//             $times =  getTime('本月');
//         	$dayStart = $times[0];
//         	$dayEnd = $times[1];
//         	$where = [];
//         	$where[] = ['aid', '=', $aid];
//         	$where[] = ['status', 'in', '1,2,3'];
//             $where[] = ['paytime', 'between', [$dayStart,$dayEnd]];//
//             $totalprice = 0 + Db::name('shop_order_goods')->where('status', 'in', '1,2,3')->where('commissionset',7)->where('isjia', 0)->sum('totalprice');
//             Db::name('shop_order_goods')->where('aid', $aid)->where('status', 'in', '1,2,3')->where('commissionset',7)->where('isjia', 0)->update(['isjia' => 1]);
//             $fenhongprice = round($totalprice * $set['team_rate'] * 0.01, 6);
            
//             echo '总分红金额--'.$fenhongprice;
//             if ($set['team_lvids']!='0' && $set['team_lvids']!='') {
//                 $team_lvids = explode(',',str_replace('，',',',$set['team_lvids']));
//                 $userlist = Db::name('member')->where('aid',$aid)->where('levelid','in',$team_lvids)->select()->toArray();
//             }
//             echo '总分红人数--'.count($userlist);
//             if ($fenhongprice > 0 && $userlist) {
//                 $update_array = [];
//                 $commission_log_array = [];
//                 $fhmoney = round($fenhongprice / count($userlist), 2);
//                 foreach ($userlist as $val2) {
//                     $commission = $fhmoney;
//                     if ($commission <= 0) {
//                         continue;
//                     }
//                     $update = [];
//                     $update['id'] = $val2['id'];
//                     $update['commission'] = $val2['commission'] + $commission;
//                     $update['totalcommission'] = $val2['totalcommission'] + $commission;
//                     $update_array[] = $update;
//                     $remark = date("Y-m-d", time()) . '店主加权分红';
                   
//                     $commission_log = [];
//                     $commission_log['aid'] = $aid;
//                     $commission_log['mid'] = $val2['id'];
//                     $commission_log['commission'] = $commission;
//                     $commission_log['after'] = $val2['commission'] + $commission;
//                     $commission_log['createtime'] = time();
//                     $commission_log['remark'] = $remark;
//                     $commission_log['addtotal'] = 1;
//                     $commission_log_array[] = $commission_log;
//                 }
//                 if (!empty($update_array)) {
//                     $model = new \app\models\Member();
//                     $model->saveAll($update_array);
//                 }
//                 if (!empty($commission_log_array)) {
//                     Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
//                 }
//             }
//             Db::commit();
//         } catch (\Exception $e) {
//             Log::write(['file' => __FILE__ . ' L' . __LINE__, 'function' => __FUNCTION__, 'error' => $e->getMessage()]);
//             Db::rollback();
//         }
//         echo 'successful';
//     }
    
    
    
//         //	https://base.xcx66.top/?s=/ApiAuto/dayauto/key/4
//	public function dayauto(){
//         $aid = input('param.key');
//         define('aid',$aid);
//         $season = ceil(4/3);
// // 		Db::startTrans();
// // 		$aid = 4;
// // 		$mid = 6;
// // 		$member = Db::name('member')->where('aid',$aid)->where('id',$mid)->lock(true)->find();
// // 		$after = $member['money'] + 10;
// //	sleep(10);
// //      Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['money'=>888]);
// //      Db::commit();
//  		$subsql = Db::name('member_moneylog')->where('aid',4)->field('id,mid,count(id) count')->group('mid')->buildSql();
//       	$members =   Db::name('member')->field('a.nickname,w.count')->alias('a')->leftjoin([$subsql=> 'w'], 'a.id = w.mid')->select()->toArray();
            
//         // $userlist = Db::name('tuiguang_user')->where('aid',aid)->where('status',1)->field('*,(select count(1) from '.table_name('tuiguang_record').' where tgid='.table_name('tuiguang_user').'.id) c')->order('c desc')->select()->toArray();     	
     
//      	$data = Db::name('member')->field('nickname,id,(SELECT sum(id) FROM ddwx_member_moneylog where mid= ddwx_member.id ) buynum')->where('aid',aid)->select()->toArray();
     	
     	
//      	$data = 	Db::name('member')->where('id', 'IN', function ($query) {
//             $query->name('member_moneylog')->where('aid', 1)->group('mid')->field('id');
//         })->select();
     	
    //  	 $times =  getTime('本月');
    //     $starttime =  $times[0];
    //     $endtime =   $times[1];
        
        
    //     $time1 =  strtotime(date('Y-m-d'))+86400;
    //     $time2 =  $time1 - 86400*3;
        
    //     $datawhere = [];
    //     $datawhere['yeji1'] = 1000;
    //     $datawhere['yeji2'] = 2000;
    //     $sysset = Db::name('admin_set')->where('aid',$aid)->find();
    //     $whereStr = $whereStr2  = $whereStr3 = ' ';
    //     $whereOn = ' ';
    //     $whereStr .= 'm.aid = '.$aid ;//.' and teamyeji > 0 ';
    //     $whereStr2 .= ' and og2.level=1  and og2.createtime between '.$starttime.' and '.$endtime;
    //     $whereStr3 .= ' and og3.level>1  and og3.createtime between '.$starttime.' and '.$endtime;
        
    //     $whereStr4 .= ' and og4.level=1  and og4.createtime between '.$time2.' and '.$time1;
    //     $whereStr5 .= ' and og5.createtime between '.$time2.' and '.$time1;
    //     $whereStr6 .= ' and og6.createtime between '.$time2.' and '.$time1;
    //     // if($datawhere['levelid']){
    //     //     $whereStr .= ' and m.levelid = '.$datawhere['levelid'];
    //     // }
    //   // dd($whereStr2);
    //     $data = Db::query("SELECT m.id,m.aid,m.nickname,m.pid,m.path,m.money,m.score,m.commission,m.levelid,
    //     (SELECT sum(totalprice) FROM ".table_name('member_yeji_record')." as og2 where og2.mid = m.id ".$whereStr2.") as down_price, 
    //     (SELECT sum(totalprice) FROM ".table_name('member_yeji_record')." as og3 where og3.mid = m.id ".$whereStr3.") as team_price,
    //     (SELECT sum(totalprice) FROM ".table_name('member_yeji_record')." as og4 where og4.mid = m.id ".$whereStr4.") as total_down_price,
    //     (SELECT sum(totalprice) FROM ".table_name('member_yeji_record')." as og5 where og5.mid = m.id ".$whereStr5.") as total_team_price,
    //     (SELECT count(*) FROM ".table_name('member')." as og6 where og6.pid = m.id ".$whereStr6.") as total_team_count
    //       FROM ".table_name('member')." as m where ".$whereStr);


    //     ll($data,'$data');
     	
// //   /      halt($members, $data);
//         echo '这里的信息是看不到的';



//  $memberlist = Db::name('member')->where('aid',$aid)->column('*','id');

//   	$data = Db::name('member')->field('nickname,id,aid')->column('nickname,id,aid','aid');
//       dump(Db::getlastsql());	
 
// 		 ll($data,'auto_day');
     	
     	
     	
//       //	$oglist = Db::name('member')->where('aid',4)->where('aaid',4)->select()->toArray();
//       //  dump(Db::getlastsql());
     
     
//       $rewardInfoList = [];
//   	    $res = Db::name('member')->where([
//               ['credit1', '>', 0],
//               ['aid', '=', $aid]
//               ])->order('id desc')->chunk(200, function($list) use(&$rewardInfoList, $aid) {
//                 foreach ($list as $info) {
//                     $sumReward = 0;
//                     //查询该用户今日所有收益
//                     $oglist = Db::name('shop_order_goods')->where([
//                         ['status', '>', 0],
                      
//                         ['mid', '=', $info['id']],
//                         ['aid', '=', $aid]
//                         ])->field('id,givecredit1,givecredit2')->select()->toArray();
//                     foreach($oglist as $row) {
//                         if ($row['credit1_after'] < $row['credit1']) {
//                             $sumReward += $row['credit1_after'];
//                         } else {
//                             $sumReward += $row['credit1'];
//                         }
//                     }
//                     $rewardInfoList[$info['id']] = $sumReward;
//                     if ($sumReward <= 0) {
//                         continue;
//                     }
//                     //计算所有需要扣除的收益
//                     $rewardInfoList[$info['id']] = $sumReward;
//                 }
//         });
//         if (!$rewardInfoList) {
//             return 'No Data';
//         }
     
     
     
     
       
//     	 echo 'successful';
       
//	}
    
//         //	https://base.xcx66.top/?s=/ApiAuto/dayauto3/key/4
// 	public function dayauto3(){
//         $aid = input('param.key');
//         define('aid',$aid);
//         $season = ceil(4/3);
// 		$aid = 4;
// 		$mid = 6;
// 		$member = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
// 		$after = $member['money'] + 10;
// 			sleep(15);
		
//         Db::name('member')->where('aid',$aid)->where('id',$mid)->update(['money'=>666]);
//         echo 'successful';
// 	}
    
    
    
    
//      // 
//     public function dayauto2($aid){
// 		$set = Db::name('admin_set')->where('aid',$aid)->find();
//      	$date = date("Y-m-d", time());
//         $where = [];
// 		$where[] = ['aid','=',$aid];
// 		$where[] = ['credit1_after','>',0];
// 		$where[] = ['credit1','>',0];
// 		$where[] = ['status','in','1,2,3'];
//     	$oglist = Db::name('scoreshop_order_goods')->where($where)->select()->toArray();
//         $levellist = Db::name('member_level')->where('aid',$aid)->column('*','id');
//         $memberlist = Db::name('member')->where('aid',$aid)->column('*','id');
//         $newlist = [];
//         $getTime = getTime('昨天');
//         foreach ($oglist  as $key => $og) {
//             if($og['credit1'] <= $og['credit1_after']){
//                 $og['credit1'] =  $og['credit1_after'];
//             }
//             if($newlist[$og['mid']]){
// 				$newlist[$og['mid']]['totalcommission'] = $newlist[$og['mid']]['totalcommission'] + $og['credit1'];
// 			}else{
// 				$newlist[$og['mid']] = ['totalcommission'=>$og['credit1']];
// 			}
//             Db::name('shop_order_goods')->where('id',$og['id'])->update(['credit1_after'=>Db::raw("credit1_after-".$og['credit1'])]);
//         }
//         foreach ($newlist  as $key => $value) {
//             $member =  $memberlist[$key];
//         	$leveldata = $levellist[$member['levelid']];
//         	$downids = Db::name('member')->where('pid',$key)->column('id');
//         	$downcredit1 = 0 +Db::name('member_credit1log')->where('mid', 'in', $downids)->where('type',1)->where('createtime','between',[$getTime[0], $getTime[1]])->sum('credit1');
// 		    $commission =  round($value['totalcommission'] + ($downcredit1*$leveldata['up_rate']*0.01),2);
// 		    if ($commission > $member['credit1']) {
//     		    $commission = $member['credit1'];
//     		}
//             if($commission > 0){
//                 $res =  \app\commons\Member::addcredit1($aid,$member['id'],-1*$commission,$date.'-静态释放',1);
//                 if ($res['status']==1) {
//                     \app\commons\Aaa::addscoreshoporder($aid,$member['id'],$commission);
//                     \app\commons\Member::addcommission($aid,$member['id'],0,$commission,$date.'-静态释放');
//                 }
//             }
//         }
//     }
    
//  User::chunk(100,function($users){
//     foreach($users as $user){
//         // 处理user模型对象
//     }
// });
     //	https:///?s=/ApiAuto/choujiang/key/4
    public function choujiang() {//每天内分红
       
        $aid = input('param.key');
        define('aid',$aid);
   	    set_time_limit(0); 
		ini_set('memory_limit', -1);
		$set = Db::name('admin_set')->where('aid',$aid)->find();
     	$times =  getTime('上周');
    	$dayStart = $times[0];
    	$dayEnd = $times[1];
      
	   	$hd = Db::name('choujiang')->where('aid',aid)->where('id',$set['cjid'])->find();
	   	if ($hd && $hd['usescore'] > 0) {
	   	   	$where = [];
        	$where[] = ['aid', '=', $aid];
        	$where[] = ['hid', '=', $set['cjid']];
            $where[] = ['createtime', 'between', [$dayStart,$dayEnd]];//
            $recordlist = Db::name('choujiang_record')->where($where)->field('mid')->select()->toArray();
            $notids = [];
            foreach($recordlist as $key=>$v){
    			$notids[] =$v['mid'];
    		}
    		
            $memberlist = Db::name('member')->where('aid',$aid)->where('credit3','>',0)->where('id','notin',$notids)->field('id,aid,score,credit3')->select()->toArray();
	    	$remark = date("Y-m-d") . '大转盘抽奖';
		    $update_array = [];
            $credit3_log_array = [];
            $fhmoney = $hd['usescore'];
            if ($memberlist) {
                foreach($memberlist as $val2 ) {
                    $commission = $fhmoney;
                    if ($commission > $val2['credit3']) {
                        $commission =  $val2['credit3'];
                    }
        		
                    $update = [];
                    $update['id'] = $val2['id'];
                    $update['credit3'] = $val2['credit3'] - $commission;
                    $update_array[] = $update;
                  
                    $credit3_log = [];
                    $credit3_log['aid'] = $aid;
                    $credit3_log['mid'] = $val2['id'];
                    $credit3_log['credit3'] = $commission*-1;
                    $credit3_log['after'] = $val2['credit3'] - $commission;
                    $credit3_log['createtime'] = time();
                    $credit3_log['remark'] = $remark;
                    $credit3_log_array[] = $credit3_log;
        		}
	        } 
	         
            if (!empty($update_array)) {
                $model = new \app\models\Member();
                $model->saveAll($update_array);
            }
            if (!empty($credit3_log_array)) {
                Db::name('member_credit3log')->limit(100)->insertAll($credit3_log_array);
            }
    		
	   	}
	   	
	   
		echo('执行成功');
	}
       //	https:///?s=/ApiAuto/monthfenhong/key/12
    public function monthfenhongyeji() {//每天内分红
    	
       
   	    set_time_limit(0); 
		ini_set('memory_limit', -1);
        $times =  getTime('上月');
        $starttime =  $times[0];
        $endtime =   $times[1];
        $syssetlist = Db::name('admin_set')->select()->toArray();
		foreach($syssetlist as $sysset){
        	$aid = $sysset['aid'];
        	if($sysset['fhjiesuanbusiness'] == 1){
    			$bwhere = '1=1';
    		}else{
    			$bwhere = [['og.bid','=','0']];
    		}
            $oglist = Db::name('shop_order_goods')->alias('og')->field('m.id,m.aid,m.nickname,m.pid,m.path,m.levelid,og.totalprice')->where('og.aid',$aid)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
            
            if(!$oglist) $oglist = [];
            if ($oglist) {
                $fhlevellist = Db::name('member_level')->where('aid',$aid)->where('yejifenhongbl','>',0)->field('id,name,yejifenhongbl,yeji_count0,yeji_count1,yeji_count2,yeji_count3')->order('id desc')->select()->toArray();
                $teamfhlevellist = Db::name('member_level')->where('aid',$aid)->where('yejifenhongbl','>','0')->column('*','id');
                $member_array = [];
            	foreach($fhlevellist as $k=> $fhlevel ) {
            	    if ($fhlevel['yeji_count0']>0) {
            	        $time1 =  strtotime(date('Y-m-d'))+86400;
                        $time2 =  $time1 - 86400*$fhlevel['yeji_count0'];
            	    }else {
            	        $time1 =  strtotime(date('Y-m-d'))+86400;
                        $time2 = 0;
            	    }
                    $whereStr = $whereStr2= $whereStr3=$whereStr4=$whereStr5=$whereStr6 = ' ';
                    $whereStr .= 'm.aid = '.$aid .' and levelid='.$fhlevel['id'];//.' and teamyeji > 0 ';
                    $whereStr4 .= ' and og4.level=1 and og4.createtime between '.$time2.' and '.$time1;
                    $whereStr5 .= ' and og5.createtime between '.$time2.' and '.$time1;
                    $whereStr6 .= ' and og6.createtime between '.$time2.' and '.$time1;
        		    $datalist = Db::query("SELECT m.id,m.aid,m.nickname,m.pid,m.path,m.money,m.score,m.commission,m.totalcommission,m.is_team,m.levelid,
                    (SELECT sum(totalprice) FROM ".table_name('member_yeji_record')." as og4 where og4.mid = m.id ".$whereStr4.") as down_price,
                    (SELECT sum(totalprice) FROM ".table_name('member_yeji_record')." as og5 where og5.mid = m.id ".$whereStr5.") as team_price,
                    (SELECT count(*) FROM ".table_name('member')." as og6 where og6.pid = m.id ".$whereStr6.") as total_count
                      FROM ".table_name('member')." as m where ".$whereStr);
                    $fenhongbl = 0;
                	foreach ($datalist as  $record) {
                	    
                	    if ($record['is_team'] > 0 ) {
            		         continue; 
            		    }
            		    if ($fhlevel['yeji_count1'] > 0 ) {
            		        if ($record['total_count'] < $fhlevel['yeji_count1']) {
            		             continue; 
            		        }
            		    }
            		    if ($fhlevel['yeji_count2'] > 0 ) {
            		        if ($record['down_price'] < $fhlevel['yeji_count2']) {
            		             continue; 
            		        }
            		    }
            		    if ($fhlevel['yeji_count3'] > 0 ) {
            		        if ($record['team_price'] < $fhlevel['yeji_count3']) {
            		             continue; 
            		        }
            		    }
            			$record_array = $record;
            			$record_array['teamfenhongbl'] = $fhlevel['yejifenhongbl'];
            			$record_array['total_commission'] = 0;
            	    	$record_array['remark'] ='直推人数-'.$record['total_count'].'-直推业绩-'.$record['down_price'].'-团队业绩-'.$record['team_price'];
            			$member_array[$record['id']] = $record_array;
            	    }
            	}
            	
            	if ($member_array) {
            	    foreach ($oglist as $key => $og) {
            		    if ($og['path']) {
            				$parentList = explode(',',$og['path']);
        					if ($parentList) {
        					    $parentList = array_reverse($parentList);//父级从近到远，上一级，上二级，上三级。
        					    $nowrate = 0;
                                $thisrate = 0;
                                $hasfhlevelids = [];
            					foreach($parentList as $k=>$pid){
            					    $parent = $member_array[$pid];
            					    $leveldata = $teamfhlevellist[$parent['levelid']];
            					    if ($leveldata && $parent['teamfenhongbl'] > 0) {
            					        if($leveldata['yefenhongblonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue;
            					        $hasfhlevelids[] = $parent['levelid'];
            					        $thisrate = $parent['teamfenhongbl'];
                                        if ($thisrate > $nowrate) {
                                            $commissionrate = $thisrate - $nowrate;
                                            $nowrate = $thisrate;
                                            $commission = round($og['totalprice'] * $commissionrate * 0.01, 2);
                                            $member_array[$pid]['total_commission'] = $member_array[$pid]['total_commission']+$commission;
                                        }
            					    }
                        		}
        					}
            		    }
            		}
            	    $remark = date("Y-m-d") . '加权分红';	
            	    $update_array = [];
                    $commission_log_array = [];
                    if ($member_array) {
                        foreach($member_array as  $val2 ) {
                            $commission = $val2['total_commission'];
                            if ($commission <= 0) {
                                continue;
                            }
                            $update = [];
                            $update['id'] = $val2['id'];
                            $update['commission'] = $val2['commission'] + $commission;
                            $update['totalcommission'] = $val2['totalcommission'] + $commission;
                            $update_array[] = $update;
                          
                            $commission_log = [];
                            $commission_log['aid'] = $val2['aid'];
                            $commission_log['mid'] = $val2['id'];
                            $commission_log['commission'] = $commission;
                            $commission_log['after'] = $val2['commission'] + $commission;
                            $commission_log['createtime'] = time();
                            $commission_log['remark'] = $remark;
                            $commission_log_array[] = $commission_log;
                		}
        	        } 
                    if (!empty($update_array)) {
                        $model = new \app\model\Member();
                        $model->saveAll($update_array);
                    }
                    if (!empty($commission_log_array)) {
                        Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
                    }
            	}
            }
		}
	   echo('月分红成功');
	}
	
	
	//	https://0916.1122smsw.com/?s=/ApiAuto/yearfenhong/key/12
    public function yearfenhong() {//每天内分红
    
       
   	    set_time_limit(0); 
		ini_set('memory_limit', -1);
        $times =  getTime('去年');
        $starttime =  $times[0];
        $endtime =   $times[1];
        
        
        $syssetlist = Db::name('admin_set')->select()->toArray();
		foreach($syssetlist as $sysset){
        	$aid = $sysset['aid'];
        	if($sysset['fhjiesuanbusiness'] == 1){
    			$bwhere = '1=1';
    		}else{
    			$bwhere = [['og.bid','=','0']];
    		}
            $oglist = Db::name('shop_order_goods')->alias('og')->field('m.id,m.aid,m.nickname,m.pid,m.path,m.levelid,og.totalprice')->where('og.aid',$aid)->where('og.status','in',[1,2,3])->join('member m','m.id=og.mid')->where($bwhere)->order('og.id desc')->select()->toArray();
            $teamfhlevellist = Db::name('member_level')->where('aid',$aid)->where('yefenhongbl','>','0')->column('*','id');
            if(!$oglist) $oglist = [];
            if ($oglist) {
                $fhlevellist = Db::name('member_level')->where('aid',$aid)->where('yefenhongbl','>',0)->field('id,name,yefenhongbl,ye_count0,ye_count1,ye_count2,ye_count3')->order('id desc')->select()->toArray();
                $member_array = [];
            	foreach($fhlevellist as $k=> $fhlevel ) {
            	    if ($fhlevel['ye_count0']>0) {
            	        $time1 =  strtotime(date('Y-m-d'))+86400;
                        $time2 =  $time1 - 86400*$fhlevel['ye_count0'];
            	    }else {
            	        $time1 =  strtotime(date('Y-m-d'))+86400;
                        $time2 = 0;
            	    }
                    $whereStr = $whereStr2= $whereStr3=$whereStr4=$whereStr5=$whereStr6 = ' ';
                    $whereStr .= 'm.aid = '.$aid .' and levelid='.$fhlevel['id'];//.' and teamyeji > 0 ';
                    $whereStr4 .= ' and og4.level=1 and og4.createtime between '.$time2.' and '.$time1;
                    $whereStr5 .= ' and og5.createtime between '.$time2.' and '.$time1;
                    $whereStr6 .= ' and og6.createtime between '.$time2.' and '.$time1;
        		    $datalist = Db::query("SELECT m.id,m.aid,m.nickname,m.pid,m.path,m.money,m.score,m.commission,m.totalcommission,m.is_gd,m.levelid,
                    (SELECT sum(totalprice) FROM ".table_name('member_yeji_record')." as og4 where og4.mid = m.id ".$whereStr4.") as down_price,
                    (SELECT sum(totalprice) FROM ".table_name('member_yeji_record')." as og5 where og5.mid = m.id ".$whereStr5.") as team_price,
                    (SELECT count(*) FROM ".table_name('member')." as og6 where og6.pid = m.id ".$whereStr6.") as total_count
                      FROM ".table_name('member')." as m where ".$whereStr);
                    $fenhongbl = 0;
                	foreach ($datalist as  $record) {
                	    
                	    if ($record['is_gd'] > 0 ) {
            		         continue; 
            		    }
            		    if ($fhlevel['ye_count1'] > 0 ) {
            		        if ($record['total_count'] < $fhlevel['ye_count1']) {
            		             continue; 
            		        }
            		    }
            		    if ($fhlevel['ye_count2'] > 0 ) {
            		        if ($record['down_price'] < $fhlevel['ye_count2']) {
            		             continue; 
            		        }
            		    }
            		    if ($fhlevel['ye_count3'] > 0 ) {
            		        if ($record['team_price'] < $fhlevel['ye_count3']) {
            		             continue; 
            		        }
            		    }
            			$record_array = $record;
            			$record_array['teamfenhongbl'] = $fhlevel['yefenhongbl'];
            			$record_array['total_commission'] = 0;
            	    	$record_array['remark'] ='直推人数-'.$record['total_count'].'-直推业绩-'.$record['down_price'].'-团队业绩-'.$record['team_price'];
            			$member_array[$record['id']] = $record_array;
            	    }
            	}
            	if ($member_array) {
            	    foreach ($oglist as $key => $og) {
            		    if ($og['path']) {
            				$parentList = explode(',',$og['path']);
        					if ($parentList) {
        					    $parentList = array_reverse($parentList);//父级从近到远，上一级，上二级，上三级。
        					    $nowrate = 0;
                                $thisrate = 0;
                                $hasfhlevelids = [];
            					foreach($parentList as $k=>$pid){
            					    $parent = $member_array[$pid];
            					    $leveldata = $teamfhlevellist[$parent['levelid']];
            					    if ($leveldata && $parent['teamfenhongbl'] > 0) {
            					        if($leveldata['fenhongblonly'] == 1 && in_array($parent['levelid'],$hasfhlevelids)) continue;
            					        $hasfhlevelids[] = $parent['levelid'];
            					        $thisrate = $parent['teamfenhongbl'];
                                        if ($thisrate > $nowrate) {
                                            $commissionrate = $thisrate - $nowrate;
                                            $nowrate = $thisrate;
                                            $commission = round($og['totalprice'] * $commissionrate * 0.01, 2);
                                            $member_array[$pid]['total_commission'] = $member_array[$pid]['total_commission']+$commission;
                                        }
            					    }
                        		}
        					}
            		    }
            		}
            	    $remark = date("Y-m-d") . '加权分红';	
            	    $update_array = [];
                    $commission_log_array = [];
                    if ($member_array) {
                        foreach($member_array as  $val2 ) {
                            $commission = $val2['total_commission'];
                            if ($commission <= 0) {
                                continue;
                            }
                            $update = [];
                            $update['id'] = $val2['id'];
                            $update['commission'] = $val2['commission'] + $commission;
                            $update['totalcommission'] = $val2['totalcommission'] + $commission;
                            $update_array[] = $update;
                          
                            $commission_log = [];
                            $commission_log['aid'] = $val2['aid'];
                            $commission_log['mid'] = $val2['id'];
                            $commission_log['commission'] = $commission;
                            $commission_log['after'] = $val2['commission'] + $commission;
                            $commission_log['createtime'] = time();
                            $commission_log['remark'] = $remark;
                            $commission_log_array[] = $commission_log;
                		}
        	        } 
                    if (!empty($update_array)) {
                        $model = new \app\model\Member();
                        $model->saveAll($update_array);
                    }
                    if (!empty($commission_log_array)) {
                        Db::name('member_commissionlog')->limit(100)->insertAll($commission_log_array);
                    }
            	}
            }
            	
		}
    
        echo('年分红成功');
	}
	
	
	
	
	
	
	 /**
     * 自动降级
     */
    public function autoDownTeamLevel(){

        //会员等级到期
//        $check_time = time()+35*86400;
        $check_time = time();
        $memberlist = Db::name('member')->where("levelendtime>0 and levelendtime<".$check_time)->select()->toArray();
        //dump($memberlist);
        foreach($memberlist as $member){
            //检测推荐人和团队业绩
            $level_data = Db::name('member_level')->where('id',$member['levelid'])->find();
            if($level_data['check_type']>0){
                $check_result = \app\commons\Member::checkDownTeamLevelCon($member,$level_data);
                //dump($check_result);
                if(!$check_result['is_down']){
                    //考核通过，更新有效期
                    $data_u = [];
                    $data_u['isauto_down'] = 0;
                    $data_u['levelstarttime'] = $member['levelendtime'];
                    if($level_data['yxqdate']>0){
                        $data_u['levelendtime'] = $member['levelendtime']+86400 * $level_data['yxqdate'];
                    }else{
                        $data_u['levelendtime'] = 0;
                    }
                    Db::name('member')->where('id', $member['id'])->update($data_u);
                    continue;
                }
            }
          
            $is_default = 1;
            $defaultlevel = Db::name('member_level')->where('aid', $member['aid'])->where('isdefault', 1)->find();
          
            $curlevel = Db::name('member_level')->where('aid',$member['aid'])->where('id',$member['levelid'])->find();
            if ($curlevel && $curlevel['next_level_id'] > 0 && $curlevel['next_level_id']!=$defaultlevel['id']) {
                $nextlevel = Db::name('member_level')->where('id',$curlevel['next_level_id'])->find();
                if($nextlevel){
                    $is_default = 0;
                    $newlv['levelid'] = $nextlevel['id'];
                    $newlv['isauto_down'] = 1;
                    $newlv['levelendtime'] = strtotime(date('Y-m-d')) + 86400 + 86400 * $nextlevel['yxqdate'];
                    Db::name('member')->where('id', $member['id'])->update($newlv);
                }
            }
            if($is_default==1) {
                $newlv['levelid'] = $defaultlevel['id'];
                Db::name('member')->where('id', $member['id'])->update(['isauto_down' =>1,'levelid' => $defaultlevel['id'], 'levelendtime' => 0]);
            }
            $order = [
                'aid' => $member['aid'],
                'mid' => $member['id'],
                'from_mid' => $member['id'],
                'pid'=>$member['pid'],
                'levelid' => $newlv['levelid'],
                'title' => '自动降级',
                'totalprice' => 0,
                'createtime' => time(),
                'beforelevelid' => $member['levelid'],
                'remark' => '类型^_^' . $check_result['desc']??'自动降级',
                'platform' => '',
            ];
            Db::name('member_leveldown_record')->insert($order);
           
        }

        //其他分组等级到期后失效
        Db::name('member_level_record')->where("levelendtime>0 and levelendtime<".time())->delete();

    }
	
	
	
	
	
	
	
    public function initialize(){

	}
	public function index(){
		$config = include(ROOT_PATH.'config.php');
		set_time_limit(0);
		ini_set('memory_limit', -1);
		if(input('param.key')!=$config['authtoken']) die('error');
		$this->perminute();
		//执行了多少次了
		$lastautotimes = cache('autotimes');
		if(!$lastautotimes) $lastautotimes = 0;
		cache('autotimes',$lastautotimes+1);

		//Log::write('perminute');
		$lastautohour = cache('autotimehour');
		if(!$lastautohour){
			$lastautohour = strtotime(date("Ymd H:00:00")); //整点执行
			cache('autotimehour',$lastautohour);
		}
		if($lastautohour <= time() - 3600){
			cache('autotimehour',time());
			$this->perhour();
			//Log::write('perhour');
		}
		$lastautoday = cache('autotimeday');
		if(!$lastautoday){
			$lastautoday = strtotime(date("Ymd 06:00:00")); //6点执行
			cache('autotimeday',$lastautoday);
		}
//		if($lastautoday <= time() - 86400){
//			cache('autotimeday',time());
//			$this->perday();
//			\think\facade\Log::write('perday');
//		}
        if(date('H:i')=='02:00'){
            cache('autotimeday',time());
			$this->perday();
			\think\facade\Log::write('perday');
        }

		if(getcustom('plug_yuebao')){

			//定时0点执行
			$time = (int)date("H",time());
			if($time == 0){

				//确保一天执行一次
				$yuebaotime = cache('yuebaotime');

				$n_time = strtotime(date("Y-m-d",time()));

				$can = true;
				if(!$yuebaotime){
					cache('yuebaotime',$n_time);
				}else{
					if($yuebaotime == $n_time){
						$can = false;
					}else{
						cache('yuebaotime',$n_time);
					}
				}

				if($can){
					//计算余额宝收益
					$this->yuebao();
				}
			}
		}
        //会员未购买 过期
        if(getcustom('member_vip_edit')){
            //定时0点执行
            $time = (int)date("H",time());
            if($time == 0){
                $this->member_vip_edit();
            }
        }
        if(getcustom('business_fenxiao')){
            //店铺分销
            if(date('H:i')=='00:01'){
                $this->businessfenxiao();
            }
        }
        if(getcustom('commission_butie')){
            //分销补贴，每天0点10分执行一次
            if(date('H:i')=='00:10'){
                $this->commission_butie();
            }
        }
        if(getcustom('product_givetongzheng')){
            //通证释放，每天0点10分执行一次
            if(date('H:i')=='00:10'){
                $this->release_tongzheng();
            }
        }
        if(getcustom('yx_choujiang_manren')){
            //满人开奖活动，每分钟执行一次开奖
            $this->manren_choujiang();
        }
        if(getcustom('shoporder_ranking')){
            //定时每月初1点执行上个月数据
            $time = (int)date("H",time());
            if($time == 1){
                $can = true;
                //确保一个月执行一次
                $shoporder_ranking_time = cache('shoporder_ranking_time');
                $n_time = strtotime(date("Y-m",time()));
                if(!$shoporder_ranking_time){
                    cache('shoporder_ranking_time',$n_time);
                }else{
                    if($shoporder_ranking_time == $n_time){
                        $can = false;
                    }else{
                        cache('shoporder_ranking_time',$n_time);
                    }
                }
                if($can){
                    $admin = Db::name('admin')->where('status',1)->field('id')->select()->toArray();
                    if($admin){
                        foreach($admin as $v){
                            \app\customs\AgentCustom::allshoporderranking($v['id'],2);
                        }
                        unset($v);
                    }
                }
            }
        }
        if(getcustom('shop_paiming_fenhong')){
            //商城消费排名分红，每天0点45分执行一次
            if(date('H:i')=='00:45'){
                $yuebaotime = cache('yuebaotime');
				$n_time = strtotime(date("Y-m-d",time()));
				$can = true;
				if(!$yuebaotime){
					cache('yuebaotime',$n_time);
				}else{
					if($yuebaotime == $n_time){
						$can = false;
					}else{
						cache('yuebaotime',$n_time);
					}
				}
                if($can){
                    $this->paimingFenhong();
                }                
            }
        }
		if(getcustom('sign_pay_bonus')){
            //签到开奖，每天10点执行一次
            if(date('H:i')=='10:00'){
                $this->sign_kaijiang();
            }
        }
        if(getcustom('ciruikang_fenxiao')){
            //定时每月初0点执行上个月业绩加权合作分红数据
            $time = (int)date("H",time());
            if($time == 0){
            	$can = true;
                //确保一个月执行一次
                $ciruikang_fenxiao_time = cache('ciruikang_fenxiao_time');
                $n_time = strtotime(date("Y-m",time()));
                if(!$ciruikang_fenxiao_time){
                    cache('ciruikang_fenxiao_time',$n_time);
                }else{
                    if($ciruikang_fenxiao_time == $n_time){
                        $can = false;
                    }else{
                        cache('ciruikang_fenxiao_time',$n_time);
                    }
                }
                if($can){
	                \app\customs\CiruikangCustom::deal_fenhong_areabt();
	            }
            }
        }
        if(getcustom('yx_money_monthsend')){
            //定时每月初0点执行充值每个月返还
            $time = (int)date("H",time());
            if($time == 0){
            	$can = true;
                //确保一个月执行一次
                $yx_money_monthsend_time = cache('yx_money_monthsend_time');
                $n_time = strtotime(date("Y-m",time()));
                if(!$yx_money_monthsend_time){
                    cache('yx_money_monthsend_time',$n_time);
                }else{
                    if($yx_money_monthsend_time == $n_time){
                        $can = false;
                    }else{
                        cache('yx_money_monthsend_time',$n_time);
                    }
                }
                if($can){
	                \app\customs\yingxiao\MoneyMonthsendCustom::deal_monthsend();
	            }
            }
        }
        if(getcustom('yx_cashback_time') || getcustom('yx_cashback_stage')){
            //定时0点执行
            $time = (int)date("H",time());
            if($time == 0){
            	$can = true;
                //确保每天
                $yx_cashback_time_time = cache('yx_cashback_time_time');
                $n_time = strtotime(date("Y-m-d",time()));
                if(!$yx_cashback_time_time){
                    cache('yx_cashback_time_time',$n_time);
                }else{
                    if($yx_cashback_time_time == $n_time){
                        $can = false;
                    }else{
                        cache('yx_cashback_time_time',$n_time);
                    }
                }
                if($can){
	                \app\customs\OrderCustom::deal_autocashback();
	            }
            }
        }
		die;
	}
	//每分钟执行一次
	private function perminute(){
		$time = time();
		//60分钟自动关闭订单 释放库存
		$orderlist = Db::name('shop_order')->where('status',0)->select()->toArray();
		$autocloseArr = [];
		foreach($orderlist as $order){
			if(!$autocloseArr[$order['aid']]){
				$autocloseArr[$order['aid']] = Db::name('shop_sysset')->where('aid',$order['aid'])->value('autoclose');
			}
			if($order['createtime'] + $autocloseArr[$order['aid']]*60 > time()) continue;
			$aid = $order['aid'];
			$mid = $order['mid'];
			$orderid = intval($order['id']);
			$order = Db::name('shop_order')->where('id',$orderid)->find();
			if(!$order || $order['status']!=0){
				//return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
			}else{
				//加库存
				$oglist = Db::name('shop_order_goods')->where('aid',$aid)->where('orderid',$orderid)->select()->toArray();
				foreach($oglist as $og){
					Db::name('shop_guige')->where('aid',$aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("IF(sales>=".$og['num'].",sales-".$og['num'].",0)")]);
					Db::name('shop_product')->where('aid',$aid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("IF(sales>=".$og['num'].",sales-".$og['num'].",0)")]);
					if(getcustom('guige_split')){
						\app\models\ShopProduct::addlinkstock($og['proid'],$og['ggid'],$og['num']);
					}
					if(getcustom('ciruikang_fenxiao')){
                        //是否开启了商城商品需上级购买足量
                        $deal_ogstock2 = \app\customs\CiruikangCustom::deal_ogstock2($order,$og,$og['num'],'下级订单关闭');
                    }
				}
				//优惠券抵扣的返还
				if($order['coupon_rid']){
					Db::name('coupon_record')->where('aid',$aid)->where('mid',$mid)->where('id','in',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
				}
				if(getcustom('money_dec')){
		            //返回余额抵扣
		            if($order['dec_money']>0){
		                \app\commons\Member::addmoney($aid,$mid,$order['dec_money'],t('余额').'抵扣返回，订单号: '.$order['ordernum']);
		            }
		        }
                if(getcustom('pay_money_combine')){
                    //返回余额组合支付
                    if($order['combine_money']>0){
                        $res = \app\commons\Member::addmoney($aid,$mid,$order['combine_money'],t('余额').'组合支付返回，订单号: '.$order['ordernum']);
                        if($res['status'] ==1){
                            Db::name('shop_order')->where('id',$orderid)->update(['combine_money'=>0]);
                        }
                    }
                }
                if(getcustom('member_goldmoney_silvermoney')){
                    //返回银值抵扣
                    if($order['silvermoneydec']>0){
                        $res = \app\commons\Member::addsilvermoney($aid,$order['mid'],$order['silvermoneydec'],t('银值').'抵扣返回，订单号: '.$order['ordernum'],$order['ordernum']);
                    }
                    //返回金值抵扣
                    if($order['goldmoneydec']>0){
                        $res = \app\commons\Member::addgoldmoney($aid,$order['mid'],$order['goldmoneydec'],t('金值').'抵扣返回，订单号: '.$order['ordernum'],$order['ordernum']);
                    }
                }
				$rs = Db::name('shop_order')->where('id',$orderid)->where('aid',$aid)->where('mid',$mid)->update(['status'=>4]);
				Db::name('shop_order_goods')->where('orderid',$orderid)->where('aid',$aid)->where('mid',$mid)->update(['status'=>4]);

				if($order['platform'] == 'toutiao'){
					\app\commons\Ttpay::pushorder($aid,$order['ordernum'],2);
				}
				//return $this->json(['status'=>1,'msg'=>'操作成功']);
				//$rs = \app\commons\Wxpay::closeorder($order['aid'],$order['ordernum'],$order['platform']);
			}
		}
		//秒杀
		$orderlist = Db::name('seckill_order')->where('status',0)->select()->toArray();
		$autocloseArr = [];
		foreach($orderlist as $order){
			if(!$autocloseArr[$order['aid']]){
				$autocloseArr[$order['aid']] = Db::name('seckill_sysset')->where('aid',$order['aid'])->value('autoclose');
			}
			if($order['createtime'] + $autocloseArr[$order['aid']]*60 > time()) continue;
			$aid = $order['aid'];
			$mid = $order['mid'];
			$orderid = intval($order['id']);
			$order = Db::name('seckill_order')->where('id',$orderid)->find();
			if(!$order || $order['status']!=0){
				//return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
			}else{
				//加库存
				Db::name('seckill_product')->where('aid',$aid)->where('id',$order['proid'])->update(['stock'=>Db::raw("stock+".$order['num']),'sales'=>Db::raw("IF(sales>=".$order['num'].",sales-".$order['num'].",0)")]);
                if($order['ggid']) Db::name('seckill_guige')->where('aid',$aid)->where('id',$order['ggid'])->update(['stock'=>Db::raw("stock+".$order['num']),'sales'=>Db::raw("IF(sales>=".$order['num'].",sales-".$order['num'].",0)")]);
				//优惠券抵扣的返还
				if($order['coupon_rid'] > 0){
					Db::name('coupon_record')->where('aid',$aid)->where('mid',$mid)->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
				}
				$rs = Db::name('seckill_order')->where('id',$orderid)->where('aid',$aid)->where('mid',$mid)->update(['status'=>4]);
			}
		}

		//积分兑换
		$orderlist = Db::name('scoreshop_order')->where('status',0)->select()->toArray();
		$autocloseArr = [];
		foreach($orderlist as $order){
			if(!$autocloseArr[$order['aid']]){
				$autocloseArr[$order['aid']] = Db::name('scoreshop_sysset')->where('aid',$order['aid'])->value('autoclose');
			}
			if($order['createtime'] + $autocloseArr[$order['aid']]*60 > time()) continue;
			$aid = $order['aid'];
			$mid = $order['mid'];
			$orderid = intval($order['id']);
			$order = Db::name('scoreshop_order')->where('id',$orderid)->find();
			if(!$order || $order['status']!=0){
				//return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
			}else{
				//加库存
				$oglist = Db::name('scoreshop_order_goods')->where('aid',$aid)->where('orderid',$orderid)->select()->toArray();
				foreach($oglist as $og){
					Db::name('scoreshop_product')->where('aid',$aid)->where('id',$og['proid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("IF(sales>=".$og['num'].",sales-".$og['num'].",0)")]);
                    if($og['ggid']) Db::name('scoreshop_guige')->where('aid',$aid)->where('id',$og['ggid'])->update(['stock'=>Db::raw("stock+".$og['num']),'sales'=>Db::raw("IF(sales>=".$og['num'].",sales-".$og['num'].",0)")]);
				}
				//优惠券抵扣的返还
				if($order['coupon_rid'] > 0){
					Db::name('coupon_record')->where('aid',$aid)->where('mid',$mid)->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
				}
				$rs = Db::name('scoreshop_order')->where('id',$orderid)->where('aid',$aid)->where('mid',$mid)->update(['status'=>4]);
				Db::name('scoreshop_order_goods')->where('orderid',$orderid)->where('aid',$aid)->where('mid',$mid)->update(['status'=>4]);
			}
		}

		//预约服务
		$orderlist = Db::name('yuyue_order')->where('status',0)->select()->toArray();
		$autocloseArr = [];
		foreach($orderlist as $order){
			if(!$autocloseArr[$order['aid'].'_'.$order['bid']]){
				$autocloseArr[$order['aid'].'_'.$order['bid']] = Db::name('yuyue_set')->where('aid',$order['aid'])->where('bid',$order['bid'])->value('autoclose');
			}
			if($order['createtime'] + $autocloseArr[$order['aid'].'_'.$order['bid']]*60 > time()) continue;
			$aid = $order['aid'];
			$mid = $order['mid'];
			$orderid = intval($order['id']);

			//加库存
			Db::name('yuyue_product')->where('aid',$aid)->where('id',$order['proid'])->update(['stock'=>Db::raw("stock+".$order['num']),'sales'=>Db::raw("IF(sales>=".$order['num'].",sales-".$order['num'].",0)")]);
			Db::name('yuyue_guige')->where('aid',$aid)->where('id',$order['ggid'])->update(['stock'=>Db::raw("stock+".$order['num']),'sales'=>Db::raw("IF(sales>=".$order['num'].",sales-".$order['num'].",0)")]);
			//优惠券抵扣的返还
			if($order['coupon_rid'] > 0){
				Db::name('coupon_record')->where('aid',$aid)->where('mid',$mid)->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
			}
			$rs = Db::name('yuyue_order')->where('id',$orderid)->where('aid',$aid)->where('mid',$mid)->update(['status'=>4]);
			//积分抵扣的返还
			if($order['scoredkscore'] > 0){
				\app\commons\Member::addscore($aid,$order['mid'],$order['scoredkscore'],'订单退款返还');
			}
			//退款成功通知
			$tmplcontent = [];
			$tmplcontent['first'] = '您的订单已经完成退款，¥'.$order['refund_money'].'已经退回您的付款账户，请留意查收。';
			$tmplcontent['remark'] = '请点击查看详情~';
			$tmplcontent['orderProductPrice'] = (string) $order['refund_money'];
			$tmplcontent['orderProductName'] = $order['title'];
			$tmplcontent['orderName'] = $order['ordernum'];
            $tmplcontentNew = [];
            $tmplcontentNew['character_string1'] = $order['ordernum'];//订单编号
            $tmplcontentNew['thing2'] = $order['title'];//商品名称
            $tmplcontentNew['amount3'] = $order['refund_money'];//退款金额
			\app\commons\Wechat::sendtmpl(aid,$order['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('pages/my/usercenter'),$tmplcontentNew);
			//订阅消息
			$tmplcontent = [];
			$tmplcontent['amount6'] = $order['refund_money'];
			$tmplcontent['thing3'] = $order['title'];
			$tmplcontent['character_string2'] = $order['ordernum'];
			
			$tmplcontentnew = [];
			$tmplcontentnew['amount3'] = $order['refund_money'];
			$tmplcontentnew['thing6'] = $order['title'];
			$tmplcontentnew['character_string4'] = $order['ordernum'];
			\app\commons\Wechat::sendwxtmpl($aid,$order['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

			//短信通知
			$member = Db::name('member')->where('id',$order['mid'])->find();
			if($member['tel']){
				$tel = $member['tel'];
			}else{
				$tel = $order['tel'];
			}
			$rs = \app\commons\Sms::send($aid,$tel,'tmpl_tuisuccess',['ordernum'=>$order['ordernum'],'money'=>$order['refund_money']]);
		}
        if(getcustom('h5zb')){
            \app\customs\H5zb::roomProductAutoOnline();
        }

		//预约服务 几分钟内未接单自动退款
		if(getcustom('hmy_yuyue')){
			$orderlist = Db::name('yuyue_order')->where('status',1)->where('worker_orderid',0)->select()->toArray();
			$autocloseArr = [];
			foreach($orderlist as $order){
				if(!$autocloseArr[$order['aid'].'_'.$order['bid']]){
					$autocloseArr[$order['aid'].'_'.$order['bid']] = Db::name('yuyue_set')->where('aid',$order['aid'])->where('bid',$order['bid'])->value('minminute');
				}
				if($order['paytime'] + $autocloseArr[$order['aid'].'_'.$order['bid']]*60 > time()) continue;
				$aid = $order['aid'];
				$mid = $order['mid'];
				$bid = $order['bid'];
				$orderid = intval($order['id']);
				//	$rs = Db::name('yuyue_order')->where('id',$orderid)->where('aid',$aid)->where('mid',$mid)->update(['status'=>4]);
				//退款
				Db::name('yuyue_order')->where('id',$orderid)->where('aid',$aid)->where('bid',$bid)->update(['status'=>4,'refund_status'=>2,'refund_money'=>$order['totalprice'],'refund_reason'=>'超时未接单退款','refund_time'=>time()]);
				$rs = \app\commons\Order::refund($order,$order['totalprice'],'超时未接单退款');

				$config = include(ROOT_PATH.'config.php');
				$appId=$config['hmyyuyue']['appId'];
				$appSecret=$config['hmyyuyue']['appSecret'];
				$headrs = array('content-type: application/json;charset=UTF-8','appid:'.$appId,'appSecret:'.$appSecret);
				$url = 'https://shifu.api.kkgj123.cn/api/1/order/cancel';
				$data = [];
				$data['sysOrderNo'] = $order['sysOrderNo'];
				$data['cancelParty'] = 3;
				$data['cancelReason'] = '超时取消';
				$data = json_encode($data,JSON_UNESCAPED_UNICODE);
				$res = curl_post($url,$data,'',$headrs);
				$res = json_decode($res,true);
			}
		}
		//超时的团
		$time = time();
		$collagewhere = [];
		if(getcustom('yx_collage_jieti')){
            $collagewhere[] = ['collage_type','=',0];
        }
		$tlist = Db::name('collage_order_team')->where($collagewhere)->where("`status`=1 and createtime+teamhour*3600<{$time}")->select()->toArray();
		Db::name('collage_order_team')->where($collagewhere)->where("`status`=1 and createtime+teamhour*3600<{$time}")->update(['status'=>3]);//改成失败状态
		if($tlist){//退款
			foreach($tlist as $t){
				$sysset = Db::name('admin')->where('id',$t['aid'])->find();
				$orderlist = Db::name('collage_order')->where('status',1)->where('teamid',$t['id'])->where('buytype','<>',1)->select()->toArray();
				foreach($orderlist as $orderinfo){
					if($orderinfo['paytype']=='微信支付'){
						$rs = \app\commons\Wxpay::refund($orderinfo['aid'],$orderinfo['platform'],$orderinfo['ordernum'],$orderinfo['totalprice'],$orderinfo['totalprice'],'拼团失败');
					}else{
						\app\commons\Member::addmoney($orderinfo['aid'],$orderinfo['mid'],$orderinfo['totalprice'],'拼团失败退款');
					}
					//积分抵扣的返还
					if($orderinfo['scoredk'] > 0){
						\app\commons\Member::addscore($orderinfo['aid'],$orderinfo['mid'],$orderinfo['scoredk'],'拼团失败退款返还');
					}
					//扣除消费赠送积分
        			\app\commons\Member::decscorein($orderinfo['aid'],'collage',$orderinfo['id'],$orderinfo['ordernum'],'拼团失败退款扣除消费赠送');
					//优惠券抵扣的返还
					if($orderinfo['coupon_rid'] > 0){
						Db::name('coupon_record')->where('aid',aid)->where('mid',$orderinfo['mid'])->where('id',$orderinfo['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
					}
					Db::name('collage_order')->where('id',$orderinfo['id'])->update(['status'=>4]);
					//退款成功通知
					$tmplcontent = [];
					$tmplcontent['first'] = '拼团失败退款，¥'.$orderinfo['totalprice'].'已经退回您的付款账户，请留意查收。';
					$tmplcontent['remark'] = '请点击查看详情~';
					$tmplcontent['orderProductPrice'] = (string) $orderinfo['totalprice'];
					$tmplcontent['orderProductName'] = $orderinfo['title'];
					$tmplcontent['orderName'] = $orderinfo['ordernum'];
                    $tmplcontentNew = [];
                    $tmplcontentNew['character_string1'] = $orderinfo['ordernum'];//订单编号
                    $tmplcontentNew['thing2'] = $orderinfo['title'];//商品名称
                    $tmplcontentNew['amount3'] = $orderinfo['totalprice'];//退款金额
					\app\commons\Wechat::sendtmpl($orderinfo['aid'],$orderinfo['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('activity/collage/orderlist',$orderinfo['aid']),$tmplcontentNew);
					//订阅消息
					$tmplcontent = [];
					$tmplcontent['amount6'] = $orderinfo['totalprice'];
					$tmplcontent['thing3'] = $orderinfo['title'];
					$tmplcontent['character_string2'] = $orderinfo['ordernum'];
					
					$tmplcontentnew = [];
					$tmplcontentnew['amount3'] = $orderinfo['totalprice'];
					$tmplcontentnew['thing6'] = $orderinfo['title'];
					$tmplcontentnew['character_string4'] = $orderinfo['ordernum'];
					\app\commons\Wechat::sendwxtmpl($orderinfo['aid'],$orderinfo['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

					//短信通知
					$member = Db::name('member')->where('id',$orderinfo['mid'])->find();
					if($member['tel']){
						$tel = $member['tel'];
					}else{
						$tel = $orderinfo['tel'];
					}
					$rs = \app\commons\Sms::send($orderinfo['aid'],$tel,'tmpl_tuisuccess',['ordernum'=>$orderinfo['ordernum'],'money'=>$orderinfo['totalprice']]);
				}
			}
		}
		
        if(getcustom('yx_collage_jieti')){
            //拼团，根据设置的结束时间进行结束操作
            $time = time();
            $jt_collage_list_team = Db::name('collage_order_team')->where('collage_type',1)->where("`status`=1 and endtime<{$time}")->select()->toArray();
            foreach($jt_collage_list_team as $jtckey=>$jtcval){
                $jtc_count = Db::name('collage_order')->where('aid',$jtcval['aid'])->where('bid',$jtcval['bid'])->where('teamid',$jtcval['id'])->where('status',1)->count();
                //更改团的状态
                Db::name('collage_order_team')->where('aid',$jtcval['aid'])->where('bid',$jtcval['bid']) ->where('id',$jtcval['id'])->update(['status' => 2,'num' => $jtc_count]);
                //修改团对应订单的数量,根据总数量 对应设置中的商品数量
                $jtdata = Db::name('collage_product')->where('aid',$jtcval['aid'])->where('bid',$jtcval['bid'])->where('id',$jtcval['proid'])->value('jieti_data');
                $jtdata = json_decode($jtdata,true);
                $give_num =1;
                foreach($jtdata as $key=>$val){
                    if($jtc_count >= $val['teamnum'] && $val['goodsnum'] > $give_num ){
                        $give_num = $val['goodsnum'];
                    }
                }
                Db::name('collage_order')->where('aid',$jtcval['aid'])->where('bid',$jtcval['bid'])->where('teamid',$jtcval['id'])->where('status',1)->update(['num'=>$give_num,'status'=>3,'collect_time'=>time()]);
            }
        }

		//超时的幸运拼团
		//超时的团
		$time = time();
		$tlist = Db::name('lucky_collage_order_team')->where("`status`=1 and createtime+teamhour*3600<{$time}")->select()->toArray();
		Db::name('lucky_collage_order_team')->where("`status`=1 and createtime+teamhour*3600<{$time}")->update(['status'=>3]);//改成失败状态
		if($tlist){//退款
			foreach($tlist as $t){
				$sysset = Db::name('admin')->where('id',$t['aid'])->find();
				$orderlist = Db::name('lucky_collage_order')->where('status',1)->where('isjiqiren',0)->where('teamid',$t['id'])->where('buytype','<>',1)->select()->toArray();
				foreach($orderlist as $orderinfo){
					$product = Db::name('lucky_collage_product')->where(['id'=>$orderinfo['proid']])->find();                   
                    if($product['failtklx']=='1'){
                        if(getcustom('luckycollage_score_pay')){
                            if($orderinfo['is_score_pay'] == 1){
                                \app\commons\Member::addscore($orderinfo['aid'],$orderinfo['mid'],$orderinfo['totalscore'],'拼团失败订单返还');
                            }
                        }
                        \app\commons\Order::refund($orderinfo,$orderinfo['totalprice'],'拼团失败订单退款');
                    }else{
                        \app\commons\Member::addmoney($orderinfo['aid'],$orderinfo['mid'],$orderinfo['totalprice'],'拼团失败退款');
                    }
                    /*if($orderinfo['paytype']=='微信支付'){
                        $rs = \app\commons\Wxpay::refund($orderinfo['aid'],$orderinfo['platform'],$orderinfo['ordernum'],$orderinfo['totalprice'],$orderinfo['totalprice'],'拼团失败');
                    }else{
                        \app\commons\Member::addmoney($orderinfo['aid'],$orderinfo['mid'],$orderinfo['totalprice'],'拼团失败退款');
                    }*/
                    //积分抵扣的返还
                    if($orderinfo['scoredk'] > 0){
                        \app\commons\Member::addscore($orderinfo['aid'],$orderinfo['mid'],$orderinfo['scoredk'],'拼团失败退款返还');
                    }
                    //扣除消费赠送积分
                    \app\commons\Member::decscorein($orderinfo['aid'],'lucky_collage',$orderinfo['id'],$orderinfo['ordernum'],'拼团失败退款扣除消费赠送');
                    //优惠券抵扣的返还
                    if($orderinfo['coupon_rid'] > 0){
                        Db::name('coupon_record')->where('aid',aid)->where('mid',$orderinfo['mid'])->where('id',$orderinfo['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
                    }
                    Db::name('lucky_collage_order')->where('id',$orderinfo['id'])->update(['status'=>4]);
                    //退款成功通知
                    $tmplcontent = [];
                    if($product['failtklx']=='1'){
                        $tmplcontent['first'] = '拼团失败退款，¥'.$orderinfo['totalprice'].'已经退回您的付款账户，请留意查收。';
                    }else{
                        $tmplcontent['first'] = '拼团失败退款，¥'.$orderinfo['totalprice'].'已经退回您的余额账户，请留意查收。';
                    }
                    $tmplcontent['remark'] = '请点击查看详情~';
                    $tmplcontent['orderProductPrice'] = (string) $orderinfo['totalprice'];
                    $tmplcontent['orderProductName'] = $orderinfo['title'];
                    $tmplcontent['orderName'] = $orderinfo['ordernum'];
                    $tmplcontentNew = [];
                    $tmplcontentNew['character_string1'] = $orderinfo['ordernum'];//订单编号
                    $tmplcontentNew['thing2'] = $orderinfo['title'];//商品名称
                    $tmplcontentNew['amount3'] = $orderinfo['totalprice'];//退款金额
                    \app\commons\Wechat::sendtmpl($orderinfo['aid'],$orderinfo['mid'],'tmpl_tuisuccess',$tmplcontent,m_url('activity/luckycollage/orderlist',$orderinfo['aid']),$tmplcontentNew);
                    //订阅消息
                    $tmplcontent = [];
                    $tmplcontent['amount6'] = $orderinfo['totalprice'];
                    $tmplcontent['thing3'] = $orderinfo['title'];
                    $tmplcontent['character_string2'] = $orderinfo['ordernum'];
                    
                    $tmplcontentnew = [];
                    $tmplcontentnew['amount3'] = $orderinfo['totalprice'];
                    $tmplcontentnew['thing6'] = $orderinfo['title'];
                    $tmplcontentnew['character_string4'] = $orderinfo['ordernum'];
                    \app\commons\Wechat::sendwxtmpl($orderinfo['aid'],$orderinfo['mid'],'tmpl_tuisuccess',$tmplcontentnew,'pages/my/usercenter',$tmplcontent);

                    //短信通知
                    $member = Db::name('member')->where('id',$orderinfo['mid'])->find();
                    if($member['tel']){
                        $tel = $member['tel'];
                    }else{
                        $tel = $order['tel'];
                    }
                    $rs = \app\commons\Sms::send($orderinfo['aid'],$tel,'tmpl_tuisuccess',['ordernum'=>$orderinfo['ordernum'],'money'=>$orderinfo['totalprice']]);
				}
			}
		}
		
		if(getcustom('yueke')){
			$orderlist = Db::name('yueke_order')->where('status',0)->select()->toArray();
			$autocloseArr = [];
			foreach($orderlist as $order){
				if(!$autocloseArr[$order['aid']]){
					$autocloseArr[$order['aid']] = Db::name('yueke_set')->where('aid',$order['aid'])->value('autoclose');
				}
				if($order['createtime'] + $autocloseArr[$order['aid']]*60 > time()) continue;
				$aid = $order['aid'];
				$mid = $order['mid'];
				$orderid = intval($order['id']);
				$order = Db::name('yueke_order')->where('id',$orderid)->find();
				if(!$order || $order['status']!=0){
					//return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
				}else{
					//优惠券抵扣的返还
					if($order['coupon_rid'] > 0){
						Db::name('coupon_record')->where('aid',$aid)->where('mid',$mid)->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
					}
					$rs = Db::name('yueke_order')->where('id',$orderid)->where('aid',$aid)->where('mid',$mid)->update(['status'=>4]);
				}
			}
		}
		if(getcustom('huodong_baoming')){
			$orderlist = Db::name('huodong_baoming_order')->where('status',0)->select()->toArray();
			$autocloseArr = [];
			foreach($orderlist as $order){
				if(!$autocloseArr[$order['aid']]){
					$autocloseArr[$order['aid']] = Db::name('huodong_baoming_set')->where('aid',$order['aid'])->value('autoclose');
				}
				if($order['createtime'] + $autocloseArr[$order['aid']]*60 > time()) continue;
				$aid = $order['aid'];
				$mid = $order['mid'];
				$orderid = intval($order['id']);
				$order = Db::name('huodong_baoming_order')->where('id',$orderid)->find();
				if(!$order || $order['status']!=0){
					//return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
				}else{
					//优惠券抵扣的返还
					if($order['coupon_rid'] > 0){
						Db::name('coupon_record')->where('aid',$aid)->where('mid',$mid)->where('id',$order['coupon_rid'])->update(['status'=>0,'usetime'=>'']);
					}
					$rs = Db::name('huodong_baoming_order')->where('id',$orderid)->where('aid',$aid)->where('mid',$mid)->update(['status'=>4]);
				}
			}
		}

        if(getcustom('express_wx')){
            //判断是否自动派单
            $peisong_set = \db('peisong_set')->where('express_wx_status',1)->where('express_wx_paidan',1)->select()->toArray();
            if($peisong_set){
                foreach ($peisong_set as $set){
                    $orderlist = Db::name('shop_order')->where('aid',$set['aid'])->where('status',1)->where('freight_type',2)->whereNull('express_type')->limit(50)->select()->toArray();
                    foreach ($orderlist as $item){
                        Db::name('shop_order')->where('id',$item['id'])->update(['express_type'=>'express_wx']);
                        \app\customs\ExpressWx::addOrder('shop_order',$item);
                    }
                }
            }
        }

		if(getcustom('plug_mantouxia')){
			Db::name('form_order')->where('money','>',0)->where('paystatus',0)->where('createtime','<',time() - 3600)->delete();
			Db::name('payorder')->where('type','form')->where('status',0)->where('createtime','<',time() - 3600)->delete();
		}

		$wxpaylog = Db::name('wxpay_log')->whereRaw('fenzhangmoney>0 or fenzhangmoney2>0')->where('isfenzhang',0)->where('createtime','<',time()-60)->select()->toArray();
		if($wxpaylog){
			$dbwxpayset = Db::name('sysset')->where('name','wxpayset')->value('value');
			$dbwxpayset = json_decode($dbwxpayset,true);

			foreach($wxpaylog as $v){
				$amount = intval($v['fenzhangmoney']*100);
				$amount2 = intval($v['fenzhangmoney2']*100);
				$sub_appid = '';
				$sub_mchid = $v['sub_mchid'];
				$appinfo = \app\commons\System::appinfo($v['aid'],$v['platform']);
				if(!$sub_mchid) $sub_mchid = $appinfo['wxpay_sub_mchid'];
				if($v['bid'] > 0){
					$bset = Db::name('business_sysset')->where('aid',$v['aid'])->find();
					if($bset['wxfw_status'] == 1){
						$dbwxpayset = [
							'mchname'=>$bset['wxfw_mchname'],
							'appid'=>$bset['wxfw_appid'],
							'mchid'=>$bset['wxfw_mchid'],
							'mchkey'=>$bset['wxfw_mchkey'],
							'apiclient_cert'=>$bset['wxfw_apiclient_cert'],
							'apiclient_key'=>$bset['wxfw_apiclient_key'],
						];
						$receivers = [];
						$addreceivers = [];
						if(getcustom('business_more_account')){
							$wxpays =  json_decode($v['wxpay_submchid_text'],true);	
							foreach($wxpays as $sub){
								if($sub['amount']>0){
									$receivers[] = ['type'=>'MERCHANT_ID','account'=>$sub['submchid'],'amount'=>$sub['amount'],'description'=>$sub_mchid.'分账'];
									$addreceivers[] = ['type'=>'MERCHANT_ID','name'=>$sub['subname'],'account'=>$sub['submchid'],'relation_type'=>'SERVICE_PROVIDER'];
								}
							}
						}
						$receivers[] = ['type'=>'MERCHANT_ID','account'=>$dbwxpayset['mchid'],'amount'=>$amount,'description'=>$sub_mchid.'分账'];
						$addreceivers[] = ['type'=>'MERCHANT_ID','name'=>$dbwxpayset['mchname'],'account'=>$dbwxpayset['mchid'],'relation_type'=>'SERVICE_PROVIDER'];
						
					}elseif($bset['wxfw_status'] == 2){
						$receivers = [];
						$addreceivers = [];
						if($amount > 0){
							$receivers[] = ['type'=>'MERCHANT_ID','account'=>$dbwxpayset['mchid'],'amount'=>$amount,'description'=>$sub_mchid.'分账'];
							$addreceivers[] = ['type'=>'MERCHANT_ID','name'=>$dbwxpayset['mchname'],'account'=>$dbwxpayset['mchid'],'relation_type'=>'SERVICE_PROVIDER'];
						}
						if($amount2 > 0){
							$receivers[] = ['type'=>'MERCHANT_ID','account'=>$bset['wxfw2_mchid'],'amount'=>$amount2,'description'=>$sub_mchid.'分账'];
							$addreceivers[] = ['type'=>'MERCHANT_ID','name'=>$bset['wxfw2_mchname'],'account'=>$bset['wxfw2_mchid'],'relation_type'=>'PARTNER'];
						}
					}
				}
                else
                {
					$admin = Db::name('admin')->where('id',$v['aid'])->find();
					if($admin['choucheng_receivertype'] == 0){
						$receivers = [['type'=>'MERCHANT_ID','account'=>$dbwxpayset['mchid'],'amount'=>$amount,'description'=>$sub_mchid.'分账']];
						$addreceivers = [['type'=>'MERCHANT_ID','name'=>$dbwxpayset['mchname'],'account'=>$dbwxpayset['mchid'],'relation_type'=>'SERVICE_PROVIDER']];
					}elseif($admin['choucheng_receivertype'] == 1){
						$receivers = [['type'=>'MERCHANT_ID','account'=>$admin['choucheng_receivertype1_account'],'amount'=>$amount,'description'=>$sub_mchid.'分账']];
						$addreceivers = [['type'=>'MERCHANT_ID','name'=>$admin['choucheng_receivertype1_name'],'account'=>$admin['choucheng_receivertype1_account'],'relation_type'=>'PARTNER']];
					}elseif($admin['choucheng_receivertype'] == 2){
						if($admin['choucheng_receivertype2_openidtype'] == 0){
							if($admin['choucheng_receivertype2_name']){
								$receivers = [['type'=>'PERSONAL_OPENID','name'=>$admin['choucheng_receivertype2_name'],'account'=>$admin['choucheng_receivertype2_account'],'amount'=>$amount,'description'=>$sub_mchid.'分账']];
								$addreceivers = [['type'=>'PERSONAL_OPENID','name'=>$admin['choucheng_receivertype2_name'],'account'=>$admin['choucheng_receivertype2_account'],'relation_type'=>'PARTNER']];
							}else{
								$receivers = [['type'=>'PERSONAL_OPENID','account'=>$admin['choucheng_receivertype2_account'],'amount'=>$amount,'description'=>$sub_mchid.'分账']];
								$addreceivers = [['type'=>'PERSONAL_OPENID','account'=>$admin['choucheng_receivertype2_account'],'relation_type'=>'PARTNER']];
							}
						}else{
							$sub_appid = $appinfo['appid'];
							if($v['platform'] == 'wx'){
								$account = $admin['choucheng_receivertype2_accountwx'];
							}else{
								$account = $admin['choucheng_receivertype2_account'];
							}
							if($admin['choucheng_receivertype2_name']){
								$receivers = [['type'=>'PERSONAL_SUB_OPENID','name'=>$admin['choucheng_receivertype2_name'],'account'=>$account,'amount'=>$amount,'description'=>$sub_mchid.'分账']];
								$addreceivers = [['type'=>'PERSONAL_SUB_OPENID','name'=>$admin['choucheng_receivertype2_name'],'account'=>$account,'relation_type'=>'PARTNER']];
							}else{
								$receivers = [['type'=>'PERSONAL_SUB_OPENID','account'=>$account,'amount'=>$amount,'description'=>$sub_mchid.'分账']];
								$addreceivers = [['type'=>'PERSONAL_SUB_OPENID','account'=>$account,'relation_type'=>'PARTNER']];
							}
						}
					}
				}
                $multi=false;
                if(getcustom('yx_queue_free_fenzhang_wxpay')) {
                    $set = Db::name('queue_free_set')->where('aid',$v['aid'])->where('bid',0)->find();
                    if($set['receive_account'] == 'fenzhang_wxpay'){
                        $multi = true;
                    }
                }
				$rs = $this->profitsharing($v,$receivers,$addreceivers,$sub_mchid,$dbwxpayset,$v['transaction_id'],$sub_appid,0,$multi);
				if($rs['status'] == 0){
					\think\facade\Log::write($rs);
					Db::name('wxpay_log')->where('id',$v['id'])->update(['isfenzhang'=>2,'fz_errmsg'=>$rs['msg']]);
				}else{
					Db::name('wxpay_log')->where('id',$v['id'])->update(['isfenzhang'=>1,'fz_errmsg'=>$rs['msg'],'fz_ordernum'=>$rs['ordernum']]);
				}
			}
		}

        if(getcustom('member_gongxian')){
            //贡献值过期
            $adminlist = Db::name('admin')->where('member_gongxian_status',1)->select()->toArray();
            foreach($adminlist as $admin) {
                $sysset = Db::name('admin_set')->where('aid',$admin['id'])->find();
                //每笔记录超过过期时间贡献值即过期
                $level_with_expire = Db::name('member_level')->where('aid',$admin['id'])->where('gongxian_days','>',0)->column('id,gongxian_days','id');
                $loglist = [];
                if($level_with_expire){
                    $levelids_with_expire = array_keys($level_with_expire);
                    foreach ($level_with_expire as $levelids_item){
                        $log1 = Db::name('member_gongxianlog')->alias('ml')->leftJoin('member m', 'm.id = ml.mid')
                            ->where('ml.aid',$admin['id'])->where('ml.value','>',0)->where('ml.is_expire',0)->where('m.levelid','=',$levelids_item['id'])->where("ml.createtime + '".($levelids_item['gongxian_days']*86400)."' < ".$time)
                            ->field('ml.*,m.levelid')->select()->toArray();
                        if($log1)
                            $loglist = array_merge((array)$log1, (array)$loglist);
                    }
                    $log2 = Db::name('member_gongxianlog')->alias('ml')->leftJoin('member m', 'm.id = ml.mid')
                        ->where('ml.aid',$admin['id'])->where('ml.value','>',0)->where('ml.is_expire',0)->where('m.levelid','not in',$levelids_with_expire)->where("ml.createtime + '".($sysset['gongxian_days']*86400)."' < ".$time)
                        ->field('ml.*,m.levelid')->select()->toArray();
                    if($log2)
                        $loglist = array_merge((array)$log2, (array)$loglist);
                }else{
                    $loglist = Db::name('member_gongxianlog')->alias('ml')->leftJoin('member m', 'm.id = ml.mid')
                        ->where('ml.aid',$admin['id'])->where('ml.value','>',0)->where('ml.is_expire',0)->where("ml.createtime + '".($sysset['gongxian_days']*86400)."' < ".$time)
                        ->field('ml.*,m.levelid')->select()->toArray();
                }
                if($loglist){
                    foreach ($loglist as $logitem){
                        Db::name('member_gongxianlog')->where('id',$logitem['id'])->update(['is_expire' => 1, 'expire_time' => $time]);
                        \app\commons\Member::addgongxian($logitem['aid'],$logitem['mid'],$logitem['value']*-1,'过期',$logitem['channel'],$logitem['orderid']);
                    }
                }
            }
        }

		if(getcustom('member_levelup_auth')){
			$orderlist = Db::name('member_salelevel_order')->where('status',0)->select()->toArray();
			foreach($orderlist as $order){
				if($order['createtime'] + 30*60 > time()) continue;
				$aid = $order['aid'];
				$mid = $order['mid'];
				$orderid = intval($order['id']);
				$order = Db::name('member_salelevel_order')->where('id',$orderid)->find();
				if(!$order || $order['status']!=0){
					//return $this->json(['status'=>0,'msg'=>'关闭失败,订单状态错误']);
				}else{
					$rs = Db::name('member_salelevel_order')->where('id',$orderid)->where('aid',$aid)->where('mid',$mid)->update(['status'=>-1]);
				}
			}
		}


		$this->fenhong('perminute');

		if(getcustom('restaurant')){
			\app\customs\Restaurant::auto_perminute();
		}

        if(getcustom('everyday_hongbao')) {
            if(date('G') == 8) {
                $this->hbcalculate();
            }
        }

        if(getcustom('image_search')){
            $this->product_img_baidu_sync();
        }
        if(getcustom('choujiang_time')){
            $this->run_dscj();
        }
        if(getcustom('invite_free')){
        	//结束已完成的免单未发放的订单
        	\app\customs\InviteFree::deal_finishfree();
		}
        //计次优惠券过期结算
        if(getcustom('coupon_times_expire')){
            \app\models\Counpon::couponExpire();
        }
//            $this->huidong();

		if(getcustom('extend_tour')){
			//导游相册订单
			$admin = Db::name('admin')
	            ->where('status',1)
	            ->field('id')
	            ->select()
	            ->toArray();
	        if($admin){
	            foreach($admin as $v){
					$tour_custom  = new \app\customs\TourCustom();
		        	$deal_tpl = $tour_custom->get_updated_order($v['id']);
		        }
		        unset($v);
	        }
		}

		if(getcustom('extend_yuyue_car')){
			//预约洗车订单
//	        \app\customs\YuyueCustom::deal_order();
            \app\customs\YuyueCustom::dispatch_order();
		}

        if(getcustom('score_to_money_auto')){
            //积分每日自动转入余额,每天0点1分执行
            if(date('G')=='0'){
                $this->scoreToMoney();
            }
        }

		$this->sxpayquery();

		$this->fifaauto();
		if(getcustom('lot_cerberuse')){
            $this->cerberuseExpire();
        }

        //再次同步小程序发货
        \app\commons\Order::retryUploadShipping();

		if(getcustom('commission_to_score')){
		    //佣金自动转积分，每日执行一次
		    if(date('H:i')=='00:01'){
		        $this->commission_to_score();
            }
        }
        if(date('H:i')=='00:01'){
            if(getcustom('yx_day_give')){
                $this->day_give();
            }
        }

        if(getcustom('product_handwork')){
        	//手工活
            \app\customs\HandWork::sendmoney();
        }

        //统计计算累计积分
        $this->count_totalscore();

        if(getcustom('yx_queue_free_fenzhang_wxpay')){
            $log = Db::name('queue_free_log')->where('receive_account','fenzhang_wxpay')->where('isfenzhang',0)->where('createtime','<',time()-60)->select()->toArray();
            if($log){
                $i = 1;
                foreach ($log as $v){
                    $order = json_decode($v['payorderjson'],true);
                    $money = round($v['money_give']*(100-$v['fenzhang_wxpay_rate'])/100,2);
                    if($order && $money > 0){
                        $rs = \app\customs\QueueFree::wxFenzhang($v['aid'],$v['bid'],$order['mid'],$order,$money,$v['mid'],$v['id'],$order['orderType']);
                        if($rs['status'] != 1)
                            \app\commons\Member::addmoney($v['aid'],$v['mid'],$v['money_give'],t('排队奖励返现'));
                        $remainder = $i % 30;
                        if($remainder == 0){
                            sleep(1);
                        }
                        $i++;
                    }
                }
            }
            //10分钟 结束分账
            $fzlog = Db::name('wxpay_fzlog')->distinct(true)->field('transaction_id')->where('isfinish',0)->where('isfenzhang','=',1)
                ->where('createtime','<',time()-600)->where('finish_error_times','<',3)->select()->toArray();
            if($fzlog){
                $i = 1;
                foreach ($fzlog as $v){
                    $info = Db::name('wxpay_fzlog')->where('transaction_id',$v['transaction_id'])->find();
                    \app\customs\QueueFree::profitsharingfinish($info['aid'],$info['transaction_id'],$info);
                    $remainder = $i % 60;
                    if($remainder == 0){
                        sleep(1);
                    }
                    $i++;
                }
            }
        }

        if(getcustom('douyin_groupbuy')){
        	//抖音券关闭
            \app\customs\DouyinGroupbuyCustom::autoclose();
        }
        if(getcustom('ganer_fenxiao')){
            $this->ganer_prize_pool();
        }

        //ERP旺店物流：每5分钟调用查询物流同步接口获取待物流同步数据，每次取数据100条，获取后将数据同步至商城平台，处理完成后调用物流同步回写接口将处理状态（成功 or 失败）回写旺店通ERP
        if(getcustom('erp_wangdiantong')){
            $checkTime = cache('wdt_logistics_check_time');
            if(!$checkTime || $checkTime<time()-5*60){
                $adminlist = Db::name('admin')->where('wdt_status',1)->field('id,wdt_status')->select()->toArray();
                foreach ($adminlist as $k=>$v){
                    $c = new \app\customs\Wdt($v['id'],0);
                    $c->logisticsQuery();
                }
                cache('wdt_logistics_check_time',time());
            }
        }
        if(getcustom('consumer_value_add')){
            //自动释放平台绿色积分
            $this->release_green_score();
        }
        if(getcustom('product_pickup_device')){
            $this->pickupDeviceAddstockRemind();
        }

        //积分转赠 未支付自动取消
        if(getcustom('score_transfer_sxf')){
            $this->closeScoreTransferSxfOrder();
        }

        if(getcustom('yuyue_before_starting')){
            //预约开始前通知
            self::sendnotice_time();
        }
        if(getcustom('yuyue_datetype1_autoendorder')){
            //时间段自动完成
            self::datetype1_autoendorder();
        }
		//酒店未支付自动取消
		if(getcustom('hotel')){
			$this->closeHotelorder();
		}

        //鱼塘 到达时间
        if(getcustom('extend_fish_pond')){
            $this->fishpond();
        }

        if(getcustom('supply_zhenxin')){
        	//甄新汇选计划任务
            \app\customs\SupplyZhenxinCustom::autotask();
        }
	}
	
	private function sxpayquery(){
		$payorderList = Db::name('payorder')->where('issxpay',1)->where('status',0)->where('createtime','>',time()-10*60)->where('createtime','<',time()-2*60)->select()->toArray();
		foreach($payorderList as $payorder){
			$aid = $payorder['aid'];
			$mid = $payorder['mid'];
			$ordernum = $payorder['ordernum'];
			$rs = \app\customs\Sxpay::tradeQuery($payorder);
//			\think\facade\Log::write([
//                'file'=>__FILE__.__FUNCTION__,
//                $rs
//            ]);
			if($rs['status'] == 1 && ($rs['data']['tranSts'] == 'CLOSED' || $rs['data']['tranSts'] == 'FAIL' || $rs['data']['tranSts'] == 'CANCELED')){
				Db::name('payorder')->where('id',$payorder['id'])->update(['issxpay'=>0]);
			}
			if($rs['status'] == 1 && $rs['data']['tranSts'] == 'SUCCESS'){
				$attach = explode(':',$rs['data']['extend']);
				$aid = intval($attach[0]);
				$tablename = $attach[1];
				$platform = $attach[2];
				if($platform == 'sxpaymp') $platform = 'mp';
				if($platform == 'sxpaywx') $platform = 'wx';
				if($platform == 'sxpayalipay') $platform = 'alipay';
				$transaction_id = $rs['data']['transactionId'];
				$isbusinesspay = 0;
				if($attach[4]){
					$isbusinesspay = 1;
				}
				Db::name('payorder')->where('id',$payorder['id'])->update(['platform'=>$platform,'isbusinesspay'=>$isbusinesspay]);

//                if($payorder['money'] != $total_fee*0.01){
                    //金额不一致 退款
//                    continue;
//                    return ['status'=>2,'msg'=>'支付金额和订单金额不一致','payorder'=>$payorder];
//                }
//                if($payorder['status'] == 2){
                    //支付单取消
//                    continue;
//                    return ['status'=>2,'msg'=>'订单已修改，请重新发起支付','payorder'=>$payorder];
//                }

				if($payorder['score'] > 0){
					$rs = \app\commons\Member::addscore($aid,$mid,-$payorder['score'],'支付订单，订单号：'.$ordernum);
					if($rs['status'] == 0){
						$order = $payorder;
						$order['totalprice'] = $order['money'];
						$order['paytypeid'] = 2;
						\app\commons\Order::refund($order,$order['money'],'积分扣除失败退款');
						continue;
					}
				}
				$rs = \app\models\Payorder::payorder($payorder['id'],'微信支付',2,$transaction_id);
				if($rs['status']==0) continue;

				$total_fee = intval($payorder['money']*100);

				$set = Db::name('admin_set')->where('aid',$aid)->find();
				//消费送积分
				if($tablename != 'recharge' && $set['scorein_money']>0 && $set['scorein_score']>0){
					$givescore = floor($total_fee*0.01 / $set['scorein_money']) * $set['scorein_score'];
					$res = \app\commons\Member::addscore($aid,$mid,$givescore,'消费送'.t('积分'));
					if($res && $res['status'] == 1){
						//记录消费赠送积分记录
						\app\commons\Member::scoreinlog($aid,0,$mid,$payorder['type'],$payorder['orderid'],$payorder['ordernum'],$givescore,$total_fee);
					}
				}
				if(getcustom('business_moneypay')){ //多商户设置的消费送积分
					if($payorder['bid'] > 0 && $tablename != 'shop'){
						$bset = Db::name('business_sysset')->where('aid',$aid)->find();
						$givescore = floor($total_fee*0.01 / $bset['scorein_money']) * $bset['scorein_score'];
						$res = \app\commons\Member::addscore($aid,$mid,$givescore,'消费送'.t('积分'));
						if($res && $res['status'] == 1){
							//记录消费赠送积分记录
							\app\commons\Member::scoreinlog($aid,$payorder['bid'],$mid,$payorder['type'],$payorder['orderid'],$payorder['ordernum'],$givescore,$total_fee);
						}
					}
				}
				//充值送积分
				if($tablename == 'recharge' && $set['scorecz_money']>0 && $set['scorecz_score']>0){
					$givescore = floor($total_fee*0.01 / $set['scorecz_money']) * $set['scorecz_score'];
					\app\commons\Member::addscore($aid,$mid,$givescore,'充值送'.t('积分'));
				}

				if($rs['status'] == 1){
					//记录
					$data = array();
					$data['aid'] = $aid;
					$data['mid'] = $payorder['mid'];
					$data['openid'] = $rs['data']['uuid'];
					$data['tablename'] = $tablename;
					$data['givescore'] = $givescore;
					$data['ordernum'] = $rs['data']['ordNo'];
					$data['mch_id'] = $rs['data']['mno'];
					$data['transaction_id'] = $rs['data']['transactionId'];
					$data['total_fee'] = $rs['data']['oriTranAmt'];
					$data['createtime'] = time();
					Db::name('wxpay_log')->insert($data);
					\app\commons\Member::uplv($aid,$mid);
				}
			}
		}
	}
	private function hbcalculate()
    {
        if(getcustom('everyday_hongbao')) {
            $date = date('Y-m-d');
            $todayStart = strtotime($date);
            $yestdayStart = $todayStart - 86400;
            $yestdayEnd = $yestdayStart + 86399;

            $yestdayDate = date('Y-m-d',$yestdayStart);

            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $sysset) {
                $aid = $sysset['aid'];
                $hd = Db::name('hongbao_everyday')->where('aid',$aid)->find();
                if($hd['status']!=1 || $hd['num'] < 1) continue;
                if($hd['starttime'] > time() && $hd['endtime'] < time()) continue;
                $haveHongbao = Db::name('hongbao_everyday_list')->where('aid',$aid)->where('createdate','=',$date)->count();
                if($haveHongbao > 0) continue;
                //前一天业绩
                if($hd['shop_order_money_type'] == 'pay') {
                    $where[] = ['status', 'in', [1,2,3]];
                    $where[] = ['paytime', 'between', [$yestdayStart,$yestdayEnd]];
                }else if($hd['shop_order_money_type'] == 'receive') {
                    $where[] = ['status', '=', 3];
                    $where[] = ['collect_time', 'between', [$yestdayStart,$yestdayEnd]];
                } else {
                    $where[] = ['status', 'in', [1,2,3]];
                    $where[] = ['paytime', 'between', [$yestdayStart,$yestdayEnd]];
                }
                $totalOrder = Db::name('shop_order')->where('aid',$aid)->where('bid', 0)->where($where)->sum('totalprice');
                $totalOrder = round($totalOrder * $hd['hongbao_bl'] / 100,2);
                $orderBusiness = Db::name('shop_order')->where('aid',$aid)->where('bid', '>',0)->where($where)->field('id,aid,bid,totalprice')->select()->toArray();
                $business = Db::name('business')->where('aid',$aid)->column('feepercent','id');
                $totalOrderBusiness = 0;
                foreach ($orderBusiness as $item) {
                    $totalOrderBusiness += $item['totalprice'] * $business[$item['bid']] / 100;
                }
                $totalOrderBusiness = round($totalOrderBusiness * $hd['hongbao_bl_business'] / 100,2);
                //买单业绩
                $maidanOrder = Db::name('maidan_order')->where('aid',$aid)->where('createtime','between',[$yestdayStart,$yestdayEnd])->where('status',1)->select()->toArray();
                $totalMaidan = 0;
                foreach ($maidanOrder as $item) {
                    $totalMaidan += $item['paymoney'] * $business[$item['bid']] / 100;
                }
                $totalMaidan = round($totalMaidan * $hd['hongbao_bl_maidan'] / 100,2);

                $yestdayLeft = Db::name('hongbao_everyday_list')->where('aid',$aid)->where('createdate','=',$yestdayDate)->sum('left');
                $total = $totalOrder + $totalOrderBusiness + $totalMaidan + $yestdayLeft;
                //生成随机红包(改为平均)
                $dataHongbao = [];
                $time = time();
//            $total = $total * 100;
                $minMoney = 1;
                $avgMoney = $total/$hd['num'];
                $avgMoney = substr(sprintf("%.3f", $avgMoney), 0, -1);
//            dd($total);
                if($avgMoney < 0.01) continue;
                for($i=0;$i<$hd['num'];$i++) {
                    if($i == $hd['num'] - 1)
                        $money = $total;
                    else
//                    $money = rand($minMoney,($total - ($hd['num']-$i) * $minMoney));
                        $money = $avgMoney;
                    $dataHongbao[] = [
                        'aid' => $aid,
                        'createdate' => $date,
                        'createtime' => $time,
                        'money' => $money,
                        'left' => $money,
                    ];
                    $total -= $money;
                    if($total <= 0) {
                        break;
                    }
                }
                Db::name('hongbao_everyday_list')->limit(100)
                    ->insertAll($dataHongbao);
            }
        }
    }
	//每小时执行一次
	private function perhour(){
		$time = time();
		//商城自动收货
		$shopsetlist = Db::name('shop_sysset')->select()->toArray();
		foreach($shopsetlist as $sysset){
			$aid = $sysset['aid'];
			if($aid){
				if(getcustom('plug_yang',$aid)){
					$list = Db::name('shop_order')->where("aid={$aid} and bid=0 and status=2 and ".time().">`send_time` + 86400*".$sysset['autoshdays'])->select()->toArray();
				}else{
                    $owhere = [];
                    $owhere[] = ['aid','=',$aid];
                    if(getcustom('product_weight',$aid)){
                        //信用额度付款的，不自动发货
                        $owhere[] = ['paytypeid','<>',38];
                    }
					$list = Db::name('shop_order')->where($owhere)->where("status=2 and ".time().">`send_time` + 86400*".$sysset['autoshdays'])->select()->toArray();
				}
				foreach($list as $order){
                    $refundOrder = Db::name('shop_refund_order')->where('refund_status','in',[1,4])->where('aid',$aid)->where('orderid',$order['id'])->count();
                    if($refundOrder){
                        continue;
                    }
                    //部分发货 不进行收货
                    if($order['express_isbufen'] ==1){
                        continue;
                    }
					$rs = \app\commons\Order::collect($order,'shop');
					if($rs['status'] == 0) continue;
					Db::name('shop_order')->where('id',$order['id'])->update(['status'=>3,'collect_time'=>time()]);
					Db::name('shop_order_goods')->where('orderid',$order['id'])->update(['status'=>3,'endtime'=>time()]);
					if(getcustom('ciruikang_fenxiao',$aid)){
                        //一次购买升级
                        \app\commons\Member::uplv($aid,$order['mid'],'shop',['onebuy'=>1,'onebuy_orderid'=>$order['id']]);
                    }else{
                        \app\commons\Member::uplv($aid,$order['mid']);
                    }

                    if(getcustom('member_shougou_parentreward',$aid)){
                        //首购解锁
                        Db::name('member_commission_record')->where('orderid',$order['id'])->where('type','shop')->where('status',0)->where('islock',1)->where('aid',$order['aid'])->where('remark','like','%首购奖励')->update(['islock'=>0]);
                    }
				}

				if(getcustom('plug_yang',$aid)){
					$list = Db::name('shop_order')->where("aid={$aid} and bid>0 and status=2 and ".time().">`send_time` + 3600*(select autocollecthour from ddwx_business where id=ddwx_shop_order.bid)")->select();
					foreach($list as $order){
						$refundOrder = Db::name('shop_refund_order')->where('refund_status','in',[1,4])->where('aid',$aid)->where('orderid',$order['id'])->count();
						if($refundOrder){
							continue;
						}
						$rs = \app\commons\Order::collect($order,'shop');
						if($rs['status'] == 0) continue;
						Db::name('shop_order')->where('id',$order['id'])->update(['status'=>3,'collect_time'=>time()]);
						Db::name('shop_order_goods')->where('orderid',$order['id'])->update(['status'=>3,'endtime'=>time()]);
						\app\commons\Member::uplv($aid,$order['mid']);
					}
				}
			}
		}
		//秒杀自动收货
        $seckillsetlist = Db::name('seckill_sysset')->select()->toArray();
        foreach($seckillsetlist as $sysset){
            $aid = $sysset['aid'];
            if($aid){
                $list = Db::name('seckill_order')->where("aid={$aid} and status=2 and ".time().">`send_time` + 86400*".$sysset['autoshdays'])->select()->toArray();
                foreach($list as $order){
                    $rs = \app\commons\Order::collect($order,'seckill');
                    if($rs['status'] == 0) continue;
                    Db::name('seckill_order')->where('id',$order['id'])->update(['status'=>3,'collect_time'=>time()]);
                }
            }
        }
		//拼团自动收货
		$collagesetlist = Db::name('collage_sysset')->select()->toArray();
		foreach($collagesetlist as $sysset){
			$aid = $sysset['aid'];
			if($aid){
				$list = Db::name('collage_order')->where("aid={$aid} and status=2 and ".time().">`send_time` + 86400*".$sysset['autoshdays'])->select()->toArray();
				foreach($list as $order){
					$rs = \app\commons\Order::collect($order,'collage');
					if($rs['status'] == 0) continue;
					Db::name('collage_order')->where('id',$order['id'])->update(['status'=>3,'collect_time'=>time()]);
				}
			}
		}
		//团购自动收货
        $tuangousetlist = Db::name('tuangou_sysset')->select()->toArray();
        foreach($tuangousetlist as $sysset){
            $aid = $sysset['aid'];
            if($aid){
                $list = Db::name('tuangou_order')->where("aid={$aid} and status=2 and ".time().">`send_time` + 86400*".$sysset['autoshdays'])->select()->toArray();
                foreach($list as $order){
                    $rs = \app\commons\Order::collect($order,'tuangou');
                    if($rs['status'] == 0) continue;
                    Db::name('tuangou_order')->where('id',$order['id'])->update(['status'=>3,'collect_time'=>time()]);
                }
            }
        }
		//幸运拼团自动收货
		$collagesetlist = Db::name('lucky_collage_sysset')->select()->toArray();
		foreach($collagesetlist as $sysset){
			$aid = $sysset['aid'];
			if($aid){
				$list = Db::name('lucky_collage_order')->where("aid={$aid} and status=2 and ".time().">`send_time` + 86400*".$sysset['autoshdays'])->select()->toArray();
				foreach($list as $order){
					$rs = \app\commons\Order::collect($order,'lucky_collage');
					if($rs['status'] == 0) continue;
					Db::name('lucky_collage_order')->where('id',$order['id'])->update(['status'=>3,'collect_time'=>time()]);
				}
			}
		}

		//砍价自动收货
		$kanjiasetlist = Db::name('kanjia_sysset')->select()->toArray();
		foreach($kanjiasetlist as $sysset){
			$aid = $sysset['aid'];
			if($aid){
				$list = Db::name('kanjia_order')->where("aid={$aid} and status=2 and ".time().">`send_time` + 86400*".$sysset['autoshdays'])->select()->toArray();
				foreach($list as $order){
					$orderid = $order['id'];
					$mid = $order['mid'];
					$rs = \app\commons\Order::collect($order,'kanjia');
					if($rs['status'] == 0) continue;
					Db::name('kanjia_order')->where('aid',$aid)->where('mid',$mid)->where('id',$orderid)->update(['status'=>3,'collect_time'=>time()]);
				}
			}
		}
		//积分商城自动收货
		$scoreshopsetlist = Db::name('scoreshop_sysset')->select()->toArray();
		foreach($scoreshopsetlist as $sysset){
			$aid = $sysset['aid'];
			if($aid){
				$list = Db::name('scoreshop_order')->where("aid={$aid} and status=2 and ".time().">`send_time` + 86400*".$sysset['autoshdays'])->select()->toArray();
				foreach($list as $order){
                    $rs = \app\commons\Order::collect($order,'scoreshop');
                    if($rs['status'] == 0) continue;
					Db::name('scoreshop_order')->where('id',$order['id'])->update(['status'=>3,'collect_time'=>time()]);
                    Db::name('scoreshop_order_goods')->where('orderid',$order['id'])->update(['status'=>3,'endtime'=>time()]);
				}
			}
		}

        foreach($scoreshopsetlist as $sysset){
            $aid = $sysset['aid'];
            if($aid){
                //积分分销补发
                $commission_record_list = Db::name('member_commission_record')->alias('r')
                    ->leftJoin('scoreshop_order o', "r.orderid = o.id and r.type = 'scoreshop'")
                    ->leftJoin('scoreshop_order_goods og', "r.ogid = og.id")
                    ->where('r.aid',$aid)->where('r.status',0)->where('o.status',3)
                    ->where('og.iscommission',0)
                    ->field('r.*,o.ordernum,o.title,og.iscommission')
                    ->select()->toArray();
                if($commission_record_list){
                    //佣金
                    foreach($commission_record_list as $commission_record){
                        Db::name('member_commission_record')->where('id',$commission_record['id'])->update(['status'=>1,'endtime'=>time()]);
                        Db::name('scoreshop_order_goods')->where('id',$commission_record['ogid'])->update(['iscommission'=>1]);
                        if($commission_record['commission'] > 0){
                            \app\commons\Member::addcommission($aid,$commission_record['mid'],$commission_record['frommid'],$commission_record['commission'],$commission_record['remark']);
                        }
                        if($commission_record['score'] > 0){
                            \app\commons\Member::addscore($aid,$commission_record['mid'],$commission_record['score'],$commission_record['remark']);
                        }
                    }
                }
            }
        }

		//等级到期自动降级
        $this->autoDownLevel();

		//延时结算分销佣金
		$syssetlist = Db::name('admin_set')->where('fxjiesuantime_delaydays','<>',0)->select()->toArray();
		foreach($syssetlist as $sysset){
			\app\commons\Order::jiesuanCommission($sysset['aid'],$sysset);
		}
        //未延时未结算的补充结算
        $syssetlist = Db::name('admin_set')->where('fxjiesuantime_delaydays','=',0)->select()->toArray();
        foreach($syssetlist as $sysset){
            \app\commons\Order::jiesuanCommission($sysset['aid'],$sysset);
        }

		if(getcustom('xixie')){
            \app\customs\Xixie::auto_endorder();
        }
        
		$this->fenhong('perhour');

        if(getcustom('restaurant')){
            \app\customs\Restaurant::auto_perhour();
        }

		if(getcustom('member_tag')){
			\app\models\Member::member_tag();
		}

        //可接收消息时段
        if(date('H') >= 8 && date('H') <= 20){

        }
        if(getcustom('fenhong_jiaquan_bylevel')){
            \app\commons\Fenhong::JiesuanJiaquanFenhongByDay();
        }
        //过期商家
        \app\commons\Business::update_expire_status();

        //积分过期
        \app\commons\Member::scoreExpire();
        if(getcustom('yx_team_yeji')){
            $this->teamyejiJiangli();
        }
		//周期优惠券发放
		if(getcustom('member_levelup_givecoupon')){
			$setlist = Db::name('admin_set')->select()->toArray();
			foreach($setlist as $set){
				$aid = $set['aid'];
				//周期优惠券发放
				$list = Db::name('member_give_coupon_log')->where('aid',$aid)->where('status',0)->where('beginzstime','<=',time())->select()->toArray();
				foreach($list as $l){
					$days=0;
					if($l['cycle_type']==2) $days=7;
					if($l['cycle_type']==3) $days=30;
					for($i=1;$i<=$l['coupon_num'];$i++){
						\app\commons\Coupon::send($aid,$l['mid'],$l['couponid'],false,0,$days);
					}
					Db::name('member_give_coupon_log')->where('id',$l['id'])->where('status',0)->update(['status'=>1,'zstime'=>time()]);
				}
			}
		}

        if(getcustom('yx_queue_free_multi_team')){
            $this->queueMultiTeamOut();
        }
        if(getcustom('yx_buy_fenhong')){
            if(date('H') == 1){
                \app\customs\BuyFenhong::sendBuyFenhong();
            }
        }
    }
	//每天执行
	private function perday(){
		$this->fenhong('perday');

		if(getcustom('score_withdraw')){
            $this->scoreToWithdraw();
        }

		$this->depositOrderExpire();
		
		if(getcustom('fxjiesuantime_perweek')){
			//每周结算佣金
			$syssetlist = Db::name('admin_set')->where('fxjiesuantime_delaydays','<>',0)->select()->toArray();
			foreach($syssetlist as $sysset){
				\app\commons\Order::jiesuanCommissionWeek($sysset['aid'],$sysset);
			}
		}

		if(getcustom('forcerebuy') && date('d') == '01'){
			$forcerebuyList = Db::name('forcerebuy')->where('type',1)->where('status',1)->select()->toArray();
			foreach($forcerebuyList as $forcerebuy){
				$aid = $forcerebuy['aid'];
				if($forcerebuy['daytype'] == 0){
					$starttime = strtotime(date('Y-m-01',strtotime(date('Y-m-01')) - 86400));
					$endtime = strtotime(date('Y-m-01')) - 1;
				}elseif($forcerebuy['daytype'] == 1){
					if(date('m') != '01' && date('m') != '04' && date('m') != '07' && date('m') != '10') continue;
					if(date('m') == '01'){
						$starttime = strtotime((date('Y')-1).'-09-01');
						$endtime = strtotime(date('Y-01-01')) - 1;
					}
					if(date('m') == '04'){
						$starttime = strtotime(date('Y').'-01-01');
						$endtime = strtotime(date('Y-04-01')) - 1;
					}
					if(date('m') == '07'){
						$starttime = strtotime(date('Y').'-04-01');
						$endtime = strtotime(date('Y-07-01')) - 1;
					}
					if(date('m') == '10'){
						$starttime = strtotime(date('Y').'-07-01');
						$endtime = strtotime(date('Y-10-01')) - 1;
					}
				}elseif($forcerebuy['daytype'] == 2){
					if(date('m') != '01') continue;
					$starttime = strtotime((date('Y')-1).'-01-01');
					$endtime = strtotime(date('Y-01-01')) - 1;
				}
				//本周期复购是否达标
				$mwhere = [];
				$mwhere[] = ['aid','=',$aid];
				if($forcerebuy['wfgtype'] == 0){
					$mwhere[] = ['commission_isfreeze','=',0];
				}
				$gettj = explode(',',$forcerebuy['gettj']);
				if(!in_array('-1',$gettj)){
					$mwhere[] = ['levelid','in',$gettj];
				}
				$memberList = Db::name('member')->where($mwhere)->select()->toArray();
				foreach($memberList as $member){
					$mid = $member['id'];
					$orderwhere = [];
					$orderwhere[] = ['aid','=',$aid];
					$orderwhere[] = ['mid','=',$mid];
					$orderwhere[] = ['createtime','>=',$starttime];
					$orderwhere[] = ['createtime','<=',$endtime];
					$orderwhere[] = ['status','in','1,2,3'];
					if($forcerebuy['fwtype'] == 1){
						$orderwhere[] = ['cid','in',$forcerebuy['categoryids']];
					}elseif($forcerebuy['fwtype'] == 2){
						$orderwhere[] = ['proid','in',$forcerebuy['productids']];
					}
					$totalprice = Db::name('shop_order_goods')->where($orderwhere)->sum('totalprice');
					if($totalprice < $forcerebuy['price']){
						if($forcerebuy['wfgtype'] == 0){
							Db::name('member')->where('id', $mid)->update(['commission_isfreeze' => 1]);
						}else{
							Db::name('member')->where('id', $mid)->update(['levelid' => $forcerebuy['wfglvid'], 'levelendtime' => 0]);
						}
					}
				}
			}
		}

        if(getcustom('coupon_expire_notice')){
            \app\commons\Coupon::auto_expire_notice();
        }
        if(getcustom('region_partner')){
            //每天发放区域分红
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $sysset){
                \app\commons\Fenhong::regionPartnerBonus($sysset['aid'],$sysset);
            }
        }
        if(getcustom('coupon_xianxia_buy')){
            $this->xianixaCouponYeji(); 
        }
        //平台加权奖励【每月1号发放】
        if(getcustom('commission_platform_avg_bonus')){
            $this->platformAvgBonus();
        }
        //薪资奖励【每月1号发放】
        if(getcustom('member_level_salary_bonus')){
            $this->levelSalaryBonus();
        }
        if(getcustom('yx_queue_free_today_average')){
            \app\customs\QueueFree::todayAverageOtherFafang();
        }
        //每日清除临时文件
        $dirTemp = ROOT_PATH . 'upload/temp';
        \app\commons\File::clear_dir($dirTemp);
	}
	//普通客户被设置成vip后，当天必须下单，不下单，第二天自动变成普通用户，下单后，后面15天没订单也变成普通用户
    public function member_vip_edit(){
        if(getcustom('member_vip_edit')) {
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            foreach ($syssetlist as $key => $sysset) {
                //查找刚 昨天 从普通会员升级为会员的 
                $defaultlevel = Db::name('member_level')->where('aid', $sysset['aid'])->where('isdefault', 1)->find();
                $zt_start_time = strtotime(date('Y-m-d 00:00:00', strtotime('-1 day')));
                $zt_end_time = strtotime(date('Y-m-d 23:59:59', strtotime('-1 day')));
                if ($sysset['member_no_order_expire_status']) {
                    $uplvlist = Db::name('member_levelup_order')->alias('ml')
                        ->join('member m', 'm.id = ml.mid')
                        ->where('ml.beforelevelid', $defaultlevel['id'])
                        ->where('ml.status', 2)
                        ->where('ml.levelup_time', 'between', [$zt_start_time, $zt_end_time])
                        ->where('m.levelid', '<>', $defaultlevel['id'])
                        ->where('ml.aid', $sysset['aid'])
                        ->select()->toArray();
                    foreach ($uplvlist as $key => $val) {
                        $count = Db::name('shop_order')->where('aid', $sysset['aid'])->where('mid', $val['mid'])->where('status', 'in', [1, 2, 3])
                            ->where('paytime', 'between', [$zt_start_time, $zt_end_time])->count();
                        //数量是0的 回退到普通会员
                        if ($count <= 0) {
                            Db::name('member')->where('aid', $sysset['aid'])->where('id', $val['mid'])->update(['levelid' => $defaultlevel['id'], 'levelendtime' => 0]);
                        }
                    }
                }

                //查找  15天以前升级为会员的普通会员 就是 第16天（今天执行不算 算昨天的第15天）
                $days = $sysset['member_vip_no_order_days'];
                $days_15 = $days + 1;
                $zt_start_time_15 = strtotime(date('Y-m-d 00:00:00', strtotime('-' . $days_15 . ' day')));//（昨天的）15天前的一天  24号执行 23号算16天 就是8号
                $zt_end_time_15 = strtotime(date('Y-m-d 23:59:59', strtotime('-' . $days_15 . ' day')));  //（昨天的）15天前的一天
                $uplvlist_15 = Db::name('member_levelup_order')->alias('ml')
                    ->join('member m', 'm.id = ml.mid')
                    ->where('ml.beforelevelid', $defaultlevel['id'])
                    ->where('ml.status', 2)
                    ->where('ml.levelup_time', 'between', [$zt_start_time_15, $zt_end_time_15])
                    ->where('m.levelid', '<>', $defaultlevel['id'])
                    ->field('ml.id,ml.mid,ml.status,ml.levelup_time,ml.levelid')
                    ->where('ml.aid', $sysset['aid'])
                    ->select()->toArray();

                $_after_15 = strtotime(date('Y-m-d 00:00:00', strtotime('-' . $days . ' day')));//订单是 后面15天 24号执行 23号算15天 就是9号 到昨天结束的订单数量
                foreach ($uplvlist_15 as $key => $val15) {
                    $count = Db::name('shop_order')->where('aid', $sysset['aid'])->where('mid', $val15['mid'])->where('status', 'in', [1, 2, 3])
                        ->where('paytime', 'between', [$_after_15, $zt_end_time])->count();
                    //数量是0的 回退到普通会员
                    if ($count <= 0) {
                        Db::name('member')->where('aid', $sysset['aid'])->where('id', $val15['mid'])->update(['levelid' => $defaultlevel['id'], 'levelendtime' => 0]);
                    }
                }
            }
        }
    }
	public function test(){
        
	}

	//分红
	private function fenhong($pertime){
		$midCommissionList = [];
		$syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
		foreach($syssetlist as $sysset){
			$aid = $sysset['aid'];
			$fhjiesuantype = $sysset['fhjiesuantype'];
            $fhjiesuantime_type = $sysset['fhjiesuantime_type'];//分红结算时间类型 0收货后，1付款后
			$fhjiesuantime = $sysset['fhjiesuantime'];
			if($fhjiesuantime_type == 1) {
                if($pertime == 'perday') continue;
                if($pertime == 'perhour') continue;
                $starttime = 1;
                $endtime = time();
            } else {
				if($fhjiesuantime == 10) continue;  //手动结算
                if($fhjiesuantime == 0){ //按天结算
                    if($pertime == 'perhour') continue;
                    if($pertime == 'perminute') continue;
                    //$starttime = strtotime(date('Y-m-d'))-86400;
					$starttime = 1;
                    $endtime = strtotime(date('Y-m-d'));
                }elseif($fhjiesuantime == 1){ //月初结算
                    if($pertime == 'perhour') continue;
                    if($pertime == 'perminute') continue;
                    //$starttime = strtotime(date('Y-m-01').' -1 month');
					$starttime = 1;
                    $endtime = strtotime(date('Y-m-01'));
                }elseif($fhjiesuantime == 2){ //按小时结算
                    if($pertime == 'perday') continue;
                    if($pertime == 'perminute') continue;
                    $starttime = 1;
                    $endtime = time();
                }elseif($fhjiesuantime == 3){ //每分钟结算
                    if($pertime == 'perday') continue;
                    if($pertime == 'perhour') continue;
                    $starttime = 1;
                    $endtime = time();
                }elseif($fhjiesuantime == 4){ //月底结算
                    if($pertime == 'perhour') continue;
                    if($pertime == 'perminute') continue;
                    if(date("t") != date("j")) continue;
                    $starttime = 1;
                    $endtime = time();
                }elseif($fhjiesuantime == 5){ //年底结算
                    if($pertime == 'perhour') continue;
                    if($pertime == 'perminute') continue;
                    if(date("t") != date("j") || date("m")!=12) continue;
                    $starttime = 1;
                    $endtime = time();
                }elseif($fhjiesuantime == 7){ //周一凌晨1点结算
                    if($pertime == 'perminute') continue;
                    if($pertime == 'perday') continue;
                    if(date("w") != 1) continue;
                    $starttime = 1;
                    //本周
                    $thisweek_start = mktime(0, 0 , 0,date("m"),date("d")-date("w")+1,date("Y"));
                    $endtime = $thisweek_start;
                }
            }
			\app\commons\Fenhong::jiesuan($aid,$starttime,$endtime);
            if(getcustom('car_hailing')){
                \app\commons\CarhailingFenhong::jiesuan($aid,$starttime,$endtime);
            }
		}
	}
	//分账 https://pay.weixin.qq.com/wiki/doc/api/allocation.php?chapter=27_1&index=1
	private function profitsharing($wxpaylog,$receivers,$addreceivers,$sub_mchid,$dbwxpayset,$transaction_id,$sub_appid,$times=0,$multi=false){

		$mchkey = $dbwxpayset['mchkey'];
		$sslcert = ROOT_PATH.str_replace(PRE_URL.'/','',$dbwxpayset['apiclient_cert']);
		$sslkey = ROOT_PATH.str_replace(PRE_URL.'/','',$dbwxpayset['apiclient_key']);

		$pars = array();
		$pars['mch_id'] = $dbwxpayset['mchid'];
		$pars['sub_mch_id'] = $sub_mchid;
		$pars['appid'] = $dbwxpayset['appid'];
		$pars['nonce_str'] = random(32);
		$pars['transaction_id'] = $transaction_id;
		$pars['out_order_no'] = 'P'.date('YmdHis').rand(1000,9999);
		$pars['receivers'] = jsonEncode($receivers);
		if($sub_appid){
			$pars['sub_appid'] = $sub_appid;
		}
		//$pars['sign_type'] = 'MD5';
		ksort($pars, SORT_STRING);
		$string1 = '';
		foreach ($pars as $k => $v) {
			$string1 .= "{$k}={$v}&";
		}
		$string1 .= "key=" . $mchkey;
		//$pars['sign'] = strtoupper(md5($string1));
		$pars['sign'] = strtoupper(hash_hmac("sha256",$string1 ,$mchkey));
		$xml = array2xml($pars);
        \think\facade\Log::write(__FILE__.__LINE__.__FUNCTION__);
		Log::write($pars);
		Log::write($xml);
		//Log::write($sslcert);

        $exist = Db::name('wxpay_fzlog')->where('transaction_id',$wxpaylog['transaction_id'])->where('receiversjson',$pars['receivers'])->find();
        if(!$exist){
            $insert = [
                'aid'=>$wxpaylog['aid'],
                'bid'=>$wxpaylog['bid'],
                'mid'=>$wxpaylog['mid'],
                'logid'=>$wxpaylog['id'],
                'openid'=>$wxpaylog['openid'],
                'tablename'=>$wxpaylog['tablename'],
                'ordernum'=>$wxpaylog['ordernum'],
                'mch_id'=>$wxpaylog['mch_id'],
                'sub_mchid'=>$wxpaylog['sub_mchid'],
                'transaction_id'=>$wxpaylog['transaction_id'],
                'out_order_no'=>$pars['out_order_no'],
                'receiversjson'=>$pars['receivers'],
                'createtime'=>time(),
                'fz_ordernum'=>$pars['out_order_no'],
                'platform'=>$wxpaylog['platform']
            ];
            $fzlogid = Db::name('wxpay_fzlog')->insertGetId($insert);
        }else{
            Db::name('wxpay_fzlog')->where('transaction_id',$wxpaylog['transaction_id'])->where('id',$exist['id'])
                ->update(['out_order_no'=>$pars['out_order_no'],'fz_ordernum'=>$pars['out_order_no'],'createtime'=>time()]);
            $fzlogid = $exist['id'];
        }

		$ch = curl_init ();
        if($multi){
            curl_setopt ( $ch, CURLOPT_URL, "https://api.mch.weixin.qq.com/secapi/pay/multiprofitsharing" );
        }else{
            curl_setopt ( $ch, CURLOPT_URL, "https://api.mch.weixin.qq.com/secapi/pay/profitsharing" );
        }

		curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
		curl_setopt ( $ch, CURLOPT_SSLCERT,$sslcert);
		curl_setopt ( $ch, CURLOPT_SSLKEY,$sslkey);
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		curl_setopt ( $ch, CURLOPT_AUTOREFERER, 1 );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $xml );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );

		$info = curl_exec ( $ch );
		curl_close ( $ch );
		//Log::write($info);
		$resp = (array)(simplexml_load_string($info,'SimpleXMLElement', LIBXML_NOCDATA));
		Log::write($resp);
		if($resp['return_code'] == 'SUCCESS' && $resp['result_code']=='SUCCESS'){
            Db::name('wxpay_fzlog')->where('transaction_id',$wxpaylog['transaction_id'])->where('id',$fzlogid)
                ->update(['isfenzhang'=>1,'fz_errmsg'=>'']);
			return ['status'=>1,'msg'=>'分账成功','resp'=>$resp,'ordernum'=>$pars['out_order_no']];
		}else{
			//Log::write('profitsharing');
			//Log::write($resp);
			if($times == 0 && ($resp['err_code'] == 'PARAM_ERROR' || $resp['err_code'] == 'RECEIVER_INVALID')){
			//if($times == 0 && $resp['err_code'] == 'RECEIVER_INVALID'){
				foreach($addreceivers as $addreceiver){
					$pars = array();
					$pars['mch_id'] = $dbwxpayset['mchid'];
					$pars['sub_mch_id'] = $sub_mchid;
					$pars['appid'] = $dbwxpayset['appid'];
					$pars['nonce_str'] = random(32);
					$pars['receiver'] = jsonEncode($addreceiver);
					if($sub_appid){
						$pars['sub_appid'] = $sub_appid;
					}
					//$pars['sign_type'] = 'MD5';
					ksort($pars, SORT_STRING);
					$string1 = '';
					foreach ($pars as $k => $v) {
						$string1 .= "{$k}={$v}&";
					}
					$string1 .= "key=" . $mchkey;
					//$pars['sign'] = strtoupper(md5($string1));
					$pars['sign'] = strtoupper(hash_hmac("sha256",$string1 ,$mchkey));
					$xml = array2xml($pars);
					$ch = curl_init ();
					curl_setopt ( $ch, CURLOPT_URL, "https://api.mch.weixin.qq.com/secapi/pay/profitsharingaddreceiver" );
					curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
					curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
					curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
					curl_setopt ( $ch, CURLOPT_SSLCERT,$sslcert);
					curl_setopt ( $ch, CURLOPT_SSLKEY,$sslkey);
					curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
					curl_setopt ( $ch, CURLOPT_AUTOREFERER, 1 );
					curl_setopt ( $ch, CURLOPT_POSTFIELDS, $xml );
					curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
					$info = curl_exec ( $ch );
					curl_close ( $ch );
					Log::write('profitsharingaddreceiver');
					Log::write($info);
					sleep(2);
				}
				return $this->profitsharing($wxpaylog,$receivers,$addreceivers,$sub_mchid,$dbwxpayset,$transaction_id,$sub_appid,1);
			}
			$msg = '未知错误';
			if ($resp['return_code'] == 'FAIL') {
				$msg = $resp['return_msg'];
			}
			if ($resp['result_code'] == 'FAIL') {
				$msg = $resp['err_code_des'];
			}
            Db::name('wxpay_fzlog')->where('transaction_id',$wxpaylog['transaction_id'])->where('id',$fzlogid)
                ->update(['isfenzhang'=>2,'fz_errmsg'=>$msg]);
			return ['status'=>0,'msg'=>$msg,'resp'=>$resp];
		}
	}

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 1、小于1积分时，按1积分允许释放。
     * 2、大于1积分时，对小数位四舍五入取整数。
     */
	public function scoreToWithdraw()
    {
        if(getcustom('score_withdraw')){
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $sysset){
                if($sysset['score_withdraw'] == 1 && $sysset['score_withdraw_percent_day'] > 0) {
//                $oneStand = (100 / $sysset['score_withdraw_percent_day']);
                    $oneStand = 0;
                    $mlist = Db::name('member')->where('aid', $sysset['aid'])->where('score', '>', $oneStand)->select()->toArray();
                    if($mlist) {
                        foreach ($mlist as $member) {
                            $score_withdraw = 0;
                            $score_withdraw = round($member['score'] * $sysset['score_withdraw_percent_day'] / 100);
                            if($score_withdraw < 1 && $member['score'] > 1) {
                                $score_withdraw = 1;
                            }
                            if($score_withdraw > 0) {
                                \app\commons\Member::addscore($sysset['aid'],$member['id'], $score_withdraw * -1, '转为允提'.t('积分',$sysset['aid']));
                                \app\commons\Member::addscore_withdraw($sysset['aid'],$member['id'], $score_withdraw, '转入允提'.t('积分',$sysset['aid']));
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * 积分自动转余额
     * 1、小于1积分时，按1积分允许释放。
     * 2、大于1积分时，对小数位四舍五入取整数。
     */
    public function scoreToMoney()
    {
        $score_weishu = 0;
        $score_weishu_set = getcustom('score_weishu')?:0;
        if(getcustom('score_to_money_auto')){
            //判断今日是否已执行
            $exit = Db::name('score_tomoney_log')->where('w_day',date('Ymd'))->find();
            if($exit){
                return true;
            }
            //添加自动转余额记录，防止重复执行
            Db::name('score_tomoney_log')->insert(['w_day'=>date('Ymd')]);
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $sysset){
                if($score_weishu_set){
                    $score_weishu = $sysset['score_weishu']??0;
                }
                if($sysset['score_to_money_auto'] == 1 && $sysset['score_to_money_auto_day'] > 0) {
                    $mlist = Db::name('member')->where('aid', $sysset['aid'])->where('score', '>', 0)
                        ->where('score_to_money_auto',1)
                        ->select()->toArray();
                    if($mlist) {
                        foreach ($mlist as $member) {
                            $score_to_money_auto_day = $sysset['score_to_money_auto_day'];
                            if($member['score_to_money_auto_day']>0){
                                $score_to_money_auto_day = $member['score_to_money_auto_day'];
                            }
                            $score_num = bcmul($member['score'] , $score_to_money_auto_day / 100,$score_weishu);
                            if($score_num <= 0) {
                                continue;
                            }
                            $score_to_money_auto_percent = $sysset['score_to_money_auto_percent']?:1;
                            $money = bcmul($score_num,$score_to_money_auto_percent,2);
                            if($score_num > 0) {
                                \app\commons\Member::addscore($sysset['aid'],$member['id'], $score_num * -1, t('积分',$sysset['aid']).'每日'.$score_to_money_auto_day.'%释放到'.t('余额',$sysset['aid']));
                                \app\commons\Member::addmoney($sysset['aid'],$member['id'], $money, t('积分',$sysset['aid']).'每日'.$score_to_money_auto_day.'%释放到'.t('余额',$sysset['aid']));
                            }
                        }
                    }
                }
            }

        }
    }

    private function yuebao()
    {
      if(getcustom('plug_yuebao')){
        //余额宝
          //读取配置
          $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
          if($syssetlist){
              foreach($syssetlist as $sv){
                  //读取大于0的用户余额
                  $sel_member = Db::name('member')
                      ->where('aid',$sv['aid'])
                      ->where('money','<>',0)
                      ->field('id,money,yuebao_rate')
                      ->select()
                      ->toArray();
                  //如果余额宝开启、余额收益比例大于0、且用户存在
                  if($sv['open_yuebao'] ==1 && $sv['yuebao_rate'] >0 && $sel_member){

                      foreach($sel_member as $mv){
                          //查询是收益率是否单独设置
                          if($mv['yuebao_rate']>=0){
                              $yuebao_rate = $mv['yuebao_rate']/100;
                          }else{
                              $yuebao_rate = $sv['yuebao_rate']/100;
                          }

                          //计算用户收益
                          $m_money = $mv['money']*$yuebao_rate;
                          if($m_money!=0){
                              $m_money = round($m_money,3);
                              \app\commons\Member::addyuebaomoney($sv['aid'],$mv['id'],$m_money,t('余额宝').'收益',1);
                          }
                      }
                  }
              }
          }
      }
    }

    //寄存订单过期
    private function depositOrderExpire()
    {
        $syssetlist = Db::name('restaurant_deposit_sysset')->where('1=1')->select()->toArray();
        if($syssetlist) {
            $time = time();
            foreach ($syssetlist as $set) {
                if($set['time'] > 0) {
                    Db::name('restaurant_deposit_order')->where('aid',$set['aid'])->where('bid',$set['bid'])
                        ->where('status',1)->where('createtime','<',$time-intval($set['time'])*86400)->update(['status' => 4]);
                }
            }
        }
    }

    public function product_img_baidu_sync()
    {
        if(getcustom('image_search')){
            $limit = 10;
            $aids = Db::name('admin')->where('image_search',1)->where('status',1)->column('id');
            if(empty($aids)) return;
            $syssetlist = Db::name('baidu_set')->whereIn('aid',$aids)->where('image_search',1)->where('baidu_apikey','<>','')->where('baidu_secretkey','<>','')->select()->toArray();
            foreach($syssetlist as $sysset) {
                $aid = $sysset['aid'];
                $bid = $sysset['bid'];
                $baidu = new \app\customs\Baidu($aid,$bid);
                $baidu->sync($limit);
            }
        }
    }

	public function fifaauto(){
		if(time() > 1671422400) return;

		$fifaauto_randnum = cache('fifaauto_randnum');
		if(!$fifaauto_randnum){
			$fifaauto_randnum = ''.rand(0,4);
			cache('fifaauto_randnum',$fifaauto_randnum);
		}

		if(!in_array(date('i'),['0'.$fifaauto_randnum,'1'.$fifaauto_randnum,'2'.$fifaauto_randnum,'3'.$fifaauto_randnum,'4'.$fifaauto_randnum,'5'.$fifaauto_randnum])) return;

		sleep(rand(0,20));
		
		\think\facade\Log::write('---fifaauto----'.date('Y-m-d H:i:s'));

		\app\customs\Fifa::initdata();

		$fifadata = Db::name('fifa')->where('matchStatus',2)->select()->toArray();
		foreach($fifadata as $fifa){
			$leftTeam_score = intval($fifa['leftTeam_score']);
			$rightTeam_score = intval($fifa['rightTeam_score']);
			$successguess2 = $leftTeam_score.':'.$rightTeam_score;
			if($leftTeam_score > $rightTeam_score){
				$successguess1 = '1';
				if($leftTeam_score > 5) $successguess2 = '胜其他';
			}elseif($leftTeam_score == $rightTeam_score){
				$successguess1 = '2';
				if($leftTeam_score > 3) $successguess2 = '平其他';
			}else{
				$successguess1 = '3';
				if($rightTeam_score > 5) $successguess2 = '其他';
			}
			$syssetlist = Db::name('fifa_set')->where('status',1)->select()->toArray();
            foreach($syssetlist as $sysset) {
				$aid = $sysset['aid'];
				$recordList = Db::name('fifa_record')->where('aid',$aid)->where('hid',$fifa['id'])->where('status',0)->select()->toArray();
				foreach($recordList as $record){
					$update = [];
					$update['status'] = 1;
					if($record['guess1'] && $record['guess1'] == $successguess1){
						$update['guess1st'] = 1;
						$update['givescore1'] = intval($sysset['givescore1']);
						
						$successnum = 1 + Db::name('fifa_record')->where('aid',$aid)->where('mid',$record['mid'])->whereRaw('guess1st=1')->count();
						$guess1set = json_decode($sysset['guess1set'],true);
						if($guess1set){
							foreach($guess1set as $k=>$v){
								if($v['score']!=='' && $v['score']!==null && $successnum == $v['times']){
									$update['givescore1'] += intval($v['score']);
								}
								//赠送优惠券
								if($v['coupon_id']!=='' && $v['coupon_id']!==null && $successnum == $v['times']){
									\app\commons\Coupon::send($aid,$record['mid'],$v['coupon_id']);
								}
							}
						}
						\app\commons\Member::addscore($aid,$record['mid'],$update['givescore1'],'世界杯竞猜成功奖励');

					}else{
						$update['guess1st'] = 2;
					}
					if($record['guess2'] && $record['guess2'] == $successguess2){
						$update['guess2st'] = 1;
						$update['givescore2'] = intval($sysset['givescore2']);
						
						$successnum = 1 + Db::name('fifa_record')->where('aid',$aid)->where('mid',$record['mid'])->whereRaw('guess2st=1')->count();
						$guess2set = json_decode($sysset['guess2set'],true);
						if($guess2set){
							foreach($guess2set as $k=>$v){
								if($v['score']!=='' && $v['score']!==null && $successnum == $v['times']){
									$update['givescore2'] += intval($v['score']);
								}
								//赠送优惠券
								if($v['coupon_id']!=='' && $v['coupon_id']!==null && $successnum == $v['times']){
									\app\commons\Coupon::send($aid,$record['mid'],$v['coupon_id']);
								}
							}
						}
						\app\commons\Member::addscore($aid,$record['mid'],$update['givescore2'],'世界杯竞猜成功奖励');
					}else{
						$update['guess2st'] = 2;
					}

					Db::name('fifa_record')->where('id',$record['id'])->update($update);
				}
			}
		}
	}

    //定时抽奖 1分钟执行一次
    public function run_dscj()
    {
        if (getcustom('choujiang_time')) {
            \app\models\Dscj::kaijiang();
        }
    }

    public function huidong()
    {
        \app\customs\HuiDong::syncMember();
    }

    /**
     * 自动降级
     */
    public function autoDownLevel(){

        //会员等级到期
//        $check_time = time()+35*86400;
        $check_time = time();
        $memberlist = Db::name('member')->where("levelendtime>0 and levelendtime<".$check_time)->select()->toArray();
        //dump($memberlist);
        foreach($memberlist as $member){

            if(getcustom('level_auto_down')){
                //检测推荐人和团队业绩
                $level_data = Db::name('member_level')->where('id',$member['levelid'])->find();
                if($level_data['check_type']>0){
                    $check_result = \app\commons\Member::checkDownLevelCon($member,$level_data);
                    //dump($check_result);
                    if(!$check_result['is_down']){
                        //考核通过，更新有效期
                        $data_u = [];
                        if($level_data['check_type']==1){
                            $data_u['levelstarttime'] = $member['levelendtime'];
                            if($level_data['yxqdate']>0){
                                $data_u['levelendtime'] = $member['levelendtime']+86400 * $level_data['yxqdate'];
                            }else{
                                $data_u['levelendtime'] = 0;
                            }
                        }else{
                            $data_u['levelendtime'] = 0;
                        }
                        Db::name('member')->where('id', $member['id'])->update($data_u);
                        continue;
                    }
                }
            }
            $is_default = 1;
            $defaultlevel = Db::name('member_level')->where('aid', $member['aid'])->where('isdefault', 1)->find();
            if(getcustom('next_level_set') || getcustom('level_auto_down')){
                //是不是有设置的下个等级
                $curlevel = Db::name('member_level')->where('aid',$member['aid'])->where('id',$member['levelid'])->find();
                if ($curlevel && $curlevel['next_level_id'] > 0 && $curlevel['next_level_id']!=$defaultlevel['id']) {
                    $nextlevel = Db::name('member_level')->where('id',$curlevel['next_level_id'])->find();
                    if($nextlevel){
                        $is_default = 0;
                        $newlv['levelid'] = $nextlevel['id'];
                        $newlv['levelendtime'] = strtotime(date('Y-m-d')) + 86400 + 86400 * $nextlevel['yxqdate'];
                        Db::name('member')->where('id', $member['id'])->update($newlv);
                    }
                }
            }

            if($is_default==1) {
                $newlv['levelid'] = $defaultlevel['id'];
                Db::name('member')->where('id', $member['id'])->update(['levelid' => $defaultlevel['id'], 'levelendtime' => 0]);
            }
            //if(getcustom('level_auto_down')) {
                //降级记录
                $order = [
                    'aid' => $member['aid'],
                    'mid' => $member['id'],
                    'from_mid' => $member['id'],
                    'pid'=>$member['pid'],
                    'levelid' => $newlv['levelid'],
                    'title' => '自动降级',
                    'totalprice' => 0,
                    'createtime' => time(),
                    'levelup_time' => time(),
                    'beforelevelid' => $member['levelid'],
                    'form0' => '类型^_^' . $check_result['desc']??'自动降级',
                    'platform' => '',
                    'status' => 2,
                    'type' => 1
                ];
                Db::name('member_levelup_order')->insert($order);
                //Db::name('member_leveldown_record')->insert($order);
            //}
        }

        //其他分组等级到期后失效
        Db::name('member_level_record')->where("levelendtime>0 and levelendtime<".time())->delete();

    }
    
    //智能开门设备过期和 10分钟提醒
    public function cerberuseExpire(){
        if (getcustom('lot_cerberuse')){
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $sysset) {
                $cerberuse_set = Db::name('cerberuse_set')->where('aid',$sysset['aid'])->find();
                //已支付的过期关闭
                Db::name('cerberuse_order')->where('aid',$sysset['aid'])->where('endtime','<=',time())->where('status','in',[1,2])->update(['status'=>3]);
                
                //未支付的 过期关闭
                $autoclose =  $cerberuse_set['autoclose']?$cerberuse_set['autoclose']:10;
                $expirse_time = time()- $autoclose * 60;
                Db::name('cerberuse_order')->where('aid',$sysset['aid'])->where('createtime','<=',$expirse_time)->where('status',0)->update(['status' => 4]);
                
                //查询距离结束还有10分钟结束的 进行提醒
                $minute = $cerberuse_set['remind_minute'];
                $remind_minute = $minute?$minute:10;
                $entime = time() + $remind_minute*60;
                $list = Db::name('cerberuse_order')->where('aid',$sysset['aid'])->where('endtime','<=',$entime)->where('status',2)->where('is_notice',0)->select()->toArray();
                if(!$list){
                    continue;
                }
                foreach($list as $key=>$val){
                    $cerberuse = Db::name('cerberuse')->where('aid',$val['aid'])->where('id',$val['proid'])->find();
                    $content =[
                        'method' => 'playTts',
                        'content' => '温馨提醒！您消费的时间还有'.$remind_minute.'分钟！即将到期！',
                        'vol' => 4
                    ];
                    $content_json = json_encode($content,JSON_UNESCAPED_UNICODE);
                    $topic= $val['aid'].'/'.$cerberuse['imei'];
                    $mqtt = new \app\customs\Mqtt();
                    $mqtt -> publish($topic,$content_json);
                    Db::name('cerberuse_order')->where('id',$val['id'])->update(['is_notice' => 1 ]);
                    //短信通知
                    $member = Db::name('member')->where('id',$val['mid'])->find();
                    if($member['tel']){
                        $tel = $member['tel'];
                    }else{
                        $tel = $val['tel'];
                    }
                    $rs = \app\commons\Sms::send($val['aid'],$tel,'tmpl_use_expire',[]);
                    //模板小时到期通知
                    $wx_tmplset = Db::name('wx_tmplset')->where('aid',aid)->field('tmpl_use_expire')->find();
                    if($wx_tmplset && $wx_tmplset['tmpl_use_expire']){
                        $tmplcontent = [];
                        $tmplcontent['thing1']  = $cerberuse['title'];
                        $tmplcontent['time2'] = date('Y-m-d H:i',$val['endtime']);
                        $tmplcontent['thing3'] = '您消费的时间即将到期';
                        \app\commons\Wechat::sendwxtmpl($val['aid'],$val['mid'],'tmpl_use_expire',$tmplcontent,m_url('pagesZ/cerberuse/index'),0);
                    }
                    
                }
                
            }
        }
    }

    //统计商户销量
    public function countSales(){
        ini_set('memory_limit','1024M');
        set_time_limit(0);
        $sales_type = [
            'sales' => 'sales',
            'shop' => 'shop_sales',
            'collage' => 'collage_sales',
            'kanjia' => 'kanjia_sales',
            'seckill' => 'seckill_sales',
            'tuangou' => 'tuangou_sales',
            'scoreshop' => 'scoreshop_sales',
            'lucky_collage' => 'lucky_collage_sales',
            'yuyue' => 'yuyue_sales',//预约服务
            'kecheng' => 'kecheng_sales',//课程
            'cycle' => 'cycle_sales',//周期购
            'restaurant_takeaway' => 'restaurant_takeaway_sales',//餐饮外卖
            'restaurant_shop' => 'restaurant_shop_sales',//餐饮点餐
            'maidan' => 'maidan_sales'//买单
        ];
        Db::startTrans();
        Db::execute('truncate table ddwx_business_sales');
        foreach($sales_type as $type=>$sales_field){
            switch ($type){
                case 'shop':
                    $orders = Db::name('shop_order_goods')->whereIn('status',[1,2,3])->field('aid,bid,num')->select()->toArray();
                    $products = Db::name('shop_product')->where('1=1')->field('aid,bid,sales')->select()->toArray();
                    break;
                case 'collage':
                    $orders = Db::name('collage_order')->whereIn('status',[1,2,3])->field('aid,bid,num')->select()->toArray();
                    $products = Db::name('collage_product')->where('1=1')->field('aid,bid,sales')->select()->toArray();
                    break;
                case 'kanjia':
                    $orders = Db::name('kanjia_order')->whereIn('status',[1,2,3])->field('aid,bid,num')->select()->toArray();
                    $products = Db::name('kanjia_product')->where('1=1')->field('aid,bid,sales')->select()->toArray();
                    break;
                case 'seckill':
                    $orders = Db::name('seckill_order')->whereIn('status',[1,2,3])->field('aid,bid,num')->select()->toArray();
                    $products = Db::name('seckill_product')->where('1=1')->field('aid,bid,sales')->select()->toArray();
                    break;
                case 'tuangou':
                    $orders = Db::name('tuangou_order')->whereIn('status',[1,2,3])->field('aid,bid,num')->select()->toArray();
                    $field = 'aid,bid,sales';
                    if(getcustom('yx_tuangou_vrnum')){
                        $field = 'aid,bid,(sales+vrnum) sales';
                    }
                    $products = Db::name('tuangou_product')->where('1=1')->field($field)->select()->toArray();
                    break;
                case 'scoreshop':
                    $orders = Db::name('scoreshop_order_goods')->whereIn('status',[1,2,3])->field('aid,bid,num')->select()->toArray();
                    $products = Db::name('scoreshop_product')->where('1=1')->field('aid,bid,sales')->select()->toArray();
                    break;
                case 'lucky_collage':
                    $orders = Db::name('lucky_collage_order')->whereIn('status',[1,2,3])->field('aid,bid,num')->select()->toArray();
                    $products = Db::name('lucky_collage_product')->where('1=1')->field('aid,bid,sales')->select()->toArray();
                    break;
                case 'yuyue':
                    $orders = Db::name('yuyue_order')->whereIn('status',[1,2,3])->field('aid,bid,num')->select()->toArray();
                    $products = Db::name('yuyue_product')->where('1=1')->field('aid,bid,sales')->select()->toArray();
                    break;
                case 'kecheng':
                    $orders = Db::name('kecheng_order')->whereIn('status',[1,2,3])->field('aid,bid')->select()->toArray();
                    $products = Db::name('kecheng_list')->where('1=1')->field('aid,bid')->select()->toArray();
                    break;
                case 'cycle':
                    $orders = Db::name('cycle_order')->whereIn('status',[1,2,3])->field('aid,bid,num')->select()->toArray();
                    $products = Db::name('cycle_product')->where('1=1')->field('aid,bid,sales')->select()->toArray();
                    break;
                case 'restaurant_takeaway':
                    $orders = Db::name('restaurant_takeaway_order_goods')->whereIn('status',[1,2,3])->field('aid,bid,num')->select()->toArray();
                    $products = Db::name('restaurant_product')->where('1=1')->field('aid,bid,sales')->select()->toArray();
                    break;
                case 'restaurant_shop':
                    $orders = Db::name('restaurant_shop_order_goods')->whereIn('status',[1,2,3])->field('aid,bid,num')->select()->toArray();
                    break;
                case 'maidan':
                    $orders = Db::name('maidan_order')->whereIn('status',[1])->field('aid,bid,1 as num')->select()->toArray();
                    $products = [];
                    break;
            }


            //更新商户虚拟销量
            foreach($products as $product){
                if($product && empty($product['sales'])){
                    $product['sales'] = 0;
                }
                $aid = $product['aid']?:1;
                $bid = $product['bid'];
                $business = Db::name('business')->where('id',$bid)->find();
                if($bid && !$business){
                    continue;
                }
                $sale_num = $product['sales'];
                $business_sales = Db::name('business_sales')
                    ->where('aid',$aid)
                    ->where('bid',$bid)
                    ->find();

                if(!$business_sales && $sale_num>0){
                    $data_sales = [];
                    $data_sales['aid'] = $aid;
                    $data_sales['bid'] = $bid;
                    $data_sales['sales'] = $sale_num;
                    $data_sales['total_sales'] = $sale_num;
                    Db::name('business_sales')->insert($data_sales);
                }else{
                    $data_sales = [];
                    $data_sales['sales'] = $business_sales['sales']+$sale_num;
                    $data_sales['total_sales'] = $business_sales['total_sales']+$sale_num;
                    Db::name('business_sales')->where('id',$business_sales['id'])->update($data_sales);
                }
            }

            //更新商户订单销量
            foreach($orders as $order){
                if($order && empty($order['num'])){
                    $order['num'] = 1;
                }
                $aid = $order['aid']?:1;
                $bid = $order['bid'];
                $business = Db::name('business')->where('id',$bid)->find();
                if($bid && !$business){
                    continue;
                }
                $sale_num = $order['num'];
                $sales_field = $sales_type[$type];
                $business_sales = Db::name('business_sales')
                    ->where('aid',$aid)
                    ->where('bid',$bid)
                    ->find();

                if(!$business_sales && $sale_num>0){
                    $data_sales = [];
                    $data_sales['aid'] = $aid;
                    $data_sales['bid'] = $bid;
                    $data_sales[$sales_field] = $sale_num;
                    $data_sales['total_sales'] = $sale_num;
                    Db::name('business_sales')->insert($data_sales);
                }else{
                    $data_sales = [];
                    $data_sales[$sales_field] = $business_sales[$sales_field]+$sale_num;
                    $data_sales['total_sales'] = $business_sales['total_sales']+$sale_num;
                    Db::name('business_sales')->where('id',$business_sales['id'])->update($data_sales);
                }
            }
        }
        Db::commit();

        die('更新完成');

    }

    //奖金池
    public function bonusPoolDaily(){
        if(getcustom('product_bonus_pool')){
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $sysset) {
                $bonus_pool_status = Db::name('admin')->where('id',$sysset['aid'])->value('bonus_pool_status');
                if(!$bonus_pool_status){
                    continue;
                }
                $yesterday_start = strtotime(date('Y-m-d 00:00:00', strtotime('-1 day')));
                $yesterday_end = strtotime(date('Y-m-d 23:59:59', strtotime('-1 day')));
                $order = Db::name('shop_order')->where('paytime','between',[$yesterday_start,$yesterday_end])->where('status','in',[1])->where('aid',$sysset['aid'])->find();
                $poolshopset = Db::name('shop_sysset')->where('aid',$sysset['aid'])->field('bonus_pool_money_max,bonus_pool_cx_days,bonus_pool_already,bonus_pool_noreleasetj')->find();
                
                //如果昨天没有业绩，进行释放
                if(!$order){
                    //连续释放次数 小于 设置的次数才能进行释放
                    
                    if($poolshopset['bonus_pool_already'] >= $poolshopset['bonus_pool_cx_days']){
                        continue;
                    }
                    //发放奖励
                    $release_list = Db::name('shop_order')->alias('so')
                        ->join('member m','m.id = so.mid')
                        ->where('so.status','in',[1,2,3])
                        ->where('so.aid',$sysset['aid'])
                        ->order('so.paytime asc')
                        ->field('so.id,so.aid,so.status,so.createtime,so.mid,m.bonus_pool_money,m.levelid')
                        ->group('so.mid')
                        ->select()->toArray(); 
                  
                    //释放奖金池
                    if($release_list){
                        foreach ($release_list as $mk=>$mv){
                            $pool = Db::name('bonus_pool')->where('aid',$mv['aid'])->where('status',0)->order('id asc')->find();
                            
                            if(!$pool){
                                continue;
                            }
                            //用户达到上限，不释放
//                            if($mv['bonus_pool_money']+$pool['money'] >= $poolshopset['bonus_pool_money_max']){
//                                \think\facade\Log::write($mv['mid'].'达到上限');
//                                continue;
//                            }
                            $bonus_pool_money = dd_money_format($mv['bonus_pool_money'] + $pool['money']);
                            //增加log
                            $log = [
                                'aid' =>$mv['aid'],
                                'mid' =>$mv['mid'],
                                'frommid' => 0,
                                'commission' => $pool['money'],
                                'after' => $bonus_pool_money,
                                'createtime' => time(),
                                'remark' => '奖金发放'
                            ];
                            Db::name('member_bonus_pool_log') ->insert($log);
                            //修改奖金池状态
                            Db::name('member')->where('id',$mv['mid'])->update(['bonus_pool_money' => $bonus_pool_money]);
                            Db::name('bonus_pool')->where('aid',$mv['aid'])->where('id',$pool['id'])->update(['status' => 1,'mid' => $mv['mid'],'endtime' => time()]);
                        }
                        //持续每天的次数增加1
                        Db::name('shop_sysset')->where('aid',$sysset['aid'])->inc('bonus_pool_already',1)->update();
                    }
                }else{
                    //有订单 持续每天 设置为0 从头开始
                    Db::name('shop_sysset')->where('aid',$sysset['aid'])->update(['bonus_pool_already' => 0]);
                }
            }
        }
       
    }
    //线下优惠券
    public function xianixaCouponYeji(){
        if(getcustom('coupon_xianxia_buy')){
            $month_last = date( "Y-m-t");
            $now_date = date('Y-m-d');
            //判断当前天 是不是当月最后一天 
            if($month_last !=$now_date){
                return;
            }
            $month_start = strtotime(date('Y-m-01 00:00:00'));
            $month_end=strtotime(date('Y-m-t 23:59:59')); 
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $sysset) {
                $levellist = Db::name('member_level')->where('aid',$sysset['aid'])->select()->toArray();
                
                foreach ($levellist as $level){
                    //如果不设置业绩，不进行操作
                    if(!$level['yeji_reward_data']){
                         continue;
                    }
                    $yeji_reward_data = json_decode($level['yeji_reward_data'],true);
                   
                    $memberlist = Db::name('member')->where('aid',$sysset['aid'])->where('levelid',$level['id'])->select()->toArray();
                    foreach($memberlist as $member){
                      $res = \app\commons\Member::xianxiaYeji($sysset['aid'],$member,$yeji_reward_data,$month_start,$month_end);
                        if($res['commission'] > 0){
                            \app\commons\Member::addcommission($sysset['aid'], $member['id'],  $member['id'], $res['commission'], '业绩奖');
                        }
                    }
                }
            }
        }
    }
    //平台加权奖励【每月1号发放】
    public function platformAvgBonus($type=0){
        if(getcustom('commission_platform_avg_bonus')){
            $day = intval(date('d'));
            if($day!=1 && $type==0){
                return;
            }
            $monthEnd = strtotime(date('Y-m-01 00:00:00',time()));
            $month = date('m',$monthEnd-86400*2);
            //等级下面设置平台奖励的会员
            $mwhere = [];
            $mwhere[] = ['m.levelid','>',0];
            $mwhere[] = ['l.isdefault','=',0];
            $mwhere[] = ['l.platform_avgbonus_percent','>',0];
            $list = Db::name('member')->alias('m')->join('member_level l','m.levelid=l.id')->where($mwhere)->field('m.aid,m.id,m.levelid,l.platform_avgbonus_percent')->select()->toArray();
            //按平台分组
            $data = [];//有要发放的记录 按aid发放
            foreach ($list as $k=>$v){
                $data[$v['aid']][] = $v;
            }
            foreach ($data as $aid=>$members){
                //平台总业绩
//                $orderMoneyCount = 0 + Db::name('shop_order')->where('aid',$aid)->where('status',3)->sum('totalprice');
                $sumResult = Db::name('shop_order')->where('aid',$aid)->where('status',3)->field("sum(`totalprice`-`refund_money`) as totalamount")->find();
                $orderMoneyCount = $sumResult['totalamount'];
                //奖励多少
                foreach ($members as $k=>$member){
                    $bonusPercent = $member['platform_avgbonus_percent'];
                    //同等级的会员平均佣金
                    $levelCount = Db::name('member')->where('aid',$aid)->where('levelid',$member['levelid'])->count('id');
                    $commission = $orderMoneyCount * $bonusPercent * 0.01 / $levelCount;//平均值
                    if($commission){
                        \app\commons\Member::addcommission($aid,$member['id'],0,$commission,$month.'月份等级业绩达标平台奖励');
                        Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$member['id'],'type'=>'platform','commission'=>$commission,'score'=>0,'remark'=>$month.'月份等级业绩达标平台奖励','createtime'=>time(),'endtime'=>time(),'status'=>1]);
                    }
                }
            }
        }
    }
    //薪资奖励【每月1号发放】type=0脚本执行
    public function levelSalaryBonus($type=0){
        if(getcustom('member_level_salary_bonus')){
            //每个月1号结算
            $day = intval(date('d'));
            if($day!=1 && $type==0){
                return;
            }
            //上个月的业绩
            $monthEnd = strtotime(date('Y-m-01 00:00:00',time()));
            $days = date('t',$monthEnd-86400*2);//上个月多少天
            $month = date('m',$monthEnd-86400*2);
            $monthStart = $monthEnd - 86400*$days;
            //等级下面设置平台奖励的会员
            $mwhere = [];
            $mwhere[] = ['m.levelid','>',0];
            $mwhere[] = ['l.isdefault','=',0];
            $mwhere[] = ['l.salary_bonus_content','<>',''];
            $mwhere[] = Db::raw('l.salary_bonus_content IS NOT NULL');
            $members = Db::name('member')->alias('m')->join('member_level l','m.levelid=l.id')->where($mwhere)->field('m.aid,m.id,m.levelid,l.salary_bonus_content')->select()->toArray();
            foreach ($members as $k=>$member){
                $aid = $member['aid'];
                //直推会员
                $yejiAmount = \app\models\Commission::getMiniTeamCommission($aid,$member['mid'],$monthStart,$monthEnd);
                if($yejiAmount<=0){
                    continue;
                }
                $memberNum = Db::name('member')->where('aid', $aid)->where('pid', $member['mid'])->count('id');
                $bonuslist = json_decode($member['salary_bonus_content'],true);
                //倒叙找符合的第一个
                $newbonuslist = array_reverse($bonuslist);
                foreach ($newbonuslist as $bk=>$bonus){
                    if($memberNum>=$bonus['member_num'] && $yejiAmount>=$bonus['yj_amount'] && $bonus['bonus']>0){
                        //发放达标奖励
                        $commission = $bonus['bonus'];
                        \app\commons\Member::addcommission($aid,$member['id'],0,$commission,$month.'月份业绩达标工资补贴');
                        Db::name('member_commission_record')->insert(['aid'=>$aid,'mid'=>$member['id'],'type'=>'salary','commission'=>$commission,'score'=>0,'remark'=>$month.'月份业绩达标工资补贴','createtime'=>time(),'endtime'=>time(),'status'=>1]);
                        break;
                    }
                }
            }
        }
    }

    //佣金自动转积分，每日0点执行一次
    public function commission_to_score(){
        if(getcustom('commission_to_score')){
            Db::startTrans();
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $sysset){
                //查询今日是否有转换记录
                $today = date('Ymd',time());
                $exit = Db::name('commission_toscore_log')->where('aid',$sysset['aid'])->where('w_day',$today)->find();
                if($exit){
                    continue;
                }
                if($sysset['commission_to_score_time']==1){
                    //设置手动发放的跳过
                    continue;
                }
                $where = [];
                $where[] = ['aid','=',$sysset['aid']];
                $where[] = ['score','>',0];
                //计算全网总佣金
                $commission_total = Db::name('member')->where($where)->field('id,commission,score')->sum('score');
                $res = \app\commons\Member::commission_to_score($sysset,0,0,0,$commission_total);
                $res = \app\commons\Member::commission_to_score2($sysset,0,0,0,$commission_total);
                dump($res);
            }
            Db::commit();
        }
        dump('完成');
    }

    public function day_give(){
        if(getcustom('yx_day_give')){
            //每日赠送
            Db::startTrans();
            $aids = Db::name('admin')->where('status',1)->column('id');
//        $aids = [6];
            if(empty($aids)) return;
            $setlist = Db::name('day_give')->whereIn('aid',$aids)->where('status',1)->select()->toArray();
            foreach($setlist as $set){
                //查询今日是否有记录
                $today = date('Y-m-d');
                $yesterdaytime = strtotime($today);
                $exit = Db::name('day_give_log')->where('aid',$set['aid'])->where('date',$today)->find();
                if($exit){
                    \think\facade\Log::write([
                        'file'=>__FILE__,
                        'fun'=>__FUNCTION__,
                        'line'=>__LINE__,
                        'msg'=>'今日分红数据已存在'
                    ]);
                    continue;
                }
                $config = json_decode($set['config_data'],true);
                if(empty($config)) continue;
                $levelids = array_keys($config);
                $memberList = Db::name('member')
                    ->field('id,aid,pid,path,levelid,createtime,day_give_score_total,day_give_commission_total')
                    ->where('aid',$set['aid'])->whereIn('levelid',$levelids)->where('createtime','<',$yesterdaytime)->select()->toArray();
                foreach ($memberList as $item) {
                    $configLevel = $config[$item['levelid']];
                    //上限
                    if(($item['day_give_score_total'] >= $configLevel['scoreMax'] && $configLevel['scoreMax'] > 0) && ($item['day_give_commission_total'] >= $configLevel['commissionMax'] && $configLevel['commissionMax'] > 0)){
                        \think\facade\Log::write([
                            'file'=>__FILE__,
                            'fun'=>__FUNCTION__,
                            'line'=>__LINE__,
                            'member'=>$item,
                            'msg'=>'已达上限'
                        ]);
                        continue;
                    }
                    //查询昨日之前注册的下级
                    $whereM = [];
                    $whereM[] = ['aid', '=', $set['aid']];
                    $whereM[] = ['createtime','<',$yesterdaytime];
                    $levelidsChild = explode(',',$set['gettj_children']);
                    if(!in_array('-1',$levelidsChild)){
                        $whereM[] = ['levelid','in',$levelidsChild];
                    }
                    if(empty($levelidsChild)){
                        \think\facade\Log::write([
                            'file'=>__FILE__,
                            'fun'=>__FUNCTION__,
                            'line'=>__LINE__,
                            'levelidsChild'=>$levelidsChild,
                            'msg'=>'下级条件为空'
                        ]);
                        continue;
                    }
                    $children1 = Db::name('member')->where($whereM)->where('pid',$item['id'])->column('id');
                    $children2 = [];
                    $children3 = [];
                    if($children1){
                        $children2 = Db::name('member')->where($whereM)->whereIn('pid',$children1)->column('id');
                        if($children2){
                            $children3 = Db::name('member')->where($whereM)->whereIn('pid',$children2)->column('id');
                        }
                    }
                    $score = 0;
                    //scoreMax 0或空无上限
                    if($item['day_give_score_total'] < $configLevel['scoreMax'] || empty($configLevel['scoreMax'])){
                        $score = $configLevel['score'];
                        if($children1) $score += $configLevel['score1'] * count($children1);
                        if($children2) $score += $configLevel['score2'] * count($children2);
                        if($children3) $score += $configLevel['score3'] * count($children3);
                        if($configLevel['scoreMax'] > 0 && $score + $item['day_give_score_total'] > $configLevel['scoreMax']){
                            $score = $configLevel['scoreMax'] - $item['day_give_score_total'];
                        }
                    }
                    $commission = 0;
                    if($item['day_give_commission_total'] < $configLevel['commissionMax'] || empty($configLevel['commissionMax'])){
                        $commission = $configLevel['commission'];
                        if($children1) $commission += $configLevel['commission1'] * count($children1);
                        if($children2) $commission += $configLevel['commission2'] * count($children2);
                        if($children3) $commission += $configLevel['commission3'] * count($children3);
                        if($configLevel['commissionMax'] > 0 && $commission + $item['day_give_commission_total'] > $configLevel['commissionMax']){
                            $commission = $configLevel['commissionMax'] - $item['day_give_commission_total'];
                        }
                    }
                    if($score > 0){
                        \app\commons\Member::addscore($item['aid'],$item['id'],$score,'系统每日奖励');
                        Db::name('member')->where('aid',$set['aid'])->where('id',$item['id'])->inc('day_give_score_total',$score)->update();
                    }
                    if($commission > 0){
                        \app\commons\Member::addcommission($item['aid'],$item['id'],0,$commission,'系统每日奖励');
                        Db::name('member')->where('aid',$set['aid'])->where('id',$item['id'])->inc('day_give_commission_total',$commission)->update();
                    }
                    if($score > 0 || $commission > 0)
                        Db::name('day_give_log')->insert(['aid'=>$item['aid'],'mid'=>$item['id'],'date'=>$today,'score'=>$score,'commission'=>$commission,'createtime'=>time()]);
                }
            }
            Db::commit();
        }
    }
    
    //团队分红奖励
    public function teamyejiJiangli(){
        $is_include_self = getcustom('yx_team_yeji_include_self');
        $is_jicha_custom = getcustom('yx_team_yeji_jicha');
        $pingji_yueji_custom = getcustom('yx_team_yeji_pingji_jinsuo');
        if(getcustom('yx_team_yeji')){
            //->where('1=1')
            $syssetlist = Db::name('admin_set')->select()->toArray();

            foreach($syssetlist as $sysset) {
                $yeji_set = Db::name('team_yeji_set')->where('aid',$sysset['aid'])->find();
                if(!$yeji_set || $yeji_set['status'] == 0){//未开启
                    continue;
                }
                if($yeji_set['jiesuan_type'] == 1){//按月
                    if(date('d') !='01' ||  date('H') !='01'){
                        continue;
                    }
                }elseif($yeji_set['jiesuan_type'] == 3){//按季度 1-1 4-1 7-1 10-1
                  if(!in_array(date('m-d'), ['01-01', '04-01', '07-01', '10-01']) || date('H') != '01'){
                    continue;
                  }
                }elseif($yeji_set['jiesuan_type'] == 2){//按年
                  if(date('m-d') != '01-01' || date('H') != '01'){
                    continue;
                  }
                }
                
                $config = json_decode($yeji_set['config_data'],true);
                if(!$config || empty($config)) continue;
                Db::name('member')->where('aid', $sysset['aid'])
                  ->order('id desc')
                  ->chunk(10, function ($member_list) use ($config, $yeji_set, $sysset,$is_include_self,$is_jicha_custom,$pingji_yueji_custom){
                    foreach($member_list as $key => $member){
                        $mid = $member['id'];
                        $fenhong = 0;
                        if($is_jicha_custom && $yeji_set['is_jicha']){
                          $fenhong= \app\commons\Order::getDownTeamyejiJiangli($member,$yeji_set,$sysset,$config,0);
                        } else{
                            $now_month = date('Y-m',strtotime('-1 month'));
                            $xuni_yeji = 0;  //虚拟业绩
                            $yejiwhere = [];
                            if($yeji_set['jiesuan_type'] == 1){//按月
                                $month_start = strtotime(date('Y-m-01 00:00:00',strtotime('-1 month')));
                                $month_end  = strtotime(date('Y-m-t 23:59:59',strtotime('-1 month')));

                                $yejiwhere[] = ['createtime','between',[$month_start,$month_end]];
                                //虚拟业绩
                                $xuni_yeji = 0 +Db::name('tem_yeji_xuni')->where('aid',$sysset['aid'])->where('mid',$mid)->where('yeji_month',$now_month)->value('yeji');
                            }elseif($yeji_set['jiesuan_type'] == 2){//按年
                                $year_start=strtotime((date('Y')-1) . '-01-01 00:00:00');
                                $year_end=strtotime((date('Y')-1) . '-12-31 23:59:59');
                                $yejiwhere[] = ['createtime','between',[$year_start,$year_end]];
                            }elseif($yeji_set['jiesuan_type'] == 3){//按季度
                                $season_start=strtotime(date('Y-m-01 00:00:00',strtotime('-3 month')));
                                $season_end=strtotime(date('Y-m-t 23:59:59',strtotime('-1 month')));
                                $yejiwhere[] = ['createtime','between',[$season_start,$season_end]];
                            }
                            $yejiwhere[] = ['status','in','1,2,3'];
                            $deep = 999;
                            if($config[$member['levelid']]['levelnum'] > 0) $deep = intval($config[$member['levelid']]['levelnum']);
                            $levelids = [];
                            if($pingji_yueji_custom){
                                //下级统计或越级不算上级业绩
                                if($yeji_set['yueji_pingji_status']){
                                    $nowlevelsort = Db::name('member_level')->where('aid',$member['aid'])->where('id',$member['levelid'])->value('sort');
                                    //查找等级排序小于当前等级的会员
                                    $levelids= Db::name('member_level')->where('aid',$member['aid'])->where('sort','<',$nowlevelsort)->column('id');
                                }
                            }
                            $downmids = \app\commons\Member::getteammids($sysset['aid'],$mid,$deep,$levelids);
                            if($is_include_self){
                                if($yeji_set['include_self']) $downmids[] = $member['id'];
                            }
                            if(!$downmids){
                                \think\facade\Log::write($member['id'].'团队为空');
                                continue;
                            }
                            //下级人数
                            $teamyeji = Db::name('shop_order_goods')->where('aid',$sysset['aid'])->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');//real_totalprice totalprice
                            $totalyeji = $teamyeji + $xuni_yeji;
                            //阶梯设置
                            $jt_range = $config[$member['levelid']]['range'];
                            if(!$jt_range){
                                \think\facade\Log::write($member['id'].'_'.$member['levelid'].'无设置');
                                continue;
                            }
                            $ratio = 0;
                            foreach($jt_range as $rk=> $range){
                                if( $range['start'] <= $totalyeji && $totalyeji < $range['end']){
                                    $ratio = $range['ratio'];
                                }
                            }
                            if($ratio > 0){
                              $fenhong = $ratio / 100 * $totalyeji;
                            }
                        }
                        if($fenhong > 0){
                            \app\commons\Member::addcommission($sysset['aid'],$mid,0,$fenhong,'团队业绩阶梯分红奖',1,'teamyejifenhong');
                        }
                        //平级
                        if($pingji_yueji_custom && $member['path']){
                            $pingji_yueji_data = json_decode($yeji_set['yueji_pingji_data'],true);
                              //查找path
                            $parentList = Db::name('member')->where('id','in',$member['path'])->order(Db::raw('field(id,'.$member['path'].')'))->select()->toArray();
                            if($parentList){
                                $parentList = array_reverse($parentList);
                                $level_lists = Db::name('member_level')->where('aid',$member['aid'])->column('*','id');
                                //当前设置
                                $this_pingjidata = $pingji_yueji_data[$member['levelid']];
                                $parent_arr = [];
                                $is_jinsuo =  $this_pingjidata['jinsuo'];
                                $dai = 1;
                                foreach($parentList as $k=>$parent){
                                    //没级别 紧缩掉
                                    $level_data = $level_lists[$parent['levelid']]??[];
                                    if(!$level_data){
                                        continue;
                                    }
									//开启紧缩后，往上查找平级
                                    if($is_jinsuo){
                                        //如果 平级，且不到2级
                                        if($level_data['id'] != $member['levelid'] || count($parent_arr) >= 2){
                                            continue;
                                        }
                                        $parent_arr[$dai] =$parent;
                                        $dai += 1;
                                    }else{
                                        if($dai <= 2 &&  $level_data['id'] == $member['levelid']){
                                            $parent_arr[$dai] =$parent;
                                        }
                                        $dai +=1;
                                    }
                                }
                                //根据设置的级数 发放奖励
                                foreach($parent_arr as $dai=>$pv){
                                    $commission1_ratio = $this_pingjidata['commission1'];
                                    $commission2_ratio = $this_pingjidata['commission2'];
                                    if($dai ==1){
                                       $commission1  = dd_money_format($totalyeji * $commission1_ratio/100);
                                        if($commission1 > 0){
                                            \app\commons\Member::addcommission($sysset['aid'],$pv['id'],$member['id'],$commission1,'团队业绩阶梯分红一级平级奖',1,'teamyejifenhong');
                                        }
                                    }
                                    if($dai ==2){
                                        $commission2  = dd_money_format($totalyeji * $commission2_ratio/100);
                                        if($commission2 > 0){
                                            \app\commons\Member::addcommission($sysset['aid'],$pv['id'],$member['id'],$commission2,'团队业绩阶梯分红二级平级奖',1,'teamyejifenhong');
                                        }
                                    }
                                }
                            }
                        }
                    }
                  });
            }
        }
    }

    //店铺分销
    public function businessfenxiao(){
        if(getcustom('business_fenxiao')){
            //->where('1=1')
            Db::startTrans();
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $sysset) {
                \app\commons\Business::business_fenxiao($sysset,0);
            }
            Db::commit();
        }
    }
    //分销补贴发放
    public function commission_butie(){
        if(getcustom('commission_butie')){
            //->where('1=1')
            Db::startTrans();
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $sysset) {
                \app\commons\Member::commission_butie($sysset['aid']);
            }
            Db::commit();
            die('stop');
        }
    }

    //统计计算累计积分
    public function count_totalscore(){
        $admins = Db::name('admin')->where('status',1)->field('id')->select()->toArray();
        if($admins){
            foreach($admins as $admin){
                self::deal_countscore($admin['id']);
            }
        }
    }
    private static function deal_countscore($aid){
        //查询未统计的累计积分的会员
        $mids = Db::name('member')->where('aid',$aid)->where('iscountscore',0)->where('totalscore',0)->limit(50)->column('id');
        if($mids && !empty($mids)){
            foreach($mids as $mid){
                //统计计算下他累计积分
                $totalscore = Db::name('member_scorelog')->where('mid',$mid)->where('score','>',0)->where('aid',$aid)->sum('score');
                $totalscore += 0;
                //再次验证是否统计过
                $count = Db::name('member')->where('id',$mid)->where('iscountscore',1)->count('id');
                $count += 0;
                if(!$count){
                    Db::name('member')->where('id',$mid)->update(['totalscore'=>$totalscore,'iscountscore'=>1]);
                }
            }
            self::deal_countscore($aid);
        }
    }
    //商城消费排名分红
    private function paimingFenhong(){
        if(getcustom('shop_paiming_fenhong')){
            Db::startTrans();
            $syssetlist = Db::name('paiming_fenhong_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $sysset) {
				$aid = $sysset['aid'];
				$diff_not_in_id = [];
				//查询昨天的销售额
				$yesterday_start = strtotime(date('Y-m-d 00:00:00', strtotime('-1 day')));
                $yesterday_end = strtotime(date('Y-m-d 23:59:59', strtotime('-1 day')));
				// $yesterday_start = strtotime(date('Y-m-d 00:00:00'));
                // $yesterday_end = strtotime(date('Y-m-d 23:59:59'));
                $totalprice = Db::name('shop_order')->where('collect_time','between',[$yesterday_start,$yesterday_end])->where('status','in',[3])->where('aid',$aid)->sum('totalprice');
                //$over_point_amount = $sysset['over_point_amount'];     
                //$totalprice = 100;
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $check_sale = Db::name('paiming_fenhong_sale')->where('day',$yesterday)->where('aid',$aid)->find();
                if($check_sale){
                    continue;
                }
                //商家每月结算货款        
                if($totalprice <=0){
                    $data_sale = [];
                    $data_sale['aid'] = $aid;
                    $data_sale['totalprice'] = $totalprice;
                    $data_sale['fenhong_amount'] = 0;
                    $data_sale['three_fenhong_amount'] = 0;
                    $data_sale['other_fenhong_amount'] = 0;
                    $data_sale['day'] = $yesterday;
                    $data_sale['createtime'] = time();
                    $id = Db::name('paiming_fenhong_sale')->insertGetId($data_sale);
                    continue;
                }
                //分红金额
                $sale_point_money = round($totalprice * $sysset['sale_ratio'] *0.01,2);
                $three_point_money = round($sale_point_money * $sysset['three_point_ratio'] *0.01,2);
                $other_point_money = $sale_point_money - $three_point_money;
                $point_amount = $sysset['point_amount'];
                writeLog('商城消费分红aid:'.$aid,'paiming_fenhong');  
                writeLog('商城消费分红上期结余:'.$sysset['last_amount'],'paiming_fenhong');  
                $all_amount = $three_point_money + $sysset['last_amount']; 
                $last_amount = 0;//本次剩余金额
                if($sale_point_money > 0){
                    $data_sale = [];
                    $data_sale['aid'] = $aid;
                    $data_sale['totalprice'] = $totalprice;
                    $data_sale['fenhong_amount'] = $sale_point_money;
                    $data_sale['three_fenhong_amount'] = $three_point_money;
                    $data_sale['other_fenhong_amount'] = $other_point_money;
                    $data_sale['day'] = $yesterday;
                    $data_sale['createtime'] = time();
                    $id = Db::name('paiming_fenhong_sale')->insertGetId($data_sale);
                } 
                $yifa_record_ids=[];           
                if($all_amount > 0){             
                    
                    //补欠的分红
                    $diff_arr = Db::name('paiming_fenhong_record_diff')->where('aid',$aid)->where(['status'=>0])->order('createtime asc')->select()->toArray();
                    if($diff_arr && $all_amount >0){
                        foreach($diff_arr as $k=>$v){
                            $record_ids = explode(',',$v['record_ids']);                        
                            $record_diff = Db::name('paiming_fenhong_record')->where('aid',$aid)->where(['status'=>0])->where('id','in',$record_ids)->order('createtime asc')->select()->toArray();
                            if($all_amount <=0){
                                continue;
                            }
                            //业绩充足
                            if($all_amount >= $v['amount']){                            
                                foreach($record_diff as $krd=>$vrd){
                                    $diff_not_in_id[] = $vrd['id'];
                                    $up_data = [];
                                    $diff_bu = $vrd['max_amount'] - $vrd['already_amount'];
                                    $all_amount = $all_amount - $diff_bu;
                                    $up_data['already_amount'] = $vrd['max_amount'];
                                    $up_data['status'] = 1;   
                                    //修改分红排位点
                                    Db::name('paiming_fenhong_record')->where('aid',$aid)->where('id',$vrd['id'])->update($up_data);                                
                                    //分红记录
                                    \app\commons\Member::addpaimingfenhong($aid,$vrd['mid'],$diff_bu,'商城消费分红',0,$vrd['id']);                            
                                }
                                Db::name('paiming_fenhong_record_diff')->where('aid',$aid)->where('id',$v['id'])->update(['status'=>1]);
                            }else{                            
                                $diff_point_num = count($record_ids);
                                $diff_point_amount = floor($all_amount*100/$diff_point_num)*0.01;
                                $diff_money_bu = 0;
                                foreach($record_diff as $krd=>$vrd){
                                    $diff_not_in_id[] = $vrd['id'];
                                    $up_data = [];
                                    $diff_bu = $vrd['max_amount'] - $vrd['already_amount'];
                                    if($diff_bu > $diff_point_amount){
                                        $add_money = $diff_point_amount;
                                        $up_data['already_amount'] = $vrd['already_amount']+$add_money;
                                        $diff_money_record = $vrd['max_amount'] - $vrd['already_amount']-$add_money;
                                        $diff_id[] = $vrd['id'];
                                        $diff_money_bu +=$diff_money_record;//累计差值金额
                                        $all_amount = $all_amount - $add_money;
                                    }else{
                                        //足额的直接扣除
                                        $add_money = $diff_bu;
                                        $use_money = $diff_point_amount - $diff_bu;
                                        $all_amount = $all_amount - $add_money;
                                        $up_data['already_amount'] = $vrd['already_amount']+$add_money;
                                        $up_data['status'] = 1;                        
                                    }
                                    //修改分红排位点
                                    Db::name('paiming_fenhong_record')->where('aid',$aid)->where('id',$vrd['id'])->update($up_data);
                                    //分红记录
                                    \app\commons\Member::addpaimingfenhong($aid,$vrd['mid'],$add_money,'商城消费分红',0,$vrd['id']);                           
                                }
                                if($diff_money_bu > 0){
                                    Db::name('paiming_fenhong_record_diff')->where('aid',$aid)->where('id',$v['id'])->update(['amount'=>$diff_money_bu,'record_ids' => implode(',',$diff_id)]);
                                }else{
                                    Db::name('paiming_fenhong_record_diff')->where('aid',$aid)->where('id',$v['id'])->update(['status'=>1]);
                                }
                                
                            }
                        }
                    }   
                    $where_r[] = ['aid','=',$aid];
                    if($diff_not_in_id){
                        $diff_not_in_id = array_unique($diff_not_in_id);
                        $where_r[]=['id','not in',$diff_not_in_id];
                        $yifa_record_ids = array_merge($yifa_record_ids,$diff_not_in_id);
                        writeLog('商城消费分红第一批补差额发放人员:'.implode(',',$diff_not_in_id),'paiming_fenhong');  
                    }  
                    
                                            
                    $point_num = floor($all_amount/$point_amount);
                    //获取真实名额 前面3个不足3个算3个，超出3个以后5个算3个，8个算6个，10个算9个，…16个算15个…32个算30个，以此类推
                    if($point_num>3){
                        $real_point_num = floor($point_num/3) * 3;
                    }else{
                        $real_point_num = 3;
                        $point_amount = floor($all_amount*100/3)*0.01;
                    }                        
                    //查询换算当前分红人数
                    $record_count = Db::name('paiming_fenhong_record')->where($where_r)->where(['status'=>0])->count();                    
                    //名额不足跳过 业绩不足跳过
                    if($record_count >3 && $all_amount >0.1){
                        writeLog('商城消费分红第一批正常发放人员数量:'.$real_point_num,'paiming_fenhong');  
                        $record = Db::name('paiming_fenhong_record')->where($where_r)->where(['status'=>0])->order('createtime asc')->limit($real_point_num)->select()->toArray();
                        $shengyu_use_money = 0;
                        
                        $diff_id = [];
                        $diff_money = 0;
                        $fafang_money = 0;
                        foreach($record as $k=>$v){
                            $up_data = [];
                            $diff = $v['max_amount'] - $v['already_amount'];
                            $yifa_record_ids[] = $v['id'];
                            if($diff > $point_amount){
                                $add_money = $point_amount;
                                $up_data['already_amount'] = $v['already_amount']+$add_money;
                                $diff_money_record = $v['max_amount'] - $v['already_amount']-$point_amount;
                                $diff_id[] = $v['id'];                        
                                $diff_money +=$diff_money_record;
                            }else{
                                $add_money = $diff;
                                $use_money = $point_amount - $diff;
                                $shengyu_use_money +=$use_money;
                                $up_data['already_amount'] = $v['already_amount']+$add_money;
                                $up_data['status'] = 1;                        
                            }
                            $fafang_money +=$add_money;
                            //修改分红排位点
                            Db::name('paiming_fenhong_record')->where('aid',$aid)->where('id',$v['id'])->update($up_data);
                            //分红记录
                            \app\commons\Member::addpaimingfenhong($aid,$v['mid'],$add_money,'商城消费分红',0,$v['id']);
                        }
                        //今日不足金额累计到第二天补齐
                        if(count($diff_id) > 0){
                            $data_record = [];
                            $data_record['aid'] = $aid;
                            $data_record['record_ids'] = implode(',',$diff_id);
                            $data_record['amount'] = $diff_money;
                            $data_record['date'] = $yesterday;
                            $data_record['status'] = 0;
                            $data_record['createtime'] = time();
                            $id = Db::name('paiming_fenhong_record_diff')->insertGetId($data_record);
                        }
                        //剩余分红累计
                        $last_amount = round($all_amount - $fafang_money,2);
                        //剩余资金充足继续发放
                        $point_num_yu = floor($last_amount/$point_amount);
                        if($point_num_yu >= 3){
                            $real_point_num = floor($point_num/3) * 3;
                            $where_yu[] = ['aid','=',$aid];
                            if($yifa_record_ids){
                                $where_yu[]=['id','not in',$yifa_record_ids];                               
                            }
                            $record_yu = Db::name('paiming_fenhong_record')->where($where_yu)->where(['status'=>0])->order('createtime asc')->limit($real_point_num)->select()->toArray();
                            $diff_id = [];
                            $diff_money = 0;
                            $fafang_money = 0;
                            foreach($record_yu as $k=>$v){                                          
                                $up_data = [];
                                $diff = $v['max_amount'] - $v['already_amount'];                                
                                if($diff < $point_amount){
                                    $yifa_record_ids[] = $v['id'];
                                    $add_money = $diff;
                                    $use_money = $point_amount - $diff;
                                    $shengyu_use_money +=$use_money;
                                    $up_data['already_amount'] = $v['already_amount']+$add_money;
                                    $up_data['status'] = 1;  
                                    $fafang_money +=$add_money;
                                    //修改分红排位点
                                    Db::name('paiming_fenhong_record')->where('aid',$aid)->where('id',$v['id'])->update($up_data);
                                    //分红记录
                                    \app\commons\Member::addpaimingfenhong($aid,$v['mid'],$add_money,'商城消费分红',0,$v['id']);                     
                                }                
                                
                            }
                            writeLog('商城消费分红剩余金额 再次发放:'.$fafang_money,'paiming_fenhong'); 
                            $last_amount = round($last_amount - $fafang_money,2);
                        }                 
                    }else{
                        //累计当前分红
                        $last_amount = $all_amount;               
                    }   
                        
                    writeLog('商城消费分红剩余第一批金额:'.$last_amount,'paiming_fenhong'); 
                    writeLog('商城消费分红第一批已发总列表:'.implode(',',$yifa_record_ids),'paiming_fenhong');         
                }
                      
                //其它金额平均分配给当天剩余未分配的人。
                if($other_point_money){
                    //查询换算当前分红人数
                    $where_other[] = ['aid','=',$aid];
                    if($yifa_record_ids){
                        $yifa_record_ids = array_unique($yifa_record_ids);
                        $where_other[]=['id','not in',$yifa_record_ids];
                    }                
                    $other_record_count = Db::name('paiming_fenhong_record')->where($where_other)->where(['status'=>0])->count();
                    $fafang_money = 0;
                    if($other_record_count>0){
                        $other_point_amount = floor($other_point_money*100/$other_record_count)*0.01;
                        $record = Db::name('paiming_fenhong_record')->where($where_other)->where(['status'=>0])->order('createtime asc')->select()->toArray();
                        
                        foreach($record as $k=>$v){
                            $up_data = [];
                            $diff = $v['max_amount'] - $v['already_amount'];
                            $yifa_record_ids[] = $v['id'];
                            if($diff > $other_point_amount){
                                $add_money = $other_point_amount;
                                $up_data['already_amount'] = $v['already_amount']+$add_money;
                            }else{
                                $add_money = $diff;
                                $use_money = $other_point_amount - $diff;
                                //$shengyu_use_money +=$use_money;
                                $up_data['already_amount'] = $v['already_amount']+$add_money;
                                $up_data['status'] = 1;                        
                            }
                            $fafang_money +=$add_money;
                            //修改分红排位点
                            Db::name('paiming_fenhong_record')->where('aid',$aid)->where('id',$v['id'])->update($up_data);
                            //分红记录
                            \app\commons\Member::addpaimingfenhong($aid,$v['mid'],$add_money,'商城消费分红',0,$v['id']);
                        }
                        
                    }
                    //剩余分红累计
                    $other_last_amount = round($other_point_money - $fafang_money,2);
                    writeLog('商城消费分红第二批剩余金额:'.$other_last_amount,'paiming_fenhong'); 
                    $last_amount +=$other_last_amount;
                
                }
                if($last_amount){
                    writeLog('商城消费分红剩余金额:'.$last_amount,'paiming_fenhong'); 
                    Db::name('paiming_fenhong_set')->where('aid',$aid)->update(['last_amount'=>$last_amount]);
                }
            }
            Db::commit();
        }
    }

    //释放通证
    public function release_tongzheng(){
        if(getcustom('product_givetongzheng')){
            //->where('1=1')
            Db::startTrans();
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $sysset) {
                \app\commons\Member::release_tongzheng($sysset);
            }
            Db::commit();
            die('stop');
        }
    }

    //满人抽奖活动开奖
    public function manren_choujiang(){
        if(getcustom('yx_choujiang_manren')){
            Db::startTrans();
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $sysset) {
                \app\commons\Choujiang::kaijiang($sysset);
            }
            Db::commit();
        }
    }
    //甘尔定制奖金池
    public function ganer_prize_pool(){
        if(getcustom('ganer_fenxiao')){
            Db::startTrans();
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $sysset) {
                $set = Db::name('prize_pool_set')->where('aid',$sysset['aid'])->find();
                if(!$set){
                    //未设置奖金池
                    continue;
                }
                if($set['send_time']==0){
                    //手动发放
                    continue;
                }
                if($set['send_time']==1){
                    //每月发放一次
                    if(date('d')!='01'){
                        continue;
                    }
                    $send_time = strtotime(date('Y-m-01 00:00:00'));
                    $exit = Db::name('prize_pool_send_log')
                        ->where('aid','=',$sysset['aid'])
                        ->where('createtime','>=',$send_time)
                        ->where('send_type','=',1)
                        ->find();
                    if($exit){
                        //已经发放过了
                        continue;
                    }
                }
                if($set['send_time']==2){
                    //每年发放一次
                    if(date('m-d')!='01-01'){
                        continue;
                    }
                    $send_time = strtotime(date('Y-01-01 00:00:00'));
                    $exit = Db::name('prize_pool_send_log')
                        ->where('aid','=',$sysset['aid'])
                        ->where('createtime','>=',$send_time)
                        ->where('send_type','=',1)
                        ->find();
                    if($exit){
                        //已经发放过了
                        continue;
                    }
                }
                $levelids = json_decode($set['levelids'],true);
                \app\commons\Fenxiao::send_prize_pool($levelids,$set['send_bili'],$sysset['aid'],1);
            }
            Db::commit();
        }
    }
	
    //签到奖金池开奖
    protected function sign_kaijiang(){
        $date = input('param.date','');
        if(getcustom('sign_pay_bonus')){
            $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
			$now_time = time();
            foreach($syssetlist as $sysset) {
				Db::startTrans();
				$aid = $sysset['aid'];
				//查询昨天的奖金池
                if($date){
                    $yesterday = $date;
                }else{
                    $yesterday = date('Ymd',$now_time-86400);
                }
				$info = Db::name('sign_bonus')->where('aid',$aid)->where('date',$yesterday)->where('status',0)->find();
				\app\commons\SignBonus::kaijiang($info);
				Db::commit();
            }
        }
    }

    //自动释放绿色积分
    public function release_green_score(){
        if(getcustom('consumer_value_add')){
            Db::startTrans();
            $syssetlist = Db::name('consumer_set')->where('1=1')->select()->toArray();
            $now_time = time();
            foreach($syssetlist as $sysset) {
                if($sysset['green_score_price']>=$sysset['max_price']){
                    \app\commons\Member::release_green_score($sysset);
                }
            }
            Db::commit();
        }
    }
    
    //商品柜 补货提醒
    public function pickupDeviceAddstockRemind(){
        if(getcustom('product_pickup_device')){
            $syssetlist= Db::name('product_pickup_device_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $key=>$sysset ){
                $aid = $sysset['aid'];
                $remind_type = explode(',',$sysset['remind_type']);
                $remind_pinlv = explode(',',$sysset['remind_pinlv']);
                //状态未开启， 未设置补货方式， 未开启该方式，未设置时间
                if(!$sysset['add_stock_remind'] || !$remind_type || !in_array(3,$remind_pinlv) ||!$sysset['remind_time']){
                    continue;
                }
                if(date('H:i')==$sysset['remind_time']){
                    $send_list = Db::name('product_pickup_device_goods')
                        ->where('aid',$aid)
                        ->whereColumn('stock','>','real_stock')
                        ->group('device_id')
                        ->field('device_id')
                        ->select()->toArray();
                    foreach($send_list as $key=>$val){
                        $device =Db::name('product_pickup_device')->where('id',$val['device_id'])->field('name,address,uid')->find();
                        //发送消息模板
                        if(in_array('tmpl',$remind_type)){
                            $tmplcontent = [];
                            $tempconNew = [];
                            $tempconNew['thing11'] = $device['name'];//设备名称
                            $tempconNew['thing12'] = $device['address'];//地点
                            $send_uid = explode(',',$device['uid']);
                            \app\commons\Wechat::sendhttmplByUids($aid,$send_uid,'tmpl_device_addstock_remind',$tempconNew,m_url('/pagesB/admin/pickupdeviceaddstock',$aid),0);
                        }
                        //发送短信
                        if(in_array('sms',$remind_type)){
                            $tel_list = Db::name('admin_user')->alias('au')
                                ->join('member m','m.id = au.mid')
                                ->where('au.aid',$aid)->where('au.bid',$sysset['bid'])
                                ->where('au.id','in',$device['uid'])
                                ->column('tel');
                            foreach($tel_list as $tel){
                                \app\commons\Sms::send($aid,$tel,'tmpl_device_addstock_remind',['address'=>$device['address'],'name' => $device['name']]);
                            }
                        }
                    }
                }
            }
        }
    }
    
    public function queueMultiTeamOut(){
        if(getcustom('yx_queue_free_multi_team')){
            //一小时执行一次 不是1点不执行
            $hour =  intval(date('H'));
            if($hour !=1 && $hour !=9){
                return;
            }
            $syssetlist= Db::name('queue_free_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $key=>$sysset ){
                $aid = $sysset['aid'];
                $bid = $sysset['bid'];
                $set = Db::name('queue_free_set')->where('aid',$aid)->where('bid',0)->find();
                if(!$set)continue;
                $days = $set['queue_multi_team_remind_days']??1;
                if ($set['queue_multi_team_repeat_datetype'] == 2) {//月底
                    $month_start = strtotime(date('Y-m-01 00:00:01'));
                    $month_end = strtotime(date('Y-m-t 23:59:59'));
                    if($hour ==1) {//1点执行 月初或者月底 退出操作
                        //当前天 不等于当月最后一天
                        if (date('t') != date('d')) continue;
                    }
                    if($hour ==9) { //9点执行  发送通知，提前n天
                        $remind_date = strtotime (date ('Y-m-t') . ' -'.$days.' day');
                        if (date('t') != date('d',$remind_date)) continue;
                    }
                } else { //月初 统计上个月
                    $month_start = strtotime(date('Y-m-01 00:00:01') . ' -1 month');
                    $month_end = strtotime(date('Y-m-01 23:59:59') . ' -1 day');
                    if($hour ==1) {//1点执行 月初或者月底 退出操作
                        $day = intval(date('d'));
                        if ($day != 1) continue;
                    }
                    if($hour ==9) { //9点执行  发送通知，提前n天
                        $days = $days-1;
                        $remind_date =strtotime(date('Y-m-t') . ' -'.$days.' day');
                        if (date('d') != date('d',$remind_date)) continue;
                    }
                }
                //正在排队的用户
                $where = [];
                $where[] = ['qf.aid','=',$aid];
                if($set['queue_type_business'] != 1){
                    $where[] = ['qf.bid','=',$bid];
                }
                $where[] = ['qf.status','=',0];
                $where[] = ['qf.quit_queue','=',0];
                $queue_member = Db::name('queue_free')->alias('qf')
                    ->join('member m','m.id = qf.mid')
                    ->where($where)
                    ->group('qf.mid')
                    ->field('qf.mid,m.tel')
                    ->select()->toArray();
                foreach($queue_member as $key=>$member){
                    //统计买单+商城的金额 月份之内
                    $member_total_money  = 0;
                    $ogwhere = [];
                    $ogwhere[] = ['aid','=',$aid];
                    if($set['queue_type_business'] != 1){
                        $ogwhere[] = ['bid','=',$bid];
                    }
                    $ogwhere[] = ['status','in',[1,2,3]];
                    $ogwhere[] = ['mid','=',$member['mid']];
                    $ogwhere[] = ['paytime','between',[$month_start,$month_end]];

                    $oglist = Db::name('shop_order_goods')->where($ogwhere)->select()->toArray();
                    foreach ($oglist as $og){
                        $product = Db::name('shop_product')->where('id',$og['proid'])->where('aid',$aid)->where('bid',$bid)->find();
                        if($product['queue_free_status'] == 1){
                            $member_total_money += $og['real_totalprice'];
                        }
                    }

                    //买单
                    $maidanwhere = [];
                    $maidanwhere[] = ['aid','=',$aid];
                    $maidanwhere[] = ['mid','=',$member['mid']];;
                    if($set['queue_type_business'] != 1){
                        $maidanwhere[] = ['bid','=',$bid];
                    }
                    $maidan_money = 0+Db::name('maidan_order')->where($maidanwhere)->where('paytime','between',[$month_start,$month_end])->sum('paymoney');
                    $member_total_money +=$maidan_money;

                    //复购金额 小于 设置金额，该会员对应排队取消
                    if($member_total_money < $set['queue_multi_team_repeat_money'] && $set['queue_multi_team_repeat_money'] > 0){
                        if($hour ==1) {//1点执行 月初或者月底 退出操作 
                            $q_whehre = [];
                            $q_whehre[] = ['aid', '=', $aid];
                            $q_whehre[] = ['status', '=', 0];
                            $q_whehre[] = ['quit_queue', '=', 0];
                            if ($set['queue_type_business'] != 1) {
                                $q_whehre[] = ['bid', '=', $bid];
                            }
                            $q_whehre[] = ['mid', '=', $member['mid']];
                            Db::name('queue_free')->where($q_whehre)->update(['quit_queue' => 1]);
                        }
                        if($hour ==9){//发送通知
                            if ($set['queue_multi_team_repeat_datetype'] == 2) {//月底
                                $quit_date = date('Y-m-t');
                            }else{
                                $quit_date =date('Y-m-d',strtotime(date('Y-m-t') . ' +1 day'));
                            }
                            //短信 
                            $tel = $member['tel'];
                            \app\commons\Sms::send($aid,$tel,'tmpl_queue_free_before_quit',['date' => $quit_date]);
                            //模板消息  
                            $tmplcontent = [];
                            $tmplcontentnew = [];
                            $tmplcontentnew['time5'] = $quit_date;
                            $tmplcontentnew['thing3'] = '消费返红包';
                            \app\commons\Wechat::sendwxtmpl($aid,$member['mid'],'tmpl_queue_free_before_quit',$tmplcontentnew,'',$tmplcontent);
                        }
                    }
                }

            }
        }
    }

    //自动关闭积分转赠订单
    public function closeScoreTransferSxfOrder(){
        if(getcustom('score_transfer_sxf')) {
            $data = Db::name('score_transfer_order')->where('status', 0)->select()->toArray();
            foreach ($data as $key => $value) {
                $closeTime = Db::name('admin_set')->where('aid', $value['aid'])->value('autoclose_score_transfer');
                if ($closeTime) {
                    if ($value['createtime'] + $closeTime * 60 > time()) {
                        continue;
                    }
                    //关闭订单
                    $res = Db::name('score_transfer_order')->where('id', $value['id'])->where('aid', $value['aid'])->where('mid', $value['mid'])->update(['status' => 4]);
                    if ($res) {
                        //返还积分
                        \app\commons\Member::addscore($value['aid'], $value['mid'], $value['score_num'], '积分转赠返还');
                    }
                }
            }
        }
    }

    //根据价格倍数自动释放绿色积分
    public function green_score_withdraw(){
        if(getcustom('consumer_value_add')){
            Db::startTrans();
            $syssetlist = Db::name('consumer_set')->where('1=1')->select()->toArray();
            foreach($syssetlist as $sysset) {
                $res = \app\customs\GreenScore::autoWithdraw($sysset['aid'],$sysset);
//                dump($res);
            }
//            die('stop');
            Db::commit();
        }
    }

    //鱼塘 到达时间
    public function fishpond(){
        if(getcustom('extend_fish_pond')){
            Db::startTrans();
            //关闭订单 0：未支付 1：已支付(使用中)
            $orderList = Db::name('fishpond_order')->where('status','in',[0,1])->select()->toArray();
            $autocloseArr = [];
            foreach($orderList as $order){
                $aid = $order['aid'];
                $mid = $order['mid'];
                $orderid = intval($order['id']);

                //未支付
                if($order['status'] == 0){
                    if(!$autocloseArr[$order['aid']]){
                        $autocloseArr[$order['aid']] = Db::name('fishpond_sysset')->where('aid',$order['aid'])->value('autoclose');
                    }

                    if($order['createtime'] + $autocloseArr[$order['aid']]*60 > time()) continue;

                    //关闭订单
                    Db::name('fishpond_order')
                        ->where('id',$orderid)
                        ->where('aid',$aid)
                        ->where('mid',$mid)
                        ->update([
                            'status' => 4
                        ]);

                    //释放钓点
                    Db::name('fishpond_basan')
                        ->where('aid',$aid)
                        ->where('orderid',$orderid)
                        ->update([
                            'orderid' => '',
                            'ordernum' => '',
                            'starttime'  => '',
                            'endtime' => '',
                            'status' => 0 //未使用
                        ]);
                }elseif($order['status'] == 1){ //已支付(使用中)

                    //判断是否到达使用时间
                    if($order['endtime'] < time()) {

                        Db::name('fishpond_order')
                            ->where('id',$orderid)
                            ->where('aid',$aid)
                            ->update([
                                'status' => 3 //已完成
                            ]);

                        //释放钓点
                        Db::name('fishpond_basan')
                            ->where('aid',$aid)
                            ->where('orderid',$orderid)
                            ->update([
                                'orderid' => '',
                                'ordernum' => '',
                                'starttime'  => '',
                                'endtime' => '',
                                'status' => 0 //未使用
                            ]);

                        //发放佣金
                        $rs = \app\commons\Order::collect($order,'fishpond');
                        if($rs['status'] == 0) continue;

                        //发送模板消息
                        $member = Db::name('member')->field('nickname,wxopenid')->where('id',$order['mid'])->find();
                        if($member['wxopenid']){
                            //模板消息
                            $tmplcontent = [];
                            $tmplcontentnew = [];
                            $tmplcontentnew['thing1'] = $order['proname'];
                            $tmplcontentnew['character_string2'] = $order['ordernum'];
                            $tmplcontentnew['thing6'] = $member['nickname']?:$order['linkman'];
                            $tmplcontentnew['time7'] = date('Y-m-d H:i',$order['endtime']);
                            $tourl = 'pagesB/fishpond/orderdetail?id='.$order['id'];
                            \app\commons\Wechat::sendwxtmpl($order['aid'],$order['mid'],'tmpl_fishpond_expire',$tmplcontentnew,$tourl,$tmplcontent);
                        }
                    }
                }
            }
            Db::commit();
        }
    }

    //预约开始前通知
    private static function sendnotice_time(){
        if (getcustom('yuyue_before_starting')) {
            $admin = Db::name('admin')->where('status',1)->field('id')->select()->toArray();
            if($admin){
                $time  = time();
                $stime = strtotime(date('Y-m-d H:i',$time));
                foreach($admin as $av){
                    $aid = $av['id'];
                    $orderlist = Db::name('yuyue_order')->where('aid',$av['id'])->where('status',1)->where('begintime','>=',$time)->whereRaw('sendnotice_time = 0 or (sendnotice_time>0 && sendnotice_time < '.$stime.')')->field('id,aid,bid,mid,ordernum,title,begintime,worker_id,sendnotice_time,yy_time,linkman,fwtype,title,linkman,tel,fwbid,area,area2,address')->select()->toArray();
                    if($orderlist){
                        foreach($orderlist as $order){
                            //现在与开始的时间差
                            $timecha = $order['begintime']-time();
                            //查询通知设置
                            $yyset = Db::name('yuyue_set')->where('aid',$order['aid'])->where('bid',$order['bid'])->field('serverbefore_notice,noticedata')->find();
                            if($yyset && $yyset['serverbefore_notice']>0 && !empty($yyset['noticedata'])){
                                $sendmember = $sendworker = false;
                                if($yyset['serverbefore_notice'] == 3){
                                    $sendmember = $sendworker = true;
                                }else if($yyset['serverbefore_notice'] == 1){
                                    if(!$order['mid']) continue;
                                    $sendmember = true;
                                }else if($yyset['serverbefore_notice'] == 2){
                                    if(!$order['worker_id']) continue;
                                    $sendworker = true;
                                }

                                $sendnotice = false;
                                $noticedata = json_decode($yyset['noticedata']);
                                foreach($noticedata as $nv){
                                    //时间差在一分钟内发送消息
                                    $cha = $timecha-$nv*60;
                                    if($cha<=60 && $cha >=-10){
                                        $sendnotice = true;
                                        break;
                                    }
                                }
                                unset($nv);
                                $begintime = date("Y-m-d H:i",$begintime);
                                //发送消息
                                if($sendnotice && ($sendmember || $sendworker)){
                                    //预约地址
                                    $yyaddress = '';
                                    //到店
                                    if($order['fwtype'] == 1){
                                        if($order['bid']!=0){
                                            $business = Db::name('business')->where('id',$order['bid'])->field('id,aid,cid,name,logo,tel,address,sales,longitude,latitude,start_hours,end_hours,start_hours2,end_hours2,start_hours3,end_hours3,end_buy_status,invoice,invoice_type,province,city,district')->find();
                                            if($business){
                                                $yyaddress .= $business['province'].$business['city'].$business['district'].$business['address'];
                                            }
                                            
                                        }else{
                                            $set = Db::name('admin_set')->where('aid',$order['aid'])->field('id,name,logo,desc,tel,province,city,district,address')->find();
                                            if($set){
                                                $yyaddress .= $set['province'].$set['city'].$set['district'].$set['address'];
                                            }
                                        }
                                    //上门
                                    }else if($order['fwtype'] == 2){
                                        $yyaddress .= $order['area'].$order['address'];
                                    //到商家
                                    }else if($order['fwtype'] == 3){
                                        $fwbusines = Db::name('business')->where('id',$order['fwbid'])->where('status',1)->where('aid',aid)->field('id,aid,name,logo,tel,linkman,linktel,province,city,district,address,latitude,longitude')->find();
                                        if($fwbusines){
                                            $yyaddress .= $fwbusines['province'].$fwbusines['city'].$fwbusines['district'].$fwbusines['address'];
                                        }
                                    }
                                    $begintime = $order['begintime']?date("Y年m月d日 H:i",$order['begintime']):'无';
                                    if($sendmember){
                                        //公众号
                                        $tmplcontentNew = [];
                                        $tmplcontentNew['thing16'] = $order['title']?$order['title']:'无';//预约项目
                                        $tmplcontentNew['time20']  = $begintime;//预约时间
                                        $tmplcontentNew['thing24'] = $yyaddress?$yyaddress:'无';//预约地址
                                        $tmplcontentNew['thing19'] = $order['linkman']?$order['linkman']:'无';//预约人
                                        $tmplcontentNew['time23']  = $order['begintime']?date("Y-m-d H:i",$order['begintime']):'无';//预约时间
                                        $rs = \app\commons\Wechat::sendtmpl($aid,$order['mid'],'tmpl_yuyue_before_starting',[],m_url('activity/yuyue/orderlist', $aid),$tmplcontentNew);

                                        //小程序
                                        $tmplcontent = [];
                                        $tmplcontent['thing1']   = $order['title']?$order['title']:'无';//预约项目
                                        $tmplcontent['time3']    = $begintime;//预约时间
                                        $tmplcontent['thing4']   = $yyaddress?$yyaddress:'无';//预约地址
                                        $tmplcontent['thing11']  = $order['linkman']?$order['linkman']:'无';//预约人
                                        $tmplcontent['time12']   = $order['begintime']?date("Y-m-d H:i",$order['begintime']):'无';//预约时间
                                        \app\commons\Wechat::sendwxtmpl($aid,$order['mid'],'tmpl_yuyue_before_starting','','activity/yuyue/orderlist',$tmplcontent);
                                        //短信通知
                                        $rs = \app\commons\Sms::send($aid,$member['tel']?$member['tel']:$order['tel'],'tmpl_yuyue_before_starting',['title'=>$order['title'],'ordernum'=>$order['ordernum'],'begintime'=>$begintime]);
                                    }
                                    if($sendworker){
                                        //查询服务人员绑定的用户
                                        $worker = Db::name('yuyue_worker')->where('id',$order['worker_id'])->where('aid',$order['aid'])->where('bid',$order['bid'])->field('id,aid,mid,tel')->find();
                                        if($worker && $worker['mid']){
                                            $wmember = Db::name('member')->where('id',$worker['mid'])->field('id,tel,wxopenid,mpopenid')->find();
                                            if($wmember){
                                                //公众号
		                                        $tmplcontentNew = [];
		                                        $tmplcontentNew['thing16'] = $order['title']?$order['title']:'无';//预约项目
		                                        $tmplcontentNew['time20']  = $begintime;//预约时间
		                                        $tmplcontentNew['thing24'] = $yyaddress?$yyaddress:'无';//预约地址
		                                        $tmplcontentNew['thing19'] = $order['linkman']?$order['linkman']:'无';//预约人
		                                        $tmplcontentNew['time23']  = $order['begintime']?date("Y-m-d H:i",$order['begintime']):'无';//预约时间
		                                        $rs = \app\commons\Wechat::sendtmpl($aid,$worker['mid'],'tmpl_yuyue_before_starting',[],m_url('activity/yuyue/jdorderlist', $aid),$tmplcontentNew);

                                                //小程序
                                                $tmplcontent = [];
                                                $tmplcontent['thing1']   = $order['title']?$order['title']:'无';//预约项目
                                                $tmplcontent['time3']    = $begintime;//预约时间
                                                $tmplcontent['thing4']   = $yyaddress?$yyaddress:'无';//预约地址
                                                $tmplcontent['thing11']  = $order['linkman']?$order['linkman']:'无';//预约人
                                                $tmplcontent['time12']   = $order['begintime']?date("Y-m-d H:i",$order['begintime']):'无';//预约时间
                                                \app\commons\Wechat::sendwxtmpl($aid,$worker['mid'],'tmpl_yuyue_before_starting','','activity/yuyue/jdorderlist',$tmplcontent);
                                                //短信通知
                                                $rs = \app\commons\Sms::send($aid,$worker['tel']?$worker['tel']:$wmember['tel'],'tmpl_yuyue_before_starting',['title'=>$order['title'],'ordernum'=>$order['ordernum'],'begintime'=>$begintime]);
                                            }
                                        }
                                    }
                                    //更新发送时间
                                    Db::name('yuyue_order')->where('id',$order['id'])->update(['sendnotice_time'=>$stime]);
                                }

                            }
                        }
                        unset($order);
                    }
                    
                }
                unset($av);
            }
        }
    }

    //时间段自动完成
    private static function datetype1_autoendorder(){
        if (getcustom('yuyue_datetype1_autoendorder')) {
            $admin = Db::name('admin')->where('status',1)->field('id')->select()->toArray();
            if($admin){
                foreach($admin as $av){
                    //筛选是时间段类型，且是自动完成类型，且预约完成时间大于0 且预约完成时间小等于现在时间
                    $orderlist = Db::name('yuyue_order')->where('aid',$av['id'])->where('status',2)->where('datetype',1)->where('datetype1_autoendorder',1)->where('yyendtime','>',0)->where('yyendtime','<=',time())->select()->toArray();
                    if($orderlist){
                        foreach($orderlist as $order){
                            if( $order['paytypeid']==4){
                                continue;
                            }
                            if($order['balance_price'] > 0 && $order['balance_pay_status']!=1){
                                continue;
                            }
                            if(getcustom('yuyue_apply') && $order['addmoney']>0 && $order['addmoneyStatus']!=1){
                                continue;
                            }

                            $psorder = Db::name('yuyue_worker_order')->where('id',$order['worker_orderid'])->where('worker_id',$order['worker_id'])->field('id,bid,worker_id,aid,status,ticheng')->find();
                            if(!$psorder || $psorder['status']!=2){
                                continue;
                            }

                            $rs = \app\commons\Order::collect($order,'yuyue');
                            if($rs['status'] == 0) continue;

                            Db::name('yuyue_worker')->where('id',$order['worker_id'])->inc('totalnum')->update();
                            Db::name('yuyue_order')->where('id',$order['id'])->update(['status'=>3,'collect_time'=>time()]);
                            Db::name('yuyue_worker_order')->where('id',$psorder['id'])->update(['status'=>3,'endtime'=>time()]);
                            \app\commons\YuyueWorker::addmoney($av['id'],$psorder['bid'],$order['worker_id'],$psorder['ticheng'],'服务提成');
                        }
                        unset($order);
                    }
                    
                }
                unset($av);
            }
        }
    }
	//自动关闭酒店订单
    public function closeHotelorder(){
        if(getcustom('hotel')) {
            $data = Db::name('hotel_order')->where('status',0)->select()->toArray();
            foreach ($data as $key => $value) {
                $closeTime = Db::name('hotel_set')->where('aid', $value['aid'])->value('autoclose');
                if ($closeTime) {
                    if ($value['createtime'] + $closeTime * 60 > time()) {
                        continue;
                    }
					if($value['paytypeid']!=1 && $value['use_money']>0){
						/*$text = \app\models\Hotel::gettext($value['aid']);
						\app\commons\Member::addmoney($value['aid'],$value['mid'],$value['use_money'],$text['酒店'].'订单超时未付款返还');*/
					}
                    //关闭订单 -1关闭
                    $res = Db::name('hotel_order')->where('id', $value['id'])->where('aid', $value['aid'])->where('mid', $value['mid'])->update(['status' => -1]);
                }
            }
        }
    }
    //根据封顶额度自动扣除绿色积分
    public function dec_green_score(){
        if(getcustom('consumer_value_add') && getcustom('greenscore_max')){
            Db::startTrans();
            $syssetlist = Db::name('consumer_set')->where('1=1')->select()->toArray();
            $now_time = time();
            foreach($syssetlist as $sysset) {
                $res = \app\customs\GreenScore::autoDec($sysset['aid'],$sysset);
            }
            Db::commit();
            dump('完成');
        }
    }
}