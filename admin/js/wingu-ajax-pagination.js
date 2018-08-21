jQuery(function ($) {
    list = {
        init: function () {
            let timer;
            let delay = 500;

            $('.tablenav-pages a, .manage-column.sortable a, .manage-column.sorted a').on('click', function (e) {
                e.preventDefault();
                var query = this.search.substring(1);

                var data = {
                    paged: list.__query(query, 'paged') || '1',
                    order: list.__query(query, 'order') || 'ASC',
                    orderby: list.__query(query, 'orderby') || 'name',
                    s: list.__query(query, 's') || '',
                };
                list.update(data);
            });

            $('input[name=paged]').on('keyup', function (e) {
                if (13 === e.which)
                    e.preventDefault();

                let data = {
                    paged: parseInt($('input[name=paged]').val()) || '1',
                    order: $('input[name=order]').val() || 'ASC',
                    orderby: $('input[name=orderby]').val() || 'name'
                };

                window.clearTimeout(timer);
                timer = window.setTimeout(function () {
                    list.update(data);
                }, delay);
            });
        },

        update: function (data) {
            $.ajax({
                url: ajaxurl,
                data: $.extend(
                    {
                        _ajax_wingu_triggers_nonce: $('#_ajax_wingu_triggers_nonce').val(),
                        action: '_ajax_fetch_wingu_triggers',
                    },
                    data
                ),
                success: function (response) {

                    var response = $.parseJSON(response);

                    if (response.rows.length)
                        $('#the-list').html(response.rows);
                    if (response.column_headers.length)
                        $('thead tr, tfoot tr').html(response.column_headers);
                    if (response.pagination.bottom.length)
                        $('.tablenav.top .tablenav-pages').html($(response.pagination.top).html());
                    if (response.pagination.top.length)
                        $('.tablenav.bottom .tablenav-pages').html($(response.pagination.bottom).html());

                    list.init();
                }
            });
        },

        __query: function (query, variable) {
            let vars = query.split("&");
            for (let i = 0; i < vars.length; i++) {
                let pair = vars[i].split("=");
                if (pair[0] == variable)
                    return pair[1];
            }
            return false;
        },
    };

    list.init();

    setTimeout(function() { jQuery( "input[name*='_wp_http_referer']" ).remove(); }, 500);
});