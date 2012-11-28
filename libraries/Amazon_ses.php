<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * CodeIgniter Amazon SES
 *
 * A CodeIgniter library to interact with Amazon Web Services (AWS) Simple Email Service (SES)
 *
 * @package        	CodeIgniter
 * @category    	Libraries
 * @author        	Joël Cox
 * @link 			https://github.com/joelcox/codeigniter-amazon-ses
 * @link			http://joelcox.nl		
 * @license         http://www.opensource.org/licenses/mit-license.html
 */
class Amazon_ses {
	
	private $_ci;               		// CodeIgniter instance
 	private $_cert_path;				// Path to SSL certificate
	
	private $_access_key;				// Amazon Access Key
	private $_secret_key;				// Amazon Secret Access Key
	private $_mime_boundary;			// Amazon Mime Boundary 
	public $region = 'us-east-1';		// Amazon region your SES service is located
	
	public $from;						// Default from e-mail address
	public $from_name;					// Vanity sender name
	public $reply_to;					// Default reply-to. Same as $from if omitted
	public $recipients = array();		// Contains all recipients (to, cc, bcc)
	public $subject;					// Message subject
	public $message;					// Message body
	public $message_alt;				// Message body alternative in plain-text
	public $charset;					// Character set
	public $attach = array();			// Array for attachment
	public $crlf;							
	
	
	public $debug = FALSE;					
	
	/**
	 * Constructor
	 */
	function __construct()
	{
		log_message('debug', 'Amazon SES Class Initialized');
		$this->_ci =& get_instance();
		
		// Load all config items
		$this->_ci->load->config('amazon_ses');
		$this->_access_key = $this->_ci->config->item('amazon_ses_access_key');
		$this->_secret_key = $this->_ci->config->item('amazon_ses_secret_key');
		$this->_cert_path = $this->_ci->config->item('amazon_ses_cert_path');			
		$this->from = $this->_ci->config->item('amazon_ses_from');
		$this->from_name = $this->_ci->config->item('amazon_ses_from_name');
		$this->charset = $this->_ci->config->item('amazon_ses_charset');
		$this->_mime_boundary = $this->_ci->config->item('amazon_ses_mime_boundary');
		
		$this->crlf = "\n";
		
		// Check whether reply_to is not set
		if ($this->_ci->config->item('amazon_ses_reply_to') === FALSE)
		{
			$this->reply_to = $this->_ci->config->item('amazon_ses_from');
		}
		else
		{
			$this->reply_to = $this->_ci->config->item('amazon_ses_reply_to');
		}
		
		// Is our certificate path valid?
		if ( ! file_exists($this->_cert_path))
		{
			show_error('CA root certificates not found. Please <a href="http://curl.haxx.se/ca/cacert.pem">download</a> a bundle of public root certificates and/or specify its location in config/amazon_ses.php');
		}
		
		// Load Phil's cURL library as a Spark or the normal way
		if (method_exists($this->_ci->load, 'spark'))
		{
			$this->_ci->load->spark('curl/1.0.0');
		}
		
		$this->_ci->load->library('curl');
		
	}
	
	/**
	 * From
	 *
	 * Sets the from address.
	 * @param 	string 	email address the message is from
	 * @param 	string 	vanity name from which the message is sent
	 * @return 	mixed
	 */
	public function from($from, $name = FALSE)
	{
		
		$this->_ci->load->helper('email');
		
		if ($name)
		{
			$this->from_name = $name;
		}
		
		if (valid_email($from))
		{
			$this->from = $from;			
			return $this;
		}
	
		log_message('debug', 'From address is not valid');
		return FALSE;
	
	}
	
	/**
	 * To
	 *
	 * Sets the to address.
	 * @param 	string 	to email address
	 * @return 	mixed 
	 */
	public function to($to)
	{
		$this->_add_address($to, 'to');
		return $this;
	}
	
	/**
	 * CC
	 *
	 * Sets the cc address.
	 * @param 	string 	cc email address
	 * @return 	mixed 
	 */
	public function cc($cc)
	{	
		$this->_add_address($cc, 'cc');
		return $this;
	}
	
