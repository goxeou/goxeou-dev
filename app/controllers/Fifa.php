<?php


// +----------------------------------------------------------------------
// | 世界杯竞猜
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Fifa extends Common
{	
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//竞猜记录
	public function record(){
		if(request()->isAjax() || input('param.excel') == 1){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.excel') == 1){
				$page = 1; $limit = 10000000000000;
			}
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
			if(input('param.nickname')) $where[] = ['nickname','like','%'.input('param.nickname').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('fifa_record')->where($where)->count();
			$list = Db::name('fifa_record')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			$fifaArr = Db::name('fifa')->where('1=1')->column('leftTeam_name,rightTeam_name,startDate,startTime','id');
			

			foreach($list as $k=>$v){
				$list[$k]['duiju'] = $fifaArr[$v['hid']]['leftTeam_name'].' vs '.$fifaArr[$v['hid']]['rightTeam_name'];
				$list[$k]['startTime'] = $fifaArr[$v['hid']]['startDate'].' '.$fifaArr[$v['hid']]['startTime'];
			}

            if(input('param.excel') == 1){
				$title = array();
				$title[] = 'ID';
				$title[] = '场次';
				$title[] = '对局';
				$title[] = t('会员').'ID';
				$title[] = '昵称';
				$title[] = '猜胜负';
				$title[] = '猜比分';
				$title[] = '猜胜负结果';
				$title[] = '猜比分结果';
				$title[] = '猜胜负获得'.t('积分');
				$title[] = '猜比分获得'.t('积分');
				$title[] = '竞猜时间';
				$data = array();
				foreach($list as $v){
					$tdata = array();
					$tdata[] = $v['id'];
					$tdata[] = $v['hid'];
					$tdata[] = $v['duiju'];
					$tdata[] = $v['mid'];
					$tdata[] = $v['nickname'];
					$tdata[] = $v['guess1'];
					$tdata[] = $v['guess2'];
					if($v['guess1st'] == 0){
						$tdata[] = '未开奖';
					}elseif($v['guess1st'] == 1){
						$tdata[] = '已猜中';
					}else{
						$tdata[] = '未猜中';
					}
					if($v['guess2st'] == 0){
						$tdata[] = '未开奖';
					}elseif($v['guess2st'] == 1){
						$tdata[] = '已猜中';
					}else{
						$tdata[] = '未猜中';
					}
					if($v['givescore1']){
						$tdata[] = $v['givescore1'];
					}else{
						$tdata[] = '';
					}
					if($v['givescore2']){
						$tdata[] = $v['givescore2'];
					}else{
						$tdata[] = '';
					}
					$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
					$data[] = $tdata;
				}
				$this->export_excel($title,$data);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		return View::fetch();
	}
	//删除
	public function recorddel(){
		$ids = input('post.ids/a');
		Db::name('fifa_record')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('竞猜记录删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//竞猜设置
	public function set(){
		if(request()->isAjax()){
			$signset = Db::name('fifa_set')->where('aid',aid)->find();
			$info = input('post.info/a');
			$guess1set = array();
			$guess1_times = input('post.guess1_times/a');
			$guess1_score = input('post.guess1_score/a');
			$guess1_coupon_id = input('post.guess1_coupon_id/a');
			$guess1_coupon_name = input('post.guess1_coupon_name/a');
			foreach($guess1_times as $k=>$v){
				$guess1set[] = array('times'=>$v,'score'=>$guess1_score[$k],'coupon_id'=>$guess1_coupon_id[$k],'coupon_name'=>$guess1_coupon_name[$k]);
			}
			$info['guess1set'] = json_encode($guess1set);
			
			$guess2set = array();
			$guess2_times = input('post.guess2_times/a');
			$guess2_score = input('post.guess2_score/a');
			$guess2_coupon_id = input('post.guess2_coupon_id/a');
			$guess2_coupon_name = input('post.guess2_coupon_name/a');
			foreach($guess2_times as $k=>$v){
				$guess2set[] = array('times'=>$v,'score'=>$guess2_score[$k],'coupon_id'=>$guess2_coupon_id[$k],'coupon_name'=>$guess2_coupon_name[$k]);
			}
			$info['guess2set'] = json_encode($guess2set);

            Db::name('fifa_set')->where('aid',aid)->update($info);
			return json(['status'=>1,'msg'=>'操作成功','url'=>true]);
		}
		$info = Db::name('fifa_set')->where('aid',aid)->find();
		if(!$info){
			$fifadata = Db::name('fifa')->where('1=1')->find();
			if(!$fifadata){
				\app\customs\Fifa::initdata();
			}
			Db::name('fifa_set')->insert(['aid'=>aid,'guize'=>'猜比分及猜胜负均以90分钟常规赛结果为准（即不包含点球大战比分）']);
			$info = Db::name('fifa_set')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
	}

	//海报设置
	public function posterset(){
		if(request()->isPost()){
			$type = input('param.type') ? input('param.type') : $this->platform[0];
			$poster_bg = input('post.poster_bg');
			$poster_data = input('post.poster_data');
			$data_index = ['poster_bg'=>$poster_bg,'poster_data'=>json_decode($poster_data)];
			$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','fifa')->where('platform',$type)->order('id')->find();
			Db::name('admin_set_poster')->where('id',$posterset['id'])->update(['content'=>json_encode($data_index)]);
			if(input('post.clearhistory') == 1){
				Db::name('member_poster')->where('aid',aid)->where('type','fifa')->where('posterid',$posterset['id'])->delete();
				$msg = '保存成功';
			}else{
				$msg ='保存成功';
			}
			return json(['status'=>1,'msg'=>$msg,'url'=>true]);
		}

		$type = input('param.type') ? input('param.type') : $this->platform[0];
		$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','fifa')->where('platform',$type)->order('id')->find();
		if(!$posterset){
			$data_product_mp = jsonEncode([
				'poster_bg' => PRE_URL.'/static/img/fifa2022/posterbg.jpg',
				'poster_data' => [
					['left' => '254px','top' => '162px','type' => 'qrmp','width' => '62px','height' => '62px','size' => '',],
					["left" => "27px","top" => "177px","type" => "text","width" => "200px","height" => "20px","size" => "18px","color" => "#FFFFFF","content" => "我已猜中 [猜中场次] 场"],
					["left" => "27px","top" => "212px","type" => "text","width" => "200px","height" => "20px","size" => "16px","color" => "#FFFFFF","content" => "我已获得 [已获得积分] 积分"],
				]
			]);
			$data_product_wx = jsonEncode([
				'poster_bg' => PRE_URL.'/static/img/fifa2022/posterbg.jpg',
				'poster_data' => [
					['left' => '254px','top' => '162px','type' => 'qrwx','width' => '62px','height' => '62px','size' => '',],
					["left" => "27px","top" => "177px","type" => "text","width" => "200px","height" => "20px","size" => "18px","color" => "#FFFFFF","content" => "我已猜中 [猜中场次] 场"],
					["left" => "27px","top" => "212px","type" => "text","width" => "200px","height" => "20px","size" => "16px","color" => "#FFFFFF","content" => "我已获得 [已获得积分] 积分"],
				]
			]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'fifa','platform'=>'mp','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'fifa','platform'=>'wx','content'=>$data_product_wx]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'fifa','platform'=>'alipay','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'fifa','platform'=>'baidu','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'fifa','platform'=>'toutiao','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'fifa','platform'=>'qq','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'fifa','platform'=>'h5','content'=>$data_product_mp]);
			Db::name('admin_set_poster')->insert(['aid'=>aid,'type'=>'fifa','platform'=>'app','content'=>$data_product_mp]);
			$posterset = Db::name('admin_set_poster')->where('aid',aid)->where('type','fifa')->where('platform',$type)->order('id')->find();
		}
		$posterdata = json_decode($posterset['content'],true);
		$poster_bg = $posterdata['poster_bg'];
		$poster_data = $posterdata['poster_data'];

		View::assign('type',$type);
		View::assign('poster_bg',$poster_bg);
		View::assign('poster_data',$poster_data);
		return View::fetch();
    }
}