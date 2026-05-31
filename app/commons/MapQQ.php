<?php



namespace app\commons;

use think\facade\Db;

class MapQQ
{
    public $apiurl = 'https://apis.map.qq.com/ws/';
    private $key;

    public function __construct()
    {
        $this->key = \app\commons\Common::getSysset('webinfo','map_key_qq');
    }

    /**
     * 获取两点之间路线规划距离（骑行 驾车等）用于骑手配送距离计算 https://lbs.qq.com/service/webService/webServiceGuide/route/webServiceRoute
     * @param $lng1 经度1
     * @param $lat1 纬度1
     * @param $lng2 经度2
     * @param $lat2 纬度2
     * @param $len_type （1:m or 2:km)
     * @param $decimal
     * @param $type type：
     * 1. 驾车（driving）：支持结合实时路况、少收费、不走高速等多种偏好，精准预估到达时间（ETA）；
     * 2. 步行（walking）：基于步行路线规划。
     * 3. 骑行（bicycling）：基于自行车的骑行路线；
     * 4. 电动车（ebicycling）：基于电动自行车的骑行路线（默认）；
     * 5. 公交（transit）：支持公共汽车、地铁等多种公共交通工具的换乘方案计算
     * @return array
     */
    public function getDirectionDistance($lng1, $lat1, $lng2, $lat2, $len_type = 1, $decimal = 2,$type = 'ebicycling')
    {
        //参数检查 lat2和lng2存在空值情况
        if($lng1=='' || $lat1=='' || $lng2=='' || $lat2==''){
            return ['status'=>0,'distance'=>-1];
        }

        $url = $this->apiurl . "direction/v1/".$type."/?";
        $url .= "from=".$lat1.",".$lng1."&to=".$lat2.",".$lng2."&key=".$this->key;

        $distance = -1;//默认为负
        $res = curl_get($url);
        $res = json_decode($res,true);
        if($res && $res['status'] ==0){
            $result   = $res['result'];
            $routes   = $result['routes'];
            if($routes[0]){
                $distance = $routes[0]['distance'];//米
            }
        }else{
            \think\facade\Log::write([
                'file'=>__FILE__.__LINE__,
                'status'=>$res['status'],
                'message'=>$res['message']
            ]);

            $this->notice('骑手配送距离计算失败',$res['message']);
        }

        if($distance>=0){
            if($len_type== 2) {
                $distance = $distance/1000;
            }
            $distance = round($distance, $decimal);
            return ['status'=>1,'distance'=>$distance];
        }else{
            return ['status'=>0,'distance'=>$distance];
        }
    }

    /**
     * apidoc:https://lbs.qq.com/service/webService/webServiceGuide/webServiceGcoder
     * @param $longitude
     * @param $latitude
     * @param int $get_poi 0 是否返回周边地点（POI）列表，可选值：
     * @param string $poi_options 具体参数，请参考官方文档
     * @return array
     */
    //$get_poi 是否返回周边地址
    //
    public function getAreaByLocation($latitude,$longitude,$get_poi=0,$poi_options='')
    {
        if($latitude && $longitude) {
            //通过坐标获取省市区 使用 locationToAddress()方法
            $url = $this->apiurl . 'geocoder/v1/?key='.$this->key.'&location=' . $latitude . ',' . $longitude.'&get_poi='.$get_poi;
            $res = json_decode(request_get($url), true);
            //dump($res);
            if ($res && $res['status'] == 0) {
                $address_component = $res['result']['address_component'];
                $landmark = '';//地标$landmark_l1 一级地标 $landmark_l2 二级地标
                if($res['result']['address_reference']){
                    $reference = $res['result']['address_reference'];
                    if($reference && $reference['landmark_l1']){
                        $landmark = $reference['landmark_l1']['title'];
                    }elseif ($reference && $reference['landmark_l2']){
                        $landmark = $reference['landmark_l2']['title'];
                    }
                    //优先地标
                    $res['result']['address_reference']['landmark'] = $landmark;
                }
                return ['status'=>1,'msg'=>'ok','province'=> $address_component['province'],'city'=> $address_component['city'],'district'=> $address_component['district'],'address'=>$res['result']['address'],'street'=>$address_component['street'],'landmark'=>$landmark,'original_data'=>$res['result']];
            }else{
                \think\facade\Log::write([
                    'file'=>__FILE__.__LINE__,
                    'status'=>$res['status'],
                    'message'=>$res['message']
                ]);

                $this->notice('周边地址获取失败',$res['message']);
            }
        }
        return ['status'=>0,'msg'=>'请输入坐标'];
    }

