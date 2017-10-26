/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */
balloon.office = {
    edit: function(node) {
        $('#fs-edit-office').remove();

        balloon.xmlHttpRequest({
            url: balloon.base+'/app/office/document?id='+balloon.id(node),
            success: function(data) {
                var doc = data.data;
                if(doc.session.length === 0) {
                    balloon.office.newSession(node, doc);
                } else {
                    if(doc.session.length === 1) {
                        balloon.office.promptSingleSessionJoin(node, doc, doc.session[0]);
                    } else {
                        balloon.office.promptSelectSessionJoin(node, doc);
                    }
                }
            } 
        });
    },
    
    promptSelectSessionJoin: function(node, doc) {
        $("#fs-office-join-prompt").remove();

        var msg = i18next.t('app.office.session.prompt.message_select', node.name);
        msg += '<ul>';
        for(var i in doc.session) {
            msg += '<li><input type="radio" name="session" value="'+doc.session[i].id+'"/>'
              +i18next.t('app.office.session.prompt.message_select_by_user', doc.session[i].user.name, balloon.timeSince(new Date((doc.session[i].created*1000))))+'</li>';
        }
        msg += '</ul>';

        var $div = $('<div id="fs-office-join-prompt" class="fs-prompt-window" title="'+i18next.t('app.office.session.prompt.title')+'">'
            +'<div id="fs-prompt-window-content">'+msg+'</div>'
            +'<div id="fs-prompt-window-button-wrapper">'
            +'    <input type="button" tabindex="2" name="new" value="'+i18next.t('app.office.session.prompt.new')+'"/>'
            +'    <input type="button" tabindex="1" name="join" value="'+i18next.t('app.office.session.prompt.join')+'"/>'
            +'</div>'
        +'</div>');
        $("#fs-namespace").append($div);
        $div.find('input[name=session]:first').attr('checked', true);
        
        $div.find('input[name=join]').unbind('click').click(function(e) {
            e.stopImmediatePropagation();
            $div.data('kendoWindow').close(); 
            balloon.office.joinSession(node, doc, $div.find('input[name=session]:checked').val());
        });

        balloon.office.sessionPrompt($div, node, doc);
    },

    sessionPrompt: function($div, node, doc)
    {
        var $k_prompt = $div.kendoWindow({
            title: $div.attr('title'),
            resizable: false,
            modal: true,
            activate: function() {
                setTimeout(function() {
                    $div.find('input[name=join]').focus()
                },200);
            }
        }).data("kendoWindow").center().open();

        $div.unbind('keydown').keydown(function(e) {
            if(e.keyCode === 27) {
                e.stopImmediatePropagation();
                $k_prompt.close(); 
            }
        });
    
        $div.find('input[name=new]').unbind('click').click(function(e) {
            e.stopImmediatePropagation();
            $k_prompt.close(); 
            balloon.office.newSession(node, doc);
        });
    },


    promptSingleSessionJoin: function(node, doc, session) {
        $("#fs-office-join-prompt").remove();
        var $div = $('<div id="fs-office-join-prompt" class="fs-prompt-window" title="'+i18next.t('app.office.session.prompt.title')+'">'
            +'<div id="fs-prompt-window-content">'+i18next.t('app.office.session.prompt.message_one', node.name, session.user.name, balloon.timeSince(new Date((session.created*1000))))+'</div>'
            +'<div id="fs-prompt-window-button-wrapper">'
            +'    <input type="button" tabindex="2" name="new" value="'+i18next.t('app.office.session.prompt.new')+'"/>'
            +'    <input type="button" tabindex="1" name="join" value="'+i18next.t('app.office.session.prompt.join')+'"/>'
            +'</div>'
        +'</div>');
        $("#fs-namespace").append($div);
        
        $div.find('input[name=join]').unbind('click').click(function(e) {
            e.stopImmediatePropagation();
            $div.data('kendoWindow').close(); 
            balloon.office.joinSession(node, doc, session.id);
        });
            
        balloon.office.sessionPrompt($div, node, doc);
    },

    newSession: function(node, doc) {
        balloon.xmlHttpRequest({
            url: balloon.base+'/app/office/session?id='+balloon.id(node),
            type: 'POST',
            success: function(session) {
                balloon.office.initLibreOffice(node, doc, session);
            } 
        });
    },

    joinSession: function(node, doc, session_id) {
        balloon.xmlHttpRequest({
            url: balloon.base+'/app/office/session/join?id='+session_id,
            type: 'POST',
            success: function(session) {
                session.data.id = session_id;
                balloon.office.initLibreOffice(node, doc, session);
            } 
        });
    },

    initLibreOffice: function(node, doc, session) {
        var $div = $('<div id="fs-edit-office"></div>');
        $('body').append($div);

        var $k_display = $div.kendoWindow({
             resizable: false,
             title: node.name,
             modal: true,
             draggable: false,
             keydown: function(e) {
                 if(e.originalEvent.keyCode !== 27) {
                     return;
                 }

                 e.stopImmediatePropagation();
                 var msg  = i18next.t('app.office.close_edit_file', node.name);
                 balloon.promptConfirm(msg, function(){
                    balloon.xmlHttpRequest({
                        url: balloon.base+'/app/office/session?id='+session.data.id+'&access_token='+session.data.access_token,
                        type: 'DELETE',
                        error: function(){},
                        complete: function() {
                            $k_display.close();
                            $div.remove();
                        }
                    });
                 });
             },
             open: function(e) {
                balloon.office.showStartupPrompt();   
            
                $('#fs-edit-office_wnd_title').html(
                    $('#fs-browser-tree').find('li[fs-id="'+node.id+'"]').find('.k-in').find('> span').clone()
                );

                var src = 'https://'+window.location.hostname+balloon.base+'/app/office/wopi/document/'+session.data.id,
                    src = encodeURIComponent(src),
                    url = doc.loleaflet+'?WOPISrc='+src+'&title='+node.name+'&lang='+i18next.language+'&closebutton=0&revisionhistory=0';
                
                $div.append(
                  '<form method="post" action="'+url+'" target="loleafletframe">'+
                    '<input type="hidden" name="access_token" value="'+session.data.access_token+'"/>'+
                    '<input type="hidden" name="access_token_ttl" value="'+session.data.access_token_ttl+'"/>'+
                  '</form>'+
                  '<iframe style="width: 100%; height: calc(100% - 40px);" name="loleafletframe"/>'
                );

                $div.find('form').submit();

                 $(this.wrapper).find('.k-i-close').unbind('click.fix').bind('click.fix', function(e){
                     e.stopImmediatePropagation();
                     var msg  = i18next.t('app.office.close_edit_file', node.name);
                     balloon.promptConfirm(msg, function(){
                        balloon.xmlHttpRequest({
                            url: balloon.base+'/app/office/session?id='+session.data.id+'&access_token='+session.data.access_token,
                            type: 'DELETE',
                            error: function(){},
                            complete: function() {
                                $k_display.close();
                                $div.remove();
                            }
                        });
                     });
                 });
            }
         }).data("kendoWindow").center().maximize();
    },

    showStartupPrompt: function(e) {
        if(localStorage.app_office_hide_prompt == "true") {
            return;
        }
        
        $("#fs-libreoffice-prompt").remove();
        
        var $div = $('<div id="fs-libreoffice-prompt" class="fs-prompt-window" title="'+i18next.t('app.office.startup_prompt.title')+'">'
            +'<div id="fs-prompt-window-content">'+i18next.t('app.office.startup_prompt.message')+'</div>'
            +'<div id="fs-prompt-window-button-wrapper">'
            +'    <input type="button" tabindex="2" name="hide" value="'+i18next.t('app.office.startup_prompt.dont_show_again')+'"/>'
            +'    <input type="button" tabindex="1" name="close" value="'+i18next.t('app.office.startup_prompt.close')+'"/>'
            +'</div>'
        +'</div>');
        $("#fs-namespace").append($div);
        
        var $k_prompt = $div.kendoWindow({
            title: $div.attr('title'),
            resizable: false,
            modal: true,
            activate: function() {
                setTimeout(function() {
                    $div.find('input[name=close]').focus()
                },200);
            }
        }).data("kendoWindow");

        setTimeout(function(){
            $k_prompt.center().open();
        }, 700);
        
        $div.unbind('keydown').keydown(function(e) {
            if(e.keyCode === 27) {
                e.stopImmediatePropagation();
                $k_prompt.close(); 
            }
        });

        $div.find('input[name=close]').unbind('click').click(function(e) {
            e.stopImmediatePropagation();
            $k_prompt.close(); 
        });
    
        $div.find('input[name=hide]').unbind('click').click(function(e) {
            localStorage.app_office_hide_prompt = true;
            e.stopImmediatePropagation();
            $k_prompt.close(); 
        });
    }
};

