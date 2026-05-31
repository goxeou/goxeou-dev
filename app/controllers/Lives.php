<?php
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use think\Log;
// // 导入对应产品模块的client
// use TencentCloud\Live\V20180801\LiveClient;
// // 导入要请求接口对应的Request类
// use TencentCloud\Live\V20180801\Models\DescribeLiveStreamOnlineListRequest;
// use TencentCloud\Live\V20180801\Models\DropLiveStreamRequest;
// use TencentCloud\Live\V20180801\Models\ForbidLiveStreamRequest;
// use TencentCloud\Live\V20180801\Models\ResumeLiveStreamRequest;

// use TencentCloud\Common\Exception\TencentCloudSDKException;
// use TencentCloud\Common\Credential;


class Lives extends Common{
    public function initialize()
    {
        parent::initialize();
    }
    public function lst(){
        
        if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
// 			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
// 			//dump($where);
			$count = 0 + Db::name('lives')->where($where)->count();
			$data = Db::name('lives')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			foreach($data as $k=>$v){
		    	if($v['mid']){
					$parent = Db::name('member')->where('aid',aid)->where('id',$v['mid'])->find();
				}else{
					$parent = array();
				}
                $data[$k]['parent'] = $parent;;
			
			}
			
			
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
       
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('lives')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		$levelList = Db::name('member_level')->where('aid',aid)->where('cid', $level_cid)->order('sort')->select()->toArray();
		View::assign('info',$info);
		$blist = Db::name('business')->field('id,name')->where('aid',aid)->order('sort desc,id')->select()->toArray();
        View::assign('blist',$blist);
        return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		if($info['id']){
		    $info['bid'] = $info['shop_id'];
			Db::name('lives')->where('aid',aid)->where('id',$info['id'])->update($info);
		}else{
			$info['aid'] = aid;
			$info['bid'] = $info['shop_id'];
			$info['create_time'] = time();
			$id = Db::name('lives')->insertGetId($info);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('lives')->where('aid',aid)->where('id','in',$ids)->delete();
	
		return json(['status'=>1,'msg'=>'删除成功']);
	}
   

    /**
     * 获取直播监控信息
     */
    public function liveMonitor(){

        $list = [];
        $field = 'm.user_name,m.phone,m.headimgurl,a.*,am.lastlogin_time,am.hot,am.recommend,am.prohibit';
        try {
            $pushdomain = db('config')->where(['ename'=>'pushdomain'])->value('value');
            $playdomain = db('config')->where(['ename'=>'playdomain'])->value('value');
            // 实例化一个证书对象，入参需要传入腾讯云账户secretId，secretKey
            $secretId=get_config_value('cos_secretid');
            $secretKey=get_config_value('cos_secretkey');
            $region=get_config_value('cos_region');
            $cred = new Credential($secretId, $secretKey);

            // # 实例化要请求产品(以cvm为例)的client对象
            $client = new LiveClient($cred, $region);

            // 实例化一个请求对象
            $req = new DescribeLiveStreamOnlineListRequest();
            //pushdomain
            $req->DomainName = $pushdomain;
            $req->AppName = "live";
            $req->PageSize = 50;
            //print_r($req);
            // 通过client对象调用想要访问的接口，需要传入请求对象
            $resp = $client->DescribeLiveStreamOnlineList($req);
            $online_data = json_decode($resp->toJsonString(),true);
            $StreamName = "";
            $time_data = [];
            if(isset($online_data['OnlineInfo']) && !empty($online_data['OnlineInfo'])){
                foreach($online_data['OnlineInfo'] as $key=>$value){
                    $StreamName = $StreamName . $value['StreamName'].',';
                    $time_data[$value['StreamName']] = $value['PublishTime'];
                }
                $StreamName = substr($StreamName,0,-1);
                $list = Db::name('live')
                          ->alias('a')
                          ->field($field)
                          ->join('member m','a.shop_id = m.shop_id','LEFT')
                          ->join('live_member am','am.uid = m.id','LEFT')
                          ->where("a.room in (".$StreamName.")")
                          ->group("a.room")
                          ->select();
                if($list){
                    //$type = "http://";
                    foreach($list as $k=>$v){
                        $type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
                        if(empty($type)){
                            $type = "https://";
                        }
                        $list[$k]['address'][0] = 'rtmp://'.$playdomain.'/live/'.$v['room'];
                        $list[$k]['address'][1] = $type.$playdomain.'/live/'.$v['room'].'.flv';
                        $list[$k]['address'][2] = $type.$playdomain.'/live/'.$v['room'].'.m3u8';
                        $list[$k]['livetime'] = $time_data[$v['room']];
                        $list[$k]['cover'] = url_format($v['cover'],'122');
                    }
                }
            }
            $data = Db::name('live')
                      ->alias('a')
                      ->field($field)
                      ->join('member m','a.shop_id = m.shop_id','LEFT')
                      ->join('live_member am','am.uid = m.id','LEFT')
                      ->where("a.room!=1")
                      ->where("a.status!=1")
                      ->group("a.room")
                      ->select();
            foreach ($data as $k=>$v){
                $data[$k]['cover'] = url_format($v['cover'],$this->webconfig['weburl']);
            }
//        dump($data);die;
            $this->assign([
                'data' =>$data,
                'list'=>$list,
            ]);
            //dump($data);
            return $this->fetch();
            //print_r($resp->toJsonString());
        }
        catch(TencentCloudSDKException $e) {
            datamsg(400,'操作失败',$e->getMessage());
        }
    }
    /***
     * 暂停 某个直播间
     */
    public function livestop(){
        $id = input('param.id');
        $live = db('live')->where(['id'=>$id])->find();
        if(empty($live)){
            datamsg(400,'没有找到直播间');
        }
        try {
            $pushdomain = db('config')->where(['ename'=>'pushdomain'])->value('value');
            // 实例化一个证书对象，入参需要传入腾讯云账户secretId，secretKey
            $secretId=get_config_value('cos_secretid');
            $secretKey=get_config_value('cos_secretkey');
            $region=get_config_value('cos_region');
            $cred = new Credential($secretId,  $secretKey);

            // # 实例化要请求产品(以cvm为例)的client对象
            $client = new LiveClient($cred,  $region);

            // 实例化一个请求对象
            $req = new DropLiveStreamRequest();
            //pushdomain
            $req->DomainName = $pushdomain;
            $req->AppName = "live";
            $req->StreamName = $live['room'];
            //print_r($req);
            // 通过client对象调用想要访问的接口，需要传入请求对象
            $resp = $client->DropLiveStream($req);
            $ret_data = json_decode($resp->toJsonString(),true);

            $member_data = db('member')->where(['shop_id'=>$live['shop_id']])->find();
            $client_data = db('member_clientid')->where(['user_id'=>$member_data['id']])->find();
            $send_data = [
                'clientid' => $client_data['client_id'] , 'notice_content' => '网管强制断流！'
            ];
            $model = new SocketNotice();
            $res = $model->send($send_data);
            datamsg(200,'操作成功');
        }
        catch(TencentCloudSDKException $e) {
            datamsg(400,'操作失败');
        }
    }

    /**
     * 关闭某个直播间
     */
    public function liveclose(){
        $id = input('param.id');
        $live = db('live')->where(['id'=>$id])->find();
        if(empty($live)){
            datamsg(400,'没有找到直播间');
        }
        try {
            $pushdomain = db('config')->where(['ename'=>'pushdomain'])->value('value');
            // 实例化一个证书对象，入参需要传入腾讯云账户secretId，secretKey
            $secretId=get_config_value('cos_secretid');
            $secretKey=get_config_value('cos_secretkey');
            $region=get_config_value('cos_region');
            $cred = new Credential($secretId,  $secretKey);

            // # 实例化要请求产品(以cvm为例)的client对象
            $client = new LiveClient($cred,  $region);
            // 实例化一个请求对象
            $req = new ForbidLiveStreamRequest();
            //pushdomain
            $req->DomainName = $pushdomain;
            $req->AppName = "live";
            $req->StreamName = $live['room'];
            //print_r($req);
            // 通过client对象调用想要访问的接口，需要传入请求对象
            $resp = $client->ForbidLiveStream($req);
            $ret_data = json_decode($resp->toJsonString(),true);
            $StreamName = "";
            $time_data = [];
            db('live')->where(['id'=>$id])->update(['status'=>2]);
            $member_data = db('member')->where(['shop_id'=>$live['shop_id']])->find();
            $client_data = db('member_clientid')->where(['user_id'=>$member_data['id']])->find();
            $send_data = [
                'clientid' => $client_data['client_id'] , 'notice_content' => '直播间已被网管禁播!'
            ];
            $model = new SocketNotice();
            $model->send($send_data);
            datamsg(200,'操作成功',$ret_data);
        }
        catch(TencentCloudSDKException $e) {
            datamsg(400,'操作失败',$e);
        }
    }

    public function test(){
        $id = $_GET['id'];
        echo  $this->changestatus($id);
    }

    public function changestatus($id){
        $data = [
            'status'  => 2
        ];
        $res = db('live')->where(['id'=>$id])->update($data);
        return $res;
        //return db('live')->getLastsql();
    }

    /***
     * 恢复某个直播间
     */
    public function resetlive(){
        $id = input('param.id');
        //$id = 21;
        $live = db('live')->where(['id'=>$id])->find();
        if(empty($live)){
            datamsg(400,'没有找到直播间');
        }
        try {
            $pushdomain = db('config')->where(['ename'=>'pushdomain'])->value('value');
            // 实例化一个证书对象，入参需要传入腾讯云账户secretId，secretKey
            $secretId=get_config_value('cos_secretid');
            $secretKey=get_config_value('cos_secretkey');
            $region=get_config_value('cos_region');
            $cred = new Credential($secretId,  $secretKey);

            // # 实例化要请求产品(以cvm为例)的client对象
            $client = new LiveClient($cred,  $region);

            // 实例化一个请求对象
            $req = new ResumeLiveStreamRequest();
            //pushdomain
            $req->DomainName = $pushdomain;
            $req->AppName = "live";
            $req->StreamName = $live['room'];
            // 通过client对象调用想要访问的接口，需要传入请求对象
            $resp = $client->ResumeLiveStream($req);
            //print_r($resp);exit();
            $ret_data = json_decode($resp->toJsonString(),true);

            db('live')->where(['id'=>$id])->update(['status' => -1]);
            datamsg(200,'操作成功',$ret_data);
        }
        catch(TencentCloudSDKException $e) {
            datamsg(400,'操作失败',$e);
        }
    }

    /***
     * 给某个主播间的主播发送提醒
     */
    public function sendnotice(){
        $id = input('param.id');
        $live = db('live')->where(['id'=>$id])->find();
        if(empty($live)){
            datamsg(400,'没有找到直播间');
        }
        $member_data = db('member')->where(['shop_id'=>$live['shop_id']])->find();
        $client_data = db('member_clientid')->where(['user_id'=>$member_data['id']])->find();
        $send_data = [
            'clientid' => $client_data['client_id'] , 'notice_content' => '警告信息，请规范直播!'
        ];
        $model = new SocketNotice();
        $res = $model->send($send_data);
        datamsg(200,'操作成功');
    }


    /***
     * 生成直播推流服务签名信息
     */
    public function sign(){
    }

    /***
     *请求
     */
    private function curl_req($data,$url,$sign){
        $files = $this->makecookie($sign);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_COOKIEJAR,$files);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
        //return json_decode($output,true);
    }

