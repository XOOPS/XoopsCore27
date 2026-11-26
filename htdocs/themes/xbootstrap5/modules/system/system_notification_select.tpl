<{if $xoops_notification.show}>
    <div class="clearfix"></div>
    <form name="notification_select" action="<{$xoops_notification.target_page}>" method="post">
        <h4><{$lang_activenotifications}></h4>

        <input type="hidden" name="not_redirect" value="<{$xoops_notification.redirect_script}>">
        <{securityToken}>

        <p><strong><{$lang_notificationoptions}></strong></p>

        <div class="row">
            <div class="col-4 col-md-4"><{$lang_category}></div>

            <div class="col-8 col-md-8">
                <input name="allbox" id="allbox" onclick="xoopsCheckAll('notification_select','allbox');" type="checkbox" value="<{$lang_checkall}>">
                <{$lang_events}>
            </div>
        </div>
        <{foreach item=category from=$xoops_notification.categories|default:null}>
            <{if !empty($category.events)}>
                <div class="row">
                    <div class="col-4"><strong><{$category.title}></strong></div>
                    <div class="col-8">
                        <{foreach item=event from=$category.events}>
                            <{counter assign=index}>
                            <input type="hidden" name="not_list[<{$index}>][params]" value="<{$category.name}>,<{$category.itemid}>,<{$event.name}>"/>
                            <label class="d-block">
                                <input type="checkbox" name="not_list[<{$index}>][status]" value="1" <{if $event.subscribed}>checked<{/if}>>
                                <{$event.caption}>
                            </label>
                        <{/foreach}>
                    </div>
                </div>
            <{/if}>
        <{/foreach}>

        <p class="aligncenter"><input class="btn btn-primary" type="submit" name="not_submit" value="<{$lang_updatenow}>"></p>

        <p class="aligncenter"><strong><{$lang_notificationmethodis}>: </strong><{$user_method}> <a class="btn btn-info btn-sm" href="<{$editprofile_url}>" title="<{$lang_change}>"><{$lang_change}></a></p>
    </form>
<{/if}>
