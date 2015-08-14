<?php
namespace Media;
use mm\Mime\Type;

class Media {
	private $track;
	private $extension;
	private $mime;
	private $driver;
	private $temp; //temp file to unset on convert
	private $tempDir;
	private $drivers = array();
	public $image;

	public function __construct($filename) {
		$this->loadTrack($filename);
		$tempDir = sys_get_temp_dir();
		$this->tempDir = !empty($tempDir) ? $tempDir : "/tmp";
	}
	/**
	 * Cast the track to a string
	 *
	 * @return type
	 */
	public function __toString() {
		return $this->track;
	}

	/**
	 * Load a track for processing
	 * @param  string $track The full path to the track
	 */
	public function loadTrack($track) {
		if(empty($track)) {
			throw new \Exception("A track must be supplied");
		}
		if(!file_exists($track)) {
			throw new \Exception("Track [$track] not found");
		}
		if(!is_readable($track)) {
			throw new \Exception("Track [$track] not readable");
		}
		$this->track = $track;

		Type::config('magic', array(
			'adapter' => 'Freedesktop',
			'file' => dirname(__DIR__).'/resources/magic.db'
		));
		Type::config('glob', array(
			'adapter' => 'Freedesktop',
			'file' => dirname(__DIR__).'/resources/glob.db'
		));
		$this->extension = Type::guessExtension($this->track);
		if(empty($this->extension) || $this->extension == "bin") {
			$parts = pathinfo($this->track);
			$this->extension = $parts['extension'];
		}
		$this->mime = Type::guessType($this->track);
	}

	private function setupDrivers() {

	}

	/**
	 * Get Stats of an audio file
	 * @return array The stats as an array
	 */
	public function getStats() {

	}

	/**
	 * Get the file comments (annotations)
	 *
	 * @param boolean $parse   Parse and return a value object
	 * @return string|object
	 */
	public function getAnnotations($parse=false) {

	}

	private function getDrivers() {
		if(!empty($this->drivers)) {
			return $this->drivers;
		}
		foreach(glob(__DIR__."/Driver/Drivers/*.php") as $file) {
			$this->drivers[] = basename($file,".php");
		}
		return $this->drivers;
	}

	public static function getSupportedFormats() {
		$formats = array(
			"out" => array(),
			"in" => array()
		);
		if(Driver\Drivers\AsteriskShell::installed()) {
			$formats = Driver\Drivers\AsteriskShell::supportedCodecs($formats);
		}
		if(Driver\Drivers\SoxShell::installed()) {
			$formats = Driver\Drivers\SoxShell::supportedCodecs($formats);
		}
		if(Driver\Drivers\Mpg123Shell::installed()) {
			$formats = Driver\Drivers\Mpg123Shell::supportedCodecs($formats);
		}
		if(Driver\Drivers\FfmpegShell::installed()) {
			$formats = Driver\Drivers\FfmpegShell::supportedCodecs($formats);
		}
		if(Driver\Drivers\LameShell::installed()) {
			$formats = Driver\Drivers\LameShell::supportedCodecs($formats);
		}
		return $formats;
	}

	/**
	 * Convert the track using the best possible means
	 * @param  string $filename The new filename
	 * @return object           New Media Object
	 */
	public function convert($newFilename) {
		$extension = Type::guessExtension($newFilename);
		$parts = pathinfo($newFilename);
		if(empty($extension) || $extension == "bin") {
			$extension = $parts['extension'];
		}
		$mime = Type::guessType($newFilename);
		//generate intermediary file
		foreach($this->getDrivers() as $driver) {
			$class = "Media\\Driver\\Drivers\\".$driver;
			if($class::installed() && $class::isCodecSupported($this->extension,"in")) {
				$driver = new $class($this->track,$this->extension,$this->mime);
				$ts = time().rand(0,1000);
				$driver->convert($this->tempDir."/temp.".$ts.".wav","wav","audio/x-wav");
				$this->track = $this->temp = $this->tempDir."/temp.".$ts.".wav";
				$this->extension = "wav";
				$this->mime = "audio/x-wav";
				break;
			}
		}
		//generate wav form png
		if(isset($this->image)) {
			$waveform = new \Jasny\Audio\Waveform($this->temp, array("width" => 700));
			$waveform->output("png",$this->image);
		}

		//generate final file
		foreach($this->getDrivers() as $driver) {
			$class = "Media\\Driver\\Drivers\\".$driver;
			if($class::installed() && $class::isCodecSupported($extension,"out")) {
				$driver = new $class($this->track,$this->extension,$this->mime);
				$driver->convert($newFilename,$extension,$mime);
				break;
			}
		}
		if(!empty($this->temp) && file_exists($this->temp)) {
			unlink($this->temp);
		}

		return file_exists($newFilename);
	}

	/**
	 * Combine two audio files
	 *
	 * @param string       $method  'concatenate', 'merge', 'mix', 'mix-power', 'multiply', 'sequence'
	 * @param string|Track $in      File to mix with
	 * @param string       $out     New filename
	 * @return Track
	 */
	public function combine($method, $in, $out) {
			if ($in instanceof self) {
				$in = $in->filename;
			}
			return new static($out);
	}
}
