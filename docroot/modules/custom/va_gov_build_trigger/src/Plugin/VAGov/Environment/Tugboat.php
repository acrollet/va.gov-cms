<?php

namespace Drupal\va_gov_build_trigger\Plugin\VAGov\Environment;

use Drupal\advancedqueue\Entity\QueueInterface;
use Drupal\advancedqueue\Job;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\va_gov_build_trigger\Environment\EnvironmentPluginBase;
use Drupal\va_gov_build_trigger\Form\TugboatBuildTriggerForm;
use Drupal\va_gov_build_trigger\Plugin\AdvancedQueue\JobType\WebBuildJobType;
use Drupal\va_gov_build_trigger\WebBuildStatusInterface;
use Drupal\va_gov_content_export\ExportCommand\CommandRunner;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tugboat Plugin for Environment.
 *
 * @Environment(
 *   id = "tugboat",
 *   label = @Translation("tugboat")
 * )
 */
class Tugboat extends EnvironmentPluginBase {
  use CommandRunner;

  /**
   * The queue storage manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $queueLoader;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerInterface $logger,
    WebBuildStatusInterface $webBuildStatus,
    EntityStorageInterface $queueLoader
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $webBuildStatus);
    $this->queueLoader = $queueLoader;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('va_gov_build_trigger'),
      $container->get('va_gov.build_trigger.web_build_status'),
      $container->get('entity_type.manager')->getStorage('advancedqueue_queue')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function triggerFrontendBuild($web_pr = NULL): void {
    $commands = [];

    if (is_numeric($web_pr)) {
      $build_date = time();
      $web_pr_branch = "build-{$web_pr}-{$build_date}";
      $commands[] = "cd /var/lib/tugboat/web && git fetch origin pull/{$web_pr}/head:{$web_pr_branch} && git checkout {$web_pr_branch}";
    }

    $commands[] = 'cd /var/lib/tugboat && COMPOSER_HOME=/var/lib/tugboat /usr/local/bin/composer --no-cache va:web:build';
    $payload = ['commands' => $commands];

    $job = Job::create(WebBuildJobType::QUEUE_ID, $payload);

    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = $this->queueLoader->load('command_runner');
    $queue->enqueueJob($job);

    $this->messenger()->addStatus('A request to rebuild the front end has been submitted.');
  }

  /**
   * {@inheritDoc}
   */
  public function shouldTriggerFrontendBuild(): bool {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function getBuildTriggerFormClass() : string {
    return TugboatBuildTriggerForm::class;
  }

}
