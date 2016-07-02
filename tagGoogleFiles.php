#!/usr/bin/php

<?php
//error_log("Called script with {$argv[1]}\n", 3, ERRORLOG);
date_default_timezone_set('America/Los_Angeles');

define('ERRORLOG',__DIR__.'/logs/errors-'.date('Y-m-d h:i:s'));

if (!isset($argv[1])){
  echo "Please specify a file name.\n\nUsage: php tagGoogleFiles.php <file name>\n\n";
  error_log("Please specify a file name.\n\nUsage: php tagGoogleFiles.php <file name>\n", 3, ERRORLOG);
  exit;
}

$fullFilePath = $argv[1];
$config = parse_ini_file(__DIR__.'/config.ini');

$gdriveCli = $config['gdriveFullPath'];
$gdriveMapping = $config['driveMapping'];

$fileDirectory = dirname($argv[1]);
$fileName = basename($argv[1]);

$fileName = googlefyLocalFileName($fileName);
$fileDirectory = getGoogleDriveBaseDirectory($fileDirectory,$gdriveMapping);
$gDriveAccount = $gdriveMapping[$fileDirectory];

//echo $fullFilePath."\n";
$fileNameSearch = addslashes($fileName);
// Files with "/" get sent with _ instead, running with "OR" takes care of those files.
// It is a hack, because it will still not help with files that have both "/" AND "_" in the name
$exectuteCommand = $gdriveCli. ' list -q "name=\''.$fileNameSearch.'\' OR name=\''.str_replace(array('_','$'), array('/','\$'),$fileNameSearch).'\'" -m 1000 -c '.$gDriveAccount;
//echo $exectuteCommand."\n";exit;
ob_start();
passthru($exectuteCommand);
$searchResults = trim(ob_get_clean());

//echo $searchResults."\n";exit;

$fileIds = extractFileIdsFromSearch($searchResults);
$fileId = $fileIds[0];

// We may have more than one result, if that's the case we need to also include the path
if (sizeof($fileIds) > 1) {
  $fileId = findFileByPath($fileIds, $fullFilePath, $gdriveCli, $gDriveAccount,$fileDirectory);
}


if (!$fileId) {
  error_log("Could not find the file '$fullFilePath' in google drive\n", 3, ERRORLOG);
  exit;
}

$googleFileShareInfo = getGoogleShareInfo($fileId, $gdriveCli, $gDriveAccount);

if (strpos($googleFileShareInfo, 'googleapi: Error') !== false) {
  error_log("$googleFileShareInfo ($fullFilePath)\n", 3, ERRORLOG);
  exit;
}
$tags = getTags($googleFileShareInfo);

tagFile($fullFilePath, $tags);

echo "Successfully completed\n";
//cho $gdriveResuls;

function extractFileIdsFromSearch($searchResults) {
  $searchResultsSplitLines = explode("\n",$searchResults);
  //print_r($searchResultsSplitLines);
  $fileIds = array();
  foreach ($searchResultsSplitLines AS $line) {
    $foundIds = array();
    preg_match_all('/[^\s]+/', $line, $foundIds);

    $fileIds[] = $foundIds[0][0];
  }
  // Remove the first item in the array, it is the "id" title
  array_shift($fileIds);

  return $fileIds;

}