    //关键词输入提示
    /**
     * apidoc:https://lbs.qq.com/service/webService/webServiceGuide/webServiceSuggestion
     * @param $keyword
     * @param $region 	限制城市范围： 根据城市名称限制地域范围， 如，仅获取“广州市”范围内的提示内容；缺省时侧进行全国范围搜索；
     * @param $region_fix 0：[默认]当前城市无结果时，自动扩大范围到全国匹配 1：固定在当前城市
     * @param array $location 经纬度 ['lat'=>35.1232,'lng'=118.23]
     * @param $address_format 可选值：short 返回“不带行政区划的”短地址
     * @param $get_subpois 是否返回子地点，如大厦停车场、出入口等取值, 0 [默认]不返回 1返回
     * @param $policy 检索策略，policy=0：默认，常规策略, policy=1：本策略主要用于收货地址、上门服务地址的填写，policy=10：出行场景（网约车） – 起点查询；policy=11：出行场景（网约车） – 终点查询
     * @param array $ext_param 扩展参数[例如地图接口中的：page_index，page_size，output，callback等，如需要可自行添加]
     */
    public function suggestionPlace($keyword='',$region='',$region_fix=0,$location=[],$address_format='',$get_subpois=0,$filter='',$policy=1,$ext_param=[]){
        $url = $this->apiurl . 'place/v1/suggestion?key='.$this->key;
        //其他参数构建
        if(empty($keyword)){
            return ['status'=>0,'msg'=>'参数错误'];
        }
        $url .="&keyword=".urlencode($keyword).'&region='.$region.'&region_fix='.$region_fix.'&address_format='.$address_format.'&get_subpois='.$get_subpois.'&policy='.$policy;
        if($location && $location['lat'] && $location['lng']){
            $url .= "&location={$location['lat']},{$location['lng']}";
        }
        if($filter){
            $url .= '&filter='.urlencode($filter);
        }
        $res = json_decode(request_get($url), true);
        if ($res && $res['status'] == 0) {
            return ['status'=>1,'msg'=>'ok','data'=> $res['data']];
        }else{
            \think\facade\Log::write([
                'file'=>__FILE__.__LINE__,
                'status'=>$res['status'],
                'message'=>$res['message']
            ]);
        }
        return ['status'=>0,'msg'=>'failed','data'=>$res];
    }

