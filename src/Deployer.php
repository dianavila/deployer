<?php namespace Grovers\Deployer;

class Deployer {

	// This will hold the URL to the Jenkins server we should call
	protected $jenkins = '';
	protected $lograw = false;

	/**
	 * The class constructor
	 * @param string $jenkins_url URL to the Jenkins server
	 */
	public function __construct($jenkins_url) {
		$this->jenkins = $jenkins_url;
	}

	/**
	 * Handle an incoming HTTP request
	 * @return void
	 */
	public function process() {
		// only handle POST requests
		if ( ! $this->isPost() ) {
			http_response_code(404);
			return;
		}

		if ($this->lograw) {
			$this->log('REQUEST Received');
			$this->log('  ** Raw Data: '. file_get_contents('php://input'));	
		}
		
		// get the job data
		$jobdata = $this->extract_data();

		// do not do anything unless we have a job defined.
		if ($jobdata['job'] != '') {
			$this->log('Requesting Job: '. $jobdata['job']);
			$this->sendJob($jobdata);
		}
	}

	/**
	 * Indicate if raw data should be logged or not.
	 * @param  boolean $lograw
	 * @return void
	 */
	public function rawdata($lograw) {
		$this->lograw = $lograw;
	}

	/**
	 * indicates if the current request is a POST request
	 * @return boolean
	 */
	private function isPost() {
		// Only respond for POST requests
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			return true;
		}
		return false;
	}



	/**
	 * Creates a log entry
	 * @param  string $msg
	 * @return void
	 */
	private function log($msg) {
		$log = $_SERVER['DOCUMENT_ROOT'] .'/deployer.log';
		$line = '['. date('Y-m-d H:i:s') .'] '. $msg . PHP_EOL;
		$fh = fopen($log, 'a+');
		if ($fh) {
			fwrite($fh, $line);
			fclose($fh);
		}
	}


	/**
	 * Extract the required information.
	 * The bulk of the information comes from the details that Bitbucket reports.
	 * The token is expected to be passed as a URL parameter.
	 *
	 * It is assumed repository names are in the format of "repoName-BranchName".
	 * 	
	 * @return array
	 */
	private function extract_data() {

		$out = [
			'repo' => '',
			'branch' => '',
			'job' => '',
			'token' => '',
		];

		$raw = json_decode(file_get_contents('php://input'));

		// repository
		$out['repo'] = $raw->repository->name;

		// 
		$changes = $raw->push->changes;
		// var_dump($changes);
		// Find the "new" data and extract the branch from there
		foreach($changes as $change) {
			if ($change->new) {
				if ($change->new->type == 'branch') {
					$out['branch'] = $change->new->name;
				}
			}
		}

		// we can only define a job if the branch is known
		if ($out['branch'] != '') {
			$out['job'] = $out['repo'] .'-'. $out['branch'];			
		}

		if (array_key_exists('token', $_GET)) {
			$out['token'] = $_GET['token'];
		}

		return $out;
	}

	/**
	 * Send the job to Jenkins
	 * - Jenkins is expected to have the "Build Authorization Token Root" Plugin installed.
	 * @param  array $jobdata
	 * @return void
	 */
	private function sendJob($jobdata) {
		// collect the URL data we'll need
		$params = array(
			'job' => $jobdata['job'],
			'token' => $jobdata['token']
		);

		$url = $this->jenkins .'/buildByToken/build?'. http_build_query($params);

		if ($this->rawdata) {
			$this->log(' - url: '. $url);
		}


		$ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status >= 400) {
        	$this->log('ERROR: [HTTP STATUS: '. $http_status .'] Error requesting Jenkins Build');
        	$this->log('       URL: '. $url);
        }
	}

}