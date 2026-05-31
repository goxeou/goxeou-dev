<?php


// +----------------------------------------------------------------------
// | 知识付费-答题记录 author:lmy 
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class KechengRecord extends Common
{
	//答题记录
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			$where = array();
			$where[] = ['aid','=',aid];
			$where[] = ['kcid','=',input('param.kcid')];
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['bid','<>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
				}else{
					$where[] = ['bid','=',0];
				}
			}else{
				$where[] = ['bid','=',bid];
			}
			if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];
			if(input('param.mid'))  $where[] = ['mid','=',$_GET['mid']];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];

			$count = 0 + Db::name('Kecheng_record')->where($where)->count();
			$data = Db::name('Kecheng_record')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$data[$k]['kcname'] = Db::name('kecheng_list')->where('aid',aid)->where('id',$v['kcid'])->value('name');
				$member = Db::name('member')->field('nickname,headimg')->where('aid',aid)->where('id',$v['mid'])->find();
				$data[$k]['nickname'] = $member['nickname'];
				$data[$k]['headimg'] = $member['headimg'];
				if($v['bid'] > 0){
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['bname'] = '平台自营';
				}
				}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);
		View::assign('sysset',$sysset);
		View::assign('systimeset',$systimeset);

		return View::fetch();
    }
	
	//答题详情
    public function logs(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			$where = array();
			$where[] = ['aid','=',aid];
			$where[] = ['recordid','=',input('param.rid')];
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['bid','<>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
				}else{
					$where[] = ['bid','=',0];
				}
			}else{
				$where[] = ['bid','=',bid];
			}
			$count = 0 + Db::name('Kecheng_recordlog')->where($where)->count();
			$data = Db::name('Kecheng_recordlog')->where($where)->page($page,$limit)->order('id desc')->select()->toArray();
			
			$optionarr = ['A','B','C','D','E','F','G'];
			foreach($data as $k=>$v){
				$tiku = Db::name('kecheng_tiku')->where('aid',aid)->where('id',$v['tmid'])->find();
				$data[$k]['title'] = $tiku['title'];
				$tkright_option = explode(',',$tiku['right_option']);
				$right_option = json_decode($v['answer'],true);
                
				if($tiku['type']==1){
				    
					if(count($tkright_option)>1){
                       
						$right_options = array();
						foreach($right_option as $d){
							$right_options[] = $optionarr[$d];
						}                       
						
						$data[$k]['answer'] = implode(',',$right_options);
					}else{
						$data[$k]['answer'] = $optionarr[$v['answer']];
					}
				}
			}
		
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);
		View::assign('sysset',$sysset);
		View::assign('systimeset',$systimeset);
		return View::fetch();
    }
	
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('kecheng_record')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		Db::name('kecheng_recordlog')->where('aid',aid)->where('bid',bid)->where('recordid','in',$ids)->delete();
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	

    public function changehg(){
        }
}
