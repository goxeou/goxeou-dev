<?php


namespace app\models;
use think\facade\Db;
class RestaurantTakeawayFreight
{
	public static function getList($where = []){
		$freightList = Db::name('restaurant_takeaway_freight')->where($where)->order('sort desc,id')->select()->toArray();
		if(!$freightList) $freightList = [['id'=>0,'name'=>'包邮','pstype'=>0,'pricedata'=>'[{"region":"全国(默认运费)","fristweight":"1000","fristprice":"0","secondweight":"1000","secondprice":"0"}]']];
        return $freightList;
	}
	function getFreightPrice($freight,$address,$product_price,$totalnum,$totalweight){
		if(!$freight) return ['status'=>0,'msg'=>'配送方式不存在'];
		$freight_price = 0;
		if($freight['pstype']==0){ //快递
			//算运费
			$pricedata = json_decode($freight['pricedata'],true);
			foreach($pricedata as $thisdata){
				$regionlist = explode('];',$thisdata['region']);
				foreach($regionlist as $j=>$regiondata){
					$regiondata = explode('[',$regiondata);
					if($regiondata[0] == '全国(默认运费)' || ($regiondata[0] == $address['province'] && ($regiondata[1] == '全部地区' || in_array($address['city'],explode(',',$regiondata[1]))))){
						if($freight['freeset']==1 && $freight['free_price'] <=$product_price){
							$freight_price = 0;
						}else{
							if($freight['type']==1){
								$freight_price = floatval($thisdata['fristprice']);   //首重价格
								if($totalweight - floatval($thisdata['fristweight']) > 0){
									$freight_price += ceil(($totalweight - floatval($thisdata['fristweight']))/floatval($thisdata['secondweight'])) * floatval($thisdata['secondprice']);  //+续重价格
								}
							}else{
								$freight_price = floatval($thisdata['fristprice']);   //首件价格
								if($totalnum - floatval($thisdata['fristweight']) > 0){
									$freight_price += ceil(($totalnum - floatval($thisdata['fristweight']))/floatval($thisdata['secondweight'])) * floatval($thisdata['secondprice']);  //+续件价格
								}
							}
						}
					}
				}
			}
		}
		if($freight['pstype']==1){ //到店自提
			$freight_price = floatval($freight['fwprice']);
		}
        if($freight['pstype']==5){ //门店配送
            $freight_price = floatval($freight['fwprice']);
        }
		if($freight['pstype']==2){ //同城配送
            if(!$address) return ['status'=>0,'freight_price'=>0,'isoutjuli'=>2,'msg'=>'请选择收货地址'];
            if($freight['peisong_rangetype'] == 1){
                $pspointsArr = explode(';',$freight['peisong_rangepath']);
                $pspoints = [];
                foreach($pspointsArr as $pspoint){
                    $pspointArr = explode(',',$pspoint);
                    $pspoints[] = ['lat'=>$pspointArr[1],'lng'=>$pspointArr[0]];
                }
                if($pspoints){
                    $rs = Freight::is_point_in_polygon(['lat'=>$address['latitude'],'lng'=>$address['longitude']],$pspoints);
                    if(!$rs){
                        return ['status'=>0,'freight_price'=>0,'isoutjuli'=>1,'msg'=>'您的收货地址超出配送范围'];
                    }
                }
                $freight_peisong_lng = $freight['peisong_lng2'];
                $freight_peisong_lat = $freight['peisong_lat2'];
            }else{
                $juli = getdistance($address['longitude'],$address['latitude'],$freight['peisong_lng'],$freight['peisong_lat'],2);
                if($juli > $freight['peisong_range']/1000){
                    return ['status'=>0,'freight_price'=>0,'isoutjuli'=>1,'msg'=>'您的收货地址超出配送范围'];
                }
                $freight_peisong_lng = $freight['peisong_lng'];
                $freight_peisong_lat = $freight['peisong_lat'];
            }

			//配送费 骑行距离
            $mapqq = new \app\commons\MapQQ();
            $bicycl = $mapqq->getDirectionDistance($address['longitude'],$address['latitude'],$freight_peisong_lng,$freight_peisong_lat,2);
            if($bicycl && $bicycl['status']==1){
                $juli = $bicycl['distance'];
            }else{
                $juli = getdistance($address['longitude'],$address['latitude'],$freight_peisong_lng,$freight_peisong_lat,2);
            }
			if($freight['freeset']==1 && $freight['free_price'] <=$product_price){
				$freight_price = 0;
			}else{
				$freight_price = floatval($freight['peisong_fee1']);
				if($juli - floatval($freight['peisong_juli1']) > 0 && floatval($freight['peisong_juli2']) > 0){
					$freight_price += ceil(($juli - floatval($freight['peisong_juli1']))/floatval($freight['peisong_juli2'])) * floatval($freight['peisong_fee2']);
				}
			}
		}
		return ['status'=>1,'freight_price'=>$freight_price,'isoutjuli'=>0];
	}
	public static function formatFreightList($freightList,$address,$product_price,$totalnum,$totalweight){
		$longitude = $address['longitude'];
		$latitude = $address['latitude'];
		$needLocation = 0;
		foreach($freightList as $k=>$freight){
			$rs = self::getFreightPrice($freight,$address,$product_price,$totalnum,$totalweight);
			$freightList[$k]['freight_price'] = $rs['freight_price'];
			$freightList[$k]['isoutjuli'] = $rs['isoutjuli'];
			if($freight['pstype']==0){ //普通快递
				$freightList[$k]['freight_price_txt'] = '运费';
			}elseif($freight['pstype']==1){ //到店自提
				$needLocation = 1;
				if($longitude && $latitude){
					$orderBy = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
				}else{
					$orderBy = 'sort desc,id';
				}
				if($freight['storetype']==0){
					$mendianArr = Db::name('mendian')->where('aid',$freight['aid'])->where('bid',$freight['bid'])->order($orderBy)->field('id,name,pic,longitude,latitude')->where('status',1)->select()->toArray();
				}else{
					$mendianArr = Db::name('mendian')->where('aid',$freight['aid'])->where('id','in',$freight['storeids'])->order($orderBy)->field('id,name,pic,longitude,latitude')->where('status',1)->select()->toArray();
				}
				foreach($mendianArr as $k2=>$v2){
					if($longitude && $latitude){
						$v2['juli'] = '距离'.getdistance($longitude,$latitude,$v2['longitude'],$v2['latitude'],2).'千米';
					}else{
						$v2['juli'] = '';
					}
					$mendianArr[$k2] = $v2;
				}
				$freightList[$k]['storedata'] = $mendianArr;
				$freightList[$k]['storekey'] = 0;
				$freightList[$k]['freight_price_txt'] = '服务费';
			}elseif($freight['pstype']==2){ //同城配送
				if(!$longitude || !$latitude){ //没选择地点时 显示起送价格
					$freightList[$k]['freight_price'] = floatval($freight['peisong_fee1']);
				}
				$freightList[$k]['freight_price_txt'] = '配送费';
            }elseif($freight['pstype']==5){ //门店配送
                $needLocation = 1;
                if($longitude && $latitude){
                    $orderBy = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
                }else{
                    $orderBy = 'sort desc,id';
                }
                if($freight['storetype']==0){
                    $mendianArr = Db::name('mendian')->where('aid',$freight['aid'])->where('bid',$freight['bid'])->where('status',1)->order($orderBy)->field('id,name,pic,longitude,latitude')->limit(50)->select()->toArray();
                }else{
                    $mendianArr = Db::name('mendian')->where('aid',$freight['aid'])->where('id','in',$freight['storeids'])->where('status',1)->order($orderBy)->field('id,name,pic,longitude,latitude')->limit(50)->select()->toArray();
                }
                foreach($mendianArr as $k2=>$v2){
                    if($longitude && $latitude){
                        $v2['juli'] = '距离'.getdistance($longitude,$latitude,$v2['longitude'],$v2['latitude'],2).'千米';
                    }else{
                        $v2['juli'] = '';
                    }
                    $mendianArr[$k2] = $v2;
                }
                $freightList[$k]['storedata'] = $mendianArr;
                $freightList[$k]['storekey'] = 0;
                $freightList[$k]['freight_price_txt'] = '配送费';
            }

			if($freight['pstimeset']==1){ //配送时间
				$pstimedata = json_decode($freight['pstimedata'],true);
				$pstimeArr = [];
				foreach($pstimedata as $k2=>$v2){
					if($v2['day']==1){
						$thistxt = '今天('.date('m月d日').') '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval = date('Y-m-d').' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval2 = date('Y-m-d').' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
						if(strtotime($thisval2) > time() + 3600*$freight['psprehour']){
							if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
								$thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
								$thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
							}
							$pstimeArr[] = [
								'title'=>$thistxt,
								'value'=>$thisval,
								'bid'=>$freight['bid'],
							];
						}
					}
					if($v2['day']==2){
						$thistxt = '明天('.date('m月d日',time()+86400).') '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval = date('Y-m-d',time()+86400).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval2 = date('Y-m-d',time()+86400).' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
						if(strtotime($thisval2) > time() + 3600*$freight['psprehour']){
							if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
								$thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
								$thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
							}
							$pstimeArr[] = [
								'title'=>$thistxt,
								'value'=>$thisval,
								'bid'=>$freight['bid'],
							];
						}
					}
					if($v2['day']==3){
						$thistxt = '后天('.date('m月d日',time()+86400*2).') '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval = date('Y-m-d',time()+86400*2).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval2 = date('Y-m-d',time()+86400*2).' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
						if(strtotime($thisval2) > time() + 3600*$freight['psprehour']){
							if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
								$thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
								$thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
							}
							$pstimeArr[] = [
								'title'=>$thistxt,
								'value'=>$thisval,
								'bid'=>$freight['bid'],
							];
						}
					}
					if($v2['day']==4){
						$thistxt = '大后天('.date('m月d日',time()+86400*3).') '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval = date('Y-m-d',time()+86400*3).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval2 = date('Y-m-d',time()+86400*3).' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
						if(strtotime($thisval) > time() + 3600*$freight['psprehour']){
							if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
								$thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
								$thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
							}
							$pstimeArr[] = [
								'title'=>$thistxt,
								'value'=>$thisval,
								'bid'=>$freight['bid'],
							];
						}
					}
				}
				$freightList[$k]['pstimeArr'] = $pstimeArr;
			}
		}
		$freightArr = [];
		foreach($freightList as $k=>$freight){
			$freightArr[] = ['title'=>$freight['name'],'value'=>$k];
			$freightList[$k]['field_list'] = json_decode($freight['field_list'],true);
			if(!$freightList[$k]['field_list']){
				$freightList[$k]['field_list'] = [
					'field1' =>['name'=>''],
					'field2' =>['name'=>''],
					'field3' =>['name'=>''],
					'field4' =>['name'=>''],
					'field5' =>['name'=>''],
					'message'=>['isshow'=>'1','name'=>'备注','tips'=>'选填，请输入备注信息','required'=>'0'],
				];
			}
		}
		return ['freightList'=>$freightList,'freightArr'=>$freightArr,'needLocation'=>$needLocation];
	}
}