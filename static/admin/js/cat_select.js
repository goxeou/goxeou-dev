// 纯JS分类三级联动
var cat_select = function(_cmbCat1, _cmbCat2, _cmbCat3, first_category_id, second_category_id, third_category_id,server_url)
{
    //这里是select下拉框的id
    console.log(first_category_id+'=>'+second_category_id+'=>'+third_category_id);
    var cat1Select = $('#'+_cmbCat1);
    var cat2Select = $('#'+_cmbCat2);
    var cat3Select = $('#'+_cmbCat3);
        var form = layui.form;
        // 一级分类
        $.ajax({
            url: server_url,
            type: 'post',
            dataType: 'json',
            data: {parent_id: 0},
            success: function (res) {
                $.each(res.data, function (index, item) {
                    if (item.id == first_category_id) {
                        cat1Select.append('<option value="' + item.id + '" selected>' + item.name + '</option>');
                        $.ajax({
                            url: server_url,
                            type: 'post',
                            dataType: 'json',
                            data: {parent_id: item.id},
                            success: function (res) {
                                $.each(res.data, function (index2, item2) {
                                    if (item2.id == second_category_id) {
                                        cat2Select.append('<option value="' + item2.id + '" selected>' + item2.name + '</option>');
                                        $.ajax({
                                            url: server_url,
                                            type: 'post',
                                            dataType: 'json',
                                            data: {parent_id: item2.id},
                                            success: function (res) {
                                                $.each(res.data, function (index3, item3) {
                                                    if (item3.id == third_category_id) {
                                                        cat3Select.append('<option value="' + item3.id + '" selected>' + item3.name + '</option>');
                                                    } else {
                                                        cat3Select.append('<option value="' + item3.id + '">' + item3.name + '</option>');
                                                    }
                                                })
                                                form.render('select');
                                            }
                                        })
                                    } else {
                                        cat2Select.append('<option value="' + item2.id + '">' + item2.name + '</option>');
                                    }
                                    form.render('select');
                                })
                            }
                        })
                        form.render('select');
                    } else {
                        cat1Select.append($("<option value='" + item.id + "'>" + item.name + "</option>"));
                    }
                });
                form.render('select');
            }
        });

        // 监听下拉框变化事件--这里和回显没有关系了 点击事件触发
        form.on('select('+_cmbCat1+')', function (data) {
            var cat1Id = data.value;
            if (cat1Id !== '') {
                // 加载数据
                $.ajax({
                    url: server_url,
                    type: 'post',
                    dataType: 'json',
                    data: {parent_id: cat1Id},
                    success: function (res) {
                        cat2Select.empty().append('<option value="">请选择</option>');
                        $.each(res.data, function (index, item) {
                            cat2Select.append('<option value="' + item.id + '">' + item.name + '</option>');
                        });
                        $('#'+_cmbCat2).val('')
                        $('#'+_cmbCat3).val('')
                        form.render('select');
                    }
                });
            } else {
                // 清空下面两级的数据
                $('#'+_cmbCat2).empty().append('<option value="">请选择</option>');
                $('#'+_cmbCat3).empty().append('<option value="">请选择</option>');
                form.render('select');
            }
        });

        // 监听下拉框变化事件--这里和回显没有关系了 点击事件触发
        form.on('select('+_cmbCat2+')', function (data) {
            var cat2Id = data.value;
            if (cat2Id !== '') {
                // 加载区县数据
                $.ajax({
                    url: server_url,
                    type: 'post',
                    dataType: 'json',
                    data: {parent_id: cat2Id},
                    success: function (res) {
                        cat3Select.empty().append('<option value="">请选择</option>');
                        $.each(res.data, function (index, item) {
                            cat3Select.append('<option value="' + item.id + '">' + item.name + '</option>');
                        });
                        $('#'+_cmbCat3).val('')
                        form.render('select');
                    }
                });
            } else {
                // 清空区县数据
                $('#'+_cmbCat3).empty().append('<option value="">请选择</option>');
                form.render('select');
            }
        });
}
