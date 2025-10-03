<{include file="db:system_header.tpl"}>
<!-- Buttons -->
<div class="card">
    <div class="card-header">
        <div class="card-tools">
            <{if $op == 'list'}>
            <button id="xo-addmenucat-btn" class="btn btn-sm btn-secondary" onclick='location="admin.php?fct=menus&amp;op=addcat"'
                    title="<{$smarty.const._AM_SYSTEM_MENUS_ADDCAT}>">
                <i class="fa fa-plus-circle ic-w mr-1" ></i>
                <{$smarty.const._AM_SYSTEM_MENUS_ADDCAT}>
            </button>
            <{/if}>
            <{if $op == 'addcat' || $op == 'editcat'}>
            <button id="xo-listmenucat-btn" class="btn btn-sm btn-secondary" onclick='location="admin.php?fct=menus"'
                    title="<{$smarty.const._AM_SYSTEM_MENUS_LISTCAT}>">
                <i class="fa fa-list ic-w mr-1" ></i>
                <{$smarty.const._AM_SYSTEM_MENUS_LISTCAT}>
            </button>
            <{/if}>
            <{if $op == 'viewcat'}>
            <button id="xo-addmenuitem-btn" class="btn btn-sm btn-secondary" onclick='location="admin.php?fct=menus&amp;op=additem&amp;category_id=<{$cat_id}>"'
                    title="<{$smarty.const._AM_SYSTEM_MENUS_ADDITEM}>">
                <i class="fa fa-plus-circle ic-w mr-1" ></i>
                <{$smarty.const._AM_SYSTEM_MENUS_ADDITEM}>
            </button>
            <{/if}>
            <{if $op == 'additem' || $op == 'edititem'}>
            <button id="xo-listitem-btn" class="btn btn-sm btn-secondary" onclick='location="admin.php?fct=menus&amp;op=viewcat&amp;category_id=<{$cat_id}>"'
                    title="<{$smarty.const._AM_SYSTEM_MENUS_LISTITEM}>">
                <i class="fa fa-list ic-w mr-1" ></i>
                <{$smarty.const._AM_SYSTEM_MENUS_LISTITEM}>
            </button>
            <{/if}>
        </div>
    </div>
</div>
<{if $error_message|default:'' != ''}>
<div class="alert alert-warning" role="alert">
    <{$error_message}>