	/**
	 * BBC
	 *
	 * Sets the bcc address.
	 * @param 	string 	bcc email address
	 * @return 	mixed 
	 */
	public function bcc($bcc)
	{
		$this->_add_address($bcc, 'bcc');
		return $this;
	}
	
	/**
	 * Subject
	 *
	 * Sets the email subject.
	 * @param 	string	the subject
	 * @return 	mixed
	 */
	public function subject($subject)
	{
		$this->subject = $subject;
		return $this;
	}
	
	/**
	 * Message
	 *
	 * Sets the message.
	 * @param 	string	the message to be sent
	 * @return 	mixed
	 */
	public function message($message)
	{
		$this->message = $message;
		return $this;
	}
	
	/**
	 * Message alt
	 *
	 * Sets the alternative message (plain-text) for when HTML email is not supported by email client.
	 * @param 	string 	the alternative message to be sent
	 * @return 	mixed
	 */
	public function message_alt($message_alt)
	{
		$this->message_alt = $message_alt;
		return $this;
	}
	
	/**
	 * Attach
	 *
	 * Attach files to the email to send
	 * @param 	string 	the alternative message to be sent
	 * @return 	mixed
	 */
	public function attach($filename, $name = FALSE)
	{
		
		if (!file_exists($filename)) return FALSE;
		if ( ! $name) $name = basename($filename);
		
		$this->attach[] = array ( "filename" 	=> $filename,
								  "mime_type"	=> $this->_mime_types(next(explode('.', basename($filename)))),
								  "name"		=> $name,
								  "ext"			=> next(explode('.', basename($filename))),
						 		);
		return $this;
	}
	
	/**
	 * Clear
	 *
	 * @param 	bool	clear attachments
	 * @return	mixed
	 */
	public function clear($clear_attachments = FALSE)
	{

		$this->reply_to		= "";
		$this->recipients 	= array();
		$this->subject		= "";
		$this->message		= "";
		$this->message_alt	= "";
		$this->attach 		= array();

		if ($clear_attachments !== FALSE)
		{
			$this->attach = array(); 
		}

		return $this;
	}
	
	/**
	 * Send
	 *
	 * Sends off the email and make the API request.
	 * @param 	bool	whether to empty the recipients array on success
	 * @return 	bool
	 */
	public function send($destroy = TRUE)
	{
		
		// Create the message query string
		$query_string = (count($this->attach)) ? $this->_format_query_raw_string() : $this->_format_query_string();
		
		// Pass it to the Amazon API	
		$response = $this->_api_request($query_string);		
		
		// Destroy recipients if set
		if ($destroy === TRUE)
		{
			unset($this->recipients);
		}
	
		return $response;
	
	}

	/**
	 * Verify address
	 *
	 * Verifies a from address as a valid sender
	 * @link 	http://docs.amazonwebservices.com/ses/latest/GettingStartedGuide/index.html?VerifyEmailAddress.html
	 * @param 	string	email address to verify as a sender
	 * @return 	bool
     * @author 	Ben Hartard
	 */
	public function verify_address($address)
	{
		
		// Prep our query string
		$query_string = array(
			'Action' => 'VerifyEmailAddress',
			'EmailAddress' => $address
		);
		
		// Hand it off to Amazon		
		return $this->_api_request($query_string);
		
	}
	
	/**
	 * Address is verified
	 *
	 * Checks whether the supplied email address is verified with Amazon.
	 * @param	string	email address to be checked
	 * @return 	bool
	 */
	public function address_is_verified($address)
	{
		// Prep our query string
		$query_string = array(
			'Action' => 'ListVerifiedEmailAddresses'
		);

		// Get our list with verified addresses
		$response = $this->_api_request($query_string, TRUE);

		// Just return the text response when we're in debug mode
		if ($this->debug === TRUE)
		{
			return $response;
		}

		/**
		 * We don't want to introduce another dependency (a XML parser)
	     * so we just check if the address is present in the response
		 * instead of returning an array with all addresses.
		 */
		if (strpos($response, $address) === FALSE)
		{
			return FALSE;
		}
		
		return TRUE;	
		
	}
	
