<{include file="db:system_header.tpl"}>
<script type="text/javascript">
/* expose des labels / options Smarty pour le JS externe */
window.XOOPS_MENUS = window.XOOPS_MENUS || {};
window.XOOPS_MENUS.labels = {
    activeYes: "<{$smarty.const._AM_SYSTEM_MENUS_ACTIVE_YES}>",
    activeNo:  "<{$smarty.const._AM_SYSTEM_MENUS_ACTIVE_NO}>"
};
</script>
<!-- Buttons -->
<{if $op != 'delcat' && $op != 'delitem'}>
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
            <button id="xo-addmenuitem-btn" class="btn btn-sm btn-secondary" onclick='location="admin.php?fct=menus&amp;op=additem&amp;category_id=<{$category_id}>"'
                    title="<{$smarty.const._AM_SYSTEM_MENUS_ADDITEM}>">
                <i class="fa fa-plus-circle ic-w mr-1" ></i>
                <{$smarty.const._AM_SYSTEM_MENUS_ADDITEM}>
            </button>
            <{/if}>
            <{if $op == 'additem' || $op == 'edititem' || $op == 'saveitem'}>
            <button id="xo-listitem-btn" class="btn btn-sm btn-secondary" onclick='location="admin.php?fct=menus&amp;op=viewcat&amp;category_id=<{$category_id}>"'
                    title="<{$smarty.const._AM_SYSTEM_MENUS_LISTITEM}>">
                <i class="fa fa-list ic-w mr-1" ></i>
                <{$smarty.const._AM_SYSTEM_MENUS_LISTITEM}>
            </button>
            <{/if}>
        </div>
    </div>
</div>
<{/if}>
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
                        <a class="btn btn-sm btn-outline-primary" href="admin.php?fct=menus&amp;op=editcat&amp;category_id=<{$itemcategory.id|escape}>" title="<{$smarty.const._AM_SYSTEM_MENUS_EDITCAT}>">
                            <i class="fa fa-edit"></i>
                        </a>
                        <a class="btn btn-sm btn-outline-primary" href="admin.php?fct=menus&amp;op=viewcat&amp;category_id=<{$itemcategory.id|escape}>" title="<{$smarty.const._AM_SYSTEM_MENUS_LISTITEM}>">
                            <i class="fa fa-bars"></i>
                        </a>
                        <a class="btn btn-sm btn-outline-danger" href="admin.php?fct=menus&amp;op=delcat&amp;category_id=<{$itemcategory.id|escape}>" title="<{$smarty.const._AM_SYSTEM_MENUS_DELCAT}>">
                            <i class="fa fa-trash"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <{/foreach}>
    </div>

<{/if}>
<{if $op|default:'' == viewcat}>
    <div class="col-12 mb-3" data-id="<{$category_id}>">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="me-2" style="flex:1; min-width:0;">
                    <h5 class="card-title mb-0 text-truncate" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <{$cat_title}>
                    </h5>
                </div>
                <small class="text-muted ms-2" style="white-space:nowrap;">#<{$category_id}></small>
            </div>
        </div>
    </div>
    <{if $items_count|default:0 != 0}>
        <ul class="list-group mb-5">
        <{foreach item=item from=$items}>
            <li class="list-group-item">
                <div class="d-flex align-items-center w-100">
                    <!-- left: indicator + title (flexible) -->
                    <div style="margin-left: <{$item.level*20}>px; flex:1; min-width:0;" class="d-flex align-items-center">
                        <{if $item.level|default:0 gt 0}>
                            <i class="fa fa-chevron-right submenu-indicator" aria-hidden="true"></i>
                        <{/if}>
                        <div style="overflow:hidden;">
                            <span class="d-block text-truncate" style="max-width:100%">
                                <{$item.title|escape}>
                                <{if $item.url != ''}>
                                    &nbsp;(<a href="<{$item.url|escape}>" target="_blank" rel="noopener"><{$item.url|escape}></a>)
                                <{/if}>
                            </span>
                        </div>
                    </div>
                    <!-- center: badge (fixed width, centered) -->
                    <div class="text-center mx-3" style="width:120px; flex:0 0 120px;">
                        <{if $item.active}>
                            <span class="badge badge-success item-active-toggle" data-id="<{$item.id|escape}>" data-active="1" style="cursor:pointer;">
                                <{$smarty.const._AM_SYSTEM_MENUS_ACTIVE_YES}>
                            </span>
                        <{else}>
                            <span class="badge badge-danger item-active-toggle" data-id="<{$item.id|escape}>" data-active="0" style="cursor:pointer;">
                                <{$smarty.const._AM_SYSTEM_MENUS_ACTIVE_NO}>
                            </span>
                        <{/if}>
                    </div>
                    <!-- right: actions -->
                    <div>
                        <div class="btn-group" role="group" aria-label="actions">
                            <a class="btn btn-sm btn-outline-primary" href="admin.php?fct=menus&amp;op=edititem&amp;item_id=<{$item.id|escape}>&amp;category_id=<{$category_id|escape}>" title="<{$smarty.const._AM_SYSTEM_MENUS_EDITITEM}>">
                                <i class="fa fa-edit"></i>
                            </a>
                            <a class="btn btn-sm btn-outline-danger" href="admin.php?fct=menus&amp;op=delitem&amp;item_id=<{$item.id|escape}>&amp;category_id=<{$category_id|escape}>" title="<{$smarty.const._AM_SYSTEM_MENUS_DELITEM}>">
                                <i class="fa fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </li>
        <{/foreach}>
        </ul>
    <{/if}>
<{/if}>
<!-- token container pour JS -->
<div id="menus-token"><{$xoops_token nofilter}></div>


<{if $form|default:'' != ''}>
<div>
    <{$form|default:''}>
</div>
<{/if}>