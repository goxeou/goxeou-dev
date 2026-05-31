<?php




namespace app\models;

use think\Model;

class RestaurantShopOrderModel extends Model
{

    protected $name = 'restaurant_shop_order';
    protected $autoWriteTimestamp = true;

    public function getList($where = [], $page = 1, $limit = 15, $order = '')
    {
        $order = $order ? $order : 'id desc';
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

        $data = $model->with(['orderGoods', 'table'])->order($order)->select();

        return ['count' => $count, 'list' => $data];

    }

    public function orderGoods()
    {
        return $this->hasMany(RestaurantShopOrderGoodsModel::class, 'order_id', 'id');
    }

    public function table()
    {
        return $this->hasOne(RestaurantTableModel::class, 'id', 'table_id');
    }

}