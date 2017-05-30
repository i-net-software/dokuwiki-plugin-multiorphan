<?php
/**
 * Dummy Renderer
 *
 */
if(!defined('DOKU_INC')) die('meh.');

/**
 * Dummy Renderer
 */
class MultiOrphanDummyRenderer extends Doku_Renderer_xhtml {

    /** @var array list of allowed URL schemes */
    public $instructions = array();

    /**
    * handle nested render instructions
    * this method (and nest_close method) should not be overloaded in actual renderer output classes
    *
    * @param array $instructions
    */
    function nest($instructions) {
        $this->instructions = array_merge($this->instructions, $instructions);
    }
    
    /**
    * dummy closing instruction issued by Doku_Handler_Nest
    *
    * normally the syntax mode should override this instruction when instantiating Doku_Handler_Nest -
    * however plugins will not be able to - as their instructions require data.
    */
    function nest_close() {
        $this->instructions = array();
    }
}

//Setup VIM: ex: et ts=4 :
