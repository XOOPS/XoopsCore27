<ul class="nav flex-column">
    <li class="nav-item"><a class="nav-link<{if empty($block.nothome)}> active<{/if}>" href="<{xoAppUrl}>" title="<{$block.lang_home}>"><span
                    class="fa-solid fa-home"></span> <{$block.lang_home}></a></li>
    <!-- start module menu loop -->
    <{foreach item=module from=$block.modules|default:null}>
        <li class="nav-item">
            <a class="nav-link<{if !empty($module.highlight)}> active<{/if}>" href="<{$xoops_url}>/modules/<{$module.directory}>/" title="<{$module.name}>"><span class="fa-solid <{if !empty($module.highlight)}>fa-square-check<{else}>fa-check<{/if}>"></span>
                <{$module.name}>
            </a>
            <ul>
                <{foreach item=sublink from=$module.sublinks|default:null}>
                    <li>
                        <a class="nav-link" href="<{$sublink.url}>" title="<{$sublink.name}>"><{$sublink.name}></a>
                    </li>
                <{/foreach}>
            </ul>
        </li>
    <{/foreach}>
    <!-- end module menu loop -->
</ul>
