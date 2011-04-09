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
 * @license         http://www.opensource.org/licenses/mit-license.html
 * 
 * Copyright (c) 2011 Joël Cox and contributers
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

class Amazon_ses
{
	
	private $_ci;               		// CodeIgniter instance
 	private $_cert_path;				// Path to SSL certificate
	
	private $_access_key;				// Amazon Access Key
	private $_secret_key;				// Amazon Secret Access Key 
	public $region = 'us-east-1';		// Amazon region your SES service is located
	
	public $from;						// Default from e-mail address
	public $reply_to;					// Default reply-to. Same as $from if omitted
	public $recipients = array();		// Contains all recipients (to, cc, bcc)
	public $subject;					// Message subject
	public $message;					// Message body
	public $message_alt;				// Message body alternative in plain-text
	public $destroy = TRUE;				// Whether to reset everything after success		
	
	public $debug = FALSE;					
	
	/**
	 * Initializes the class and references CI and read config
	 */
	function __construct()
	{
		log_message('debug', 'Amazon SES Class Initialized.');

		$this->_ci =& get_instance();
		
		// Load all config items
		$this->_ci->load->config('amazon_ses');
		$this->_access_key = $this->_ci->config->item('amazon_ses_access_key');
		$this->_secret_key = $this->_ci->config->item('amazon_ses_secret_key');
		$this->_cert_path = $this->_ci->config->item('amazon_ses_cert_path');			
		$this->from = $this->_ci->config->item('amazon_ses_from');
		
		// Check whether reply_to is not set
		if ($this->_ci->config->item('reply_to') === FALSE)
		{
			$this->reply_to = $this->_ci->config->item('amazon_ses_from');
		}
		else
		{
			$this->reply_to = $this->_ci->config->item('amazon_ses_reply_to');
		}
		
		if ( ! file_exists($this->_cert_path))
		{
			show_error('CA root certificates not found. Please <a href="http://curl.haxx.se/ca/cacert.pem">download</a> a bundle of public root certificates and/or specify its location in config/amazon_ses.php');
		}
		
	}
	
	/**
	 * Sets the from address
	 * @param string email address the message is from
	 * @param string name for the from address
	 * @return void
	 */
	public function from($from, $name = NULL)
	{
		
		$this->_ci->load->helper('email');
		
		if (valid_email($from))
		{
			$this->from = $from;
		}
		else
		{
			log_message('debug', 'From address is not valid');
		}
	}
	
	/**
	 * Sets the to address
	 * @param string to email address
	 * @return void 
	 */
	public function to($to)
	{
		$this->_add_address($to, 'to');
		
		return $this;
	}
	
	/**
	 * Sets the cc address
	 * @param string cc email address
	 * @return void 
	 */
	public function cc($cc)
	{	
		$this->_add_address($cc, 'cc');
		
		return $this;
	}
	
	/**
	 * Sets the bcc address
	 * @param string bcc email address
	 * @return void 
	 */
	public function bcc($bcc)
	{
		$this->_add_address($bcc, 'bcc');

		return $this;
	}
	
	/**
	 * Sets the email subject
	 * @param string the subject
	 * @return void
	 */
	public function subject($subject)
	{
		$this->subject = $subject;
	}
	
	/**
	 * Sets the message
	 * @param string the message
	 * @return void
	 */
	public function message($message)
	{
		$this->message = $message;
	}
	
	/**
	 * Sets the alternative message (plain-text) for when HTML email is not supported by email client
	 * @param string the message
	 * @return void
	 */
	public function message_alt($message_alt)
	{
		$this->message_alt = $message_alt;
	}
	