    //删除到回收站
    public function recycle(){
        $id = input('id');
        if (!empty($id) && !is_array($id)) {
            $shop = Db::name('live')->where('id', $id)->where('is_recycle', 0)->find();
            if ($shop) {
                // 启动事务
                Db::startTrans();
                try {
                    Db::name('live')->where('id', $id)->update(array('is_recycle'=>1));

                    // 提交事务
                    Db::commit();
                    $value = array('status' => 1, 'mess' => '加入回收站成功');

                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    $value = array('status' => 0, 'mess' => '加入回收站失败');
                }
            } else {
                $value = array('status' => 0, 'mess' => '找不到相关信息');
            }
        } else {
            $value = array('status' => 0, 'mess' => '未选中任何数据');
        }
        return json($value);
    }

    //直播间回收站
    public function hslst()
    {
        $where = array();
        $where['is_recycle']=1;
        $field = 'm.user_name,m.phone,m.headimgurl,a.*,s.shop_name';
        $list = Db::name('live')
                  ->alias('a')
                  ->field($field)
                  ->join('member m','a.shop_id = m.shop_id','LEFT')
                  ->join('shops s','s.id = a.shop_id','LEFT')
                  ->where($where)
                  ->order('a.id desc')
                  ->paginate($limit)
                  ->each(function ($item){
                      $item['type_name'] =  db('type')->where(['id'=>$item['type_id']])->value('type_name');
                      return $item;
                  });
        $page = $list->render();
        $this->assign([
            'list'=>$list,
            'page'=>$page
        ]);
        if (request()->isAjax()) {
            return $this->fetch('hsajaxpage');
        } else {
            return $this->fetch('hslst');
        }
    }

