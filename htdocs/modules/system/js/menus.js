jQuery(function($){
    'use strict';

    // utilitaires
    function getTokenData() {
        var $tokenInput = $('#menus-token').find('input').first();
        var data = {};
        if ($tokenInput.length) {
            data[$tokenInput.attr('name')] = $tokenInput.val();
            data['XOOPS_TOKEN_REQUEST'] = $tokenInput.val(); // fallback
        }
        return data;
    }

    function updateTokenFromResponse(resp) {
        if (resp && resp.token) {
            $('#menus-token').html(resp.token);
        }
    }

    var labelsCfg = (window.XOOPS_MENUS && window.XOOPS_MENUS.labels) || {};
    var LABEL_YES = labelsCfg.activeYes || 'Yes';
    var LABEL_NO  = labelsCfg.activeNo  || 'No';

    function ajaxJsonPost(url, data, onSuccess) {
        return $.ajax({
            url: url,
            method: 'POST',
            data: data,
            dataType: 'json'
        }).done(function(response){
            updateTokenFromResponse(response);
            if (typeof onSuccess === 'function') onSuccess(response);
        }).fail(function(jqXHR, textStatus, errorThrown){
            console.error('Ajax error:', textStatus, errorThrown, jqXHR.responseText);
            alert('Ajax error (voir console)');
        });
    }

    // SORTABLE
    if ($.fn.sortable) {
        $('#menus-row').sortable({
            items: '[data-id]',
            placeholder: 'card-placeholder',
            tolerance: 'pointer',
            forcePlaceholderSize: true,
            helper: function(e, ui) {
                var $clone = ui.clone();
                $clone.css({ 'width': ui.outerWidth(), 'box-sizing': 'border-box' }).appendTo('body');
                return $clone;
            },
            appendTo: 'body',
            start: function(evt, ui) {
                ui.placeholder.height(ui.helper.outerHeight());
                ui.placeholder.width(ui.helper.outerWidth());
                ui.helper.css('z-index', 1200);
            },
            update: function() {
                var ids = $('#menus-row').children('[data-id]').map(function(){ return $(this).data('id'); }).get();
                var data = $.extend({ order: ids }, getTokenData());
                ajaxJsonPost('admin.php?fct=menus&op=saveorder', data, function(response){
                    if (!(response && response.success)) {
                        alert(response && response.message ? response.message : 'Save failed');
                    }
                });
            }
        }).disableSelection();
    } else {
        console.warn('jQuery UI sortable not found.');
    }

    // TOGGLE ACTIVE (categories & items) - délégation unique
    $(document).on('click', '.category-active-toggle, .item-active-toggle', function(e){
        e.preventDefault();
        var $el = $(this);
        var isCategory = $el.hasClass('category-active-toggle');
        var id = $el.data('id');
        if (!id) return;

        var url = isCategory ? 'admin.php?fct=menus&op=toggleactivecat' : 'admin.php?fct=menus&op=toggleactiveitem';
        var paramName = isCategory ? 'category_id' : 'item_id';
        var data = {};
        data[paramName] = id;
        $.extend(data, getTokenData());

        ajaxJsonPost(url, data, function(response){
            if (response && response.success) {
                var active = parseInt(response.active, 10) ? 1 : 0;
                if (active) {
                    $el.removeClass('badge-danger').addClass('badge-success').attr('data-active', 1).text(LABEL_YES);
                } else {
                    $el.removeClass('badge-success').addClass('badge-danger').attr('data-active', 0).text(LABEL_NO);
                }
            } else {
                alert(response && response.message ? response.message : 'Toggle failed');
            }
        });
    });

});