	/**
	* Sends off the email
	* @param boolean whether to empty the $recipients array on success
	* @return boolean
	*/
	public function send($destroy = TRUE)
	{
		// First try to load the cURL library through Sparks and fall back on the default loader
		if (method_exists($this->_ci->load, 'spark'))
		{
			$this->_ci->load->spark('curl/1.0');
		}
		else
		{
			$this->_ci->load->library('curl');		
		}
		
		// Set the endpoint		
		$this->_ci->curl->create($this->_endpoint());
		
		// Add post options and headers
		$this->_format_query_string();
		$this->_set_headers();
		
		// Make sure we connect over HTTPS and verify
		$this->_ci->curl->ssl(TRUE, 2, $this->_cert_path);
		
		// Show headers and output when in debug mode		
		if($this->debug === TRUE)
		{
			$this->_ci->curl->option(CURLOPT_FAILONERROR, FALSE);
			$this->_ci->curl->option(CURLINFO_HEADER_OUT, TRUE);
			
			return $this->_ci->curl->execute();
			
		}
			
		$response = $this->_ci->curl->execute();	
				
		// Check if everything went okay
		if ($response === FALSE)
		{
			log_message('debug', 'Email could not be send');
			return FALSE;
		}
		else
		{
			// Destroy recipients if needed
			if ($destroy === TRUE)
			{
				unset($this->recipients);
			}
			
			return TRUE;				
		}
	}

	/**
	* Verify an email sender 'From' address
	* @param string email to verify as a sender for this Amazon SES account
	* @return boolean
	*/
	public function verify($email)
	{
		// First try to load the cURL library through Sparks and fall back on the default loader
		if (method_exists($this->_ci->load, 'spark'))
		{
                       $this->_ci->load->spark('curl/1.0');
		}
		else
		{
			$this->_ci->load->library('curl');		
		}
		
		// Set the endpoint		
		$this->_ci->curl->create($this->_endpoint());
		
		// Add post options and headers
		$query_string = array(
			'Action' => 'VerifyEmailAddress',
			'EmailAddress' => $email
		);		
		$this->_ci->curl->post($query_string);
		$this->_set_headers();
		
		// Make sure we connect over HTTPS and verify
		$this->_ci->curl->ssl(TRUE, 2, $this->_cert_path);
		
		// Show headers and output when in debug mode		
		if($this->debug === TRUE)
		{
			$this->_ci->curl->option(CURLOPT_FAILONERROR, FALSE);
			$this->_ci->curl->option(CURLINFO_HEADER_OUT, TRUE);
			
			return $this->_ci->curl->execute();
			
		}
			
		$response = $this->_ci->curl->execute();	
				
		// Check if everything went okay
		if ($response === FALSE)
		{
			log_message('debug', 'Email verification request failed.');
			return FALSE;
		}
		else
		{
			return TRUE;				
		}
	}
	
	/**
	* Sets debugmode
	* Make send() return the actual response instead of a bool
	* @return void
	*/
	public function debug()
	{
		$this->debug = TRUE;
	}
	
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
				log_message('debug', 'The ' . $type . ' address is not valid');	
			}
			
		}
		
	}
	
	/**
	 * Formats arrays and comma delimertered lists
	 * @param array or string the list with addresses
	 * @param string recipient type (i.e. to, cc, bcc)
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
		else
		{
			
			return FALSE;
			
		}
	}
	
	/**
	 * Generates the query string to be posted
	 * @return void
	 */
	private function _format_query_string()
	{
		$query_string = array(
			'Action' => 'SendEmail',
			'Source' => $this->from,
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
				
		$this->_ci->curl->post($query_string);
		
	}
	
	/**
	 * Generates the X-Amzn headers
	 * @return string headers including signed signature
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
	 * Calculate signature
	 * @param string date used in the header
	 * @return string the RFC 2104-compliant HMAC hash
	 */
	private function _sign_signature($date)
	{
		
		$hash = hash_hmac('sha256', $date, $this->_secret_key, TRUE);
		
		return base64_encode($hash);
	}
	
	/**
	 * Generates API endpoint
	 * @return string URL to the SES endpoint for the region
	 */
	private function _endpoint()
	{		
		return 'https://email.' . $this->region . '.amazonaws.com';
	}
		
}