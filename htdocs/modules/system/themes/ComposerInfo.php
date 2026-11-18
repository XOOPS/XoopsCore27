<?php

// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === 'ComposerInfo.php') {
    http_response_code(403);
    exit('Access Denied');
}

class ComposerInfo
{
    /**
     * Assign the bundled packages, with the advisories that cover them, to the template.
     *
     * @param XoopsTpl      $xoopsTpl
     * @param callable|null $fetch    replaces the advisory request (tests); see ComposerAdvisories::forPackages()
     *
     * @return void
     */
    public static function getComposerInfo(XoopsTpl $xoopsTpl, ?callable $fetch = null)
    {
        global $xoopsLogger;

        try {
            // Define the path to the composer.lock file
            $composerLockPath = XOOPS_TRUST_PATH . '/composer';
            // Get the packages data from composer.lock file
            $packages = self::readComposerLockFile($composerLockPath);
            // Extract package name and version
            $composerPackages = self::extractPackageNamesAndVersions($packages);
            // Published advisories that cover an installed version (cached, see ComposerAdvisories)
            include_once __DIR__ . '/ComposerAdvisories.php';
            $check    = ComposerAdvisories::forPackages($composerPackages, $fetch);
            $affected = 0;
            foreach ($composerPackages as $i => $package) {
                $composerPackages[$i]['advisories'] = $check['advisories'][$package['name']] ?? [];
                $affected += [] === $composerPackages[$i]['advisories'] ? 0 : 1;
            }
            // Assign the $composerPackages array to the Smarty template
            $xoopsTpl->assign('composerPackages', $composerPackages);
            $xoopsTpl->assign('composerAdvisoriesAvailable', $check['available']);
            $xoopsTpl->assign('composerAdvisoriesAffected', $affected);
        } catch (Exception $e) {
            // Handle any exception and log the error using XOOPS Logger
            $xoopsLogger->handleError(E_USER_ERROR, $e->getMessage(), __FILE__, __LINE__);
            echo 'An error occurred. Please try again later.';
        }
    }

    // Function to read and parse composer.lock file
    private static function readComposerLockFile(string $composerLockPath): array
    {
        $composerLockFile = $composerLockPath . '.lock';
        if (!file_exists($composerLockFile)) {
            $composerLockFile = $composerLockPath . '.dist.lock';
        }
        if (!file_exists($composerLockFile)) {
            throw new InvalidArgumentException('Failed to read the file: ' . $composerLockFile);
        }

        $composerLockData = file_get_contents($composerLockFile);

        if ($composerLockData === false) {
            throw new RuntimeException('Failed to read the file: ' . $composerLockFile);
        }

        $composerData = json_decode($composerLockData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new JsonException('Failed to decode JSON data: ' . json_last_error_msg());
        }

        return $composerData['packages'] ?? [];
    }

    // Function to extract package name and version (using array_map for optimization)
    private static function extractPackageNamesAndVersions(array $packages): array
    {
        return array_map(
            static fn($package) => [
                'name'    => $package['name'],
                'version' => $package['version'],
            ],
            $packages,
        );
    }
}
