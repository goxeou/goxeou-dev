    /*! 定义构造函数 */
    function Excel(data, name) {
        if (data && name) this.export(data, name);
    }

    /*! 默认导出配置 */
    Excel.prototype.options = {writeOpt: {bookSST: true}};

    /*! 导出 Excel 文件 */
    Excel.prototype.export = function (data, name, options) {
        if (name.substring(0, -5).toLowerCase() !== '.xlsx') name += '.xlsx';
        layui.excel.exportExcel(data, name, 'xlsx', options || this.options || {writeOpt: {bookSST: true}});
    };

    /*! 绑定导出的事件 */
    // <a data-form-export>通用导出</a>
    // <a data-form-export="URL链接">指定链接导出</a>
    // <!-- 自定义导出 Excel 文件 -->
    // <a id="EXPORT1" data-excel="URL链接">自定义导出1</a>
    // <a id="EXPORT2" data-excel="URL链接">自定义导出2</a>
    // <script>
    //      Excel.bind(DONE1,FILENAME1,'#EXPORT1')
    //      Excel.bind(DONE2,FILENAME2,'#EXPORT2')
    // </script>
    Excel.prototype.bind = function (done, filename, selector, options) {
        let that = this;
        this.options = options || {}
        $('body').off('click', selector || '[data-form-export]').on('click', selector || '[data-form-export]', function () {
            let form = $(this).parents('form');
            let name = this.dataset.filename || filename;
            let method = this.dataset.method || form.attr('method') || 'get';
            let location = this.dataset.excel || this.dataset.formExport || form.attr('action') || '';
            let sortType = $(this).attr('data-sort-type') || '', sortField = $(this).attr('data-sort-field') || '';
            if (sortField.length > 0 && sortType.length > 0) {
                location += (location.indexOf('?') > -1 ? '&' : '?') + '_order_=' + sortType + '&_field_=' + sortField;
            }

            that.load(location, datawhere, method).then(function (data,title) {
                that.export(done.call(that, data, title), name);
            }).fail(function (ret) {
                $.msg.tips(ret || '文件导出失败');
            });
        });
    };

    /*! 加载导出的文档 */
    Excel.prototype.load = function (url, data, method) {
        return (function (defer, lists, loaded,title) {
            loaded = $.msg.loading('<sapn data-upload-tip>正在加载 <span data-upload-count>0.00</span>%</sapn>');
            return (lists = []), LoadNextPage(1, 1), defer;

            function LoadNextPage(curPage, maxPage, urlParams) {
                //url中没有指定limit数据的话默认10000条
                var limit = parseInt($.form.getQueryString(url,'limit'))
                var has_limit = 0;
                if(!limit || limit<1){
                    limit = 10000;
                }else{
                    has_limit = 1;
                }
                let proc = (curPage / maxPage * 100).toFixed(2);
                if(proc<100 || curPage<=1){
                    $('[data-upload-count]').html(proc >= 100 ? '100.00' : proc);
                }else{
                    $('[data-upload-tip]').html('生成文件中，请耐心等待....');
                }
                if (curPage > maxPage) return layer.close(loaded), defer.resolve(lists,title);
                if(has_limit){
                    urlParams = '&page=' + curPage;
                }else{
                    urlParams = '&limit='+limit+'&page=' + curPage;
                }
                $.form.load(url + urlParams, data, method, function (ret) {
                    if (ret.code==0) {
                        curPage = curPage +1;
                        if(ret.page && ret.page.max_age){
                            maxPage = ret.page.max_age
                        }else{
                            maxPage = Math.ceil(ret.count/limit)+1;
                        }

                        lists = lists.concat(ret.data);
                        if(ret.title){
                            title = ret.title;
                        }

                        if (ret.data) LoadNextPage(curPage, maxPage);
                    //     $page = [
                    //         'current' => (int)$page,
                    //         'limit' => (int)$limit,
                    //         'max_age' => $page_total,
                    //         'total' => $count,
                    // ];
                        // if (ret.page) LoadNextPage(curPage, maxPage);
                        // lists = lists.concat(ret.data);
                        // if (ret.page) LoadNextPage((ret.page.current || 1) + 1, ret.page.pages || 1);
                    } else {
                        defer.reject('数据加载异常');
                    }
                    return false;
                }, false);
            }
        })($.Deferred());
    };

    /*! 设置表格导出样式 */
    // this.withStyle(data, {A: 60, B: 80, C: 99, E: 120, G: 120}, 99, 28)
    Excel.prototype.withStyle = function (data, colsWidth, defaultWidth, defaultHeight) {
        // 自动计算列序
        let idx, colN = 0, defaC = {}, lastCol;
        for (idx in data[0]) defaC[lastCol = layui.excel.numToTitle(++colN)] = defaultWidth || 99;
        defaC[lastCol] = 160;

        // 设置表头样式
        layui.excel.setExportCellStyle(data, 'A1:' + lastCol + '1', {
            // s: {
            //     font: {sz: 12, bold: true, color: {rgb: "FFFFFF"}, name: '微软雅黑', shadow: true},
            //     fill: {bgColor: {indexed: 64}, fgColor: {rgb: '5FB878'}},
            //     alignment: {vertical: 'center', horizontal: 'center'}
            // }
            s: {
                font: {sz: 12, bold: true, name: '微软雅黑', shadow: true},
                alignment: {vertical: 'center', horizontal: 'center'}
            }
        });

        // 设置内容样式
        (function (style1, style2) {
            layui.excel.setExportCellStyle(data, 'A2:' + lastCol + data.length, {s: style1}, function (rawCell, newCell, row, config, curRow) {
                // return rawCell;
                typeof rawCell !== 'object' && (rawCell = {v: rawCell});
                rawCell.s = Object.assign({}, style2, rawCell.s || {});
                return (curRow % 2 === 0) ? newCell : rawCell;
            });
        })({
            font: {sz: 10, shadow: true, name: '微软雅黑'},
            // fill: {bgColor: {indexed: 64}, fgColor: {rgb: "EAEAEA"}},
            alignment: {vertical: 'center', horizontal: 'center'}
        }, {
            font: {sz: 10, shadow: true, name: '微软雅黑'},
            // fill: {bgColor: {indexed: 64}, fgColor: {rgb: "FFFFFF"}},
            alignment: {vertical: 'center', horizontal: 'center'}
        });

        // 设置表格行宽高，需要设置最后的行或列宽高，否则部分不生效 ？？？
        let rowsC = {1: 33}, colsC = Object.assign({}, defaC, {A: 60}, colsWidth || {});
        rowsC[data.length] = defaultHeight || 28, this.options.extend = Object.assign({}, {
            '!cols': layui.excel.makeColConfig(colsC, defaultWidth || 99),
            '!rows': layui.excel.makeRowConfig(rowsC, defaultHeight || 28),
        }, this.options.extend || {});
        return data;
    }




