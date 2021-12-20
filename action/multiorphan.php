<?php
/**
 * DokuWiki Plugin multiorphan (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  i-net software <tools@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) {
    die();
}

class action_plugin_multiorphan_multiorphan extends DokuWiki_Action_Plugin {

    private $checkInstructions = array('plugin', 'externallink', 'interwikilink', 'locallink', 'windowssharelink');
    private $pagesInstructions = array('internallink', 'camelcaselink');
    private $mediaInstructions = array('internalmedia');

    private $renderer = null;
    private $checkExternal = false;
    private $includeWindowsShares = false;

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call_unknown');
        $controller->register_hook('MULTIORPHAN_INSTRUCTION_LINKED', 'BEFORE', $this, 'handle_unknown_instructions');
        $controller->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, 'extend_JSINFO');
    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return false|null
     */

    public function handle_ajax_call_unknown(Doku_Event &$event, $param) {

        global $INPUT, $conf, $AUTH;

        if ( $event->data != 'multiorphan' ) {
            return false;
        }
        if ((!$helper = $this->loadHelper('multiorphan'))) {
            return false;
        }
        if ( !checkSecurityToken() ) {
            return false;
        }
        $event->preventDefault();

        $namespace = $INPUT->str('ns');
        $ns_dir  = utf8_encodeFN(str_replace(':','/',$namespace));
        $this->checkExternal = $INPUT->bool('checkExternal');
        $this->includeWindowsShares = $INPUT->bool('includeWindowsShares');
        $result  = array();

        switch( $INPUT->str('do') ) {

            case 'loadpages': {

                $type = 'both'; //$INPUT->str('type');
                $includeHidden = $INPUT->bool('includeHidden', false);

                if ( $type == 'both' || $type == 'pages') {
                    $pages = array();
                    search($pages,$conf['datadir'],'search_universal',array(
                        'showhidden' => $includeHidden,
                        'skipacl' => $includeHidden,
                        'pagesonly' => true,
                        'listfiles' => true,
                        'idmatch' => trim($INPUT->str('filter'))
                    ),$ns_dir);
                    array_walk($pages, array($this, '__map_ids'));
                }

                if ( $type == 'both' || $type == 'media') {
                    $media = array();
                    search($media,$conf['mediadir'],'search_media',array(
                        'showhidden' => $includeHidden,
                        'skipacl' => $includeHidden,
                        'pattern' => '/' . str_replace('/', '\/', trim($INPUT->str('filter'))) . '/'
                    ),$ns_dir);
                    array_walk($media, array($this, '__map_ids'));
                }

                $result = array(
                    'pages' => $pages, 
                    'media' => $media
                );

                break;
            }

            case 'checkpage': {

                $id = $INPUT->str('id');
                $result = $this->__check_pages($id);
                break;
            }

            case 'deletePage' : {

                $link = urldecode($INPUT->str('link'));
                saveWikiText($link, '', "Remove page via multiORPHANS");
                break;
            }

            case 'viewMedia' : {

                $link = $INPUT->str('link');
                ob_start();
                tpl_mediaFileDetails($link, null);
                $result = array( 'dialogContent' => ob_get_contents());
                ob_end_clean();

                // If there is no content, this could be a link only
                if ( !empty( $result['dialogContent'] ) ) {
                    break;
                }
            }

            case 'deleteMedia' : {

                $link = urldecode($INPUT->str('link'));
                $status = media_delete($link, $AUTH);
                break;
            }

            default: {
                $result = array(
                    'error' => 'I do not know what to do.'
                );
                break;
            }
        }

        print json_encode($result);

    }

    /**
     * Remove not needed information from search
     */
    private function __map_ids(&$element) {
        $element = $element['id'];
    }

    /**
     * Checks a page for the contained links and media.
     * Returns an array: page|media => array of ids with count
     */
    private function __check_pages($id) {

        global $conf;

        $file         = wikiFN($id);
        $instructions = p_cached_instructions($file, false, $id);
        $links        = array('pages' => array(), 'media' => array(), 'href' => wl($id) );

        $this->walk_instructions( $links, $id, $instructions );
        return $links;
    }

    /**
     * Walks a list of instructions to find links
     */
    public function walk_instructions( &$links, $id, $instructions ) {
        global $ID;

        if (!is_array($instructions)) {
            return;
        }

        $internalID = $ID;
        $ID = $id;
        foreach ($instructions as $ins) {

            if ($ins[0] == 'nest' ) {
                $this->walk_instructions( $links, $id, $ins[1][0] );
                continue;
            }

            $data = $this->_getDataContainer( $id, $ins);
            $evt = new Doku_Event('MULTIORPHAN_INSTRUCTION_LINKED', $data);

            // If prevented, this is definitely an orphan.
            if (!is_null($data['type']) || (in_array($ins[0], $this->checkInstructions) && $evt->advise_before())) {
                $this->_addEntryToLinkList( $links, $data);
            }

            unset($evt);
        }
        $ID = $internalID;
    }

    private function _getDataContainer( $id, $instructions ) {

        return array(
                    'pageID' => $id,
                    'instructions' => $instructions[1],
                    'checkNamespace' => getNS($id),
                    'entryID' => !empty($instructions[1][0]) ? $instructions[1][0] : null,
                    'syntax' => $instructions[0],
                    'type' => $this->getInternalMediaType($instructions[0]),
                    'exists' => null,
                    'additionalEntries' => array(),
                );
    }

    private function _addEntryToLinkList( &$links, $data ) {

        if ( !$data || is_null($data['type'])) {
            // still not media type so ignore the entry.
            return;
        }

        $mid = $data['entryID'];
        $hash = null;
        if (strpos($mid, '#') !== false) {
            list($mid, $hash) = explode('#', $mid); //record pages without hashs
        }

		$isLocalLink = $data['syntax'] == 'locallink';
		if ( $isLocalLink ) {
	        $mid = $data['pageID'];
	        $hash = cleanID($data['instructions'][0]);
	        $data['type'] = 'pages';
		}

        if (( !is_bool($data['exists']) || !$data['exists']) && $data['type'] == 'media') {
            resolve_mediaid($data['checkNamespace'], $mid, $data['exists']);
        } else if (!is_bool($data['exists']) || !$data['exists']) {
            resolve_pageid($data['checkNamespace'], $mid, $data['exists']);
            if ( $data['exists'] && !empty( $hash) ) {
                // check for 'locallink' in a different page than the current one
                $linkData = array(
                    'pageID' => $mid,
                    'entryID' => $hash,
                    'exists' => null,
                );

                $this->_check_locallink( $linkData );
                $data['exists'] = $linkData['exists'];
            }
        }

        $itemIndex = $mid . (!empty($hash) ? '#'.$hash : '');
        if (!isset($links[$data['type']][$itemIndex])) {
            $links[$data['type']][$itemIndex] = array(
                'href' => $this->hrefForType( $data['type'], $itemIndex),
                'amount' => 0
            );
        }

        $links[$data['type']][$itemIndex]['amount'] += (is_bool($data['exists']) && $data['exists']) ? 1 : 0;

        if ( !is_array($data['additionalEntries']) ) {
            return;
        } 

        foreach( $data['additionalEntries'] as $additionalEntry ) {
            $this->_addEntryToLinkList( $links, array_merge( $this->_getDataContainer( $data['id'], $data['instructions']), $additionalEntry));
        }
    }

    private function _plugin_input_to_header( &$input, &$data ) {

        // print_r($input);
        switch( $input[1][0] ) {
            case 'box2':
                if ( $input[1][1][0] == 'title' ) {
                    $input = array( 'header', array( $input[1][1][1]) );
                }
                break;
            case 'include_include':
                // Get included instructions
                $plugin = plugin_load('syntax', 'include_include');
                if($plugin != null) {
                    $plugin->render($this->renderer->getFormat(), $this->renderer, $input[1][1]);
                }

                $instructions = $this->renderer->instructions;
                $this->renderer->nest_close();
                $this->_check_locallink( $data, $instructions);
                break;
        }
    }

    private function _check_locallink( &$data, $instructions = null ) {
        $this->_init_renderer();
        $renderer = &$this->renderer;
        $data['type'] = 'pages';
        $data['exists'] = false;
        $result = array();

        if ( is_null($instructions) ) {
            $instructions = p_cached_instructions(wikiFN($data['pageID']), false, $data['pageID']);
        }

        if ( !is_null($instructions) ) {
            $result = array_filter($instructions, function( $input ) use ( $data, $renderer ) {
                // Closure requires PHP >= 5.3

                if ( $input[0] == 'plugin' ) {
                    $this->_plugin_input_to_header( $input, $data );
                }

                if ( $input[0] != 'header' ) {
                    return $data['exists'];
                }

                $hid = $renderer->_headerToLink( $input[1][0] );
                $check = $renderer->_headerToLink( $data['entryID'] );

                return ($hid == $check);
            });
        }

        $data['exists'] = $data['exists'] || count($result) > 0;
    }

    /**
     * Handles unknown instructions using the Event.
     */
    public function handle_unknown_instructions(Doku_Event &$event) {

        //print "Beginn:\n";
        //print_r($event->data);
        $instructions = $event->data['instructions'];
        switch( $event->data['syntax'] ) {

            case 'locallink': {
                $this->_check_locallink( $event->data );
            }
            case 'interwikilink': {

                if ( ! $this->checkExternal ) { return false; }
                $this->_init_renderer();
                $exists = false;
                $event->data['entryID'] = $this->renderer->_resolveInterWiki($instructions[2], $instructions[3], $exists);
            }
            case 'externallink': {

                if ( ! $this->checkExternal ) { return false; }

                $httpClient = new dokuwiki\HTTP\DokuHTTPClient();
                $httpClient->keep_alive = false; // just close it already.
                $httpClient->max_bodysize = 0;
                $data = $httpClient->sendRequest( $event->data['entryID'], null, 'GET' );
                $event->data['exists'] = ( $httpClient->status >= 200 && $httpClient->status <= 200 ) || $httpClient->status == 304;
                $event->data['status'] = $httpClient->status;
                $event->data['type'] = 'urls';
                if ( !empty( $httpClient->error ) ) {
                    $event->data['error'] = $httpClient->error;
                }

                return true;
            }
            case 'windowssharelink': {
                if ( ! $this->includeWindowsShares ) { return false; }
                
                if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                    return false;
                }
                $event->data['exists'] = file_exists($event->data['entryID']);
                $event->data['type'] = 'media';
                return true;
            }
            case 'plugin': {
                switch( $instructions[0] ) {
                    case 'include_include':
                        $event->data['entryID'] = $instructions[1][1];
                        $event->data['type'] = 'pages';
                        return true;
                    case 'imagemapping':
                        if ( $instructions[1][1] == 'area' ) {
                            $event->data['type'] = $this->getInternalMediaType($instructions[1][4]);
                            $event->data['entryID'] = $instructions[1][6];
                        } else 
                        {
                            $event->data['type'] = $this->getInternalMediaType($instructions[1][1]);
                            $event->data['entryID'] = $instructions[1][2];
                        }
                        return true;
                    case 'mp3play':
                        $event->data['entryID'] = $instructions[1]['mp3'];
                        $event->data['type'] = 'media';
                        return true;
                    case 'imagebox':
                        if ( $instructions[1][0] === 1 ) {
                            $event->data['entryID'] = $instructions[1][1]['src'];
                            $event->data['exists'] = $instructions[1][1]['exist'];
                            $event->data['type'] = 'media';
                            return true;
                        }
                    default:
                        // print_r($instructions);
                }
            }
        }

        return false;
    }

    public function extend_JSINFO($event, $param) {
        global $JSINFO;
        $JSINFO['schemes'] = array_values(getSchemes());
    }

    private function _init_renderer() {
        if ( $this->renderer != null ) {
            return;
        }

        @include_once( dirname( __FILE__ ) .  "/../inc/MultiOrphanDummyRenderer.php");
        $this->renderer = new MultiOrphanDummyRenderer();
        $this->renderer->interwiki = getInterwiki();
    }

    private function getInternalMediaType($ins) {
        return in_array($ins, $this->mediaInstructions) ? 'media' : (in_array($ins, $this->pagesInstructions) ? 'pages' : null);
    }

    private function hrefForType( $type, $id ) {
        switch( $type ) {
            case 'pages':
                list($link, $hash) = explode('#', $id, 2);
                if ( !empty( $hash) ) {
                    $this->_init_renderer();
                    $hash = '#' . $this->renderer->_headerToLink( $hash );
                }

                return wl($link) . $hash;
            case 'urls':
                return $id;
            case 'media':
                return  ml($id);
            default:
                return null;
        }
    }
}

// vim:ts=4:sw=4:et:
