<?php


namespace app\controllers;
use think\facade\Db;
class ApiMendian extends ApiCommon{
    
    	//入驻申请
	public function apply(){
		$this->checklogin();
        if(getcustom('business_num_limit')){
            if($this->admin['business_num_limit'] > 0){
                $bcount = Db::name('business')->where('aid',aid)->count();
                if($bcount >= $this->admin['business_num_limit']){
                    return $this->json(['status'=>-4,'msg'=>'多商户数量已达上限']);
                }
            }
        }
        if(request()->isPost()){
			$formdata = input('post.info/a');
			
			$hasun = Db::name('admin_user')->where('id','<>',$formdata['id'])->where('un',$formdata['un'])->find();
			if(!$formdata['id'] && $hasun){
				return $this->json(['status'=>0,'msg'=>'该账号已存在']);
			}
			$info = [];
			$info['aid'] = aid;
			$info['mid'] = mid;
			$info['name'] = $formdata['name'];
			$info['subname'] = $formdata['subname'];
			$info['tel'] = $formdata['tel'];
			$info['pic'] = $formdata['pic'];
			$info['address'] = $formdata['address'];
			$info['latitude'] = $formdata['latitude'];
			$info['longitude'] = $formdata['longitude'];
			$info['status'] = 0;
			$info['check_status'] = 0;
			
			$info['type'] = 1;
			$info['createtime'] = time();
            //通过经纬度获取省市区
            if($info['latitude'] && $info['longitude'] && !$info['district']){
                //通过坐标获取省市区
                $address_component = \app\commons\Common::getAreaByLocation($info['longitude'],$info['latitude']);
                if($address_component && $address_component['status']==1){
                    $info['area'] = $address_component['province'].','.$address_component['city'].','.$address_component['district'];
                    $info['province'] = $address_component['province'];
                    $info['city'] = $address_component['city'];
                    $info['district'] = $address_component['district'];
                }
            }
			$uinfo = [];
			$uinfo['un'] = $formdata['un'];
			$uinfo['pwd'] = $formdata['pwd'];
			$uinfo['auth_data'] = '{"1":"ShopOrder\/index,ShopOrder\/*"}';
			$uinfo['wxauth_data'] = '{"2":"order"}';
			$uinfo['notice_auth_data'] = '["tmpl_orderconfirm","tmpl_orderpay"]';
			$uinfo['hexiao_auth_data'] = '["shop"]';
			$uinfo['auth_type'] = 0;
			
			
			
			
			if($formdata['id']){
				Db::name('mendian')->where('aid',aid)->where('mid',mid)->where('id',$formdata['id'])->update($info);
				if($uinfo['pwd']!=''){
					$uinfo['pwd'] = md5($uinfo['pwd']);
				}else{
					unset($uinfo['pwd']);
				}
				Db::name('admin_user')->where('aid',aid)->where('mdid',$formdata['id'])->update($uinfo);
			}else{
				$bid = Db::name('mendian')->insertGetId($info);
				$uinfo['aid'] = aid;
				$uinfo['bid'] = 0;
				$uinfo['mdid'] = $bid;
                $uinfo['mid'] = mid;
				$uinfo['auth_type'] = 1;
				$uinfo['pwd'] = md5($uinfo['pwd']);
				$uinfo['createtime'] = time();
				$uinfo['isadmin'] = 0;
				$uinfo['random_str'] = random(16);
				$id = Db::name('admin_user')->insertGetId($uinfo);
			}
			return $this->json(['status'=>1,'msg'=>'提交成功，请等待审核']);
		}
		$info = Db::name('mendian')->where('aid',aid)->where('mid',mid)->find();
		if($info && $info['check_status']==1){
			return $this->json(['status'=>2,'msg'=>'您已成功入驻']); 
		}
		$rdata = [];
        $rdata['title'] = '申请入驻';
		$rdata['bset'] = [];
		$rdata['info'] = $info ? $info : [];
		return $this->json($rdata);
	}
	
    
    
    
    
    
    
    
    
