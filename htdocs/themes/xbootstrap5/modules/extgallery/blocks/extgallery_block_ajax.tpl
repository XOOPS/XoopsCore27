<div id="extgallery-carousel" class="carousel slide" data-bs-ride="carousel">
    <div id="ext-ind" class="carousel-indicators">
        <{foreach name=indicators item=photo from=$block.photos|default:null}>
            <button type="button" aria-label="<{$photo.photo_title|escape:'html'}>" data-bs-slide-to="<{$smarty.foreach.indicators.index}>"<{if $smarty.foreach.indicators.first}> class="active" aria-current="true"<{/if}> data-bs-target="#extgallery-carousel"></button>
        <{/foreach}>
    </div>
    <div class="carousel-inner">
        <{foreach name=slides item=photo from=$block.photos|default:null}>
            <div class="carousel-item<{if $smarty.foreach.slides.first}> active<{/if}>">
                <img src="<{$xoops_url}>/uploads/extgallery/public-photo/medium/<{$photo.photo_name}>" alt="<{$photo.photo_title}>">

                <div class="carousel-caption">
                    <h3><{$photo.photo_title}></h3>
                    <p><{$photo.photo_desc}></p>
                    <a class="btn btn-sm btn-primary" title="<{$photo.photo_title}>" href="<{$xoops_url}>/modules/extgallery/public-photo.php?photoId=<{$photo.photo_id}>">
                        <{$smarty.const._MB_EXTGALLERY_MOREINFO}>
                    </a>
                </div>
            </div>
        <{/foreach}>
    </div>
    <a class="carousel-control-prev" aria-label="Previous" href="#extgallery-carousel" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span></a>
    <a aria-label="Next" data-bs-slide="next" href="#extgallery-carousel" class="carousel-control-next"><span class="carousel-control-next-icon" aria-hidden="true"></span></a>
</div>
