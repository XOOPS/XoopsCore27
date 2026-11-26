<script>
    function openFaqFromHash() {
        const question = document.getElementById(window.location.hash.slice(1));
        const answer = question && question.querySelector('.collapse');
        if (answer) {
            bootstrap.Collapse.getOrCreateInstance(answer, {toggle: false}).show();
        }
    }
    document.addEventListener('DOMContentLoaded', openFaqFromHash);
    window.addEventListener('hashchange', openFaqFromHash);
</script>
<ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index.php"><{$smarty.const._XO_LA_XOOPSFAQ}></a></li>
    <li class="breadcrumb-item active"><a href="#"><{$category_name}></a></li>
</ol>

<div class="accordion" id="accordionExample">
<{foreach item=question from=$questions|default:null}>
    <div id="q<{$question.id}>" class="card">
        <div class="card-header" id="faqheading<{$question.id}>">
            <h2 class="mb-0">
                <button class="btn btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#collapsefaq<{$question.id}>" aria-expanded="false" aria-controls="collapsefaq<{$question.id}>">
                    <{$question.title}>
                </button>
            </h2>
        </div>

        <div id="collapsefaq<{$question.id}>" class="collapse" aria-labelledby="faqheading<{$question.id}>" data-bs-parent="#accordionExample">
            <div class="card-body">
                <{$question.answer}>
            </div>
        </div>
    </div>
<{/foreach}>
</div>

<div style="text-align:center; padding: 3px; margin:3px;">
    <{$commentsnav}>
    <{$lang_notice}><{$smarty.const._XO_LA_BACKTOINDEX}>
</div>

<div style="margin:3px; padding: 3px;">
    <!-- start comments loop -->
    <{if isset($comment_mode)}>
        <{if $comment_mode == "flat"}>
            <{include file="db:system_comments_flat.tpl"}>
        <{elseif $comment_mode == "thread"}>
            <{include file="db:system_comments_thread.tpl"}>
        <{elseif $comment_mode == "nest"}>
            <{include file="db:system_comments_nest.tpl"}>
        <{/if}>
    <{/if}>
    <!-- end comments loop -->
</div>