</div>
<{/if}>
<{if $category_count|default:0 != 0}>
    <div class="row" id="menus-row">
    <{foreach item=itemcategory from=$category}>
        <div class="col-6 col-sm-4 col-md-3 col-lg-2 mb-3" data-id="<{$itemcategory.id|escape}>">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="me-2" style="flex:1; min-width:0;">
                        <h5 class="card-title mb-0 text-truncate" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <{$itemcategory.title|escape}>
                        </h5>
                    </div>
                    <small class="text-muted ms-2" style="white-space:nowrap;">#<{$itemcategory.id|escape}></small>
                </div>
                <div class="card-body d-flex flex-column">
                    <{if $itemcategory.url|default:'' != ''}>
                        <p class="card-text mb-2">
                            <a href="<{$itemcategory.url|escape}>" target="_blank" rel="noopener"><{$itemcategory.url|escape}></a>
                        </p>
                    <{/if}>
                    <div class="mt-auto">
                    <{if $itemcategory.active}>
                        <span class="badge badge-success category-active-toggle" data-id="<{$itemcategory.id|escape}>" data-active="1" style="cursor:pointer;">
                            <{$smarty.const._AM_SYSTEM_MENUS_ACTIVE_YES}>
                        </span>
                    <{else}>
                        <span class="badge badge-danger category-active-toggle" data-id="<{$itemcategory.id|escape}>" data-active="0" style="cursor:pointer;">
                            <{$smarty.const._AM_SYSTEM_MENUS_ACTIVE_NO}>
                        </span>
                    <{/if}>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <div class="btn-group" role="group" aria-label="actions">
                        <a class="btn btn-sm btn-outline-primary" href="admin.php?fct=menus&amp;op=editcat&amp;category_id=<{$itemcategory.id|escape}>">
                            <i class="fa fa-edit"></i>
                        </a>
                        <a class="btn btn-sm btn-outline-primary" href="admin.php?fct=menus&amp;op=viewcat&amp;category_id=<{$itemcategory.id|escape}>">
                            <i class="fa fa-bars"></i>
                        </a>
                        <a class="btn btn-sm btn-outline-danger" href="admin.php?fct=menus&amp;op=delcat&amp;category_id=<{$itemcategory.id|escape}>" onclick="return confirm('Are you sure?')">
                            <i class="fa fa-trash"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <{/foreach}>
    </div>

    <{literal}>
    <style>
      #menus-row [data-id] { cursor: move; }
      .card-placeholder { border: 2px dashed #ccc; height: 80px; margin-bottom: .75rem; }
    </style>
    <script>
    jQuery(function($){
        if (!$.fn.sortable) {
            console.warn('jQuery UI sortable not found.');
            return;
        }

        $('#menus-row').sortable({
            items: '[data-id]',
            placeholder: 'card-placeholder',
            tolerance: 'pointer',
            forcePlaceholderSize: true,
            helper: function(e, ui) {
                // clone and append to body so dragging isn't constrained by container
                var $clone = ui.clone();
                $clone.css({
                    'width': ui.outerWidth(),
                    'box-sizing': 'border-box'
                }).appendTo('body');
                return $clone;
            },
            appendTo: 'body',
            start: function(evt, ui) {
                // ensure placeholder has same size as helper
                ui.placeholder.height(ui.helper.outerHeight());
                ui.placeholder.width(ui.helper.outerWidth());
                ui.helper.css('z-index', 1200);
            },
            update: function() {
                var ids = $('#menus-row').children('[data-id]').map(function(){ return $(this).data('id'); }).get();
                var $tokenInput = $('#menus-token').find('input').first();
                var data = { order: ids };
                if ($tokenInput.length) {
                    data[$tokenInput.attr('name')] = $tokenInput.val();
                    data['XOOPS_TOKEN_REQUEST'] = $tokenInput.val(); // fallback
                }
                $.post('admin.php?fct=menus&op=saveorder', data, function(response){
                    if (response && response.token) { $('#menus-token').html(response.token); }
                    if (!(response && response.success)) {
                        alert(response && response.message ? response.message : 'Save failed');
                    }
                }, 'json').fail(function(jqXHR, textStatus, errorThrown){
                    console.error('Ajax error:', textStatus, errorThrown, jqXHR.responseText);
                    alert('Ajax error (voir console)');
                });
            }
        }).disableSelection();
    });
    </script>
    <script>
    jQuery(function($){
        // toggle active via AJAX
        $(document).on('click', '.category-active-toggle', function(e){
            e.preventDefault();
            var $el = $(this);
            var id = $el.data('id');
            // relire token à chaque fois
            var $tokenInput = $('#menus-token').find('input').first();
            var data = { category_id: id };
            if ($tokenInput.length) {
                data[$tokenInput.attr('name')] = $tokenInput.val();
                data['XOOPS_TOKEN_REQUEST'] = $tokenInput.val(); // fallback
            }
            $.post('admin.php?fct=menus&op=toggleactive', data, function(response){
                if (response && response.token) {
                    $('#menus-token').html(response.token);
                }
                if (response && response.success) {
                    var active = parseInt(response.active, 10);
                    if (active) {
                        $el.removeClass('badge-danger').addClass('badge-success').attr('data-active', 1).text('<{$smarty.const._AM_SYSTEM_MENUS_ACTIVE_YES}>');
                    } else {
                        $el.removeClass('badge-success').addClass('badge-danger').attr('data-active', 0).text('<{$smarty.const._AM_SYSTEM_MENUS_ACTIVE_NO}>');
                    }
                } else {
                    alert(response && response.message ? response.message : 'Toggle failed');
                }
            }, 'json').fail(function(jqXHR, textStatus, errorThrown){
                console.error('Ajax error:', textStatus, errorThrown, jqXHR.responseText);
                alert('Ajax error (voir console)');
            });
        });
    });
    </script>
    <{/literal}>
<{/if}>
<{if $op|default:'' == viewcat}>
    <div class="col-12 mb-3" data-id="<{$cat_id}>">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="me-2" style="flex:1; min-width:0;">
                    <h5 class="card-title mb-0 text-truncate" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <{$cat_title}>
                    </h5>
                </div>
                <small class="text-muted ms-2" style="white-space:nowrap;">#<{$cat_id}></small>
            </div>
        </div>
    </div>
<{/if}>

<!-- token container pour JS -->
<div id="menus-token"><{$xoops_token nofilter}></div>


<{if $form|default:'' != ''}>
<div>
    <{$form|default:''}>
</div>
<{/if}>