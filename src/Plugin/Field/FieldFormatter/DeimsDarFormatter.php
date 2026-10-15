<?php

namespace Drupal\deims_dar_formatter\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * Plugin implementation of the 'DeimsDarFormatter' formatter.
 *
 * @FieldFormatter(
 *   id = "deims_dar_formatter",
 *   label = @Translation("DEIMS DAR Formatter"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "string",
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
class DeimsDarFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Formats a deims.id field of Drupal');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $site_id = urlencode((string) $item->value);
      $api_url = "https://dar.elter-ri.eu/api/search/?q=&sort=newest&page=1&size=10&metadata_siteReferences_siteID=" . $site_id;
      $landing_page_url = "https://dar.elter-ri.eu/search/?q=&l=list&p=1&s=10&sort=newest&f=metadata_siteReferences_siteID:" . $site_id;

      $data = $this->fetchData($api_url);

      if ($data === NULL) {
        // Service unavailable. Short max-age so the page recovers by itself
        // once the DAR is back, instead of caching the failure indefinitely.
        $elements[$delta] = [
          '#markup' => $this->t('Dataset information from the eLTER Digital Asset Register is temporarily unavailable.'),
          '#cache' => ['max-age' => 300],
        ];
        continue;
      }

      $total = (int) ($data['hits']['total'] ?? 0);
      if ($total === 0) {
        // Nothing to show; an empty result lets Drupal treat the field as empty.
        continue;
      }

      $max_iterations = 5;
      $count = 0;
      $dataset_list = '<ul>';

      foreach ($data['hits']['hits'] ?? [] as $value) {
        if ($count >= $max_iterations) {
          break;
        }
        $count++;

        $url = Html::escape($value['links']['self_html'] ?? '#');
        $title = Html::escape($value['metadata']['titles'][0]['titleText'] ?? 'Untitled');
        $dataset_list .= "<li><a href='$url'>$title</a></li>";
      }

      $dataset_list .= '</ul>';

      if ($total === 1) {
        $output = 'There is one dataset for this site available in the eLTER Digital Asset Register (DAR):';
      }
      else {
        $output = 'There is a total of ' . $total . ' datasets for this site available in the eLTER Digital Asset Register (DAR).';
      }

      if ($count > 0) {
        if ($total > 5) {
          $output .= ' The latest ones include:';
        }
        $output .= $dataset_list;
      }
      $output .= " To see more <a href='" . Html::escape($landing_page_url) . "'>visit the eLTER DAR.</a>";

      $elements[$delta] = [
        '#markup' => $output,
        '#cache' => ['max-age' => 3600],
      ];
    }

    return $elements;
  }

  /**
   * Fetches and decodes the JSON response, or NULL on any failure.
   */
  protected function fetchData(string $api_url): ?array {
    $logger = \Drupal::logger('deims_dar_formatter');

    try {
      $response = \Drupal::httpClient()->get($api_url, [
        'headers' => ['Accept' => 'application/json'],
        // Fail fast so an offline service cannot stall page rendering.
        'connect_timeout' => 3,
        'timeout' => 5,
      ]);
    }
    catch (ConnectException $e) {
      // DNS failure, refused connection or timeout: there is no response.
      $logger->warning('DAR service unreachable: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
    catch (RequestException $e) {
      // The service answered with an HTTP error (4xx/5xx).
      $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'n/a';
      $logger->warning('DAR service returned HTTP @status for @url', [
        '@status' => $status,
        '@url' => $api_url,
      ]);
      return NULL;
    }
    catch (GuzzleException $e) {
      $logger->error('DAR request failed: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }

    $data = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($data)) {
      $logger->notice('DAR returned an empty or invalid response for @url', ['@url' => $api_url]);
      return NULL;
    }

    return $data;
  }

}
