<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "https://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="https://www.w3.org/1999/xhtml" xml:lang="<{$xoops_langcode}>" lang="<{$xoops_langcode}>">
<head>
    <!-- Assign Theme name -->
    <{assign var=theme_name value=$xoTheme->folderName}>

    <!-- Title and meta -->
    <meta http-equiv="content-language" content="<{$xoops_langcode}>"/>
    <meta http-equiv="content-type" content="text/html; charset=<{$xoops_charset}>"/>
    <title><{if !empty($xoops_pagetitle)}><{$xoops_pagetitle}> - <{/if}><{$xoops_sitename}></title>
    <meta name="robots" content="<{$xoops_meta_robots}>"/>
    <meta name="keywords" content="<{$xoops_meta_keywords}>"/>
    <meta name="description" content="<{$xoops_meta_description}>"/>
    <meta name="rating" content="<{$xoops_meta_rating}>"/>
    <meta name="author" content="<{$xoops_meta_author}>"/>
    <meta name="copyright" content="<{$xoops_meta_copyright}>"/>
    <meta name="generator" content="XOOPS"/>

    <!-- Rss -->
    <link rel="alternate" type="application/rss+xml" title="" href="<{xoAppUrl url='backend.php'}>"/>

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/ico" href="<{xoImgUrl url='icons/favicon.ico'}>"/>
    <link rel="icon" type="image/png" href="<{xoImgUrl url='icons/favicon.png'}>"/>

    <!-- Sheet Css -->
    <link rel="stylesheet" type="text/css" media="all" title="Style sheet" href="<{xoAppUrl url='xoops.css'}>"/>
    <link rel="stylesheet" type="text/css" media="all" title="Style sheet" href="<{xoImgUrl url='style.css'}>"/>
    <!--[if <= IE 8]>
    <link rel="stylesheet" href="<{xoImgUrl url='styleIE8.css'}>" type="text/css"/>
    <![endif]-->

    <!-- customized header contents -->
    <{$xoops_module_header}>
</head>
<body id="<{$xoops_dirname}>" class="<{$xoops_langcode}>">

<!-- Start Header -->
<table cellspacing="0">
    <tr id="header">
        <td id="headerlogo"><a href="<{xoAppUrl url='/'}>" title="<{$xoops_sitename}>"><img src="<{xoImgUrl url='xoops-logo.png'}>"
                                                                                      alt="<{$xoops_sitename}>"/></a></td>
        <td id="headerbanner"><{$xoops_banner}></td>
    </tr>
    <tr>
        <td id="headerbar" colspan="2">&nbsp;</td>
    </tr>
</table>
<!-- End header -->

