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
            <{if $op == 'addcat'}>
            <button id="xo-listmenucat-btn" class="btn btn-sm btn-secondary" onclick='location="admin.php?fct=menus"'
                    title="<{$smarty.const._AM_SYSTEM_MENUS_LISTCAT}>">
                <i class="fa fa-list ic-w mr-1" ></i>
                <{$smarty.const._AM_SYSTEM_MENUS_LISTCAT}>
            </button>
            <{/if}>
        </div>
    </div>
</div>
<div>
    <{$form|default:''}>
</div>