<?php

namespace Drupal\deims_dar_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

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
		// Render each element as markup in case of multi-values.

		foreach ($items as $delta => $item) {
		  
			$api_url = "https://dar.elter-ri.eu/api/search/?q=&sort=newest&page=1&size=10&metadata_siteReferences_siteID=" . $item->value;
			$landing_page_url = "https://dar.elter-ri.eu/search/?q=&l=list&p=1&s=10&sort=newest&f=metadata_siteReferences_siteID:" . $item->value;
			
			try {
				$response = \Drupal::httpClient()->get($api_url, array('headers' => array('Accept' => 'application/json')));
				$data = (string) $response->getBody();
				if (empty($data)) {
					// potentially add a more meaningful error message here in case data can't be fetched from DAR
					\Drupal::logger('deims_dar_formatter')->notice(serialize(array()));
				}
				else {
					$data = json_decode($data, TRUE);
                }
			}
			catch (GuzzleException $e) {
				if ($e->hasResponse()) {
                    $response = $e->getResponse()->getBody()->getContents();
					\Drupal::logger('deims_dar_formatter')->notice(serialize($response));
                }
				return array();
			}
			
			if (intval($data["hits"]["total"])>0) {
				
				$maxIterations = 5;
				$count = 0;
				$dataset_list = "<ul>";

				foreach ($data["hits"]["hits"] as $key => $value) {
					if ($count >= $maxIterations) {
						break;
					}
					$count++;
				
					$url = htmlspecialchars($value["links"]["self_html"] ?? '#', ENT_QUOTES, 'UTF-8');
					$title = htmlspecialchars($value["metadata"]["titles"][0]["titleText"] ?? 'Untitled', ENT_QUOTES, 'UTF-8');
					$dataset_list .= "<li><a href='$url'>$title</a></li>";
					
				}
				
				$dataset_list .= "</ul>";
				
				if ($data["hits"]["total"] == 1) {
					$output = "There is one dataset for this site available on the eLTER Digital Asset Register (DAR).";
				}
				else {
					$output = "There is a total of " . $data["hits"]["total"] . " datasets for this site available on the eLTER Digital Asset Register (DAR).";
				}
				
				if ($count>0) {
					if ($count>1) {
						$output .= " The latest ones include: ";
					} 
					$output .= $dataset_list;
				}
				$output .= "To see all of these datasets <a href='$landing_page_url'>visit the eLTER DAR.</a>";

			}
			else {
				// need to return empty array for Drupal to realise the field is empty without throwing an error
				return array();
			}
			
			$elements[$delta] = [
				'#markup' => $output,
			];

		}

		return $elements;

	}
	
}
