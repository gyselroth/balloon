/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */
"use strict";

var balloon = {
    /**
     * Debug mode
     */
    DEBUG_SIMULATOR: {
        idevice:    false,
        mobileport: false,
        touch:      false,
    },

        
    /**
     * Version
     */
    BALLOON_VERSION: '1.0.12',


    /**
     * API Version
     */
    BALLOON_API_VERSION: 1,


    /**
     * Prompt warning if open large file
     */
    EDIT_TEXT_SIZE_LIMIT: 2194304,


    /**
     * Chunk upload size (4MB Chunk)
     */
    BYTES_PER_CHUNK: 4194304,


    /**
     * Chunk upload size (1GB)
     */
    MAX_FILE_UPLOAD_SIZE: 1073741824,


    /**
     * API Base url
     *
     */
    base: '/api',


    /**
     * Datasource
     * 
     * @var HierarchicalDataSource
     */
    datasource: null,


    /**
     * Previous selected node
     * 
     * @var object
     */
    previous: null, 


    /**
     * Last selected node
     * 
     * @var object
     */
    last: null,


    /**
     * Upload manager
     * 
     * @var object
     */
    upload_manager: null,

    
    /**
     * Is initialized?
     *
     * @var bool
     */
    initialized: false,


    /**
     * Last html5 pushState() url
     *
     * @var string
     */
    history_last_url: null,


    /**
     * Selected nodes action
     *
     * @var object
     */
    selected_action: {
        command: null,
        nodes: null,
        collection: null
    },


    /**
     * Quota usage
     * 
     * @var object
     */
    quota: {},


    /**
     * Init file browsing
     *
     * @return void
     */
    init: function() {
        if(balloon.isInitialized()) {
            balloon.resetDom();
        }
        else {
            this.base = this.base+'/v'+this.BALLOON_API_VERSION;
        }

        balloon.kendoFixes();

        var $fs_browser_layout = $("#fs-browser-layout");
        if($fs_browser_layout.data('kendoSplitter') === undefined) {
            $fs_browser_layout.kendoSplitter({
                panes: [
                    { collapsible: true, size: "13%", min: "13%" },
                    { collapsible: true, size: "50%", min: "25%" },
                    { collapsible: true, size: "37%", min: "35%", collapsed: true },
                ],
                scrollable: false,
                collapsed: true,
            });
        }

        $("#fs-menu-left").off('click').on('click', 'li', balloon.menuLeftAction);   
        $("#fs-identity").off('click').on('click', 'li', balloon._menuRightAction);   
    
        balloon.createDatasource();
    	balloon.initCrumb();
    	
    	$("#fs-browser-action").find(".fs-action-element").unbind('click').click(balloon.doAction);
    	$("#fs-browser-select-action").find(".fs-action-element").unbind('click').click(balloon.doAction);
        $("#fs-browser-header").find("> div").unbind('click').click(balloon._sortTree);

        $(document).unbind('drop').on('drop', function(e) {
            e.stopPropagation();
            e.preventDefault();
        })
        .unbind('dragover').on('dragover', function(e) {
            e.stopPropagation();
            e.preventDefault();
        })
        .unbind('keyup').bind('keyup', balloon._treeKeyup);

        var menu = balloon.getURLParam('menu');
        if(menu != 'cloud' && menu !== null) {
            balloon.menuLeftAction(menu);
        }

        var options  = {
	    	dataSource:    balloon.datasource,
	    	dataTextField: "name",
	      	dragAndDrop:   true,
            dragstart:     balloon._treeDragstart,
            dragend:       balloon._treeDragend,
	    	drag:          balloon._treeDrag,
            drop:          balloon._treeDrop,
            dataBound:     balloon._treeDataBound,
            select:        balloon._treeSelect,
            messages: {
                loading: "",
            }
        };

        var $fs_browser_tree = $('#fs-browser-tree');
    	$fs_browser_tree.kendoTreeView(options);

        if(balloon.isTouchDevice()) {
            $fs_browser_tree
                .off('touchstart', '.k-in').on('touchstart', '.k-in', balloon._treeTouch)
                .off('touchend', '.k-in').on('touchend', '.k-in', balloon._treeTouchEnd)
                .off('touchmove', '.k-in').on('touchmove', '.k-in', balloon._treeTouchMove);
        } 

        if(!balloon.isTouchDevice() && balloon.isMobileViewPort()) {
            $fs_browser_tree
                .off('dblclick', '.k-in').on('click', '.k-in', balloon._treeDblclick);
        } else {
            $fs_browser_tree
                .off('click', '.k-in').on('click', '.k-in', balloon._treeClick)
                .off('dblclick', '.k-in').on('dblclick', '.k-in', balloon._treeDblclick);
        }

        balloon.displayQuota();                

        $('#fs-action-search').find('input:text')
          .unbind('focus').bind('focus', function(){balloon.menuLeftAction('search')})
          .unbind('keyup').keyup(balloon._searchKeyup);

        $('#fs-search-button').unbind('click').bind('click', function(e) {
            if($(window).width() <= 800) {
                balloon.menuLeftAction('search');
            } else {
                balloon.search($(this).parent().find('input').val());
            }
        });

        $('#fs-search-reset-button').unbind('click').click(balloon._searchResetClick);

        $('#fs-namespace').unbind('dragover').on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if(balloon.isSearch()) {
                return;
            }

            $fs_browser_tree.addClass('fs-file-dropable');
            var $parent = $(e.target).parents('.k-item'),
                $target = $parent.find('.k-in');

            if($parent.attr('fs-type') !== 'folder') {
                return;
            }
            
            if($target.parents('#fs-browser-tree').length !== 0) {
                $target.addClass('fs-file-drop');
            }        
         })
        .unbind('dragleave').on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $fs_browser_tree.removeClass('fs-file-dropable');
            $fs_browser_tree.find('.fs-file-drop').removeClass('fs-file-drop');
        });

        $(window).unbind('popstate').bind('popstate', balloon._statePop);
        balloon.buildCrumb(balloon.getURLParam('collection'));

        $('#fs-title').unbind('click').bind('click', function(){balloon.menuLeftAction('cloud')});
        
        balloon.showHint();
        balloon.initialized = true;
    },


    /**
     * show hint
     * 
     * @return void
     */
    showHint: function() {
        var disabled = localStorage.noHint;
        if(disabled == "true" || balloon.getURLParam('menu') !== null) {
            return;
        }

        var $fs_hint_win = $('#fs-hint-window');
        var $k_hint = $fs_hint_win.kendoWindow({
             title: $fs_hint_win.attr('title'),
             resizable: false,
             modal: true,
             open: function() {
                balloon._showHint();
                setTimeout(function(){
                    $fs_hint_win.find('input[name=next]').focus();
                }, 900);
                
                var $k_that = this;
                $fs_hint_win.find('input[name=stop]').off('click').on('click', function(){
                    localStorage.noHint = true;
                    $k_that.close();
                });
                
                $fs_hint_win.find('input[name=next]').focus().off('click').on('click', function(){
                    balloon._showHint();
                });
             }
        }).data('kendoWindow').center().open();

        $fs_hint_win.unbind('keydown').keydown(function(e) {
            if(e.keyCode === 27) {
                e.stopImmediatePropagation();
                $k_hint.close();
            }
        });
    },


    /**
     * show hint
     * 
     * @return void
     */
    _showHint: function() {
        var total = 25,
            hint  = Math.floor(Math.random() * total) + 1,
            $div  = $('#fs-hint-window-content');

        $div.html(i18next.t("hint.hint_"+hint));
    },


    /**
     * Kendo fixes & enhancements
     *
     * @return void
     */
    kendoFixes: function() {
        //disable inbuilt navigate() event, conflict with balloon.fileUpload()
        window.kendo.ui.TreeView.fn._keydown = function(){};

        window.kendo.ui.Window.fn._keydown = function (originalFn) {
            return function (e) {
                if('keydown' in this.options) {
                    this.options.keydown.call(this, e);
                } else {
                    originalFn.call(this, e);
                }
            };
        }(kendo.ui.Window.fn._keydown);

        window.kendo.ui.AutoComplete.fn.search = function (word) {
            var that = this,
            options = that.options,
            ignoreCase = options.ignoreCase,
            separator = options.separator,
            length;
         
            word = word || that.value();
         
            that._current = null;
         
            clearTimeout(that._typing);
         
            if (separator) {
                word = wordAtCaret(caretPosition(that.element[0]), word, separator);
            }
         
            length = word.length;
         
            if (!length && !length == 0) {
                that.popup.close();
            } else if (length >= that.options.minLength) {
                that._open = true;
         
                that.dataSource.filter({
                    value: ignoreCase ? word.toLowerCase() : word,
                    operator: options.filter,
                    field: options.dataTextField,
                    ignoreCase: ignoreCase
                });
            }
        };
    },


    /**
     * Check if client is initialized
     *
     * @return bool
     */
    isInitialized: function() {
        return balloon.initialized;
    },


    /**
     * Ajax request
     * 
     * @param   object options
     * @return  object
     */
    xmlHttpRequest: function(options) {
        if(options.beforeSend === undefined) {
            options.beforeSend = balloon.showLoader;
        } else {
            var beforeSend = options.beforeSend;
            options.beforeSend = function(jqXHR, settings) {
                balloon.showLoader();
                beforeSend(jqXHR, settings);
            };
        }

        var complete = options.complete;
        options.complete = function(jqXHR, textStatus) {
            balloon.hideLoader();
            
            var valid = ['POST', 'PUT', 'DELETE'],
                show  = (valid.indexOf(options.type) > -1);
            
            if(show && jqXHR.status.toString().substr(0, 1) === '2') {
                balloon.showResponseTick();
            }

            if(complete !== undefined) {
                complete(jqXHR, textStatus);
            }
        };

        if(options.error === undefined) {
            options.error = balloon.displayError;
        }
        
        if(options.cache === undefined) {
            options.cache = false;
        }

        options.headers = {
            'X-Client': 'Webinterface|'+balloon.BALLOON_VERSION
        }
        
        return login.xmlHttpRequest(options);
    },


    /**
     * Show response tick
     *
     * @return void
     */
    showResponseTick: function() {
        var $tick = $('#fs-request-success').fadeIn(300);

        setTimeout(function() {
            $tick.fadeOut(600)
        }, 1000)
    },


    /**
     * Kendo tree: dragstart event
     * 
     * @param   object e
     * @return  void
     */
    _treeDragstart: function(e) {
        if((balloon.isSearch() && balloon.getCurrentCollectionId() === null) || balloon.touch_move === true) {
            e.preventDefault();
            return;
        }

        $('#fs-browser-tree').find('li[fs-type=folder]').addClass('fs-file-dropable');
        $('#fs-browser-top').find('li').addClass('fs-file-dropable');
        $('#fs-upload').addClass('fs-file-dropable');

        if(balloon.isMultiSelect()) {
            var clue = $('.k-drag-clue').html();

            clue = clue.substr(0, clue.search('</span>')+7);
            var names = '';
            for(var n in balloon.multiselect) {
                if(names.length != 0) {
                    names += '; ';
                }
                
                names += balloon.multiselect[n].name;
            }
            
            if(names.length > 100) {
                names = name.substr(0, 100)+'...';
            }
            
            clue += names;
            $('.k-drag-clue').html(clue);
        }
    },
    

    /**
     * Keyup
     * 
     * @param   object e
     * @return  void
     */
    _treeKeyup: function(e) {
        e.preventDefault();

        if($('.k-window').is(':visible') || $('input,select,textarea').is(':focus')) {
            return;
        }

        //keyup/keydown node selection
        if(e.keyCode === 38 || e.keyCode === 40) {
            if($("#fs-share-collection").find("input[name=share_role]").is(':focus') 
            || $('#fs-properties-meta-tags').hasClass('fs-select-tags')) {
                return;
            }
            
            var next    = balloon.datasource._data.indexOf(balloon.last);
            var current = next;
    
            if(e.keyCode === 40) {
                next++;

                if(next >= balloon.datasource._data.length) {
                    next--;
                }
            }
            else if(e.keyCode === 38) {
                next--;
                if(0 > next) {
                    next = 0;
                }
            }
            
            if(next == current) {
                return;
            }
            
            var $fs_browser_tree = $("#fs-browser-tree"),
                $k_tree = $fs_browser_tree.data('kendoTreeView'), 
                $node;

            if(balloon.datasource._data[next].id == '_FOLDERUP') {
                $node = $fs_browser_tree.find('.k-first');
            }
            else {
                $node = $fs_browser_tree.find('.k-item[fs-id='+balloon.datasource._data[next].id+']');            
            }
            
            $k_tree.select($node);
            $k_tree.trigger('select', {node: $node});
            
            return;
        }

        var $fs_namespace_input = $("#fs-namespace").find("input");
        if(e.keyCode === 13 && !$fs_namespace_input.is(":focus") && !$("#fs-prompt-window").is(':visible') && !$('#fs-edit-live').is(':visible')) {
            balloon._treeDblclick();
        }

        if($fs_namespace_input.is(":focus")) {
            return;
        }
        
        balloon._keyAction(e);
    },
      

    /**
     * Trigger action using keyboard
     * 
     * @param  object e
     * @return void
     */
    _keyAction: function(e) {  
        switch(e.keyCode) {
            //delete/shift+delete
            case 46:
                if(balloon.last !== undefined) {
                    if(e.shiftKey) {
                        balloon.deletePrompt(balloon.getSelected(balloon.last), true);
                    } else {
                        balloon.deletePrompt(balloon.getSelected(balloon.last));
                    }
                }
            break;

            //cut node (shift+x)
            case 88:
                if(e.shiftKey && !(balloon.isSearch() && balloon.getCurrentCollectionId() === null)) {
                    balloon.doAction('cut');
                }
            break;
            
            //copy node (shift+c)
            case 67:
                if(e.shiftKey && !(balloon.isSearch() && balloon.getCurrentCollectionId() === null)) {
                    balloon.doAction('copy');
                }
            break;
            
            //paste node (shift+v)
            case 86:
                if(e.shiftKey && !(balloon.isSearch() && balloon.getCurrentCollectionId() === null)) {
                    balloon.doAction('paste');
                }
            break;
        
            //add folder (shift+n)
            case 78:
                if(e.shiftKey && !(balloon.isSearch() && balloon.getCurrentCollectionId() === null)) {
                    balloon.doAction('folder');
                }
            break;
            
            //add file (shift+a)
            case 65:
                if(e.shiftKey && !(balloon.isSearch() && balloon.getCurrentCollectionId() === null)) {
                    balloon.doAction('file');
                }
            break;
            
            //upload (shift+u)
            case 85:
                if(e.shiftKey && !(balloon.isSearch() && balloon.getCurrentCollectionId() === null)) {
                    balloon.doAction('upload');
                }
            break;
            
            //download (shift+d)
            case 68:
                if(e.shiftKey && balloon.last !== undefined) {
                    balloon.doAction('download');
                }
            break;
            
            //download (shift+r)
            case 82:
                if(e.shiftKey && balloon.last !== undefined) {
                    balloon.doAction('restore');
                }
            break;
            
            //rename node (F2)        
            case 113:
                if(balloon.last !== undefined) {
                    balloon.initRenameBrowser(balloon.last);
                }
            break;
        }
    },


    /**
     * Kendo tree: drag event
     * 
     * @param   object e
     * @return  void
     */
    _treeDrag: function(e) {
        var src = balloon.datasource.getByUid($(e.sourceNode).attr('data-uid')),
            $drop_target = $(e.dropTarget),
            $dest;

        if(src == undefined || balloon.isSystemNode(src)) {
            e.setStatusClass("k-denied");
            return;
        }

        if($drop_target.attr('fs-id') != null) {
            $dest = $drop_target;
        } else if($drop_target.parent().attr('fs-id') != null) {
            $dest = $drop_target.parent();
        }
        
        if(!$drop_target.parents('li.k-item').hasClass('fs-file-dropable') &&
        !($drop_target.hasClass('fs-file-dropable') || $drop_target.parent().hasClass('fs-file-dropable')) 
          || (balloon.isSearch() && balloon.getCurrentCollectionId() === null)) {
            e.setStatusClass("k-denied");
            return;
        } else if($dest != undefined) {
            e.setStatusClass("k-add");
            return;
        }
    },


    /**
     * Kendo tree: drag end
     * 
     * @param   object e
     * @return  void
     */
    _treeDragend: function(e) { 
        $('#fs-browser-tree').find('.k-item').removeClass('fs-file-dropable');
        $('#fs-browser-top').find('li').removeClass('fs-file-dropable');
        $('#fs-upload').removeClass('fs-file-dropable');
    },
    
    
    /**
     * Kendo tree: drop event
     * 
     * @param   object e
     * @return  void
     */
    _treeDrop: function(e) {
        $('#fs-browser-tree').find('.k-item').removeClass('fs-file-dropable');
        $('#fs-browser-top').find('li').removeClass('fs-file-dropable');
        $('#fs-upload').removeClass('fs-file-dropable');
        
        if(balloon.isSearch() && balloon.getCurrentCollectionId() === null) {
            return;
        }
        
        e.preventDefault();
        
        var src        = balloon.datasource.getByUid($(e.sourceNode).attr('data-uid')),
            dest       = balloon.datasource.getByUid($(e.destinationNode).attr('data-uid')),
            src_parent = $(e.sourceNode).parents('.k-item').first().attr('data-uid');
            
        if(src == undefined || balloon.isSystemNode(src)) {
            e.setValid(false); return;
        }

        if(typeof(dest) == 'object') {
            var dest_parent = $(e.destinationNode).parents('.k-item').first().attr('data-uid');
            
            var c1 = src_parent == dest_parent && e.dropPosition != 'over',
                c2 = e.dropPosition == 'over' && dest.directory == false,
                c3 = src.id == dest.id,
                c4 = balloon.isSystemNode(src) && !dest.id == '_FOLDERUP';
            
            if(c1 || c2 || dest.id == undefined || dest.id == '' || c3 || c4) {
                e.setValid(false); return;
            }
            
            if(dest.id == '_FOLDERUP') {
                dest = balloon.getPreviousCollectionId();
            }
            
            if(e.dropPosition != 'over') {
                dest = dest_parent;
            }
        }
        else if(dest === undefined) {
            if($(e.dropTarget).attr('fs-id') != null) {
                dest = $(e.dropTarget).attr('fs-id');
            } else if($(e.dropTarget).parent().attr('fs-id') != null) {
                dest = $(e.dropTarget).parent().attr('fs-id');
            }
            
            //root folder
            if(dest === '') {
                dest = null;
            }
                        
            if(dest === undefined || dest == balloon.getCurrentCollectionId()) {
                e.setValid(false); return;
            }
        }

        balloon.move(balloon.getSelected(src), dest);
    },
    
    
    /**
     * Kendo tree: dataBound event
     * 
     * @param   object e
     * @return  void
     */
    _treeDataBound: function(e) {
        balloon.resetDom(['multiselect', 'action-bar']);

        var actions = ['file', 'folder', 'upload', 'refresh', 'filter'];
        
        if(balloon.selected_action.command !== null) {
            actions.push('paste');
        }
       
        if(!balloon.isSearch() || balloon.getCurrentCollectionId() !== null) {
            balloon.showAction(actions);
        } else {
            balloon.showAction(['refresh']);
        }
           
        var selected = balloon.getURLParam('selected[]'),     
            $fs_browser_tree = $("#fs-browser-tree"),
            $k_tree = $fs_browser_tree.data('kendoTreeView'),
            rename_match = false;
          
        $fs_browser_tree.find('.k-item').each(function() {
            var $that = $(this), node;
            node = balloon.datasource.getByUid($(this).attr('data-uid'));
            
            if(balloon.isSystemNode(node)) {
                if(balloon.id(node) == '_FOLDERUP') {
                    $that.attr('fs-type', 'folder');
                    balloon.fileUpload(balloon.getPreviousCollectionId(), $that);
                }
                return;
            }

            if(node.meta != undefined && node.meta.tags != undefined) {
                node.meta.tags = $.makeArray(node.meta.tags);
            }

            if(node.deleted !== false) {
                 $that.addClass('fs-node-deleted');
            }
            
            var order = ['name', 'shared', 'deleted', 'sharelink', 'tag', 'size', 'changed'];
            $that.attr('fs-id', balloon.id(node));
            
            if(node.directory === true) {
                $that.attr('fs-type', 'folder');
            } else {
                $that.attr('fs-type', 'file');
            }
            
            var $that_k_in = $that.find('.k-in');
            $that_k_in.first().find('.fs-browser-meta').remove();
            $that_k_in.first().append('<div class="fs-browser-meta"></div>');
            var html_children = [];
            
            for(var prop in order) {
                switch(order[prop]) {
                    case 'sharelink':
                        if(node.sharelink === true) {
                            html_children.push('<div class="fs-node-shared fs-icon fs-i-hyperlink"></div>');
                        } else {
                            html_children.push('<div>&nbsp;</div>');
                        }
                    break;

                    case 'changed':
                        var ts    = node.changed.sec*1000,
                            date  = new Date(ts),
                            since = balloon.timeSince(date);

                        html_children.push('<span class="fs-meta-info">'+since+'</span>');
                    break;

                    case 'deleted':
                        if(node.deleted !== false) {
                            html_children.push('<div class="fs-node-shared fs-icon fs-i-trash"></div>');
                        } else {
                            html_children.push('<div>&nbsp;</div>');
                        }
                    break;

                    case 'size':
                        if(node.directory) {
                            html_children.push('<span class="fs-meta-info">'+i18next.t("view.prop.data.childcount", {
                                count: node.size,
                            })+'</span>');
                    
                        } else {
                            html_children.push('<span class="fs-meta-info">'+balloon.getReadableFileSizeString(node.size)+'</span>');
                        }
                    break;
                    
                    case 'name':
                        var ext = balloon.getFileExtension(node);
                        if(ext != null && !node.directory) {
                            var sprite = $that.find('.k-sprite').addClass('fs-icon')[0].outerHTML;
                            var name = '<span class="fs-browser-name">'+node.name.substr(0, node.name.length-ext.length-1)+'</span> <span class="fs-ext">[.'+ext+']</span>';
                            $that_k_in.html(sprite+name+$(this).find('.k-in > .fs-browser-meta')[0].outerHTML);
                        }
                        else {
                           var sprite = $(this).find('.k-sprite').addClass('fs-icon')[0].outerHTML;
                           $that_k_in.html(sprite+'<span class="fs-browser-name">'+node.name+'</span>'+$that.find('.fs-browser-meta')[0].outerHTML);
                        }
                    break;

                    case 'tag':
                        var color = node.meta.color,
                            color_tag;
                        if(typeof color != "undefined"){
                            color_tag = '<span style="background-color: '+color+';" class="fs-color-tag"></span>';
                        }
                        else{
                            color_tag = '<span style="background-color: transparent;" class="fs-color-tag"></span>';
                        }
                         
                        html_children.push(color_tag);

                    break;
                }

                $that_k_in.find('.fs-browser-meta').html(html_children.join(''));
            }

            if(node.directory) {
                balloon.fileUpload(node);
            }
            
            if(balloon.added_rename == balloon.id(node)) {
                rename_match = node;
            }
            
            if(selected !== null && typeof(selected) === 'object' && selected.indexOf(balloon.id(node)) > -1) {
                if(selected.length > 1) {
                    balloon.multiSelect(node);
                }

                var dom_node = $fs_browser_tree.find('.k-item[fs-id='+balloon.id(node)+']');
                $k_tree.select(dom_node);
                $k_tree.trigger('select', {node: dom_node});
            }
        });
        
        if(rename_match !== false) {
            var dom_node = $('li[data-uid='+rename_match.uid+']');
            $k_tree.select(dom_node);
            $k_tree.trigger('select', {node: dom_node});

            balloon.initRenameBrowser(rename_match);
            balloon.added_rename = null;
            rename_match = false;
        }

        balloon.fileUpload(balloon.getCurrentCollectionId(), $('#fs-layout-left'));
    },
    
    
    /**
     * Kendo tree: select event
     * 
     * @param   object e
     * @return  void
     */
    _treeSelect: function(e) {
        $('.k-in').removeClass('fs-rename');

        var id   = $(e.node).attr('data-uid'),
            node = balloon.datasource.getByUid(id);

        if(balloon.id(node) === balloon.id(balloon.last)) {
            balloon.last = node;
            return;
        }
        
        balloon.resetDom(
            ['properties','preview','view-bar', 'action-bar',
            'history','share-collection','share-link'
        ]);

        var copy     = balloon.last;
        balloon.last     = node;

        if(!balloon.isSystemNode(copy)) {
            balloon.previous = copy;
        }
        
        var actions = ['download', 'delete', 'refresh'];
        if(!balloon.isSearch() || balloon.getCurrentCollectionId() !== null) {
            actions.push('file', 'folder', 'upload', 'cut', 'copy', 'filter');
        }
        if(balloon.last.deleted !== false) {
            actions.push('restore', 'delete');
        }
        if(balloon.selected_action.command !== null) {
            actions.push('paste');
        }        

        balloon.showAction(actions);            
        $('.fs-action-select-only').css('display','inline-block');

        if(balloon.isSystemNode(node) || balloon.isMultiSelect()) {
             e.preventDefault();
             return;
        }

        if(typeof(balloon.last_click_event) == 'object' && balloon.last_click_event.ctrlKey == false 
        && balloon.last_click_event.metaKey == false && balloon.last_click_event.shiftKey == false) {
            balloon.multiSelect();
        }

        $(e.node).find('.k-in').addClass('k-state-selected');
        
        balloon.resetDom([
            'selected',
            'properties',
            'preview',
            'multiselect',
            'view-bar',
            'history',
            'share-collection',
            'share-link',
        ]);
        
        balloon.displayProperties(node, 
            {
                data: {
                    name: node.name,
                }
            }  
        );

        var view  = balloon.getURLParam('view');

        if(balloon.previous !== null && balloon.previous.id !== balloon.last.id 
         || balloon.previous !== null && view === null || balloon.previous === null && view === null) {
            view = 'preview';
        }        
        
        balloon.switchView(view);
        $('#fs-properties-name').show();
       
        var $fs_view_bar_li = $('#fs-view-bar').find('li'); 
        $fs_view_bar_li.removeClass('fs-view-bar-active');
        $('#fs-view-'+view).addClass('fs-view-bar-active');

        if(balloon.last.directory) {
            if(balloon.last.deleted !== false) {
                balloon.showView(['preview', 'properties', 'events', 'advanced']);
            } else if(balloon.last.shared === false && balloon.last.share === false || (balloon.last.shared === true && balloon.last.reference === false)) {
                balloon.showView(['preview', 'properties', 'share-collection', 'share-link', 'events', 'advanced']);
            } else  {
                balloon.showView(['preview', 'properties', 'share-link', 'events', 'advanced']);
            }
        } else {
            if(balloon.last.deleted !== false) {
                balloon.showView(['preview', 'properties', 'history', 'events', 'advanced']);
            } else {
                balloon.showView(['preview', 'properties', 'history', 'share-link', 'events', 'advanced']);
            }
        }
        
        $fs_view_bar_li.unbind('click').click(function() {
            var $that = $(this),
                action = $that.attr('id').substr(8);
            
            $fs_view_bar_li.removeClass('fs-view-bar-active');
            $that.addClass('fs-view-bar-active');

            if(balloon.getViewName() != action) {
                balloon.switchView(action);      
            }   
        });

        balloon.pushState();
    },


    /**
     * Pop state
     *
     * @param   object e
     * @return  void
     */ 
    _statePop: function(e) {
        balloon.resetDom('multiselect');
        balloon.resetDom('breadcrumb');
        balloon.previous = null;
        balloon.last = null;

        var view       = balloon.getURLParam('view'),
            collection = balloon.getURLParam('collection'),
            selected   = balloon.getURLParam('selected[]'),
            menu       = balloon.getURLParam('menu');

        if(collection !== null) {
            balloon.menuLeftAction(menu, false);
            balloon.refreshTree('/collection/children', {id: collection}, null, {nostate: true});
        } else {
            balloon.menuLeftAction(menu);
        }

        if(e.originalEvent.state === null) {
            balloon.buildCrumb(collection);
        } else {
            balloon._repopulateCrumb(e.originalEvent.state.parents);
        }
    },


    /**
     * Build breadcrumb with parents
     * 
     * @param   string collection
     * @return  void
     */
    buildCrumb: function(collection) {
        if(collection === null) {
            return;
        }

        balloon.xmlHttpRequest({
            url: balloon.base+'/node/parents',
            type: 'GET',
            dataType: 'json',
            data: {
                id: collection,
                attributes: ['id', 'name'],
                self: true
            },
            success: function(body) {
                balloon._repopulateCrumb(body.data.reverse());
            },
        });
    },
    

    /**
     * Rebuild breadcrum with existing node list
     *
     * @param   array nodes
     * @return  void
     */
    _repopulateCrumb: function(nodes) {
        balloon.resetDom(['breadcrumb-home','breadcrumb-search']);
        for(var node in nodes) {
            balloon.addCrumbRegister(nodes[node]);
        }
    },


    /**
     * Push state
     *
     * @param  bool replace
     * @param  bool reset_selected
     * @return void
     */
    pushState: function(replace, reset_selected) {
        if (!window.history || !window.history.pushState) {
            return true;
        }

        var list = [];
        var selected = [];

        if(balloon.getSelected() === null) {
            return;
        }  

        if(reset_selected !== true) {
            if(balloon.isMultiSelect()) {
                selected = balloon.getSelected(); 
            } else {
                selected.push(balloon.getSelected());
            }

            for(var node in selected) {
                list.push(selected[node].id);
            }  
        } else {
            balloon.last = null;
            balloon.previous = null;
        }

        var exec;
        if(replace === true) {
            exec = 'replaceState';
        } else {
            exec = 'pushState';
        }
        
        var url = '?'+balloon.param('menu', balloon.getMenuName())+'&'+balloon.param('menu')+'&'+balloon.param('collection', balloon.getCurrentCollectionId())+'&'
                 +balloon.param('selected', list)+'&'+balloon.param('view', balloon.getViewName());

        if(balloon.history_last_url !== url) {
            window.history[exec](
                {parents: balloon.getCrumbParents()}, 
                balloon.getCurrentCollectionId(), 
                url
            );

            balloon.history_last_url = url;
        }
    },



    /**
     * Read query string param
     *
     * @param   string key
     * @param   string target
     * @return  mixed
     */
    getURLParam: function(key, target) {
        var values = [];
        if(!target) {
            target = document.location.href;
        }

        key = key.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");

        var pattern = key + '=([^&#]+)';
        var o_reg = new RegExp(pattern,'ig');
        while(true) {
            var matches = o_reg.exec(target);
            if(matches && matches[1]) {
                values.push(matches[1]);
            }
            else {
                break;
            }
        }
        
        if(!values.length) {
            return null;   
        }
        else if(key.slice(-4) == '\\[\\]') {
            return values;
        } 
        else {
           return values.length == 1 ? values[0] : values;
        }
    },


    /**
     * Display user profile
     * 
     * @return void
     */
    displayUserProfile: function() {
        balloon.resetDom('user-profile');
        
        var $fs_profile_win = $('#fs-profile-window');    
        $fs_profile_win.kendoWindow({
             title: $fs_profile_win.attr('title'),
             resizable: false,
             modal: true,
             height: '60%',
             width: '40%',
             open: function() {
                var $fs_quota_usage = $('#fs-profile-quota-bar'),
                    $k_progress = $fs_quota_usage.data('kendoProgressBar');

                if($k_progress == undefined) {
                    $k_progress = $fs_quota_usage.kendoProgressBar({
                        type: "percent",
                        value: 1,
                        animation: {
                            duration: 1500
                        }
                    }).data("kendoProgressBar");
                }
    
                balloon.xmlHttpRequest({
                    url: balloon.base+'/user/quota-usage',
                    type: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        var percentage = Math.round(data.data.used/data.data.hard_quota*100);
                        $k_progress.value(percentage);

                        if(percentage >= 90) {
                            $fs_quota_usage.find('.k-state-selected').addClass('fs-quota-high');
                        } else {
                            $fs_quota_usage.find('.k-state-selected').removeClass('fs-quota-high');
                        }

                        var used = balloon.getReadableFileSizeString(data.data.used),
                            max  = balloon.getReadableFileSizeString(data.data.hard_quota),
                            free = balloon.getReadableFileSizeString(data.data.hard_quota - data.data.used); 
                    
                        $('#fs-profile-quota-used').find('td').html(used);
                        $('#fs-profile-quota-max').find('td').html(max);
                        $('#fs-profile-quota-left').find('td').html(free);
                    },
                });

                balloon.xmlHttpRequest({
                    url: balloon.base+'/user/attributes',
                    data: {
                        attributes: ['id','username','mail','created','last_attr_sync','avatar']
                    },
                    type: 'GET',
                    success: function(body) {
                        var $table = $('#fs-profile-user').find('table');
                        for(var attribute in body.data) {
                            switch(attribute) {
                                case 'created':
                                case 'last_attr_sync':
                                    var ts     = body.data[attribute].sec*1000,
                                        date   = new Date(ts),
                                        format = kendo.toString(date, kendo.culture().calendar.patterns.g),
                                        since  = balloon.timeSince(date);

                                    $table.append('<tr><th>'+attribute+'</th><td>'+i18next.t('view.history.changed_since', since, format)+'</td></tr>');
                                break;

                                case 'avatar':
                                    var $avatar = $('#fs-profile-avatar');
                                    $avatar.css('background-image', 'url(data:image/jpeg;base64,'+body.data[attribute]+')');
                                break;
        
                                default:
                                    $table.append('<tr><th>'+attribute+'</th><td>'+body.data[attribute]+'</td></tr>')
                                break;
                            }
                        }
                    }
                }); 
            }
        }).data("kendoWindow").center().open();
    },


    /**
     * Get and display event log
     * 
     * @param   object $dom
     * @param   object|string node
     * @return  void
     */
    displayEvents: function($dom, node, params) {
        if(balloon._event_limit === true) {
            return;
        }

        var $elements = $dom.find('li');

        if(params === undefined) {
            $elements.remove();
            params = {limit: 50};
        }

        if(node !== undefined) {
            params.id = balloon.id(node);
        }

        var share_events = [
            'deleteCollectionReference',
            'deleteCollectionShare',
            'forceDeleteCollectionReference',
            'forceDeleteCollectionShare',
            'undeleteCollectionReference',
            'undeleteCollectionShare',
            'addCollectionShare',
            'addCollectionReference',
            'renameCollectionShare',
            'renameCollectionReference',
            'moveCollectionReference',
            'moveCollectionShare',
            'copyCollectionReference'
        ];

        balloon.xmlHttpRequest({
            url: balloon.base+'/node/event-log',
            data: params,
            type: 'GET',
            success: function(body) {
                var $node, 
                    $icon, 
                    $undo, 
                    undo,
                    username, 
                    operation, 
                    date, 
                    that = this;
                    
                if(body.data.length === 0) {
                    balloon._event_limit = true;
                }

                if(body.data.length === 0 && $elements.length === 0) {
                    $dom.append('<li>'+i18next.t('events.no_events')+'</li>');
                    return;   
                }               
 
                for(var log in body.data) {
                    if(body.data[log].user === null) {
                        username = '<user removed>';
                    } else if(body.data[log].user.username == login.getUsername()) {
                        username = body.data[log].user.username+' ('+i18next.t('events.you')+')';
                    } else {
                        username = body.data[log].user.username;
                    }

                    undo      = false;
                    date      = kendo.toString(new Date((body.data[log].timestamp.sec*1000)), kendo.culture().calendar.patterns.g);
                    operation = balloon.camelCaseToUnderline(body.data[log].operation);
                    $node     = $('<li></li>');
                    $icon    = $('<div class="fs-icon"></div>');
                    $node.append($icon);

                    switch(body.data[log].operation) {
                        case 'deleteCollectionReference':
                        case 'deleteCollectionShare':
                        case 'deleteCollection':
                        case 'deleteFile':
                            undo = true;
                            $icon.addClass('fs-i-trash');
                            break;
                        case 'forceDeleteCollectionReference':
                        case 'forceDeleteCollectionShare':
                        case 'forceDeleteCollection':
                        case 'forceDeleteFile':
                            $icon.addClass('fs-i-trash');
                            break;
                        case 'addCollection':
                            undo = true;
                            $icon.addClass('fs-i-folder-add');
                            break;
                        case 'addFile':
                            undo = true;
                            $icon.addClass('fs-i-file-add');
                            break;
                        case 'addCollectionShare':
                        case 'addCollectionReference':
                            undo = true;
                            $icon.addClass('fs-i-group');
                            break;
                        case 'unshareCollection':
                            undo = false;
                            $icon.addClass('fs-i-group');
                            break;
                        case 'editFile':
                            undo = true;
                            $icon.addClass('fs-i-pencil');
                            break;
                        case 'undeleteFile':
                        case 'undeleteCollection':
                        case 'undeleteCollectionReference':
                        case 'undeleteCollectionShare':
                            undo = true;
                            $icon.addClass('fs-i-restore-trash');
                            break;
                        case 'restoreFile':
                            undo = true;
                            $icon.addClass('fs-i-restore');
                            break;
                        case 'renameFile':
                        case 'renameCollection':
                        case 'renameCollectionShare':
                        case 'renameCollectionReference':
                            undo = true;
                            $icon.addClass('fs-i-italic');
                            break;
                        case 'moveFile':
                        case 'moveCollection':
                        case 'moveCollectionReference':
                        case 'moveCollectionShare':
                            undo = true;
                            $icon.addClass('fs-i-paste');
                            break;
                        case 'copyFile':
                        case 'copyCollection':
                        case 'copyCollectionReference':
                            undo = true;
                            $icon.addClass('fs-i-copy');
                            break;
                    }


                    if(body.data[log].share !== null && share_events.indexOf(body.data[log].operation) == -1) {
                        $node.append(i18next.t('events.share', { 
                            share:  body.data[log].share.name,
                        })+' ');
                    }
                    
                    if(body.data[log].parent !== null && body.data[log].parent.name === null) {
                        body.data[log].parent.name = "<"+i18next.t('events.root_folder')+'>';
                    }
                    
                    if(body.data[log].previous !== null && body.data[log].previous.parent !== undefined && body.data[log].previous.parent !== null) {
                        if(body.data[log].previous.parent.name === null) {
                            body.data[log].previous.parent.name = "<"+i18next.t('events.root_folder')+'>';
                        }
                    } else if(body.data[log].previous !== null && body.data[log].previous.parent === null) {
                        body.data[log].previous.parent = {name: "<"+i18next.t('events.deleted_folder')+'>'};
                    }

                    if(body.data[log].parent === null) {
                        body.data[log].parent = {name: "<"+i18next.t('events.deleted_folder')+'>'};
                    }

                    $node.append(i18next.t('events.'+operation, { 
                        user:   username, 
                        name:   body.data[log].name,
                        previous: body.data[log].previous,
                        parent: body.data[log].parent
                    }));

                    if(body.data[log].node === null) {
                        undo = false;
                    }                    

                    if(undo === true) {
                        $undo = $('<div class="fs-icon fs-i-undo"></div>').unbind('click').bind('click', 
                            body.data[log], balloon._undoEvent);
                        $node.append($undo);
                    }

                    var app = body.data[log].client.type;

                    if(body.data[log].client.app !== null) {
                        app = body.data[log].client.app;
                    }

                    if(app === null) {
                        var via = i18next.t('events.date',{
                            date: date
                        });
                    } else {
                        var via = i18next.t('events.via',{
                            date: date,
                            app: app
                        });
                    }

                    if(body.data[log].client.hostname !== null) {
                        via += ' ('+body.data[log].client.hostname+')';
                    } 

                    $node.append('<span class="fs-event-time">'+via+'</span>');
                    $dom.append($node);
                }
            },
        });
    },


    /**
     * Infinite scroll events
     *
     * @param  object $list
     * @param  object node
     * @return void
     */
    displayEventsInfiniteScroll: function($list, node) {
        balloon._event_limit = false;
        var skip = 0;
        $list.unbind('scroll').bind('scroll', function() {
            if(($list.scrollTop() + 700) >= $list[0].scrollHeight) {
                skip = skip + 50;
                balloon.displayEvents($list, node, {skip: skip, limit: 50});
            }
        });
    },


    /**
     * Display events
     * 
     * @return void
     */
    displayEventsWindow: function() {
        var $fs_event_win   = $('#fs-event-window'),
            $fs_event_list  = $fs_event_win.find('ul'),
            datastore       = [];

        if($fs_event_win.is(':visible')) {
            balloon.displayEventsInfiniteScroll($fs_event_list);
            balloon.displayEvents($fs_event_list);
        } else {
            balloon.resetDom('events-win');
            $fs_event_win   = $('#fs-event-window'),
            $fs_event_list  = $fs_event_win.find('ul'),
            balloon.displayEventsInfiniteScroll($fs_event_list);

            $fs_event_win.kendoWindow({
                title: $fs_event_win.attr('title'),
                resizable: false,
                modal: true,
                height: '400px',
                width: '800px',
                open: function() {
                    balloon.displayEvents($fs_event_list);
                }
            }).data("kendoWindow").center().open();
        }
    },


    /**
     * Undo event
     * 
     * @param  object e
     * @return void
     */
    _undoEvent: function(e) {
        var successAction;
        if($('#fs-event-window.k-window-content').is(':visible')) {
            successAction = {action: 'displayEventsWindow'};
        } else {
            successAction = {
                action: 'switchView',
                params: ['events']
            };
        }
        
        switch(e.data.operation) {
            case 'deleteCollectionReference':
            case 'deleteCollectionShare':
            case 'deleteCollection':
            case 'deleteFile':
                var msg  = i18next.t('events.prompt.trash_restore', e.data.node.name);
                balloon.promptConfirm(msg, [
                    {
                        action: 'undelete',
                        params: [e.data.node.id]
                    }, successAction
                ]);
                break;
            case 'addCollectionShare':
                var msg  = i18next.t('events.prompt.unshare', e.data.node.name);
                balloon.promptConfirm(msg, [
                    {
                        action: '_shareCollection',
                        params: [e.data.node, {options: {shared: false}}]
                    }, successAction
                ]);
                break;
            case 'addCollection':
            case 'addFile':
            case 'addCollectionReference':
            case 'undeleteFile':
            case 'undeleteCollection':
            case 'undeleteCollectionReference':
            case 'undeleteCollectionShare':
            case 'copyCollection':
            case 'copyCollectionReference':
            case 'copyFile':
                if(successAction.action == 'switchView') {
                    successAction = null;
                }

                var msg  = i18next.t('events.prompt.trash_delete', e.data.node.name);
                balloon.promptConfirm(msg, [
                    {
                        action: 'remove',
                        params: [e.data.node.id]
                    }, successAction
                ]);
                break;
            case 'editFile':
            case 'restoreFile':
                var msg  = i18next.t('events.prompt.restore', e.data.node.name, e.data.previous.version);
                balloon.promptConfirm(msg, [
                    {
                        action: 'restoreVersion',
                        params: [e.data.node.id, e.data.previous.version]
                    }, successAction
                ]);
                break;
            case 'renameFile':
            case 'renameCollection':
            case 'renameCollectionShare':
            case 'renameCollectionReference':
                var msg  = i18next.t('events.prompt.rename', e.data.node.name, e.data.previous.name);
                balloon.promptConfirm(msg, [
                    {
                        action: 'rename',
                        params: [e.data.node.id, e.data.previous.name]
                    }, successAction
                ]);
                break;
            case 'moveFile':
            case 'moveCollection':
            case 'moveCollectionReference':
            case 'moveCollectionShare':
                var msg  = i18next.t('events.prompt.move', e.data.node.name, e.data.previous.parent.name);
                balloon.promptConfirm(msg, [
                    {
                        action: 'move',
                        params: [e.data.node.id, e.data.previous.parent]
                    }, successAction
                ]);
                break;
        }
    },


    /**
     * Convert camelCase string to underline separated string
     *
     * @param   string string
     * @return  string
     */
    camelCaseToUnderline: function(string) {
        return string.replace(/(?:^|\.?)([A-Z])/g, function (x,y){return "_" + y.toLowerCase()}).replace(/^_/, "")
    },


    /**
     * Get view name
     *
     * @return string
     */
    getViewName: function() {
        var name = $('#fs-content-data').find('div[class^=fs-view-]').filter(':visible').attr('id');

        if(name === undefined) {
            return null;
        }
        
        name = name.substr(3); 
        return name;
    },


    /**
     * Get menu name
     * 
     * @return string
     */
    getMenuName: function() {
        return $('.fs-menu-left-active').attr('id').substr(8);
    },


    /**
     * User menu
     *
     * @return void
     */
    _menuRightAction: function() {
        var $that  = $(this);
        var action = $that.attr('id').substr(13);

        switch(action) {
            case 'events':
                balloon.displayEventsWindow();
            break;

            case 'profile':
                balloon.displayUserProfile();
            break;
        }   
    },


    /**
     * Get current menu
     * 
     * @return string
     */
    getCurrentMenu: function() {
        return $('.fs-menu-left-active').attr('id').substr(8);
    },


    /**
     * Main menu
     *
     * @return void
     */
    menuLeftAction: function(menu, exec) {
        if(menu === null) {
            menu = 'cloud';
        }        
        
        if(typeof(menu) === 'string') {
            var $that  = $('#fs-menu-'+menu);
            var action = menu;
        } else {
            var $that  = $(this);
            var action = $that.attr('id').substr(8);
        }
        
        if(balloon.getCurrentMenu() != action) {
            $("#fs-action-filter-select").find('input[name=deleted]').prop('checked', false);
            balloon.tree.filter.deleted = 0;
        } else if(action === 'search' && exec !== false) {
            if($('#fs-search-extend').is(':visible')) {
                return balloon.buildExtendedSearchQuery();
            } else {
                return balloon.search($('#fs-action-search').find('input').val());
            }
        }
        
        $that.parent().find('li').removeClass('fs-menu-left-active');
        $that.addClass('fs-menu-left-active');
        balloon.togglePannel('content', true);        

        if(action != 'search') {
            balloon.resetDom(['search']);
        }
        
        if(action === 'cloud') {
            balloon.resetDom('breadcrumb-home');
            $('#fs-crumb-search-list').hide();
            $('#fs-crumb-home-list').show();
        } else {
            balloon.resetDom(['breadcrumb-search']);
            $('#fs-crumb-home-list').hide();
            $('#fs-crumb-search-list').show();
            $('#fs-crumb-search').find('div:first-child').html($that.find('div:last-child').html());
        }

        if(exec === false) {
            return;
        }

        balloon.pushState(false, true);

        switch(action) {
            case 'cloud':
                balloon.refreshTree('/collection/children', {}, {});
            break;

            case 'shared_for_me':
                balloon.refreshTree('/node/query', {filter: {shared: true, reference: {$exists: 1}}}, {});
            break;

            case 'shared_from_me':
                balloon.refreshTree('/node/query', {filter: {shared: true, reference: {$exists: 0}}}, {});
            break;

            case 'shared_link':
                balloon.refreshTree('/node/query', {filter: {sharelink: {$exists: 1}}}, {});
            break;

            case 'trash':
                balloon.tree.filter.deleted = 1;
                balloon.refreshTree('/node/trash', {}, {});
            break;

            case 'search':
                balloon.datasource.data([]);
                balloon._extendedSearch();
            break;
        }
    },


    /**
     * Switch view
     * 
     * @return void
     */
    switchView: function(view) {
        $('.fs-view-content').hide();        
        var $view = $('#fs-'+view).show();

        switch(view) {
            case 'properties':
                balloon.displayProperties(balloon.getCurrentNode());
            break;
            case 'preview':
                balloon.displayPreview(balloon.getCurrentNode());
            break;
            case 'history':
                balloon.displayHistory(balloon.getCurrentNode());
            break;
            case 'share-collection':
                balloon.shareCollection(balloon.getCurrentNode());
            break;
            case 'share-link':
                balloon.shareLink(balloon.getCurrentNode());
            break;
            case 'events':
                var $view_list = $view.find('ul');
                balloon.displayEventsInfiniteScroll($view_list, balloon.getCurrentNode());
                balloon.displayEvents($view_list, balloon.getCurrentNode());
            break;
            case 'advanced':
                balloon.advancedOperations(balloon.getCurrentNode());
            break;
        }

        balloon.pushState();
    },


    /**
     * Advanced operations
     * 
     * @param   object node
     * @return  void
     */
    advancedOperations: function(node) {
        balloon.resetDom('advanced');

        balloon.xmlHttpRequest({
            url: balloon.base+'/node/attributes',
            type: 'GET',
            dataType: 'json',
            data: {
                id: balloon.id(node),
                attributes: ['readonly', 'destroy']
            },
            success: function(data) {
                var $fs_advanced   = $('#fs-advanced'),
                    $fs_destroy_at = $fs_advanced.find('input[name=destroy_at]'),
                    $fs_readonly   = $fs_advanced.find('input[name=readonly]'),
                    $fs_submit     = $fs_advanced.find('input[name=submit]'),
                    formatted      = '';

                if(data.data.destroy !== null) {
                    var ts = data.data.destroy.sec,
                        date = new Date(parseInt(ts) * 1000);
                    formatted = kendo.toString(date, kendo.culture().calendar.patterns.g);

                    $fs_destroy_at.val(formatted);
                }

                if(data.data.readonly === true) {
                    $fs_readonly.prop('checked', true);         
                }
                
                $fs_destroy_at.kendoDateTimePicker({
                    format: kendo.culture().calendar.patterns.g,
                    min: new Date(),
                });            
                
                $fs_submit.off('click').on('click', function(){      
                    var ts = $fs_destroy_at.val();
                    if(ts !== formatted) {
                        formatted = ts;
                        if(ts === '') {
                            balloon.selfDestroyNode(node, ts);
                        } else {
                            var msg  = i18next.t('view.advanced.prompt_destroy', ts, node.name);
                            balloon.promptConfirm(msg, 'selfDestroyNode', [node, ts]);
                        }
                    }
                
                    if(data.data.readonly !== $fs_readonly.is(':checked')) {
                        data.data.readonly = $fs_readonly.is(':checked');
                        balloon.xmlHttpRequest({
                            url: balloon.base+'/node/readonly',
                            type: 'POST',
                            data: {
                                id: balloon.id(node),
                                readonly: data.data.readonly
                            },
                        });
                    }
                });
            },
        });
    },
    

    /**
     * Set self destroy node
     *
     * @param object node
     * @param string ts
     */
    selfDestroyNode: function(node, ts) {
        var url;
        
        if(ts !== '') {
            ts = kendo.parseDate(ts, kendo.culture().calendar.patterns.g);
            
            if(ts !== null) {
                ts = Math.round(ts.getTime() / 1000);
            }

            url = balloon.base+'/node?id='+balloon.id(node)+'&'+'at='+ts;
        } else {
            url = balloon.base+'/node?id='+balloon.id(node)+'&at=0';
        }

        balloon.xmlHttpRequest({
            url: url,
            type: 'DELETE',
        });
    },


    /** 
     * Tree touch move
     *
     * @return void
     */
    _treeTouchMove: function(e) {
        balloon.touch_move = true;
    },


    /**
     * Tree touch start on tree node
     *
     * @param   object e
     * @return  void
     */
   _treeTouch: function(e) {
        balloon.touch_move = false;
        balloon.long_touch = false;
        
        if(balloon.lock_touch_timer){
            return;
        }
        
        balloon.touch_timer = setTimeout(function(){
            if(balloon.touch_move !== true) {
                setTimeout(balloon._treeLongtouch(e), 50);
            }
        }, 650); 
        balloon.lock_touch_timer = true;        
    },

    
    /**
     * touch end from a tree node
     * 
     * @param   object e
     * @return  void
     */
   _treeTouchEnd: function(e) {
        if(balloon.touch_move === true)  {
            clearTimeout(balloon.touch_timer);
            balloon.lock_touch_timer = false;
            return;
        }

        if(balloon.touch_timer) {
            clearTimeout(balloon.touch_timer);
            balloon.lock_touch_timer = false;
        }
            
        if(!balloon.long_touch) {
            //call dblclick with a timeout of 50ms, otherwise balloon._treeSelect() would be fired after
            setTimeout(function(){
                balloon._treeDblclick(e);
            }, 50);
        }
    },


    /**
     * Long toch event on a tree node
     *
     * @param  object e
     * @return void
     */
    _treeLongtouch: function(e) {
        balloon.long_touch = true;
        var $node = $(e.target).parents('li'),
            $k_tree = $('#fs-browser-tree').data('kendoTreeView');

        //need to fire balloon._treeSelect() since select() would not be fired when _treeLongtouch is called
        $k_tree.select($node);
        $k_tree.trigger('select', {node: $node});

        balloon.long_touch = true;
        balloon.togglePannel('content', false)

        if(!balloon.isSystemNode(balloon.last)) {
            $('#fs-browser-tree').find('.k-in').removeClass('k-state-selected');
            
            if(balloon.isMultiSelect()) {
                balloon.multiSelect(balloon.getCurrentNode());
            }
            else {
                //balloon.multiSelect(balloon.previous, true);
                balloon.multiSelect(balloon.getCurrentNode());
            }
    
            balloon.pushState();
        }
    },


    /**
     * treeview select click event (triggered after select())
     * 
     * @param   object e
     * @return  void
     */
    _treeClick: function(e) {
        if(balloon.touch_move === true)  {
            return;
        }
        balloon.last_click_event = e;
        balloon.togglePannel('content', false);
        
        if(balloon.rename_node !== null && balloon.rename_node !== undefined) {
            balloon._rename();
        }

        if(!balloon.isSystemNode(balloon.last)) {
            if(e.ctrlKey || e.metaKey) {
                $('#fs-browser-tree').find('.k-in').removeClass('k-state-selected');
                
                if(balloon.isMultiSelect()) {
                    balloon.multiSelect(balloon.getCurrentNode());
                } else {
                    balloon.multiSelect(balloon.previous, true);
                    balloon.multiSelect(balloon.getCurrentNode());
                }
            } else if(e.shiftKey) {
                balloon.resetDom('multiselect');
                var last_pos  = balloon.datasource._data.indexOf(balloon.getCurrentNode());
                var prev_pos  = balloon.datasource._data.indexOf(balloon.previous);
                
                if(prev_pos == -1) {
                    prev_pos = last_pos;
                }
                
                if(balloon._shift_start === undefined) {
                    balloon._shift_start = prev_pos;
                } else {
                    prev_pos = balloon._shift_start;
                }

                if(prev_pos > last_pos) {
                    var _last_pos = last_pos;
                    var _prev_pos = prev_pos;
                    last_pos = _prev_pos;
                    prev_pos = _last_pos;
                }
                
                for(var node=prev_pos; node <= last_pos; node++) {
                    balloon.multiSelect(balloon.datasource._data[node]);
                }
            }
            else {
                balloon._shift_start = undefined;
                balloon.resetDom('multiselect');
            }
        
            balloon.pushState();
        }
    },     


    /**
     * Is touch device?
     *
     * @return bool
     */
    isTouchDevice: function() {
        if(balloon.DEBUG_SIMULATOR.touch === true) {
            return true;
        }

        return ('ontouchstart' in window || window.DocumentTouch && document instanceof DocumentTouch); 
    },


    /**
     * Is mobile view
     *
     * @return bool
     */
    isMobileViewPort: function() {
        if(balloon.DEBUG_SIMULATOR.mobileport === true) {
            return true;
        }

        if(window.innerWidth > 800)  {
            return false;
        } else {
            return true;
        }
    },


    /**
     * treeview dblclick
     * 
     * @param   object e
     * @return  void
     */
    _treeDblclick: function(e) {
        if(balloon.last.directory === true) {
            balloon.resetDom('selected');
        }
        
        if(balloon.last !== null && balloon.last.directory) {
            balloon.togglePannel('content', true);

            var $k_tree = $("#fs-browser-tree").data("kendoTreeView");
            
            if(balloon.last.id == '_FOLDERUP') {
                var params = {},
                    id     = balloon.getPreviousCollectionId();

                if(id !== null) {
                    params.id = id;    
                    balloon.refreshTree('/collection/children', params, null, {action: '_FOLDERUP'});
                } else {
                    
                    balloon.menuLeftAction(balloon.getCurrentMenu());
                }
            } else {
                balloon.refreshTree('/collection/children', {id: balloon.getCurrentNode().id}, null, {action: '_FOLDERDOWN'});
            }

            balloon.resetDom(
                ['selected','properties','preview','action-bar','multiselect','view-bar', 
                'history','share-collection','share-link']
            );
        } else if(balloon.isEditable(balloon.last.mime)) {
            balloon.editFile(balloon.getCurrentNode());
        } else if(balloon.isViewable(balloon.last.mime)) {
            balloon.displayFile(balloon.getCurrentNode());            
        } else {
            balloon.downloadNode(balloon.getCurrentNode());
        }

        balloon.pushState();
    },


    /**
     * Keyup in search (when a char was entered)
     *
     * @param   object e
     * @return  void
     */
    _searchKeyup: function(e){
        if(e.keyCode == 13) {
            balloon.search($(this).val());
            return;
        }

        var $target = $(e.target);

        if($target.val().length > 0) {
            $('#fs-search-reset-button').show();
        } else {
            $('#fs-search-reset-button').hide();
        }
    },

 
    /**
     * When the reset button in the searchbar is clicked
     * 
     * @param   object e
     * @return  void
     */
    _searchResetClick: function(e) {
        var $input = $('#fs-action-search').find('input:text')
            .val('');
        
        if(typeof e != "undefined") {
            $(e.target).hide();
        }
        
        balloon.menuLeftAction('cloud');
        $('#fs-crumb-home-list').show();
        
        var $fs_crumb_search_list = $('#fs-crumb-search-list').hide();
        $fs_crumb_search_list.find('li').remove();
        $fs_crumb_search_list.append('<li fs-id="" id="fs-crumb-search"><div>'+i18next.t('nav.search')+'</div><div class="fs-icon fs-i-arrowhead-e"></div></li>');
        
        balloon.resetDom(['selected', 'properties', 'preview', 'action-bar', 'multiselect', 
            'view-bar', 'history', 'share-collection', 'share-link', 'search']);
        
        balloon.refreshTree('/collection/children', {id: balloon.getCurrentCollectionId()});
    },
    
    
    /**
     * Does node exists?
     * 
     * @param   string name
     * @return  bool 
     */
    nodeExists: function(name) {
        for(var node=0; node < balloon.datasource._data.length; node++) {
            if(balloon.datasource._data[node].name.toLowerCase() === name.toLowerCase()) {
                return true;
            }
        }
        return false;
    },


    /**
     * Tree sort/filter
     *
     * @var object
     */
    tree:  {
        sort:  {
            field: 'name',
            dir:   'asc',
        },
        filter:  {
            hidden:    false,
            directory: true,
            file:      true,
            share:     true,
            deleted:   0,
        }
    },


    /**
     * Create datasource
     * 
     * @return HierarchicalDataSource
     */
    createDatasource: function() {
        balloon.datasource = new kendo.data.HierarchicalDataSource({
            transport: {
                read: function(operation, a) {
                    balloon.resetDom('upload');
                    if(balloon.datasource._url == undefined) {
                        balloon.datasource._url = balloon.base+'/collection/children';
                    }
                    
                    if(balloon.isSystemNode(operation.data)) {
                        return;
                    }
                 
                    var attributes = [
                        'id', 'name', 'mime', 'deleted', 'meta.color', 'meta.tags', 
                        'directory', 'changed', 'size', 'filtered', 
                        'shared', 'sharelink', 'hash', 'reference', 'share', 'access'
                    ];
                    
                    if(balloon.datasource._ds_params === undefined) {
                        balloon.datasource._ds_params = {action: '', nostate: false};
                    }
                    if(balloon.datasource._static_request_params === undefined) {
                        balloon.datasource._static_request_params = {};
                    }
                    if(balloon.datasource._dynamic_request_params === undefined) {
                        balloon.datasource._dynamic_request_params = {};
                    }
                    if(!('attributes' in balloon.datasource._static_request_params)) {
                        balloon.datasource._static_request_params.attributes = attributes;
                    }        
                    
                    balloon.datasource._static_request_params.deleted = balloon.tree.filter.deleted;
 
                    operation.data = $.extend(operation.data, balloon.datasource._request_params);
                    var params = JSON.parse(JSON.stringify(balloon.datasource._static_request_params));
                    $.extend(params, balloon.datasource._dynamic_request_params);
                    operation.data = params;
                    balloon.datasource._dynamic_request_params = {};
                   
                    var collection = balloon.getURLParam('collection');
                    
                    if(collection !== null && balloon.last === null && !('id' in params)) {
                        operation.data.id = collection;
                    }
     
                    if(balloon.datasource._ds_params.sort === true) {                                                
                        balloon.datasource._ds_params = false;
                        balloon._sortDatasource(
                            balloon._filterDatasource(balloon.datasource._raw_data, balloon.tree.filter), 
                            balloon.tree.sort.field, 
                            balloon.tree.sort.dir, 
                            operation
                        );
                        
                        return;
                    }
                    
                    balloon.xmlHttpRequest({
                        url: balloon.datasource._url,
                        type: 'GET',
                        dataType: 'json',
                        contentType: 'application/json',
                        data: JSON.stringify(operation.data),
                        processData: false,
                        success: function(data, msg, http) {
                            if(http.status === 204) {
                                data = {data:[]};
                            }
                            var pool = data.data;
                            for(var node in pool) {
                                pool[node].spriteCssClass = balloon.getSpriteClass(pool[node]);
                            }                                      
                            
                            if(balloon.datasource._ds_params.action == '_FOLDERDOWN') {
                                balloon.addCrumbRegister(balloon.getCurrentNode());
                            } else if(balloon.datasource._ds_params.action == '_FOLDERUP') {
                                var crumbs = balloon.getCrumb().find('li').filter(':hidden').get()/*.reverse()*/;
                                crumbs = crumbs.slice(-1);
                                $(crumbs).show();
                                //balloon.resetDom('search');
                                balloon.getCrumb().find('li:last-child').remove();            
                            }                    
                            
                            if(balloon.datasource._ds_params.nostate !== true && balloon.getCurrentNode() !== null) {
                                balloon.pushState();
                            }
                            balloon.datasource._ds_params.nostate = false;
                                

                            var depth = balloon.getFolderDepth(),
                                param_col = balloon.getURLParam('collection');  
                            
                            if(pool.length == 0 && depth == 1 && param_col  === null) {
                                $('#fs-browser-fresh').show();
                            } else {
                                $('#fs-browser-fresh').hide();
                            }
                            
                            if(depth != 1 && balloon.isSearch() === false || 'id' in operation.data && operation.data.id !== null) {
                                pool.unshift({
                                    id: '_FOLDERUP',
                                    name: "..",
                                    directory: true,
                                    spriteCssClass: 'fs-icon fs-i-folder',
                                });
                            }
                            
                            balloon.datasource._raw_data = pool;
                            balloon._sortDatasource(
                                balloon._filterDatasource(pool, balloon.tree.filter), 
                                balloon.tree.sort.field, 
                                balloon.tree.sort.dir, 
                                operation
                            );
                        },
                        error: function(e) {
                            if(balloon.datasource._raw_data === undefined) {
                                operation.success([]);
                            } else {
                                balloon._sortDatasource(
                                    balloon._filterDatasource(balloon.datasource._raw_data, balloon.tree.filter), 
                                    balloon.tree.sort.field, 
                                    balloon.tree.sort.dir, 
                                    operation
                                );
                            }
                            
                            balloon.displayError(e);
                        },
                    });
                }
            },
            schema: {
                model: {
                    id: "id",
                    hasChildren: false,
                }
            },
        });
    },


    /**
     * Check if node has a hidden name (begins with ".")
     * 
     * @param   string|object node
     * @return  bool
     */
    isHidden: function(node) {
        if(typeof(node) == 'object') {
            node = node.name;
        }
        var regex = /^\..*/;
        return regex.test(node);
    },


    /**
     * Sort tree by field and dir
     * 
     * @param   string field
     * @param   string dir
     * @return  void
     */
    sortTree: function(field, dir) {
        balloon.tree.sort = { field: field, dir: dir };
        balloon.refreshTree(null, {id: balloon.getCurrentCollectionId()}, null, {
            sort: true,
        });
    },

    
    /**
     * Sort tree (click callback)
     * 
     * @return void
     */
    _sortTree: function() {
        var field = $(this).attr('id').substr(18);
        
        $('#fs-browser-header').find('span').removeAttr('class');
        
        var dir;

        if(balloon.tree.sort.field == field) {
            if(balloon.tree.sort.dir == 'asc') {
                dir = 'desc';
            } else {
                dir = 'asc';
            }
        } else {
            dir = 'asc';
        }

        if(dir == 'asc') {
            $(this).find('span').addClass('k-icon').addClass('k-i-arrow-s');
        } else {
            $(this).find('span').addClass('k-icon').addClass('k-i-arrow-n');
        }
        
        balloon.sortTree(field, dir);
    },

    
    /**
     * Sort datasource by field and dir (This is an 
     * internal method, use sortTree() to make use of data sorting)
     * 
     * @param   array data
     * @param   string field
     * @param   string dir
     * @param   object operation
     * @return  void
     */
    _sortDatasource: function(data, field, dir, operation) {        
        //sort folders first, 2nd abc
        data.sort(function(a, b) {
            var aname, bname;
            
            if(balloon.isSystemNode(a) && !balloon.isSystemNode(b)) {
                return -1;
            } else if(balloon.isSystemNode(a) && balloon.isSystemNode(b)) {
                return 1;
            } else if(a.directory && !b.directory) {
                return -1;
            } else if(!a.directory && b.directory) {
                return 1;
            }

            if(field == 'name') {
                aname = a[field].toLowerCase();
                bname = b[field].toLowerCase();
            } else if(field == 'size') {
                aname = parseInt(a.size);
                bname = parseInt(b.size);
            } else if(field == 'changed') {
                aname = parseInt(a[field].sec);
                bname = parseInt(b[field].sec);        
            }
            
            if(dir == 'asc') {
                if(aname < bname) { 
                    return -1;
                } else if(aname > bname) {
                    return 1
                } else {
                    return 0;
                }
            }
            else if(dir == 'desc') {
                if(aname > bname) { 
                    return -1;
                } else if(aname < bname) {
                    return 1
                } else {
                    return 0;
                }
            }
        });
        
        operation.success(data);
    },


    /**
     * Filter datasource
     * 
     * @param   array data
     * @param   object filter
     * @return  void
     */
    _filterDatasource: function(data, filter) {        
        var filtered = []
        
        var def = {
            hidden:    false,
            directory: true,
            file:      true,
            share:     true,
        };
        
        $.extend(def, filter);
        filter = def;

        for(var node in data) {
            var result = true;
            for(var n in filter) {
                if(filter[n] === true) {
                    continue;
                }

                switch(n) {
                    case 'hidden':
                        if(balloon.isHidden(data[node]) && !balloon.isSystemNode(data[node])) {
                            result = false;
                            break;
                        }
                    break;

                    case 'directory':
                        if(data[node].directory === true  && !balloon.isSystemNode(data[node])) {
                            result = false;
                            break;
                        }
                    break;
                    
                    case 'share':
                        if(data[node].directory === true  && !balloon.isSystemNode(data[node]) && data[node].shared === true) {
                            result = false;
                            break;
                        }
                    break;
                    
                    case 'file':
                        if(data[node].directory === false  && !balloon.isSystemNode(data[node])) {
                            result = false;
                            break;
                        }
                    break;
                }
            }
            
            if(result === true) {
                filtered.push(data[node]);
            }
        }
        return filtered;
    },


    /**
     * Rename node directly in the browser
     * 
     * @param   object node
     * @return  void
     */
    initRenameBrowser: function(node) {
        balloon.rename_node = node;
        var $target = $('li[data-uid='+node.uid+']').find('.k-in')
          .addClass('fs-rename');
        
        $target.find('.fs-ext').hide(); 

        var value = node.name,
            name  = $target.find('.fs-browser-name').html();

        $target.find('.fs-browser-name')
          .html('<input name="rename" value="'+value+'"/>');

        var ext = balloon.getFileExtension(node);
        balloon.rename_input = $target.find('input').focus();
        if(ext === null) {
           balloon.rename_input.select();
        } else {
            var length = node.name.length - ext.length - 1;
            balloon.rename_input[0].setSelectionRange(0, length);
        }     

        $(document).unbind('click').click(function(e) {            
           if($(e.target).attr('name') == 'rename') {
                return;
            }
            
            if(node.id == balloon.rename_node.id) {
                balloon.rename_input.parent().removeClass('fs-rename');
            }
            
            balloon._rename();
        });

        balloon.rename_input.keyup(function(e) {
           e.stopImmediatePropagation();
            
            if(e.which === 27) {
                balloon.resetRename(name);
            } else if(e.which === 13) {
                if(node.id == balloon.rename_node.id) {
                    balloon.rename_input.parent().removeClass('fs-rename');
                }
   
                balloon._rename();
            }
        });
    },


    /**
     * Rename the selected node in the right view
     * 
     * @param   object e
     * @return  void
     */
    initRenameProperties: function(e) {
        if(balloon.rename_node !== null && balloon.rename_node !== undefined ){
            return;
        }

        var $fs_properties_name = $('#fs-properties-name'),
            $target             = $fs_properties_name.find('.fs-value'),
            node                = balloon.getSelected(),
            $input              = $('<input class="fs-filename-rename" type="text" value="'+ node.name +'" />'),
            name                = $target.html();

        balloon.rename_node = node;
        balloon.rename_input = $input;

        $fs_properties_name.find('.fs-ext').hide();
        $target.html($input);
        var $e_target = $(e.target);

        $input.focus();
        var ext = balloon.getFileExtension(node);
        if(ext === null) {
           $input.select();
        } else {
            var length = node.name.length - ext.length - 1;
            $input[0].setSelectionRange(0, length);
        } 

        $(document).unbind('click').click(function(e) { 
            var $some_target = $(e.target);
            if($some_target.attr('id') == 'fs-properties-name'  || 
            $some_target.parent().attr('id') == 'fs-properties-name' || 
            $some_target.parent().parent().attr('id') == 'fs-properties-name') {
                return;
            }

            balloon._rename();
            balloon._resetRenameView();
        });

        $input.keyup(function(e) {
            e.stopImmediatePropagation();
            if(e.which === 27) {
                balloon.resetRename(name);
            } else if(e.keyCode == 13) {
                balloon._rename();
                balloon._resetRenameView();
            }
        });
    },


    /**
     * Reset rename (back to the original name)
     * 
     * @param string name
     */
    resetRename: function(name) {
        var $parent = balloon.rename_input.parent();
        balloon.rename_input.remove().unbind('keyup');
        $parent.html(name);
        $parent.parent().find('.fs-ext').show();
        $(document).unbind('click');
        balloon.rename_node = undefined;
        balloon.rename_input = null;
    },



    /**
     * Reset normal fs-value-name view
     *
     * @return void
     */
    _resetRenameView: function(){
        balloon.rename_node = undefined;
        balloon.rename_input = null;
        $('#fs-properties-name').find('.fs-value').find('input').remove();
    },


    /**
     * Rename node
     * 
     * @return void
     */
    _rename: function() {
        if(balloon.rename_input === null || balloon.rename_input === undefined || balloon.rename_node === null) {
            return;
        }
        
        var new_value = balloon.rename_input.val();
        var parent = balloon.rename_input.parent();
        balloon.rename_input.remove();
        parent.html(new_value);
                      
        balloon.rename_input.unbind('keyup');
        $(document).unbind('click');
        
        if(new_value != balloon.rename_node.name) {
            balloon.rename_node.name = new_value;

            var $browser_node = $('#fs-browser').find('li[fs-id='+balloon.rename_node.id+']').find('.k-in');

            if(!balloon.rename_node.directory) {
                var ext = balloon.getFileExtension(new_value);
                
                if(ext != null) {      
                    var sprite = '<span class="k-sprite fs-icon '+balloon.getSpriteClass(balloon.rename_node)+'"></span>';
                    var name = '<span class="fs-browser-name">'+new_value.substr(0, new_value.length-ext.length-1)+'</span> <span class="fs-ext">[.'+ext+']</span>';
                    $browser_node.html(sprite+name+$browser_node.find('.fs-browser-meta')[0].outerHTML);
                }
            } else {
                $browser_node.find('.fs-browser-name').html(new_value);
            }
            
            balloon.rename(balloon.rename_node, new_value);
        } else {
            $('#fs-properties-name').find('.fs-ext').show();
            balloon._resetRenameView();
        }
    },


    /**
     * Rename node
     * 
     * @param   object node
     * @param   string new_name
     * @return  void
     */
    rename: function(node, new_name) {
        balloon.xmlHttpRequest({
            url: balloon.base+'/node/name?id='+balloon.id(node),
            type: 'POST',
            dataType: 'json',
            data: { 
                name: new_name,
            },
            success: function(data) {
                if(typeof(node) === 'object') {
                    node.name = new_name;
                    node.spriteCssClass = balloon.getSpriteClass(node);
                    
                    balloon.displayProperties(node, 
                        {
                            data: {
                                name: node.name,
                                share: node.share,
                                meta: {
                                    color: node.meta.color,
                                    tags: node.meta.tags,
                                }
                            }
                        }  
                    );
                }

                balloon.refreshTree('/collection/children', {id: balloon.getCurrentCollectionId()});
                balloon.rename_node = null;
            },
            error: function(response) {                
                balloon.rename_node = null;
                balloon.displayError(response);
            }
        });
    },


    /**
     * Select multiple nodes
     * 
     * @param   object node
     * @param   bool stay
     * @return  void
     */
    multiSelect: function(node, stay) {
        if(stay === undefined) {
            stay = false;
        }
        
        if(node == undefined || node == null) {
            balloon.resetDom('multiselect');
            return;
        }
        
        if(typeof(balloon.multiselect) != 'object') {
            balloon.multiselect = [];
        }
        
        balloon.resetDom(['upload', 'preview', 'properties', 'history', 'selected', 'view-bar']);               
        balloon.togglePannel('content', true);        

        var index = balloon.multiselect.indexOf(node);
        var $selected = $('#fs-browser-tree').find('li[fs-id='+balloon.id(node)+']');
        
        if(index >= 0 && stay === false) {
            balloon.multiselect.splice(index, 1);

            $selected.removeClass('fs-multiselected');
        }
        else if(index <= 0) {        
            balloon.multiselect.push(node);
            $selected.addClass('fs-multiselected');
        }
        
        $('#fs-browser-summary').html(i18next.t('tree.selected', {count: balloon.multiselect.length}));
    },
    

    /**
     * Check if node is selected
     * 
     * @param   object node
     * @return  bool
     */
    isSelected: function(node) {
        if(balloon.isMultiSelect()) {
            return (balloon.multiselect.indexOf(node) >= 0);
        }
        else {
            return (node.id === balloon.getCurrentNode().id);
        }
    },


    /**
     * Is multi select running
     * 
     * @return bool
     */
    isMultiSelect: function() {
        return (typeof(balloon.multiselect) == 'object' && balloon.multiselect.length > 0);
    },


    /**
     * Get node by id
     * 
     * @param   string id
     * @return  object
     */
    getNodeById: function(id) {
        return $("#fs-browser-tree").data("kendoTreeView")
            .dataSource.getByUid($('.k-item[fs-id='+id+']').attr('data-uid'));    
    },
    

    /**
     * Get node id either from object node or directly
     *
     * Return value NULL means root collection.
     * 
     * @param   string|object node 
     * @return  string
     */
    getSelected: function(node) {
        if(balloon.isMultiSelect()) {
            return balloon.multiselect;
        }
        else {
            if(node !== undefined) {
                return node;
            }
            else {
                return balloon.getCurrentNode();
            }
        }
    },

    
    /**
     * Parse error response
     *
     * @param  mixed response
     * @return mixed
     */
    parseError: function(response) {
        if(typeof(response) === 'object' && response instanceof Error) {
            return false;
        }
        else {
            if('XMLHttpRequest' in response) {
                response = response.XMLHttpRequest;
            }
                
            if(response.statusText == 'abort' && response.status == 0) {
                return;
            }

            try {
                var body = JSON.parse(response.responseText);        
            }
            catch(err) {
                body = false;
                var js_error = err.message;
            }

            return body;
        }
    },


    /**
     * Display error
     * 
     * @param   object response
     * @return  void
     */
    displayError: function(response) {
        var $fs_error_win = $('#fs-error-window'),
            $fs_error_single = $fs_error_win.find('fieldset:first').hide(),
            $fs_error_multi = $fs_error_win.find('fieldset:last').hide(),
            $list = $fs_error_win.find('ul');
        
        $list.find('li').remove();
        $fs_error_win.find('td').html('');
        
        var result = balloon.parseError(response)

        if(typeof(response) === 'object' && response instanceof Error) {
            $fs_error_single.show();
            $("#fs-error-status").html(0);
            $("#fs-error-code").html(0);
            $("#fs-error-message").html(response.message);
            $("#fs-error-error").html(response.name);
        } else {
            if('XMLHttpRequest' in response) {
                response = response.XMLHttpRequest;
            }
                
            if(response.statusText == 'abort' && response.status == 0) {
                return;
            }

            try {
                var body = JSON.parse(response.responseText);        
            } catch(err) {
                body = false;
                var js_error = err.message;
            }
            
            if(body != false) {
                if(body.data instanceof Array) {
                    $fs_error_multi.show();
                    var dom;

                    for(var i in body.data) {
                        dom = '<li><table>'+
                                '<tr>'+
                                    '<th>'+i18next.t('error.node')+'</th>'+
                                    '<td>'+body.data[i].name+'</td>'+
                                '</tr>'+
                                '<tr>'+
                                    '<th>'+i18next.t('error.classification')+'</th>'+
                                    '<td>'+body.data[i].error+'</td>'+
                                '</tr>'+
                                '<tr>'+
                                    '<th>'+i18next.t('error.code')+'</th>'+
                                    '<td>'+body.data[i].code+'</td>'+
                                '</tr>'+
                                '<tr>'+
                                    '<th>'+i18next.t('error.message')+'</th>'+
                                    '<td>'+body.data[i].message+'</td>'+
                                '</tr>'+
                              '</table></li>';

                        $list.append(dom);
                    }   
                } else {
                    $fs_error_single.show();
                    $("#fs-error-status").html(response.status);
                    $("#fs-error-code").html(body.data.code);
                    $("#fs-error-error").html(body.data.error);
                    $("#fs-error-message").html(body.data.message);
                }
            } else {
                $fs_error_single.show();
                $("#fs-error-status").html(response.status);
                $("#fs-error-message").html(response.statusText);
                $("#fs-error-error").html('parseJSONResponse');
            }
        }

        var $k_win = $fs_error_win.data('kendoWindow');
        if($k_win != undefined) {
            $k_win.destroy();
        }

        $fs_error_win.kendoWindow({
            title: $fs_error_win.attr('title'),
            resizable: false,
            width: '600px',
            modal: true,
            open: function() {
                $fs_error_win.parent().addClass('fs-error-window');
            },
            close: function() {
               balloon.showAction(['file', 'menu', 'download', 'folder', 'upload', 'refresh', 'delete', 'cut', 'copy', 'filter']);
            }
         }).data("kendoWindow").center().open();
    },
    

    /**
     * Get node id either from object node or directly
     *
     * Returning value NULL means root collection.
     * Returning an array means multiple node id's.
     * 
     * @param   string|object|array node 
     * @return  string
     */
    id: function(node) {
        if(node === null || node === '' || node === undefined) {
            return null;
        }
        else if(node instanceof Array) {
            var id = [];
            for(var i in node) {
                if(typeof(node[i]) === 'string') {
                    id.push(node[i]);
                } else {
                    id.push(node[i].id);
                }
            }

            return id;
        }
        else if(typeof(node) == 'string') {
            return node;
        }
        else {
            return node.id;
        }
    },


    /**
     * Encode string|array to uri query string
     * 
     * @param   string attr
     * @param   string|array value 
     * @return  string
     */
    param: function(attr, value) {
        var str = '';
        if(value instanceof Array) {
            for(var i in value) {
                if(str.length != 0) {
                    str += '&';
                }
                str += attr+'[]='+value[i];   
            }
        }
        else if(value === null || value === undefined) {
            return '';
        }
        else {
            str = attr+'='+value;
        }
        
        return str;
    },


    /**
     * Show loader
     *
     * @return  void
     */
    showLoader: function() {
        var $fs_loader = $('#fs-loader');

        if(!$fs_loader.is(':visible')) {
            $('#fs-namespace').addClass('fs-loader-cursor');
            $fs_loader.show();
        }
    },


    /**
     * Hide loader
     *
     * @return  void
     */
    hideLoader: function() {
        var $fs_loader = $('#fs-loader');

        if($fs_loader.is(':visible')) {
            $('#fs-namespace').removeClass('fs-loader-cursor');
            $fs_loader.hide();
        }
    },
    

    /**
     * Navigate crumb
     * 
     * @return  void
     */
    initCrumb: function() {
        $('#fs-crumb').on('click', 'li', function() {            
            var $k_tree = $("#fs-browser-tree").data("kendoTreeView"),
                $that = $(this),
                id = $that.attr('fs-id');
      
            if(id === '') {
                balloon.menuLeftAction(balloon.getCurrentMenu());
            } else {
                balloon.refreshTree('/collection/children', {id: id}, null, {action: false});
            }
            
            var $next = $that.nextAll();
            if($next.length === 0) {
                return;
            }

            var crumbs = $that.parent().find('li').filter(':hidden').get();
            crumbs = crumbs.slice($next.length * -1);
            $(crumbs).show();
            $next.remove();
            //balloon.resetDom('search');
        });
    },


    /**
     * Get crumb
     * 
     * @return object
     */
    getCrumb: function() {
        if(balloon.isSearch()) {
            return $("#fs-crumb-search-list");
        } else {
            return $("#fs-crumb-home-list");        
        }
    },
    

    /**
     * Get crumb parents
     *
     * @return array
     */
    getCrumbParents: function() {
        var list  = [];
        $('#fs-crumb-home-list').find('li').each(function() {
            var $that = $(this);

            if($that.attr('id') != 'fs-crumb-home') {
                list.push({
                    name: $that.find('div').html(),
                    id:   $that.attr('fs-id'),     
                });
            }
        });

        return list;
    },


    /**
     * Add crumb register
     * 
     * @param   object node    
     * @return  void
     */
    addCrumbRegister: function(node) {
        var exists = false;
        $('#fs-crumb-home-list').find('li').each(function(){
            if($(this).attr('fs-id') == node.id) {
                exists = true;
            }
        });

        if(exists === true) {
            return;
        }
        
        var $crumbs = $('#fs-crumb').find('li:not(#fs-crumb-home,#fs-crumb-search)').filter(':visible');
        if($crumbs.length > 2) {
            $($crumbs[0]).hide();
        }

        var child = '<li fs-id="'+node.id+'"><div>'+node.name+'</div><div class="fs-icon fs-i-arrowhead-e"></div></li>';
        balloon.getCrumb().append(child);
    },


    /**
     * Get folder depth
     * 
     * @return void
     */
    getFolderDepth: function() {
        return balloon.getCrumb().find('li').length;
    },


    /**
     * Get current collection id
     * 
     * @return string|null
     */
    getCurrentCollectionId: function() {
        var last = balloon.getCrumb().find('li:last-child');
        return balloon.id(last.attr('fs-id'));
    },


    /**
     * Get current selected node
     *
     * @return object
     */
    getCurrentNode: function() {
        if(balloon.isSystemNode(balloon.last)) {
            return balloon.previous;
        } else {
            return balloon.last;
        }
    },


    /**
     * Get previous collection id
     * 
     * @return string|null
     */
    getPreviousCollectionId: function() {
        var last = balloon.getCrumb().find('li:last-child');
        var up = last.prev();
        return balloon.id(up.attr('fs-id'));
    },


    /**
     * Refresh tree
     * 
     * @return  void
     */
    refreshTree: function(url, dynamic_params, static_params, ds_params) {
        if(typeof(ds_params) === 'object') {
             if(!('action' in ds_params)) {
                 ds_params.action = '';
             }
             if(!('nostate' in ds_params)) {
                 ds_params.nostate = false;
             }
        } else {
            ds_params = {action: '', nostate: false};
        } 

        balloon.datasource._ds_params = ds_params;       
        var $k_tree = $("#fs-browser-tree").data("kendoTreeView");

        if(url !== undefined && url !== null) {
            balloon.datasource._url = balloon.base+url;
        }

        if(dynamic_params !== undefined) {
            balloon.datasource._dynamic_request_params = dynamic_params;
        }

        if(static_params !== undefined && static_params != null) {
            balloon.datasource._static_request_params = static_params;
        } 
        
        if($k_tree != undefined) {
            $k_tree.dataSource.read();
        }
    },
    
    
    /**
     * Add folder
     * 
     * @return  void
     */
    addFolder: function() {
        var name = i18next.t('tree.new_folder');
        
        if(balloon.nodeExists(name)) {
            name = name+' ('+balloon.randomString(4)+')';
        }
        
        balloon.xmlHttpRequest({
            url: balloon.base+'/collection',
            type: 'POST',
            data: {
                id:   balloon.getCurrentCollectionId(),
                name: name,
            },
            dataType: 'json',
            success: function(data) {
                balloon.refreshTree('/collection/children', {id: balloon.getCurrentCollectionId()});
                balloon.added_rename = data.data;
            },
        });
    },
    

    /**
     * Add new file
     *
     * @return void
     */
    addFile: function() {
        var name = i18next.t('tree.new_file')+'.txt';
            
        if(balloon.nodeExists(name)) {
            name = i18next.t('tree.new_file')+' ('+balloon.randomString(4)+').txt';
        }

        name = encodeURI(name);        

        balloon.xmlHttpRequest({
            url: balloon.base+'/file?name='+name+'&'+balloon.param('collection', balloon.getCurrentCollectionId()),
            type: 'PUT',
            success: function(data) {
                balloon.refreshTree('/collection/children', {id: balloon.getCurrentCollectionId()});
                balloon.added_rename = data.data;
            },
        });
    },


    /**
     * Create random string
     * 
     * @param  int length
     * @param  string set
     * @return string
     */
    randomString: function(length, set) {
        set = set || 'abcdefghijklmnopqrstuvwxyz0123456789';
        var randomString = '';
        for (var i = 0; i < length; i++) {
            var randomPoz = Math.floor(Math.random() * set.length);
            randomString += set.substring(randomPoz,randomPoz+1);
        }
        
        return randomString;
    },
    
    
    /**
     * Check if system node
     * 
     * @param   object node
     * @return  bool
     */
    isSystemNode: function(node) {
        var system = [
            '_ROOT',
            '_SEARCH',
            '_TRASH',
            '_FOLDERUP',
            '_NEWFOLDER'
        ];
        
        if(node === null || typeof(node) != 'object') {
            return true;
        }
        else {
            return system.indexOf(node.id) > 0;    
        }
    },
    
    
    /**
     * Share node
     * 
     * @param   object|string node
     * @return  void
     */
    shareCollection: function(node) {
        if(node.directory === false) {
            return false;
        }

        balloon.resetDom('share-collection');

        balloon.xmlHttpRequest({
            url: balloon.base+'/collection/share',
            type: 'GET',
            dataType: 'json',
            data: {
                id: balloon.id(node),
            },
            success: function(data) {
                var acl = {
                    group: [],
                    user:  [],
                };
                
                var $fs_share_collection       = $('#fs-share-collection'),
                    $fs_share_collection_tbl   = $fs_share_collection.find('table'),
                    $fs_share_collection_tbody = $fs_share_collection_tbl.find('tbody');

                $fs_share_collection.find('th').each(function(){
                    var $that = $(this);
                    var chars = $that.html().length * 6;
                    $that.height(chars);
                });

                if(data.data !== false ) {
                    $fs_share_collection.find('.fs-share-remove').show();
                    var checked,
                        $acl_role;
                   
                    for(var i in data.data) {
                        checked = '';
                        if(data.data[i].priv == 'r') {
                            checked = "checked='checked'";                            
                        }
                        
                        if(data.data[i].type == 'user') {
                            acl.user.push({
                                user: data.data[i].id,
                                priv: data.data[i].priv,
                            });
                        } else {
                            acl.group.push({
                                group: data.data[i].id,
                                priv: data.data[i].priv,
                            });
                        }
                        
                        $acl_role = $('<tr fs-acl-name="'+data.data[i].id+'" fs-acl-type="'+data.data[i].type+'">'+
                                        '<td><div class="fs-icon fs-i-'+data.data[i].type+'"></div></td>'+
                                        '<td class="fs-role-name">'+data.data[i].name+'</td>'+
                                        '<td><input name="priv_'+data.data[i].id+'" value="rw" type="radio"/></td>'+
                                        '<td><input name="priv_'+data.data[i].id+'" value="r" type="radio"/></td>'+
                                        '<td><input name="priv_'+data.data[i].id+'" value="w" type="radio"/></td>'+
                                        '<td><input name="priv_'+data.data[i].id+'" value="w+" type="radio"/></td>'+
                                        '<td class="fs-delete-role"><div class="fs-icon fs-i-trash"></div></td>'+
                                    '</tr>');
                        
                        $acl_role.find('input[value="'+data.data[i].priv+'"]').prop('checked', true);
                         
                           
                        $fs_share_collection_tbody.append($acl_role);
                    }
                    
                    if(data.data.length > 0) {
                        $fs_share_collection_tbl.show().css('display', 'table');
                        $fs_share_collection.find('input[name=share]').val(i18next.t('view.share.update'));
                    }
                }

                $fs_share_collection_tbl.off('click', '.fs-i-trash').on('click', '.fs-i-trash', function() {
                    var $that = $(this),
                        $parent = $that.parent().parent(),
                        role = $parent.attr('fs-acl-name'),
                        type = $parent.attr('fs-acl-type');
                    
                    for(var i in acl[type]) {
                        if(acl[type][i][type] == role) {
                            acl[type].splice(acl[type].indexOf(acl[type][i]), 1);
                        }
                    }
                     
                    $parent.remove();

                    if($fs_share_collection.find('tr').length === 1) {
                        $fs_share_collection_tbl.hide();
                    }
                })
                .off('click', 'input[type=radio]').on('click', 'input[type=radio]', function() {
                    var $that = $(this),
                        $parent = $that.parent().parent(),
                        role = $parent.attr('fs-acl-name'),
                        type = $parent.attr('fs-acl-type');
                    
                    for(var i in acl[type]) {
                        if(acl[type][i][type] == role) {
                           acl[type][i].priv = $(this).prop('value');
                        }
                    }
                });

                var $share_role = $fs_share_collection.find("input[name=share_role]");
                var selected = false;

                $share_role.unbind('keyup').bind('keyup', function(e) {
                    if(e.keyCode == 13) {
                        if(selected === true) {
                            selected = false;
                            return;
                        }
                        
                        var value = $share_role.data("kendoAutoComplete").value()
                        if(value === '' || value === undefined) {
                            return;
                        }

                        balloon.xmlHttpRequest({
                            url: balloon.base+'/resource/acl-roles',
                            data: {
                                q: function(){
                                    return $share_role.data("kendoAutoComplete").value();
                                },
                                single: true,
                            },
                            success: function(data) {
                                $share_role.val('').focus();
                                acl = balloon._addShareRole(data.data, acl);
                            }
                        });
                    }
                });
                
                $share_role.kendoAutoComplete({
                    minLength: 3,
                    dataTextField: "name",
                    dataSource: new kendo.data.DataSource({
                        serverFiltering: true,
                        transport: {
                            read: function(operation) {
                                var value = $share_role.data("kendoAutoComplete").value()
                                if(value === '' || value === undefined) {
                                    operation.success({data:[]});
                                    return;
                                }
                          
                                balloon.xmlHttpRequest({
                                    url: balloon.base+'/resource/acl-roles',
                                    data: {
                                        q: value
                                    },
                                    success: function(data) {
                                        operation.success(data);
                                    }
                                });
                            }
                        },
                        sort: {
                            dir: 'asc',
                            field: 'name'
                        },
                        schema: {
                            data:"data",
                        }
                    }),
                    dataBound: function(e) {
                        $(e.sender.ul).find('li').each(function(n) {
                           $(this).html('<div class="fs-icon fs-i-'+e.sender.dataSource._data[n].type+'"></div>'+$(this).html());
                        });
                    
                        var $container =  $(e.sender.list);
                        $container.parent().css({'margin-top': '-18px'});
                    },  
                    change: function(e) {
                       this.dataSource.read();
                    },
                    select: function(e) {
                        selected = true;

                        setTimeout(function(){
                            $share_role.val('').focus();
                        },50);

                        $fs_share_collection.find("input[name=share_role]").val("");
                        var item = this.dataItem(e.item.index());
                        acl = balloon._addShareRole(item, acl);                   
                    }
                });
                 
                $fs_share_collection.find('input[type=submit]').unbind().click(function(){
                    if($(this).attr('name') == 'share') {
                        if(acl.user.length === 0 && acl.group.length === 0) {
                            $share_role.focus();
                        } else {
                            balloon._shareCollection(node, acl);                        
                        }
                    }
                    else {
                        var msg = i18next.t('view.share.prompt_remove_share', node.name);
                        balloon.promptConfirm(msg, 'deleteShare', [node]);
                    }
                    
                });
            },
        });
    },

    
    /**
     * Deleted share
     * 
     * @param  object node
     * @return void
     */
    deleteShare: function(node) {
        var url = balloon.base+'/collection/share?id='+balloon.id(node);

        balloon.xmlHttpRequest({
            url: url,
            type: 'DELETE',
            dataType: 'json',
            statusCode: {
                204: function(e) {
                    balloon.refreshTree('/collection/children', {id: balloon.getCurrentCollectionId()});
                    if(balloon.id(node) == balloon.id(balloon.last)) {
                        balloon.switchView('share-collection');
                    }
                }
            },
        });  
    },


    /**
     * Add share role
     *
     * @param  object item 
     * @param  object acl
     * @return object
     */
    _addShareRole: function(item, acl) {
        var $fs_share_collection       = $('#fs-share-collection'),
            $fs_share_collection_tbl   = $fs_share_collection.find('table'),
            $fs_share_collection_tbody = $fs_share_collection_tbl.find('tbody');

        if(item.type == 'group') {
            if($.grep(acl.group, function(e) { 
                return e.group == item.id; 
            }).length != 0) {
                return acl;
            }
            
            $fs_share_collection_tbody.append(
                '<tr fs-acl-name="'+item.id+'" fs-acl-type="group">'+
                    '<td><div class="fs-icon fs-i-group"></div></td>'+
                    '<td class="fs-role-name">'+item.name+'</td>'+
                    '<td><input name="priv_'+item.id+'" value="rw" checked="checked" type="radio"/></td>'+
                    '<td><input name="priv_'+item.id+'" value="r" type="radio"/></td>'+
                    '<td><input name="priv_'+item.id+'" value="w" type="radio"/></td>'+
                    '<td><input name="priv_'+item.id+'" value="w+" type="radio"/></td>'+
                    '<td class="fs-delete-role"><div class="fs-icon fs-i-trash"></div></td>'+
                '</tr>'
            );
            
            acl.group.push({
                group: item.id,
                priv: 'rw',
            });
        }
        else if(item.type == 'user') {
            if($.grep(acl.user, function(e) { 
                return e.user == item.id; 
            }).length != 0) {
                return acl;
            }

            $fs_share_collection_tbody.append(
                '<tr fs-acl-name="'+item.id+'" fs-acl-type="user">'+
                    '<td><div class="fs-icon fs-i-user"></div></td>'+
                    '<td class="fs-role-name">'+item.name+'</td>'+
                    '<td><input name="priv_'+item.id+'" value="rw" checked="checked" type="radio"/></td>'+
                    '<td><input name="priv_'+item.id+'" value="r" type="radio"/></td>'+
                    '<td><input name="priv_'+item.id+'" value="w" type="radio"/></td>'+
                    '<td><input name="priv_'+item.id+'" value="w+" type="radio"/></td>'+
                    '<td class="fs-delete-role"><div class="fs-icon fs-i-trash"></div></td>'+
                '</tr>'
            );
            
            acl.user.push({
                user: item.id,
                priv: 'rw',
            });
        }
 
        if($fs_share_collection_tbody.find('tr').length > 0) {
            $fs_share_collection_tbl.show();
        }
        
        return acl;
    },


    /**
     * Save share collection
     * 
     * @param   object node
     * @param   object acl
     * @return  void
     */
     _shareCollection: function(node, acl) {
        var url = balloon.base+'/collection/share?id='+balloon.id(node);

        balloon.xmlHttpRequest({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: {acl: acl},
            statusCode: {
                201: function(e) {
                    balloon.refreshTree('/collection/children', {id: balloon.getCurrentCollectionId()});
                    if(balloon.id(node) == balloon.id(balloon.last)) {
                        balloon.switchView('share-collection');
                    }
                },
                204: function(e) {
                    balloon.refreshTree('/collection/children', {id: balloon.getCurrentCollectionId()});
                    if(balloon.id(node) == balloon.id(balloon.last)) {
                        balloon.switchView('share-collection');
                    }
                }
            },
        });
    },
    
    
    /**
     * Share node
     * 
     * @param   object|string node
     * @return  void
     */
    shareLink: function(node) {
        balloon.resetDom('share-link');
        balloon.xmlHttpRequest({
            url: balloon.base+'/node/share-link',
            type: 'GET',
            dataType: 'json',
            data: {
                id: balloon.id(node),
            },
            success: function(data) {
                var token,
                    $fs_share_link = $('#fs-share-link'),
                    $fs_share_expr = $fs_share_link.find('input[name=share_expiration]'),
                    $fs_share_pw   = $fs_share_link.find('input[name=share_password]');

                if(data.data !== false && data.data.token != undefined) {
                    $fs_share_link.find('.fs-share-remove').show();
                    
                    $('#fs-link-options').show();
                    $fs_share_link.find('input[name=file_url]').val(window.location.origin+'/share?t='+data.data.token).show().
                        unbind('click').bind('click', function(){
                            this.select();
                            document.execCommand("copy");
                    });

                    token = data.data.token;
                    if('expiration' in data.data && data.data.expiration != '' && data.data.expiration != null) {
                        var ts = data.data.expiration;
                        var date = new Date(parseInt(ts) * 1000);
                        var formatted = kendo.toString(date, kendo.culture().calendar.patterns.g);

                        $fs_share_expr.val(formatted);
                    }
                    
                    $fs_share_link.find('input[name=share]').val(i18next.t('view.share.update'));
                }

                $fs_share_expr.kendoDateTimePicker({
                    format: kendo.toString(date, kendo.culture().calendar.patterns.g),
                    min: new Date(),
                });            
                                                
                $fs_share_link.find('input:submit').unbind().click(function(){
                    var shared,
                        date = $fs_share_expr.val();

                    shared = $(this).attr('name') == 'share';
                    
                    if(date != null || date != '' || date != undefined) {
                        date = kendo.parseDate(date, kendo.culture().calendar.patterns.g);
                        
                        if(date !== null) {
                            date = Math.round(date.getTime() / 1000);
                        }
                    }
                
                var data = {
                    id: balloon.id(node),
                    options: {
                        expiration: date,
                        token: token,
                        password: $fs_share_pw.val()
                    },
                };
                    
                    var url = url = balloon.base+'/node/share-link';

                    if(shared === true) {
                        var options = {
                            type: 'POST',
                            url: balloon.base+'/node/share-link',
                            data: {
                                id: balloon.id(node),
                                options: {
                                    expiration: date,
                                    token: token,
                                    password: $fs_share_pw.val()
                                },
                            }
                        };
                    } else {
                        var options = {
                            url: balloon.base+'/node/share-link?id='+balloon.id(node),
                            type: 'DELETE',
                        };
                    }
                     
                    options.statusCode = {
                        204: function(e) {
                            balloon.refreshTree('/collection/children', {id: balloon.getCurrentCollectionId()});
                            balloon.switchView('share-link');
                        },
                    }

                    balloon.xmlHttpRequest(options);
                });
            },
        });
    },

    
    /**
     * Check if we're running in an iOS devie
     *
     * (This stupid browser can't handle downloads, therefore we try to display it) 
     *
     * @return bool
     */
    isiOS: function() {
        if(balloon.DEBUG_SIMULATOR.idevice === true) {
            return true;
        }

        var iDevices = [
            'iPad Simulator',
            'iPhone Simulator',
            'iPod Simulator',
            'iPad',
            'iPhone',
            'iPod'
        ];

        while (iDevices.length) {
            if(navigator.platform === iDevices.pop()) { 
                return true; 
            }
        }

        return false;
    },

    
    /**
     * Download node
     * 
     * @param   object|array node
     * @return  void
     */
    downloadNode: function(node) {
        var $iframe = $("#fs-fetch-file");
        var id = balloon.id(node);

        if(typeof(id) === 'array') {
            name += '&name=selected.zip';
        }
        
        var url = balloon.base+'/node?'+balloon.param('id', id)+''+name;    
       
        if(typeof(login) === 'object' && login.getAccessToken() !== false) {
            url += '&access_token='+login.getAccessToken();
        }   
        
        if((node.directory == true || !balloon.isMobileViewPort()) && !balloon.isiOS()) {
            url += "&download=true";
            $iframe.attr("src", url).load();
        } else {
            window.location.href = url;
        }
    },
    

    /**
     * Extended search popup
     *
     * @param   object e
     * @return  void
     */
    _extendedSearch: function(e) {
        var $fs_search_extend = $('#fs-search-extend');

        if($fs_search_extend.is(':visible')) {
            return;
        }

        if($(window).width() < 1200) {
            $('#fs-action-search').find('input:text')
              .unbind('keyup').bind('keyup', function() {
                if($(this).val().length > 2) {
                    balloon.search($(this).val());
                }
            });

            return;
        }
        
        var $k_splitter = $('#fs-browser-layout').data('kendoSplitter'),
            $pane       = $k_splitter.insertAfter({ size: "100px" }, ".k-pane:last");

        var html = '<div class="fs-browser-top"></div>'+
                   '<div id="fs-action-search" style="display: none;">'+
                         '<div class="fs-icon fs-i-search"></div>'+
                         '<button id="fs-search-button" type="button"></button>'+
                         '<input accesskey="s" type="text" placeholder="'+i18next.t('nav.action.search')+'"/>'+
                         '<button id="fs-search-reset-button" type="button">x</button>'+
                     '</div>'+
                     '<div>'+
                        '<fieldset id="fs-search-filter-color">'+
                            '<label>'+i18next.t('search.filter.color')+'</label>'+
                            '<div>'+i18next.t('search.filter.no_color')+'</div>'+
                        '</fieldset>'+
                        '<fieldset id="fs-search-filter-tags">'+
                            '<label>'+i18next.t('search.filter.tags')+'</label>'+
                            '<div>'+i18next.t('search.filter.no_tags')+'</div>'+
                         '</fieldset>'+
                        '<fieldset id="fs-search-filter-mime">'+
                            '<label>'+i18next.t('search.filter.mime')+'</label>'+
                            '<div>'+i18next.t('search.filter.no_mime')+'</div>'+
                         '</fieldset>'+
                     '</div>'+
                   '</div>';

        $pane.html(html).attr('id', 'fs-search-extend');
        
        $fs_search_extend = $('#fs-search-extend');
        $k_splitter.size("#fs-layout-left", "35%");
        $k_splitter.size("#fs-content", "30%");
        $k_splitter.size("#fs-search-extend", "25%");

        $('#fs-browser-action #fs-action-search').hide("slide",{duration: 400, direction: 'right'});
        $('#fs-search-extend #fs-action-search').show("slide",{duration: 400, direction: 'left'});
        $('#fs-search-extend #fs-search-reset-button').unbind('click').click(balloon._searchResetClick);

        $fs_search_extend.find('input:text')
          .focus()
          .unbind('keyup').bind('keyup', balloon.buildExtendedSearchQuery);

        balloon.xmlHttpRequest({
            url: balloon.base+'/user/node-attribute-summary',
            type: 'GET',
            dataType: 'json',
            data: {
                attributes: ['meta.color', 'meta.tags', 'mime']
            },
            success: function(body) {
                var $color_list = $('#fs-search-filter-color').find('div'),
                    colors = body.data['meta.color'],
                    children = [];

                for(var i in colors) {
                    children.push('<li data-item="'+colors[i]._id+'" style="background-color: '+colors[i]._id+'"></li>');
                }
               
                if(children.length >= 1) {
                    $color_list.html('<ul>'+children.join('')+'</ul>');
                }
    
                var $tag_list = $('#fs-search-filter-tags').find('div'),
                    tags = body.data['meta.tags'],
                    children = [];

                for(var i in tags) {
                    children.push('<li data-item="'+tags[i]._id+'" >'+tags[i]._id+' ('+tags[i].sum+')</li>');
                }
    
                if(children.length >= 1) {
                    $tag_list.html('<ul>'+children.join('')+'</ul>');
                }

                var $mime_list = $('#fs-search-filter-mime').find('div'),
                    mimes = body.data['mime'],
                    children = [];

                for(var i in mimes) {
                    var ext = balloon.mapMimeToExtension(mimes[i]._id);
                    if(ext !== false) {
                        children.push('<li data-item="'+mimes[i]._id+'"><div class="fs-icon '+balloon.getSpriteClass(ext)+'"></div><div>['+mimes[i]._id+']</div></li>');
                    }
                    else {
                        children.push('<li data-item="'+mimes[i]._id+'"><div class="fs-icon fs-i-file"></div><div>['+mimes[i]._id+']</div></li>');
                    }
                }

                if(children.length >= 1) {
                    $mime_list.html('<ul>'+children.join('')+'</ul>');
                }

                $fs_search_extend.find('li').unbind('click').bind('click', function() {
                    var $that     = $(this),
                        $parent   = $that.parent().parent(),
                        parent_id = $parent.attr('id');
                    
                    if(parent_id === 'fs-search-filter-color' || parent_id === 'fs-search-filter-mime') {
                        $parent.find('.fs-search-filter-selected')
                          .not($that)
                          .removeClass('fs-search-filter-selected');
                    }

                    $(this).toggleClass('fs-search-filter-selected');
                    balloon.buildExtendedSearchQuery();
                });
            },
        });
    },

    
    /**
     * Build query & search
     *
     * @return void
     */
    buildExtendedSearchQuery: function() {
        var must = [];

        var should1 = [];
        $('#fs-search-filter-mime').find('li.fs-search-filter-selected').each(function(){
            should1.push({
                'query_string': {
                    'query': '(mime:"'+$(this).attr('data-item')+'")'
                }
            });
        });

        var should2 = [];
        $('#fs-search-filter-tags').find('li.fs-search-filter-selected').each(function(){
            should2.push({
                term: {
                    'meta.tags': $(this).attr('data-item')
                }
            });
        });

        var should3 = [];
        $('#fs-search-filter-color').find('li.fs-search-filter-selected').each(function(){
            should3.push({
                match: {
                    'meta.color': $(this).attr('data-item').substr(1)
                }
            });
        });

        must = [
            {bool: {should: should1}},
            {bool: {should: should2}},
            {bool: {should: should3}},
        ];

        var content = $('#fs-search-extend').find('input:text').val();
        var query   = balloon.buildQuery(content, must);

        balloon.menuLeftAction('search', false);    
        
        if(content.length < 3 && should1.length == 0 && should2.length == 0 && should3.length == 0) {
            query = undefined;
        }
        
        if(query == undefined) {
            balloon.datasource.data([]);
            return;
        }

        balloon.refreshTree('/file/search', {query: query});
    },


    /**
     * build query 
     *
     * @param   string value
     * @param   object filter
     * @return  object
     */
    buildQuery: function(value, filter) {
        var a = value.split(':');
        var attr, type;

        if(a.length > 1) {
            attr  = a[0];
            value = a[1];
        }
    
        var query = {
            body: {
                from: 0, 
                size: 500,
                query: {bool: {}}
            }
        };
        
        if(attr == undefined && value == "" && filter !== undefined) {
            query.body.query.bool.must = filter;
        } else if(attr == undefined) {     
            var should = [
                {
                   match: {
                      "content.content": {
                         query:value,
                         minimum_should_match: "90%"
                      }
                   }
                },
                {
                   match: {
                      name: {
                         query:value,
                         minimum_should_match: "90%"
                      }
                   }
                }
            ];
           
            if(filter === undefined) {
                query.body.query.bool.should = should;
            } else {
                query.body.query.bool.should = should;
                query.body.query.bool.minimum_should_match = 1;
                query.body.query.bool.must = filter;
            }
        } else{
            query.body.query.bool = {must:{term:{}}};
            query.body.query.bool.must.term[attr] = value;
            
            if(filter !== undefined) {
                query.body.query.bool.must = filter;
            }
        }
    
        return query;
    },

  
    /**
     * Search node
     * 
     * @param   string search_query
     * @return  void
     */
    search: function(search_query) {
        var value = search_query, query;
        if(value == '') {
            return balloon._searchResetClick();
        }
        
        balloon.showAction([]);
        if(typeof(search_query) === 'object') {        
            query = search_query;
        } else {
            query = balloon.buildQuery(search_query);
        }

        balloon.menuLeftAction('search', false);    
        $('#fs-search').show();
        $('#fs-action-search').find('input:text').val(search_query);

        if(query === undefined) {
            return;
        }

        balloon.refreshTree('/file/search', {query: query});
    },


    /**
     * Check if search window is active
     * 
     * @return bool
     */
    isSearch: function() {
        return $('#fs-crumb-search-list').is(':visible') || $('#fs-action-search').find('input').val().length > 0;
    },


    /**
     * Delete node
     * 
     * @param   string|object node
     * @param   bool ignore_flag
     * @return  void
     */
    deletePrompt: function(node, ignore_flag) {
        if(ignore_flag === undefined) {
            ignore_flag = false;
        }
                
        var delete_msg = i18next.t('prompt.force_delete'),
            todelete   = 0, 
            totrash    = 0,
            trash_msg  = i18next.t('prompt.trash_delete');

        trash_msg  += '<ul>';        
        delete_msg += '<ul>';

        if(balloon.isMultiSelect()) {
            for(var n in node) {
                if(node[n].deleted !== false || ignore_flag === true) {
                    todelete++;
                    delete_msg += '<li>'+node[n].name+'</li>';
                } else {
                    totrash++;
                    trash_msg += '<li>'+node[n].name+'</li>';
                }
            }
        } else if(node.deleted !== false || ignore_flag === true) {
            todelete++;
            delete_msg += '<li>'+node.name+'</li>';
        }
        
        delete_msg += '</ul>';
        trash_msg  += '</ul>';

        if(todelete > 0 && totrash > 0) {
            balloon.promptConfirm(delete_msg+'</br>'+trash_msg, 'remove', [node, true, ignore_flag]);
        } else if(todelete > 0) {
            balloon.promptConfirm(delete_msg, 'remove', [node, true, ignore_flag]);
        } else {
            balloon.remove(node);
        }
    },


    /**
     * Confirm prompt
     * 
     * @param   string msg
     * @param   string action
     * @return  void
     */
    promptConfirm: function(msg, action, params) {
        balloon.resetDom('prompt');
        var $div = $("#fs-prompt-window"),
            $k_prompt = $div.data('kendoWindow');
        $('#fs-prompt-window-content').html(msg);
        
        var $k_prompt = $div.kendoWindow({
            title: $div.attr('title'),
            resizable: false,
            modal: true,
            activate: function() {
                setTimeout(function() {
                $div.find('input[name=cancel]').focus()
                },200);
            }
        }).data("kendoWindow").center().open();
        
        $div.unbind('keydown').keydown(function(e) {
            if(e.keyCode === 27) {
                e.stopImmediatePropagation();
                $k_prompt.close(); 
            }
        });

        $div.find('input[name=cancel]').unbind('click').bind('click', function(e) {
            e.stopImmediatePropagation();
            $k_prompt.close(); 
        });
        
        var $parent = this;
        $div.find('input[name=confirm]').unbind('click').click(function(e) {
            e.stopImmediatePropagation();
            if(action.constructor === Array) {
                for(var i in action) {
                    if(action[i] !== null) {
                        $parent[action[i].action].apply($parent,action[i].params);
                    }
                }
            } else if(typeof action === 'string') {
                $parent[action].apply($parent,params);
            } else {
                action.apply($parent,params);
            }
            $k_prompt.close(); 
        });
    },
    

    /**
     * Delete node
     * 
     * @param   string|object node
     * @param   bool force
     * @param   bool ignore_flag
     * @return  void
     */
    remove: function(node, force, ignore_flag) {
        if(force === undefined) {
            force = false;
        }

        if(ignore_flag === undefined) {
            ignore_flag = false;
        }

        node = balloon.id(node);
        
        balloon.xmlHttpRequest({
            url: balloon.base+'/node?ignore_flag='+ignore_flag+'&force='+force+'&'+balloon.param('id', node),
            type: 'DELETE',
            dataType: 'json',
            beforeSend: function() {              
                balloon.resetDom(['selected', 'properties', 'preview', 'action-bar', 'multiselect', 
                    'view-bar', 'history', 'share-collection', 'share-link', 'search', 'events']);                  
                
                var $tree = $('#fs-browser-tree').find('ul');

                if(node instanceof Array) {
                    for(var n in node) {
                        $tree.find('.k-item[fs-id='+node[n].id+']').hide(1000);              
                    }                    
                } else {
                    $tree.find('.k-item[fs-id='+balloon.id(node)+']').hide(1000);              
                }
            },
            complete: function() {
                balloon.resetDom('multiselect');
            },
            success: function(data) {
                var count = 1;
                if(node instanceof Array) {
                    count = node.length;
                }
                balloon.displayQuota();

                if(balloon.getCurrentCollectionId() === null) {
                    balloon.menuLeftAction(balloon.getCurrentMenu());
                } else {
                    balloon.refreshTree('/collection/children', {id: balloon.getCurrentCollectionId()});
                }
            },
        });
    },


    /**
     * Prompt: Undelete
     * 
     * @param   string|object node
     * @return  void
     */
    undeletePrompt: function(node) {
        var restore_msg   = i18next.t('prompt.restore'),
            torestore     = 0, 
            untouched     = 0,
            clean         = [],
            untouched_msg = i18next.t('prompt.untouched');

        untouched_msg += '<ul>';
        restore_msg   += '<ul>';

        if(balloon.isMultiSelect()) {
            for(var n in node) {
                if(node[n].deleted !== false) {
                    clean.push(node[n]);
                    torestore++;
                    restore_msg += '<li>'+node[n].name+'</li>';
                } else {
                    untouched++;
                    untouched_msg += '<li>'+node[n].name+'</li>';
                }
            }
        } else if(node.deleted !== false) {
            torestore++;
            clean = node;
            restore_msg += '<li>'+node.name+'</li>';
        }
        
        restore_msg   += '</ul>';
        untouched_msg += '</ul>';

        if(torestore == 0) {
            return;
        } else if(torestore > 0 && untouched > 0) {
            balloon.promptConfirm(restore_msg+'</br>'+untouched_msg, 'undelete', [clean]);
        } else {
            balloon.undelete(node);
        }
    },


    /**
     * Undelete node
     * 
     * @param   string|object node
     * @return  void
     */
    undelete: function(node, move, parent, conflict) {
        node = balloon.id(node);

        balloon.xmlHttpRequest({
            url: balloon.base+'/node/undelete?'+balloon.param('id', node),
            type: 'POST',
            data: {
                move: move,
                destid: parent,
                conflict: conflict
            },
            dataType: 'json',
            complete: function() {
                balloon.resetDom('multiselect');
            },
            success: function(data) {
                balloon.displayQuota();
         
                if(balloon.getCurrentCollectionId() === null) {
                     balloon.menuLeftAction(balloon.getCurrentMenu());
                } else {
                    balloon.refreshTree('/collection/children', {id: balloon.getCurrentCollectionId()});
                }
            },
            error: function(response) {
                var data = balloon.parseError(response);
                if(data === false || data.status != 400 && data.status != 404) {
                    balloon.displayError(response);
                } else {
                    switch(data.data.code) {
                        case 0:
                        case 21:
                            setTimeout(function(){
                                balloon.promptConfirm(i18next.t('prompt.restore_to_root'), 'undelete', [node, true, null]); 
                            }, 500);
                        break;

                        case 19:
                            setTimeout(function(){
                                balloon.promptConfirm(i18next.t('prompt.merge'), 'undelete', [node, move, null, 2]); 
                            }, 500);
                        break;

                        default: 
                            balloon.displayError(response);
                    }
                }
            }
        });
    },
    

    /**
     * clone node
     * 
     * @param   string|object|array source
     * @param   string|object|array destination
     * @param   int conflict
     * @return  void
     */
    clone: function(source, destination, conflict) {
        return balloon.move(source, destination, conflict, true);
    },


    /**
     * move node
     * 
     * @param   string|object|array source
     * @param   string|object|array destination
     * @param   int conflict
     * @param   bool clone
     * @return  void
     */
    move: function(source, destination, conflict, clone) {
        if(clone === true) {
            var action = 'clone'
        } else {
            var action = 'move';
        }

        balloon.xmlHttpRequest({
            url: balloon.base+'/node/'+action,
            type: 'POST',
            dataType: 'json',
            data: {
                id: balloon.id(source),
                destid: balloon.id(destination),
                conflict: conflict
            },
            complete: function() {
                balloon.resetDom('multiselect');
            },
            success: function(data) {
                var count = 1;
                if(source instanceof Array) {
                    count = source.length;
                }
            
                balloon.refreshTree('/collection/children', {id: balloon.getCurrentCollectionId()});
            },
            error: function(data) {
                if(data.status === 400 && data.responseJSON && data.responseJSON.data.code === 19 && conflict !== 2) {
                    var body = data.responseJSON;
                    if(typeof(balloon.id(source)) == 'string') {
                        var nodes = [source];
                    } else {
                        var nodes = body.data;
                    }
                    
                    var id   = [];
                    var list = i18next.t('prompt.merge');
                    list += '<ul>';
        
                    for(var i in nodes) {
                        id.push(nodes[i].id); 
                        list += '<li>'+nodes[i].name+'</li>';
                    }
 
                    list   += '</ul>';

                    if(typeof(balloon.id(source)) === 'string') {
                        id = balloon.id(source);
                    }

                    balloon.promptConfirm(list, 'move', [id, destination, 2, clone]);
                } else {
                    balloon.displayError(data);
                }   
            }
        });
    },

    
    /**
     * Map mime to file extension
     *
     * @param   string mime
     * @return  string|bool
     */
    mapMimeToExtension: function(mime) {
        var map = {
            "application/pdf": "pdf",
            "application/msaccesscab": "accdc",
            "application/x-csh": "csh",
            "application/x-msdownload": "dll",
            "application/xml": "xml",
            "audio/x-pn-realaudio-plugin": "rpm",
            "application/octet-stream": "bin",
            "text/plain": "txt",
            "text/css": "css",
            "text/x-perl": "pl",
            "text/x-php": "php",
            "text/x-ruby": "rb",
            "message/rfc822": "eml",
            "application/x-pkcs12": "p12",
            "application/x-zip-compressed": "zip",
            "application/x-gzip": "gz",
            "application/x-compressed": "tgz",
            "application/x-gtar": "gtar",
            "application/x-shockwave-flash": "swf",
            "video/x-flv": "flv",
            "image/png": "png",
            "image/jpeg": "jpg",
            "image/tiff": "tiff",
            "image/x-icon": "ico",
            "image/gif": "gif",
            "application/vndms-excel": "xls",
            "application/vndopenxmlformats-officedocumentspreadsheetmlsheet": "xlsx",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document": "docx",
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": "xlsx",
            "application/vnd.oasis.opendocument.presentation": "pptx",
            "text/csv": "csv",
            "application/vndoasisopendocumentspreadsheet": "ods",
            "application/msword": "doc",
            "application/vnd.ms-word": "doc",
            "application/vnd.ms-excel": "xls",
            "application/msexcel": "xls",
            "application/vndopenxmlformats-officedocumentwordprocessingmldocument": "docx",
            "application/vndoasisopendocumenttext": "odt",
            "text/vbscript": "vbs",
            "application/vndms-powerpoint": "ppt",
            "application/vndopenxmlformats-officedocumentpresentationmlpresentation": "pptx",
            "application/vndoasisopendocumentpresentation": "odp",
            "image/svg+xml": "svg",
            "text/html": "html",
            "text/xml": "xml",
            "video/x-msvideo": "avi",
            "video/mp4": "mp4",
            "video/quicktime": "mov",
            "video/mpeg": "mpeg",
            "audio/wav": "wav"
        };
        
        if(mime in map) {
            return map[mime];
        }
        else {
            return false;
        }
    },

    
    /**
     * Extension => Sprite classname mapper
     * 
     * @param  object|string node
     * @return string
     */
    getSpriteClass: function(node) {
        if(typeof(node) === 'object') {
            if(node.directory) {
                if(node.filtered === true) {
                    if(node.shared === true && node.reference === true) {
                        return 'fs-i-folder-filter-received';
                    }
                    else if(node.shared === true) {
                        return 'fs-i-folder-filter-shared';
                    }
                    else {   
                        return 'fs-i-folder-filter';
                    }
                }
                else if(node.shared === true && node.reference === true) {
                    return 'fs-i-folder-received';
                }
                else if(node.shared === true) {
                    return 'fs-i-folder-shared';
                }
                else {
                    return 'fs-i-folder';
                }
            }
            var extension = balloon.getFileExtension(node);
        }
        else {
            var extension = node;
        }

        var map = {
            pdf:    'fs-i-file-pdf',
            dll:    'fs-i-file-archive',
            rpm:    'fs-i-file-archive',
            deb:    'fs-i-file-archive',
            bundle: 'fs-i-file-archive',
            jar:    'fs-i-file-archive',
            dmg:    'fs-i-file-archive',
            txt:    'fs-i-file-text',
            log:    'fs-i-file-text',
            css:    'fs-i-file-text',
            xml:    'fs-i-file-text',
            eml:    'fs-i-mail',
            gpg:    'fs-i-lock',
            pem:    'fs-i-lock',
            p12:    'fs-i-lock',
            cert:   'fs-i-locl',
            rar:    'fs-i-file-archive',
            zip:    'fs-i-file-archive',
            xz:     'fs-i-file-archive',
            gz:     'fs-i-file-archive',
            tgz:    'fs-i-file-archive',
            tar:    'fs-i-file-archive',
            bz2:    'fs-i-file-archive',
            swf:    'fs-i-file-movie',
            flv:    'fs-i-file-movie',
            jpeg:   'fs-i-file-image',
            tiff:   'fs-i-file-image',
            svg:    'fs-i-file-image',
            ico:    'fs-i-file-image',
            gif:    'fs-i-file-image',
            psd:    'fs-i-file-image',
            png:    'fs-i-file-image',
            jpg:    'fs-i-file-image',
            xls:    'fs-i-file-excel',
            xlsx:   'fs-i-file-excel',
            csv:    'fs-i-file-excel',
            ods:    'fs-i-file-excel',
            doc:    'fs-i-file-word',
            docx:   'fs-i-file-word',
            odt:    'fs-i-file-word',
            iso:    'fs-i-file-archive',
            ppt:    'fs-i-file-powerpoint',
            pptx:   'fs-i-file-powerpoint',
            odp:    'fs-i-file-powerpoint',
            sql:    'fs-i-file-text',
            html:   'fs-i-file-text',
            rss:    'fs-i-rss-feed',
            avi:    'fs-i-file-movie',
            mkv:    'fs-i-file-movie',
            mp4:    'fs-i-file-movie',
            mpeg:   'fs-i-file-movie',
            mov:    'fs-i-file-movie',
            mp3:    'fs-i-file-music',
            wav:    'fs-i-file-music',
            flac:    'fs-i-file-music',
            ogg:    'fs-i-file-music',
            acc:    'fs-i-file-music'
        };

        if(extension in map) {
            return map[extension];
        }
        else {
            return 'fs-i-file-text';
        }
    },
    

    /**
     * Modify file
     *
     * @param   object node
     * @return  void
     */
    editFile: function(node) {
        if(node.size > balloon.EDIT_TEXT_SIZE_LIMIT) {
            var msg  = i18next.t('prompt.open_big_file', node.name);
            balloon.promptConfirm(msg, '_editFile', [node]);
        } else {
            balloon._editFile(node);
        }
    },

    
    /**
     * Modify file
     *
     * @param   object node
     * @return  void
     */
    _editFile: function(node) {
        balloon.resetDom('edit');
        var $div = $('#fs-edit-live'),
            $textarea = $div.find('textarea');

        balloon.xmlHttpRequest({
            url: balloon.base+'/file/',
            type: 'GET',
            data: {
                id: balloon.id(node),
            },
            dataType: 'text',
            success: function (data) {
                $textarea.val(data);

                var $k_display = $div.kendoWindow({
                    width: '70%',
                    height: '70%',
                    resizable: false,
                    modal: true,
                    keydown: function(e) {
                        if(e.originalEvent.keyCode !== 27) {
                            return;
                        }

                        if(data == $textarea.val()) {
                            $k_display.close();
                            return;
                        }

                        e.stopImmediatePropagation();
                        var msg  = i18next.t('prompt.close_save_file', node.name);
                        balloon.promptConfirm(msg, function(){
                            balloon.saveFile(node, $textarea.val());
                            $k_display.close();
                        });
                            
                        $("#fs-prompt-window").find('input[name=cancel]').unbind('click').bind('click', function(){
                            $("#fs-prompt-window").data('kendoWindow').close();
                            $k_display.close();
                        });
                    },
                    open: function(e) {
                        $('#fs-edit-live_wnd_title').html(
                            $('#fs-browser-tree').find('li[fs-id="'+node.id+'"]').find('.k-in').find('> span').clone()
                        );
                
                        setTimeout(function(){
                            e.sender.wrapper.find('textarea').focus();
                        }, 600);
                            
                        e.sender.wrapper.find('textarea').unbind('change').bind('change',function(){
                            data = $textarea.val();
                        });

                        $(this.wrapper).find('.k-i-close').unbind('click.fix').bind('click.fix', function(e){
                            e.stopImmediatePropagation();
                            
                            if(data == $textarea.val()) {
                                $k_display.close();
                                return;
                            }
                            var msg  = i18next.t('prompt.close_save_file', node.name);
                            balloon.promptConfirm(msg, function(){
                                balloon.saveFile(node, $textarea.val());
                                $k_display.close();
                            });
                            
                            $("#fs-prompt-window").find('input[name=cancel]').unbind('click').bind('click', function(){
                                $("#fs-prompt-window").data('kendoWindow').close();
                                $k_display.close();
                            });
                        });
                    }
                }).data("kendoWindow").center().open();
            } 
        });

        $div.find('input[type=submit]').off('click').on('click', function(e) {
            balloon.saveFile(node, $textarea.val());
        });
    },


    /**
     * Change file content
     *
     * @param  object node
     * @param  string content
     * @return void
     */
    saveFile: function(node, content) {
        balloon.xmlHttpRequest({
            url: balloon.base+'/file?id='+balloon.id(node),
            type: 'PUT',
            data: content,
            success: function(data) {
                balloon.resetDom('edit');
            } 
        });
    },
   
     
    /**
     * Display file
     *
     * @param   object node
     * @return  void
     */
    displayFile: function(node) {
        var $div = $('#fs-display-live');
        $('#fs-display-left').hide();        
        $('#fs-display-right').hide();        

        var options = {
            draggable: false,
            resizable: false,
            modal: false,
            open: function(e) {
                $('#fs-display-live_wnd_title').html(
                    $('#fs-browser-tree').find('li[fs-id="'+node.id+'"]').find('.k-in').find('> span').clone()
                );
                
                $(this.wrapper).addClass('fs-transparent-window');
                $div.addClass('fs-transparent-window');
                $('body').append('<div class="fs-display-overlay"></div>');
            },
            close: function() {
                $('.fs-display-overlay').remove();
                $('#fs-display-content > *').remove(); 
            }
        };

        if($div.is(':visible')) {
            options.close();
            options.open();
        } else {
            var $k_display = $div.kendoWindow(options).data("kendoWindow").open().maximize();
        } 
        
        var url = balloon.base+'/file?id='+node.id+'&hash='+node.hash;
        if(typeof(login) === 'object' && login.getAccessToken() !== false) {
            url += '&access_token='+login.getAccessToken();
        }
        var $div_content = $('#fs-display-content').html('').hide(),
            $element,
            type = node.mime.substr(0, node.mime.indexOf('/'));
        $div_content.css({width: 'inherit', height: 'inherit'});

        if(type == 'image') {
            $element = $('<img src="'+url+'"/>');
        } else if(type == 'video') {
            $element = $('<video autoplay controls><source src="'+url+'" type="'+node.mime+'"></video>');
        } else if(type == 'audio' || node.mime == 'application/ogg') {
            $element = $('<audio autoplay controls><source src="'+url+'" type="'+node.mime+'">Not supported</audio>');
        } else if(node.mime == 'application/pdf') {
            $div_content.css({width: '90%', height: '90%'})
            $element = $('<embed src="'+url+'" pluginspage="http://www.adobe.com/products/acrobat/readstep2.html">');
        }
        $div_content.show().html($element);

        var index = node.index;
        var data = this.datasource.data();

        for(var i=++index; i<=data.length; i++) {
            if(i in data && balloon.isViewable(data[i].mime)) {
                $('#fs-display-right').show().unbind('click').bind('click', function(){
                    balloon.displayFile(data[i]);
                });
                break;
            }
        }

        index = node.index;
        for(var i2=--index; i2!=-1; i2--) {
            if(i2 in data && balloon.isViewable(data[i2].mime)) {
                $('#fs-display-left').show().unbind('click').bind('click', function(){
                    balloon.displayFile(data[i2]);
                });
                break;
            }
        }

    },

   
    /** 
     * Display user quta
     *
     * @return void
     */
    displayQuota: function() {
        var $fs_quota_usage = $('#fs-quota-usage'),
            $k_progress = $fs_quota_usage.data('kendoProgressBar');

        if($k_progress == undefined) {
            $k_progress = $fs_quota_usage.kendoProgressBar({
                type: "percent",
                value: 1,
                animation: {
                    duration: 1500
                }
            }).data("kendoProgressBar");
        }
        
        balloon.xmlHttpRequest({
            url: balloon.base+'/user/quota-usage',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                var percentage = Math.round(data.data.used/data.data.hard_quota*100);
                $k_progress.value(percentage);

                if(percentage >= 90) {
                    $fs_quota_usage.find('.k-state-selected').addClass('fs-quota-high');
                } else {
                    $fs_quota_usage.find('.k-state-selected').removeClass('fs-quota-high');
                }

                var used = balloon.getReadableFileSizeString(data.data.used),
                    max  = balloon.getReadableFileSizeString(data.data.hard_quota),
                    free = balloon.getReadableFileSizeString(data.data.hard_quota - data.data.used);
               
                balloon.quota = data.data;
            
                $('#fs-quota-total').html(i18next.t('user.quota_left', free));
                $('#fs-quota').attr('title', i18next.t('user.quota_detail', used, max, 
                  percentage, free));
            },
        });
    },


    /**
     * Convert bytes to human readable size
     *
     * @param   int bytes
     * @return  string
     */
    getReadableFileSizeString: function(bytes) {
        if(bytes === null) {
            return '0B';    
        }

        if(bytes < 1024) {
            return bytes+'B';    
        }
        
        var i = -1;
        var units = ['kB', 'MB', 'GB', ' TB', 'PB', 'EB', 'ZB', 'YB'];
        do {
            bytes = bytes / 1024;
            i++;
        } while (bytes >= 1024);
    
        return Math.max(bytes, 0.1).toFixed(1) + ' ' + units[i];
    },
        

    /**
     * Get time since
     *
     * @param   Date date
     * @return  string
     */
    timeSince: function(date) {
        var seconds = Math.floor((new Date() - date) / 1000);    

        if(seconds < -1) {
            seconds *= -1;
        }

        var interval = Math.floor(seconds / 31536000);
    
        if (interval >= 1) {
            return i18next.t('time.year', {count: interval});
        }
        interval = Math.floor(seconds / 2592000);
        if (interval >= 1) {
            return i18next.t('time.month', {count: interval});
        }        
        interval = Math.floor(seconds / 86400);
        if (interval >= 1) {
            return i18next.t('time.day', {count: interval});
        }
        interval = Math.floor(seconds / 3600);
        if (interval >= 1) {
            return i18next.t('time.hour', {count: interval});
        }
        interval = Math.floor(seconds / 60);
        if (interval >= 1) {
            return i18next.t('time.minute', {count: interval});
        }
        
        seconds = Math.round(seconds);
        if(seconds < 0) {
            seconds = 0;
        }
        
        return i18next.t('time.second', {count: seconds});
    },
    

    /**
     * Check if can edit a file via browser
     *
     * @param   string mime
     * @return  bool
     */
    isEditable: function(mime) {
        if(balloon.isMobileViewPort()) {
            return false;
        }
        
        var type = mime.substr(0, mime.indexOf('/'));
        if(type == 'text') {
            return true;
        }

        var valid = [
            'application/xml',
            'application/json',
            'inode/x-empty'
        ];
        
        return valid.indexOf(mime) > -1;
    },


    /**
     * Check if we can display the file live
     *
     * @param   string
     * @return  bool
     */
    isViewable: function(mime) {
        if(balloon.isMobileViewPort()) {
            return false;
        }

        var type = mime.substr(0, mime.indexOf('/'));

        if(type == 'image' || type == 'video' || type == 'audio') {
            return true;
        }

        var valid = [
            'application/ogg',
            'application/pdf',
        ];
        
        return valid.indexOf(mime) > -1;
    },


    /**
     * Get node file extension
     * 
     * @param   object node
     * @return  void|string
     */
    getFileExtension: function(node) {
        if(typeof(node) == 'object' && node.directory == true) {
            return null;
        }
        var ext;
        if(typeof(node) == 'string') {
            ext = node.split('.');
        }
        else {
            ext = node.name.split('.'); 
        }
        
        if(ext.length == 1) {
            return null;
        }
        else {
            return ext.pop();
        }
    },


    /**
     * Display file history
     *
     * @param   object node
     * @return  void
     */
    displayHistory: function(node) {
        balloon.resetDom('history');

        var $view = $("#fs-history"),
            $fs_history = $view.find("> ul");

        balloon.xmlHttpRequest({
           dataType: "json",
            url: balloon.base+'/file/history',
            type: "GET",
            data: {
                id: balloon.id(node)
            },
            success: function(data) {
                var icon, dom_node, ts, since, radio;
                data.data.reverse();

                for(var i in data.data) {
                    switch(data.data[i].type) {
                        case 0:
                            icon = '<div class="fs-icon fs-i-add">'+ i18next.t('view.history.added')+'</div>';
                        break;
                        
                        case 1:
                            icon = '<div class="fs-icon fs-i-pencil">'+ i18next.t('view.history.modified')+'</div>';
                        break;

                        case 2:
                            if(data.data[i].origin != undefined) {
                                icon = '<div class="fs-icon fs-i-restore">'+i18next.t('view.history.restored_from', data.data[i].origin)+'</div>';
                            }
                            else {
                                icon = '<div class="fs-icon fs-i-restore">'+ i18next.t('view.history.restored')+'</div>';
                            }
                        break;

                        case 3:
                            icon = '<div class="fs-icon fs-i-trash">'+ i18next.t('view.history.deleted')+'</div>';
                        break;

                        case 4:
                            icon = '<div class="fs-icon fs-i-restore-trash">'+ i18next.t('view.history.undeleted')+'</div>';
                        break;
                    }

                    since = balloon.timeSince(new Date((data.data[i].changed.sec*1000))), 
                    ts = kendo.toString(new Date((data.data[i].changed.sec*1000)), kendo.culture().calendar.patterns.g)

                    if(i != 0) {
                        radio = '<input type="radio" name="version" value="'+data.data[i].version+'"/>';
                    } else {
                        radio = '<input style="visibility: hidden;" type="radio" name="version" value="'+data.data[i].version+'"/>';
                    }

                    dom_node = '<li>'+radio+
                        '<div class="fs-history-version">'+i18next.t('view.history.version', data.data[i].version)+'</div>'+
                        icon+'<div class="fs-history-label">'+i18next.t('view.history.changed_by', data.data[i].user, since, ts)+'</div></li>';

                    $fs_history.append(dom_node);
                }
        
                var $submit = $view.find('input[type=submit]');
                if(data.data.length > 1) {
                    $submit.show();
                }

                $submit.off('click').on('click', function(){
                    var version = $fs_history.find('input[name=version]:checked').val();
                    if(version !== undefined) {
                        balloon.restoreVersion(node, version);
                    }
                });
            }
        });
    },


    /**
     * Restore file to a previous version
     * 
     * @param   string|object node
     * @param   int version
     * @return  void
     */    
    restoreVersion: function(node, version) {
        balloon.xmlHttpRequest({
            url: balloon.base+'/file/restore?id='+balloon.id(node),
            type: 'POST',
            dataType: 'json',
            data: {
                version: version
            },
            success: function(data) {
                balloon.refreshTree('/collection/children', {id: balloon.getCurrentCollectionId()});
                balloon.displayHistory(node);
            }
        });
    },


    /**
     * Save editable meta attributes
     * 
     * @return void
     */
    _saveMetaAttributes: function() {
        var $that = $(this),
            value = $that.val(),
            name  = $that.parent().attr('id').substr(14),
            attrs = {};
        
        attrs[name] = value;

        if(balloon.getCurrentNode().meta[name] != value) {
            balloon.saveMetaAttributes(balloon.getCurrentNode(), attrs);
        }
    },


    /**
     * Display properties of one node
     *
     * @param   object node
     * @param   object data
     * @return  void
     */
    displayProperties: function(node, data) {
        balloon.resetDom('properies');

        var $fs_prop_collection = $("#fs-properties-collection").hide(),
            $fs_prop_file       = $("#fs-properties-file").hide();

        $('#fs-properties').off('focusout').on('focusout', 'textarea,input,select', balloon._saveMetaAttributes);

        if(node.directory == true) {
            $fs_prop_collection.show();
        } else {
            $fs_prop_file.show();                            
        }
        
        var $fs_prop_tags = $("#fs-properties-meta-tags").show();
        $("#fs-properties-node").show();

        var success = function(data) {    
            var $field;
            data.data = $.extend(true, data.data, node.toJSON());
            for(var prop in data.data) {
                $field = $('#fs-properties-'+prop).find('.fs-value');
                if(data.data[prop] != '' && data.data[prop] !== null) {
                    $('#fs-properties-'+prop).parent().show();
                }                

                switch(prop) {
                    case 'changed':
                    case 'deleted':
                    case 'created':
                        if(typeof data.data[prop] === 'object' && data.data[prop] !== null) {
                            var ts     = data.data[prop].sec*1000,
                                date   = new Date(ts),
                                format = kendo.toString(date, kendo.culture().calendar.patterns.g),
                                since  = balloon.timeSince(date);

                            $field.html(i18next.t('view.history.changed_since', since, format));
                        }
                    break;
                    
                    case 'size':
                        if(node.directory === true) {
                            $field.html(i18next.t('view.prop.data.childcount', {count: data.data[prop]}));
                        } else {
                            $field.html(i18next.t('view.prop.data.size', balloon.getReadableFileSizeString(data.data[prop]), data.data[prop])); 
                        }
                    break;
                    
                    case 'name':
                        var $fs_prop_name = $("#fs-properties-name");
                        $fs_prop_name.find(".fs-icon:first-child")
                            .removeAttr('class')
                            .addClass('fs-icon')
                            .addClass(node.spriteCssClass);
                        
                        $field.parent().unbind('click').click(balloon.initRenameProperties);        
                        var ext = balloon.getFileExtension(data.data);

                        if(ext != null && node.directory == false) {
                            $fs_prop_name.find(".fs-ext").html('[.'+ext+']').show();
                            $field.html(data.data[prop].substr(0, data.data[prop].length-ext.length-1));
                        } else {
                            $fs_prop_name.find(".fs-ext").html('');
                            $field.html(data.data[prop]);
                        }
                    break;
                    
                    case 'meta':
                        try {
                        for(var meta_attr in data.data.meta) {
                            $field = $('#fs-properties-'+meta_attr).find('.fs-value');
                            
                            switch(meta_attr) {
                                case 'color':
                                    if(data.data[prop].color != undefined) {
                                        var $fs_prop_color = $('#fs-properties-'+prop+'-color');
                                        var color = 'fs-color-'+data.data[prop].color.substr(1);
                                        $fs_prop_color.find('.'+color).addClass('fs-color-selected');   
                                        
                                    }
                                break;
                                
                                case 'tags':
                                    var children = [],
                                        $fs_prop_tags_parent = $fs_prop_tags.parent();

                                    $fs_prop_tags_parent.find('.fs-add').unbind('click').bind('click', function(){
                                        balloon.initMetaTagCompletion();
                                        $('#fs-preview-add-tag').show();
                                        $fs_prop_tags_parent
                                            .find('input:text')
                                            .focus()
                                            .data('kendoAutoComplete').search();
                                    });

                                    $fs_prop_tags.find('li').remove();
                                    for(var tag in data.data.meta.tags) {
                                        children.push('<li><div class="fs-delete">x</div><div class="tag-name">'+data.data.meta.tags[tag]+'</div></li>');
                                    }
                               
                                    $fs_prop_tags.find('ul').html(children.join(''));
                                break;
                                
                                case 'description':
                                    $field = $('#fs-properties-'+meta_attr).find('textarea');
                                    $field.val(data.data.meta[meta_attr]);
                                break;
                                
                                case 'coordinate':
                                    $field = $('#fs-properties-'+meta_attr).find('input');
                                    $field.val(data.data.meta[meta_attr]);
                                break;


                                default:
                                    $field = $('#fs-properties-'+meta_attr).find('input');
                                    $field.val(data.data.meta[meta_attr]);
                                break;
                            }
                         }
                         }
                         catch(err) {

                         }
                    break;
                    
                    case 'share':
                        if(data.data[prop] !== false && 'shareowner' in data.data) {
                            var msg = i18next.t('view.prop.head.share_value', data.data[prop], data.data.shareowner, data.data.access);
                            $field.html(msg)
                                .parent().parent().css('display','table-row');

                            var sclass = '';
                            if(data.data.shareowner == login.username) {
                                sclass = 'fs-icon fs-i-folder-shared';
                            } else {
                                sclass = 'fs-icon fs-i-folder-received';
                            }
                              
                            $field.parent().find('div:first-child').attr('class', sclass);
                        }
                    break;

                    default:
                        if($field.length != 0 && prop !== 'shareaccess' && prop != 'shareowner') { 
                            $field.html(data.data[prop]);
                        }                                
                    break;
                }
            }              
            
            var $fs_prop_color = $("#fs-properties-meta-color");
            $fs_prop_color.find("li").unbind('click').click(function(e){
                var color = '#'+$(this).attr('class').substr(9, 6);

                $fs_prop_color.find('.fs-color-selected').removeClass('fs-color-selected');                
                
                if(color === balloon.getCurrentNode().meta.color) {
                    color = null;
                    $('#fs-browser-tree').find('li[fs-id='+balloon.getCurrentNode().id+']').find('.fs-color-tag').css('background-color', 'transparent');
                } else {
                    $(this).addClass('fs-color-selected');   
                    $('#fs-browser-tree').find('li[fs-id='+balloon.getCurrentNode().id+']').find('.fs-color-tag').css('background-color', color);
                }

                balloon.saveMetaAttributes(balloon.getCurrentNode(), {color: color});
            });             
            
            balloon.handleTags(node);                 
        };


        if(data !== undefined) {
            success(data);
            return;
        }

        var attributes = [
            'path',
            'meta.license',
            'meta.description',
            'meta.copyright',
            'meta.coordinate',
            'meta.author',
            'created',
            'version',
            'shareowner'
        ];

        balloon.xmlHttpRequest({
            url: balloon.base+'/node/attributes',
            type: 'GET',
            dataType: 'json',
            data: {
                id: balloon.id(node),
                attributes: attributes
            },
            success: success,
        });
    },
    

    /**
     * Auto complete tags
     * 
     * @return void
     */
    initMetaTagCompletion: function() {
        var $meta_tags = $('#fs-properties-meta-tags'),
            $meta_tags_parent = $meta_tags.parent(),
            $input = $meta_tags_parent.find('input');

         $input.kendoAutoComplete({
            minLength: 0,
            dataTextField: "_id",
            dataSource: new kendo.data.DataSource({
                transport: {
                    read: function(operation) {
                        balloon.xmlHttpRequest({
                            url: balloon.base+'/user/node-attribute-summary',
                            data: {
                                attributes: ['meta.tags']
                            },
                            success: function(data) {
                                data.data['meta.tags'].sort();
                                operation.success(data.data['meta.tags']);
                            }
                        });
                    }
                },
            }),
            sort: {
                dir: 'asc',
                field: '_id'
            },
            change: function(e) {
               this.dataSource.read();
            },
        });

        $input.unbind('focus').bind('focus', function() {
            $meta_tags.addClass('fs-select-tags');
            $input.data('kendoAutoComplete').search();
        });
        
        $input.unbind('blur').bind('blur', function() {
            $meta_tags.removeClass('fs-select-tags');
        });
    },


    /**
     * Display preview
     *
     * @param   object node
     * @return  void
     */
    displayPreview: function(node) {
        balloon.resetDom('preview');

        balloon.displayProperties(node, 
            {
                data: {
                    name: node.name,
                    share: node.share,
                    meta: {
                        color: node.meta.color,
                        tags: node.meta.tags,
                    }
                }
            }  
        );
        
        var $fs_preview_outer = $("#fs-preview-thumb"),
            $fs_preview       = $fs_preview_outer.find("div");
        
        if(node.directory == true) {            
            return;
        } else {
            $fs_preview_outer.show();
        }

        balloon.xmlHttpRequest({
            url: balloon.base+'/file/preview?encode=base64&id='+balloon.id(node),
            type: 'GET',
            timeout: 5000,
            beforeSend: function() {
                $fs_preview_outer.show();
                $fs_preview.addClass('fs-loader');                       
            },
            complete: function() {
                $fs_preview.removeClass('fs-loader');
        
                $fs_preview.find('*').unbind('click').bind('click', function() {
                    if(balloon.isViewable(node.mime)) {
                        balloon.displayFile(node);
                    } else {
                        balloon.downloadNode(node);
                    }
                });
            },
            success: function(data) {
                if(data == '') {
                    this.error();
                } else {                        
                    var img = document.createElement('img');
                    img.src = 'data:image/jpeg;base64,' + data;
                    $fs_preview.html(img);
                }
            },
            error: function() {
                $fs_preview.html('<div class="fs-preview-placeholder">'+i18next.t('view.preview.failed')+'</div>');
            }
        });
    },


    /**
     * Manage meta tags
     * 
     * @param   object node
     * @return  void
     */
    handleTags: function(node) {
        var last_tag,
            $fs_prop_tags = $('#fs-properties-meta-tags'),
            $fs_prop_tags_parent = $fs_prop_tags.parent();

        $fs_prop_tags.unbind('click').on('click', 'li', function(e) {
            if($(e.target).attr('class') == 'fs-delete') {
                $(this).remove();

                var tags = $fs_prop_tags.find('li').map(function() { 
                    return $(this).find('.tag-name').text();
                }).get();
                
                if(tags.length === 0) {
                    tags = '';
                }
                
                balloon.saveMetaAttributes(node, {tags: tags});
                return;
            }

            balloon._extendedSearch();            
            var value = 'meta.tags:'+$(this).find('.tag-name').text();

            $('#fs-action-search').find('input:text').val(value);
            balloon.search(value);           
        });
        
        $fs_prop_tags_parent.find('input[name=add_tag]').unbind('keypress').keypress(function(e) {
            balloon.resetDom('upload');

            $(document).unbind('click').click(function(e) {
                return balloon._metaTagHandler(node, e, last_tag);        
            });

            last_tag = $(this);
            return balloon._metaTagHandler(node, e, last_tag);
        });
    },
 

    /**
     * Manage meta tags
     * 
     * @param   object node
     * @return  void
     */
    _metaTagHandler: function(node, e, $last_tag) {
        var code    = (!e.charCode ? e.which : e.charCode),
            strcode = String.fromCharCode(!e.charCode ? e.which : e.charCode);

        if(e.type == 'click' || code == 13 || code == 32 || code == 0) {
            var value = $last_tag.val();
            
            if(value == '') {
                return;
            }

            if(node.meta.tags !== undefined && node.meta.tags.indexOf(value) != -1) {
                return false;
            }

            var $fs_prop_tags = $('#fs-properties-meta-tags');
            if($last_tag.attr('name') == 'add_tag') {
                $fs_prop_tags.find('ul').append('<li><div class="fs-delete">x</div><div class="tag-name">'+value+'</div></li>');
                $last_tag.val('').focus();
            }
            else {
                var $parent = $last_tag.parent();
                $last_tag.remove();
                $parent.html('<div class="tag-name">'+value+'</div><div class="fs-delete">x</div>');                
            }
            
            var tags = $fs_prop_tags.find('li').map(function () { 
                return $(this).find('.tag-name').text(); 
            }).get();
            
            $(document).unbind('click');
            $last_tag = undefined;
            
            balloon.saveMetaAttributes(node, {tags: tags});
   
            e.preventDefault();
        }
        
        var allowed = new RegExp("^[a-zA-Z0-9\-\_]+$");
        if (allowed.test(strcode) || code == 8) {
            return true;
        }
        
        e.preventDefault();
        return false;  
    },
 

    /**
     * Save meta attributes
     * 
     * @param   object node
     * @param   object meta
     * @return  void
     */
    saveMetaAttributes: function(node, meta) {
        balloon.xmlHttpRequest({
            url: balloon.base+'/node/meta-attributes?id='+balloon.id(node),
            type: 'POST',
            data: meta,
            success: function() {
                for(var attr in meta) {
                    if(meta[attr] == '') {
                        delete node.meta[attr];
                    }
                    else {
                        node.meta[attr] = meta[attr];
                    }
                }
                
            },
        });
    },


    /**
     * Show view
     * 
     * @param   string|array elements
     * @return  void
     */
    showView: function(elements) {
        balloon.resetDom('view-bar');
        
        if(elements == undefined) {
            return;
        } else if(typeof(elements) == 'string') {
            return balloon.showView([
                elements,
            ]);
        }
        
        for(var element in elements) {
            $('#fs-view-'+elements[element]).css('display', 'inline-block');
        }
    },


    /**
     * Show action
     * 
     * @param   string|array elements
     * @param   bool reset
     * @return  void
     */
    showAction: function(elements, reset) {
        if(reset === true || reset === undefined) {
            balloon.resetDom('action-bar');
        }

        if(elements == undefined) {
            return;
        } else if(typeof(elements) == 'string') {
            return balloon.showAction([
                elements
            ], reset);
        }        
        
        for(var element in elements) {
            $('#fs-action-'+elements[element]).removeClass('fs-action-disabled');
        }
    },


    /**
     * Toggle pannel
     * 
     * @param  string pannel
     * @return void
     */
    togglePannel: function(pannel, hide) {
        var $fs_browser_layout = $('#fs-browser-layout'),
            $k_splitter = $fs_browser_layout.data('kendoSplitter'),
            $pannel  = $('#fs-'+pannel);

        if(hide === true) {
            if($pannel.width() !== 0) {
                $k_splitter.toggle($pannel);
            }
        } else if(hide === false) {
            if($pannel.width() === 0) {
                $k_splitter.toggle($pannel);
            }
        } else {
            $k_splitter.toggle($pannel);
        }
    }, 

    
    /**
     * Do a node action
     * 
     * @param  string name
     * @return void
     */
    doAction: function(name) {
        if(typeof(name) !== 'string') {
            var $that = $(this);
            name = $that.attr('id').substr(10);

            if($that.hasClass('fs-action-disabled')) {
                return;
            }
        }

        switch(name) {
            case 'menu':
                balloon.togglePannel('menu-left');
            break;
            case 'upload':
                var $files = $('#fs-files');
                $files.unbind('change').change(function(e) {
                    balloon._handleFileSelect(e, balloon.getCurrentCollectionId());
                });    
                $files.click();
            break;
            case 'file':
                balloon.addFile();
            break;
            case 'folder':
                balloon.addFolder();
            break;
            case 'delete':
                balloon.deletePrompt(balloon.getSelected(balloon.getCurrentNode()));
            break;
            case 'restore':
                balloon.undeletePrompt(balloon.getSelected(balloon.getCurrentNode()));
            break;
            case 'download':
                balloon.downloadNode(balloon.getSelected(balloon.getCurrentNode()));
            break;
            case 'refresh':
                balloon.displayQuota();
                if(balloon.getCurrentCollectionId() === null) {
                    balloon.menuLeftAction(balloon.getCurrentMenu());
                } else {
                    balloon.refreshTree('/collection/children', {id: balloon.getCurrentCollectionId()});
                }
            break;

            case 'cut':
            case 'copy':
                balloon.selected_action.command    = name;
                balloon.selected_action.nodes      = balloon.getSelected(balloon.getCurrentNode());
                balloon.selected_action.collection = balloon.getCurrentCollectionId(); 
                balloon.showAction('paste', false);
            break;

            case 'paste':
                var parent;
                if(balloon.getCurrentNode() !== null) {
                    parent = balloon.getCurrentCollectionId()
                } else if(!balloon.isMultiSelect()) {
                    parent = balloon.getSelected(balloon.getCurrentNode());
                }
        
                if(balloon.selected_action.command === 'cut') {
                    if(balloon.selected_action.collection == parent) {
                        balloon.showAction(['download', 'file', 'folder', 'upload', 'refresh', 'delete', 'cut', 'copy', 'filter']);
                    } else {
                        balloon.move(balloon.selected_action.nodes, parent);
                    }
                } else if(balloon.selected_action.command === 'copy') {
                    if(balloon.selected_action.collection == parent) {
                        balloon.clone(balloon.selected_action.nodes, parent, 1);
                    } else {
                        balloon.clone(balloon.selected_action.nodes, parent);
                    }
                }
    
                balloon.selected_action = {nodes: null, command: null, collection: null};
            break;

            case 'filter':
                $('body').off('click').on('click', function(e){
                    var $target = $(e.target);

                    if($target.attr('id') != "fs-action-filter") {
                        $('#fs-action-filter-select').hide();
                    }
                })

                $('#fs-action-filter-select').show().off('click', 'input[type=checkbox]')
                    .on('click', 'input[type=checkbox]', balloon._filterTree);
            break;
        }
    },


    /**
     * Filter tree
     * 
     * @return void
     */
    _filterTree: function(e) {
        e.stopImmediatePropagation();

        var $that   = $(this), 
            name    = $that.attr('name'),
            checked = $that.is(':checked');

        balloon.tree.filter[name] = checked;
        $('#fs-action-filter-select').hide();
            
        if(name === 'deleted' && balloon.tree.filter[name]) {
            balloon.tree.filter.deleted = 2;
            balloon.refreshTree(null, {id: balloon.getCurrentCollectionId()}, {deleted: 2});
        } else {
            balloon.tree.filter.deleted = 0;
            balloon.refreshTree(null, {id: balloon.getCurrentCollectionId()}, {deleted: 0});
        }
    },

    
    /**
     * Reset dom elements to default
     * 
     * @param   string|array elements
     * @return  void
     */
    resetDom: function(elements) {
        if(elements == undefined) {
            return balloon.resetDom([
                'shortcuts',
                'selected',
                'properties',
                'preview',
                'action-bar',
                'multiselect',
                'view-bar',
                'history',
                'share-collection',
                'share-link',
                'advanced',
                'search',
                'prompt',
                'tree',
                'edit',
                'events',
                'events-win',
                'breadcrumb-search',
                'breadcrumb-home',
            ]);
        }
        else if(typeof(elements) == 'string') {
            return balloon.resetDom([
                elements,
            ]);
        }
        
        for(var element in elements) {
            switch(elements[element]) {
                case 'upload-progress':
                    $("#fs-upload-progress-bar").find("> div").remove();
                    var $fs_upload_progress = $("#fs-upload-progress");
                    $fs_upload_progress.find(".fs-loader-small").hide();
                    $fs_upload_progress.hide().find('#fs-upload-progress-bar').append('<div class="fs-total-progress"></div>');
                    $("#fs-upload-progress-more").find("span:last-child").html('0%');
                break;

                case 'uploadmgr-progress':
                    var $fs_up_total_prog =  $("#fs-uploadmgr-total-progress");
                    var $fs_up_files_prog =  $("#fs-uploadmgr-files-progress");
                    $fs_up_total_prog.find("> div").remove();
                    $fs_up_total_prog.append('<div></div>');
                    $fs_up_files_prog.find("> div").remove();
                    $fs_up_files_prog.append('<div></div>');
                break;
                
                case 'uploadmgr-progress-files':
                    $("#fs-upload-list > div").remove();
                break;

                case 'user-profile':
                    $('#fs-profile-user').find('tr').remove();
                    var $win    = $('#fs-profile-window'),
                        $k_win  = $('#fs-profile-window').data('kendoWindow'),
                        $quota  = $('#fs-profile-quota-bar'),
                        $k_quota= $quota.data('kendoProgressBar');

                    if($k_quota !== undefined) {
                        $k_quota.destroy();
                        $quota.remove();
                        $('#fs-profile-quota').prepend('<div id="fs-profile-quota-bar"></div>');
                    }

                    balloon.resetWindow('fs-profile-window');
                break;

                case 'properties':
                    balloon._rename();
                    balloon._resetRenameView();
                    var $fs_prop = $('#fs-properties');
                    $fs_prop.find('tr:not(.fs-editable)').hide();
                    $fs_prop.find('textarea').val('');
                    $fs_prop.find('input').val('');
                    $fs_prop.find('select').val('');
                    $fs_prop.hide();
                    $fs_prop.find("span").html('');
                    $('#fs-properties-root').hide();
                    $('#fs-properties-id').parent().show();
                    $('#fs-view').hide();
                    $("#fs-properties-collection").hide();
                    $("#fs-properties-file").hide();
                    $("#fs-properties-node").hide();
                break;

                case 'advanced':
                    var $fs_advanced   = $('#fs-advanced');
                    $fs_advanced.find('input[name=destroy_at]').val(''),
                    $fs_advanced.find('input[name=readonly]').prop('checked', false);
                break;                

                case 'selected':
                    var $name = $("#fs-properties-name").hide();
                    $name.find('.fs-sprite').removeAttr('class').addClass('fs-sprite');
                    $name.find('.fs-value').html('');
                    $name.find('.fs-ext').html('');
                break;

                case 'preview':
                    $('#fs-properties-share').parent().hide();
                    
                    var $fs_meta_tags = $("#fs-properties-meta-tags");
                    $fs_meta_tags.hide()
                        .find('li').remove();
                   
                    
                    var $fs_preview_add_tag = $('#fs-preview-add-tag').hide(), 
                        $add   = $fs_preview_add_tag.find("input"),
                        $k_add = $add.data('kendoAutoComplete');

                    if($k_add != undefined) {
                        $k_add.destroy();
                    }

                    $fs_preview_add_tag.html('<input type="text" name="add_tag"/>');
                    $("#fs-properties-meta-color").find('.fs-color-selected').removeClass('fs-color-selected');
                    var $fs_preview_thumb =  $("#fs-preview-thumb");

                    $fs_preview_thumb.hide()
                        .find('.fs-hint').hide();
                    $fs_preview_thumb.find("div").html('');
                break;
                
                case 'prompt':
                    balloon.resetWindow('fs-prompt-window');
                    $("#fs-prompt-window").addClass("fs-prompt-window");
                break;   

                case 'edit':
                    balloon.resetWindow('fs-edit-live');
                break;   

                case 'events':
                    $('#fs-events').find('li').remove();
                    $('#fs-event-window').find('li').remove();
                break;

                case 'events-win':
                    balloon.resetWindow('fs-event-window');
                break; 
                               
                case 'view-bar':
                    $('#fs-view-bar').find('li').hide();
                break;

                case 'action-bar':
                    $('.fs-action-select-only').addClass('fs-action-disabled');
                    $('.fs-action-collection-only').addClass('fs-action-disabled');
                break;

                case 'search':
                    $('#fs-browser-action #fs-action-search').show();
                    $('#fs-browser-fresh').hide();

                    var $k_splitter = $('#fs-browser-layout').data('kendoSplitter');
                    if($k_splitter != undefined) {
                        $k_splitter.remove('#fs-search-extend');
                        $k_splitter.size("#fs-layout-left", "50%");
                        $k_splitter.size("#fs-content", "37%");
                    }
                break;
                
                case 'history':
                    var $view = $("#fs-history");
                    $view.find("li").remove();
                    $view.find('input[type=submit]').hide();
                break;
                
                case 'multiselect':
                    balloon.multiselect = [];
                    $('#fs-browser-summary').html('');
                    $('#fs-browser-tree').find('.fs-multiselected')
                        .removeClass('fs-multiselected')
                        .removeClass('k-state-selected');
                break;

                case 'share-collection':      
                    var $collection = $('#fs-share-collection');
                    var $complete =  $collection.find('input[name=share_role]');

                    if($complete.data('kendoAutoComplete') != undefined) {
                       $complete.data('kendoAutoComplete').destroy();
                    }

                    $complete.parent().html('<input type="text" name="share_role" placeholder="'+i18next.t('view.share.search_user')+'">');
                    
                    $collection.find('input[name=share]').val(i18next.t('view.share.create'));
                    $collection.find('.fs-share-remove').hide();
                    $collection.find('tbody tr').remove();
                    $collection.find('table').hide()
                    $collection.find("input:radio").prop('checked', false);
                break;

                case 'share-link':   
                    var $link = $('#fs-share-link');        
                    $link.find('.fs-share-remove').hide();
                    $link.find('input[name=file_url]').hide();    
                    $('#fs-link-options').hide();
                    $link.find("input:text").val('');
                    $link.find("input:password").val('');
                    $link.find("input:checkbox").prop('checked', false);
                    $link.find('input[name=share]').val(i18next.t('view.share.create'));
                break;

                case 'tree':
                    var $tree   = $("#fs-browser-tree"),
                        $k_tree = $tree.data('kendoTreeView');

                    if($k_tree !== undefined) {
                        $k_tree.destroy();
                        $tree.remove();
                        $("#fs-browser").append('<div id="fs-browser-tree"></div>');
                    }
                    $('#fs-browser-fresh').hide();
                break;

                case 'breadcrumb-home':
                    var $crumb = $('#fs-crumb-home-list');
                    $crumb.find('li').remove();
                    $crumb.append('<li id="fs-crumb-home" fs-id=""><div>'+i18next.t('menu.cloud')+'</div><div class="fs-icon fs-i-arrowhead-e"></div></li>');
                break;
                
                case 'breadcrumb-search':
                    var $crumb = $('#fs-crumb-search-list');
                    $crumb.find('li').remove();
                    $crumb.append('<li id="fs-crumb-search" fs-id=""><div>'+i18next.t('menu.search')+'</div><div class="fs-icon fs-i-arrowhead-e"></div></li>');
                break;

                case 'shortcuts':   
                    $(document).unbind('keyup');
                break;
            }
        }
    },


    /**
     * reset kendow window
     *
     * @return void
     */
    resetWindow: function(id) {
        var $win = $('#'+id);
        $win.find('li').remove();
        if($win.data('kendoWindow') !== undefined) {
            $win.data('kendoWindow').close().destroy();
            var html = $win.html(),
                title= $win.attr('title');
            $win.remove();
            $('#fs-namespace').append('<div id="'+id+'" title="'+title+'">'+html+'</div>');
        }
    },


    /**
     * Perform file uploads
     * 
     * @param   object|string parent_node
     * @param   object dom_node
     * @return  void
     */
    fileUpload: function(parent_node, dom_node) {
        var dn,
            directory,
            $fs_browser_tree = $('#fs-browser-tree');

        if(dom_node != undefined) {
            dn = dom_node;
        } else {
            dn = $('#fs-browser-tree').find('.k-item[fs-id='+balloon.id(parent_node)+']');
        }

        if(typeof(parent_node) == 'string' || parent_node === null) {
            directory = true;
        } else {
            directory = parent_node.directory;
        }

        function handleDragOver(e) {
            e.stopPropagation();
            e.preventDefault();
            
            // Explicitly show this is a copy.
            e.originalEvent.dataTransfer.dropEffect = 'copy';            
        };
        
        dn.unbind('drop').on('drop', function(e) {
            $fs_browser_tree.removeClass('fs-file-dropable');
            $fs_browser_tree.find('.fs-file-drop').removeClass('fs-file-drop');
            $('#fs-upload').removeClass('fs-file-dropable');
            
            balloon._handleFileSelect(e, parent_node);
        });
    },
    
    
    /**
     * Prepare selected files for upload
     * 
     * @param   object e
     * @param   object|string parent_node
     * @return  void
     */    
    _handleFileSelect: function(e, parent_node) {
        if(balloon.isSearch() && balloon.getCurrentCollectionId() === null) {
            return;
        }
        
        e.stopPropagation();
        e.preventDefault();
        
        if('originalEvent' in e && 'dataTransfer' in e.originalEvent) {
            var blobs = e.originalEvent.dataTransfer.files;
        } else {
            var blobs = e.target.files; 
        }

        balloon.uploadFiles(blobs, parent_node);
    },


       
    /**
     * Prepare selected files for upload
     * 
     * @param   array files
     * @param   object|string parent_node
     * @return  void
     */    
    uploadFiles: function(files, parent_node) {
        var $div = $('#fs-uploadmgr');
        var $k_manager_win = $div.kendoWindow({
            title: $div.attr('title'),
            resizable: false,
            modal: true,
            activate: function() {
                $div.find('p').html(i18next.t('uploadmgr.max_upload_size', 
                    balloon.getReadableFileSizeString(balloon.MAX_FILE_UPLOAD_SIZE)
                ));
            }
        }).data("kendoWindow");

        balloon.resetDom(['upload-progress', 'uploadmgr-progress']);        
         
        if(balloon.upload_manager === null || 
        balloon.upload_manager.count.transfer === balloon.upload_manager.count.upload) {
            balloon.resetDom('uploadmgr-progress-files');

            balloon.upload_manager = {
                parent_node: parent_node,
                progress: {
                    mgr_percent: null,
                    notifier_percent: null,
                    mgr_chunk:   null,
                },
                files: [],
                upload_bytes: 0,
                transfered_bytes: 0,
                count: {
                    upload:   0,
                    transfer: 0,
                    success:  0,
                    last_started: 0
                },
                start_time: new Date(),
            };
        }
        
        for(var i = 0, progressnode, file, last = balloon.upload_manager.count.last_started; file = files[i]; i++, last++) {
            if(file instanceof Blob) {
                file = {
                    name: file.name,
                    blob: file
                }
            }                

            if(file.blob.size === 0) {
                balloon.displayError(new Error('Upload folders or empty files is not yet supported'));          
            } else if(file.blob.size+balloon.quota.used > balloon.quota.hard_quota) {
                balloon.displayError(new Error('Quota is too low to upload this file'));
            } else if(file.blob.size > balloon.MAX_FILE_UPLOAD_SIZE) {
                balloon.displayError(new Error('File has exceeded the maximum upload size of '+balloon.MAX_FILE_UPLOAD_SIZE+' Bytes'));
            } else if(file.blob.size != 0 && file.blob.size <= balloon.MAX_FILE_UPLOAD_SIZE) {                
                progressnode = $('<div id="fs-upload-'+last+'">'+file.name+'</div>');
                $('#fs-upload-list').append(progressnode);
                
                progressnode.kendoProgressBar({
                    type: 'percent',
                    animation: {
                        duration: 10
                    },
                });
                
                balloon.upload_manager.files.push({
                    progress:   progressnode,
                    blob:       file.blob,
                    name:       file.name,
                    index:      1,
                    start:      0,
                    end:        0,
                    transfered_bytes: 0,
                    success:    Math.ceil(file.blob.size / balloon.BYTES_PER_CHUNK),
                    slices:     Math.ceil(file.blob.size / balloon.BYTES_PER_CHUNK),
                    chunkgroup: balloon.UUIDv4(),
                    manager:    balloon.upload_manager,
                    request:    null,
                    status:     1,
                });

                progressnode.contents().filter(function() {
                       return this.nodeType == 3; //Node.TEXT_NODE
                }).remove();                
                progressnode.append('<div class="fs-progress-filename">'+file.name+'</div>');
                progressnode.append('<div class="fs-progress-stop k-icon k-i-cancel"></div>');
                                
                balloon.upload_manager.upload_bytes += file.blob.size;
            }
        }
        
        if(balloon.upload_manager.files.length <= 0) {
            return;   
        }
        
        $('#fs-upload-list').on('click', '.fs-progress-stop', function() {
            var i    = parseInt($(this).parent().attr('id').substr(10)),
                file = balloon.upload_manager.files[i];
            
            if(file.status !== 1) {
                return;
            }
            
            file.status = 0;
            $(this).addClass('fs-status-loader');
        });
        
        balloon.upload_manager.count.upload  = balloon.upload_manager.files.length;                
        balloon._initProgress(balloon.upload_manager);    
            
        $('#fs-upload-progress').unbind('click').click(function(){
            $k_manager_win.center().open();
        });

        $('#fs-uploadmgr-files').html(
            i18next.t('uploadmgr.files_uploaded', "0", balloon.upload_manager.count.upload)
        );
        
        $('#fs-uploadmgr-bytes').html('<span>0</span> / '+balloon.getReadableFileSizeString(balloon.upload_manager.upload_bytes));
        $('#fs-upload-info').html('<span>0</span> / '+balloon.getReadableFileSizeString(balloon.upload_manager.upload_bytes));
                
        for(var i = balloon.upload_manager.count.last_started; i < balloon.upload_manager.count.upload; i++) {
            balloon.upload_manager.count.last_started = i + 1;
            balloon._chunkUploadManager(balloon.upload_manager.files[i]);
        }
    },


    /**
     * Init upload progress bars
     * 
     * @param  object manager
     * @return void
     */
    _initProgress: function(manager) {
        $(".fs-status-loader").show();
        $("#fs-upload-progress").show();
        
        manager.progress.mgr_percent = $("#fs-uploadmgr-total-progress").find("> div").kendoProgressBar({
            type: 'percent',
            animation: {
                duration: 10
            },
        }).data("kendoProgressBar");
                
        manager.progress.notifier_percent = $("#fs-upload-progress-bar").find("> div").kendoProgressBar({
            type: 'percent',
            animation: {
                duration: 10
            },
        }).data("kendoProgressBar");       

        if(manager.count.upload > 1) {
            manager.progress.mgr_chunk = $("#fs-uploadmgr-files-progress").find("> div").kendoProgressBar({
                type: 'chunk',
                animation: {
                    duration: 10
                },
                value: manager.count.transfer,
                min: 0,
                max: manager.count.upload,
                chunkCount: manager.count.upload,
            }).data("kendoProgressBar");       
        } else {
            manager.progress.mgr_chunk = $("#fs-uploadmgr-files-progress").find("> div").kendoProgressBar({
                type: 'value',
                animation: {
                    duration: 10
                },
                value: manager.count.transfer,
                min: 0,
                max: 1,
            }).data("kendoProgressBar");       
        }           
    },
    
 
    /**
     * Chunked file upload
     * 
     * @param   object file
     * @return  void
     */
    _chunkUploadManager: function(file) {
        //Check if all chunks of one blob have been uploaded
        if(file.index > file.slices) {
            if(file.success == file.slices) {
                file.status = 3;
                file.manager.count.transfer++;
                file.manager.count.success++;
                file.manager.progress.mgr_chunk.value(file.manager.count.transfer);
                
                file.progress.find('.fs-progress-stop').removeClass('k-i-cancel').addClass('k-i-tick');
                
                $('#fs-uploadmgr-files').html(i18next.t('uploadmgr.files_uploaded',
                    file.manager.count.success.toString(), file.manager.count.upload)
                );
            }
            
            return;
        }
        
        //Abort upload (stop uploading next chunks)
        if(file.status === 0 ) {
            file.progress.find('.fs-progress-stop').remove();
            
            $('#fs-uploadmgr-bandwidth').html('0B/s');
            file.progress.data("kendoProgressBar").value(0);
            
            file.manager.upload_bytes = file.manager.upload_bytes - file.blob.size;
            file.manager.transfered_bytes = file.manager.transfered_bytes - file.transfered_bytes;
                        
            file.manager.progress.mgr_chunk.value(file.manager.count.transfer);
            $($('#fs-uploadmgr-files-progress .k-item')[file.manager.count.transfer]).addClass('fs-progress-error');

            file.manager.count.transfer++;
        } else {
            file.end = file.start + balloon.BYTES_PER_CHUNK;
    
            if(file.end > file.blob.size) {
                file.end = file.blob.size;
            }
    
            balloon._chunkUpload(file);
    
            file.start = file.end;
            file.index++;    
        }        
    },  
    
    
    /**
     * Check if all files were proceeded
     * 
     * @return void
     */
    _checkUploadEnd: function() {
        if(balloon.upload_manager.count.transfer >= balloon.upload_manager.count.upload) {
            $('#fs-uploadmgr-bandwidth').html('0B/s');
            $('#fs-uploadmgr-time').html(
                 i18next.t('uploadmgr.finished')
            );

            $(".fs-status-loader").hide();
            balloon.refreshTree('/collection/children', {id: balloon.getCurrentCollectionId()});
            balloon.displayQuota();
    
            setTimeout(function() {
                balloon.resetDom('upload-progress');
            }, 3000);
        }
    },


    /**
     * Create uuid v4 conform string
     * 
     * @return string
     */
    UUIDv4: function() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random()*16|0, v = c == 'x' ? r : (r&0x3|0x8);
            return v.toString(16);
        });
    },
    

    /**
     * transfer selected files to the server
     * 
     * @param   object file
     * @return  void
     */            
    _chunkUpload: function(file) {
        var url = balloon.base + '/file/chunk?name=' + encodeURI(file.name) + '&index=' +
            file.index + '&chunks=' + file.slices + '&chunkgroup=' + file.chunkgroup+'&size=' + file.blob.size;
    
        if(file.manager.parent_node !== null) {
            url += '&collection='+balloon.id(file.manager.parent_node);
        }

        var chunk = file.blob.slice(file.start, file.end),
            size  = file.end - file.start,
            last  = 0;
    
        balloon.xmlHttpRequest({
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(e) {
                    if (e.lengthComputable) {                        
                        var add  = e.loaded - last;
                        file.manager.transfered_bytes += add;
                        file.transfered_bytes += add;
                        last = e.loaded;
                        
                        var file_complete = file.transfered_bytes / file.blob.size;
                        file_complete = Math.round(file_complete * 100);
                        file.progress.data("kendoProgressBar").value(file_complete);

                        var total_complete = file.manager.transfered_bytes / file.manager.upload_bytes;
                        total_complete = Math.round(total_complete * 100);
                        file.manager.progress.mgr_percent.value(total_complete);
                        file.manager.progress.notifier_percent.value(total_complete);
                        
                        $("#fs-upload-progress-more span:last-child").text(total_complete + "%");
                        $('#fs-uploadmgr-bytes > span').html(balloon.getReadableFileSizeString(file.manager.transfered_bytes));
                        $('#fs-upload-info > span').html(balloon.getReadableFileSizeString(file.manager.transfered_bytes));
                        
                        var now  = new Date();
                        var took = now.getTime() - file.manager.start_time.getTime();
                        var bytes_left = file.manager.upload_bytes - file.manager.transfered_bytes;
                        var end = new Date(now.getTime() + (Math.round(took / file.manager.transfered_bytes * bytes_left)));

                        var rate = file.manager.transfered_bytes / (took / 1000);
                        $('#fs-uploadmgr-bandwidth').html(balloon.getReadableFileSizeString(rate)+'/s');                             

                        if(bytes_left > 0) {
                            $('#fs-uploadmgr-time').html(i18next.t('uploadmgr.time_left', balloon.timeSince(end)));
                        }
                    }
                    
               }, false);
               
               file.request = xhr;  
        
               return xhr;
            },            
            url: url,
            type: 'PUT',
            data: chunk,
            processData: false,
            complete: function() {
                if(file.transfered_bytes === 0)  {
                    file.transfered_bytes += size;
                    file.manager.transfered_bytes += size;
                }                
                
                var file_complete = file.transfered_bytes / file.blob.size;
                file_complete = Math.round(file_complete * 100);
                file.progress.data("kendoProgressBar").value(file_complete);
                
                var total_complete;
                if(file.manager.upload_bytes === 0) {
                    total_complete = 0;
                } else {
                    total_complete = file.manager.transfered_bytes / file.manager.upload_bytes;
                    total_complete = Math.round(total_complete * 100);
                }
                
                file.manager.progress.mgr_percent.value(total_complete);
                file.manager.progress.notifier_percent.value(total_complete);
                
                $("#fs-upload-progress-more span:last-child").text(total_complete + "%");
                $('#fs-uploadmgr-bytes > span').html(balloon.getReadableFileSizeString(file.manager.transfered_bytes));
                $('#fs-upload-info > span').html(balloon.getReadableFileSizeString(file.manager.transfered_bytes));
            },
            success: function() {
                balloon._chunkUploadManager(file);
                balloon._checkUploadEnd();
            },
            error: function(e) {
                file.success--;

                $('#fs-uploadmgr-bandwidth').html('0B/s');
                                 
                file.manager.progress.mgr_chunk.value((file.manager.count.transfer + 1));
                
                if(file.manager.count.upload == 1) {
                    $('#fs-uploadmgr-files-progress .k-state-selected').addClass('fs-progress-error');
                } else {
                    $($('#fs-uploadmgr-files-progress .k-item')[file.manager.count.transfer]).addClass('fs-progress-error');                
                }
                
                file.progress.find('.k-state-selected').addClass('fs-progress-error');
                file.progress.find('.fs-progress-stop').removeClass('k-i-cancel').addClass('k-i-exception');

                file.status = 2;
                file.manager.count.transfer++;
                balloon._checkUploadEnd();

                var data = balloon.parseError(e);
                if(data === false || data.status != 403) {
                     balloon.displayError(response);
                 } else {
                    if(data.data.code === 40) {
                        var new_name = balloon.getCloneName(file.blob.name);
                        var new_file = {
                            name: new_name,
                            blob: file.blob
                        };

                        balloon.promptConfirm(i18next.t('prompt.auto_rename_node', file.blob.name, new_name), 'uploadFiles', [[new_file], file.manager.parent_node]);
                    } else {
                        balloon.displayError(e);
                    }
                }
            }
        });
    },


    /**
     * Get clone name
     *
     * @param  string name
     * @return string
     */
    getCloneName: function(name) {
        var ext = balloon.getFileExtension(name);
        if(ext === null) {
            return name+' ('+balloon.randomString(4)+')';
        } else {
            name = name.substr(0, name.length - (ext.length+1));
            name = name+' ('+balloon.randomString(4)+').'+ext;
            return name;
        }
    }
};
