<ul class="nav nav-tabs">
    <li class="nav-item"><a class="nav-link<{if isset($view) && $view == "calmonth"}> active<{/if}>" href="view_calendar-month.php"<{if isset($view) && $view == "calmonth"}> aria-current="page"<{/if}>><{$lang.calmonth}></a>
    </li>
    <li class="nav-item"><a class="nav-link<{if isset($view) && $view =="calweek"}> active<{/if}>" href="view_calendar-week.php"<{if isset($view) && $view =="calweek"}> aria-current="page"<{/if}>><{$lang.calweek}></a>
    </li>
    <li class="nav-item"><a class="nav-link<{if isset($view) && $view =="year"}> active<{/if}>" href="view_year.php"<{if isset($view) && $view =="year"}> aria-current="page"<{/if}>><{$lang.year}></a>
    </li>
    <li class="nav-item"><a class="nav-link<{if isset($view) && $view =="month"}> active<{/if}>" href="view_month.php"<{if isset($view) && $view =="month"}> aria-current="page"<{/if}>><{$lang.month}></a>
    </li>
    <li class="nav-item"><a class="nav-link<{if isset($view) && $view == "week"}> active<{/if}>" href="view_week.php"<{if isset($view) && $view == "week"}> aria-current="page"<{/if}>><{$lang.week}></a>
    </li>
    <li class="nav-item"><a class="nav-link<{if isset($view) && $view == "day"}> active<{/if}>" href="view_day.php"<{if isset($view) && $view == "day"}> aria-current="page"<{/if}>><{$lang.day}></a>
    </li>
    <li class="nav-item"><a class="nav-link<{if isset($view) && $view == "agendaweek"}> active<{/if}>" href="view_agenda-week.php"<{if isset($view) && $view == "agendaweek"}> aria-current="page"<{/if}>><{$lang.agendaweek}></a>
    </li>
    <li class="nav-item"><a class="nav-link<{if isset($view) && $view == "agendaday"}> active<{/if}>" href="view_agenda-day.php"<{if isset($view) && $view == "agendaday"}> aria-current="page"<{/if}>><{$lang.agendaday}></a>
    </li>
    <li class="nav-item"><a class="nav-link<{if isset($view) && $view == "search"}> active<{/if}>" href="view_search.php"<{if isset($view) && $view == "search"}> aria-current="page"<{/if}>><{$lang.search}></a>
    </li>
    <{*<li class="nav-item"><a class="nav-link<{if isset($view) && $view == "newevent"}> active<{/if}>" href="view_new-event.php"<{if isset($view) && $view == "newevent"}> aria-current="page"<{/if}>><{$lang.newevent}></a>*}>
    <{*</li>*}>
</ul>