	/**
	 * Debug
	 *
	 * Makes send() return the actual API response instead of a bool
	 * @param 	bool
	 * @return 	void
	 */
	public function debug($bool)
	{
		$this->debug = (bool) $bool;
	}
	
	/**
	 * Add address
	 *
	 * Add a new address to arecipients list.
	 * @param 	string 	email address
	 * @param	string 	recipient type (e.g, to, cc, bcc)
	 */
	private function _add_address($address, $type)
	{
		
		$this->_ci->load->helper('email');
		
		// Take care of arrays and comma delimitered lists	
		if ( ! $this->_format_addresses($address, $type))	
		{	
			$this->_ci->load->helper('email');
						
			if (valid_email($address))
			{
				$this->recipients[$type][] = $address;
			}
			else
			{
				log_message('debug', ucfirst($type) . ' e-mail address is not valid');
				return FALSE;	
			}
			
		}
		
	}
	
	/**
	 * Format addresses
	 *
	 * Formats arrays and comma delimertered lists.
	 * @param 	mixed 	the list with addresses
	 * @param 	string 	recipient type (e.g, to, cc, bcc)
	 */
	private function _format_addresses($addresses, $type)
	{
		// Make sure we're dealing with a proper type
		if (in_array($type, array('to', 'cc', 'bcc'), TRUE) === FALSE)
		{
			log_message('debug', 'Unknow type queue.');
			return FALSE;
		}
		
		// Check if the input is an array
		if (is_array($addresses))
		{
			foreach ($addresses as $address)
			{
				$this->{$type}($address);
			}
			
			return TRUE;
		}
		// Check if we're dealing with a comma seperated list
		elseif (strpos($addresses, ', ') !== FALSE)
		{
			// Write each element
			$addresses = explode(', ', $addresses);
			
			foreach ($addresses as $address)
			{
				$this->{$type}($address);
			}
			
			return TRUE;	
		}
			
		return FALSE;
			
	}
	
	/**
	 * Format query string
	 *
	 * Generates the query string for email
	 * @return	array
	 */
	private function _format_query_string()
	{
		$query_string = array(
			'Action' => 'SendEmail',
			'Source' => ($this->from_name ? $this->from_name . ' <' . $this->from . '>' : $this->from),
			'Message.Subject.Data' => $this->subject,
			'Message.Body.Text.Data' => (empty($this->message_alt) ? strip_tags($this->message) : $this->message_alt),
			'Message.Body.Html.Data' => $this->message
		);
		
		// Add all recipients to array
		if (isset($this->recipients['to']))
		{
			for ($i = 0; $i < count($this->recipients['to']); $i++)
			{
				$query_string['Destination.ToAddresses.member.' . ($i + 1)] = $this->recipients['to'][$i]; 
			}	
		}
		
		if (isset($this->recipients['cc']))
		{
			for ($i = 0; $i < count($this->recipients['cc']); $i++)
			{
				$query_string['Destination.CcAddresses.member.' . ($i + 1)] = $this->recipients['cc'][$i]; 
			}
		}
		
		if (isset($this->recipients['bcc']))
		{
			for ($i = 0; $i < count($this->recipients['bcc']); $i++)
			{
				$query_string['Destination.BccAddresses.member.' . ($i + 1)] = $this->recipients['bcc'][$i]; 
			}
		}
		
		if (isset($this->reply_to) AND ( ! empty($this->reply_to))) 
		{
			$query_string['ReplyToAddresses.member'] = $this->reply_to;
		}
		
		
		// Add character encoding if set
		if ( ! empty($this->charset))
		{
			$query_string['Message.Body.Html.Charset'] = $this->charset;
			$query_string['Message.Body.Text.Charset'] = $this->charset;
			$query_string['Message.Subject.Charset'] = $this->charset;	
		}
				
		return $query_string;
		
	}

