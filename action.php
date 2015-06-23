<?php
/**
 * DokuWiki Plugin multiorphan (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  i-net software <tools@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class action_plugin_multiorphan extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {

       $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle_ajax_call_unknown');
   
    }

    /**
     * [Custom event handler which performs action]
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     * @return void
     */

    public function handle_ajax_call_unknown(Doku_Event &$event, $param) {

        global $INPUT, $conf, $AUTH;

        if ( $event->data != 'multiorphan' ) return false;
        if ((!$helper = $this->loadHelper('multiorphan'))) return false;
        if ( !checkSecurityToken() ) return false;
        $event->preventDefault();
        
        $namespace = $INPUT->str('ns');
        $ns_dir  = utf8_encodeFN(str_replace(':','/',$namespace));
        $result  = array();
    
        switch( $INPUT->str('do') ) {
            
            case 'loadpages': {
                
                $type = 'both'; //$INPUT->str('type');
                
                if ( $type == 'both' || $type == 'pages') {
                    $pages = array();
                    search($pages,$conf['datadir'],'search_allpages',array(),$ns_dir);
                    array_walk($pages, array($this, '__map_ids'));
                }
        
                if ( $type == 'both' || $type == 'media') {
                    $media = array();
                    search($media,$conf['mediadir'],'search_media',array(),$ns_dir);
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
                
                $link = $INPUT->str('link');
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
                if ( !empty( $result['dialogContent'] ) ) break;
            }
            
            case 'viewPage' : {
                
                $link = $INPUT->str('link');
                $result = array('link' => wl($link));
                break;
            }
            
            case 'deleteMedia' : {
                
                $link = $INPUT->str('link');
                $status = media_delete($link,$AUTH);
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
        $instructions = p_cached_instructions($file,false,$id);
        $links        = array();
        $cns          = getNS($id);
        $exists       = false;
        foreach($instructions as $ins) {
            
            switch( $ins[0] ) {
                case 'internallink'  : 
                case 'camelcaselink' : {

                    $mid = $ins[1][0];
                    list($mid) = explode('#', $mid); //record pages without hashs
                    resolve_pageid($cns, $mid, $exists);
                    $links['pages'][$mid] += $exists ? 1 : 0;
                    break;
                }
                
                case 'internalmedia' : {

                    $mid = $ins[1][0];
                    list($mid) = explode('#', $mid); //record pages without hashs
                    resolve_mediaid($cns, $mid, $exists);
                    $links['media'][$mid] += $exists ? 1 : 0;
                    break;
                }
                
            }
        }
        return $links;
    }
}

// vim:ts=4:sw=4:et:
