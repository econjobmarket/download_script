<?php 
require_once __DIR__.'/../vendor/autoload.php';
use Econjobmarket\OauthClient;
use Econjobmarket\DownloadUtility;
use Illuminate\Database\Capsule\Manager;
class db extends Illuminate\Database\Capsule\Manager{};

// run the script
$showdots = false;
if ($_SERVER ['HTTP_HOST'])
  die ('cli only');
  if ($argv [1] != '-c')
    die ("Usage: ejm_setup.php -c location/of/config/file\n");
    if (! is_readable ($argv [2]))
      die ("{$argv[2]} is not a readable configuration file\n");
      include $argv [2];
  // print_r( $cfg);
  if(isset($argv[3])) $showdots = true;
  $link = new db;
  $link -> addConnection($cfg -> database);
  $link -> setAsGlobal();
  $u = new DownloadUtility($cfg);
  if($cfg -> rating_terms_add_once) {
    foreach($cfg -> rating_terms as $rating) {
      $u -> verifyRatingExists($rating);
    }
  }
 // $autoloader = require... then $result = $autoloader -> findFile('Econjobmarket\OauthClient');
  $started_at = date("Y-m-d H:i:s");
  print "Starting script at ".$started_at."\n";
  print "Started script using ".memory_get_usage()." with peak ".memory_get_peak_usage()."\n";
  $client = new OauthClient($cfg);
  if($client -> error) {
    print "Error creating client:".$client -> error."\nDescription: ".$client -> error_description;
    die("No Curl client, stopped without processing");
  } else {
    // check applicants first so we can retrospectively record gender with applications
    
    print "Start by fetching applicants:\n";
    $result = $client -> fetch('applicants');

    if($result) {
      // make a record of existing applicants
      $query = "select aid from myapplicants";
      $myads = array();
      $myaids_stub = db :: select($query, [1]);
      foreach($myaids_stub as $a) {
        $myaids[$a -> aid] = 0;
      }
      unset($myaids_stub);
      foreach($result as $res) {
        if($showdots) print("*");
        $myaids[$res -> aid] = 1;
        db::table('myapplicants')
        -> updateOrInsert(
            ['aid' => $res -> aid],
            [
            'aid' => $res -> aid,
            'enrolldate' => $res -> enrolldate,
            'changedate' => $res -> changedate,
            'email' => $res -> email,
            'fname' => $res -> fname,
            'mname' => $res -> mname,
            'lname' => $res -> lname,
            'country' => $res -> country,
            'phone' => $res -> phone,
            'fax' => $res -> fax,
            'url' => $res -> url,
            'category_id' => $res -> category_id,
            'primary_field' => $res -> primary_field,
            'degreetype' => $res -> degreetype,
            'degreeinst' => $res -> degreeinst,
            'degreebegyear' => $res -> degreebegyear,
            'degreeendyear' => $res -> degreeendyear,
            'curpos' => $res -> curpos,
            'curinst' => $res -> curinst,
            'ethnic' => $res -> ethnic,
            'ethnicity' => $res -> ethnicity,
            'race' => $res -> race,
            'gender' => $res -> gender,
            'cv_url' => $res -> cv_url,
            'ackdate' => $res -> ack_date,
            'nationality' => $res -> nationality,
            'statusinactivedate' => $res -> statusinactivedate,
            'last_revised_time' => $res -> last_revised_time,
        ]
            );
        //make sure the gender has been recorded in my_sorting_criteria
        if(db :: table ( 'my_sorting_criteria' )
            -> where ( 'criteria', $res -> gender )
            -> doesntExist ()) {
              //add the criteria
              if($res -> gender and (strlen($res -> gender) > 0))
              db::table('my_sorting_criteria')
                -> insert([
                    'criteria' => $res -> gender,
                    'gender' => 1,
                ]);
            }
            
        //add the secondary fields
        foreach($res -> secondary_fields as $key => $value) {
          db::table('my_secondary_fields')
          -> updateOrInsert(
              ['aid' => $res -> aid,
              'category_id' => $key],
              [
              'aid' => $res -> aid,
              'category_id' => $key,
              'name' => $value,
          ]);
          // add the secondary fields if they aren't there
          if(db :: table ( 'my_sorting_criteria' )
              -> where ( 'field_id', $key )
              -> doesntExist ()) {
                //add the criteria
                  db::table('my_sorting_criteria')
                  -> insert([
                      'criteria' => $value,
                      'field_id' => $key,
                  ]);
              }
              
        }
        //now the conferences
        foreach($res -> conferences as $key => $value) {
          db::table('myconferences')
          -> updateOrInsert(
              ['aid' => $res -> aid,
              'conference_id' => $key],
              [
              'aid' => $res -> aid,
              'conference_id' => $key,
              'conference_name' => $value,
          ]);
          if(db :: table ( 'my_sorting_criteria' )
              -> where ( 'conference_id', $key )
              -> doesntExist ()) {
                //add the criteria
                db::table('my_sorting_criteria')
                -> insert([
                    'criteria' => $value,
                    'conference_id' => $key,
                ]);
              }
              
        }
      }
      print "\nAfter applicants using ".memory_get_usage()." with peak ".memory_get_peak_usage()."\n";
      // now remove applicants who are no longer there
      $k =0;
      $kn = 0;
      foreach($myaids as $key => $value) {
        $kn++;
        if($value == 0) {
          db :: table('myapplicants') -> where('aid', $key) -> delete();
          db :: table('myconferences') -> where('aid', $key) ->  delete();
          db :: table('my_secondary_fields') -> where('aid', $key) -> delete();
          $k++;
        }
      }
      print "Removed $k of the $kn applicants\n";
      unset($myaids);
      
    } else print "Nothing returned fetching applicants\n";

    
   
      //starting with appications, note the insert or update method so entries are always replaced
    print "Processing applications now\n";
    $result = $client -> fetch('applications');
    if($result -> error == 'invalid request' and ($result -> error_description == 'Missing or expired token.')) {
      unset($client);
      $client = new OauthClient($cfg);
      $result = $client -> fetch('applications');
    }
    if($result) {
      // make a record of existing applications
      $query = "select appid from myapplications";
      $myappids = array();
      $myappids_stub = db :: select($query, [1]);
      foreach($myappids_stub as $a) {
        $myappids[$a -> aid] = 0;
      }
      unset($myaids_stub);
      foreach($result as $res) {
          if($showdots) print("*");
          $myappids[$res -> appid] = 1;
          db::table('myapplications')
            -> updateOrInsert(
                ['appid' => $res -> appid],
                [
                'appid' => $res -> appid,
                'aid' => $res -> aid,
                'posid' => $res -> posid,
                'appdate' => $res -> appdate,
                'changedate' => $res -> changedate,
                'adtitle' => $res -> adtitle,
                ]
                );
            // mark the gender of the application
            $criteria_id = $u -> getGenderId($res -> aid);
            if(db :: table ( 'my_criteria_assignment' )
                -> where ( [['criteria_id', $criteria_id],
                    ['appid', $res -> appid]]
                    )
                -> doesntExist ()) {
                  //add the criteria
                  db::table('my_criteria_assignment')
                  -> insert([
                      'appid' => $res -> appid,
                      'criteria_id' => $criteria_id,
                  ]);
                }
                // next add the secondary to mark the applications
                $fields = $u -> getFields($res -> aid);
                foreach($fields as $field) {
                if(db :: table ( 'my_criteria_assignment' )
                    -> where ( [['criteria_id', $field],
                        ['appid', $res -> appid]]
                        )
                    -> doesntExist ()) {
                      //add the criteria
                      db::table('my_criteria_assignment')
                      -> insert([
                          'appid' => $res -> appid,
                          'criteria_id' => $field,
                      ]);
                    }
                }
                    
                // final step add the conferences
                $conferences = $u -> getConferences($res -> aid);
                foreach($conferences as $conference) {
                  if(db :: table ( 'my_criteria_assignment' )
                      -> where ( [['criteria_id', $conference],
                          ['appid', $res -> appid]]
                          )
                      -> doesntExist ()) {
                        //add the criteria
                        db::table('my_criteria_assignment')
                        -> insert([
                            'appid' => $res -> appid,
                            'criteria_id' => $conference,
                        ]);
                      }
                }
                
            foreach($res -> questions as $question) {
              db::table('myquestions') 
             -> updateOrInsert(
                    ['appid' => $res -> appid,
                    'question_id' => $question -> id],
                    [
                    'appid' => $res -> appid,
                    'question_id' => $question -> id,
                    'question' => $question -> question,
                    'type_id' => $question -> type_id,
                    'instructions' => $question -> instructions,
                    'response' => $question -> response,
                    'response_id' => $question -> response_id,
                    'created_at' => $question -> created_at,
                    'updated_at' => $question -> updated_at,
                    'response_text' => $question -> response_text,
                    'sort_order' => $question -> sort_order,
                ]);
                //save the sorting criteria if it isn't there
                //also, only do this part for some question types multiple-choice,check-boxes,radio
             if($question -> type_id != 'text'){
                if(db :: table ( 'my_sorting_criteria' )
                    -> where ( 'response_id', $question -> response_id )
                    -> doesntExist ()) {
                      //add the criteria
                      db::table('my_sorting_criteria')
                      -> insert([
                          'criteria' => $question -> response_text,
                          'response_id' => $question -> response_id,
                          'posid' => $res -> posid,
                      ]);
                    }
                 //now save the assignment
                    $criteria_id = $u -> getSortingIdFromResponseId($question -> response_id);
                    if(db :: table ( 'my_criteria_assignment' )
                        -> where ( [['criteria_id', $criteria_id],
                            ['appid', $res -> appid]]
                        )
                        -> doesntExist ()) {
                          //add the criteria
                          db::table('my_criteria_assignment')
                          -> insert([
                              'appid' => $res -> appid,
                              'criteria_id' => $criteria_id,
                          ]);
                        }
             }
                        
            }
// now files
            foreach($res -> files as $file) {
              db::table('myfiles')
              -> updateOrInsert(
                  ['appid' => $res -> appid,
                  'file_id' => $file -> file_id],
                  [
                  'appid' => $res -> appid,
                  'file_id' => $file -> file_id,
                  'description' => $file -> description,
                  'type_id' => $file -> type_id,
                  'type' => $file -> type,
                  'blobid' => $file -> blobid,
                  'aid' => $file -> aid,
                  'long_description' => $file -> long_description,
                  'created_at' => $file -> created_at,
                  'updated_at' => $file -> updated_at,
              ]);
            }
            //now recommendations
            foreach($res -> recommendations as $recommendation) {
              db::table('myrecommendations')
              -> where([
                  
              ])
              -> updateOrInsert(
                  ['appid' => $res -> appid,
                  'recid' => $recommendation -> recid],
                  [
                  'appid' => $res -> appid,
                  'recid' => $recommendation -> recid,
                  'email' => $recommendation -> email,
                  'fname' => $recommendation -> fname,
                  'lname' => $recommendation -> lname,
                  'blobid' => $recommendation -> blobid,
                  'letterid' => $recommendation -> letterid,
                  'institution' => $recommendation -> institution,
                  'department' => $recommendation -> department,
                  'created_at' => $recommendation -> created_at,
                  'updated_at' => $recommendation -> updated_at,
              ]);
            }
            
            
      }
      print "\nAfter applications used ".memory_get_usage()." with peak ".memory_get_peak_usage()."\n";
      $k =0;
      $kn = 0;
      foreach($myappids as $key => $value) {
        $kn++;
        if($value == 0) {
          db :: table('myapplications') -> where('appid', $key) -> delete();
          db :: table('my_criteria_assignment') -> where('appid', $key) ->  delete();
          db :: table('myquestions') -> where('appid', $key) -> delete();
          db :: table('myfiles') -> where('appid', $key) -> delete();
          db :: table('myrecommendations') -> where('appid', $key) -> delete();
          $k++;
        }
      }
      print "Removed $k of the $kn applications\n";
      unset($myappids);
    } else print "Nothing returned fetching applications\n";
       //final section will update blobs
    // check myfiles and myrecommendation tables then verify whether or not the blobs have 
    // already been downloaded.
    //Then clean the blobs table by deleting any blobs that don't have a matching file or recommendation record
    if(!$cfg -> transfer_blobs) die ("Ended without blob transfer as per config");
    print "Processing files:\n";
    $user_files = db::table ('myfiles')
      -> select('file_id', 'blobid')
      -> get();
    $n = 0;
      foreach($user_files as $user_file) {
        if($showdots) print "*";

        if(db::table('myblobs') -> where('blobid', $user_file -> blobid) -> doesntExist()) {

          if(!($result = $client -> fetch('files', $user_file -> file_id))) {
            die("Failed to get files");
          }
          //print "using ".$result ->{0} -> blobid." then ".$result -> filename."\n";

          //die("For debug");
          if(!$result ->{0} -> blobid or ($result ->{0} -> blobid == 0)) continue;
        //  print "\npassed continue\n";
          $n++;
          //if($n > 2) die("ended for debug at 10");
          db::table('myblobs') 
            -> insert([
                'blobid' => $result ->{0} -> blobid,
                'filename' => $result ->{0} -> filename,
                'filesize' => $result ->{0} -> filesize,
                'filetype' => $result ->{0} -> filetype,
                'filecontent' => base64_decode($result ->{0} -> filecontent),
                'sha1_hash' => $result ->{0} -> sha1_hash,
            ]);
        }
      }
      print "\n Added $n new files\n";
      print "After processing files used ".memory_get_usage()." with peak ".memory_get_peak_usage()."\n";
      // finally the files associated with recommendations
      print "Processing Letters\n";
      $user_letters = db::table ('myrecommendations')
      -> select('letterid', 'blobid')
      -> get();
      $n = 0;
      foreach($user_letters as $user_letter) {
        if($showdots) print "*";
        if(!$user_letter -> blobid or ($user_letter -> blobid == 0)) {
          //print "Continuing\n";
          continue;
        }
        
        if(db::table('myblobs') -> where('blobid', $user_letter -> blobid) -> doesntExist()) {
          //print " for debug ".$user_letter -> letterid." then ".$user_letter -> blobid." ";
          if(!($result = $client -> fetch('letters', $user_letter -> letterid))) {
            die("Failed to get files");
          }
          if(!$result ->{0} -> blobid or ($result ->{0} -> blobid == 0)) continue;
          $n++;          

          try {
          db::table('myblobs')
          -> insert([
              'blobid' => $result ->{0} -> blobid,
              'filename' => $result ->{0} -> filename,
              'filesize' => $result ->{0} -> filesize,
              'filetype' => $result ->{0} -> filetype,
              'filecontent' => base64_decode($result ->{0} -> filecontent),
              'sha1_hash' => $result ->{0} -> sha1_hash,
          ]);
          } catch (Exception $e) {
              echo "Query problem: ".$e -> getMessage();
              continue;
          }
        } 
      }
      print "\n Added $n new letters\n";
      print "After processing files used ".memory_get_usage()." with peak ".memory_get_peak_usage()."\n";
      print "Updating placement officers\n";
      $result = $client -> fetch('placement_officers');
      foreach($result as $res) {
        db::table('my_placement_officers')
        -> updateOrInsert(
            ['department' => $res -> department, 'name' => $res -> name],
            ['fname' => $res -> fname, 'lname' => $res -> lname, 
                'email' => $res -> email, 'urlplacement' => $res ->urlplacement]
            );
      }
      
      print "Script started at $started_at and completed at ".date("Y-m-d H:i:s")."\n";

  }

   
?>