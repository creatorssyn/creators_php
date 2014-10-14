<?php
error_reporting(E_ALL);

require_once('../creators_php.php');
require_once('simpletest/autorun.php');

class TestApi extends UnitTestCase 
{
	// This is a demo user key. These tests are written specifically 
	// for this user. They will fail if the API key is changed.
	var $api_key = 'B8C31227882C3C10D954BD11A67DF138125C895B';
    
    // Username and password for the demo API account.
    var $api_username = 'cr_api_demo';
    var $api_password = 'WAg_h(POJI*GF4&3e2R$v6/9=';
	
    function TestAuthenticationFail()
    {
        $api = new Creators_API();
        $this->assertEqual($api->authenticate($this->api_username, 'gobbledygook'), FALSE);
    }
    
    function TestAuthenticationPass()
    {
        $api = new Creators_API();
        $this->assertEqual($api->authenticate($this->api_username, $this->api_password), TRUE);
        $this->assertEqual($api->api_key, $this->api_key);
    }
    
    function TestAPIAuthenticationFail()
	{
		$api = new Creators_API('NotAnAPIKey');
		
		try {
			$api->syn();
		}
		catch(ApiException $e) {
			if($e->getCode() == 401)
				$this->pass();
			else
				$this->fail('Unexpected ApiException error code');
		}
		catch(Exception $e) {
			$this->fail('Unexpected exception type');
		}
	}
	
	function TestAPIAuthenticationPass()
	{
		$api = new Creators_API($this->api_key);
		$this->assertEqual($api->syn(), "ack");
	}
	
	function TestFeatures()
	{
		$api = new Creators_API($this->api_key);
		
		$this->assertIsA($api->get_features(), 'array');
		$this->assertEqual(count($api->get_features()), 4);
	}
	
	function TestFeatureDetails()
	{
		$api = new Creators_API($this->api_key);
		
		try {
			$api->get_feature_details('zzzz');
		}
		catch(ApiException $e) {
			if($e->getCode() == 404)
				$this->pass();
			else
				$this->fail('Unexpected ApiException error code');
		}
		catch(Exception $e) {
			$this->fail('Unexpected exception type');
		}
		
		try {
			$api->get_feature_details('wiz');
		}
		catch(ApiException $e) {
			if($e->getCode() == 403)
				$this->pass();
			else
				$this->fail('Unexpected ApiException error code');
		}
		catch(Exception $e) {
			$this->fail('Unexpected exception type');
		}
		
		$feature = $api->get_feature_details('bc');
		
		$this->assertIsA($feature, 'array');
		
		foreach(array('file_code', 'title', 'category', 'type') as $i)
			$this->assertIsA($feature[$i], 'string');
		
		$this->assertIsA($feature['authors'], 'array');
		$this->assertEqual(count($feature['authors']), 2);
	}
	
	function TestReleases()
	{
		$api = new Creators_API($this->api_key);
		
		try {
			$api->get_releases('zzzz');
		}
		catch(ApiException $e) {
			if($e->getCode() == 404)
				$this->pass();
			else
				$this->fail('Unexpected ApiException error code');
		}
		catch(Exception $e) {
			$this->fail('Unexpected exception type');
		}
		
		try {
			$api->get_releases('wiz');
		}
		catch(ApiException $e) {
			if($e->getCode() == 403)
				$this->pass();
			else
				$this->fail('Unexpected ApiException error code');
		}
		catch(Exception $e) {
			$this->fail('Unexpected exception type');
		}
		
		$releases = $api->get_releases('tma', 1);
		
		$this->assertIsA($releases, 'array');
		$this->assertEqual(count($releases), 1);
		$this->assertIsA($releases[0], 'array');
		
		foreach(array('id', 'title', 'file_code', 'release_date') as $i)
			$this->assertTrue(isset($releases[0][$i]));
			
		$this->assertIsA($releases[0]['files'], 'array');
		$this->assertIsA($releases[0]['notes'], 'array');
		$this->assertTrue(count($releases[0]['files']) > 0);
	}
	
	function TestFiles()
	{
		$api = new Creators_API($this->api_key);
		$dest = "api_test_file";
		
		try {
			$api->download_file('/api/files/download/-1', $dest);
		}
		catch(ApiException $e) {
			if($e->getCode() == 403)
				$this->pass();
			else
				$this->fail('Unexpected ApiException error code');
		}
		catch(Exception $e) {
			$this->fail('Unexpected exception type');
		}
		
		$releases = $api->get_releases('mrz', 1);
		
		$api->download_file($releases[0]['files'][0]['url'], $dest, $headers);
		
		$this->assertEqual($releases[0]['files'][0]['sha1'], sha1_file($dest));
		$this->assertTrue(isset($headers['Content-Length']));
		$this->assertEqual($headers['Content-Length'], filesize($dest));
		unlink($dest);
	}
	
	function TestZip()
	{
		$api = new Creators_API($this->api_key);
		$dest = "api_test_zip";
		$dir = "unzip";
		
		try {
			$api->download_zip(-1, $dest);
		}
		catch(ApiException $e) {
			if($e->getCode() == 404)
				$this->pass();
			else
				$this->fail('Unexpected ApiException error code');
		}
		catch(Exception $e) {
			$this->fail('Unexpected exception type');
		}
		
		$releases = $api->get_releases('tma', 1);
		
		$api->download_zip($releases[0]['id'], $dest, $headers);
		
		$this->assertTrue(isset($headers['Content-Length']));
		$this->assertEqual($headers['Content-Length'], filesize($dest));
		$this->assertEqual($headers['Content-Type'], 'application/zip');
		
		exec("unzip -d $dir $dest", $out, $ret);
		
		$this->assertEqual($ret, 0);
		
		foreach(glob("$dir/*") as $file)
		{
			$found = FALSE;
			
			foreach($releases[0]['files'] as $rel_file)
			{
				if(basename($file) == $rel_file['filename'])
				{
					$found = TRUE;
					$this->assertEqual($rel_file['sha1'], sha1_file($file));
				}
			}
			
			$this->assertEqual($found, TRUE);
		}
		
		exec("rm -r $dir");
		unlink($dest);
	}
}

?>