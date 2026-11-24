<?php

/**
 * Arahkan config_sync_directory ke /opt/drupal/config.
 *
 * Folder itu di-mount dari repo. Aman diulang: baris lama dibuang dulu.
 */

$settings_file = '/opt/drupal/web/sites/default/settings.php';
$new_line = "\$settings['config_sync_directory'] = '/opt/drupal/config';\n";

// settings.php dan foldernya dikunci saat instalasi. Buka dulu supaya bisa ditulis.
@chmod(dirname($settings_file), 0755);
@chmod($settings_file, 0644);

$kept = [];
foreach (file($settings_file) as $line) {
  $is_old_setting = strpos(ltrim($line), "\$settings['config_sync_directory']") === 0;
  if (!$is_old_setting) {
    $kept[] = $line;
  }
}
$kept[] = $new_line;

file_put_contents($settings_file, implode('', $kept));
echo "config_sync_directory ok\n";
