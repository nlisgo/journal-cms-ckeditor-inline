<?php

/**
 * @file
 * Contains \DrupalProject\composer\ScriptHandler.
 */

namespace DrupalProject\composer;

use Composer\Script\Event;
use Composer\Semver\Comparator;
use DrupalFinder\DrupalFinder;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

class ScriptHandler {

  public static function createRequiredFiles(Event $event) {
    $fs = new Filesystem();
    $root = getcwd();
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot($root);
    $drupalRoot = $drupalFinder->getDrupalRoot();
    $configRoot = $root . '/config';
    $srcRoot = $root . '/src';

    $dirs = [
      'modules',
      'profiles',
      'themes',
    ];

    // Required for unit testing
    foreach ($dirs as $dir) {
      if (!$fs->exists($drupalRoot . '/'. $dir)) {
        $fs->mkdir($drupalRoot . '/'. $dir);
        $fs->touch($drupalRoot . '/'. $dir . '/.gitkeep');
      }
    }

    // Prepare the settings file for installation
    if (!$fs->exists($drupalRoot . '/sites/default/settings.php') and $fs->exists($drupalRoot . '/sites/default/default.settings.php')) {
      $fs->copy($drupalRoot . '/sites/default/default.settings.php', $drupalRoot . '/sites/default/settings.php');
      require_once $drupalRoot . '/core/includes/bootstrap.inc';
      require_once $drupalRoot . '/core/includes/install.inc';
      $settings['config_directories'] = [
        CONFIG_SYNC_DIRECTORY => (object) [
          'value' => Path::makeRelative($drupalFinder->getComposerRoot() . '/config/sync', $drupalRoot),
          'required' => TRUE,
        ],
      ];
      drupal_rewrite_settings($settings, $drupalRoot . '/sites/default/settings.php');
      $fs->chmod($drupalRoot . '/sites/default/settings.php', 0666);
      $event->getIO()->write("Create a sites/default/settings.php file with chmod 0666");
    }

    // Create the files directory with chmod 0777
    if (!$fs->exists($drupalRoot . '/sites/default/files')) {
      $oldmask = umask(0);
      $fs->mkdir($drupalRoot . '/sites/default/files', 0777);
      umask($oldmask);
      $event->getIO()->write("Create a sites/default/files directory with chmod 0777");
    }

    if ($fs->exists($configRoot . '/settings.php')) {
      if ($fs->exists($drupalRoot . '/sites/default/settings.php')) {
        $fs->chmod($drupalRoot . '/sites/default', 0755);
        $fs->remove($drupalRoot . '/sites/default/settings.php');
      }
      $fs->copy($configRoot . '/settings.php', $drupalRoot . '/sites/default/settings.php');
      $fs->chmod($drupalRoot . '/sites/default/settings.php', 0666);
    }

    // Create private folder, with known sub-folders.
    $localSettings = str_replace('"', "'", file_get_contents($configRoot . '/local.settings.php'));
    if (preg_match("/'file_private_path'[^']+'(?P<file_private_path>[^']+)/", $localSettings, $match)) {
      $filePrivatePath = rtrim($match['file_private_path'], '/');

      $filePrivatePath = preg_replace('~^\./~', '/', $filePrivatePath);

      if (substr($match['file_private_path'], 0, 1) != '/') {
        $filePrivatePath = $drupalRoot . $filePrivatePath;
      }

      if (!$fs->exists($filePrivatePath)) {
        $fs->mkdir($filePrivatePath, 0755);
      }
    }

    // Create symlink to custom modules folder.
    if ($fs->exists($srcRoot . '/modules') && !$fs->exists($drupalRoot . '/modules/custom')) {
      $fs->symlink('../../src/modules', $drupalRoot . '/modules/custom');
    }

    // Create symlink to custom libraries folder.
    if ($fs->exists($srcRoot . '/libraries') && !$fs->exists($drupalRoot . '/libraries')) {
      $fs->symlink('../src/libraries', $drupalRoot . '/libraries');
    }
  }

  /**
   * Checks if the installed version of Composer is compatible.
   *
   * Composer 1.0.0 and higher consider a `composer install` without having a
   * lock file present as equal to `composer update`. We do not ship with a lock
   * file to avoid merge conflicts downstream, meaning that if a project is
   * installed with an older version of Composer the scaffolding of Drupal will
   * not be triggered. We check this here instead of in drupal-scaffold to be
   * able to give immediate feedback to the end user, rather than failing the
   * installation after going through the lengthy process of compiling and
   * downloading the Composer dependencies.
   *
   * @see https://github.com/composer/composer/pull/5035
   */
  public static function checkComposerVersion(Event $event) {
    $composer = $event->getComposer();
    $io = $event->getIO();

    $version = $composer::VERSION;

    // The dev-channel of composer uses the git revision as version number,
    // try to the branch alias instead.
    if (preg_match('/^[0-9a-f]{40}$/i', $version)) {
      $version = $composer::BRANCH_ALIAS_VERSION;
    }

    // If Composer is installed through git we have no easy way to determine if
    // it is new enough, just display a warning.
    if ($version === '@package_version@' || $version === '@package_branch_alias_version@') {
      $io->writeError('<warning>You are running a development version of Composer. If you experience problems, please update Composer to the latest stable version.</warning>');
    }
    elseif (Comparator::lessThan($version, '1.0.0')) {
      $io->writeError('<error>Drupal-project requires Composer version 1.0.0 or higher. Please update your Composer before continuing</error>.');
      exit(1);
    }
  }

}
