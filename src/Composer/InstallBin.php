<?php

namespace andrewmriley\logasaurus\Composer;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

class InstallBin {

  public static function install(Event $event): void {
    $fs = new Filesystem();

    $from = dirname(__DIR__,2) . '/bin/logasaurus';
    $binDir = $event->getComposer()->getConfig()->get('bin-dir');
    $to = $binDir . '/logasaurus';
    $fs->copy($from, $to);
  }

}
