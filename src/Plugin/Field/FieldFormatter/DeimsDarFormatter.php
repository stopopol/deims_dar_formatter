<?php

namespace Drupal\deims_dar_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\HttpFoundation\JsonResponse;


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
		  
			$url = "https://catalogue.lter-europe.net/elter/documents?facet=elterDeimsUri%7Chttps://deims.org/" . $item->value;
			
			try {
				$response = \Drupal::httpClient()->get($url."&format=json", array('headers' => array('Accept' => 'text/plain')));
				$data = (string) $response->getBody();
				if (empty($data)) {
				  return FALSE;
				}
			}
			catch (RequestException $e) {
				return array();
			}
			
			if ($data) {
				$data = json_decode($data, TRUE);
				if (intval($data["numFound"])>0) {
					$output = "There is a total of " . $data["numFound"] . " datasets available on the eLTER Digital Asset Register (DAR). <a href='" . $url . "'>Click here to get an overview of these datasets.</a>";
				}
				else {
					// need to return empty array for Drupal to realise the field is empty without throwing an error
					return array();
				}
			}
			
			$elements[$delta] = [
				'#markup' => $output,
			];

		}

		return $elements;

	}
	
}