    //地址检索
    /**
     * apidoc:https://lbs.qq.com/service/webService/webServiceGuide/webServiceSearch
     * $keyword 关键字，
     * $location 经纬度数组，['lat'=>35.1232,'lng'=118.23]
     * $range 检索范围，默认1公里内
     * $auto_extend 是否自动扩大范围 默认0 不扩大，1扩大
     * $get_subpois 是否返回子地点，如大厦停车场、出入口等取值, 0 [默认]不返回 1返回
     * $filter 筛选过滤
    ---1. 指定分类筛选，语句格式为：
    ---category=分类名1,分类名2
    ---分类词数量建议不超过5个，支持设置分类编码（支持的分类请参考：POI分类表）
    ---2. 排除指定分类，语句格式为：
    ---category<>分类名1,分类名2
    ---分类词数量建议不超过5个，支持设置分类编码（支持的分类请参考：POI分类表）
    ---3. 筛选有电话的地点：tel<>null
     * $orderby 排序，支持按距离由近到远排序，取值：_distance
     * @param array $ext_param 扩展参数[例如地图接口中的：page_index，page_size，output，callback等，如需要可自行添加]
     */
    public function searchNearbyPlace($keyword='',$location=[],$range=1000,$auto_extend=0,$get_subpois=0,$filter='',$orderby='',$ext_param=[]){
        $url = $this->apiurl . 'place/v1/search?key='.$this->key;
        if(empty($keyword) || empty($location)){
            return ['status'=>0,'msg'=>'参数错误'];
        }
        $boundary = '';
        if($location && $location['lat'] && $location['lng']){
            if(isset($location['type']) && $location['type']=='city'){
                //格式顺序为纬度在前，经度在后
                $boundary = "region({$location['lng']},{$location['lat']},{$auto_extend})";
            }else{
                $boundary = "nearby({$location['lat']},{$location['lng']},{$range},{$auto_extend})";
            }
        }
        if(empty($boundary)){
            return ['status'=>0,'msg'=>'参数错误[boundary]'];
        }
        $url .="&keyword=".urlencode($keyword).'&boundary='.$boundary.'&get_subpois='.$get_subpois.'&orderby='.$orderby;
        if($filter){
            $url .= '&filter='.urlencode($filter);
        }
        $res = json_decode(request_get($url), true);
        if($res && $res['status']===0){
            $result = [
                'status' => 1,
                'msg' => '',
                'data' => $res['data'],
            ];
            if (isset($res['cluster']) && $res['cluster']) {
                $result['cluster'] = $res['cluster'];
            }
            return $result;
        }else{
            \think\facade\Log::write([
                'file'=>__FILE__.__LINE__,
                'status'=>$res['status'],
                'message'=>$res['message']
            ]);
            $this->notice('地址检索失败',$res['message']);
        }
        return ['status'=>0,'msg'=>'failed','message'=>$res['message']];
    }

    //获取地址坐标
    public function addressToLocation($address=''){
        if(empty($address)){
            return ['status'=>0,'msg'=>'请传入正确的地址'];
        }
        $url = $this->apiurl . 'geocoder/v1/?key='.$this->key;
        $url .="&address=".urlencode($address);
        $res = json_decode(request_get($url), true);
        if($res && $res['status']===0){
            $result = $res['result'];
            return ['status'=>1,'msg'=>'','latitude'=>$result['location']['lat'],'longitude'=>$result['location']['lng'],'title'=>$result['title']];
        }else{
            \think\facade\Log::write([
                'file'=>__FILE__.__LINE__,
                'status'=>$res['status'],
                'message'=>$res['message']
            ]);

            $this->notice('坐标获取失败',$res['message']);
        }
        return ['status'=>0,'msg'=>'failed'];
    }

    /**
     * 通过坐标获取省市区 https://lbs.qq.com/service/webService/webServiceGuide/address/Gcoder
     * @param $latitude 纬度
     * @param $longitude 经度
     * @return array
     */
    public function locationToAddress($latitude='', $longitude='')
    {
        if(empty($latitude) || empty($longitude)){
            return ['status'=>0,'msg'=>'请传入正确的经纬度'];
        }
        $url = $this->apiurl . 'geocoder/v1/?key='.$this->key.'&location='.$latitude.','.$longitude;
        $res = json_decode(request_get($url),true);
        if($res && $res['status']==0){
            $address = ['status'=>1, 'msg'=>'ok'];
            $address_component = $res['result']['address_component'];
            $address['area']  = $address_component['province'].','.$address_component['city'].','.$address_component['district'];
            $address['province']  = $address_component['province'];
            $address['city']      = $address_component['city'];
            if($address_component['district']){
                $address['district']  = $address_component['district'];
            }else{
                //广东省东莞市樟木头镇银河北路1号  这种地址获取不到区县
                $address_reference = $res['result']['address_reference'];
                $town = $address_reference['town']['title']??'';
                $address['district'] = $town;
            }
            $address['address'] = $address_component['address'];//	以行政区划+道路+门牌号等信息组成的标准格式化地址
            $address['result']  = $res['result'];
            return $address;
        }else{
            \think\facade\Log::write([
                'file'=>__FILE__.__LINE__,
                'status'=>$res['status'],
                'message'=>$res['message']
            ]);
            $this->notice('获取坐标信息失败',$res['message']);
            return ['status'=>0,'msg'=>'获取坐标信息出错'];
        }
    }

