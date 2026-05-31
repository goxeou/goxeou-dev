<?php


namespace app\controllers;
use think\facade\Db;
class ApiCashback extends ApiCommon
{

    public function initialize(){
        parent::initialize();
		$this->checklogin();
    }
    public function index(){
        $where = [];
        $where[] = ['cashback_member.aid','=',aid];
        $where[] = ['cashback_member.mid','=',mid];
        $pernum = 20;
        $pagenum = input('post.pagenum');
        $status = input('post.status');
        $time = time();
        if($status == 1){
            $where[] = ['c.starttime','<',$time];
            $where[] = ['c.endtime','>',$time];
        }elseif($status == 2){
            $where[] = ['c.endtime','<',$time];
        }
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('cashback_member')->alias('cashback_member')->field('cashback_member.*,c.name,c.starttime,c.endtime')->join('cashback c','c.id=cashback_member.cashback_id')->where($where)->page($pagenum,$pernum)->order('cashback_member.id desc')->select()->toArray();
        
        foreach($datalist as &$v){
            if($v['starttime'] > $time){
                $v['status'] = '未开始';
            }elseif($v['endtime'] < $time){
                $v['status'] = '已结束';
            }else{
                $v['status'] = '进行中';
            }
            $cashback_num = 0;
            $v['back_type_name'] = '额度';    
            if($v['back_type'] == 1){
                $cashback_num = $v['cashback_money'];                
                $v['back_type_name'] = t('余额');                
            }elseif($v['back_type'] == 2){
                $cashback_num = $v['commission'];
                $v['back_type_name'] = t('佣金'); 
            }elseif($v['back_type'] == 3){
                $cashback_num = $v['score'];
                $v['back_type_name'] = t('积分');    
            }
            $v['cashback_num'] = $cashback_num;
            $v['progress'] = $v['cashback_money_max']>0 && $cashback_num >0 ?round($cashback_num/$v['cashback_money_max']*100,2):0;
        }
        $rdata = [];
        $rdata['status']   = 1;
        $rdata['datalist'] = $datalist;
        return $this->json($rdata);
    }

    public function recordlog(){
        $where = [];
        $where[] = ['cml.aid','=',aid];
        $where[] = ['cml.mid','=',mid];
        $pernum = 20;
        $pagenum = input('post.pagenum');
        $cashback_id = input('post.cashback_id');
        $pro_id = input('post.pro_id');
        $where[] = ['cml.pro_id','=',$pro_id];
        $where[] = ['cml.cashback_id','=',$cashback_id];
        $time = time();
        if(!$pagenum) $pagenum = 1;
        $datalist = Db::name('cashback_member_log')->alias('cml')->field('cml.*,c.name,c.starttime,c.endtime')->join('cashback c','c.id=cml.cashback_id')->where($where)->page($pagenum,$pernum)->order('cml.id desc')->select()->toArray();
        
        foreach($datalist as &$v){
            $v['back_type_name'] = '额度';    
            $cashback_num = 0;
            if($v['back_type'] == 1){
                $cashback_num = $v['cashback_money'];                
                $v['back_type_name'] = t('余额');                
            }elseif($v['back_type'] == 2){
                $cashback_num = $v['commission'];
                $v['back_type_name'] = t('佣金'); 
            }elseif($v['back_type'] == 3){
                $cashback_num = $v['score'];
                $v['back_type_name'] = t('积分');  
            }
            $v['cashback_num'] = $cashback_num;   
            $v['create_time'] = date('Y-m-d H:i:s',$v['create_time']);;   
        }
        $rdata = [];
        $rdata['status']   = 1;
        $rdata['datalist'] = $datalist;
        return $this->json($rdata);
    }

}