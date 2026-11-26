<ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index.php"><{$smarty.const._XO_LA_XOOPSFAQ}></a></li>
</ol>

<div class="alert alert-info"><{$smarty.const._XO_LA_TOC}></div>

<ul class="list-group">
    <{foreach item=category from=$categories|default:null}>&nbsp;&nbsp;&nbsp;
        <strong><a href="index.php?cat_id=<{$category.id}>">
                <li class="list-group-item"><span class="badge text-bg-danger"><span class="fa-solid fa-check"></span>&nbsp;&nbsp;<{$category.name}></span>
                </li>
            </a></strong>
        <!-- start question loop -->
        <{foreach item=question from=$category.questions|default:null}>
            <li class="list-group-item">&nbsp;<a href="index.php?cat_id=<{$category.id}>#q<{$question.link}>"><span
                            class="fa-solid fa-hand-point-right"></span>&nbsp;&nbsp;<{$question.title}></a></li>
        <{/foreach}>
        <!-- end question loop -->
    <{/foreach}>
</ul>

