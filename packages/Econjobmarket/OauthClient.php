<?php
namespace Econjobmarket;

class OauthClient {
  public $client_key;
  public $client_secret;
  public $expires_in;
  public $token_type = false;
  public $scope = false;
  public $access_token = false;
  public $error = false;
  public $error_description = false;
  // the $cfg object defines external urls, database passwords, key and secret
  public function __construct($cfg ) {

   $this -> token_url = $cfg -> token_url;
   $this -> resource_url = $cfg -> resource_url;
    $curl_session = curl_init();
    curl_setopt($curl_session, CURLOPT_URL, $this -> token_url);
    curl_setopt($curl_session, CURLOPT_HEADER, false);
    curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
    $post_array = array (
        'grant_type' => "client_credentials",
    );
    //    debug($post_array);
    curl_setopt($curl_session, CURLOPT_POST, true);
    curl_setopt($curl_session, CURLOPT_USERPWD, $cfg -> client_key.":".$cfg -> client_secret);
    curl_setopt ($curl_session, CURLOPT_POSTFIELDS, $post_array);

    if($result = curl_exec($curl_session)) {
      $json =  json_decode($result);

      if(isset($json -> error)) {

        $this -> error = $json -> error;
        $this -> error_description = $json -> error_description;
      } else {

        $this -> access_token = $json -> access_token;
        $this -> expires_in = $json -> expires_in;
        $this -> token_type = $json -> token_type;
      }
    } else {

      $this -> error = "curl request returned false fetching $this->token_url ";
      $this -> error_description =  curl_error($curl_session).curl_errno($curl_session).debug(curl_getinfo($curl_session));
    }
    
  }
  public function fetch($resource = false, $id = false ) {
    if (!$resource) $this -> json_error('invalid request', "This request did not specify a resource.
      See documentation");
    if(!$id) $resource_url = $this -> resource_url.$resource."/";
    else {
      settype($id, "INTEGER");
      $resource_url = $this -> resource_url.$resource."/".$id.'/';
    }
    // drupal_set_message($resource_url);
    $curl_session = curl_init();
    curl_setopt($curl_session, CURLOPT_URL, $resource_url);
    curl_setopt($curl_session, CURLOPT_HEADER, false);
    curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
    $post_array = array (
        'access_token' => $this -> access_token,
    );
    //    debug($post_array);
    curl_setopt($curl_session, CURLOPT_POST, true);
    // curl_setopt($curl_session, CURLOPT_USERPWD, $this -> client_key.":".$this -> client_secret);
    curl_setopt ($curl_session, CURLOPT_POSTFIELDS, $post_array);
    if($result = curl_exec($curl_session)) {
      curl_close($curl_session);
      return json_decode($result);
      
    } else {
      $result -> error = 'curl error';
      $result -> error_description = "curl request returned false fetching $resource_url ".curl_error($curl_session);
      curl_close($curl_session);
      return $result;
    }
    
    
  }
  private function json_error($error, $error_description) {
    $error_types = array ('invalid request', 'no results returned', 'database connection error',
        'curl error', 'unauthorized request', 'unknown error');
    if (array_search($error, $error_types) === false) {
      $error = 'unknown error';
      $error_description = 'unknown error code $error';
    }
    header ('Content-type: application/json');
    $output = array("error" => $error, "error_description" => $error_description);
    echo json_encode($output);
    die();
  }
}