    //取出回收站直播间
    public function recovery()
    {
        $id = input('id');
        if (!empty($id) && !is_array($id)) {
            $shop = Db::name('live')->where('id', $id)->where('is_recycle', 1)->find();
            if ($shop) {
                // 启动事务
                Db::startTrans();
                try {
                    Db::name('live')->where('id', $id)->update(array('is_recycle'=>0));

                    // 提交事务
                    Db::commit();
                    $value = array('status' => 1, 'mess' => '恢复直播间成功');

                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    $value = array('status' => 0, 'mess' => '恢复直播间失败');
                }
            } else {
                $value = array('status' => 0, 'mess' => '找不到相关信息');
            }
        } else {
            $value = array('status' => 0, 'mess' => '未选中任何数据');
        }
        return json($value);
    }

    public function delete() {

        if (input('post.id')) {
            $id = array_filter(explode(',', input('post.id')));
        } else {
            $id = input('id');
        }

        if (!empty($id)) {
            if (!is_array($id)) {
                $shop = Db::name('live')->where('id', $id)->where('is_recycle', 1)->find();
                if ($shop) {
                    // 启动事务
                    Db::startTrans();
                    try {
                        $delResult = db('live')->where('id', $id)->delete();
                        // 提交事务
                        Db::commit();
                        ys_admin_logs('删除直播间成功', 'live', $id);
                        $value = array('status' => 1, 'mess' => '删除直播间成功');
                    } catch (\Exception $e) {
                        // 回滚事务
                        Db::rollback();
                        $value = array('status' => 0, 'mess' => '删除直播间失败');
                    }
                } else {
                    $value = array('status' => 0, 'mess' => '找不到相关信息');
                }
            } else {
                $idarr = $id;
                foreach ($idarr as $vd) {
                    $shop = Db::name('live')->where('id', $vd)->where('is_recycle', 1)->find();
                    if ($shop) {
                        // 启动事务
                        Db::startTrans();
                        try {
                            $delResult = db('live')->where('id', $vd)->delete();
                            // 提交事务
                            Db::commit();
                            ys_admin_logs('删除直播间成功', 'live', $vd);
                        } catch (\Exception $e) {
                            // 回滚事务
                            Db::rollback();
                        }
                    }
                }
                $value = array('status' => 1, 'mess' => '删除直播间成功');
            }
        } else {
            $value = array('status' => 0, 'mess' => '未选中任何数据');
        }
        return json($value);
    }

    //是否开启
    public function changeIsClose() {
        if (!request()->isPost()) {
            return json(array('status' => 0, 'mess' => '请求方式错误'));
        }
        $id = input('post.id');
        if (!$id) {
            return json(array('status' => 0, 'mess' => '参数错误'));
        }
        $isclose = abs(input('post.isclose') - 1);
        $data = array('isclose' => $isclose);
        $updateResult = db('live')->where(array('id' => $id))->update($data);
        if (!$updateResult) {
            return json(array('status' => 0, 'mess' => '修改失败'));
        }
        return json(array('status' => 1, 'mess' => '修改成功'));
    }

}
?>