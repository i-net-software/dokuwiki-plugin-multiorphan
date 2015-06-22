
// multiorphan Admin Plugin Script
(function(){

    var canBeStopped = false, $orphanForm = null, $currentPagesAndMedia, $currentResults;

    var ORPHANACTIONS = {

        view : function(type) {
            return {
                label: 'View',
                click: function() {
                    var $link = jQuery(this);
                    request({'do':'view'+type, 'link':$link.attr('elementid')}, function(response){
                        
                        if ( response.dialogContent ) {
                            jQuery('<div/>').attr('id', 'multiorphan__preview_dialog').appendTo('body').dialog({
                                title:'Preview',
                                height: Math.min(700,jQuery(window).height()-50),
                                width: Math.min(700,jQuery(window).width()-50),
                                autoOpen:true,
                                closeOnEscape:true,
                                modal:true,
                                close: function() { 
                                    jQuery(this).dialog('close').remove();
                                } 
                            }).html(response.dialogContent);
                        } else if ( response.link ) {
                            var win = window.open(response.link, '_blank');
                            win.focus();
                        }
                    });
                    return false;
                }
            }
        },
        
        delete : function(type) {
            return {
                label: 'Delete',
                click: function() {
                    var $link = jQuery(this);
                    request({'do':'delete'+type , 'link':$link.attr('elementid')}, function(response){
                        $link.parents('.entry[elementid="'+$link.attr('elementid')+'"]').addClass('deleted disabled');
                        $link.parent('.actions').remove();
                    });
                    return false;
                }
            }
        }
        
    };

    var init = function() {
        $orphanForm = jQuery('form#multiorphan').submit(loadpages);
        $orphanForm.find( ".multiorphan__result_group" ).accordion({
            collapsible: true,
            active: false,
            heightStyle: "content"
        });
        reset();
    };
    
    /**
     * Load all pages and media
     * Cycle the result.
     */
    var loadpages = function(event) {
        
        if ( canBeStopped ) {
            
            reset();
            errorLog(getLang('request-aborted'));
            return false;
        }
        
        reset(true);
        canBeStopped = true;
        event.stopPropagation();
        $orphanForm.find('input[type=submit]').val(getLang('stop'));
        request({'do':'loadpages'}, function( $result ){

            // Start cycling pages
            $currentPagesAndMedia = $result;
            $currentPagesAndMedia.interval = Math.floor($currentPagesAndMedia.pages.length / 100);
            checkpagesandmedia(jQuery.makeArray($result.pages));
        });
        
        return false;
    };
    
    /**
     * Walk the current elements Tree
     */
    var checkpagesandmedia = function(elements) {

        // Cycle pages. Media is implicite.
        var validateElement = function(result) {
            
            // Check if we still have elements in the elements list (cycle-list) and in the resultList (could be stopped.)
            if ( elements && elements.length && $currentPagesAndMedia && $currentPagesAndMedia.pages && $currentPagesAndMedia.pages.length ) {
                var element = elements.pop();
                status(getLang('checking-page') + "("+($currentPagesAndMedia.pages.length-elements.length)+"/"+($currentPagesAndMedia.pages.length)+"): " + element);
                request({'do':'checkpage','id':element}, function(response) {
                    checkResponseForWantedAndLinked(response, element);

                    // Every 10 pages
                    if ( elements && elements.length && elements.length % $currentPagesAndMedia.interval == 0 ) {
                        findOrphans();
                    }
                }).always(validateElement);
            } else {
                
                // All done. Check for Orphans.                
                findOrphans();
                
                // Now we can leave.
                status(getLang('checking-done'));
                reset();
            }
        };
        
        validateElement();
    };
    
    var guiElementActions = function(actions, id, $insertPoint) {
        
        // Add actions
        var $buttonSet = jQuery('<div/>').addClass('actions').appendTo($insertPoint);
        jQuery.each(actions||[], function(idx, action) {
            var $link = jQuery('<a href=""/>').attr('elementid', id).text(action.label).appendTo($buttonSet).click(action.click);
            if ( action.process ) {
                action.process($link);
            }
        })
    };
    
    /**
     * Add an entry to the accordion of the according type.
     */
    var addGUIEntry = function($insertPoint, id, requestPage, actions) {

        var $header = $insertPoint.prev('.header');
        $header.attr('count', parseInt($header.attr('count')||0)+1);
        
        var $appendTo = $insertPoint.find('.entry[elementid="'+id+'"] > ul');
        if ( !$appendTo.length ) {
            var $wrapper = jQuery('<div/>').text(id).addClass('entry').attr('elementid', id).appendTo($insertPoint);
            guiElementActions(actions, id, $wrapper);
            
            $appendTo = jQuery('<ul/>').appendTo($wrapper);
        }
        
        if ( requestPage && requestPage.length ) {
            var $entry = jQuery('<li/>').addClass('requestPage').text(requestPage).appendTo($appendTo);
            guiElementActions(actions, requestPage, $entry);
        }
    };
    
    /**
     * Build up the structure for linked and wanted pages
     */
    var checkResponseForWantedAndLinked = function(response, requestPage) {
        
        // Fill the $currentResults object with information.
        var checkResponse = function( id, amount, object, $output, actions ) {

            var checkPoint  = amount == 0 ? object.wanted : object.linked;
            if ( !Array.isArray(checkPoint[id]) ) {
                checkPoint[id] = [];
            }
            
            if ( checkPoint[id].indexOf(requestPage) == -1 ) {
                checkPoint[id].push(requestPage);
            }
            
            addGUIEntry($output.find('.multiorphan__result.' + (amount == 0 ? 'wanted' : 'linked')), id, requestPage, actions);
        };
      
        var $pagesOut = $orphanForm.find('.multiorphan__result_group.pages');
        var $mediaOut = $orphanForm.find('.multiorphan__result_group.media');
        jQuery.each((response||{}).pages||[], function(page, amount){
            checkResponse(page, amount, $currentResults.pages, $pagesOut, [ORPHANACTIONS.view('Page')]);
        });
      
        jQuery.each((response||{}).media||[], function(media, amount){
            checkResponse(media, amount, $currentResults.media, $mediaOut, [ORPHANACTIONS.view('Media')]);
        });
    };
    
    /**
     * walk all linked pages and remove them from the ones that actually exist in the wiki
     * assign the result to the array.
     */
    var findOrphans = function() {

        // Sort out all not 
        var orphaned = function(linked, orphaned) {
            
            if ( !orphaned || !orphaned.length ) return [];
            
            jQuery.each(linked, function(link) {
                if ( (idx = orphaned.indexOf(link)) > -1 ) {
                    orphaned.splice(idx, 1);
                }
            });
            
            return orphaned;
        };

        status(getLang('checking-orphans'));
        $orphanForm.find('.multiorphan__result_group .orphan.header').attr('count', null);
        $orphanForm.find('.multiorphan__result_group .multiorphan__result.orphan').html('');
        
        var $pagesOut = $orphanForm.find('.multiorphan__result_group.pages .multiorphan__result.orphan');
        $currentResults.pages.orphan = orphaned($currentResults.pages.linked, $currentPagesAndMedia.pages);
        jQuery.each($currentResults.pages.orphan, function(idx, orphan){
            addGUIEntry($pagesOut, orphan, null, [ORPHANACTIONS.view('Page'), ORPHANACTIONS.delete('Page')]);
        });        
        
        var $mediaOut = $orphanForm.find('.multiorphan__result_group.media .multiorphan__result.orphan');
        $currentResults.media.orphan = orphaned($currentResults.media.linked, $currentPagesAndMedia.media);
        jQuery.each($currentResults.media.orphan, function(idx, orphan){
            addGUIEntry($mediaOut, orphan, null, [ORPHANACTIONS.view('Media'), ORPHANACTIONS.delete('Media')]);
        });        
    };

    /**
     * Send a request to the plugin.
     */
    var request = function(data, success) {
        data['ns']     = $orphanForm.find('input[name=ns]').val();
        data['sectok'] = $orphanForm.find('input[name=sectok]').val();
        // data['type']   = $orphanForm.find('select[name=type]').val() || 'both';
        data['call']   = 'multiorphan';

        throbber(true);
        return jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', data, handleResponse(success)).always(function(){
            throbber(false);
        });
    };

    /**
     * Handles Reponses with JSON from AJAX requests.
     * Also takes care of errors.
     */ 
    var handleResponse = function( next ) {
        return function( response ) {
            // Check for errors
            var $result;
            try {
                $result = jQuery.parseJSON(response);
            } catch( e ) {
                throbber(false);
                return errorLog( getLang('error-parsing') + "\n" + response + "\n\n" + e );
            }

            delete response;
            if ( $result && $result.error ) {
                reset();
                return errorLog( $result.error );
            } else {
                return next( $result );
            }
        }
    };

    /**
     * Set text for status
     */
    var status = function(text) {
        jQuery('#multiorphan__out').html(text).removeClass('error');
    };

    /**
     * Log errors into container
     */
    var errorLog = function(text) {

        if (!text || !text.length) {
            return;
        }

        if (!jQuery('#multiorphan__errorlog').size()) {
            jQuery('#multiorphan__out').parent().append(jQuery('<div id="multiorphan__errorlog"/>'));
        }

        var msg = text.split("\n");
        for ( var int = 0; int < msg.length; int++) {

            var txtMsg = msg[int];
            txtMsg = txtMsg.replace(new RegExp("^runtime error:", "i"), "");

            if (txtMsg.length == 0) {
                continue;
            }

            jQuery('#multiorphan__errorlog').append(jQuery('<p/>').text(txtMsg.replace(new RegExp("</?.*?>", "ig"), "")));
        }
    };

    var resetErrorLog = function() {
        jQuery('#multiorphan__errorlog').remove();
    };
    
            
    /**
     * Display the loading gif
     */
    var throbberCount = 0;
    var throbber = function(on) {
        throbberCount = Math.max(0, throbberCount + (on?1:-1));
        jQuery('#multiorphan__throbber').css('visibility', throbberCount>0 ? 'visible' : 'hidden');
    };
    
    var reset = function(fullReset) {
        canBeStopped = false;
        
        // Result Object
        $currentResults = {
            
            pages: {
                linked: {},
                wanted: {},
                orphan: []
            },
            media: {
                linked: {},
                wanted: {},
                orphan: []
            }
        };

        // All pages and Media from DW
        $currentPagesAndMedia = {};

        throbber(false);
        $orphanForm.find('input[type=submit]').val(getLang('start'));
        
        if ( fullReset === true ) {
            resetErrorLog();
            $orphanForm.find('.multiorphan__result_group .header').attr('count', null);
            $orphanForm.find('.multiorphan__result_group .multiorphan__result').html('');
        }
    };
    
    var getLang = function(key) {
        return LANG.plugins.multiorphan ? LANG.plugins.multiorphan[key] : key;
    };
    
    jQuery(document).ready(init);
})();