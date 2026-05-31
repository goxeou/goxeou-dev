<?php


// +----------------------------------------------------------------------
// | 拓客活动 custom_file(yx_hbtk)
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\Db;
class ApiHbtkActivity extends ApiCommon
{
	public function initialize(){
		parent::initialize();
		$this->checklogin();
	}
	public function getdetail(){
		$hid = input('param.id/d');
		$pid = input('param.pid/d');
		$hd = Db::name('hbtk_activity')->where('aid',aid)->where('id',$hid)->lock(true)->find();
		if(!$hd) return $this->json(['status'=>0,'msg'=>'活动不存在']);
		if($hd['status']==0) return $this->json(['status'=>0,'msg'=>'活动未开启']);
		$member = Db::name('member')->where('aid',aid)->where('id',mid)->find();
		//增加浏览次数
        Db::name('hbtk_activity')->where('aid',aid)->where('id',$hid)->update(['viewnum' => $hd['viewnum']+1]);
         
       
		//参与列表
		$join_list = Db::name('hbtk_order')->where('aid',aid)->where('hid',$hid)->order('id desc')->limit(11)->select()->toArray();
		foreach ($join_list as  $key=>&$val){
            $str = $this->getNickname($val['nickname']);
            $val['nickname'] = $str;
        }
		//分配虚拟参与人数
        $jcount = count($join_list);
        if($jcount < 10 &&  $hd['xn_joinnum'] > 0){
            $headimglist = $this->getXnlist($jcount,$hd['xn_joinnum'],$hd,11);
            $join_list = array_merge_recursive($join_list,$headimglist);
        }
		
		//购买列表
        $buy_list =   Db::name('hbtk_order')->where('aid',aid)->where('hid',$hid)->where('status','in',[1,2])->order('id desc')->select()->toArray();
        foreach ($buy_list as  $key=>&$val){
            $str = $this->getNickname($val['nickname']);
            $val['nickname'] = $str;
            $val['createtime'] = date('Y-m-d',$val['createtime']);
        }
        $buycount = count($buy_list);
        if($jcount < 10 &&  $hd['xn_buynum'] > 0){
            $headimglist = $this->getXnlist($buycount,$hd['xn_buynum'],$hd);
            $buy_list = array_merge_recursive($buy_list,$headimglist);
        }
        //邀请排行榜
        $yq_list = Db::name('hbtk_order')->where('aid',aid)->where('hid',$hid)->group('mid')->order('id asc')->limit(10)->select()->toArray();
        foreach($yq_list as $k=>&$v){
               $v['yqnum'] = 0 + Db::name('hbtk_order')->where('aid',aid)->where('pid',$v['mid'])->where('hid',$hid)->count();
               if($v['yqnum'] < 1){
                   unset($yq_list[$k]);
                   continue;
               }
               $v['jxmoney'] = 0+ Db::name('hbtk_order')->where('aid',aid)->where('mid',$v['mid'])->where('hid',$hid)->sum('jxmoney');
            $str = $this->getNickname($val['nickname']);
            $v['nickname'] = $str;
        }
        $sort = array_column($yq_list,'yqnum');
        array_multisort($sort,SORT_DESC,SORT_REGULAR ,$yq_list);
        //参与人数
        $join_count =   Db::name('hbtk_order')->where('hid',$hid)->count();
        //分享人数
        $share_cout = Db::name('hbtk_sharelog')->where('aid',aid)->where('hid',$hid)->count();
        $buy_count =   Db::name('hbtk_order')->where('hid',$hid)->where('status','in',[1,2])->count();
        $hd['buy_count'] = $hd['xn_buynum']+$buy_count;//虚拟+真实
        $hd['share_count'] =$hd['xn_zfnum'] +$share_cout; //虚拟+真实
        $hd['join_count'] =$join_count + $hd['xn_joinnum']; //虚拟+真实
        $hd['viewnum'] =  $hd['xn_bgnum'] +  $hd['viewnum'];   //虚拟+真实
		$rdata = [];
		$rdata['info'] 		= $hd;
		$rdata['join_list'] 	= $join_list;
        $rdata['buy_list'] 	= $buy_list;
        $rdata['yq_list'] 	= $yq_list;
		$rdata['member'] 	= ['realname'=>$member['realname'],'tel'=>$member['tel'],'score'=>$member['score'],'money'=>$member['money']];
		$this-> setRecord($hid,$pid);
		return $this->json($rdata);
	}
	public function setRecord($hid,$pid=0){
	    $record = Db::name('hbtk_order')->where('aid',aid)->where('mid',mid)->where('hid',$hid)->find();
	    $hbtk = Db::name('hbtk_activity')->where('aid',aid)->where('id',$hid)->find();
	    if(!$record){
	        $insert=[
	            'aid' => aid,
                'bid' => $hbtk['bid'],
                'hid' => $hid,
                'name' => $hbtk['name'],
                'mid'  => mid,
                'pid' =>$pid,
                'headimg' => $this->member['headimg'],
                'nickname' => $this->member['nickname'],
                'status' => 0,
                'createtime' => time(),
                'code' =>  random(16),
                'price' => $hbtk['price'],
                'ordernum' =>date('ymdHis').aid.rand(1000,9999) 
            ];
             $insert['hexiaoqr'] = createqrcode(m_url('admin/hexiao/hexiao?type=hbtk&co='.$insert['code']));
            $record =  Db::name('hbtk_order')->insert($insert);
        }
	    //二维码分享进入的用户 加入分享日志
        $sharelog = Db::name('hbtk_sharelog')->where('aid',aid)->where('hid',$hid)->where('mid',$pid)->find();
        if(!$sharelog && $pid){
            $data = [];
            $data['aid'] = aid;
            $data['hid'] = $hid;
            $data['mid'] = $pid;
            $data['createtime'] = time();
            Db::name('hbtk_sharelog')->insert($data);
        }
       return $record;
    }
    public function getXnlist($count,$xnnum,$hd,$showcout = 12){
        //如果加上虚拟的总的人数 不够12个，生成个数 = 总人数-实际存在的   否则就是生成个数 = 12- 实际存在 
        $diff =  $showcout-$count;
        $xnjoin =$diff > $xnnum?$xnnum:$diff;

        //查询虚拟头像库
        $order = 'rand('.rand(0,100).')';
        $headimglist = Db::name('headimg_upload')->limit($xnjoin)->orderRaw($order)->where('aid',aid)->select()->toArray();

        foreach ($headimglist as $k=>&$v){
            $nickname = getRandomNickname(rand(1,2));
            $str = $this->getNickname($nickname);
            $v['nickname'] = $str;
            $v['price']  = $hd['price'];
            $v['headimg']  = $v['url'];
            $ct =time()-rand(100,99999);
            $v['createtime']  = date('Y-m-d',$ct);
        }
        return $headimglist;
    }
    //商品海报
    function getposter(){
        $this->checklogin();
        $post = input('post.');
        $platform = platform;
        $page = '/pagesExt/hbtk/index';
        $scene = 'id_'.$post['proid'].'-pid_'.$this->member['id'];
        //if($platform == 'mp' || $platform == 'h5' || $platform == 'app'){
        //	$page = PRE_URL .'/h5/'.aid.'.html#'. $page;
        //}
        $posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','hbtk')->where('platform',$platform)->order('id')->find();

        $posterdata = Db::name('member_poster')->where('aid',aid)->where('mid',mid)->where('scene',$scene)->where('type','hbtk')->where('posterid',$posterset['id'])->find();
        if(!$posterdata){
            $product = Db::name('hbtk_activity')->where('id',$post['proid'])->find();
            $sysset = Db::name('admin_set')->where('aid',aid)->find();
            $textReplaceArr = [
                '[头像]'=>$this->member['headimg'],
                '[昵称]'=>$this->member['nickname'],
                '[姓名]'=>$this->member['realname'],
                '[手机号]'=>$this->member['mobile'],
                '[商家名称]'=>$sysset['name'],
                '[活动名称]'=>$product['name'],
                '[活动价格]'=>$product['price'],
                '[商品图片]'=>$product['fmpic'],
            ];
            $poster = $this->_getposter(aid,$product['bid'],$platform,$posterset['content'],$page,$scene,$textReplaceArr);
            $posterdata = [];
            $posterdata['aid'] = aid;
            $posterdata['mid'] = $this->member['id'];
            $posterdata['scene'] = $scene;
            $posterdata['page'] = $page;
            $posterdata['type'] = 'hbtk';
            $posterdata['poster'] = $poster;
            $posterdata['createtime'] = time();
            Db::name('member_poster')->insert($posterdata);
        }
        return $this->json(['status'=>1,'poster'=>$posterdata['poster']]);
    }
    public function getNickname($nickname){
        $len = mb_strlen($nickname, 'UTF-8');
        if($len >= 3){
            //三个字符或三个字符以上掐头取尾，中间用*代替
            $str = mb_substr($nickname, 0, 1, 'UTF-8') . '*' . mb_substr($nickname, -1, 1, 'UTF-8');
        } elseif($len == 2) {
            //两个字符
            $str = mb_substr($nickname, 0, 1, 'UTF-8') . '*';
        }else{
            $str = $nickname.'*';
        }
        return $str;
    }
    /**
     * [qrcode_ 在二维码的中间区域镶嵌图片]
     * @param  [type] $QR   [背景图片。比如file_get_contents(imageurl)返回的数据,或者微信给返回的数据]
     * @param  [type] $logo [中间显示图片的数据流。比如file_get_contents(imageurl)返回的东东]
     * @return [type]       [返回图片数据流]
     */
    public  function qrcode_with_logo($QR, $logo)
    {
        $QR = imagecreatefromstring($QR);
       
        $logo = imagecreatefromstring($logo);
        $QR_width = imagesx($QR); // 二维码图片宽度
        $QR_height = imagesy($QR); // 二维码图片高度
        $logo_width = imagesx($logo); // logo图片宽度
        $logo_height = imagesy($logo); // logo图片高度
        $logo_qr_width = $QR_width / 2.2; // 组合之后logo的宽度(占二维码的1/2.2)
        $scale = $logo_width / $logo_qr_width; // logo的宽度缩放比(本身宽度/组合后的宽度)
        $logo_qr_height = $logo_height / $scale; // 组合之后logo的高度
        $from_width = ($QR_width - $logo_qr_width) / 2; // 组合之后logo左上角所在坐标点
        /**
         * 重新组合图片并调整大小
         * imagecopyresampled() 将一幅图像(源图象)中的一块正方形区域拷贝到另一个图像中
         */
        imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);
        /**
         * 如果想要直接输出图片，应该先设header。header("Content-Type: image/png; charset=utf-8");
         * 并且去掉缓存区函数
         */
        //获取输出缓存，否则imagepng会把图片输出到浏览器
        ob_start();
        imagepng($QR);
        imagedestroy($QR);
        imagedestroy($logo);
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
	//分享
	public function share(){
		$hid = input('param.hid/d');
		$hd = Db::name('hbtk_activity')->where('aid',aid)->where('id',$hid)->find();
		if(!$hd) return $this->json(['status'=>0,'msg'=>'活动不存在']);
		if($hd['status']==0) return $this->json(['status'=>0,'msg'=>'活动未开启']);
		$sharelog = Db::name('hbtk_sharelog')->where('aid',aid)->where('hid',$hid)->where('mid',mid)->find();
		$status = 0;
		if(!$sharelog){
			$data = [];
			$data['aid'] = aid;
			$data['hid'] = $hid;
			$data['mid'] = mid;
			$data['createtime'] = time();
			Db::name('hbtk_sharelog')->insert($data);
		}
		return $this->json(['status'=>$status,'msg'=>'']);
	}
    //下单
    public function createOrder(){
        $hid = input('param.id/d');
        $pid = input('param.pid/d');
        $hd = Db::name('hbtk_activity')->where('aid',aid)->where('id',$hid)->find();
        if(!$hd){
            return $this->json(['status'=>0,'msg'=>'活动不存在']); 
        }
        if($hd['status'] == 0){
            return $this->json(['status'=>0,'msg'=>'活动未开启']);
        }
        if($hd['starttime'] > time()){
            return $this->json(['status'=>0,'msg'=>'活动未开始']);
        }
        if($hd['endtime'] < time()){
            return $this->json(['status'=>0,'msg'=>'活动已结束']);
        }
        if($hd['fanwei'] == 1){
            $juli = getdistance(input('post.longitude'),input('post.latitude'),$hd['fanwei_lng'],$hd['fanwei_lat'],2);
            if($juli > $hd['fanwei_range']/1000){
                return $this->json(['status'=>0,'msg'=>'超出活动范围']);
            }
        }
        $gettj = explode(',',$hd['gettj']);
        if(!in_array('-1',$gettj) && !in_array($this->member['levelid'],$gettj)){ //不是所有人
            if(in_array('0',$gettj)){ //关注用户才能领
                if($this->member['subscribe']!=1){
                    $appinfo = \app\commons\System::appinfo(aid,'mp');
                    return $this->json(['status'=>0,'msg'=>'请先关注'.$appinfo['nickname'].'公众号']);
                }
            }else{
                return $this->json(['status'=>0,'msg'=>'您没有参与权限']);
            }
        }
        $record = Db::name('hbtk_order')->where('hid',$hid)->where('mid',mid)->find();
        if($record && $record['status'] ==0){
            Db::name('hbtk_order')->where('hid',$hid)->where('mid',mid)->update(['price' => $hd['price']]);
        }
        if(!$record){
           $record =  $this->setRecord($hid,$pid);
        }
        if($record['status'] ==1){
            return $this->json(['status'=>0,'msg'=>'活动参加中']);
        }   
        if($record['status'] ==2){
            return $this->json(['status'=>0,'msg'=>'活动已核销']);
        }
        if($hd['price'] >0){
            Db::name('hbtk_order')->where('hid',$hid)->update(['price' =>$hd['price']]);
            $payorder = Db::name('payorder')->where('aid',aid)->where('bid',$hd['bid'])->where('ordernum',$record['ordernum'])->where('status',0)->where('type','hbtk')->find();
            if($payorder){
                  $update = ['money' => $hd['price'] ];
                  Db::name('payorder')->where('id',$payorder['id'])->update($update);
                $payorderid =   $payorder['id'];
            }else{
                $payorderid = \app\models\Payorder::createorder(aid,$hd['bid'],mid,'hbtk',$record['id'],$record['ordernum'],$record['name'],$hd['price']);
            }
            return $this->json(['status'=>1,'payorderid'=>$payorderid,'msg'=>'提交成功']);
        }else{
            $data = $this->hbtk_pay($record['id']);
            $data['status'] =1;
            Db::name('hbtk_order')->where('hid',$hid)->update($data);
          
            return $this->json(['status'=>1,'payorderid'=>0,'msg'=>'提交成功']);
        }
       
    }
    public function hbtk_pay($orderid){
        $hid = Db::name('hbtk_order')->where('id',$orderid)->value('hid');
        $hd = Db::name('hbtk_activity')->where('id',$hid)->find();
        //发红包 和产生佣金
        if($hd['j1yj'] > $hd['j1sl']) $hd['j1yj'] = $hd['j1sl'];
        if($hd['j2yj'] > $hd['j2sl']) $hd['j2yj'] = $hd['j2sl'];
        if($hd['j3yj'] > $hd['j3sl']) $hd['j3yj'] = $hd['j3sl'];
        if($hd['j4yj'] > $hd['j4sl']) $hd['j4yj'] = $hd['j4sl'];
        if($hd['j5yj'] > $hd['j5sl']) $hd['j5yj'] = $hd['j5sl'];
        if($hd['j6yj'] > $hd['j6sl']) $hd['j6yj'] = $hd['j6sl'];
        if($hd['j7yj'] > $hd['j7sl']) $hd['j7yj'] = $hd['j7sl'];
        if($hd['j8yj'] > $hd['j8sl']) $hd['j8yj'] = $hd['j8sl'];
        if($hd['j9yj'] > $hd['j9sl']) $hd['j9yj'] = $hd['j9sl'];
        if($hd['j10yj'] > $hd['j10sl']) $hd['j10yj'] = $hd['j10sl'];
        if($hd['j11yj'] > $hd['j11sl']) $hd['j11yj'] = $hd['j11sl'];
        if($hd['j12yj'] > $hd['j12sl']) $hd['j12yj'] = $hd['j12sl'];
        $count =  ($hd['j1sl'] - $hd['j1yj']) + ($hd['j2sl'] - $hd['j2yj']) + ($hd['j3sl'] - $hd['j3yj']) + ($hd['j4sl'] - $hd['j4yj']) + ($hd['j5sl'] - $hd['j5yj']) + ($hd['j6sl'] - $hd['j6yj']) + ($hd['j7sl'] - $hd['j7yj']) + ($hd['j8sl'] - $hd['j8yj']) + ($hd['j9sl'] - $hd['j9yj']) + ($hd['j10sl'] - $hd['j10yj']) + ($hd['j11sl'] - $hd['j11yj']) + ($hd['j12sl'] - $hd['j12yj']);

        if($count>0){
            $jparr = [
                ($hd['j1sl'] - $hd['j1yj']),
                ($hd['j2sl'] - $hd['j2yj']),
                ($hd['j3sl'] - $hd['j3yj']),
                ($hd['j4sl'] - $hd['j4yj']),
                ($hd['j5sl'] - $hd['j5yj']),
                ($hd['j6sl'] - $hd['j6yj']),
                ($hd['j7sl'] - $hd['j7yj']),
                ($hd['j8sl'] - $hd['j8yj']),
                ($hd['j9sl'] - $hd['j9yj']),
                ($hd['j10sl'] - $hd['j10yj']),
                ($hd['j11sl'] - $hd['j11yj']),
                ($hd['j12sl'] - $hd['j12yj']),
            ];
            $rands = rand(1,$count);
            $qian = 0;
            foreach ($jparr as $k=>$v) {
                if($rands > $qian && $rands <= $qian + $v){
                    $jx = $k+1;
                    $jxmc = $hd["j{$jx}mc"];
                    $jxtp = $hd["j{$jx}tp"];
                    break;
                }
                $qian += $v;
            }
        }
        $data = [];
        $data['jx'] = $jx;
        $data['jxtp'] = $jxtp;
        $data['jxmc'] = $jxmc;
        return $data;
    }
    public function orderlist(){
        $st = input('param.st');
        if(!$st && $st!=='0') $st = 'all';
        $pagenum = input('param.pagenum') ? input('param.pagenum') : 1;
        $where = [];
        $where[] = ['aid','=',aid];
        $where[] = ['mid','=',mid];
        if(input('param.keyword')) $where[] = ['ordernum|name', 'like', '%'.input('param.keyword').'%'];
        if($st == 'all'){
            $where[] = ['status','in',[1,2]];
        }elseif($st == '1'){
            $where[] = ['status','=',1];
        }elseif($st == '2'){
            $where[] = ['status','=',2];
        }
        $datalist = Db::name('hbtk_order')->where($where)->page($pagenum,10)->select()->toArray();
        if(!$datalist) $datalist = [];
        foreach($datalist as $key=>&$v){   
            $hb = Db::name('hbtk_activity')->where('id',$v['hid'])->find();
            $v['pic'] = $hb['fmpic'];
            $v['yqnum'] = 0 + Db::name('hbtk_order')->where('aid',aid)->where('pid',$v['mid'])->where('hid',$v['hid'])->count();
        }
        return $this->json(['status'=>1,'msg'=>'','datalist' => $datalist]);
    }
    //详情
    public function orderdetail(){
        $detail = Db::name('hbtk_order')->where('id',input('param.id/d'))->where('aid',aid)->where('mid',mid)->find();
        if($detail){
            $detail['createtime'] = date('Y-m-d H:i:s',$detail['createtime']);
            $detail['hxtime'] = date('Y-m-d H:i:s',$detail['hxtime']);
            $hb = Db::name('hbtk_activity')->where('id',$detail['hid'])->find();
            $detail['pic'] =$hb['fmpic'];
            $detail['yqnum'] = 0 + Db::name('hbtk_order')->where('aid',aid)->where('pid',$detail['mid'])->where('hid',$detail['hid'])->count();
            $yqlist =  Db::name('hbtk_order')->where('aid',aid)->where('hid',$detail['hid'])->where('pid',$detail['mid'])->where('status','in',[1,2])->select()->toArray();
            $detail['yqlist'] = $yqlist?$yqlist:[];
        }
        $rdata['detail'] = $detail? $detail:[];
        return $this->json($rdata);
    }
}