/*! 消息组件实例 */
$.msg = new function () {
    this.idx = [];
    this.mdx = [];
    this.shade = [0.02, '#000000'];

    /*! 状态消息提示 */
    this.tips = function (msg, time, call) {
        let idx = layer.msg(msg, {time: (time || 3) * 1000, shade: this.shade, end: call, shadeClose: true});
        return $.msg.idx.push(idx), idx;
    };
    /*! 显示加载提示 */
    this.loading = function (msg, call) {
        let idx = msg ? layer.msg(msg, {icon: 16, scrollbar: false, shade: this.shade, time: 0, end: call}) : layer.load(0, {time: 0, scrollbar: false, shade: this.shade, end: call});
        return $.msg.idx.push(idx), idx;
    };
    /*! 页面加载层 */
    this.page = new function () {
        this.$body = $('body>.think-page-loader');
        this.$main = $('.think-page-body+.think-page-loader');
        this.stat = function () {
            return this.$body.is(':visible');
        }, this.done = function () {
            return $.msg.page.$body.fadeOut();
        }, this.show = function () {
            this.stat() || this.$main.removeClass('layui-hide').show();
        }, this.hide = function () {
            if (this.time) clearTimeout(this.time);
            this.time = setTimeout(function () {
                ($.msg.page.time = 0) || $.msg.page.$main.fadeOut();
            }, 200);
        };
    };
};

/*! 表单自动化组件 */
$.form = new function () {
    /*! 异步加载的数据 */
    this.load = function (url, data, method, callable, loading, tips, time, headers) {
        // 如果主页面 loader 显示中，绝对不显示 loading 图标
        loading = $('.layui-page-loader').is(':visible') ? false : loading;
        let defer = jQuery.Deferred(), loadidx = loading !== false ? $.msg.loading(tips) : 0;
        if (typeof data === 'object' && typeof data['_token_'] === 'string') {
            headers = headers || {}, headers['User-Form-Token'] = data['_token_'], delete data['_token_'];
        }
        $.ajax({
            data: data || {}, type: method || 'GET', url: url, beforeSend: function (xhr, i) {
                if (typeof Pace === 'object' && loading !== false) Pace.restart();
                if (typeof headers === 'object') for (i in headers) xhr.setRequestHeader(i, headers[i]);
            }, error: function (XMLHttpRequest, $dialog, layIdx, iframe) {
                // 异常消息显示处理
                if (defer.notify('load.error') && parseInt(XMLHttpRequest.status) !== 200 && XMLHttpRequest.responseText.indexOf('Call Stack') > -1) try {
                    layIdx = layer.open({title: XMLHttpRequest.status + ' - ' + XMLHttpRequest.statusText, type: 2, move: false, content: 'javascript:;'});
                    layer.full(layIdx), $dialog = $('#layui-layer' + layIdx), iframe = $dialog.find('iframe').get(0);
                    (iframe.contentDocument || iframe.contentWindow.document).write(XMLHttpRequest.responseText);
                    iframe.winClose = {width: '30px', height: '30px', lineHeight: '30px', fontSize: '30px', marginLeft: 0};
                    iframe.winTitle = {color: 'red', height: '60px', lineHeight: '60px', fontSize: '20px', textAlign: 'center', fontWeight: 700};
                    $dialog.find('.layui-layer-title').css(iframe.winTitle) && $dialog.find('.layui-layer-setwin').css(iframe.winClose).find('span').css(iframe.winClose);
                    setTimeout(function () {
                        $(iframe).height($dialog.height() - 60);
                    }, 100);
                } catch (e) {
                    layer.close(layIdx);
                }
                layer.closeAll('loading');
                if (parseInt(XMLHttpRequest.status) !== 200) {
                    $.msg.tips('E' + XMLHttpRequest.status + ' - 服务器繁忙，请稍候再试！');
                } else {
                    this.success(XMLHttpRequest.responseText);
                }
            }, success: function (res) {
                defer.notify('load.success', res) && (time = time || res.wait || undefined);
                if (typeof callable === 'function' && callable.call($.form, res, time, defer) === false) return false;
                return typeof res === 'object' ? $.msg.auto(res, time) : $.form.show(res);
            }, complete: function () {
                defer.notify('load.complete') && $.msg.page.done() && layer.close(loadidx);
            }
        });
        return defer;
    };
    this.getQueryString = function(url,name) {
        var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)"); //构造一个含有目标参数的正则表达式对象
        var r = url.substr(1).match(reg);  //匹配目标参数
        if( r != null ) return decodeURI( r[2] ); return null;
    }

};