function findFileByPath($fileIds, $fullFilePath, $gdriveCli, $gDriveAccount,$fileDirectory) {

  $expectedGoogleDrivePath = str_replace($fileDirectory,'',googlefyLocalFileName($fullFilePath));

  // We need to remove the first "/" for the match with google drive.

  $expectedGoogleDrivePath = trim(ltrim($expectedGoogleDrivePath,'/'));

  // We store the file info in an associative array in case we need to dig deeper by date
  $googleFileInfoArr = array();
  foreach ($fileIds as $fileId) {
    $exectuteCommand = "$gdriveCli info $fileId -c $gDriveAccount";

    ob_start();
    passthru($exectuteCommand);
    $googleFileInfo = trim(ob_get_clean());
    $googleFileInfoArr[$fileId] = $googleFileInfo;
     //echo "\nEXPECTED: $expectedGoogleDrivePath\n";
     //echo $googleFileInfo."\n===============\n";

    if (strpos($googleFileInfo, 'Path: '.$expectedGoogleDrivePath) !== false) {
      // We found the right file based on the path
      return $fileId;
    }
  }

  // Could not determine based on path only, dig deaper based on date (md5 is not on all items in googledrive)
  $md5checksum = md5_file($fullFilePath);
  $creationDate = date('Y-m-d H:i', filemtime($fullFilePath));

  //echo "Creation Date: $creationDate\n";exit;
  foreach ($googleFileInfoArr as $fileId => $fileInfo) {
    // We could do "Created: ".$creationDate , but sometimes the modify date in google
    // drive is the creation date
    if (strpos($fileInfo, $creationDate) !== false) {
      // We found the right file based on the path
      //return $fileId;
      return $fileId;
    }

  }

  error_log('Could not determine file out of '.sizeof($googleFileInfoArr)." options\n", 3, ERRORLOG);

  return false;
}
/*
  finds the google drive directory from a full Directory

  ex: /Users/myuser/google drive/folder 1/folder 2

  returns /Users/myuser/google drive
*/
function getGoogleDriveBaseDirectory($fileDirectory,$gdriveMapping) {

  foreach (array_keys($gdriveMapping) as $localFolder) {
    // replace the base with nothing in the file directory passed.
    // if the result is different than file directory passed, it means
    // that the google drive folder is contained in the file direcory passed.
    // If the results of the substition is the same as the original file directory
    // then the file directory does not contain the google drive folder.
    $rootFolder = str_replace($localFolder,'',$fileDirectory);
    if ($rootFolder != $fileDirectory) {
      // The $fileDirectory contained the $localfolder, so we can use it to determine
      // the mapping to google drive.
      return $localFolder;
    }
  }

  // If we get here, it means that none of the mapped folders are contained in the
  // file directorty passed.
  error_log("The file path has to match one of the driveMapping array items\n", 3, ERRORLOG);
  exit;

}

function getGoogleShareInfo($fileId, $gdriveCli, $gDriveAccount) {
  $exectuteCommand = "$gdriveCli share list $fileId -c $gDriveAccount";
  ob_start();
  passthru($exectuteCommand);
  $googleFileShareInfo = trim(ob_get_clean());

  return $googleFileShareInfo;
}

/**
 Google does not have the extention for native google files
*/
function googlefyLocalFileName($localFileName) {
  $ext = pathinfo($localFileName, PATHINFO_EXTENSION);

  switch($ext) {
    case "gdsheet":
    case "gsheet":
    case "gddoc":
    case "gdoc":
    case "gdslides":
    case "gslides":
    case "gform":
    case "gdform":
    case "gdlist":
    case "glist":
    case "gdlink":
    case "glink":
    case "gddraw":
    case "gdraw":
        $googleFileName = str_replace(".$ext","",$localFileName);
        break;
    default:
        $googleFileName = $localFileName;
  }

  return $googleFileName;
}

function tagFile($filePath, $tags) {
  $tagsString = '';
  foreach ($tags as $tag) {
    $tagsString.= empty($tagsString)? '"'.$tag.'"':',"'.$tag.'"';
    //$tagsString.= empty($tagsString)? $tag:";$tag";

  }

  //echo __DIR__."/tagfile.py \"$tagsString\" '$filePath'\n";
  //$filePath = addslashes($filePath);

  // fixes files that have ' quote in them
  $filePath = str_replace("'",'\'"\'"\'',$filePath);

  exec("/usr/bin/xattr -w com.apple.metadata:_kMDItemUserTags '($tagsString)' '$filePath'");

  //exec(__DIR__."/tagfile.py \"$tagsString\" '$filePath'");
  //error_log("Setup attributes completed $tagsString$filePath \n", 3, ERRORLOG);
}

function getTags($fileShareInfo) {
  $fileShareInfoLines = explode("\n",$fileShareInfo);
  $tags = array();
  // Get rid of the first row, it is just the titles
  array_shift($fileShareInfoLines);

  foreach ($fileShareInfoLines AS $line) {
    $individualColumns = array();
    preg_match_all('/[^\s]+/', $line, $individualColumns);

    $role =  ucfirst($individualColumns[0][2]);
    $email = $individualColumns[0][3];
    $emailSplit = explode("@",$email);
    $name = ucfirst($emailSplit[0]);

    // Just the first portion of the email - before the @
    $tags[] = "$role:$name";
  }
  // Remove the first item in the array, it is the "id" title


  return $tags;
}
