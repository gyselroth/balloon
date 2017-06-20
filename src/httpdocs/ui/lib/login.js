/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */
"use strict";

var login = {
    token: {},
    oauth2: false,
    adapter: null,
    username: null,

    init: function() {
        JSO.enablejQuery(jQuery);
        if(typeof(config) === 'object' && config.oauth2 === true) {
            //this breaks OAUTH2
            //config.jso.redirect_uri += location.search;
            login.oauth2 = new JSO(config.jso);

            if(config.oauth2_login_button !== undefined) {
                $('#login-oauth').show().html('<img src="'+config.oauth2_login_button+'"/>');
            }
        }

        this.checkAuth();
    },

    isOauth2Enabled: function() {
        if(typeof(login.oauth2) === 'object') {
            return true;
        }
        else {
            return false;
        }
    },

    checkAuth: function() {
        if(login.isOauth2Enabled()) {
            login.oauth2.callback(null, function(token) {
                login.token = token;
            });
        }

        var login_helper = function(e) {
            login.destroyAccessToken();
            login.destroyBrowser();     
            var $login = $('#login').show();
            $('#fs-namespace').hide();
            $('#login').find('input[type=submit]').off('click').on('click', login.initBasicAuth);

            if(localStorage.username !== undefined) {
                $login.find('input[name=username]').val(localStorage.username);
            }

            $(document).on('keydown', function(e) {
                if(e.keyCode === 13) {
                    login.initBasicAuth();
                }
            });
                    
            if(login.isOauth2Enabled()) {
                $('#login-oauth').off('click').on('click', login.initOauth2);
            }
        };
       
        var options = {
            type:'GET',
            url: '/api/auth',
            statusCode: {
                500: function(e) {
                    balloon.displayError(e);
                },
                401: login_helper,
                403: login_helper,
                400: function(e) {
                    if(login.getAccessToken() !== false) {
                        login.adapter = 'oauth2';
                    }
                    else {
                        login.adapter = 'basic';
                    }

                    login.setIdentity();
                    login.initBrowser();
                }
            }
        }

        if(login.getAccessToken() != '') {
            options.headers = {
                "Authorization": 'Bearer '+login.getAccessToken(),
            }
        }   

        $.ajax(options);
    },

    logout: function() {
        if(login.adapter == 'oauth2') {
            $.ajax({
                type: 'post',
                cache: false,
                url: config.jso.revoke+'?access_token='+login.getAccessToken(),
                complete: function() {
                    login.destroyAccessToken();
                    login.destroyBrowser();     
                    window.history.pushState('Login', 'Title', '/');
                    login.checkAuth();
                }
            }); 
        }
        else {
            if(navigator.userAgent.indexOf('MSIE') > -1 || navigator.userAgent.indexOf('Edge') > -1) {
                document.execCommand('ClearAuthenticationCache', 'false');
                login.destroyBrowser();     
                login.checkAuth();             
            }
            else {
                $.ajax({
                    url: '/api/v'+balloon.BALLOON_API_VERSION,
                    username: '_logout',
                    password: 'logout',
                    cache: false,
                    statusCode: {
                        401: function() {
                            login.destroyBrowser();     
                            login.checkAuth();             
                        }
                    }
                }); 
            }
        }        
    },

    setIdentity: function() {
        var headers = {};

        if(login.getAccessToken() !== false) {
            headers = {
                "Authorization": 'Bearer '+login.getAccessToken(),
            };
        }
        
        $.ajax({
            url: '/api/v'+balloon.BALLOON_API_VERSION+'/user/whoami',
            headers: headers,
            cache: false,
            success: function(body) {
                login.username = body.data;
                localStorage.username = login.username;
                $('#fs-identity').show().find('#fs-identity-username').html(body.data);        
                
                $('#fs-menu-user-logout').unbind('click').bind('click', function() {
                    login.logout(); 
                });
            }
        });
    },

    getUsername: function() {
        return login.username;
    },

    destroyAccessToken: function() {
        if(config.oauth2 === true) {
            login.oauth2.wipeTokens();
        }

        login.token = {};
    },

    getAccessToken: function()
    {
        if(login.token.access_token === undefined) {
            return false;
        }
        else {
            return login.token.access_token;
        }
    },

    xmlHttpRequest: function(options) {
        if(login.getAccessToken() !== false) {
            return login.oauth2.ajax(options);
        }
        else {
            return $.ajax(options);
        }
    },

    initOauth2: function() {
        login.oauth2.getToken(function(token){login.token = token;});
        login.checkAuth();
    },

    initBasicAuth: function() {
        var username_input = $('#login').find('input[type=text]');
        var password_input = $('#login').find('input[type=password]');

        var username = username_input.val();
        var password = password_input.val();

        if(username == '') {
            username_input.addClass('error');
        }
        if(password == '') {
            password_input.addClass('error');
        }

        if(username == '' || password == '') {
            return;
        } 

        password_input.val('');

        login.doAuth(username, password);
    },

    doAuth: function(username, password) {
        var username_input = $('#login').find('input[type=text]');
        var password_input = $('#login').find('input[type=password]');
        
        $.ajax({
            type: 'GET',
            username: username,
            password: password,
            url: '/api/v1',
            beforeSend: function() {
                username_input.removeClass('error');
                password_input.removeClass('error');    
            },
            statusCode: {
                401: function() {
                    username_input.addClass('error');
                    password_input.addClass('error');
                },
                403: function() {
                    $('#fs-namespace').hide();
                    username_input.addClass('error');
                    password_input.addClass('error');
                },
                200: function() {
                    login.basic_auth = 
                    username_input.removeClass('error');
                    password_input.removeClass('error');
                    login.adapter = 'basic';
                    login.initBrowser();
                    login.setIdentity();
                }
            }
        });
    },

    initBrowser: function() {
        $('#login').hide();
        $('#fs-namespace').show();
        balloon.init();
    },

    destroyBrowser: function() {
        $('#login').show();
        balloon.resetDom();
        $('#fs-namespace').hide();
    },
}