balloon._treeDblclick = function(e) {
    if(balloon.last.directory === true) {
        balloon.resetDom('selected');
    }
        
    var supported_office = [
        'csv', 'odt','ott','ott','docx','doc','dot','rtf','xls','xlsx','xlt','ods','ots','ppt','pptx','odp','otp','potm'
    ];
    
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
    } else if(supported_office.indexOf(balloon.getFileExtension(balloon.last.name)) > -1 && !balloon.isMobileViewPort()) {
        balloon.office.edit(balloon.getCurrentNode());
    } else if(balloon.isEditable(balloon.last.mime)) {
        balloon.editFile(balloon.getCurrentNode());
    } else if(balloon.isViewable(balloon.last.mime)) {
        balloon.displayFile(balloon.getCurrentNode());            
    } else {
        balloon.downloadNode(balloon.getCurrentNode());
    }

    balloon.pushState();
};

balloon.addTextFile = balloon.addFile;

balloon.addFile = function() {
    var $box = $('#fs-new-file');
    if($box.is(':visible')) {
       $box.remove(); 
       return;
    }

    var $select = $('<div id="fs-new-file">'+
        '<div class="fs-icon fs-i-file-add"></div>' +
        '<ul>'+
            '<li><span class="fs-i-file-text fs-icon"></span><span>'+i18next.t('app.office.text_document')+'</span></li>'+
            '<li><span class="fs-i-file-word fs-icon"></span><span>'+i18next.t('app.office.word_document')+'</span></li>'+
            '<li><span class="fs-i-file-excel fs-icon"></span><span>'+i18next.t('app.office.excel_document')+'</span></li>'+
            '<li><span class="fs-i-file-powerpoint fs-icon"></span><span>'+i18next.t('app.office.powerpoint_document')+'</span></li>'+
        '</ul>'+    
    '</div>');

    var $bar = $('#fs-browser-action');
    $bar.append($select);
    $box = $('#fs-new-file');

    $box.on('click', 'li', function(){
        var $type = $(this).find('.fs-icon');

        if($type.hasClass('fs-i-file-text')) {
            balloon.addTextFile();
        } else if($type.hasClass('fs-i-file-word')) {
            balloon.addOfficeFile('docx');
        } else if($type.hasClass('fs-i-file-excel')) {
            balloon.addOfficeFile('xlsx');
        } else if($type.hasClass('fs-i-file-powerpoint')) {
            balloon.addOfficeFile('pptx');
        } 

        $box.remove();
    });

    $(document).off('click.office').on('click.office', function(e) {
        if($(e.target).hasClass('fs-i-file-add')) {
            return;
        }

        var $box = $('#fs-new-file');
        if($box.is(':visible')) {
           $box.remove(); 
        }
    });
};

balloon.addOfficeFile = function(type) {
    var new_name = i18next.t('tree.new_file'),
        name = new_name+'.'+type;
    
    if(balloon.nodeExists(name)) {
        name = new_name+' ('+balloon.randomString(4)+').'+type;
    }
    
    name = encodeURI(name);
    
    balloon.xmlHttpRequest({
        url: balloon.base+'/app/office/document?type='+type+'&name='+name+'&'+balloon.param('collection', balloon.getCurrentCollectionId()),
        type: 'PUT',
        complete: function() {
            $('#fs-new-file').remove();
        },
        success: function(data) {
            balloon.refreshTree('/collection/children', {id: balloon.getCurrentCollectionId()});
             balloon.added_rename = data.data;
        }
    });
};
