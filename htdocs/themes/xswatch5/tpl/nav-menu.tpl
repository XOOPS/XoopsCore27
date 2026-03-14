    <div class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a href="<{$xoops_url}>" class="navbar-brand xlogo" title="<{$xoops_sitename}>">
                <img src="<{$xoops_imageurl}>images/logo.png" alt="<{$xoops_sitename}>">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarResponsive">
                <{function name=renderMenu}>
                    <{assign var="level" value=$level|default:0}>
                    <ul class="dropdown-menu">
                    <{foreach $items as $item}>
                        <li<{if $item.children}> class="dropdown-submenu"<{/if}>>
                            <a class="dropdown-item<{if $item.children}> dropdown-toggle<{/if}>" href="<{if $item.url neq ''}><{$item.url|escape}><{else}>#<{/if}>"<{if $item.children}> role="button" data-bs-toggle="dropdown" aria-expanded="false"<{/if}> target="<{$item.target}>" <{if $item.target eq '_blank'}>rel="noopener noreferrer"<{/if}>>
                                <{$item.prefix}> <{$item.title|escape}> <{$item.suffix}>
                            </a>
                            <{if $item.children}>
                                <{call name=renderMenu items=$item.children level=$level+1}>
                            <{/if}>
                        </li>
                    <{/foreach}>
                    </ul>
                <{/function}>
                <ul class="navbar-nav me-auto">
                    <{foreach $xoMenuCategories as $cat}>
                        <li class="nav-item<{if $cat.items}> dropdown<{/if}>">
                            <a class="nav-link<{if $cat.items}> dropdown-toggle<{/if}>" href="<{$cat.category_url|escape|default:'#'}>" <{if $cat.items}>role="button" data-bs-toggle="dropdown" aria-expanded="false"<{/if}> target="<{$cat.category_target}>" <{if $cat.category_target eq '_blank'}>rel="noopener noreferrer"<{/if}>>
                                <{$cat.category_prefix}> <{$cat.category_title|escape}> <{$cat.category_suffix}>
                            </a>
                            <{if $cat.items}>
                                <{call name=renderMenu items=$cat.items level=0}>
                            <{/if}>
                        </li>
                    <{/foreach}>
                </ul>
                <{if $xoops_search|default:false}>
                <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <form class="d-flex align-items-center my-2 my-lg-0" role="search" action="<{xoAppUrl url='search.php'}>" method="get">
						<div class="input-group mb-3">
							<input class="form-control" type="text" name="query" placeholder="<{$smarty.const.THEME_SEARCH_TEXT}>">
							<div class="input-group-append">
								<button class="btn btn-secondary" type="submit"><i class="fa fa-search" aria-hidden="true"></i></button>
							</div>
						</div>
						<input type="hidden" name="action" value="results">
                    </form>
                </li>
                </ul>
                <{/if}>
            </div>
        </div>
    </div>
