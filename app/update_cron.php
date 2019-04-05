<?php 
require_once '../vendor/autoload.php';
use Econjobmarket\OauthClient;
use Econjobmarket\DownloadUtility;
use Illuminate\Database\Capsule\Manager;
class db extends Illuminate\Database\Capsule\Manager{};

// run the script
if ($_SERVER ['HTTP_HOST'])
  die ('cli only');
  if ($argv [1] != '-c')
    die ("Usage: ejm_setup.php -c location/of/config/file\n");
    if (! is_readable ($argv [2]))
      die ("{$argv[2]} is not a readable configuration file\n");
      include $argv [2];
  // print_r( $cfg);
  $link = new db;
  $link -> addConnection($cfg -> database);
  $link -> setAsGlobal();
 // $autoloader = require... then $result = $autoloader -> findFile('Econjobmarket\OauthClient');
  print_r($result);
  $client = new OauthClient($cfg);
  print "Now using ".memory_get_usage()." with peak ".memory_get_peak_usage()."\n";
  if($client -> error) {
    print_r($client);
    die("No Curl client, stopped without processing");
  } else {
    //starting with appications, note the insert or update method so entries are always replaced
    $result = $client -> fetch('applications');
    if($result) {
      foreach($result as $res) {
          db::table('myapplications')
            -> where('appid', $res -> appid)
            -> updateOrInsert([
                'appid' => $res -> appid,
                'aid' => $res -> aid,
                'posid' => $res -> posid,
                'appdate' => $res -> appdate,
                'changedate' => $res -> changedate,
                'adtitle' => $res -> adtitle,
                ]
                );
            foreach($res -> questions as $question) {
              db::table('myquestions') 
                -> where([
                    ['appid', $res -> appid],
                    ['question_id', $question -> id],
                ])
                -> updateOrInsert([
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
                ]);
            }
// now files
            foreach($res -> files as $file) {
              db::table('myfiles')
              -> where([
                  ['appid', $res -> appid],
                  ['file_id', $file -> file_id],
              ])
              -> updateOrInsert([
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
                  ['appid', $res -> appid],
                  ['recid', $recommendation -> recid],
              ])
              -> updateOrInsert([
                  'appid' => $res -> appid,
                  'recid' => $recommendation -> recid,
                  'email' => $recommendation -> email,
                  'fname' => $recommendation -> fname,
                  'lname' => $recommendation -> lname,
                  'blobid' => $recommendation -> blobid,
                  'letterid' => $recommendation -> letterid,
                  'department' => $recommendation -> department,
                  'created_at' => $recommendation -> created_at,
                  'updated_at' => $recommendation -> updated_at,
              ]);
            }
            
            
      }
      print "After applications used ".memory_get_usage()." with peak ".memory_get_peak_usage()."\n";
    } else print "Nothing returned fetching applications\n";
    $result = $client -> fetch('applicants');
    if($result) {
        foreach($result as $res) {
            db::table('myapplicants')
            -> where('aid', $res -> aid)
            -> updateOrInsert([
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
            //add the secondary fields
            foreach($res -> secondary_fields as $key => $value) {
            db::table('my_secondary_fields')
                -> where([
                    ['aid', $res -> aid],
                    ['category_id', $key]
                ])
                -> updateOrInsert([
                    'aid' => $res -> aid,
                    'category_id' => $key,
                    'name' => $value,
                ]);
            }
            //now the conferences
            foreach($res -> conferences as $key => $value) {
                db::table('myconferences')
                -> where([
                    ['aid', $res -> aid],
                    ['conference_id', $key],
                ])
                -> updateOrInsert([
                    'aid' => $res -> aid,
                    'conference_id' => $key,
                    'conference_name' => $value,
                ]);
            }
        }
        print "After applicants using ".memory_get_usage()." with peak ".memory_get_peak_usage()."\n";
    } else print "Nothing returned fetching applications\n";
   
    //final section will update blobs
    // check myfiles and myrecommendation tables then verify whether or not the blobs have 
    // already been downloaded.
    //Then clean the blobs table by deleting any blobs that don't have a matching file or recommendation record
    if(!$cfg -> transfer_blobs) die ("Ended without blob transfer as per config");
    $user_files = db::table ('myfiles')
      -> select('file_id', 'blobid')
      -> get();
    $n = 0;
      foreach($user_files as $user_file) {
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
      $user_letters = db::table ('myrecommendations')
      -> select('letterid', 'blobid')
      -> get();
      $n = 0;
      foreach($user_letters as $user_letter) {
        if(db::table('myblobs') -> where('blobid', $user_letter -> blobid) -> doesntExist()) {
          if(!($result = $client -> fetch('letters', $user_letter -> letterid))) {
            die("Failed to get files");
          }
          if(!$result ->{0} -> blobid or ($result ->{0} -> blobid == 0)) continue;
          $n++;
          print "*";
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
      print "\n Added $n new letters\n";
      print "After processing files used ".memory_get_usage()." with peak ".memory_get_peak_usage()."\n";
      
  }

   
?>