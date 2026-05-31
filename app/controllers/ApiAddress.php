<?php


namespace app\controllers;
use think\facade\Db;
class ApiAddress extends ApiCommon
{
	public function initialize(){
		parent::initialize();
		if(!in_array(request()->action(),['getAreaByLocation','searchNearbyPlace','suggestionPlace','addressToZuobiao'])){
			$this->checklogin();
		}
	}
	public function address(){
		$type = input('param.type');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['mid','=',mid];
		if(input('param.keyword')){
			$where[] = ['name|tel','like','%'.input('param.keyword').'%'];
		}
		if($type == 1){ //需要坐标
			$datalist = Db::name('member_address')->where($where)->where('latitude','>',0)->order('isdefault desc,id desc')->select()->toArray();
		}else{
			$datalist = Db::name('member_address')->where($where)->order('isdefault desc,id desc')->select()->toArray();
		}
		return $this->json(['status'=>1,'data'=>$datalist]);
	}
	public function addressadd(){
		$type = input('param.type');
		if(request()->isPost()){
			$post = input('post.');
			if($type == 1){
				if(!$post['latitude'] || !$post['longitude']){
					return $this->json(['status'=>0,'msg'=>'请选择坐标点']);
				}
			}
			if(!checkTel($post['tel'])){
				return $this->json(['status'=>0,'msg'=>'手机号格式错误']);
			}

			$data = array();
			$data['aid'] = aid;
			$data['mid'] = mid;
			$data['name'] = $post['name'];
			$data['tel'] = $post['tel'];
			$data['address'] = $post['address'];
			$data['createtime'] = time();
            $data['company'] = $post['company'];
			if($type == 1 || $post['latitude']){
				$data['area'] = $post['area'];
				$data['latitude'] = $post['latitude'];
				$data['longitude'] = $post['longitude'];
				if($data['latitude'] && !$data['province']){
                    //通过坐标获取省市区
                    $mapqq = new \app\commons\MapQQ();
                    $res = $mapqq->locationToAddress($data['latitude'],$data['longitude']);
                    if($res && $res['status']==1){
                        $data['province'] = $res['province'];
                        $data['city'] = $res['city'];
                        $data['district'] = $res['district'];
                    }
				}
			}else{
				$area = explode(',',$post['area']);
				$data['province'] = $area[0];
				$data['city'] = $area[1];
				$data['district'] = $area[2];
				$data['area'] = implode('',$area);
			}
			if(getcustom('paotui')) {
                $source = input('source')?input('source'):'';
                if($source && $source == 'paotui'){
                    //查询洗鞋插件是否开启
                    $paotui_set = Db::name('paotui_set')->where('aid',aid)->find();
                    if(!$paotui_set || $paotui_set['status'] !=1 ){
                        return $this->json(['status'=>0,'msg'=>'系统暂未开启跑腿服务']);
                    }

                    //查询是否属于开通城市
                    $province = explode(',',$paotui_set['province']);
                    if(!in_array($data['province'],$province)){
                        return $this->json(['status'=>0,'msg'=>'此区域暂未开通服务']);
                    }

                    //查询是否属于开通城市
                    $city = explode(',',$paotui_set['city']);
                    if(!in_array($data['city'],$city)){
                        return $this->json(['status'=>0,'msg'=>'此区域暂未开通服务']);
                    }
                    $post['addressid'] = 0;//跑腿来的一直添加
                }
            }
			if($post['addressid']){
				Db::name('member_address')->where('aid',aid)->where('mid',mid)->where('id',$post['addressid'])->update($data);
				$addressid = $post['addressid'];
			}else{
				$default = Db::name('member_address')->where('aid',aid)->where('mid',mid)->where('isdefault',1)->find();
				if(!$default) $data['isdefault'] = 1;
				$addressid = Db::name('member_address')->insertGetId($data);
			}
			return $this->json(['status'=>1,'msg'=>'保存成功','addressid'=>$addressid]);
		}
		if(input('param.id')){
			$addressid = input('param.id/d');
			$address = Db::name('member_address')->where('aid',aid)->where('mid',mid)->where('id',$addressid)->find();
		}else{
			$address = [];
		}
		return $this->json(['status'=>1,'data'=>$address]);
	}
	//设置默认地址
	public function setdefault(){
		$from = input('param.from');
		$addressid = input('param.addressid/d');
		Db::name('member_address')->where('aid',aid)->where('mid',mid)->update(['isdefault'=>0]);
		Db::name('member_address')->where('aid',aid)->where('mid',mid)->where('id',$addressid)->update(['isdefault'=>1]);
		return $this->json(['status'=>1,'msg'=>'设置成功']);
	}
	//删除地址
	public function del(){
		$addressid = input('param.addressid/d');
		$rs = Db::name('member_address')->where('aid',aid)->where('mid',mid)->where('id',$addressid)->delete();
		if($rs){
			return $this->json(['status'=>1,'msg'=>'删除成功']);
		}else{
			return $this->json(['status'=>0,'msg'=>'删除失败']);
		}
	}

