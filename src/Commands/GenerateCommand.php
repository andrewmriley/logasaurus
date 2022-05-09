<?php

namespace Logasaurus\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class GenerateCommand extends Command {

  protected function configure(): void {
    $this
      ->setName('generate')
      ->setDescription('Updates the changelog with the latest entries.')
      ->addArgument('version', InputArgument::REQUIRED, 'The version number.')
      ->addArgument('date', InputArgument::OPTIONAL, 'The date for the changelog.', date('Y-m-d'))
      ->setHelp(
        <<<'EOT'
        The <info>generate</info> command creates or updates the configured changelog file.
        Files describing changes should be added to the configured <comment>filesPath</comment> directory
        (changelogs/unreleased/ is used by default) as work is completed.
        The individual change file names should match their related Jira ticket number: eg. "JIRA-9999"; and contain
        only the description text for the change.
        EOT
      );
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return int
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $config = Yaml::parseFile('.logasaurus.yml');

    $changelogFile = $config['changelogFile'] ?? '';
    $filesPath = $config['filesPath'] ?? 'changelogs/unreleased/';
    $finalize = $config['finalize'] ?? FALSE;

    if (empty($changelogFile)) {
      $output->writeln('No output file name was specified in "changelogFile", update your ".logasaurus.yml" settings.');
      return 1;
    }

    $fs = new Filesystem();
    if (!$fs->exists($filesPath)) {
      $output->writeln(sprintf('The filesPath %s does not exist.', $filesPath));
      return 1;
    }

    $sourceFiles = $this->getFiles($filesPath);
    if (empty($sourceFiles)) {
      $output->writeln(
        sprintf('There were no files found at the filesPath %s so nothing could be updated.', $filesPath));
      return 0;
    }

    $change_list = $this->createList($sourceFiles);
    $version = $input->getArgument('version');
    $date = $input->getArgument('date');

    try {
      $this->updateChangelog($changelogFile, $version, $date, $change_list);
      $this->cleanupFiles($filesPath, $sourceFiles, $changelogFile, $finalize);
      return 0;
    } catch (Exception $e) {
      $output->writeln(sprintf('Error writing output to changelog %s',
        $e->getMessage()));
      return 1;
    }
  }

  /**
   * @param string $directory Directory path where to find files to include in the changelog.
   * @return array List of file names and contents.
   */
  private function getFiles(string $directory): array {
    $files = [];
    $finder = new Finder();
    $finder->files()->in($directory);
    if ($finder->hasResults()) {
      foreach ($finder as $file) {
        $files[] = [
          'name' => $file->getRelativePathname(),
          'contents' => $file->getContents(),
        ];
      }
    }
    return $files;
  }

  /**
   * @param array $files List of file names and contents.
   * @return string
   */
  private function createList(array $files): string {
    $list = '';
    foreach ($files as $file) {
      $splitName = pathinfo($file['name'], PATHINFO_FILENAME);
      $list .= ' * ' . $splitName . ' ' . trim($file['contents']) . "\n";
    }
    return $list;
  }

  /**
   * @param string $path Changelog path.
   * @param string $version Version string to use in the changelog.
   * @param string $date Date to use in the changelog.
   * @param string $list List of changes from consumed files.
   *
   * @throws Exception
   */
  private function updateChangelog(
    string $path,
    string $version,
    string $date,
    string $list
  ): void {
    $newContents = "\n## >> $version ($date)\n$list";
    $contents = file_get_contents($path);

    if (empty($contents)) {
      $contents = $newContents;
    }
    else {
      $last_entry_pos = strpos($contents, '## >> ');
      if ($last_entry_pos !== FALSE) {
        $contents = substr_replace($contents, $newContents, $last_entry_pos, 0);
      }
      else {
        $contents .= $newContents;
      }
    }

    $fs = new Filesystem();
    $fs->dumpFile($path, $contents);
  }

  /**
   * @param string $path Directory path of files to be consumed.
   * @param array $sourceFiles List of file names and contents.
   * @param string $changelog Path to changelog.
   * @param bool $finalize Should the change log be committed and tagged.
   */
  private function cleanupFiles(
    string $path,
    array $sourceFiles,
    string $changelog,
    bool $finalize
  ): void {
    foreach ($sourceFiles as $file) {
      passthru('git rm ' . $path . $file['name'], $status);
    }
    passthru('git add ' . $changelog);
    if ($finalize) {
      passthru('git commit -m "Finalize version {version}"');
      passthru('git tag {version}');
    }
  }

}