    /**
     * 获取多个终点之间路线规划距离（骑行 驾车等）用于骑手配送距离计算 https://lbs.qq.com/service/webService/webServiceGuide/route/webServiceMatrix
     * @param $lng1 经度1
     * @param $lat1 纬度1
     * @param $to 终点坐标 （经度与纬度用英文逗号分隔，坐标间用英文分号分隔）
     * @param $len_type （1:m or 2:km)
     * @param $decimal
     * @param $type type：
     * 1. 驾车（driving）：支持结合实时路况、少收费、不走高速等多种偏好，精准预估到达时间（ETA）；
     * 2. 步行（walking）：基于步行路线规划。
     * 3. 骑行（bicycling）：基于自行车的骑行路线；

     * @return array
     */
    public function getDirectionDistanceMatrix($lng1, $lat1, $to, $len_type = 1, $decimal = 2,$type = 'bicycling')
    {
        //参数检查 $to存在空值情况
        if($lng1=='' || $lat1=='' || $to==''){
            return ['status'=>0,'distance_arr'=>[]];
        }
        $url = $this->apiurl . "distance/v1/matrix/?mode=".$type;
        $url .= "&from=".$lat1.",".$lng1."&to=".$to."&key=".$this->key;
        $res = curl_get($url);
        $res = json_decode($res,true);
        $distance_arr = [];
        if($res && $res['status'] ==0){
            $result   = $res['result'];
            if($result){
                foreach($result['rows'] as $k=>$v){
                    foreach($v['elements'] as $k2=>$v2){
                        $distance_arr[] = $v2['distance'];
                    }
                }
            }
        }else{
            \think\facade\Log::write([
                'file'=>__FILE__.__LINE__,
                'status'=>$res['status'],
                'message'=>$res['message']
            ]);

            $this->notice('多个终点路线规划距离计算失败',$res['message']);
        }

        if($distance_arr){
            if($len_type== 2) {
                foreach($distance_arr as $k3=>$distance){
                    $distance = $distance/1000;
                    $distance = round($distance, $decimal);
                    $distance_arr[$k3] = $distance;
                }
            }
            return ['status'=>1,'distance_arr'=>$distance_arr];
        }else{
            return ['status'=>0,'distance_arr'=>$distance_arr];
        }
    }


    //通知
    public function notice($title='',$content=''){
        if(empty($title) || empty($content)){
            return false;
        }

        //给当前管理员发送通知
        $where = [];
        $where[] = ['bid','=', 0];
        $where[] = ['status','=', 1];
        $where[] = ['isadmin','=', 2]; //平台主管理员
        $adminUser = Db::name('admin_user')->field('id,aid,bid')->where($where)->find();
        if($adminUser){

            //查询记录 1个小时内是否存在数据
            $where = [];
            $where[] = ['uid','=', $adminUser['id']];
            $where[] = ['title','=', $title];
            $where[] = ['content','=', $content];
            $where[] = ['createtime','>', time() - 3600];
            $notice = Db::name('admin_notice')->where($where)->find();

            //不存在则插入
            if(!$notice){
                Db::name('admin_notice')->insert([
                    'aid' => $adminUser['aid'],
                    'bid' => $adminUser['bid'],
                    'uid' => $adminUser['id'],
                    'title' => $title,
                    'content' => $content,
                    'createtime' => time()
                ]);
            }
        }
    }
}