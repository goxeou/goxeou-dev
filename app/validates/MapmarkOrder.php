<?php


declare (strict_types = 1);

namespace app\validates;
use think\Validate;

class MapmarkOrder extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'name' => 'require',
        'shop_type' => 'require',
        'shop_tel' => 'require',
        'shop_time'  => 'require',
        'address' => 'require',
        'mobile' => 'require',
        'license_img' => 'require',
        'shop_img' => 'require',
    ];
    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        'name' => '请填写标注名称',
        'shop_type' => '请填写经营类型',
        'shop_tel' => '请填写营业电话',
        'shop_time'  => '请填写营业时间',
        'address' => '请填写详细经营地址',
        'mobile' => '请填写联系电话',
        'license_img' => '请上传营业执照',
        'shop_img' => '请上传门面照片',

    ];
}