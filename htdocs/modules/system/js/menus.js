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
            alert('Ajax error (see console)');
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

    // helper to check ancestors for disabled state
    function hasInactiveAncestor($li) {
        var pid = parseInt($li.data('pid'), 10) || 0;
        while (pid) {
            var $parentBadge = $('.item-active-toggle[data-id="' + pid + '"]');
            if ($parentBadge.length) {
                if (parseInt($parentBadge.attr('data-active'), 10) === 0) {
                    return true;
                }
                pid = parseInt($parentBadge.closest('li.list-group-item').data('pid'), 10) || 0;
            } else {
                break;
            }
        }
        return false;
    }

    // helper to update og row visuals depending on state
    function updateRowState($elem, state) {
        var $row = $elem.closest('li.list-group-item, .card');
        if ($row.length) {
            if (state) {
                $row.removeClass('text-muted inactive');
                // enable action buttons in this row if present
                $row.find('.btn-group .btn').removeClass('disabled').removeAttr('aria-disabled').css('pointer-events', '');
            } else {
                $row.addClass('text-muted inactive');
                // disable action buttons so they cannot be clicked
                $row.find('.btn-group .btn').addClass('disabled').attr('aria-disabled', 'true').css('pointer-events', 'none');
            }
        }
    }

    function refreshChildLocks() {
        $('.item-active-toggle').each(function() {
            var $badge = $(this);
            var $li = $badge.closest('li.list-group-item');
            var active = parseInt($badge.attr('data-active'), 10) ? 1 : 0;
            updateRowState($badge, active);
            if (hasInactiveAncestor($li)) {
                $badge.addClass('disabled').css('cursor', 'not-allowed').attr('title', 'Parent inactive');
            } else {
                $badge.removeClass('disabled').css('cursor', '').removeAttr('title');
            }
        });
        // also mark category cards if necessary
        $('.category-active-toggle').each(function() {
            var $badge = $(this);
            var active = parseInt($badge.attr('data-active'), 10) ? 1 : 0;
            updateRowState($badge, active);
        });
    }

    // initial state on page load
    refreshChildLocks();

    // TOGGLE ACTIVE (categories & items) - délégation unique
    $(document).on('click', '.category-active-toggle, .item-active-toggle', function(e){
        e.preventDefault();
        var $el = $(this);
        if ($el.hasClass('disabled')) {
            var msg = (window.XOOPS_MENUS && window.XOOPS_MENUS.messages && window.XOOPS_MENUS.messages.parentInactive) ? window.XOOPS_MENUS.messages.parentInactive : 'Parent is inactive';
            alert(msg);
            return;
        }
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
                function updateBadge($badge, state) {
                    if (state) {
                        $badge.removeClass('badge-danger').addClass('badge-success').attr('data-active', 1).text(LABEL_YES);
                    } else {
                        $badge.removeClass('badge-success').addClass('badge-danger').attr('data-active', 0).text(LABEL_NO);
                    }
                }

                // update clicked element
                updateBadge($el, active);
                updateRowState($el, active);

                // if server sent list of updated children, adjust them as well
                if (response.updated && Array.isArray(response.updated)) {
                    response.updated.forEach(function(id) {
                        var $child = $('.item-active-toggle[data-id="' + id + '"]');
                        if ($child.length) {
                            updateBadge($child, active);
                            updateRowState($child, active);
                        }
                    });
                }

                // re-evaluate locks after changes
                refreshChildLocks();
            } else {
                alert(response && response.message ? response.message : 'Toggle failed');
            }
        });
    });

});