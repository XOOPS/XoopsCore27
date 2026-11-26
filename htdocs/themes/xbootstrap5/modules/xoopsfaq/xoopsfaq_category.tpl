<ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index.php"><{$smarty.const._XO_LA_XOOPSFAQ}></a></li>
    <li class="breadcrumb-item"><a href="index.php"><{$smarty.const._XO_LA_MAIN}></a></li>
    <li class="breadcrumb-item"><a href="#"><{$category_name}></a></li>
</ol>


<ul class="list-group">
    <!-- start question loop -->
    <{foreach item=question from=$questions|default:null}>
        <li class="list-group-item"><a href="#q<{$question.id}>"><span class="fa-solid fa-hand-point-right"></span>&nbsp;&nbsp;<{$question.title}></a>
        </li>
    <{/foreach}>
    <!-- end question loop -->
</ul>


<!-- start question and answer loop -->
<{foreach item=question from=$questions|default:null}>
    <div class="card">
        <div class="card-header"><a id="q<{$question.id}>" name="q<{$question.id}>"></a><span class="badge text-bg-danger"><{$question.title}></span>
        </div>
        <div class="card-body">
            <td class="even"><{$question.answer}>
                <div style="text-align: right;"><a href="#top"><span class="badge text-bg-primary"><{$smarty.const._XO_LA_BACKTOTOP}></span></a></div>
            </td>
        </div>
    </div>
<{/foreach}>
<!-- end question and answer loop -->

<br><br>
<div style="text-align:center;"><b>[ <a href="index.php"><{$smarty.const._XO_LA_BACKTOINDEX}></a> ]</b></div>

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
