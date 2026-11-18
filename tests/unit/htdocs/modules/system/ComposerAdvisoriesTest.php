<?php
/**
 * Tests for the advisory matching behind the Composer Packages panel
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package   system
 * @since     2.7.5
 */

declare(strict_types=1);

namespace modulessystem;

use ComposerAdvisories;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once XOOPS_ROOT_PATH . '/modules/system/themes/ComposerAdvisories.php';

/**
 * Matching runs against a fixed copy of a Packagist answer: no test here uses the network.
 */
final class ComposerAdvisoriesTest extends TestCase
{
    /**
     * @return array<string, mixed> advisories per package name
     */
    private static function advisories(): array
    {
        $data = json_decode((string) file_get_contents(dirname(__DIR__, 4) . '/fixtures/packagist-advisories.json'), true);

        return $data['advisories'];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}> package, installed version, marked
     */
    public static function installedVersions(): array
    {
        return [
            'inside the range'                        => ['acme/in-range', '1.2.3', true],
            'the first fixed release'                 => ['acme/in-range', '1.2.5', false],
            'above the range'                         => ['acme/out-of-range', '2.0.0', false],
            'a pre-release the range starts below'    => ['acme/beta-inside', 'v2.0.0-Beta1', true],
            'a release candidate of the fixed release' => ['acme/rc-before-fix', '1.3.4-RC2', false],
            'the release before the fixed one'        => ['acme/rc-before-fix', 'v1.3.3', true],
            'the second of two ranges'                => ['acme/several-ranges', '3.1.0', true],
            'the first of two ranges'                 => ['acme/several-ranges', '2.0.4', true],
            'between two ranges'                      => ['acme/several-ranges', '2.5.0', false],
            'a branch instead of a release'           => ['acme/dev-branch', 'dev-master', false],
            // what cannot be compared is shown, not passed over
            'a version nobody can read'               => ['acme/in-range', 'not a version', true],
            'no version at all'                       => ['acme/in-range', '', true],
            'a range nobody can read'                 => ['acme/unreadable-range', '1.0.0', true],
            'a package without advisories'            => ['acme/clean', '1.0.0', false],
            'a package Packagist did not answer for'  => ['acme/unknown', '1.0.0', false],
        ];
    }

    #[Test]
    #[DataProvider('installedVersions')]
    public function aPackageIsMarkedOnlyWhenItsVersionIsInsideAnAffectedRange(string $name, string $version, bool $marked): void
    {
        $found = ComposerAdvisories::match([$name => $version], self::advisories());

        $this->assertSame($marked, isset($found[$name]));
    }

    #[Test]
    public function aMarkedPackageCarriesTheAdvisoryTexts(): void
    {
        $found = ComposerAdvisories::match(['acme/in-range' => '1.2.3'], self::advisories());

        $this->assertSame(
            [
                'acme/in-range' => [
                    [
                        'title'    => 'In range: unsafe handling of a crafted request',
                        'link'     => 'https://github.com/advisories/GHSA-aaaa-aaaa-aaaa',
                        'cve'      => 'CVE-2026-00001',
                        'severity' => 'high',
                    ],
                ],
            ],
            $found
        );
    }

    #[Test]
    public function onlyAnHttpsLinkIsKeptAndMissingFieldsBecomeEmptyText(): void
    {
        $found = ComposerAdvisories::match(['acme/bad-link' => '1.0.0'], self::advisories());

        $this->assertSame(
            [
                ['title' => 'Link is not an https address', 'link' => '', 'cve' => '', 'severity' => 'medium'],
                ['title' => 'Second advisory for the same package', 'link' => '', 'cve' => 'CVE-2026-00009', 'severity' => ''],
            ],
            $found['acme/bad-link']
        );
    }

    #[Test]
    public function anAnswerOfTheWrongShapeMarksNothing(): void
    {
        $versions = ['acme/in-range' => '1.2.3'];

        $this->assertSame([], ComposerAdvisories::match($versions, ['acme/in-range' => 'none']));
        $this->assertSame([], ComposerAdvisories::match($versions, ['acme/in-range' => ['text', 7, null]]));
        $this->assertSame([], ComposerAdvisories::match($versions, ['acme/in-range' => [['affectedVersions' => ['<2.0']]]]));
        $this->assertSame([], ComposerAdvisories::match($versions, ['acme/in-range' => [['title' => 'no range given']]]));
    }
}
