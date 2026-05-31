<?php




namespace app\models;

use think\Model;

class RestaurantTableModel extends Model
{

    protected $name = 'restaurant_table';
    protected $autoWriteTimestamp = true;

    public function getList($where = [], $page = 1, $limit = 15, $order = '')
    {
        $order = $order ? $order : 'sort desc,id';
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

        $data = $model->with('category')->order($order)->select();

        return ['count' => $count, 'list' => $data];

    }

    public function category()
    {
        return $this->hasOne(RestaurantTableCategoryModel::class, 'id', 'cid');
    }

}