    public function mendianlist(){
        $bid = input('param.bid/d',0);
        $proid = input('param.proid','');//多个用逗号分隔，售卖某商品的店
        $longitude = input('param.longitude','');
        $latitude = input('param.latitude','');
        $pernum = 100;
        $pagenum = input('param.pagenum');
        if(!$pagenum) $pagenum = 1;
        $mdwhere = [];
        $mdwhere[] = ['aid','=',aid];
        $mdwhere[] = ['status','=',1];
        $field = '*';
        //如果一个商品包含全部，则为全部门店
        if($proid){
            $productlist = Db::name('shop_product')->where('id','in',explode(',',$proid))->field('id,bind_mendian_ids')->select()->toArray();
            $tmpmdids = [];
            if(empty($productlist)) $productlist = [];
            foreach ($productlist as $k=>$product){
                $bindMendianIds = $product['bind_mendian_ids']?explode(',',$product['bind_mendian_ids']):[];
                if(empty($bindMendianIds) || in_array('-1',$bindMendianIds)){
					break;
                }
				$tmpmdids = array_merge($tmpmdids,$bindMendianIds);     
            }
			if(false){}else{
			    $mdwhere[] = ['bid','=',$bid];
			}
			if($tmpmdids){
				$mdwhere[] = ['id','in',array_unique($tmpmdids)];
			}
        }else{
			$mdwhere[] = ['bid','=',$bid];
		}
        if($longitude && $latitude){
            $field .= ",round(6378.138*2*asin(sqrt(pow(sin( ({$latitude}*pi()/180-latitude*pi()/180)/2),2)+cos({$latitude}*pi()/180)*cos(latitude*pi()/180)* pow(sin( ({$longitude}*pi()/180-longitude*pi()/180)/2),2)))*1000) AS distance";
            $mdorder = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) asc");
        }else{
            $field .= ",0 distance";
            $mdorder = 'sort desc,id asc';
        }
        $mendianlist = Db::name('mendian')->field($field)->where($mdwhere)->orderRaw($mdorder)->page($pagenum,$pernum)->select()->toArray();
        if(empty($mendianlist)){
            $mendianlist = [];
        }
        foreach ($mendianlist as $mdkey=>$mendian){
            if(empty($mendian['distance'])){
                $mendianlist[$mdkey]['distance'] = '';
            }elseif($mendian['distance']<1000){
                $mendianlist[$mdkey]['distance'] = round($mendian['distance'],1).'m';
            }else{
                $mendianlist[$mdkey]['distance'] = round($mendian['distance']/1000,1).'km';
            }
            $mendianlist[$mdkey]['distanceNumKm'] = $mendian['distance'] ? round($mendian['distance']/1000,1) : 0;
            $mendianlist[$mdkey]['distanceNumM'] = $mendian['distance'] ? $mendian['distance'] : 0;
            $mendianlist[$mdkey]['address'] = $mendian['address']??'';
            $mendianlist[$mdkey]['area'] = $mendian['area']??'';
        }
        return $this->json(['status'=>1,'msg'=>'','data'=>$mendianlist]);
    }
    //默认门店
    public function getNearByMendian(){
        $mendian_id = input('param.mendian_id/d',0);
        $bid = input('param.bid',0);
        $mendian_isinit = input('param.mendian_isinit');
        $latitude = input('param.latitude','');
        $longitude = input('param.longitude','');
        $bfield = 'id,name,province,city,district,address,longitude,latitude';
        if($mendian_id && !$mendian_isinit){
            //不是初始化的用户选择的门店
            $mendian = Db::name('mendian')->where('id',$mendian_id)->field($bfield)->find();
        }else if($latitude && $longitude){
            $mdorder = Db::raw("({$longitude}-longitude)*({$longitude}-longitude) + ({$latitude}-latitude)*({$latitude}-latitude) asc");
            $mendian = Db::name('mendian')->where('aid',aid)->where('status',1)->where('bid',$bid)->orderRaw($mdorder)->field($bfield)->find();
        }else{
            $mendian = Db::name('mendian')->where('aid',aid)->where('status',1)->where('bid',$bid)->order('sort desc,id asc')->field($bfield)->find();
        }
        if($mendian){
            $mendian['address'] = ($mendian['province']??'').($mendian['city']??'').($mendian['address']??'');
            $bdistance = '';
            if($mendian['latitude'] && $mendian['longitude'] && $latitude && $longitude){
                $bdistance = getdistance($longitude,$latitude,$mendian['longitude'],$mendian['latitude'],2);
            }
            $mendian['distance'] = $bdistance?$bdistance.'km':'';
			$mendian_upgrade = false;
			$mendian['mendian_upgrade'] = $mendian_upgrade;
            return $this->json(['status'=>1,'msg'=>'','mendian'=>$mendian??'']);
        }else{
            return $this->json(['status'=>0,'msg'=>'','msg'=>'未查询到门店']);
        }
    }
    
    public function getMendianCategory(){
        }

	public function updatemendian(){
		$this->checklogin();
		$mendian_id = input('param.mendianid/d',0);
		Db::name('member')->where('aid',aid)->where('id',mid)->update(['mdid'=>$mendian_id]);
		return $this->json(['status'=>1,'msg'=>'']); 
	}
}