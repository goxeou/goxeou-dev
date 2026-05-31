<?php




namespace app\models;

use think\Model;

class RestaurantShopOrderGoodsModel extends Model
{

    protected $name = 'restaurant_shop_order_goods';
    protected $autoWriteTimestamp = true;

    public function getList($where = [], $page = 1, $limit = 15, $order = '')
    {
        $order = $order ? $order : 'id';
        $page = $page ? intval($page) : 1;
        $limit = $limit ? intval($limit) : 15;

        $model = $this;
        if ($where) {
            $model = $model->where($where);
        }
        $count = $model->count();
        if ($limit != 'all') {
            $model = $model->page($page, $limit);
        }

        $data = $model/*->with('product')*/->order($order)->select();

        return ['count' => $count, 'list' => $data];

    }

    public function product()
    {
        return $this->hasOne(RestaurantProductModel::class, 'id', 'product_id');
    }

}