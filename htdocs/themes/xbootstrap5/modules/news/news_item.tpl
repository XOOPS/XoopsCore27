<section role="main" class="news-item">
    <article role="article">
        <{if $story.picture|default:'' != ''}>
            <div class="news-header">
                <{if $story.poster|default:'' != ''}><em><strong><{$lang_postedby}>: </strong><{$story.poster}><{/if}> <{$lang_on}> <{$story.posttime}></em>

                <strong class="float-end hit-counter"><{$story.hits}> <{$lang_reads}></strong>

                <h2 class="news-title" role="heading"><{$story.topic_title}> <{$story.news_title}></h2>
                <a title="<{$story.news_title|strip_tags}>" data-bs-toggle="modal" data-bs-target="#news-image-<{$story.id}>" href="#news-image-<{$story.id}>">
                    <img src="<{$story.picture}>" alt="<{$story.news_title|strip_tags}>">
                </a>
            </div>
        <{else}>
            <h2 role="heading" class="news-no-image"><{$story.topic_title}>: <{$story.news_title}></h2>
            <{if $story.poster|default:'' != ''}>
                <em><strong><{$lang_postedby}>: </strong><{$story.poster}><{/if}><{$lang_on}> <{$story.posttime}></em>
            <strong class="float-end hit-counter"><{$story.hits}> <{$lang_reads}></strong>
        <{/if}>
        <{if $story.files_attached|default:''}>
            <{$story.attached_link}>
        <{/if}>
        <{$story.text}>
    </article>
    <{$story.morelink}>
</section>

<!-- Modal Image -->
<div class="modal fade" id="news-image-<{$story.id}>" tabindex="-1" role="dialog" aria-labelledby="news-image-title-<{$story.id}>" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="news-image-title-<{$story.id}>"><{$story.news_title}></h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<{$smarty.const._CLOSE}>"></button>
            </div>
            <div class="modal-body">
                <img src="<{$story.picture}>" alt="<{$story.news_title|strip_tags}>" class="img-fluid">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div><!-- .modal-content -->
    </div><!-- .modal-dialog -->
</div><!-- .modal -->
