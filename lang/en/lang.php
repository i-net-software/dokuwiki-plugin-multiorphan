<?php
/**
 * english language file
 */
 
// settings must be present and set appropriately for the language
$lang['encoding']   = 'utf-8';
$lang['direction']  = 'ltr';
 
// for admin plugins, the menu prompt to be displayed in the admin menu
// if set here, the plugin doesn't need to override the getMenuText() method
$lang['menu'] = 'multiORPHANS'; 

$lang['startingNamespace'] = 'Enter your starting Namespace'; 

$lang['startProcess'] = 'Start Process'; 
$lang['start'] = 'Start'; 
$lang['status'] = 'Status'; 
$lang['ns'] = 'Set Namespace';
$lang['idFilter'] = 'RegEx to filter full ID';
$lang['purge'] = 'Purge cache for checked ID';

$lang['depth'] = 'Depth';
$lang['depthType'] = 'Export Type';
$lang['depth.pageOnly'] = 'this page only';
$lang['depth.allSubNameSpaces'] = 'all sub namespaces';
$lang['depth.specifiedDepth'] = 'specified depth';

$lang['type'] = 'What to check';
$lang['type.pages'] = 'Pages only';
$lang['type.media'] = 'Media only';
$lang['type.both'] = 'Pages and Media';

$lang['pages-result'] = 'Result of PAGES check';
$lang['media-result'] = 'Result of MEDIA check';
$lang['wanted'] = 'Wanted';
$lang['orphan'] = 'Orphaned';
$lang['linked'] = 'Linked';
$lang['checkExternal'] = 'Check external URLs';
$lang['includeHidden'] = 'Include hidden pages';
$lang['includeWindowsShares']  = 'Include Windows Share Links';
$lang['checkExternalHint'] = 'Implies a performance hit due to checking external pages for HTTP status "200 OK".';
$lang['includeWindowsSharesHint'] = 'By default links to Windows Shares are not considered. False-positives might occur while checking these links in consequence of misconfigured <a href="https://secure.php.net/manual/de/function.file-exists.php#76194" target="_blank">share- or NTFS-permissions</a>!';



$lang['js']['request-aborted'] = 'Request aborted';
$lang['js']['start'] = 'Start';
$lang['js']['stop'] = 'Stop';

$lang['js']['checking-done'] = 'Done checking the pages';
$lang['js']['checking-page'] = 'Checking page';
$lang['js']['checking-orphans'] = 'Checking for orphans.';

$lang['js']['error-parsing'] = 'Error parsing answer:';
$lang['js']['please-wait-orphan'] = 'Please wait for the process to finish.';

$lang['throttle'] = 'Throttle request in seconds';
$lang['js']['throttled']        = 'throttled';

//Setup VIM: ex: et ts=4 enc=utf-8 :
