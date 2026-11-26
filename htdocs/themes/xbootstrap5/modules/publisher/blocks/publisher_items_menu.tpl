<{if !empty($block.currentcat)}>
    <span class="badge text-bg-success" style="margin-right: 3px;">
        <{$block.currentcat}>
    </span>
<{/if}>

<{foreach item=category from=$block.categories|default:null}>
    <span class="badge text-bg-primary" style="margin-right: 3px;"><{$category.categoryLink}></span>
    <{if !empty($category.items)}>
        <{foreach item=item from=$category.items|default:null}>
            <span class="badge text-bg-primary" style="margin-right: 3px;"><{$item.titleLink}></span>
        <{/foreach}>
    <{/if}>
<{/foreach}>