<table cellspacing="0">
    <tr>
        <!-- Start left blocks loop -->
        <{if isset($xoops_showlblock)}>
        <td id="leftcolumn">
            <{foreach item=block from=$xoBlocks.canvas_left|default:null}>
            <{include file="$theme_name/theme_blockleft.tpl"}>
            <{/foreach}>
        </td>
        <{/if}>
        <!-- End left blocks loop -->

        <td id="centercolumn">
            <!-- Display center blocks if any -->
            <{if $xoBlocks.page_topleft || $xoBlocks.page_topcenter || $xoBlocks.page_topright}>
            <table cellspacing="0">
                <tr>
                    <td id="centerCcolumn" colspan="2">
                        <!-- Start center-center blocks loop -->
                        <{foreach item=block from=$xoBlocks.page_topcenter|default:null}>
                        <{include file="$theme_name/theme_blockcenter_c.tpl"}>
                        <{/foreach}>
                        <!-- End center-center blocks loop -->
                    </td>
                </tr>
                <tr>
                    <td id="centerLcolumn">
                        <!-- Start center-left blocks loop -->
                        <{foreach item=block from=$xoBlocks.page_topleft|default:null}>
                        <{include file="$theme_name/theme_blockcenter_l.tpl"}>
                        <{/foreach}>
                        <!-- End center-left blocks loop -->
                    </td>
                    <td id="centerRcolumn">
                        <!-- Start center-right blocks loop -->
                        <{foreach item=block from=$xoBlocks.page_topright|default:null}>
                        <{include file="$theme_name/theme_blockcenter_r.tpl"}>
                        <{/foreach}>
                        <!-- End center-right blocks loop -->
                    </td>
                </tr>
            </table>
            <{/if}>
            <!-- End center top blocks loop -->

            <!-- Start content module page -->
            <{if $xoops_contents && ($xoops_contents != ' ') }>
            <div id="content"><{$xoops_contents}></div>
            <{/if}>
            <!-- End content module -->

            <!-- Start center bottom blocks loop -->
            <{if $xoBlocks.page_bottomleft or $xoBlocks.page_bottomright or $xoBlocks.page_bottomcenter}>
            <table cellspacing="0">
                <{if $xoBlocks.page_bottomcenter}>
                <tr>
                    <td id="bottomCcolumn" colspan="2">
                        <{foreach item=block from=$xoBlocks.page_bottomcenter|default:null}>
                        <{include file="$theme_name/theme_blockcenter_c.tpl"}>
                        <{/foreach}>
                    </td>
                </tr>
                <{/if}>

                <{if $xoBlocks.page_bottomleft or $xoBlocks.page_bottomright}>
                <tr>
                    <td id="bottomLcolumn">
                        <{foreach item=block from=$xoBlocks.page_bottomleft|default:null}>
                        <{include file="$theme_name/theme_blockcenter_l.tpl"}>
                        <{/foreach}>
                    </td>

                    <td id="bottomRcolumn">
                        <{foreach item=block from=$xoBlocks.page_bottomright|default:null}>
                        <{include file="$theme_name/theme_blockcenter_r.tpl"}>
                        <{/foreach}>
                    </td>
                </tr>
                <{/if}>
            </table>
            <{/if}>
            <!-- End center bottom blocks loop -->
        </td>

        <!-- Start right blocks loop -->
        <{if isset($xoops_showrblock)}>
        <td id="rightcolumn">
            <{foreach item=block from=$xoBlocks.canvas_right|default:null}>
            <{include file="$theme_name/theme_blockright.tpl"}>
            <{/foreach}>
        </td>
        <{/if}>
        <!-- End right blocks loop -->


        <!-- =============================== Start Footer blocks loop ===============================  -->
        <{if $xoBlocks.footer_left || $xoBlocks.footer_right || $xoBlocks.footer_center}>
        <table>

            <tr>
                <{if $xoBlocks.footer_left}>
                <td id="footerLeft">
                    <{foreach item=block from=$xoBlocks.footer_left|default:null}>
                    <{include file="$theme_name/theme_blockfooter_l.tpl"}>
                    <{/foreach}>
                </td>
                <{/if}>


                <{if $xoBlocks.footer_center}>
                <td id="footerCenter">
                    <{foreach item=block from=$xoBlocks.footer_center|default:null}>
                    <{include file="$theme_name/theme_blockfooter_c.tpl"}>
                    <{/foreach}>
                </td>
                <{/if}>

                <{if $xoBlocks.footer_right}>
                <td id="footerRight">
                    <{foreach item=block from=$xoBlocks.footer_right|default:null}>
                    <{include file="$theme_name/theme_blockfooter_r.tpl"}>
                    <{/foreach}>
                </td>
                <{/if}>

            </tr>
        </table>

        <{/if}>
        <!-- ===============================  End Footer blocks loop =============================== -->

    </tr>
</table>

<!-- Start footer -->
<table cellspacing="0">
    <tr id="footerbar">
        <td><{$xoops_footer}></td>
    </tr>
</table>
<!-- End footer -->
<!--{xo-logger-output}-->
</body>
</html>
