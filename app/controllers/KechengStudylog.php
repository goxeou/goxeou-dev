<?php


// +----------------------------------------------------------------------
// | 课程-学习记录
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class KechengStudylog extends Common
{
    public function initialize(){
        parent::initialize();
        $this->defaultSet();
    }
	//订单列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			if($this->mdid){
				$where[] = ['mdid','=',$this->mdid];
			}
			if(input('?param.status') && input('param.status')!==''){
				if(input('param.status') == 5){
					$where[] = ['refund_status','=',1];
				}elseif(input('param.status') == 6){
					$where[] = ['refund_status','=',2];
				}elseif(input('param.status') == 7){
					$where[] = ['refund_status','=',3];
				}else{
					$where[] = ['status','=',input('param.status')];
				}
			}
			$count = 0 + Db::name('kecheng_studylog')->where($where)->count();
			$list = Db::name('kecheng_studylog')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			foreach($list as $k=>$vo){
				$member = Db::name('member')->where('id',$vo['mid'])->find();
				$kecheng = Db::name('kecheng_list')->field('name')->where('id',$vo['kcid'])->find();
				$chapter = Db::name('kecheng_chapter')->field('name')->where('id',$vo['mlid'])->find();

				$list[$k]['chaptername'] = $chapter['name'];
				$list[$k]['kecheng'] = $kecheng['name'];
				$list[$k]['headimg'] = $member['headimg'];
				$list[$k]['nickname'] = $member['nickname'];
				$list[$k]['platform'] = getplatformname($vo['platform']);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$list]);
		}
		$where = [];
		if(input('param.')) $where = input('param.');
		$where = json_encode($where);
		View::assign('where',$where);
		return View::fetch();
    }
	//导出
	public function excel(){
		set_time_limit(0);
		ini_set('memory_limit', '2000M');
		if(input('param.field') && input('param.order')){
			$order = input('param.field').' '.input('param.order');
		}else{
			$order = 'id desc';
		}
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['bid','=',bid];
		if($this->mdid){
			$where[] = ['mdid','=',$this->mdid];
		}
		if(input('param.proname')) $where[] = ['proname','like','%'.input('param.proname').'%'];
		if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
		if(input('param.linkman')) $where[] = ['linkman','like','%'.input('param.linkman').'%'];
		if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['createtime','>=',strtotime($ctime[0])];
			$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('?param.status') && input('param.status')!==''){
			if(input('param.status') == 5){
				$where[] = ['refund_status','=',1];
			}elseif(input('param.status') == 6){
				$where[] = ['refund_status','=',2];
			}elseif(input('param.status') == 7){
				$where[] = ['refund_status','=',3];
			}else{
				$where[] = ['status','=',input('param.status')];
			}
		}
		$list = Db::name('kecheng_order')->where($where)->order($order)->select()->toArray();
		$title = array('订单号','下单人','课程名称','课程价格','实付款','支付方式','下单时间');
		$data = [];
		foreach($list as $k=>$vo){
			$member = Db::name('member')->where('id',$vo['mid'])->find();
			$status='';
			if($vo['status']==0){
				$status = '未支付';
			}elseif($vo['status']==1){
				$status = '已支付';
			}
			$data[] = [
				' '.$vo['ordernum'],
				$member['nickname'],
				$vo['title'],
				$vo['product_price'],
				$vo['totalprice'],
				$vo['paytype'],
				date('Y-m-d H:i:s',$vo['createtime']),
				$status
			]; 
		}
		$this->export_excel($title,$data);
	}


	//删除
	public function del(){
		$id = input('post.ids/a');

		if(is_array($id)){
			Db::name('kecheng_studylog')->where('aid',aid)->where('bid',bid)->where('id','in',$id)->delete();
		}else{
			Db::name('kecheng_studylog')->where('aid',aid)->where('bid',bid)->where('id',intval($id))->delete();
		}
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    function defaultSet(){
        $set = Db::name('kecheng_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('kecheng_sysset')->insert(['aid'=>aid]);
        }
    }
}
