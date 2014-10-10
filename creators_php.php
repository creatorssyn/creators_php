<?php

/**
 * Creators GET API Interface v0.2
 * Full API docs: http://get.creators.com/docs/wiki
 * Copyright (C) 2014 Creators.com
 * @author Brandon Telle <btelle@creators.com>
 */

class Creators_API 
{
	/**
	 * User's API key.
	 * @var string API key
	 */
	public $api_key = NULL;
	
	/**
	 * URL used to access the GET API.
	 */
	const API_URL = 'http://get.creators.com';
	
	/**
	 * API Version
	 */
	const API_VERSION = 0.2;
	
	/**
	 * Constructor
	 * @param string api_key User's API key.
	 */
	function __construct($api_key=NULL)
	{
		$this->api_key = $api_key;
	}
	
	/**
	 * Make an API request.
	 * @param endpoint string API url
	 * @param parse_json bool if TRUE, parse the result as JSON and return the parsed object
	 * @param headers array stores the headers returned with the API response
	 * @throws ApiException if an error code is returned by the API
	 * @return mixed parsed JSON object, or raw return string
	 */
	function api_request($endpoint, $parse_json=TRUE, &$headers=array())
	{
		if($this->api_key === NULL)
			throw new ApiException("API Key must be set");
			
		$ch = curl_init(self::API_URL.($endpoint[0] == '/'?'':'/').$endpoint);
 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, 
			array('X_API_KEY: '.$this->api_key, 
			      'X_API_VERSION: '.self::API_VERSION));
				  
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
 
        $response = curl_exec($ch);
		
		// Separate the headers from the body. This may be slow for large body size?
		while(substr($response, 0, 7) == 'HTTP/1.')
		{
			list($header, $response) = explode("\r\n\r\n", $response, 2);
			
			if(preg_match('/Location: (.*)/', $header, $r))
				$location = trim($r[1]);
		}
		
		$headers = http_parse_headers($header);
		
		if(isset($location))
			$headers['Redirect-URL'] = $location;
		
        if(!$response)
        {
            throw new ApiException("Server error", 500);
        }
        else 
        {
			// Check for HTTP error messages
			preg_match('/Error ([0-9]+): (.*)/', $response, $matches);
			if(isset($matches[1]))
				throw new ApiException($matches[2], $matches[1]);
		
			// Parse JSON responses
			if($parse_json)
				$response = json_decode($response, TRUE);
            
			// Check for server-generated errors
			if(is_array($response) && isset($response['error']) && $response['error'] > 0)
				throw new ApiException($response['message'], $response['error']);
				
			return $response;
        }
	}
	
	/**
	 * SYN the server
	 * @return string "ack"
	 */
	function syn()
	{
		return $this->api_request('api/etc/syn');
	}
	
	/**
	 * Get a list of available features
	 * @param int limit number of results to return
	 * @return array features
	 */
	function get_features($limit=1000)
	{
		return $this->api_request('api/features/get_list/json/NULL/'.$limit.'/0');
	}
	
	/**
	 * Get details on a feature
	 * @param string filecode unique file code for the feature
	 * @return array feature info
	 */
	function get_feature_details($filecode)
	{
		return $this->api_request('api/features/details/json/'.$filecode);
	}
	
	/**
	 * Get a list of releases for a feature
	 * @param filecode string unique filecode for a feature
	 * @param int limit number of releases to return, default 10
	 * @param int offset offset from the head of the list, default 0
	 * @param string start_date start date: YYYY-MM-DD, default NULL
	 * @param string end_date end_date: YYYY-MM-DD, default NULL
	 * @return array releases
	 */
	function get_releases($filecode, $limit=10, $offset=0, $start_date=NULL, $end_date=NULL)
	{
		return $this->api_request('api/features/get_list/json/'.$filecode."/".$limit."/".$offset."?start_date=".$start_date."&end_date=".$end_date);
	}
	
	/**
	 * Download a file
	 * @param string url URL string provided in the files section of a release result
	 * @param string destination path to the location the file should be saved to
	 * @param array headers stores the headers returned with the API response
	 * @throws ApiException if destination is not a writable file location or url is unavailable
	 * @return bool TRUE if file is downloaded successfully
	 */
	function download_file($url, $destination, &$headers=array())
	{
		$fh = fopen($destination, 'wb');
		
		if($fh !== FALSE)
		{
			$contents = $this->api_request($url, FALSE, $headers);
			
			if($contents[0] === '{')
			{
				$response = json_decode($contents, TRUE);
				
				if(is_array($response) && isset($response['error']) && $response['error'] > 0)
					throw new ApiException($response['message'], $response['error']);
			}
			
			if(fwrite($fh, $contents) === FALSE)
				throw new ApiException("Unable to write to file");
				
			fclose($fh);
			return TRUE;
			
		}
		else
		{
			throw new ApiException("Unable to open destination");
		}
	}
	
	function download_zip($release_id, $destination, &$headers=array())
	{
		$fh = fopen($destination, 'wb');
		
		if($fh !== FALSE)
		{
			$contents = $this->api_request('/api/files/zip/'.$release_id, FALSE, $headers);
			
			if($contents[0] === '{')
			{
				$response = json_decode($contents, TRUE);
				
				if(is_array($response) && isset($response['error']) && $response['error'] > 0)
					throw new ApiException($response['message'], $response['error']);
			}
			
			if(fwrite($fh, $contents) === FALSE)
				throw new ApiException("Unable to write to file");
				
			fclose($fh);
			return TRUE;
			
		}
		else
		{
			throw new ApiException("Unable to open destination");
		}
	}
}

/**
 * API Exception class
 */
class ApiException extends Exception
{
	public function __construct($message, $code = 0, Exception $previous = null) 
	{
        parent::__construct($message, $code, $previous);
    }
	
	public function __toString() 
	{
        return __CLASS__ . ": ".(($this->code > 0)?"[{$this->code}]:":"")." {$this->message}\n";
    }
}

/**
 * Substitute for http_parse_headers if pecl_http is not installed
 *
 * http://php.net/manual/en/function.http-parse-headers.php
 */
if (!function_exists('http_parse_headers'))
{
    function http_parse_headers($raw_headers)
    {
        $headers = array();
        $key = ''; // [+]

        foreach(explode("\n", $raw_headers) as $i => $h)
        {
            $h = explode(':', $h, 2);

            if (isset($h[1]))
            {
                if (!isset($headers[$h[0]]))
                    $headers[$h[0]] = trim($h[1]);
                elseif (is_array($headers[$h[0]]))
                {
                    // $tmp = array_merge($headers[$h[0]], array(trim($h[1]))); // [-]
                    // $headers[$h[0]] = $tmp; // [-]
                    $headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1]))); // [+]
                }
                else
                {
                    // $tmp = array_merge(array($headers[$h[0]]), array(trim($h[1]))); // [-]
                    // $headers[$h[0]] = $tmp; // [-]
                    $headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1]))); // [+]
                }

                $key = $h[0]; // [+]
            }
            else // [+]
            { // [+]
                if (substr($h[0], 0, 1) == "\t") // [+]
                    $headers[$key] .= "\r\n\t".trim($h[0]); // [+]
                elseif (!$key) // [+]
                    $headers[0] = trim($h[0]);trim($h[0]); // [+]
            } // [+]
        }

        return $headers;
    }
}
 
?>