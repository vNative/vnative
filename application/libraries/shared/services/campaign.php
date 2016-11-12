<?php
namespace Shared\Services;
use Shared\Utils as Utils;

class Campaign {
	public static function minutely() {
		// process all the campaign which needs processing
		$today = date('Y-m-d');
		$ads = \Ad::all(['meta.processing' => true, 'created' => Db::dateQuery($today, $today)]);
		foreach ($ads as $a) {
			$meta = $a->meta;
			switch ($a->type) {
				case 'video':
					// download the video and save it
					$arr = []; $found = false;

					foreach (['240p', '360p'] as $q) {
						$vid = Utils::media($a->meta['videoUrl'], 'download', ['type' => 'video', 'quality' => $q]);

						if (strlen($vid) >= 4) {
							$found = true;
							$arr[] = [
								'file' => $vid,
								'quality' => $q
							];
						}
					}
					if ($found) unset($meta['processing']);
					$meta['videos'] = $arr;
					
					$a->meta = $meta; $a->save();
					break;
			}
		}
	}

	public static function displayData($ads = []) {
	    $result = []; $ads = (array) $ads;
	    foreach ($ads as $a) {
	        $a = (object) $a;
	        $find = \Ad::first(['_id' => $a->_id], ['title', 'image', 'url']);
	        $result[] = [
	            '_id' => $a->_id,
	            'clicks' => $a->clicks,
	            'title' => $find->title,
	            'image' => $find->image,
	            'url' => $find->url
	        ];
	    }
	    return $result;
	}
}