<a title="<{$smarty.const._MD_NEWBB_SUBFORUMS}>" data-bs-toggle="collapse" href="#xoops-subforum" class="btn btn-primary float-end">
    <span class="fa-solid fa-circle-plus"></span> <{$smarty.const._MD_NEWBB_SUBFORUMS}>
</a>
<div class="newbb-subforum mb10 clearfix">
    <div class="collapse" id="xoops-subforum">
        <{foreach item=sforum from=$subforum|default:null}>
        <ul class="subforum-loop list-unstyled clearfix">
        <li class="col-12 col-md-6">
            <a href="<{$xoops_url}>/modules/<{$xoops_dirname}>/viewforum.php?forum=<{$sforum.forum_id}>" title="<{$sforum.forum_name}>">
                <{$sforum.forum_folder}> <strong><{$sforum.forum_name}></strong>
            </a>
        </li>

        <li class="col-12 col-md-6 text-end">
            <strong><{$smarty.const._MD_NEWBB_LASTPOST}>:</strong>
            <{$sforum.forum_lastpost_time}> <strong><{$smarty.const._MD_NEWBB_BY}></strong> <{$sforum.forum_lastpost_user}>
        </li>

        <li>
        <ul class="list-inline col-md-6">
            <{if $sforum.forum_moderators}><li class="list-inline-item"><span class="badge text-bg-info"><{$smarty.const._MD_NEWBB_MODERATOR}>: <{$sforum.forum_moderators}></span></li><{/if}>

            <li class="list-inline-item"><span class="badge text-bg-info"><{$smarty.const._MD_NEWBB_TOPICS}>: <{$sforum.forum_topics}></span></li>

            <li class="list-inline-item"><span class="badge text-bg-info"><{$smarty.const._MD_NEWBB_POSTS}>: <{$sforum.forum_posts}></span></li>

            <!-- If subforum description -->
            <{if $sforum.forum_desc|default:'' != ''}>
                <li class="list-inline-item">
                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#subforum-<{$sforum.forum_id}>">
                        <span class="fa-solid fa-circle-info"></span>
                    </button>
                </li>
            <{/if}>
        </ul>

        <ul class="list-inline col-md-6 text-end">
        <{if $sforum.forum_lastpost_subject}>
            <li class="list-inline-item">
                <a href="<{$xoops_url}>/modules/<{$xoops_dirname}>/viewtopic.php?post_id=<{$sforum.forum_lastpost_id}>">
                    <{$sforum.forum_lastpost_subject}>
                    <{$sforum.forum_lastpost_icon}>
                </a>
            </li>
        <{else}>
            <li class="list-inline-item"><{$smarty.const._MD_NEWBB_NONEWPOSTS}></li>
        <{/if}>
        </ul>
        </li>

        </ul><!-- .subforum-loop -->

        <!-- Modal -->
        <div class="modal fade" id="subforum-<{$sforum.forum_id}>" tabindex="-1" role="dialog" aria-labelledby="subforum-title-<{$sforum.forum_id}>" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h4 class="modal-title" id="subforum-title-<{$sforum.forum_id}>"><{$sforum.forum_name}></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<{$smarty.const._CLOSE|escape:'html'}>"></button>
              </div>
              <div class="modal-body">
                <p><{$sforum.forum_desc}></p>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><{$smarty.const.THEME_CLOSE}></button>
                <button type="button" class="btn btn-primary"><{$smarty.const.THEME_GOTOTHEFORUM}></button>
              </div>
            </div><!-- /.modal-content -->
          </div><!-- /.modal-dialog -->
        </div><!-- /.modal -->

        <{/foreach}>
    </div><!-- #xoops-subforum -->
</div><!-- .xoops-newbb-subforum -->