	/**
	 * Format query raw string
	 *
	 * Generates the query string for raw email
	 * @return	array
	 */
	private function _format_query_raw_string()
	{
			$query_string = array(
			'Action' => 'SendRawEmail',
			'Source' => ($this->from_name ? $this->from_name . ' <' . $this->from . '>' : $this->from),
			'RawMessage.Data' => ''
		);

		// Add all recipients to array
		if (isset($this->recipients['to']))
		{
			
			for ($i = 0; $i < count($this->recipients['to']); $i++)
			{
				$query_string['Destinations.member.' . ($i + 1)] = $this->recipients['to'][$i];  
			}	

			$query_string['RawMessage.Data'] .= "To:".implode(",", $this->recipients['to'])."\n";
		}

		$query_string['RawMessage.Data'] .= "Subject: ".$this->subject."\n";
		
		if (isset($this->recipients['cc']) and count($this->recipients['cc']))
		{
			$query_string['RawMessage.Data'] .= "Cc:".implode(",", $this->recipients['cc'])."\n";
		}
		
		if (isset($this->recipients['bcc']))
		{
			$query_string['RawMessage.Data'] .= "Bcc:".implode(",", $this->recipients['bcc'])."\n";
		}
		
		if (isset($this->reply_to) AND ( ! empty($this->reply_to))) 
		{
			$query_string['RawMessage.Data'] .= "Reply-To:".$this->reply_to."\n";
		}
		
		 
		$query_string['RawMessage.Data'] .= "Content-Type: multipart/mixed;\n";
		$query_string['RawMessage.Data'] .= "	boundary=\"".$this->_mime_boundary."\"\n";
		$query_string['RawMessage.Data'] .= "MIME-Version: 1.0\n\n";
		
		$query_string['RawMessage.Data'] .= "--".$this->_mime_boundary."\n";
		$query_string['RawMessage.Data'] .= "Content-Type: text/html; charset=\"UTF-8\"\n";
		$query_string['RawMessage.Data'] .= "Content-Transfer-Encoding: quoted-printable\n\n";

		$query_string['RawMessage.Data'] .= $this->_prep_quoted_printable($this->message);
		
		//Add attachment
		foreach($this->attach as $a){
			$query_string['RawMessage.Data'] .= "\n\n--".$this->_mime_boundary."\n";
			$query_string['RawMessage.Data'] .= "Content-Type: ".$a['mime_type']."; name=\"".$this->_prep_quoted_printable($a['name']).".".$a['ext']."\"\n";
			$query_string['RawMessage.Data'] .= "Content-Description: \"".$this->_prep_quoted_printable($a['name'])."\"\n";
			$query_string['RawMessage.Data'] .= "Content-Disposition: attachment; filename=\"".$this->_prep_quoted_printable($a['name']).".".$a['ext']."\"; size=".filesize($a['filename']).";\n";
			$query_string['RawMessage.Data'] .= "Content-Transfer-Encoding: base64\n\n";
			
			$filetype = pathinfo($a['filename'], PATHINFO_EXTENSION);
			$imgbinary = fread(fopen($a['filename'], "r"), filesize($a['filename']));
			$query_string['RawMessage.Data'] .= base64_encode($imgbinary);
			
		}

		$query_string['RawMessage.Data'] = base64_encode($query_string['RawMessage.Data']);
		
		return $query_string;
	}

	/**
	 * Prep Quoted Printable
	 * 
	 * Prepares string for Quoted-Printable Content-Transfer-Encoding
	 * 
	 * @access	private
	 * @param	string
	 * @param	integer
	 * @return	string
	 */
	
