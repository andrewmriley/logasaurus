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

/**
 * Class GenerateCommand
 * @package andrewmriley\logasaurus\App\Commands
 */
class GenerateCommand extends Command {
  /**
   * @var OutputInterface
   */
  private $output;

  /**
   *
   */
  protected function configure(): void {
    $this->setName('generate')
      ->setDescription('Updates the changelog with the latest entries.')
      ->setHelp('Put text here.')
      ->addArgument('version', InputArgument::REQUIRED,
        'Enter the version number.')
      ->addArgument('date', InputArgument::OPTIONAL,
        'Enter the date for the changelog.', date('Y-m-d'));
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   * @return int|null
   */
  protected function execute(
    InputInterface $input,
    OutputInterface $output
  ): ?int {
    $this->output = $output;
    $fs = new Filesystem();
    $config = Yaml::parseFile('.logasaurus.yml');
    $config['filesPath'] = $config['filesPath'] ?? 'changelogs/unreleased/';
    if (!$fs->exists($config['filesPath'])) {
      $this->output->writeln(sprintf('The filesPath %s does not exist.',
        $config['filesPath']));
      return 0;
    }
    $config['finalize'] = $config['finalize'] ?? FALSE;

    $sourceFiles = $this->getFiles($config['filesPath']);
    if (!empty($sourceFiles)) {
      $list = $this->createList($sourceFiles);
      if ($this->updateChangelog($config['changelogFile'],
        $input->getArgument('version'), $input->getArgument('date'), $list)) {
        $this->cleanupFiles($config['filesPath'], $sourceFiles,
          $config['changelogFile'], $config['finalize']);
      }
      else {
        $this->output->writeln(sprintf('There were no files found at the filesPath %s so nothing could be updated.',
          $config['filesPath']));
      }
      return 1;
    }
    return 0;
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
   * @return bool
   */
  private function updateChangelog(
    string $path,
    string $version,
    string $date,
    string $list
  ): bool {
    $newContents = "---\n## >> $version ($date)\n$list";
    $changelog = file_get_contents($path);
    $changelog = str_replace('---', $newContents, $changelog);
    $fs = new Filesystem();
    try {
      $fs->dumpFile($path, $changelog);
    } catch (Exception $e) {
      $this->output->writeln(sprintf('Error writing output to changelog %s',
        $e));
      return FALSE;
    }
    return TRUE;
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
