<?php

namespace Drupal\va_gov_build_trigger\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\va_gov_build_trigger\Plugin\AdvancedQueue\JobType\WebBuildJobType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'ContentReleaseStatusBlock' block.
 *
 * @Block(
 *  id = "content_release_status_block",
 *  admin_label = @Translation("Content release status"),
 * )
 */
class ContentReleaseStatusBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * EnvironmentDiscovery Service.
   *
   * @var \Drupal\va_gov_build_trigger\Environment\EnvironmentDiscovery
   */
  protected $environmentDiscovery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->database = $container->get('database');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->environmentDiscovery = $container->get('va_gov.build_trigger.environment_discovery');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $jobs = $this->getCommandRunnerJobs();

    if (count($jobs) === 0) {
      return $build;
    }

    $table = [
      '#type' => 'table',
      '#header' => [
        '',
        $this->t('Status'),
        $this->t('Created'),
        $this->t('Processed'),
        $this->t('Logs'),
      ],
    ];

    foreach ($jobs as $job) {
      $table['#rows'][] = $this->buildTableRow($job);
    }

    $build['#attached']['library'][] = 'va_gov_build_trigger/content_release_status_block';
    $build['#attached']['drupalSettings']['contentReleaseStatusBlock'] = [
      'blockRefreshPath' => Url::fromRoute(
        'va_gov_build_trigger.content_release_status_block_controller_get_block'
      )->toString(),
    ];

    $build['content_release_status_block'] = $table;

    return $build;
  }

  /**
   * Build a queue status table row array.
   *
   * @param object $job
   *   Advancedqueue job database row.
   *
   * @return array
   *   Drupal table row array.
   */
  private function buildTableRow(\stdClass $job) : array {
    $row = [];

    $row[] = $this->getStatusIcon($job->state);
    $row[] = $this->getHumanReadableStatus($job->state);
    $row[] = $job->available ? $this->dateFormatter->format($job->available, 'standard') : '';
    $row[] = $job->processed ? $this->dateFormatter->format($job->processed, 'standard') : '';

    // The log page will show an error if there are no log messages,
    // so only show it once the job is being processed.
    $row[] = $job->state !== 'queued' ? $this->getLogLink() : '';

    return $row;
  }

  /**
   * Add log links to table.
   *
   * @return array
   *   Link to dblog, filtered to web build messages.
   */
  private function getLogLink() : array {
    $log_url = Url::fromRoute(
      "dblog.overview",
      [],
      [
        'attributes' => [
          'target' => '_blank',
        ],
        'query' => [
          'type[]' => WebBuildJobType::QUEUE_ID,
        ],
      ]
    );

    return [
      'data' => [
        '#title' => $this->t('View logs'),
        '#type' => 'link',
        '#url' => $log_url,
      ],
    ];
  }

  /**
   * Get the status icon for an advancedqueue job state.
   *
   * @param string $state
   *   Advancedqueue job state.
   *
   * @return array
   *   Table cell array with job status icon.
   */
  private function getStatusIcon(string $state) : array {
    $class = '';
    $icon = '';

    switch ($state) {
      case 'queued':
        $icon = '🕐';
        break;

      case 'processing':
        $class = 'status-animated';
        $icon = '🔨';
        break;

      case 'success':
        $icon = '✅';
        break;

      case 'failure':
        $icon = '❌';
        break;

      default:
        $icon = '❓';
        break;
    }

    return [
      'data' => $icon,
      'class' => $class,
    ];
  }

  /**
   * Get the human readable status for an advancedqueue job state.
   *
   * @param string $state
   *   Advancedqueue job state.
   *
   * @return array
   *   Table cell array with human-readable job status.
   */
  private function getHumanReadableStatus(string $state) : array {
    $status = '';
    switch ($state) {
      case 'queued':
        $status = $this->t('Pending');
        break;

      case 'processing':
        $status = $this->t('In Progress');
        break;

      case 'success':
        $status = $this->t('Success');
        break;

      case 'failure':
        $status = $this->t('Error');
        break;

      default:
        $status = $this->t('Unknown');
        break;
    }

    return [
      'data' => $status,
      'class' => "status-{$state} ajax-progress-throbber",
    ];
  }

  /**
   * Get content release build jobs.
   *
   * @return array
   *   Array of job database row objects.
   */
  private function getCommandRunnerJobs() : array {
    return $this->database->select('advancedqueue', 'aq')
      ->condition('aq.queue_id', 'command_runner')
      ->fields('aq')
      ->range(0, 10)
      ->orderBy('available', 'DESC')
      ->execute()
      ->fetchAll();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