	private function _prep_quoted_printable($str, $charlim = '')
	{
		// Set the character limit
		// Don't allow over 76, as that will make servers and MUAs barf
		// all over quoted-printable data
		if ($charlim == '' OR $charlim > '76')
		{
			$charlim = '76';
		}

		// Reduce multiple spaces
		$str = preg_replace("| +|", " ", $str);

		// kill nulls
		$str = preg_replace('/\x00+/', '', $str);

		// Standardize newlines
		if (strpos($str, "\r") !== FALSE)
		{
			$str = str_replace(array("\r\n", "\r"), "\n", $str);
		}


		// Break into an array of lines
		$lines = explode("\n", $str);

		$escape = '=';
		$output = '';

		foreach ($lines as $line)
		{
			$length = strlen($line);
			$temp = '';

			// Loop through each character in the line to add soft-wrap
			// characters at the end of a line " =\r\n" and add the newly
			// processed line(s) to the output (see comment on $crlf class property)
			for ($i = 0; $i < $length; $i++)
			{
				// Grab the next character
				$char = substr($line, $i, 1);
				$ascii = ord($char);

				// Convert spaces and tabs but only if it's the end of the line
				if ($i == ($length - 1))
				{
					$char = ($ascii == '32' OR $ascii == '9') ? $escape.sprintf('%02s', dechex($ascii)) : $char;
				}

				// encode = signs
				if ($ascii == '61')
				{
					$char = $escape.strtoupper(sprintf('%02s', dechex($ascii)));  // =3D
				}

				// If we're at the character limit, add the line to the output,
				// reset our temp variable, and keep on chuggin'
				if ((strlen($temp) + strlen($char)) >= $charlim)
				{
					$output .= $temp.$escape.$this->crlf;
					$temp = '';
				}

				// Add the character to our temporary line
				$temp .= $char;
			}

			// Add our completed line to the output
			$output .= $temp.$this->crlf;
		}

		// get rid of extra CRLF tacked onto the end
		$output = substr($output, 0, strlen($this->crlf) * -1);

		return $output;
	}	
	
	/**
	 * Set headers
	 *
	 * Generates the X-Amzn headers
	 * @return 	string	headers including signed signature
	 */
	private function _set_headers()
	{
		$date = date(DATE_RSS);
		$signature = $this->_sign_signature($date);
		
		$this->_ci->curl->http_header('Content-Type', 'application/x-www-form-urlencoded');
		$this->_ci->curl->http_header('Date', $date);
		$this->_ci->curl->http_header('X-Amzn-Authorization', 'AWS3-HTTPS AWSAccessKeyId=' . $this->_access_key . ', Algorithm=HmacSHA256, Signature=' . $signature);
		
	}
	
	/**
	 * Sign signature
	 *
	 * Calculate signature using HMAC.
	 * @param	string	date used in the header
	 * @return	string 	RFC 2104-compliant HMAC hash
	 */
	private function _sign_signature($date)
	{
		$hash = hash_hmac('sha256', $date, $this->_secret_key, TRUE);	
		return base64_encode($hash);
	}
	
	/**
	 * Endpoint
	 *
	 * Generates the API endpoint.
	 * @return 	string	URL to the SES endpoint for the region
	 */
	private function _endpoint()
	{		
		return 'https://email.' . $this->region . '.amazonaws.com';
	}
	
	/**
	 * API request
	 *
	 * Send a request to the Amazon SES API using Phil's cURL lib.
	 * @param arra		query parameters that have to be added
	 * @param bool		whether to return the actual response
	 * @return mixed
	 */
	private function _api_request($query_string, $return = FALSE)
	{
		
		// Set the endpoint		
		$this->_ci->curl->create($this->_endpoint());
				
		$this->_ci->curl->post($query_string);
		$this->_set_headers();
		
		// Make sure we connect over HTTPS and verify
		if( ! isset($_SERVER['HTTPS']))
		{
			$this->_ci->curl->ssl(TRUE, 2, $this->_cert_path);
		}
		
		// Show headers when in debug mode		
		if($this->debug === TRUE)
		{
			$this->_ci->curl->option(CURLOPT_FAILONERROR, FALSE);
			$this->_ci->curl->option(CURLINFO_HEADER_OUT, TRUE);
		}
			
		$response = $this->_ci->curl->execute();

		// Return the actual response when in debug or if requested specifically
		if($this->debug === TRUE OR $return === TRUE)
		{
			return $response;
		}
				
		// Check if everything went okay
		if ($response === FALSE)
		{
			log_message('debug', 'API request failed.');
			return FALSE;
		}
		
		return TRUE;				
		
	}
	
