<?php namespace Grovers\Deployer;

class Deployer {

	/**
	 * Handle an incoming HTTP request
	 * @return void
	 */
	public function process() {
		// only handle POST requests
		if ( ! self::isPost() ) {
			http_response_code(404);
			return;
		}

		self::log('REQUEST Received');
		if (strtolower(getenv('LOG_RAW') === 'true')) {
			self::log('  ** Raw Data: '. file_get_contents('php://input'));	
		}
		
		// get the job data
		$jobdata = self::extract_data();
		self::log('  jobdata: '. json_encode($jobdata));

		// do not do anything unless we have a job defined.
		if ($jobdata['job'] != '') {
			// self::log('  Requesting Job: '. $jobdata['job']);
			self::sendJob($jobdata);
		}
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
		if (strtolower(getenv('LOG_ENABLE')) === 'true') {
			$log = $_SERVER['DOCUMENT_ROOT'] .'/deployer.log';
			$line = '['. date('Y-m-d H:i:s') .'] '. $msg . PHP_EOL;
			$fh = fopen($log, 'a+');
			if ($fh) {
				fwrite($fh, $line);
				fclose($fh);
			}
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
			'repo'   => '',
			'branch' => '',
			'job'    => '',
			'token'  => '',
			'author' => ''
		];

		$raw = json_decode(file_get_contents('php://input'));

		// repository
		$out['repo'] = $raw->repository->name;

		// 
		$changes = $raw->push->changes;

		// Find the "new" data and extract the branch from there
		foreach($changes as $change) {
			if ($change->new) {
				if ($change->new->type == 'branch') {
					$out['branch'] = $change->new->name;
					$out['author'] = $change->new->target->author->raw;
				}
			}
		}

		// we can only define a job if the branch is known
		if ($out['branch'] != '') {
			$out['job'] = $out['repo'] . getenv('JENKINS_SEPARATOR', '-') . $out['branch'];			
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

		$url = getenv('JENKINS_URL') .'/buildByToken/build?'. http_build_query($params);
		self::log('  url: '. $url);

		$ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status == 0 OR $http_status >= 400) {
        	$msg = 'ERROR: [HTTP STATUS: '. $http_status .'] Error requesting Jenkins Build';
        	self::log('  '. $msg);
        	self::log('  URL: '. $url);
        	self::notifyAuthor($jobdata, $msg);
        }
	}

	/**
	 * Send an email to the commit author notifying an error has occured.
	 */
	private function notifyAuthor($jobdata, $error) {
		if (strtolower(getenv('SMTP_ENABLE')) === 'true') {
			// Set up the mail
			$to = self::extractEmailAddress($jobdata['author']);
			$msgBody = "A deployment error has occured: \n". $error;

			$transport = \Swift_SmtpTransport::newInstance(getenv('SMTP_HOSTNAME'), getenv('SMTP_PORT'))
						->setUsername(getenv('SMTP_USERNAME'))
						->setPassword(getenv('SMTP_PASSWORD'));

			$mailer = \Swift_Mailer::newInstance($transport);
			$message = \Swift_Message::newInstance('Deployment Error')
						->setFrom([getenv('SMTP_SENDER_EMAIL') => getenv('SMTP_SENDER_NAME')])
						->setTo($to)
						->setBody($msgBody);

			// Send the message
			$result = $mailer->send($message);

			self::log('  email sent: '. $to);
		}
	}


	/**
	 * Finds the first email address in a source string
	 */
	private function extractEmailAddress($src) {
		$email = null;
		preg_match("/[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})/i", $src, $matches);
		$email = $matches[0];
		return $email;
	}
}