<?php

namespace XF\Data;

use XF\Util\Str;

class Exif
{
	/**
	 * @param array<string, array{id: int, value: mixed}> $tags
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getExifDataExtended(array $tags): array
	{
		$exif = [];

		foreach ($tags AS $name => $data)
		{
			$group = $this->getExifTagGroupExtended($name, $data['id']);
			$name = $this->getExifTagNameExtended($group, $data['id']);
			if ($name === null)
			{
				continue;
			}

			if (
				$group === 'MAKERNOTE'
				&& strpos($name, 'UndefinedTag:') === 0
				&& !Str::check_encoding($data['value'])
			)
			{
				$data['value'] = base64_encode($data['value']);
			}

			$exif[$group][$name] = $data['value'];
		}

		$exif['FILE']['SectionsFound'] = $exif
			? 'ANY_TAG, ' . implode(', ', array_keys($exif))
			: '';

		return $exif;
	}

	/**
	 * @param array<int, string|int> $tags
	 *
	 * @return array<string, array<string, string|int>>
	 */
	public function getExifData(array $tags): array
	{
		$exif = [];

		foreach ($tags AS $tagId => $tagValue)
		{
			$tagName = $this->getExifTagName($tagId);
			if ($tagName === null)
			{
				continue;
			}

			$tagGroup = $this->getExifTagGroup($tagId);
			$exif[$tagGroup][$tagName] = $tagValue;
		}

		$exif['FILE']['SectionsFound'] = $exif
			? 'ANY_TAG, ' . implode(', ', array_keys($exif))
			: '';

		return $exif;
	}

	public function getExifTagNameExtended(string $tagGroup, int $tagId): ?string
	{
		if ($tagGroup === 'GPS')
		{
			switch ($tagId)
			{
				case 1: return 'GPSLatitudeRef';
				case 2: return 'GPSLatitude';
				case 3: return 'GPSLongitudeRef';
				case 4: return 'GPSLongitude';
				case 5: return 'GPSAltitudeRef';
				case 6: return 'GPSAltitude';
				case 7: return 'GPSTimeStamp';
				case 8: return 'GPSSatellites';
				case 16: return 'GPSImgDirectionRef';
				case 18: return 'GPSMapDatum';
				case 29: return 'GPSDateStamp';
				default: return null;
			}
		}

		if ($tagGroup === 'INTEROP')
		{
			switch ($tagId)
			{
				case 1: return 'InterOperabilityIndex';
				case 2: return 'InterOperabilityVersion';
				default: return null;
			}
		}

		if ($tagGroup === 'EXIF')
		{
			switch ($tagId)
			{
				case 42035: return 'LensMake';
				case 42036: return 'LensModel';
			}
		}

		return $this->getExifTagName($tagId);
	}

	public function getExifTagName(int $tagId): ?string
	{
		if (!function_exists('exif_tagname'))
		{
			throw new \RuntimeException('The exif extension is not installed');
		}

		$tagName = exif_tagname($tagId);
		if ($tagName === false)
		{
			return null;
		}

		return $tagName;
	}

	public function getExifTagGroupExtended(string $tagName, int $tagId): string
	{
		if (
			strpos($tagName, 'GPS') === 0
			&& $tagName !== 'GPS Info IFD Pointer'
		)
		{
			return 'GPS';
		}

		if (
			strpos($tagName, 'Interoperability') === 0
			&& $tagName !== 'Interoperability IFD Pointer'
		)
		{
			return 'INTEROP';
		}

		return $this->getExifTagGroup($tagId);
	}

	public function getExifTagGroup(int $tagId): string
	{
		if ($tagId === 0x8769 || $tagId === 0x8825)
		{
			return 'IFD0';
		}

		if ($tagId >= 0x0100 && $tagId <= 0x0FFF)
		{
			return 'IFD0';
		}

		// grouping is not comprehensive
		return 'EXIF';
	}
}
