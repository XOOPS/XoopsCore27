<!--smilies-->
<{include file="db:system_header.tpl"}>
<script type="text/javascript">
    IMG_ON = "<{xoAdminIcons url='success.png'}>";
    IMG_OFF = "<{xoAdminIcons url='cancel.png'}>";
</script>
<{if isset($smilies_count) && $smilies_count == true}>
    <div class="floatright">
        <div class="xo-buttons">
            <a class="ui-corner-all tooltip" href="admin.php?fct=smilies&amp;op=new_smilie" title="<{$smarty.const._AM_SYSTEM_SMILIES_ADD}>">
                <img src="<{xoAdminIcons url='add.png'}>" alt="<{$smarty.const._AM_SYSTEM_SMILIES_ADD}>"/>
                <{$smarty.const._AM_SYSTEM_SMILIES_ADD}>
            </a>
        </div>
    </div>
    <table id="xo-smilies-sorter" cellspacing="1" class="outer tablesorter">
        <thead>
        <tr>
            <th class="txtcenter"><{$smarty.const._AM_SYSTEM_SMILIES_CODE}></th>
            <th class="txtcenter"><{$smarty.const._AM_SYSTEM_SMILIES_SMILIE}></th>
            <th class="txtcenter"><{$smarty.const._AM_SYSTEM_SMILIES_DESCRIPTION}></th>
            <th class="txtcenter"><{$smarty.const._AM_SYSTEM_SMILIES_DISPLAY}></th>
            <th class="txtcenter width10"><{$smarty.const._AM_SYSTEM_SMILIES_ACTION}></th>
        </tr>
        </thead>
        <tbody>
        <{foreach item=smiley from=$smilies|default:null}>
            <tr class="<{cycle values='even,odd'}> alignmiddle">
                <td class="txtcenter width5"><{$smiley.code}></td>
                <td class="txtcenter width5"><{$smiley.image}></td>
                <td class="txtcenter width50"><{$smiley.emotion}></td>
                <td class="xo-actions txtcenter width10">
                    <img id="loading_sml<{$smiley.smilies_id}>" src="images/spinner.gif" style="display:none;" title="<{$smarty.const._AM_SYSTEM_LOADING}>"
                         alt="<{$smarty.const._AM_SYSTEM_LOADING}>"/><img class="cursorpointer tooltip" id="sml<{$smiley.smilies_id}>"
                                                                          onclick="system_setStatus( { fct: 'smilies', op: 'smilies_update_display', smilies_id: <{$smiley.smilies_id}> }, 'sml<{$smiley.smilies_id}>', 'admin.php' )"
                                                                          src="<{if $smiley.display}><{xoAdminIcons url='success.png'}><{else}><{xoAdminIcons url='cancel.png'}><{/if}>"
                                                                          alt="<{if $smiley.display}><{$smarty.const._AM_SYSTEM_SMILIES_OFF}><{else}><{$smarty.const._AM_SYSTEM_SMILIES_ON}><{/if}>"
                                                                          title="<{if $smiley.display}><{$smarty.const._AM_SYSTEM_SMILIES_OFF}><{else}><{$smarty.const._AM_SYSTEM_SMILIES_ON}><{/if}>"/>
                </td>
                <td class="xo-actions txtcenter width10">
                    <a class="tooltip" href="admin.php?fct=smilies&amp;op=edit_smilie&amp;smilies_id=<{$smiley.smilies_id}>"
                       title="<{$smarty.const._AM_SYSTEM_SMILIES_EDIT}>">
                        <img src="<{xoAdminIcons url='edit.png'}>" alt="<{$smarty.const._AM_SYSTEM_SMILIES_EDIT}>"/></a>
                    <a class="tooltip" href="admin.php?fct=smilies&amp;op=smilies_delete&amp;smilies_id=<{$smiley.smilies_id}>"
                       title="<{$smarty.const._AM_SYSTEM_SMILIES_DELETE}>">
                        <img src="<{xoAdminIcons url='delete.png'}>" alt="<{$smarty.const._AM_SYSTEM_SMILIES_DELETE}>"/></a>
                </td>
            </tr>
        <{/foreach}>
        </tbody>
    </table>
    <!-- Display smilies navigation -->
    <div class="clear spacer"></div>
    <{if !empty($nav_menu)}>
        <div class="xo-avatar-pagenav floatright"><{$nav_menu}></div>
        <div class="clear spacer"></div>
    <{/if}>
<{/if}>
<!-- Display smilies form (add,edit) -->
<{if !empty($form)}>
    <div class="spacer"><{$form}></div>
<{/if}>
