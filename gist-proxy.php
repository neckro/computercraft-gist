<?php
// Gist proxy script to be used with ComputerCraft gist.lua
// by necKro 2013, WTFPL.  See README.

// if set, requests will be logged to this file
$log_file = null;
header("Content-Type: text/plain");
ini_set("display_errors", 0);

file_log($_SERVER['REQUEST_METHOD'] . ' /' . $_SERVER['QUERY_STRING']);

try {
	if (!empty($_REQUEST['filename']) && !empty($_REQUEST['data'])) {
		// upload
		$filename = removeFileExtension($_REQUEST['filename'], 'lua');
		$filename = "{$filename}.lua";
		file_log("Uploading file {$filename}");
		$g = new Gist();
		$g->post($filename, $_REQUEST['data']);
		echo $g->id . "\n";
		if ($g->fork_id) echo $g->fork_id . "\n";
		file_log("Posted Gist {$g->id}" .
			($g->fork_id ? " forked from {$g->fork_id}" : ""));
		exit;
	} else {
		// download
		$g = new Gist(@$_GET['gist']);
		$filename = removeFileExtension($g->get_filename(), 'lua');
		$out  = "$filename\n";
		$out .= "-- " . $g->get_url() . "\n";
		$out .= filter_gist($g->retrieve());
		echo $out;
		exit;
	}
} catch (Exception $e) {
	// failure
	http_response_code(400);
	file_log("There was an error: " . $e->getMessage(), true);
	exit;
}

class Gist {
	public $id = null;
	public $commit = null;
	public $fork_id = null;
	protected $info = null;
	protected $last_header = null;
	protected $last_body = null;
	public $curl = array();
	public $debug = false;

	public function __construct($gist_id = '') {
		$gist_id = explode('/', $gist_id);
		$this->id = @array_shift($gist_id);
		$this->commit = @array_shift($gist_id);
	}
	public function post($filename, $data) {
		$description = '';
		@list($line, $out) = @explode("\n", $data, 2);
		$parsed = $this->parse_url($line);
		if ($parsed !== false) {
			$this->fork_id = $parsed['id'];
			$fork = "Forked from " . $this->get_url($parsed['id'], $parsed['commit']);
			$description = $fork;
			$data = "-- {$fork}\n";
			$data .= $out;
		}
		$request = json_encode(array(
			'public' => true,
			'files' => array(
				$filename => array(
					'content' => $data
				)
			)
		));
		$response = $this->apiCall('https://api.github.com/gists', array(
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $request,
		));
		$this->info = json_decode($response->body, true);
		$this->id = $this->info['id'];
		$this->commit = $this->get_commit();
		return $this->get_url();
	}
	public function retrieve($index = 0) {
		$this->info = $this->get_info();
		$url = $this->get_file_info($index)['raw_url'];
		$curl = $this->apiCall($url);
		return $curl->body;
	}
	public function get_filename($index = 0) {
		return $this->get_file_info($index)['filename'];
	}
	public function get_commit($index = 0) {
		// index 0 = HEAD
		$commits = $this->get_info('history');
		return $commits[array_keys($commits)[$index]]['version'];
	}
	public function get_url($id = null, $commit = null) {
		if ($id === null) {
			$id = $this->id;
			$commit = $this->get_commit();
		}
		$out = "https://gist.github.com/{$id}";
		if (!empty($commit)) $out .= '/' . substr($commit, 0, 7);
		return $out;
	}
	public function parse_url($url) {
		$pattern = '/(-- )?https:\/\/gist.github.com\/([0-9]+)(\/)?([0-9a-f]+)?/';
		if (!preg_match($pattern, $url, $matches)) return false;
		return array(
			'id' => @$matches[2],
			'commit' => @$matches[4],
		);
	}
	protected function get_file_info($index) {
		$files = $this->get_info('files');
		return $files[array_keys($files)[$index]];
	}
	protected function get_info($key = null) {
		if ($this->info === null) {
			$url = "https://api.github.com/gists/{$this->id}";
			if (!empty($commit)) $url .= "/{$this->commit}";
			$curl = $this->apiCall($url);
			$this->info = json_decode($curl->body, true);
		}
		return ($key === null) ? $this->info : $this->info[$key];
	}
	protected function apiCall($url, $headers = array()) {
		$curl = new GistCurl($url, $headers);
		$this->curl[] = $curl;
		$this->last_header = $curl->headers;
		$this->last_body = $curl->body;
		$response_code = $curl->headers['Response Code'];
		if ($this->debug) var_dump($curl);

		if ($response_code == 302) {
			// redirect!
			return $this->apiCall($curl->headers['Location'], $headers);
		}
		if (!in_array($response_code, array(100, 200, 201))) {
			throw new Exception('Bad response code: '
				. $curl->headers['Response Code'] . ' '
				. $curl->headers['Response Status']);
		}
		$rate_limit = @$curl->headers['X-Ratelimit-Limit'];
		$limit_remaining = @$curl->headers['X-Ratelimit-Remaining'];
		if (is_numeric($rate_limit) && is_numeric($limit_remaining)) {
			file_log("API limit: {$rate_limit}; remaining: {$limit_remaining}");
			if ($limit_remaining < 1) {
				throw new Exception("No API calls remaining");
			}
		}
		return $curl;
	}
}

// really, php?  I have to write my own class for this?
class GistCurl {
	protected $handle;
	public $url = null;
	public $headers = null;
	public $body = null;
	public $error = false;

	public function __construct($url, $options = array()) {
		$this->url = $url;
		$this->handle = curl_init($this->url);
		curl_setopt_array($this->handle, $options);
		curl_setopt_array($this->handle, array(
			CURLOPT_HEADER => 1,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_VERBOSE => 1,
		));
		$response = curl_exec($this->handle);
		$header_size = curl_getinfo($this->handle, CURLINFO_HEADER_SIZE);
		$this->headers = http_parse_headers(substr($response, 0, $header_size));
		ksort($this->headers);
		$this->body = substr($response, $header_size);
		$this->error = curl_error($this->handle);
		curl_close($this->handle);
	}
}

function removeFileExtension($file, $ext) {
	$ext = ".{$ext}";
	if (strtolower(substr($file, -strlen($ext))) === $ext) {
		return substr($file, 0, strlen($file)-strlen($ext));
	}
	return $file;
}

function filter_gist($body) {
	@list($line, $out) = @explode("\n", $body, 2);
	if (substr($line, 0, 26) === '-- https://gist.github.com') return $out;
	return $body;
}

function file_log($message, $passthru = false) {
	static $handle = null;
	global $log_file;
	if ($passthru) echo $message . "\n";
	if ($handle === null) {
		if (empty($log_file)) return false;
		$handle = @fopen($log_file, 'ab');
	}
	if (!$handle) return false;
	$script_name = pathinfo($_SERVER['SCRIPT_FILENAME'], PATHINFO_FILENAME);
	$message = date('ymd H:i:s ') . $_SERVER['REMOTE_ADDR'] . " {$script_name} {$message}\n";
	return fwrite($handle, $message);
}
