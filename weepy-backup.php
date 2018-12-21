<?php
require_once('functions.php');

define('LOOKUP_PATH',DATA_PATH . '/sha1');

if (!is_dir(LOOKUP_PATH)) {
  mkdir(LOOKUP_PATH);
}

class WeepyBackup {

  static function get_lookup_path_of_hash($sha1) {
    //return DATA_PATH . '/sha1/' . substr($sha1,0,2) . '/' . substr($sha1,2); 
    $first2 = substr($sha1,0,2);
    $rest = substr($sha1,2);
    return sprintf('%s/%s/%s',LOOKUP_PATH,$first2,$rest);
  }

  static function get_lookup_dir_of_hash($sha1) {
    /////return DATA_PATH . '/sha1/' . substr($sha1,0,2);
    $first2 = substr($sha1,0,2);
    return sprintf('%s/%s',LOOKUP_PATH,$first2);
  }

  static function put_into_inventory_db($item) {
    //pr($item,'item?');
    $sha1 = $item['sha1'];
    $dir = static::get_lookup_dir_of_hash($sha1);
    //pr($dir,'dir?');
    if (!is_dir($dir)) {
      mkdir($dir);
    }  
    $lookup_path = static::get_lookup_path_of_hash($sha1);
    if (!file_exists($lookup_path)) {
      file_put_contents($lookup_path, $item['relative_path']);
    }
  }

  static function get_inventory_from_fs($path) {
    $all_files = glob($path);
    $filtered = $all_files;
    $result = array_map(function($f){
        $sha1 = sha1_file($f);  
        $relative_path = $f;
        if (strstr($relative_path, APP_PATH)) {
          $relative_path = substr($f,strlen(APP_PATH));
        }    
        return [
            'relative_path' => $relative_path,
            //'path' => $f,
            'sha1' => $sha1
        ];
    },$filtered);
    return $result;
  }

  static function lookup_relative_path($sha1) {
    $lookup = static::get_lookup_path_of_hash($sha1);
    $result = file_get_contents($lookup);
    return $result;
  }


  static function get_volumes_menu() {
    return apply_filters('get_backup_volumes_menu',[]);
  }

  static function serve_backup_endpoint() {

    $opts = [];
    $opts = array_merge($opts,$_GET);
    $opts = array_merge($opts,$_POST);

    if (array_key_exists('sha1', $opts)) {
      $sha1 = $opts['sha1'];
      $hit = static::lookup_relative_path($sha1);
      //pr($hit,'hit');
      //die('exiting early');
      // the file name of the download, change this if needed
      $full_path = APP_PATH . '/' . preg_replace('/^\/+/','',$hit);////['relative_path'];
      $fresh_sha1 = sha1_file($full_path);
      if ($fresh_sha1 !== $sha1) {
        pr($hit,sprintf('fresh sha1 %s does not match stored sha1 %s',$fresh_sha1,$sha1));
        die(123);
      }
      //pp($full_path,'full_path');
      $public_name = basename($full_path);
      //pp($public_name,'public_name');
      // get the file's mime type to send the correct content type header
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime_type = finfo_file($finfo,$full_path);
      //pp($mime_type,'mime_type');
      //die('123');
      // send the headers
      header("Content-Disposition: attachment; filename=$public_name;");
      header("Content-Type: $mime_type");
      header('Content-Length: ' . filesize($full_path));

      // stream the file
      $fp = fopen($full_path, 'rb');
      fpassthru($fp);
      exit;
    }

    if (array_key_exists('menu',$opts)){
      header('Content-Type: application/json');
      $menu = static::get_volumes_menu();
      echo json_encode($menu);
      exit;
    }

    if (array_key_exists('volume',$opts)){
      $volume = $opts['volume'];
      preg_match('/^\d+$/',$volume) or die('not numeric');
      header('Content-Type: application/json');
      $menu = static::get_volumes_menu();
      $set = $menu[$volume];
      //($set,'set');
      $list = static::get_inventory_from_fs($set);
      //pr($list,'list');
      foreach($list as $item) {
          static::put_into_inventory_db($item);
      }
      echo json_encode($list);
      exit;
    }

  }

}