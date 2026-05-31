<?php


// +----------------------------------------------------------------------
// | 抽奖活动
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use ZipArchive;

class Choujiang extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
			//复制商品
	public function procopy(){
        $where = [];
        if(bid > 0){
            $where[] = ['bid','=',bid];
        }
		$product = Db::name('choujiang')->where('aid',aid)->where($where)->where('id',input('post.id/d'))->find();
		if(!$product) return json(['status'=>0,'msg'=>'活动不存在,请重新选择']);
	
		$data = $product;
		$data['name'] = '复制-'.$data['name'];
        for ($i = 0; $i < 13; $i++) {
            $data['j'.$i.'yj'] = 0;
        }
		$data['starttime'] = strtotime(date('Y-m-d',time()));
		$data['endtime'] = strtotime(date('Y-m-d',time()))+7*86399;
       	$data['createtime'] = time();
       	$data['updatetime'] = time();
		unset($data['id']);
		unset($data['cjnum']);
		unset($data['zjnum']);
		$data['status'] = 0;
		$newproid = Db::name('choujiang')->insertGetId($data);
		\app\commons\System::plog('抽奖活动复制'.$newproid);
		return json(['status'=>1,'msg'=>'复制成功','proid'=>$newproid]);
	}
	//活动列表
	public function index(){
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
			$count = 0 + Db::name('choujiang')->where($where)->count();
			$data = Db::name('choujiang')->where($where)->page($page,$limit)->order($order)->select()->toArray();

			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('choujiang')->where('aid',aid)->where('id',input('param.id/d'))->find();
            $info['usescore'] = dd_money_format($info['usescore'],$this->score_weishu);
		}else{
			$info = array(
				'id'=>'',
				'guize'=>'1.活动时间：'.date('Y年m月d日').'——'.date('Y年m月d日',time()+86400).'。
2.活动期间，每人每天可参与抽奖3次。
3.本次活动奖品为奖品一、奖品二、奖品三。
4.实物奖品请到指定地点领取；红包奖品将直接发放微信红包，请注意领取；优惠券将直接发放到您的个人账户请注意查收。',
				'pertotal'=>'3',
				'perday'=>'3',
				'shareaddnum'=>'0',
				'sharedaytimes'=>'0',
				'sharetimes'=>0,
				'starttime'=>time()-100,
				'endtime'=>time()+86400-100,
				'use_type'=>1,
				'usescore'=>0,
				'usemoney'=>0,
				'status'=>1,
				'sharepic'=>'',
				'gettj'=>'-1',
				'j0mc'=>'谢谢参与',
				'j0pic'=>PRE_URL.'/static/img/dzp/face.png',
				'j0sl'=>'55',
				'j0yj'=>'0',
				'j1mc'=>'奖品一',
				'j1pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
				'j1sl'=>'5',
				'j1yj'=>'0',
				'j2mc'=>'奖品二',
				'j2pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
				'j2sl'=>'10',
				'j2yj'=>'0',
				'j3mc'=>'奖品三',
				'j3pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
				'j4pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
				'j5pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
				'j6pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
				'j7pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
				'j8pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
				'j3sl'=>'30',
				'j3yj'=>'0',
				'formcontent'=>'[{"key":"input","val1":"姓名","val2":"","val3":"1"},{"key":"input","val1":"手机号","val2":"","val3":"1"}]',

			);
			if(input('param.type')=='dzp'){
				$info['type'] = 'dzp';
				$info['name'] = '幸运大转盘活动开始啦';
				$info['banner'] = PRE_URL.'/static/img/dzp/title.png';
				$info['bgpic'] = PRE_URL.'/static/img/dzp/bg.png';
                $info['bgcolor']='#F58D40';
			}
			if(input('param.type')=='ggk'){
				$info['type'] = 'ggk';
				$info['name'] = '刮刮卡活动开始啦';
				$info['banner'] = PRE_URL.'/static/img/ggk/title.png';
                $info['bgcolor']='#C40004';
			}
		}
		$info['gettj'] = explode(',',$info['gettj']);
		View::assign('info',$info);

		$default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
		View::assign('memberlevel',$memberlevel);
		View::assign('score_weishu',$this->score_weishu);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$info['starttime'] = strtotime($info['starttime']);
		$info['endtime'] = strtotime($info['endtime']);
		$info['gettj'] = implode(',',$info['gettj']);
		$datatype = input('post.datatype/a');
		$dataval1 = input('post.dataval1/a');
		$dataval2 = input('post.dataval2/a');
		$dataval3 = input('post.dataval3/a');
		$dhdata = array();
		foreach($datatype as $k=>$v){
			if($dataval3[$k]!=1) $dataval3[$k] = 0;
			$dhdata[] = array('key'=>$v,'val1'=>$dataval1[$k],'val2'=>$dataval2[$k],'val3'=>$dataval3[$k]);
		}
		$info['formcontent'] = jsonEncode($dhdata);

		if($info['id']){
			$info['updatetime'] = time();
			Db::name('choujiang')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑抽奖活动'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('choujiang')->insertGetId($info);
			\app\commons\System::plog('添加抽奖活动'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('choujiang')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除抽奖活动'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//领取记录
	public function record(){
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
			if(input('param.hid')){
				$where[] = ['hid','=',input('param.hid/d')];
			}
			if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
			if(input('param.nickname')) $where[] = ['nickname','like','%'.input('param.nickname').'%'];
			if(input('param.linkman')) $where[] = ['formdata','like','%'.input('param.linkman').'%'];
			if(input('param.jxmc')) $where[] = ['jxmc','like','%'.input('param.jxmc').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			if(input('?param.status') && input('param.status')!==''){
				$where[] = ['status','=',input('param.status')];
				if(input('param.status') == 0){
					$where[] = ['jx','<>',0];
				}
			}
			$count = 0 + Db::name('choujiang_record')->where($where)->count();
			$data = Db::name('choujiang_record')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$formdataArr = array();
				$formdata = json_decode($v['formdata'],true);
				foreach($formdata as $k2=>$v2){
				    if(is_array($v2)){
				        $v2 = implode(',',$v2);
                    }
					$formdataArr[] = $k2.'：'.$v2;
				}
				$data[$k]['formdata'] = implode('<br>',$formdataArr);
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	//改状态
	public function setst(){
		$ids = input('post.ids/a');
		$st = input('post.st/d');
		Db::name('choujiang_record')->where('aid',aid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('修改抽奖记录状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'修改成功']);
	}
	//领取记录导出
	public function recordexcel(){
        $page = input('param.page');
        $limit = input('param.limit');
		$where = [];
		$where[] = ['aid','=',aid];
		if(input('param.hid')){
			$where[] = ['hid','=',input('param.hid/d')];
		}
		if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
		if(input('param.nickname')) $where[] = ['nickname','like','%'.input('param.nickname').'%'];
		if(input('param.linkman')) $where[] = ['formdata','like','%'.input('param.linkman').'%'];
		if(input('param.jxmc')) $where[] = ['jxmc','like','%'.input('param.jxmc').'%'];
		if(input('param.ctime') ){
			$ctime = explode(' ~ ',input('param.ctime'));
			$where[] = ['createtime','>=',strtotime($ctime[0])];
			$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
		}
		if(input('?param.status') && input('param.status')!==''){
			$where[] = ['status','=',input('param.status')];
			if(input('param.status') == 0){
				$where[] = ['jx','<>',0];
			}
		}
		$list = Db::name('choujiang_record')->where($where)->page($page,$limit)->select()->toArray();
        $count = Db::name('choujiang_record')->where($where)->count();

		$title = array();
		$title[] = '序号';
		$title[] = '活动ID';
		$title[] = '活动名称';
		$title[] = t('会员').'ID';
		$title[] = '昵称';
		$title[] = '奖品';
		$title[] = '兑奖信息';
		$title[] = '领取时间';
		$title[] = '状态';
		$title[] = '备注';
		$data = array();
	
		foreach($list as $v){
            $formdataArr = [];
		    $formdata = json_decode($v['formdata'],true);
		    if($formdata){
                foreach ($formdata as $key=>$val) {
                    $formdataArr[] = $key.'：'.$val;
                }
            }
            
            $formdatastr = implode("\r\n",$formdataArr);
			$tdata = array();
			$tdata[] = $v['id'];
			$tdata[] = $v['hid'];
			$tdata[] = $v['name'];
			$tdata[] = $v['mid'];
			$tdata[] = $v['nickname'];
			$tdata[] = $v['jxmc'];
//			$tdata[] = $v['linkman'] ? $v['linkman'].'('.$v['tel'].')':'';
            $tdata[] = $formdatastr;
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$status = '';
			if($v['jx']==0){
				$status = '未中奖';
			}elseif($v['status']==1){
				$status = '已领取';
			}elseif($v['status']==0){
				$status = '未领取';
			}
			$tdata[] = $status;
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//删除
	public function recorddel(){
		$ids = input('post.ids/a');
		Db::name('choujiang_record')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除抽奖记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
    public function redpacklog()
    {
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
            if(input('param.ordernum')) $where[] = ['ordernum','like','%'.input('param.ordernum').'%'];
            $count = 0 + Db::name('sendredpack_log')->where($where)->count();
            $data = Db::name('sendredpack_log')->where($where)->page($page,$limit)->order($order)->select()->toArray();

            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    public function redpackinfo(){
        $id = input('post.id');
        $log = Db::name('sendredpack_log')->where('aid',aid)->where('id','=',$id)->find();
        $info = \app\commons\Wxpay::gethbinfo($log);

        return json(['status'=>1,'msg'=>'','data'=>$info]);
    }
}