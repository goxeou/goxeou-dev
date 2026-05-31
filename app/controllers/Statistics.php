<?php

namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Statistics extends Common
{
    public function initialize(){
		parent::initialize();
	
	}
	public function index(){
         $groups = Db::name('shop_group')->where('aid',aid)->select()->toArray();
    	if(request()->isAjax()){
	       	$year = date('Y');
			$month = '';
			$day = '';
			$tjtype = 1;
			if(input('param.year')) $year = input('param.year');
			if(input('param.month')) $month = input('param.month');
			if(input('param.day')) $day = input('param.day');
			if(input('param.tjtype')) $tjtype = input('param.tjtype');
			if(input('param.month')==''){
				//$month = intval(date('m'));
			}
            $where = [];
            if(input('param.mid')){
                if (input('param.isteam')==1) {
                    $downmids = \app\commons\Member::getteammids(aid,input('param.mid'));
                    $where[] = ['mid','in',$downmids];
                } else {
                    $where[] = ['mid','=',input('param.mid')];
                }
                
            }
        	$data = [];
			$totalval = 0;
			$maxval = 0;
			$maxdate = '';
            if(!$month){
				for($i=1;$i<13;$i++){
					$thismonth = $i >=10 ? ''.$i : '0'.$i;
					$starttime = strtotime($year.'-'.$thismonth.'-01');
					if($thismonth == 12){
						$endtime = strtotime(($year+1).'-01-01');
					}else{
						$nextmonth = $thismonth+1;
						$nextmonth = $nextmonth >=10 ? ''.$nextmonth : '0'.$nextmonth;
						$endtime = strtotime($year.'-'.$nextmonth.'-01');
					}
					$thisdata = [];
					$thisdata['date'] = $i;
					$thisdata['begin'] = $starttime;
					$thisdata['end'] = $endtime;
					$data[] = $thisdata;
				}
			}elseif(!$day){
				$month = $month>9?''.$month:'0'.$month;
				$ts = date('t',strtotime($year.'-'.$month.'-01'));
				for($i=1;$i<=$ts;$i++){
					$thisday = $i >=10 ? ''.$i : '0'.$i;
					$starttime = strtotime($year.'-'.$month.'-'.$thisday);
					$endtime = $starttime + 86400;
					$thisdata = [];
					$thisdata['date'] = $i;
					$thisdata['begin'] = $starttime;
					$thisdata['end'] = $endtime;
					$data[] = $thisdata;
				}
			
			}else{
				$month = $month>9?''.$month:'0'.$month;
				$day = $day >6 ? ''.$day : '0'.$day;
				for($i=0;$i<24;$i++){
					$starttime = strtotime($year.'-'.$month.'-'.$day) + $i*3600;
					$endtime = $starttime + 3600;
					
					$thisdata = [];
					$thisdata['date'] = $i.'点-'.($i+1).'点';
					$thisdata['begin'] = $starttime;
					$thisdata['end'] = $endtime;
					$data[] = $thisdata;
				}
			}
		
			foreach ($data as $k=> $item){
			    $where1 = [];
                $where1[] = ['aid','=',aid];
                $where1[] = ['status','in',[1,2,3]];
                if (bid>0) {
                    $where1[] = ['bid','=',bid];
                }
                $where1[] = ['createtime','between',[$item['begin'],$item['end']]];
                
                $data[$k]['tong_shop_1'] = 0 + Db::name('shop_order')->where($where1)->sum('totalprice');
    		 
                
                $data[$k]['tong_maidan_1'] = 0 + Db::name('maidan_order')->where($where1)->sum('money');
                
    		
                $data[$k]['tong_cashier_1'] = 0 + Db::name('cashier_order')->where($where1)->sum('totalprice');
    		
                
             
            
                
            }
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($data),'data'=>$data,'tjtype'=>1]);
		}
		$getTime= getTime();
		
		$total = array();
		$where1 = [];
        $where1[] = ['aid','=',aid];
        $where1[] = ['status','in',[1,2,3]];
        
        if ( bid > 0) {
            $where1[] = ['bid','=',bid];
        }
        
    	$tong_shop_1 = Db::name('shop_order')->where($where1)->sum('totalprice');
    
        $total['tong_shop_1'] = round($tong_shop_1,2);
       
        
        $tong_maidan_1 = Db::name('maidan_order')->where($where1)->sum('money');
    
        $total['tong_maidan_1'] = round($tong_maidan_1,2);
       
          
        $tong_cashier_1 = Db::name('cashier_order')->where($where1)->sum('totalprice');
    
        $total['tong_cashier_1'] = round($tong_cashier_1,2);
       
        
	   
	   	return view('', ['total' => $total,'groups'=>$groups]);

    }
    
}