	//识别地址信息
	public function shibie(){
		$addressxx = input('param.addressxx');
		$postdata = [];
		$postdata['text'] = $addressxx;
	//	$rs = request_post('https:///index/api/address',$postdata);
		$rs = json_decode($rs,true);
		return $this->json($rs);
	}

	//根据经纬度获取当前地区信息
	public function getAreaByLocation(){
	    $latitude = input('param.latitude');
	    $longitude = input('param.longitude');
	    $type = input('param.type',0);//0,仅返回基础数据[省市县地标],1 返回原始全部数据
	    if($latitude && $longitude){
            $mapqq = new \app\commons\MapQQ();
            $data = $mapqq->getAreaByLocation($latitude,$longitude,$type);
            if($data && $data['status']==1){
                if($type==1){
                    return $this->json(['status'=>1,'msg'=>'ok','data'=>$data['original_data']]);
                }else{
                    unset($data['original_data']);
                    return $this->json($data);
                }
            }else{
                return $this->json(['status'=>0,'msg'=>'参数有误']);
            }
            return $this->json($data);
        }
	    return $this->json(['status'=>0,'msg'=>'参数有误']);
    }

    //附近地址
    public function searchNearbyPlace(){
        $latitude = input('param.latitude');
        $longitude = input('param.longitude');
        $keyword = input('param.keyword');
        if(empty($keyword) && empty($latitude) && empty($longitude)){
            return $this->json(['status'=>0,'msg'=>'关键字和经纬度不可为空']);
        }
        if(empty($keyword) && $latitude && $longitude){
            //通过经纬度，获取关键字[地标]
            $mapqq = new \app\commons\MapQQ();
            $res = $mapqq->getAreaByLocation($latitude,$longitude);
            if($res && $res['status']==1){
                $keyword = $res['landmark'];
            }
        }
        if(empty($keyword)){
            return $this->json(['status'=>0,'msg'=>'地址信息有误']);
        }
        $mapqq = new \app\commons\MapQQ();
        $rdata = $mapqq->searchNearbyPlace($keyword,['lat'=>$latitude,'lng'=>$longitude]);
        return $this->json($rdata);
    }

    //关键字输入提示
    public function suggestionPlace(){
        $latitude = input('param.latitude');
        $longitude = input('param.longitude');
        $keyword = input('param.keyword');
        $region = input('param.region');
        if(empty($keyword)){
            return $this->json(['status'=>0,'msg'=>'关键字不可为空']);
        }
        $location = ['lat'=>$latitude,'lng'=>$longitude];
        $mapqq = new \app\commons\MapQQ();
        $rdata = $mapqq->suggestionPlace($keyword,$region,1,$location,'short');
        return $this->json($rdata);
    }

    //地址解析（地址转坐标）
    public function addressToZuobiao(){
        $address = input('param.address');
        $mapqq = new \app\commons\MapQQ();
        $rdata = $mapqq->addressToLocation($address);
        return $this->json($rdata);
    }
}