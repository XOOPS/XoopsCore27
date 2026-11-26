<div class="text-center">
    <div>
        <form class="d-flex flex-wrap align-items-center justify-content-center gap-2" method="get" action="<{$pageName}>">
            <{$commentModeSelect->render()}>
            <{$commentOrderSelect->render()}>
            <{$commentRefreshButton->render()}>
            <{if ($commentPostButton|default:false) }>
                <{$commentPostButton->render()}>
            <{/if}>
            <{$commentPostHidden}>
        </form>
    </div>
</div>
