<?php

namespace GetId3\Write;

use GetId3\Lib\Helper;
use GetId3\GetId3;

/////////////////////////////////////////////////////////////////
/// GetId3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// write.id3v1.php                                             //
// module for writing ID3v1 tags                               //
// dependencies: module.tag.id3v1.php                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

/**
 * module for writing ID3v1 tags
 *
 * @author James Heinrich <info@getid3.org>
 * @link http://getid3.sourceforge.net
 * @link http://www.getid3.org
 * @uses GetId3\Module\Tag\Id3v1
 */
class Id3v1
{
	public $filename;
	public $filesize;
	public $tag_data;
    /**
     *
     * @var array
     */
	public $warnings = array(); // any non-critical errors will be stored here
    /**
     *
     * @var array
     */
	public $errors   = array(); // any critical errors will be stored here

    /**
     *
     * @return boolean
     */
	public function __construct() {
		return true;
	}

    /**
     *
     * @return boolean
     */
	public function WriteID3v1() {
		// File MUST be writeable - CHMOD(646) at least
		if (!empty($this->filename) && is_readable($this->filename) && is_writable($this->filename) && is_file($this->filename)) {
			$this->setRealFileSize();
			if (($this->filesize <= 0) || !Helper::intValueSupported($this->filesize)) {
				$this->errors[] = 'Unable to WriteID3v1('.$this->filename.') because filesize ('.$this->filesize.') is larger than '.round(PHP_INT_MAX / 1073741824).'GB';
				return false;
			}
			if ($fp_source = fopen($this->filename, 'r+b')) {
				fseek($fp_source, -128, SEEK_END);
				if (fread($fp_source, 3) == 'TAG') {
					fseek($fp_source, -128, SEEK_END); // overwrite existing ID3v1 tag
				} else {
					fseek($fp_source, 0, SEEK_END);    // append new ID3v1 tag
				}
				$this->tag_data['track'] = (isset($this->tag_data['track']) ? $this->tag_data['track'] : (isset($this->tag_data['track_number']) ? $this->tag_data['track_number'] : (isset($this->tag_data['tracknumber']) ? $this->tag_data['tracknumber'] : '')));

				$new_id3v1_tag_data = GetId3\Module\Tag\Id3v1::GenerateID3v1Tag(
														(isset($this->tag_data['title']  ) ? $this->tag_data['title']   : ''),
														(isset($this->tag_data['artist'] ) ? $this->tag_data['artist']  : ''),
														(isset($this->tag_data['album']  ) ? $this->tag_data['album']   : ''),
														(isset($this->tag_data['year']   ) ? $this->tag_data['year']    : ''),
														(isset($this->tag_data['genreid']) ? $this->tag_data['genreid'] : ''),
														(isset($this->tag_data['comment']) ? $this->tag_data['comment'] : ''),
														(isset($this->tag_data['track']  ) ? $this->tag_data['track']   : ''));
				fwrite($fp_source, $new_id3v1_tag_data, 128);
				fclose($fp_source);
				return true;

			} else {
				$this->errors[] = 'Could not fopen('.$this->filename.', "r+b")';
				return false;
			}
		}
		$this->errors[] = 'File is not writeable: '.$this->filename;
		return false;
	}

    /**
     *
     * @return boolean
     */
	public function FixID3v1Padding() {
		// ID3v1 data is supposed to be padded with NULL characters, but some taggers incorrectly use spaces
		// This function rewrites the ID3v1 tag with correct padding

		// Initialize GetId3 engine
		$getID3 = new GetId3();
		$getID3->option_tag_id3v2  = false;
		$getID3->option_tag_apetag = false;
		$getID3->option_tags_html  = false;
		$getID3->option_extra_info = false;
		$getID3->option_tag_id3v1  = true;
		$ThisFileInfo = $getID3->analyze($this->filename);
		if (isset($ThisFileInfo['tags']['id3v1'])) {
			foreach ($ThisFileInfo['tags']['id3v1'] as $key => $value) {
				$id3v1data[$key] = implode(',', $value);
			}
			$this->tag_data = $id3v1data;
			return $this->WriteID3v1();
		}
		return false;
	}

	public function RemoveID3v1() {
		// File MUST be writeable - CHMOD(646) at least
		if (!empty($this->filename) && is_readable($this->filename) && is_writable($this->filename) && is_file($this->filename)) {
			$this->setRealFileSize();
			if (($this->filesize <= 0) || !Helper::intValueSupported($this->filesize)) {
				$this->errors[] = 'Unable to RemoveID3v1('.$this->filename.') because filesize ('.$this->filesize.') is larger than '.round(PHP_INT_MAX / 1073741824).'GB';
				return false;
			}
			if ($fp_source = fopen($this->filename, 'r+b')) {

				fseek($fp_source, -128, SEEK_END);
				if (fread($fp_source, 3) == 'TAG') {
					ftruncate($fp_source, $this->filesize - 128);
				} else {
					// no ID3v1 tag to begin with - do nothing
				}
				fclose($fp_source);
				return true;

			} else {
				$this->errors[] = 'Could not fopen('.$this->filename.', "r+b")';
			}
		} else {
			$this->errors[] = $this->filename.' is not writeable';
		}
		return false;
	}

    /**
     *
     * @return boolean
     */
	public function setRealFileSize() {
		if (PHP_INT_MAX > 2147483647) {
			$this->filesize = filesize($this->filename);
			return true;
		}
		// 32-bit PHP will not return correct values for filesize() if file is >=2GB
		// but GetId3->analyze() has workarounds to get actual filesize
		$getID3 = new GetId3();
		$getID3->option_tag_id3v1  = false;
		$getID3->option_tag_id3v2  = false;
		$getID3->option_tag_apetag = false;
		$getID3->option_tags_html  = false;
		$getID3->option_extra_info = false;
		$ThisFileInfo = $getID3->analyze($this->filename);
		$this->filesize = $ThisFileInfo['filesize'];
		return true;
	}

}