	/**
	 * _mime_types
	 *
	 * Check if the extension file is allowed from Amazon SES
	 * @param string	Extension
	 * @return string	Mime type
	 */
	private function _mime_types($ext = "")
	{
		$mimes = array(	'hqx'	=>	'application/mac-binhex40',
						'cpt'	=>	'application/mac-compactpro',
						'doc'	=>	'application/msword',
						'bin'	=>	'application/macbinary',
						'dms'	=>	'application/octet-stream',
						'lha'	=>	'application/octet-stream',
						'lzh'	=>	'application/octet-stream',
						'exe'	=>	'application/octet-stream',
						'class'	=>	'application/octet-stream',
						'psd'	=>	'application/octet-stream',
						'so'	=>	'application/octet-stream',
						'sea'	=>	'application/octet-stream',
						'dll'	=>	'application/octet-stream',
						'oda'	=>	'application/oda',
						'pdf'	=>	'application/pdf',
						'ai'	=>	'application/postscript',
						'eps'	=>	'application/postscript',
						'ps'	=>	'application/postscript',
						'smi'	=>	'application/smil',
						'smil'	=>	'application/smil',
						'mif'	=>	'application/vnd.mif',
						'xls'	=>	'application/vnd.ms-excel',
						'ppt'	=>	'application/vnd.ms-powerpoint',
						'wbxml'	=>	'application/vnd.wap.wbxml',
						'wmlc'	=>	'application/vnd.wap.wmlc',
						'dcr'	=>	'application/x-director',
						'dir'	=>	'application/x-director',
						'dxr'	=>	'application/x-director',
						'dvi'	=>	'application/x-dvi',
						'gtar'	=>	'application/x-gtar',
						'php'	=>	'application/x-httpd-php',
						'php4'	=>	'application/x-httpd-php',
						'php3'	=>	'application/x-httpd-php',
						'phtml'	=>	'application/x-httpd-php',
						'phps'	=>	'application/x-httpd-php-source',
						'js'	=>	'application/x-javascript',
						'swf'	=>	'application/x-shockwave-flash',
						'sit'	=>	'application/x-stuffit',
						'tar'	=>	'application/x-tar',
						'tgz'	=>	'application/x-tar',
						'xhtml'	=>	'application/xhtml+xml',
						'xht'	=>	'application/xhtml+xml',
						'zip'	=>	'application/zip',
						'mid'	=>	'audio/midi',
						'midi'	=>	'audio/midi',
						'mpga'	=>	'audio/mpeg',
						'mp2'	=>	'audio/mpeg',
						'mp3'	=>	'audio/mpeg',
						'aif'	=>	'audio/x-aiff',
						'aiff'	=>	'audio/x-aiff',
						'aifc'	=>	'audio/x-aiff',
						'ram'	=>	'audio/x-pn-realaudio',
						'rm'	=>	'audio/x-pn-realaudio',
						'rpm'	=>	'audio/x-pn-realaudio-plugin',
						'ra'	=>	'audio/x-realaudio',
						'rv'	=>	'video/vnd.rn-realvideo',
						'wav'	=>	'audio/x-wav',
						'bmp'	=>	'image/bmp',
						'gif'	=>	'image/gif',
						'jpeg'	=>	'image/jpeg',
						'jpg'	=>	'image/jpeg',
						'jpe'	=>	'image/jpeg',
						'png'	=>	'image/png',
						'tiff'	=>	'image/tiff',
						'tif'	=>	'image/tiff',
						'css'	=>	'text/css',
						'html'	=>	'text/html',
						'htm'	=>	'text/html',
						'shtml'	=>	'text/html',
						'txt'	=>	'text/plain',
						'text'	=>	'text/plain',
						'log'	=>	'text/plain',
						'rtx'	=>	'text/richtext',
						'rtf'	=>	'text/rtf',
						'xml'	=>	'text/xml',
						'xsl'	=>	'text/xml',
						'mpeg'	=>	'video/mpeg',
						'mpg'	=>	'video/mpeg',
						'mpe'	=>	'video/mpeg',
						'qt'	=>	'video/quicktime',
						'mov'	=>	'video/quicktime',
						'avi'	=>	'video/x-msvideo',
						'movie'	=>	'video/x-sgi-movie',
						'doc'	=>	'application/msword',
						'word'	=>	'application/msword',
						'xl'	=>	'application/excel',
						'eml'	=>	'message/rfc822'
					);

		return ( ! isset($mimes[strtolower($ext)])) ? "multipart/*" : $mimes[strtolower($ext)];
	}
		
}