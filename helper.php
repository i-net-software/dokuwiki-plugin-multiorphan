<?php
/**
 * DokuWiki Plugin multiorphan (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  i-net software <tools@inetsoftware.de>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_multiorphan extends DokuWiki_Plugin {

    /**
     * Return info about supported methods in this Helper Plugin
     *
     * @return array of public methods
     */
    public function getMethods() {
        return array(
            array(
                'name'   => 'getThreads',
                'desc'   => 'returns pages with discussion sections, sorted by recent comments',
                'params' => array(
                    'namespace'         => 'string',
                    'number (optional)' => 'integer'
                ),
                'return' => array('pages' => 'array')
            ),
            array(
                // and more supported methods...
            )
        );
    }
    
    /**
     * Constructs the base GUI
     */
    public function __multiorphan_gui() {

		
        global $ID, $conf;

        print $this->locale_xhtml('intro');

        $form = new Doku_Form('multiorphan', null, 'post');
        $form->startFieldset( $this->getLang('startingNamespace') );

        $form->addElement(form_makeTextField('ns', getNS($ID), $this->getLang('ns') . ':', 'ns'));
/*
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeListboxField('type', array( "pages" => $this->getLang('type.pages'), "media" => $this->getLang('type.media'), "both" => $this->getLang('type.both') ), (empty($_REQUEST['type']) ? $this->getLang('type.both') : $_REQUEST['type']), $this->getLang('type') . ':', 'type', null, array_merge(array('class' => 'edit'))));
*/
/*
        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeListboxField('depthType', array( "0" => $this->getLang('depth.pageOnly'), "1" => $this->getLang('depth.allSubNameSpaces'), "2" => $this->getLang('depth.specifiedDepth') ), (empty($_REQUEST['depthType']) ? $this->getLang('depth.allSubNameSpaces') : $_REQUEST['depthType']), $this->getLang('depthType') . ':', 'depthType', null, array_merge(array('class' => 'edit'))));

        $form->addElement(form_makeTag('br'));
        $form->addElement(form_makeOpenTag("div", array('style' => 'display:' . ($_REQUEST['depthType'] == "2" ? "block" : "none") . ';', 'id' => 'depthContainer')));
        $form->addElement(form_makeTextField('depth', $this->getConf('depth'), $this->getLang('depth') . ':', 'depth'));
        $form->addElement(form_makeCloseTag("div"));
*/
        $form->endFieldset();
        
        $form->startFieldset( $this->getLang('startProcess') );
        $form->addElement(form_makeButton('submit', 'multiorphan', $this->getLang('start') , array('style' => 'float:right;')));
        $form->endFieldset();

        $form->startFieldset( $this->getLang('status') );
        $form->addElement(form_makeTag('span', array('id' => 'multiorphan__out')));
        $form->addElement(form_makeOpenTag('span', array('class' => 'multiorphan__throbber')));
        $form->addElement(form_makeTag('img', array('src' => DOKU_BASE.'lib/images/loading.gif', 'id' => 'multiorphan__throbber')));
        $form->addElement(form_makeCloseTag('span'));
        $form->endFieldset();

        $this->__makeForm($form, 'pages');
        $this->__makeForm($form, 'media');

        $form->printForm();

    }

    private function __makeForm(&$form, $type) {
        
        $form->startFieldset( $this->getLang($type.'-result') );
        $form->addElement(form_makeOpenTag('div', array('class' => 'multiorphan__result_group '.$type)));

        $form->addElement(form_makeOpenTag('h3', array('class' => 'header')));
        $form->addElement( $this->getLang('wanted') );
        $form->addElement(form_makeCloseTag('h3'));
        $form->addElement(form_makeOpenTag('div', array('class' => 'multiorphan__result wanted')));
        $form->addElement(form_makeCloseTag('div'));

        $form->addElement(form_makeOpenTag('h3', array('class' => 'header')));
        $form->addElement( $this->getLang('orphan') );
        $form->addElement(form_makeCloseTag('h3'));
        $form->addElement(form_makeOpenTag('div', array('class' => 'multiorphan__result orphan')));
        $form->addElement(form_makeCloseTag('div'));

        $form->addElement(form_makeOpenTag('h3', array('class' => 'header')));
        $form->addElement( $this->getLang('linked') );
        $form->addElement(form_makeCloseTag('h3'));
        $form->addElement(form_makeOpenTag('div', array('class' => 'multiorphan__result linked')));
        $form->addElement(form_makeCloseTag('div'));

        $form->addElement(form_makeCloseTag('div'));
        $form->endFieldset();
    }
}

// vim:ts=4:sw=4:et:
