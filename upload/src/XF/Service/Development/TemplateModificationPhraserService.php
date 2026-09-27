<?php

namespace XF\Service\Development;

use XF\Entity\Phrase;
use XF\Entity\TemplateModification;
use XF\Service\AbstractService;

class TemplateModificationPhraserService extends AbstractService
{
	use TemplateContentPhraserTrait;

	public function isTemplateModificationPhrasable(TemplateModification $templateModification): bool
	{
		if (preg_match('/\.(less|css)$/i', $templateModification->template))
		{
			return false;
		}

		return $this->templateHasPhrasableText($templateModification->replace);
	}

	public function applyTemplateChanges(TemplateModification $templateModification, string $newTemplateValue, array $replacements): void
	{
		$this->db()->beginTransaction();

		try
		{
			$addOnId = $templateModification->addon_id;

			$templateModification->replace = $newTemplateValue;
			$templateModification->save(true, false);

			foreach ($replacements AS $replacement)
			{
				if ($replacement['existingName'] !== $replacement['name'])
				{
					$existing = $this->em()->findOne(Phrase::class, [
						'title' => $replacement['name'],
						'addon_id' => $addOnId,
						'language_id' => 0,
					]);
					if ($existing)
					{
						continue;
					}

					$phrase = $this->em()->create(Phrase::class);
					$phrase->title = $replacement['name'];
					$phrase->phrase_text = $replacement['text'];
					$phrase->language_id = 0;
					$phrase->addon_id = $addOnId;
					$phrase->save(true, false);
				}
			}

			$this->db()->commit();
		}
		catch (\Throwable $e)
		{
			$this->db()->rollback();
			throw $e;
		}
	}
}
