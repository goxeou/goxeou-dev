<?php


namespace app\models;
use think\facade\Db;
class Freight
{
	public static function getList($where = [],$order='sort desc,id'){
		$freightList = Db::name('freight')->where($where)->order($order)->select()->toArray();
		if(!$freightList) $freightList = [['id'=>0,'name'=>'包邮','pstype'=>0,'pricedata'=>'[{"region":"全国(默认运费)","fristweight":"1000","fristprice":"0","secondweight":"1000","secondprice":"0"}]']];
        return $freightList;
	}
	function getFreightPrice($freight,$address,$product_price,$totalnum,$totalweight){
		if(!$freight) return ['status'=>0,'msg'=>'配送方式不存在'];
		$freight_price = 0;
		if($freight['pstype']==0 || $freight['pstype']==10){ //快递
			$open_freight_district = 0;
			//算运费
			$pricedata = json_decode($freight['pricedata'],true);
			foreach($pricedata as $thisdata){
			    //首重续重默认值为1000
                if($thisdata['fristweight'] <= 0 || empty($thisdata['fristweight'])) $thisdata['fristweight'] = 1000;
                if($thisdata['secondweight'] <= 0 || empty($thisdata['secondweight'])) $thisdata['secondweight'] = 1000;
				$regionlist = explode('];',$thisdata['region']);
				foreach($regionlist as $j=>$regiondata){
					if(!$regiondata) continue;
					$regiondata = explode('[',$regiondata);
					$city_list = [];
					$area_list = [];
					//开启三级县区限制
					if($regiondata[1] != '全部地区'){
						$citys = explode(',',$regiondata[1]);
						foreach($citys as $c){
							$cityarr = explode('|',$c);
							$city_list[] = $cityarr[0];
							$areaarr = explode('-',$cityarr[1]);
							foreach($areaarr as $ar){
								$area_list[$cityarr[0]][] = $ar;
							}
						}
					}

					if($open_freight_district == 1){
						if($regiondata[0] == '全国(默认运费)' || ($regiondata[0] == $address['province'] && ($regiondata[1] == '全部地区' || (in_array($address['city'],$city_list) && ($area_list[$address['city']][0] == '全部县区' || in_array($address['district'],$area_list[$address['city']]) ) )))){
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
							if($thisdata['ispeisong'] == 2 && $address){ //该地区设置了不配送
								return ['status'=>0,'freight_price'=>0,'isoutjuli'=>1,'msg'=>'该地区不在配送范围'];
							}
						}
					
					}else{
						//if($regiondata[0] == '全国(默认运费)' || ($regiondata[0] == $address['province'] && ($regiondata[1] == '全部地区' || in_array($address['city'],explode(',',$regiondata[1]))))){
						if($regiondata[0] == '全国(默认运费)' || ($regiondata[0] == $address['province'] && ($regiondata[1] == '全部地区' || in_array($address['city'],$city_list)))){
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
							if($thisdata['ispeisong'] == 2 && $address){ //该地区设置了不配送
								return ['status'=>0,'freight_price'=>0,'isoutjuli'=>1,'msg'=>'该地区不在配送范围'];
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
		if($freight['pstype']==2){ //同城配送  同步修改app/model/RestaurantTakeawayFreight
			if(!$address) return ['status'=>0,'freight_price'=>0,'isoutjuli'=>2,'msg'=>'请选择收货地址'];
			if($freight['peisong_rangetype'] == 1){
                //peisong_rangetype=1，多边形范围
				$pspointsArr = explode(';',$freight['peisong_rangepath']);
				$pspoints = [];
				foreach($pspointsArr as $pspoint){
					$pspointArr = explode(',',$pspoint);
					$pspoints[] = ['lat'=>$pspointArr[1],'lng'=>$pspointArr[0]];
				}
				if($pspoints){
					$rs = self::is_point_in_polygon(['lat'=>$address['latitude'],'lng'=>$address['longitude']],$pspoints);
					if(!$rs){
						return ['status'=>0,'freight_price'=>0,'isoutjuli'=>1,'msg'=>'您的收货地址超出配送范围'];
					}
				}
                $freight_peisong_lng = $freight['peisong_lng2'];
                $freight_peisong_lat = $freight['peisong_lat2'];
//                $juli = getdistance($address['longitude'],$address['latitude'],$freight['peisong_lng2'],$freight['peisong_lat2'],2);
			}else{
                //peisong_rangetype=0，圆形范围
                $juli = getdistance($address['longitude'],$address['latitude'],$freight['peisong_lng'],$freight['peisong_lat'],2);
				if($juli > $freight['peisong_range']/1000){
					if($freight['freeset']==1 && $freight['free_price'] <=$product_price){
						$freight_price = 0;
					}
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
                $juli = getdistance($address['longitude'],$address['latitude'],$freight['peisong_lng'],$freight['peisong_lat'],2);
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
		if($freight['pstype']==12){ //平台跑腿配送  app 同步
			if(!$address) return ['status'=>0,'freight_price'=>0,'isoutjuli'=>2,'msg'=>'请选择收货地址'];
            //骑行距离
            $mapqq = new \app\commons\MapQQ();
            $bicycl = $mapqq->getDirectionDistance($address['longitude'],$address['latitude'],$freight['peisong_lng'],$freight['peisong_lat'],2);
            if($bicycl && $bicycl['status']==1){
                $juli = $bicycl['distance'];
            }else{
                $juli = getdistance($address['longitude'],$address['latitude'],$freight['peisong_lng'],$freight['peisong_lat'],2);
            }

			if($juli > $freight['peisong_range']/1000){
				if($freight['freeset']==1 && $freight['free_price'] <=$product_price){
					$freight_price = 0;
				}
				return ['status'=>0,'freight_price'=>0,'isoutjuli'=>1,'msg'=>'您的收货地址超出配送范围'];
			}
			//配送费
			if($freight['freeset']==1 && $freight['free_price'] <=$product_price){
				$freight_price = 0;
			}else{
				$freight_price = floatval($freight['peisong_fee1']);
				if($juli - floatval($freight['peisong_juli1']) > 0 && floatval($freight['peisong_juli2']) > 0){
					$freight_price += ceil(($juli - floatval($freight['peisong_juli1']))/floatval($freight['peisong_juli2'])) * floatval($freight['peisong_fee2']);
				}
			}
		}
		if($freight['pstype']==11){
			$type11key = $freight['type11key'] ? $freight['type11key'] : 0;
			$type11pricedata = json_decode($freight['type11pricedata'],true);
			$pricedata = $type11pricedata[$type11key];
			if($freight['freeset']==1 && $freight['free_price'] <=$product_price){
				$freight_price = 0;
			}else{
				$freight_price = floatval($pricedata['price']);
			}
		}

		$freight_price = floatval(round($freight_price,2));
		if($freight['minpriceset']==1 && $freight['minprice']>0 && $freight['minprice'] > $product_price){
			return ['status'=>0,'msg'=>$freight['name'] . '满'.$freight['minprice'].'元起送','freight_price'=>$freight_price,'isoutjuli'=>0];
		}
		if($freight['minnumset']==1 && $freight['minnum']>0 && $freight['minnum'] > $totalnum){
			return ['status'=>0,'msg'=>$freight['name'] . '满'.$freight['minnum'].'件起送','freight_price'=>$freight_price,'isoutjuli'=>0];
		}

		return ['status'=>1,'freight_price'=>$freight_price,'isoutjuli'=>0];
	}
	public static function formatFreightList($freightList,$address,$product_price,$totalnum,$totalweight,$limit_mendianids=[],$mendian_id=0){
		$longitude = $address['longitude'];
		$latitude = $address['latitude'];
		$needLocation = 0;
		foreach($freightList as $k=>$freight){
			$rs = self::getFreightPrice($freight,$address,$product_price,$totalnum,$totalweight);
			$freightList[$k]['freight_price'] = $rs['freight_price'];
			$freightList[$k]['isoutjuli'] = $rs['isoutjuli'];
			if($freight['pstype']==0 || $freight['pstype']==10){ //普通快递
				$freightList[$k]['freight_price_txt'] = '运费';
			}elseif($freight['pstype']==1){ //到店自提
				$needLocation = 1;
				if($longitude && $latitude){
					$orderBy = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) ");
				}else{
					$orderBy = 'sort desc,id';
				}
                //storetype 0全部门店，1选择门店
				if($freight['storetype']==0){
					$whereb = [];
					$whereb[] = ['aid','=',$freight['aid']];
					$whereb[] = ['status','=',1];
					if(false){}else{
						$whereb[] = ['bid','=',$freight['bid']];
					}
					$mendianArr = Db::name('mendian')->where($whereb)->order($orderBy)->field('id,name,pic,longitude,latitude,address')->select()->toArray();
					}else{
					$mendianArr = Db::name('mendian')->where('aid',$freight['aid'])->where('id','in',$freight['storeids'])->where('status',1)->order($orderBy)->field('id,name,pic,longitude,latitude,address')->select()->toArray();
				}

				$freightList[$k]['isbusiness'] = 0;
                $mendianTable = 'mendian';
				$mendianArrNew = [];
                $storekey = 0;
                $i = 0;
				foreach($mendianArr as $k2=>$v2){
                    //限定显示门店
                    if($mendianTable=='mendian' && $limit_mendianids && !in_array($v2['id'],$limit_mendianids)){
                        continue;
                    }
					if($longitude && $latitude){
						$v2['juli'] = '距离'.getdistance($longitude,$latitude,$v2['longitude'],$v2['latitude'],2).'千米';
					}else{
						$v2['juli'] = '';
					}
                    if($mendian_id==$v2['id']){
                        $storekey = $i;
                    }
					$mendianArr[$k2] = $v2;
                    $mendianArrNew[] = $v2;
                    $i++;
				}
				$freightList[$k]['storedata0'] = $mendianArr;
				$freightList[$k]['storedata'] = $mendianArrNew;
				$freightList[$k]['storekey'] = $storekey;
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
                $mendianArrNew = [];
                foreach($mendianArr as $k2=>$v2){
                    //限定显示门店
                    if($mendianTable=='mendian' && $limit_mendianids && !in_array($v2['id'],$limit_mendianids)){
                        continue;
                    }
                    if($longitude && $latitude){
                        $v2['juli'] = '距离'.getdistance($longitude,$latitude,$v2['longitude'],$v2['latitude'],2).'千米';
                    }else{
                        $v2['juli'] = '';
                    }
                    $mendianArr[$k2] = $v2;
                    $mendianArrNew[] = $v2;
                }
                $freightList[$k]['storedata0'] = $mendianArr;
                $freightList[$k]['storedata'] = $mendianArrNew;
                $freightList[$k]['storekey'] = 0;
                $freightList[$k]['freight_price_txt'] = '配送费';
			}elseif($freight['pstype']==11){
				$freightList[$k]['type11pricedata'] = json_decode($freight['type11pricedata'],true);
				$freightList[$k]['type11key'] = 0;
				$freightList[$k]['freight_price_txt'] = '运费';
			}elseif($freight['pstype']==12){ //同步到app 端
				if(!$longitude || !$latitude){ //没选择地点时 显示起送价格
					$freightList[$k]['freight_price'] = floatval($freight['peisong_fee1']);
				}
				$freightList[$k]['freight_price_txt'] = '配送费';
			}

			if($freight['pstimeset']==1){ //配送时间
				$pstimedata = json_decode($freight['pstimedata'],true);
				$pstimeArr = [];
				foreach($pstimedata as $k2=>$v2){
					if($v2['day']==1){
						$thistxt = date('m月d日').' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval = date('Y-m-d').' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval2 = date('Y-m-d').' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
						if(strtotime($thisval2) > time() + 3600*$freight['psprehour']){
							if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
								$thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
								$thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
							}
							$pstimeArr[] = [
								'title'=>$thistxt.'（今天）',
								'value'=>$thisval,
								'bid'=>$freight['bid'],
							];
						}
					}
					if($v2['day']==2){
						$thistxt = ''.date('m月d日',time()+86400).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval = date('Y-m-d',time()+86400).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval2 = date('Y-m-d',time()+86400).' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
						if(strtotime($thisval2) > time() + 3600*$freight['psprehour']){
							if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
								$thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
								$thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
							}
							$pstimeArr[] = [
								'title'=>$thistxt.'（明天）',
								'value'=>$thisval,
								'bid'=>$freight['bid'],
							];
						}
					}
					if($v2['day']==3){
						$thistxt = ''.date('m月d日',time()+86400*2).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval = date('Y-m-d',time()+86400*2).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval2 = date('Y-m-d',time()+86400*2).' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
						if(strtotime($thisval2) > time() + 3600*$freight['psprehour']){
							if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
								$thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
								$thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
							}
							$pstimeArr[] = [
								'title'=>$thistxt.'（后天）',
								'value'=>$thisval,
								'bid'=>$freight['bid'],
							];
						}
					}
					if($v2['day']==4){
						$thistxt = ''.date('m月d日',time()+86400*3).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval = date('Y-m-d',time()+86400*3).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
						$thisval2 = date('Y-m-d',time()+86400*3).' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
						if(strtotime($thisval2) > time() + 3600*$freight['psprehour']){
							if($v2['hour2'] != $v2['hour'] || $v2['minute2'] != $v2['minute']){
								$thistxt.= ('-'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
								$thisval.= ('~'.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']));
							}
							$pstimeArr[] = [
								'title'=>$thistxt.'（大后天）',
								'value'=>$thisval,
								'bid'=>$freight['bid'],
							];
						}
					}
                    if($v2['day']>4){
                        $thistxt = ''.date('m月d日',time()+86400*($v2['day']-1)).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
                        $thisval = date('Y-m-d',time()+86400*($v2['day']-1)).' '.($v2['hour']<=9?'0'.$v2['hour']:$v2['hour']).':'.($v2['minute']<=9?'0'.$v2['minute']:$v2['minute']);
                        $thisval2 = date('Y-m-d',time()+86400*($v2['day']-1)).' '.($v2['hour2']<=9?'0'.$v2['hour2']:$v2['hour2']).':'.($v2['minute2']<=9?'0'.$v2['minute2']:$v2['minute2']);
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
				}
				$freightList[$k]['pstimeArr'] = $pstimeArr;
			}
		}
		$freightArr = [];
		foreach($freightList as $k=>$freight){
			$freightArr[] = ['title'=>$freight['name'],'value'=>$k];
			$freightList[$k]['formdata'] = json_decode($freight['formdata'],true);
			if(!$freightList[$k]['formdata']){
				$freightList[$k]['formdata'] = [];
			}
			//弃用
			$freightList[$k]['field_list'] = [
				'field1' =>['name'=>''],
				'field2' =>['name'=>''],
				'field3' =>['name'=>''],
				'field4' =>['name'=>''],
				'field5' =>['name'=>''],
				'message'=>['isshow'=>'0','name'=>'备注','tips'=>'选填，请输入备注信息','required'=>'0'],
			];
		}
		return ['freightList'=>$freightList,'freightArr'=>$freightArr,'needLocation'=>$needLocation];
	}
	//保存自定义表单内容
	public static function saveformdata($orderid,$type,$freightid,$formdata,$extendInput=[]){
		if(!$orderid || !$type || !$freightid || !$formdata) return ['status'=>0];
		$formfield = Db::name('freight')->where('id',$freightid)->find();
		$formdataSet = json_decode($formfield['formdata'],true);
		if($extendInput){
			$formdataSet = array_merge($extendInput,$formdataSet);
		}//var_dump($formdataSet);
		$data = [];
		foreach($formdataSet as $k=>$v){
			$value = $formdata['form'.$k];
			if(is_array($value)){
				$value = implode(',',$value);
			}
			$value = strval($value);
			$data['form'.$k] = $v['val1'] . '^_^' .$value . '^_^' .$v['key'];
			if($v['val3']==1 && $value===''){
				//return ['status'=>0,'msg'=>$v['val1'].' 必填'];
			}
		}
		$data['aid'] = aid;
		$data['type'] = $type;
		$data['orderid'] = $orderid;
		$data['createtime'] = time();
		Db::name('freight_formdata')->insert($data);
		return ['status'=>1];
	}
	//获取自定义表单数据
	public static function getformdata($orderid,$type){
		if(!$orderid || !$type) return [];
		$formdata = Db::name('freight_formdata')->where('aid',aid)->where('orderid',$orderid)->where('type',$type)->order('id desc')->find();
		if(!$formdata) return [];
		$data = [];
		for($i=0;$i<=30;$i++){
			if($formdata['form'.$i]){
				$thisdata = explode('^_^',$formdata['form'.$i]);
				if($thisdata[1]!==''){
					$data[] = $thisdata;
				}
			}
		}
		return $data;
	}
	//点是否在多边形内
	public static function is_point_in_polygon($point, $pts) {
		$N = count($pts);
		$boundOrVertex = true; //如果点位于多边形的顶点或边上，也算做点在多边形内，直接返回true
		$intersectCount = 0;//cross points count of x 
		$precision = 2e-10; //浮点类型计算时候与0比较时候的容差
		$p1 = 0;//neighbour bound vertices
		$p2 = 0;
		$p = $point; //测试点
	 
		$p1 = $pts[0];//left vertex        
		for ($i = 1; $i <= $N; ++$i) {//check all rays
			// dump($p1);
			if ($p['lng'] == $p1['lng'] && $p['lat'] == $p1['lat']) {
				return $boundOrVertex;//p is an vertex
			}
			 
			$p2 = $pts[$i % $N];//right vertex            
			if ($p['lat'] < min($p1['lat'], $p2['lat']) || $p['lat'] > max($p1['lat'], $p2['lat'])) {//ray is outside of our interests
				$p1 = $p2; 
				continue;//next ray left point
			}
			 
			if ($p['lat'] > min($p1['lat'], $p2['lat']) && $p['lat'] < max($p1['lat'], $p2['lat'])) {//ray is crossing over by the algorithm (common part of)
				if($p['lng'] <= max($p1['lng'], $p2['lng'])){//x is before of ray
					if ($p1['lat'] == $p2['lat'] && $p['lng'] >= min($p1['lng'], $p2['lng'])) {//overlies on a horizontal ray
						return $boundOrVertex;
					}
					 
					if ($p1['lng'] == $p2['lng']) {//ray is vertical                        
						if ($p1['lng'] == $p['lng']) {//overlies on a vertical ray
							return $boundOrVertex;
						} else {//before ray
							++$intersectCount;
						}
					} else {//cross point on the left side
						$xinters = ($p['lat'] - $p1['lat']) * ($p2['lng'] - $p1['lng']) / ($p2['lat'] - $p1['lat']) + $p1['lng'];//cross point of lng
						if (abs($p['lng'] - $xinters) < $precision) {//overlies on a ray
							return $boundOrVertex;
						}
						 
						if ($p['lng'] < $xinters) {//before ray
							++$intersectCount;
						} 
					}
				}
			} else {//special case when ray is crossing through the vertex
				if ($p['lat'] == $p2['lat'] && $p['lng'] <= $p2['lng']) {//p crossing over p2
					$p3 = $pts[($i+1) % $N]; //next vertex
					if ($p['lat'] >= min($p1['lat'], $p3['lat']) && $p['lat'] <= max($p1['lat'], $p3['lat'])) { //p.lat lies between p1.lat & p3.lat
						++$intersectCount;
					} else {
						$intersectCount += 2;
					}
				}
			}
			$p1 = $p2;//next ray left point
		}
	 
		if ($intersectCount % 2 == 0) {//偶数在多边形外
			return false;
		} else { //奇数在多边形内
			return true;
		}
	}
}