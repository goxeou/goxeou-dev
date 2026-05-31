<?php


namespace app\commons;
use think\worker\Server;
use think\facade\Log;
use think\facade\Db;
$config = include(ROOT_PATH.'config.php');
define('kfsocket','websocket://0.0.0.0:'.$config['kfport']);
class Worker extends Server{
    protected $socket = kfsocket;
	protected $count = 1;
	protected $processes = 1;
    /**
     * 收到信息
     * @param $connection
     * @param $data
     */
    public function onMessage($connection, $res){
		error_reporting(0);
//        $connection->send('我收到你的信息了!');
		$config = include(ROOT_PATH.'config.php');
		$authtoken = $config['authtoken'];
		//Log::write($res);
		$res = json_decode($res,true);
		if(!$res) return ;


		if($res['type'] == 'khinit'){
			$data = $res['data'];
			$aid = $data['aid'];
			$mid = $data['mid'];
			$member = Db::name('member')->where('id',$mid)->find();
			if($res['token']!= $member['random_str']){
				return;
			}
			//Log::write($data);
			$connection->ctype = 'kehu';
			$connection->aid = $aid;
			$connection->mid = $mid;
			$connection->send(json_encode(['data'=>$data]));
		}
		
		if($res['type'] == 'notice'){ //消息提醒
			$data = $res['data'];
			if($data){
				$aid = $data['aid'];
				if($data['mids']){
					$mids = $data['mids'];
				}else{
					$mids = [$data['mid']];
				}
				
				foreach($this->worker->connections as $con){
					if(isset($con->aid) && $con->aid == $aid && $con->ctype=='kehu' && in_array($con->mid,$mids)){
						$con->send(json_encode(['type'=>'notice','data'=>['title'=>$data['title'],'desc'=>$data['desc'],'url'=>$data['url']]]));
					}
				}
			}
			$connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
		}
    	if($res['type'] == 'update'){ //消息提醒
			$data = $res['data'];
				if($data){
        		    $path = '/www/wwwroot/230311.xcx88.top/app/Log/ctype.txt';
                    $s = print_r( $con->ctype, true);
                    file_put_contents($path, $s);
        
        			foreach($this->worker->connections as $con){
        				if(isset($con->aid) && $con->aid == $aid ){
        					$con->send(json_encode(['type'=>'update','data'=>$data]));
        				}
        			}
				}
		
		}
	   if($res['type'] == 'room'){ //客户发送消息给管理员
			$data = $res['data'];
			if($data){
				if($data['pre_url'] && !defined('PRE_URL')){
					define('PRE_URL',$data['pre_url']);
				}
				$aid = $data['aid'];
				$mid = $data['mid'];
				$bid = $data['bid'];
				$platform = $data['platform'];

				$member = Db::name('member')->where('id',$mid)->find();
				if($res['token']!= $member['random_str']){
					return;
				}
			
				$insertdata = [];
				$insertdata['aid'] = $aid;
				$insertdata['mid'] = $mid;
				$insertdata['bid'] = $bid;
				$insertdata['nickname'] = $member['nickname'];
				$insertdata['headimg'] = $member['headimg'];
				$insertdata['tel'] = $member['tel'];
				$insertdata['room'] = $data['room'];
				$insertdata['msgtype'] = $data['msgtype'];
				$insertdata['content'] = getshowcontent($data['content']);
				$insertdata['createtime'] = time();
				$insertdata['isreply'] = 0;
				$insertdata['platform'] = $platform;
				$insertdata['isread'] = 0;
				$hasonline = 0;
				foreach($this->worker->connections as $con){
					if(isset($con->aid) && $con->aid == $aid && $con->ctype == 'kehu' ){
						$con->send(json_encode(['type'=>'room','data'=>$insertdata]));
					}
				}
				$insertdata['content'] = $data['content'];
				$insertdata['iswx'] = ($data['iswx'] == 1 ? 1 : 0);
				// Db::name('kefu_message')->insert($insertdata);
			
			}
			$connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
		}
        
		if($res['type'] == 'tokefu2'){ //客户发送消息给管理员
			$data = $res['data'];
			if($data){
				if($data['pre_url'] && !defined('PRE_URL')){
					define('PRE_URL',$data['pre_url']);
				}
				$aid = $data['aid'];
				$mid = $data['mid'];
				$bid = $data['bid'];
				$platform = $data['platform'];

				$member = Db::name('member')->where('id',$mid)->find();
				if($res['token']!= $member['random_str']){
					return;
				}
			
				$insertdata = [];
				$insertdata['aid'] = $aid;
				$insertdata['mid'] = $mid;
				$insertdata['bid'] = $bid;
				$insertdata['nickname'] = $member['nickname'];
				$insertdata['headimg'] = $member['headimg'];
				$insertdata['tel'] = $member['tel'];
				$insertdata['room'] = $data['room'];
				$insertdata['msgtype'] = $data['msgtype'];
				$insertdata['content'] = getshowcontent($data['content']);
				$insertdata['createtime'] = time();
				$insertdata['isreply'] = 0;
				$insertdata['platform'] = $platform;
				$insertdata['isread'] = 0;
				$hasonline = 0;
				foreach($this->worker->connections as $con){
					if(isset($con->aid) && $con->aid == $aid && $con->ctype == 'kehu' ){
						$con->send(json_encode(['type'=>'tokefu2','data'=>$insertdata]));
					}
				}
				$insertdata['content'] = $data['content'];
				$insertdata['iswx'] = ($data['iswx'] == 1 ? 1 : 0);
				Db::name('kefu_message')->insert($insertdata);
			
			}
			$connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
		}
		if($res['type'] == 'tokehu'){ //管理员发送消息给客户
			$data = $res['data'];
			if($data){
				if($data['pre_url'] && !defined('PRE_URL')){
					define('PRE_URL',$data['pre_url']);
				}
				$uinfo = Db::name('admin_user')->where(['id'=>$data['uid']])->find();
				if($res['token'] != $uinfo['random_str']){
					Log::write('鉴权失败');
					return;
				}

				$aid = $data['aid'];
				$bid = $uinfo['bid'];
				$uid = $data['uid'];
				$umid = $data['umid'];
				$mid = $data['mid'];
				$msgtype = $data['msgtype'];

				$lastmessage = Db::name('kefu_message')->where(['aid'=>$aid,'bid'=>$bid,'mid'=>$mid,'isreply'=>0])->order('id desc')->find();
				$platform = $lastmessage['platform'];
				$iswx = $lastmessage['iswx'];

				$minfo = Db::name('member')->where(['aid'=>$aid,'id'=>$mid])->find();
				if(!$minfo) return;
				if($bid == 0){
					$business = Db::name('admin_set')->where(['aid'=>$aid])->field('name,logo')->find();
				}else{
					$business = Db::name('business')->where(['id'=>$bid])->field('name,logo')->find();
				}
				$hasonline = 0;
				if($iswx){
					if($msgtype == 'text'){
						$rs = $this->send_text($aid,$platform,$data['content'],$minfo[$platform.'openid']);
					}elseif($msgtype == 'image'){
						$rs = $this->send_image($aid,$platform,$data['content'],$minfo[$platform.'openid']);
					}
					if($rs['status']!=1){
						$connection->send(json_encode(['type'=>'response','data'=>$rs]));
						return;
					}else{
						$hasonline = 1;
					}
				}
				$insertdata = [];
				$insertdata['aid'] = $aid;
				$insertdata['bid'] = $bid;
				$insertdata['mid'] = $mid;
				$insertdata['uid'] = $uid;
				$insertdata['nickname'] = $minfo['nickname'];
				$insertdata['headimg'] = $minfo['headimg'];
				$insertdata['tel'] = $minfo['tel'];
				$insertdata['unickname'] = $business['name'];
				$insertdata['uheadimg'] = $business['logo'];
				$insertdata['msgtype'] = $msgtype;
				$insertdata['content'] = getshowcontent($data['content']);
				$insertdata['createtime'] = time();
				$insertdata['isreply'] = 1;
				$insertdata['platform'] = $platform;
				$insertdata['isread'] = $hasonline;
				foreach($this->worker->connections as $con){
					if(isset($con->aid) && $con->aid == $aid && $con->ctype=='kehu' && ($con->mid == $mid || $con->mid == $umid)){
						if($con->mid == $mid){
							$hasonline = 1;
						}
						$con->send(json_encode(['type'=>'tokehu','data'=>$insertdata]));
					}
				}
				$insertdata['content'] = $data['content'];
				$insertdata['isread'] = $hasonline;
				Db::name('kefu_message')->insert($insertdata);
				//不在线 发消息通知
				if($hasonline==0){
					if($msgtype == 'text'){
						$content = $data['content'];
					}elseif($msgtype == 'image'){
						$content = '[图片]';
					}elseif($msgtype == 'voice'){
						$content = '[语音]';
					}elseif($msgtype == 'video'){
						$content = '[小视频]';
					}elseif($msgtype == 'miniprogrampage'){
						$content = json_decode($data['content']);
						$content = '小程序页面['.$content->Title.']';
					}else{
						$content = $data['content'];
					}
					$tmplcontent = array();
					$tmplcontent['first'] = '咨询回复通知';
					$tmplcontent['keyword1'] = $minfo['nickname'];
					$tmplcontent['keyword2'] = $minfo['tel'];
					$tmplcontent['keyword3'] = date('Y-m-d H:i:s');
					$tmplcontent['remark'] = '回复内容：'.$content.'，请点击进入查看~';
					
					$tmplcontent_new = [];
					$tmplcontent_new['thing5'] = '咨询回复通知';
					$tmplcontent_new['thing11'] = $minfo['nickname'];

					$rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_kehuzixun',$tmplcontent,m_url('/pages/kefu/index?bid='.$bid,$aid),$tmplcontent_new);
				}
			}
		}
		if($res['type'] == 'tokefu'){ //客户发送消息给管理员
			$data = $res['data'];
			if($data){
				if($data['pre_url'] && !defined('PRE_URL')){
					define('PRE_URL',$data['pre_url']);
				}
				$aid = $data['aid'];
				$mid = $data['mid'];
				$bid = $data['bid'];
				$platform = $data['platform'];

				$member = Db::name('member')->where('id',$mid)->find();
				if($res['token']!= $member['random_str']){
					return;
				}
				//可接收消息的管理员
				$umids = Db::name('admin_user')->where('aid',$aid)->where('bid',$bid)->where('mid','<>',0)->where('tmpl_kehuzixun',1)->where('mdid',0)->column('mid');
				
				$insertdata = [];
				$insertdata['aid'] = $aid;
				$insertdata['mid'] = $mid;
				$insertdata['bid'] = $bid;
				$insertdata['nickname'] = $member['nickname'];
				$insertdata['headimg'] = $member['headimg'];
				$insertdata['tel'] = $member['tel'];
				$insertdata['msgtype'] = $data['msgtype'];
				$insertdata['content'] = getshowcontent($data['content']);
				$insertdata['createtime'] = time();
				$insertdata['isreply'] = 0;
				$insertdata['platform'] = $platform;
				$insertdata['isread'] = 0;

				$hasonline = 0;
				foreach($this->worker->connections as $con){
					if(isset($con->aid) && $con->aid == $aid && $con->ctype == 'kehu' && ($con->mid==$mid || in_array($con->mid,$umids))){
						if(in_array($con->mid,$umids)){
							$hasonline = 1;
						}
						$con->send(json_encode(['type'=>'tokefu','data'=>$insertdata]));
					}
				}
				$insertdata['content'] = $data['content'];
				$insertdata['iswx'] = ($data['iswx'] == 1 ? 1 : 0);
				Db::name('kefu_message')->insert($insertdata);

                if ($data['question_id']) {
                   	if($bid == 0){
        				$newset = Db::name('admin_set')->where('aid',$aid)->find();
        			}else{
        				$newset = Db::name('business')->field('name,logo')->where('id',$bid)->find();
        			}
        			$template = Db::name('kefu_question')->where('aid',$aid)->where('bid',$bid)->where('id',$data['question_id'])->where('status',1)->find();
        			
        			if ($template['desc']) {
        			   	$insertdata2 = [];
            			$insertdata2['aid'] = $aid;
            			$insertdata2['mid'] = $mid;
            			$insertdata2['bid'] = $bid;
            			$insertdata2['uid'] = 0;
            			$insertdata2['nickname'] = $member['nickname'];
            			$insertdata2['headimg'] = $member['headimg'];
            			$insertdata2['tel'] = $member['tel'];
            			$insertdata2['unickname'] = $newset['name'];
            			$insertdata2['uheadimg'] = $newset['logo'];
            			$insertdata2['msgtype'] = 'text';
            			$insertdata2['content'] = $template['desc'];
            			$insertdata2['createtime'] = time();
            			$insertdata2['isreply'] = 1;
            			$insertdata2['isread'] = 1;
            			$insertdata2['platform'] = $platform;
            			Db::name('kefu_message')->insert($insertdata2);
            			foreach($this->worker->connections as $con){
        					if(isset($con->aid) && $con->aid == $aid && $con->ctype=='kehu' && ($con->mid == $mid || $con->mid == $umid)){
        						if($con->mid == $mid){
        							$hasonline = 1;
        						}
        						$con->send(json_encode(['type'=>'tokehu','data'=>$insertdata2]));
        					}
        				}
        			}
                }else {
                    if($bid == 0){
        				$newset = Db::name('admin_set')->where('aid',$aid)->find();
        			}else{
        				$newset = Db::name('business')->field('name,logo')->where('id',$bid)->find();
        			}
        			$fastreply = Db::name('kefu_fastreply')->where('aid',$aid)->where('bid',$bid)->where('name','like','%'.$data['content'].'%')->where('status',1)->find();
        			if ($fastreply && $fastreply['desc']) {
        			   	$insertdata2 = [];
            			$insertdata2['aid'] = $aid;
            			$insertdata2['mid'] = $mid;
            			$insertdata2['bid'] = $bid;
            			$insertdata2['uid'] = 0;
            			$insertdata2['nickname'] = $member['nickname'];
            			$insertdata2['headimg'] = $member['headimg'];
            			$insertdata2['tel'] = $member['tel'];
            			$insertdata2['unickname'] = $newset['name'];
            			$insertdata2['uheadimg'] = $newset['logo'];
            			$insertdata2['msgtype'] = 'text';
            			$insertdata2['content'] = $fastreply['desc'];
            			$insertdata2['createtime'] = time();
            			$insertdata2['isreply'] = 1;
            			$insertdata2['isread'] = 1;
            			$insertdata2['platform'] = $platform;
            			Db::name('kefu_message')->insert($insertdata2);
            			foreach($this->worker->connections as $con){
        					if(isset($con->aid) && $con->aid == $aid && $con->ctype=='kehu' && ($con->mid == $mid || $con->mid == $umid)){
        						if($con->mid == $mid){
        							$hasonline = 1;
        						}
        						$con->send(json_encode(['type'=>'tokehu','data'=>$insertdata2]));
        					}
        				}
        			}
				
                }

				if($hasonline==0){ //没有在线的客服 发送通知
					if($data['msgtype'] == 'text'){
						$content = $data['content'];
					}elseif($data['msgtype'] == 'image'){
						$content = '[图片]';
					}elseif($data['msgtype'] == 'voice'){
						$content = '[语音]';
					}elseif($data['msgtype'] == 'video'){
						$content = '[小视频]';
					}elseif($data['msgtype'] == 'goods'){
						$content = '[商品]';
					}elseif($data['msgtype'] == 'miniprogrampage'){
						$content = json_decode($data['content']);
						$content = '小程序页面['.$content->Title.']';
					}else{
						$content = $data['content'];
					}
					$tmplcontent = array();
					if($data['platform']=='h5'){
						$tmplcontent['first'] = '用户['.$member['nickname'].']正在通过在线客服咨询您';
					}elseif($data['platform']=='mp'){
						$tmplcontent['first'] = '用户['.$member['nickname'].']正在通过公众号咨询您';
					}elseif($data['platform']=='wx'){
						$tmplcontent['first'] = '用户['.$member['nickname'].']正在通过小程序咨询您';
					}
					$tmplcontent['keyword1'] = $member['nickname'];
					$tmplcontent['keyword2'] = $member['tel'];
					$tmplcontent['keyword3'] = date('Y-m-d H:i:s');
					$tmplcontent['remark'] = '咨询内容：'.$content.'，请点击进入查看~';

					$tmplcontent_new = [];
					$tmplcontent_new['thing5'] = '用户咨询通知';
					$tmplcontent_new['thing11'] = $member['nickname'];

					//Log::write($tmplcontent);
					$rs = \app\commons\Wechat::sendhttmpl($data['aid'],$data['bid'],'tmpl_kehuzixun',$tmplcontent,m_url('/admin/kefu/index',$data['aid']),0,$tmplcontent_new);

					$tmplcontent = [];
					$tmplcontent['name1'] = $member['nickname'];
					$tmplcontent['thing3'] = $content;
					$tmplcontent['date2'] = date('Y-m-d H:i');
					\app\commons\Wechat::sendhtwxtmpl($data['aid'],$data['bid'],'tmpl_kehuzixun',$tmplcontent,'admin/kefu/index');
				}
			}
			$connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
		}
		if($res['type'] == 'peisong'){ //发配送单
			$data = $res['data'];
			if($data){
				$aid = $data['aid'];
				$psorderid = $data['psorderid'];
				$psorder = Db::name('peisong_order')->where('id',$psorderid)->find();
				$binfo = json_decode($psorder['binfo'],true);
				$orderinfo = json_decode($psorder['orderinfo'],true);
				
				$desc = $binfo['name'].'->'.$orderinfo['address'];
				if($psorder['psid']){
					$title = '有新的订单待配送';
					$mids = Db::name('peisong_user')->where('aid',$aid)->where('id',$psorder['psid'])->column('mid');
					$type = '1';
				}else{
					$title = '有新的配送订单待接单';
					$mids = Db::name('peisong_user')->where('aid',$aid)->where('status',1)->column('mid');
					$type = '0';
				}
				foreach($this->worker->connections as $con){
					if(isset($con->aid) && $con->aid == $aid && $con->ctype=='kehu' && in_array($con->mid,$mids)){
						$con->send(json_encode(['type'=>'peisong','data'=>['title'=>$title,'desc'=>$desc,'type'=>$type]]));
					}
				}
			}
			$connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
		}
		if($res['type'] == 'peisong_jiedan'){ //配送单被接单
			$data = $res['data'];
			if($data){
				$aid = $data['aid'];
				$mid = $data['mid'];
				$psorderid = $data['psorderid'];
				$psorder = Db::name('peisong_order')->where('id',$psorderid)->find();
				$mids = Db::name('peisong_user')->where('aid',$aid)->where('status',1)->where('mid','<>',$mid)->column('mid');
				foreach($this->worker->connections as $con){
					if(isset($con->aid) && $con->aid == $aid && $con->ctype=='kehu' && in_array($con->mid,$mids)){
						$con->send(json_encode(['type'=>'peisong_jiedan','data'=>$data]));
					}
				}
			}
			$connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
		}

		if($res['type'] == 'worker_paidan'){ //预约服务发配送单
			$data = $res['data'];
			if($data){
				$aid = $data['aid'];
				$worker_orderid = $data['worker_orderid'];
				$worker_order = Db::name('yuyue_worker_order')->where('id',$worker_orderid)->find();
				$binfo = json_decode($worker_order['binfo'],true);
				$orderinfo = json_decode($worker_order['orderinfo'],true);
				
				$desc = $binfo['name'].'->'.$orderinfo['address'];
				if($worker_order['worker_id']){
					$title = '有新的订单';
					$mids = Db::name('yuyue_worker')->where('aid',$aid)->where('id',$worker_order['worker_id'])->column('mid');
					$type = '1';
				}else{
					$title = '有新的配送订单待接单';
					$mids = Db::name('yuyue_worker')->where('aid',$aid)->where('status',1)->column('mid');
					$type = '0';
				}
				foreach($this->worker->connections as $con){
					if(isset($con->aid) && $con->aid == $aid && $con->ctype=='kehu' && in_array($con->mid,$mids)){
						$con->send(json_encode(['type'=>'worker_paidan','data'=>['title'=>$title,'desc'=>$desc,'type'=>$type]]));
					}
				}
			}
			$connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
		}
		if($res['type'] == 'worker_jiedan'){ //预约服务配送单被接单
			$data = $res['data'];
			if($data){
				$aid = $data['aid'];
				$mid = $data['mid'];
				$worker_orderid = $data['worker_orderid'];
				$psorder = Db::name('yuyue_worker_order')->where('id',$worker_orderid)->find();
				$mids = Db::name('yuyue_worker')->where('aid',$aid)->where('status',1)->where('mid','<>',$mid)->column('mid');
				foreach($this->worker->connections as $con){
					if(isset($con->aid) && $con->aid == $aid && $con->ctype=='kehu' && in_array($con->mid,$mids)){
						$con->send(json_encode(['type'=>'worker_jiedan','data'=>$data]));
					}
				}
			}
			$connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
		}

		if($res['type'] == 'restaurant_queue'){
			$data = $res['data'];
			if($res['token']!=md5(md5($authtoken.$data['aid'].$data['bid']))){
				Log::write('token校验失败');
				return;
			}
			Log::write($data);
			$connection->ctype = 'restaurant_queue';
			$connection->aid = $data['aid'];
			$connection->bid = $data['bid'];
			$connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
		}
		if($res['type'] == 'restaurant_queue_callno'){
			$data = $res['data'];
			if($res['token']!=md5(md5($authtoken.$data['aid'].$data['bid']))){
				Log::write('token校验失败'.$authtoken.$data['aid'].$data['bid']);
				return;
			}
			Log::write($data);
			foreach($this->worker->connections as $con){
				if($con->ctype == 'restaurant_queue' && $con->aid==$data['aid'] && $con->bid==$data['bid']){
					$con->send(json_encode(['type'=>'restaurant_queue_callno','data'=>['call_id'=>$data['call_id'],'call_no'=>$data['call_no']]]));
				}
			}
			$connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
		}
		if($res['type'] == 'restaurant_queue_add'){
			$data = $res['data'];
			if($res['token']!=md5(md5($authtoken.$data['aid'].$data['bid']))){
				Log::write('token校验失败'.$authtoken.$data['aid'].$data['bid']);
				return;
			}
			Log::write($data);
			foreach($this->worker->connections as $con){
				if($con->ctype == 'restaurant_queue' && $con->aid==$data['aid'] && $con->bid==$data['bid']){
					$con->send(json_encode(['type'=>'restaurant_queue_add','data'=>['queue_id'=>$data['queue_id'],'queue_no'=>$data['queue_no']]]));
				}
			}
			$connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
		}
		if($res['type'] == 'restaurant_queue_cancel'){
			$data = $res['data'];
			if($res['token']!=md5(md5($authtoken.$data['aid'].$data['bid']))){
				Log::write('token校验失败'.$authtoken.$data['aid'].$data['bid']);
				return;
			}
			Log::write($data);
			foreach($this->worker->connections as $con){
				if($con->ctype == 'restaurant_queue' && $con->aid==$data['aid'] && $con->bid==$data['bid']){
					$con->send(json_encode(['type'=>'restaurant_queue_cancel','data'=>['queue_id'=>$data['queue_id'],'queue_no'=>$data['queue_no']]]));
				}
			}
			$connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
		}
        if(getcustom('h5zb')) {
            if ($res['type'] == 'h5zb') {
                $data = $res['data'];
                if ($data) {
                    if ($data['pre_url'] && !defined('PRE_URL')) {
                        define('PRE_URL', $data['pre_url']);
                    }
                    $aid = $data['aid'];
                    $mid = $data['mid'];
                    $bid = $data['bid'];
                    $roomid = $data['roomid'];//roomid
                    $proid = $data['proid']??0;
                    $platform = $data['platform'];
//                    $member = Db::name('member')->where('id', $mid)->find();
//                    if ($res['token'] != $member['random_str']) {
//                        return;
//                    }
                    $msgtype = $data['msgtype'];
                    $insertdata = [];
                    if ($msgtype == 'join') {
                        Db::name('h5zb_live_room')->where('aid',$aid)->where('id',$roomid)->inc('watch_num',1)->update();
                        Db::name('h5zb_room_record')->insert([
                            'aid'=>$aid,
                            'bid'=>$bid,
                            'createtime'=>time(),
                            'remark'=>'加入直播加',
                            'nickname'=>$member['nickname'],
                            'headimg'=>$member['headimg'],
                            'roomid'=>$roomid,
                            'eventid'=>0,
                            'type'=>'join',
                            'eventdata'=>''
                        ]);
                    }else if ($msgtype == 'bonus') {
                        $data['content'] = $member['nickname'] . ' 获得答题红包';
                        $insertdata['status'] = 1;
                    } elseif ($msgtype == 'topnum') {
//                        $room = Db::name('h5zb_live_room')->where('aid', $aid)->where('id', $roomid)->find();
                        $connectionMid = [];
                        $connectionCount = 0;
                        foreach ($this->worker->connections as $con) {
                            $ckey = $aid . '_' . $mid;
                            if (!isset($connectionMid[$ckey])) {
                                $connectionMid[$ckey] = true;
                                $connectionCount++;
                            }
                        }
                        /*$toprecord = Db::name('video_top_record')->where('aid', $aid)->where('bid', $bid)->where('video_id', $vid)->find();
                        if ($toprecord) {
                            if ($toprecord['num'] < $connectionCount) {
                                Db::name('video_top_record')->where('id', $toprecord['id'])->update(['num' => $connectionCount]);
                            }
                        } else {
                            Db::name('video_top_record')->insert([
                                'aid' => $aid,
                                'bid' => $bid,
                                'video_id' => $vid,
                                'b_video_id' => $video['pid'],
                                'createtime' => time(),
                                'num' => $connectionCount
                            ]);
                        }*/
                    }elseif($msgtype == 'productst1'){
                        //上架
                        $data['content'] = \app\customs\H5zb::getRoomProinfo($proid);
                    }elseif($msgtype == 'productst0'){
                        $data['content'] = \app\customs\H5zb::getRoomProinfo($proid);
                    }elseif($msgtype == 'producttop'){
                        //置顶
                        $data['content'] = \app\customs\H5zb::getRoomTopProinfo($proid,$roomid);
                    } else {
                        //会员是不是被拉黑和禁言
                        $bmap = [];
                        $bmap[] = ['aid', '=', $aid];
                        $bmap[] = ['mid', '=', $mid];
                        $bmap[] = ['roomid', '=', $roomid];
                        $bexist = Db::name('h5zb_member_blacklist')->where($bmap)->count();
                        if ($bexist) {
                            return;
                        }
                        //可接收消息的管理员
//                $umids = Db::name('admin_user')->where('aid',$aid)->where('bid',$bid)->where('mid','<>',0)->where('tmpl_kehuzixun',1)->where('mdid',0)->column('mid');
                        $room = Db::name('h5zb_live_room')->where('aid', $aid)->where('id', $roomid)->find();
                        if ($room['pinglun_banned']) {
                            return;//禁言
                        } elseif ($room['pinglun_ischeck']) {
                            $insertdata['status'] = 0;
                        } else {
                            $insertdata['status'] = 1;
                        }
                        if ($msgtype == 'image' && $room['pinglun_noimg']) {
                            return;//禁止发图
                        }
                    }
                    $insertdata['aid'] = $aid;
                    $insertdata['mid'] = $mid;
                    $insertdata['bid'] = $bid;
                    $insertdata['roomid'] = $roomid;
                    $insertdata['nickname'] = $member['nickname'];
                    $insertdata['headimg'] = $member['headimg'];
                    $insertdata['tel'] = $member['tel'];
                    $insertdata['msgtype'] = $data['msgtype'];
                    if(in_array($msgtype,['producttop','productst0','producttop1']) && $data['content']){
                        $insertdata['content'] = $data['content'];
                    }else{
                        $insertdata['content'] = getshowcontent($data['content']);
                    }
                    $insertdata['createtime'] = time();
                    $insertdata['isreply'] = 0;
                    $insertdata['platform'] = $platform;
                    $insertdata['isread'] = 0;
                    foreach ($this->worker->connections as $con) {
                        if (isset($con->aid) && $con->aid == $aid) {
                            $con->send(json_encode(['type' => 'h5zb', 'data' => $insertdata]));
                        }
                    }
                    unset($insertdata['ismine']);
                    $insertdata['iswx'] = ($data['iswx'] == 1 ? 1 : 0);
                    if(in_array($msgtype,['producttop','productst0','producttop1'])){
                        //产品信息
                        $insertdata['content'] = json_encode($data['content']);
                    }else{
                        $insertdata['content'] = $data['content'];
                    }
                    Db::name('h5zb_message')->insert($insertdata);
                }
                $connection->send(json_encode(['type' => 'response', 'data' => ['status' => 1]]));
            }
        }
        if($res['type'] == 'zhaopin'){ //招聘-我要应聘-收到的求职
            $data = $res['data'];
            if($data){
                $aid = $data['aid'];
                $mid = $data['mid'];
                $bid = $data['bid']??0;
                if($data['pre_url'] && !defined('PRE_URL')){
                    define('PRE_URL',$data['pre_url']);
                }
                $minfo = Db::name('member')->where('aid',$aid)->where('id',$mid)->find();
                if(!$minfo) return;
                $msgtype = $data['msgtype'];

//                $lastmessage = Db::name('zhaopin_message')->where(['aid'=>$aid,'bid'=>$bid,'mid'=>$mid])->order('id desc')->find();
//                $platform = $lastmessage['platform'];
//                $iswx = $lastmessage['iswx'];
                $zhaopin = Db::name('zhaopin')->where('aid',$aid)->where('id',$data['tbid'])->find();
                if(empty($zhaopin)){
                    return;
                }
                if($zhaopin['mid']==$mid){
                    $isreply = 1;
                    $tomid = $data['tomid'];//回复求职者 求职者的mid
                }else{
                    $isreply = 0;
                    $tomid = $zhaopin['mid'];
                }
                $toMember =  Db::name('member')->where('aid',$aid)->where('id',$tomid)->find();
                if(empty($toMember)){
                    return;
                }

                $hasonline = 0;
                $insertdata = [];
                $insertdata['aid'] = $aid;
                $insertdata['tableid'] = $data['tbid'];
                $insertdata['tablename'] = 'zhaopin';
                $insertdata['mid'] = $mid;
                $insertdata['nickname'] = $minfo['nickname'];
                $insertdata['headimg'] = $minfo['headimg'];
                $insertdata['tel'] = $minfo['tel'];
                $insertdata['tomid'] = $toMember['id'];
                $insertdata['unickname'] = $toMember['nickname'];
                $insertdata['uheadimg'] = $toMember['headimg'];
                $insertdata['msgtype'] = $msgtype;
                $insertdata['content'] = getshowcontent($data['content']);
                $insertdata['createtime'] = time();
                $insertdata['platform'] = $platform;
                $insertdata['isread'] = 0;
                $insertdata['isreply'] = $isreply;
                foreach($this->worker->connections as $con){
                    if(isset($con->aid) && $con->aid == $aid && ($con->mid == $mid || $con->mid == $tomid)){
                        if($con->mid == $tomid){
                            $hasonline = 1;
                        }
                        if($data['tbtype']==1 && $con->mid==$mid){
                            $cres = $this->chatFee($aid,$bid);
                            if($cres){
                                $con->send(json_encode(['type'=>'zhaopin','data'=>$insertdata]));
                            }
                        }else{
                            $con->send(json_encode(['type'=>'zhaopin','data'=>$insertdata]));
                        }
                    }
                }
                $insertdata['content'] = $data['content'];
                $insertdata['isread'] = $hasonline;
                Db::name('zhaopin_message')->insert($insertdata);
                //不在线 发消息通知
                if($hasonline==0){
                    if($msgtype == 'text'){
                        $content = $data['content'];
                    }elseif($msgtype == 'image'){
                        $content = '[图片]';
                    }elseif($msgtype == 'voice'){
                        $content = '[语音]';
                    }elseif($msgtype == 'video'){
                        $content = '[小视频]';
                    }elseif($msgtype == 'miniprogrampage'){
                        $content = json_decode($data['content']);
                        $content = '小程序页面['.$content->Title.']';
                    }else{
                        $content = $data['content'];
                    }
                    $tmplcontent = array();
                    $tmplcontent['first'] = '您有新的应聘消息';
                    $tmplcontent['keyword1'] = $minfo['nickname'];
                    $tmplcontent['keyword2'] = $minfo['tel'];
                    $tmplcontent['keyword3'] = date('Y-m-d H:i:s');
                    $tmplcontent['remark'] = '内容：'.$content.'，请点击进入查看~';

                    $tmplcontent_new = [];
					$tmplcontent_new['thing5'] = '您有新的应聘消息';
					$tmplcontent_new['thing11'] = $minfo['nickname'];

                    $rs = \app\commons\Wechat::sendtmpl($aid,$tomid,'tmpl_kehuzixun',$tmplcontent,m_url('/zhaopin/notice/index?bid='.$bid,$aid),$tmplcontent_new);
                    //发送短信
                    if($toMember['tel']){
                        $rs = \app\commons\Sms::send($aid,$toMember['tel'],'tmpl_sysmsg_notice',[]);
                    }
                }
            }
            $connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
        }
        if($res['type'] == 'qiuzhi'){ //求职
            $data = $res['data'];
            if($data){
                $aid = $data['aid'];
                $mid = $data['mid'];
                $bid = $data['bid']??0;
                if($data['pre_url'] && !defined('PRE_URL')){
                    define('PRE_URL',$data['pre_url']);
                }
                $minfo = Db::name('member')->where(['aid'=>$aid,'id'=>$mid])->find();
                if(!$minfo) return;
                $msgtype = $data['msgtype'];
                $qiuzhi = Db::name('zhaopin_qiuzhi')->where('aid',$aid)->where('id',$data['tbid'])->find();
                if (empty($qiuzhi)){
                    return;
                }
                if($qiuzhi['mid']==$mid){
                    $isreply = 1;
                    $tomid = $data['tomid'];//回复招聘者 招聘者的mid
                }else{
                    $isreply = 0;
                    $tomid = $qiuzhi['mid'];
                }
                $toMember =  Db::name('member')->where('aid',$aid)->where('id',$tomid)->find();
                if(empty($toMember)){
                    return;
                }
                $hasonline = 0;
                $insertdata = [];
                $insertdata['aid'] = $aid;
                $insertdata['tableid'] = $data['tbid'];
                $insertdata['tablename'] = 'zhaopin_qiuzhi';
                $insertdata['mid'] = $mid;
                $insertdata['nickname'] = $minfo['nickname'];
                $insertdata['headimg'] = $minfo['headimg'];
                $insertdata['tel'] = $minfo['tel'];
                $insertdata['tomid'] = $toMember['id'];
                $insertdata['unickname'] = $toMember['nickname'];
                $insertdata['uheadimg'] = $toMember['headimg'];
                $insertdata['msgtype'] = $msgtype;
                $insertdata['content'] = getshowcontent($data['content']);
                $insertdata['createtime'] = time();
                $insertdata['platform'] = $platform;
                $insertdata['isread'] = 0;
                $insertdata['isreply'] = $isreply;
                foreach($this->worker->connections as $con){
                    if(isset($con->aid) && $con->aid == $aid && ($con->mid == $mid || $con->mid == $tomid)){
                        if($con->mid == $tomid){
                            $hasonline = 1;
                        }
                        if($data['tbtype']==1 && $con->mid==$mid){
                            $cres = $this->chatFee($aid,$bid);
                            if($cres){
                                $con->send(json_encode(['type'=>'qiuzhi','data'=>$insertdata]));
                            }
                        }else{
                            $con->send(json_encode(['type'=>'qiuzhi','data'=>$insertdata]));
                        }
                    }
                }
                $insertdata['content'] = $data['content'];
                $insertdata['isread'] = $hasonline;
                Db::name('zhaopin_message')->insert($insertdata);
                //不在线 发消息通知
                if($hasonline==0){
                    if($msgtype == 'text'){
                        $content = $data['content'];
                    }elseif($msgtype == 'image'){
                        $content = '[图片]';
                    }elseif($msgtype == 'voice'){
                        $content = '[语音]';
                    }elseif($msgtype == 'video'){
                        $content = '[小视频]';
                    }elseif($msgtype == 'miniprogrampage'){
                        $content = json_decode($data['content']);
                        $content = '小程序页面['.$content->Title.']';
                    }else{
                        $content = $data['content'];
                    }
                    $tmplcontent = array();
                    $tmplcontent['first'] = '您有新的应聘消息';
                    $tmplcontent['keyword1'] = $minfo['nickname'];
                    $tmplcontent['keyword2'] = $minfo['tel'];
                    $tmplcontent['keyword3'] = date('Y-m-d H:i:s');
                    $tmplcontent['remark'] = '内容：'.$content.'，请点击进入查看~';

                    $tmplcontent_new = [];
					$tmplcontent_new['thing5'] = '您有新的应聘消息';
					$tmplcontent_new['thing11'] = $minfo['nickname'];

                    $rs = \app\commons\Wechat::sendtmpl($aid,$mid,'tmpl_kehuzixun',$tmplcontent,m_url('/zhaopin/zhaopin/index?bid='.$bid,$aid),$tmplcontent_new);
                    //发送短信
                    if($toMember['tel']){
                        $rs = \app\commons\Sms::send($aid,$toMember['tel'],'tmpl_sysmsg_notice',[]);
                    }
                }
            }
            $connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
        }
        if($res['type'] == 'restaurant_outfood'){
            $data = $res['data'];
          
            Log::write($data);
            $connection->ctype = 'restaurant_outfood';
            $connection->aid = $data['aid'];
            $connection->bid = $data['bid'];
            $connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
        }
        if($res['type'] == 'restaurant_outfood_create'){
            $data = $res['data'];
            foreach($this->worker->connections as $con){
                if($con->ctype == 'restaurant_outfood' && $con->aid==$data['aid'] && $con->bid==$data['bid']){
                    $con->send(json_encode(['type'=>'restaurant_outfood_create','data'=>['id'=>$data['id']]]));
                }
            }
            $connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));              
        }
        if($res['type'] == 'restaurant_outfood_call'){
            $data = $res['data'];
            foreach($this->worker->connections as $con){
                if($con->ctype == 'restaurant_outfood' && $con->aid==$data['aid'] && $con->bid==$data['bid']){
                    $con->send(json_encode(['type'=>'restaurant_outfood_call','data'=>['id'=>$data['id']]]));
                }
            }
            $connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
        }
		$connection->send(json_encode(['type'=>'response','data'=>['status'=>1]]));
    }

    /**
     * 当连接建立时触发的回调函数
     * @param $connection
     */
    public function onConnect($connection){

    }
    /**
     * 当连接断开时触发的回调函数
     * @param $connection
     */
    public function onClose($connection){
		
    }

    /**
     * 当客户端的连接上发生错误时触发
     * @param $connection
     * @param $code
     * @param $msg
     */
    public function onError($connection, $code, $msg){
        echo "error $code $msg\n";
    }
    /**
     * 每个进程启动
     * @param $worker
     */
    public function onWorkerStart($worker){

    }

	
	function send_text($aid,$platform,$content,$openid){
		$access_token = \app\commons\Wechat::access_token($aid,$platform);
		$url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$access_token;
		$data = array();
		$data['touser'] = trim($openid);
		$data['msgtype'] = 'text';
		$data['text'] = array('content'=>$content);
		$rs = curl_post($url,jsonEncode($data));
		$rs = json_decode($rs,true);
		if($rs['errcode']!=0){
			return ['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)];
		}else{
			return ['status'=>1,'msg'=>'发送成功'];
		}
	}

	function send_image($aid,$platform,$picurl,$openid){
		$access_token = \app\commons\Wechat::access_token($aid,$platform);
		$media_id = \app\commons\Wechat::pictomedia($aid,$platform,$picurl);
		$url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$access_token;
		$data = array();
		$data['touser'] = trim($openid);
		$data['msgtype'] = 'image';
		$data['image'] = array('media_id'=>$media_id);
		//Log::write($data);
		//Log::write($platform);
		$rs = curl_post($url,jsonEncode($data));
		$rs = json_decode($rs,true);
		//Log::write($rs);
		if($rs['errcode']!=0){
			return ['status'=>0,'msg'=>\app\commons\Wechat::geterror($rs)];
		}else{
			return ['status'=>1,'msg'=>'发送成功'];
		}
	}

    /**
     * 消息费扣除
     */
    public function chatFee($aid=1,$bid=0){
        if($bid>0){
            //免费额度是不是用完
            $set = \app\models\Zhaopin::getSetValue($aid,'zhaopin');
            $free_send_times = $set['free_send_times']??0;
            $chat_per_fee = $set['chat_per_fee']??0;
            if($chat_per_fee>0){
                if($free_send_times>0){
                    $mwhere = [];
                    $timeS = strtotime(date('Y-m-d 00:00:00',time()));
                    $timeE = $timeS + 86400;
                    $mwhere[] = ['createtime','between',[$timeS,$timeE]];
                    $mwhere[] = ['isreply','=',1];
                    $mwhere[] = ['bid','=',$bid];
                    $usetimes = Db::name('zhaopin_message')->where($mwhere)->count();
                    if($usetimes<$free_send_times){
                        //免费额度没用完，就不扣
                        return true;
                    }
                }
                $bmoney = Db::name('business')->where('aid',$aid)->where('id',$bid)->value('money');
                if(empty($bmoney) || $bmoney<$chat_per_fee){
                    //金额不够，不让发送消息
                    return false;
                }
                //扣除消息费
                $resd = \app\commons\Business::addmoney($aid,$bid,-$chat_per_fee,'招聘消息费扣除');
            }else{
                //未设置消息费
                return true;
            }
        }
        return true;
    }
}

/*
启动
以debug（调试）方式启动
php server.php start

以daemon（守护进程）方式启动
php server.php start -d

停止
php server.php stop

重启
php server.php restart

平滑重启
php server.php reload

查看状态
php server.php status

查看连接状态（需要Workerman版本>=3.5.0）
php server.php connections
*/