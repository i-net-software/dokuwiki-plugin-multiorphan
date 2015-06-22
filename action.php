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

        global $INPUT, $conf;

        if ( $event->data != 'multiorphan' ) return false;
        if ((!$helper = $this->loadHelper('multiorphan'))) return false;
        $event->preventDefault();
        
        $namespace = $INPUT->str('ns');
        $ns_dir  = utf8_encodeFN(str_replace(':','/',$namespace));
    
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
                
                print json_encode(array(
                    'pages' => $pages, 
                    'media' => $media
                ));
                
                break;
            }
            
            case 'checkpage': {
                
                $id = $INPUT->str('id');
                $result = $this->__check_pages($id);
                print json_encode($result);
                break;
            }
            
            default: {
                print json_encode(array(
                   'error' => 'I do not know what to do.'
                ));
                break;
            }
        }
    
    }
    
    /**
     * Remove not needed information from search
     */
    private function __map_ids(&$element) {
        $element = $element['id'];
    }
    
    private function __check_pages($id) {
        
    }
}

// vim:ts=4:sw=4:et:
