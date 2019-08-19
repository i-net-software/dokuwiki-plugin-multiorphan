
// multiorphan Admin Plugin Script
(function($){

    var canBeStopped = false, $orphanForm = null, $currentPagesAndMedia, $currentResults;

    var ORPHANACTIONS = {

        view : function(type) {
            return {
                label: 'View',
                actionId: 'view',
                click: function() {
                    var $link = $(this);
                    if (type === 'Page' || type === 'URL') {
                        return true;
                    }
                    request({'do':'view'+type, 'link':decodeURIComponent($link.attr('elementid'))}, function(response){

                        if ( response.dialogContent ) {
                            $('<div/>').attr('id', 'multiorphan__preview_dialog').appendTo('body').dialog({
                                title:'Preview',
                                height: Math.min(700,$(window).height()-50),
                                width: Math.min(700,$(window).width()-50),
                                autoOpen:true,
                                closeOnEscape:true,
                                modal:true,
                                close: function() { 
                                    $(this).dialog('close').remove();
                                } 
                            }).html(response.dialogContent);
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
                    var $link = $(this);
                    request({'do':'delete'+type , 'link':decodeURIComponent($link.attr('elementid'))}, function(response){
                        $link.parents('.entry[elementid="'+$link.attr('elementid')+'"]').addClass('deleted disabled');
                        $link.parent('.actions').remove();
                    });
                    return false;
                }
            }
        },

        clear: function() {
            return {
                label: 'Clear',
                click: function() {
                    var $link = $(this);
                    $link.parents('.entry[elementid="'+$link.attr('elementid')+'"]').remove();
                    return false;
                }
            }
        }
    };

    var init = function() {
        $orphanForm = $('form#multiorphan').submit(loadpages);
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

            canBeStopped = false;
            $currentPagesAndMedia.stop = true;
			$orphanForm.find('button[type=submit]').text(getLang('start'));
            errorLog(getLang('request-aborted'));
            return false;
        }

        reset(true);
        canBeStopped = true;
        event.stopPropagation();
        $orphanForm.find('button[type=submit]').text(getLang('stop'));
        request({'do':'loadpages'}, function( $result ){

            // Start cycling pages
            $currentPagesAndMedia = $result;
            $currentPagesAndMedia.interval = 1;//Math.floor($currentPagesAndMedia.pages.length / 10);
            checkpagesandmedia($.makeArray($result.pages));
        });

        return false;
    };

    /**
     * Time Check while running
     */
    var prevTime = 0;
    var getTimeDifference = function(){
        var newTime = Date.now();
        var time = (newTime - (prevTime||newTime)) / 1000;
        prevTime = newTime;
        return time + "s";
    };

    /**
     * Walk the current elements Tree
     */
    var checkpagesandmedia = function(elements) {

		var throttleValue = 0;

        // Cycle pages. Media is implicite.
        var validateElement = function(result) {

            // Check if we still have elements in the elements list (cycle-list) and in the resultList (could be stopped.)
            if ( elements && elements.length && !($currentPagesAndMedia && $currentPagesAndMedia.stop) ) {
                var element = elements.pop();

                status(getLang('checking-page') + " ("+($currentPagesAndMedia.pages.length-elements.length)+"/"+($currentPagesAndMedia.pages.length)+" " + getTimeDifference() + ")" + (throttleValue > 0 ? ' <i>' + getLang('throttled') + '</i>' : '') + ":<br/>" + element);

				window.setTimeout(function() {
	                request({'do':'checkpage','id':element}, function(response) {
	                    checkResponseForWantedAndLinked(response, element);
	
	                    // Every 10 pages
	                    //if ( elements && elements.length && elements.length % $currentPagesAndMedia.interval == 0 ) {
	                        findOrphans();
	                    //}
	                }).always(validateElement);
				}, Math.max(0, throttleValue) * 1000 );
            } else {

                // All done. Check for Orphans.                
                findOrphans(true);

                // Now we can leave.
                status(getLang('checking-done'));
                reset();
            }
        };

        validateElement();
        throttleValue = parseInt( $orphanForm.find('input[name=throttle]').val() );
    };

    var buildUrl = function (id) {
        var cleanedID = decodeURIComponent(id);
        var schemeSepPos = cleanedID.indexOf('://');
        if (schemeSepPos > -1) {
            var scheme = cleanedID.substr(0, schemeSepPos);
            if (JSINFO.schemes.indexOf(scheme) > -1) {
                // we have an external url
                return cleanedID;
            }
        }

        return DOKU_BASE + 'doku.php?id=' + id;
    };

    var guiElementActions = function(actions, id, url, $insertPoint) {

        // Add actions
        var $buttonSet = $('<div/>').addClass('actions').appendTo($insertPoint);
        $.each(actions||[], function(idx, action) {
            const attrs = {
                href: url || buildUrl(id),
                elementid: id
            };

            if (action.actionId === 'view') {
                attrs.target = '_blank';
            }
            var $link = $('<a>').attr(attrs).text(action.label).appendTo($buttonSet).click(action.click);
            if ( action.process ) {
                action.process($link);
            }
        })
    };

    /**
     * Add an entry to the accordion of the according type.
     */
    var addGUIEntry = function($insertPoint, name, url, requestPage, requestPageURL, actions) {

        var id = encodeURIComponent(name);
        var $header = $insertPoint.prev('.header');
        $header.attr('count', parseInt($header.attr('count')||0)+1);

        var $appendTo = $insertPoint.find('.entry[elementid="'+id+'"] > ul');
        if ( !$appendTo.length ) {
            var $wrapper = $('<div/>').addClass('entry').attr('elementid', id).appendTo($insertPoint);
            $('<span/>').text(name).appendTo($wrapper);

            guiElementActions(actions.concat([ORPHANACTIONS.clear()]), id, url, $wrapper);

            $appendTo = $('<ul/>').appendTo($wrapper);
        }

        if ( requestPage && requestPage.length ) {
            var $pageId = $('<span>').text(requestPage);
            var $entry = $('<li>').addClass('requestPage').append($pageId).appendTo($appendTo);
            guiElementActions(actions, requestPage, requestPageURL, $entry);
        }
    };

    /**
     * Build up the structure for linked and wanted pages
     */
    var checkResponseForWantedAndLinked = function(response, requestPage) {

        // Fill the $currentResults object with information.
        var checkResponse = function( name, url, amount, object, $output, actions ) {

            var checkId = name.split( '#', 2 ).shift();
            var checkPoint = amount == 0 ? object.wanted : object.linked;
            if ( !Array.isArray(checkPoint[checkId]) ) {
                checkPoint[checkId] = [];
            }

            if ( checkPoint[checkId].indexOf(requestPage) == -1 ) {
                checkPoint[checkId].push(requestPage);
            }

            addGUIEntry($output.find('.multiorphan__result.' + (amount == 0 ? 'wanted' : 'linked')), name, url, requestPage, (response||{}).href, actions);
        };

        var $pagesOut = $orphanForm.find('.multiorphan__result_group.pages');
        var $mediaOut = $orphanForm.find('.multiorphan__result_group.media');
        $.each((response||{}).pages||[], function(page, data){
            checkResponse(page, data.href, data.amount, $currentResults.pages, $pagesOut, [ORPHANACTIONS.view('Page')]);
        });
        $.each((response||{}).urls||[], function(page, data){
            checkResponse(page, data.href, data.amount, $currentResults.pages, $pagesOut, [ORPHANACTIONS.view('URL')]);
        });
        $.each((response||{}).media||[], function(media, data){
            checkResponse(media, data.href, data.amount, $currentResults.media, $mediaOut, [ORPHANACTIONS.view('Media')]);
        });
    };

    /**
     * walk all linked pages and remove them from the ones that actually exist in the wiki
     * assign the result to the array.
     */
    var findOrphans = function(processCompleted) {

        // Sort out all not 
        var orphaned = function(linked, original) {

            if ( !original || !original.length ) return [];
            var orphaned = $.makeArray(original); // make copy

            $.each(linked, function(link) {
                if ( (idx = orphaned.indexOf(link)) > -1 ) {
                    orphaned.splice(idx, 1);
                }
            });

            return orphaned;
        };

        status(getLang('checking-orphans'));
        $currentResults.pages.orphan = orphaned($currentResults.pages.linked, $currentPagesAndMedia.pages);
        $currentResults.media.orphan = orphaned($currentResults.media.linked, $currentPagesAndMedia.media);

        var $pagesOut = $orphanForm.find('.multiorphan__result_group.pages .multiorphan__result.orphan');
        var $mediaOut = $orphanForm.find('.multiorphan__result_group.media .multiorphan__result.orphan');

        $orphanForm.find('.multiorphan__result_group .orphan.header').attr('count', null);

        if ( processCompleted == true ) {
            $orphanForm.find('.multiorphan__result_group .multiorphan__result.orphan').html('');
            $.each($currentResults.pages.orphan, function(idx, orphan){
                addGUIEntry($pagesOut, orphan, null, null, null, [ORPHANACTIONS.view('Page'), ORPHANACTIONS.delete('Page')]);
            });        

            $.each($currentResults.media.orphan, function(idx, orphan){
                addGUIEntry($mediaOut, orphan, null, null, null, [ORPHANACTIONS.view('Media'), ORPHANACTIONS.delete('Media')]);
            });        
        } else {
            $orphanForm.find('.multiorphan__result_group .multiorphan__result.orphan').append($('<div/>').html(getLang('please-wait-orphan')));
            $pagesOut.prev('.header').attr('count', $currentResults.pages.orphan.length);
            $mediaOut.prev('.header').attr('count', $currentResults.media.orphan.length);
        }
    };

    /**
     * Send a request to the plugin.
     */
    var request = function(data, success) {
        data['ns']     = $orphanForm.find('input[name=ns]').val();
        data['filter'] = $orphanForm.find('input[name=filter]').val();
        data['sectok'] = $orphanForm.find('input[name=sectok]').val();

        if ( $orphanForm.find('input[name=purge]').is(':checked') ) {
            data['purge'] = true
        }

        if ( $orphanForm.find('input[name=checkExternal]').is(':checked') ) {
            data['checkExternal'] = true
        }

        if ( $orphanForm.find('input[name=includeWindowsShares]').is(':checked') ) {
            data['includeWindowsShares'] = true
        }

        if ( $orphanForm.find('input[name=includeHidden]').is(':checked') ) {
            data['includeHidden'] = true
        }

        // data['type']   = $orphanForm.find('select[name=type]').val() || 'both';
        data['call']   = 'multiorphan';

        throbber(true);
        return $.post(DOKU_BASE + 'lib/exe/ajax.php', data, handleResponse(success)).always(function(){
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
                $result = $.parseJSON(response);
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
        $('#multiorphan__out').html(text).removeClass('error');
    };

    /**
     * Log errors into container
     */
    var errorLog = function(text) {

        if (!text || !text.length) {
            return;
        }

        if (!$('#multiorphan__errorlog').size()) {
            $('#multiorphan__out').parent().append($('<div id="multiorphan__errorlog"/>'));
        }

        var msg = text.split("\n");
        for ( var int = 0; int < msg.length; int++) {

            var txtMsg = msg[int];
            txtMsg = txtMsg.replace(new RegExp("^runtime error:", "i"), "");

            if (txtMsg.length == 0) {
                continue;
            }

            $('#multiorphan__errorlog').append($('<p/>').text(txtMsg.replace(new RegExp("</?.*?>", "ig"), "")));
        }
    };

    var resetErrorLog = function() {
        $('#multiorphan__errorlog').remove();
    };

    /**
     * Display the loading gif
     */
    var throbberCount = 0;
    var throbber = function(on) {
        throbberCount = Math.max(0, throbberCount + (on?1:-1));
        $('#multiorphan__throbber').css('visibility', throbberCount>0 ? 'visible' : 'hidden');
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
        $orphanForm.find('button[type=submit]').text(getLang('start'));

        if ( fullReset === true ) {
            resetErrorLog();
            $orphanForm.find('.multiorphan__result_group .header').attr('count', null);
            $orphanForm.find('.multiorphan__result_group .multiorphan__result').html('');
        }
    };

    var getLang = function(key) {
        return LANG.plugins.multiorphan ? LANG.plugins.multiorphan[key] : key;
    };

    $(document).ready(init);
})(jQuery);
