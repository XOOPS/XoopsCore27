<?php

declare(strict_types=1);

namespace Xoops\ModuleTools\Common;

/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.
*/

/**
 * Appends the standard "developer/share" config tail to a module's $modversion,
 * in one consistent order, so every module shows the SAME options in the SAME
 * place (directly above the system Comment Rules) without copy-pasting blocks.
 *
 * Title/description use the module's own language constants by convention:
 *   _MI_<DIRNAME-UPPER>_<SUFFIX>  and  ..._DESC
 * (e.g. pedigree + 'displaySampleButton' -> _MI_PEDIGREE_SHOW_SAMPLE_BUTTON).
 * The constant NAME is passed through (XOOPS resolves it when rendering the form),
 * so a module just has to define those constants in its modinfo language file.
 *
 * @api Stable Common-tier API.
 * @since 1.3.0
 */
final class StandardConfig
{
    /**
     * config name => [constant-suffix, formtype, valuetype, default]
     *
     * @var array<string, array{0:string,1:string,2:string,3:int}>
     */
    private const array CATALOG = [
        'socialBookmarks'       => ['SOCIAL_BOOKMARKS', 'yesno', 'int', 1],
        'displaySampleButton'   => ['SHOW_SAMPLE_BUTTON', 'yesno', 'int', 1],
        'displayDeveloperTools' => ['SHOW_DEV_TOOLS', 'yesno', 'int', 0],
    ];

    /**
     * Append the requested standard config items to $modversion['config'].
     *
     * @param array<string,mixed>  $modversion  the module's $modversion (by reference)
     * @param string               $dirname     module dirname (e.g. 'pedigree')
     * @param list<string>         $include     which items, in order (default: all)
     * @param array<string,int>    $defaults    override defaults per config name
     */
    public static function append(array &$modversion, string $dirname, array $include = [], array $defaults = []): void
    {
        $upper   = \mb_strtoupper($dirname);
        $include = [] === $include ? \array_keys(self::CATALOG) : $include;

        foreach ($include as $name) {
            if (!isset(self::CATALOG[$name])) {
                continue;
            }
            [$suffix, $formtype, $valuetype, $default] = self::CATALOG[$name];
            $modversion['config'][] = [
                'name'        => $name,
                'title'       => '_MI_' . $upper . '_' . $suffix,
                'description' => '_MI_' . $upper . '_' . $suffix . '_DESC',
                'formtype'    => $formtype,
                'valuetype'   => $valuetype,
                'default'     => $defaults[$name] ?? $default,
            ];
        }
    }
}
