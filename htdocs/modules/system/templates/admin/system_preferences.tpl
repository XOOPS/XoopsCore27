<{if !empty($breadcrumb)}>
    <{include file="db:system_header.tpl"}>
<{/if}>
<!--Preferences-->
<{if !empty($menu)}>
    <div class="xo-catsetting">
        <{foreach item=preference from=$preferences|default:null}>
            <a class="tooltip" href="admin.php?fct=preferences&amp;op=show&amp;confcat_id=<{$preference.id}>" title="<{$preference.name}>">
                <img src="<{$preference.image}>" alt="<{$preference.name}>"/>
                <span><{$preference.name}></span>
            </a>
        <{/foreach}>
        <a class="tooltip" href="admin.php?fct=preferences&amp;op=showmod&amp;mod=1" title="<{$smarty.const._AM_SYSTEM_PREFERENCES_SETTINGS}>">
            <img src="<{xoAdminIcons url='xoops/system_mods.png'}>" alt="<{$smarty.const._AM_SYSTEM_PREFERENCES_SETTINGS}>"/>
            <span><{$smarty.const._AM_SYSTEM_PREFERENCES_SETTINGS}></span>
        </a>
    </div>
<{/if}>
<div class="clear">&nbsp;</div>
<br>


