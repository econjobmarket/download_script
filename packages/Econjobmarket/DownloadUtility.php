<?php
namespace  Econjobmarket;

use \Illuminate\Database\Capsule\Manager;
class db extends \Illuminate\Database\Capsule\Manager{};


class DownloadUtility {
  public function __contruct($cfg) {
    $this ->link = new db;
    $this -> link -> addConnection($cfg -> database);
    $this -> link -> setAsGlobal();
  }
  public function get_sorting_id($criteria) {
    // $criteria would be a text string like Male - this would find the id used for my_criteria_assignment
    $id = db:: table('my_sorting_criteria') 
      -> where('criteria', $criteria)-> value('criteria_id') ;
    if($id) return $id;
    else return false;
  }
  public function getSortingIdFromResponseId($response_id) {
    $id = db:: table('my_sorting_criteria')
    -> where('response_id', $response_id)-> value('criteria_id') ;
    if($id) return $id;
    else return false;
    
  }
  //from an applicant aid, get their gender and its corresponding criteria_id
  public function getGenderId($aid) {
    $gender = db::table('myapplicants')
     -> where('aid', $aid) -> value('gender');
     if($gender) {
       $criteria_id = $this -> get_sorting_id($gender);
       if($criteria_id) return $criteria_id;
       return false;
     }
    return false;
  }
  public function getSortingIdFromFieldId($field_id) {
    $id = db::table('my_sorting_criteria')
      -> where('field_id', $field_id) -> value('criteria_id');
    if($id) return $id;
    return false;
  }
  public function getSortingIdFromConferenceId($conference_id) {
    $id = db::table('my_sorting_criteria')
    -> where('conference_id', $conference_id) -> value('criteria_id');
    if($id) return $id;
    return false;
  }
  
  public function getFields($aid) {
    $fields = array();
    $field_ids = db::table('my_secondary_fields')
    -> where('aid', $aid) -> pluck('category_id');
    foreach($field_ids as $f) {
      $fields[] = $this -> getSortingIdFromFieldId($f);
    }
    return $fields;
  }
  public function getConferences($aid) {
    $conferences = array();
    $conference_ids = db::table('myconferences')
    -> where('aid', $aid) -> pluck('conference_id');
    foreach($conference_ids as $c) {
      $conferences[] = $this -> getSortingIdFromConferenceId($c);
    }
    return $conferences;
  }
  public function verifyRatingExists($rating) {
    if(db :: table ( 'myratings' )
        -> where ( 'rating', $rating )
        -> doesntExist ()) {
          //add the criteria
          $n =
            db::table('myratings')
            -> insert([
                'rating' => $rating,
                'weight' => 0,
            ]);
        }
  }
}

?>