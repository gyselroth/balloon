/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */
balloon.apps['Balloon.App.Shadow'] = {
    render: function() {
        var $node = $('<li id="fs-view-shadow" style="display: inline-block;" class="fs-view-bar-active">'
                +'<span data-i18n="app.balloon_app_shadow.menu_title"></span>'
            +'</li>');
        
        $('#fs-view-bar').find('ul').append($node);
        balloon.apps['Balloon.App.Shadow'].$menu = $node;

        var $view = $('<div id="fs-shadow" class="fs-view-content">'
                +'<div id="fs-shadow-description" data-i18n="app.balloon_app_shadow.description"></div>'
                +'<div id="fs-shadow-not-supported" data-i18n="app.balloon_app_shadow.not_supported"></div>'
                +'<select name="formats">'
                    +'<option data-i18n="app.balloon_app_shadow.choose_format"></option>'
                +'</select>'
                +'<span class="k-sprite fs-i-add fs-icon"></span>'
                +'<ul></ul>'
                +'<input type="submit" data-i18n="[value]button.save" name="save"/>'
            +'</div>');

        $('#fs-content-data').append($view);
        balloon.apps['Balloon.App.Shadow'].$view = $view;
    },

    init: function()  {
        $('#fs-browser-tree').data('kendoTreeView').bind("select", this.selectNode);
    },

    resetView: function() {
        balloon.apps['Balloon.App.Shadow'].$view.find('li, option[value]').remove();
        balloon.apps['Balloon.App.Shadow'].$view.find('.fs-shadow-not-supported').hide();
    },

    selectNode: function() {
        if(balloon.last.directory || balloon.last.deleted) {
            return;
        }
        
        balloon.apps['Balloon.App.Shadow'].$menu.show().unbind('click').bind('click', function(){
            balloon.apps['Balloon.App.Shadow'].resetView();
            $('.fs-view-content').hide();
            balloon.apps['Balloon.App.Shadow'].$view.show();
            balloon.apps['Balloon.App.Shadow'].loadShadows(balloon.last);
        });
    },
    
    loadShadows: function(node) {
        balloon.xmlHttpRequest({
            url: balloon.base+'/file/convert/shadow',
            type: 'GET',
            dataType: 'json',
            data: {
                id: balloon.id(node)
            },
            success: function(data) {
                var $view = balloon.apps['Balloon.App.Shadow'].$view,
                    $ul = $view.find('ul');

                for(var format in data.data) {
                    let sprite = balloon.getSpriteClass(data.data[format]);
                    $ul.append('<li><span class="k-sprite fs-i-remove fs-icon"></span>'
                    +'<span class="k-sprite '+sprite+' fs-icon"></span>'
                    +'<span>'+data.data[format]+'</span></li>');
                }

                balloon.apps['Balloon.App.Shadow'].loadSupportedFormats(balloon.last, data.data);
            }
        })
    },

    loadSupportedFormats: function(node, formats) {
        balloon.xmlHttpRequest({
            url: balloon.base+'/file/convert/supported-formats',
            type: 'GET',
            dataType: 'json',
            data: {
                id: balloon.id(node)
            },
            success: function(data) {
                var $view = balloon.apps['Balloon.App.Shadow'].$view,
                    $submit = $view.find('input'),
                    $ul = $view.find('ul'),
                    $add = $view.find('.fs-i-add'),
                    $select = $view.find('select');

                if(data.data.length === 0) {
                    $view.find('fs-shadow-not-supported').show();
                    return;
                }

                for(var format in data.data) {
                    if(formats.indexOf(data.data[format]) !== -1) {
                        continue;
                    }
    
                    let sprite = balloon.getSpriteClass(data.data[format]);    
                    $select.append('<option value="'+data.data[format]+'" class="'+sprite+' fs-icon">'
                    +data.data[format]+'</option>');
                }
                
                $add.unbind('click').bind('click', function() {
                    if($select.val() ===  $select.find('option:first-child').val()) {
                        return;
                    }  
                    
                    var sprite = balloon.getSpriteClass($select.val());
                    $ul.append('<li>'
                        +'<span class="k-sprite fs-i-remove fs-icon"></span>'
                        +'<span class="k-sprite '+sprite+' fs-icon"></span><span>'+$select.val()+'</span></li>')
                    $select.find('option[value='+$select.val()+']').remove();
                    $select.find('option:first-child').select();
                });

                $submit.unbind('click').bind('click', function() {
                    var formats = [];
                    $ul.find('li').each(function(){
                        formats.push($(this).find('span:last-child').html());
                    })
                    
                    balloon.apps['Balloon.App.Shadow'].setShadowFormats(node, formats);
                });
            }
        });
    },
    
    setShadowFormats: function(node, formats) {
        balloon.xmlHttpRequest({
            url: balloon.base+'/file/convert/shadow',
            type: 'POST',
            dataType: 'json',
            data: {
                id: balloon.id(node),
                formats: formats
            },
        });
    }
};

$(document).ready(function(e) {
    balloon.apps['Balloon.App.Shadow'].render();
});
