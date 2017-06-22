/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */
$(document).init(function(){
    var locale_version = '2017062201';

    var locales = [
        ['en',    'English'],
        ['en-US', 'English (USA)'],
        ['en-AU', 'English (Australia'],
        ['en-GB', 'English (UK)'],
        ['de',    'Deutsch'],
        ['de-CH', 'Deutsch (Schweiz)'],
        ['de-DE', 'Deutsch (Deutschland)'],
        ['de-AT', 'Deutsch (Österreich)']
    ];
    
    if(localStorage.i18next_version !== locale_version) {
        var key;
        for(var i = 0; i < localStorage.length; i++){
            key = localStorage.key(i);
            if(key.substr(0, 12) === 'i18next_res_') {
                localStorage.removeItem(key);
            }
        }
        localStorage.i18next_version = locale_version;
    }

    i18next
        .use(i18nextXHRBackend)
        .use(i18nextBrowserLanguageDetector)
        .use(i18nextLocalStorageCache)
        .use(i18nextSprintfPostProcessor)
        .init({
            postProcess: "sprintf",
            overloadTranslationOptionHandler: i18nextSprintfPostProcessor.overloadTranslationOptionHandler,
            compatibilityJSON: 'v2',
            debug: false,
            cache: {
                enabled: true,
                prefix: 'i18next_res_',
                expirationTime: 60*60*120
            },
            fallbackLng: config.default_lang,
            backend: {
                loadPath: function(lng,ns){
                    if(typeof lng === 'object') {
                        lng = lng[0];
                        var pos = lng.indexOf('-');
                        if(pos !== -1) {
                            lng = lng.substr(0, pos);  
                        }
                    }
                    return '/ui/locale/build.'+lng+'.json?v='+locale_version;
                }
            },   
        }, function() {
            jqueryI18next.init(i18next, $, {
                tName: 't',
                i18nName: 'i18n',
                handleName: 'localize',
                selectorAttr: 'data-i18n',
                targetAttr: 'i18n-target',
                optionsAttr: 'i18n-options',
                useOptionsAttr: false,
                parseDefaultValueFromContent: true
            });
            
            $('[data-i18n]').localize();
            
            login.init();
            if($('#fs-namespace').is(':visible')) {
                balloon.init();
            }
            
            var current = localStorage.i18nextLng;
            kendo.culture(current);
            
            for(lang in locales) {
                $('#login-locale').append('<option value="'+locales[lang][0]+'">'+locales[lang][1]+'</option>')
            }
   
            $('#login-locale option[value='+current+']').attr('selected','selected'); 
            $('#login-locale').unbind('change').change(function(){
                kendo.culture($(this).val());
                i18next.changeLanguage($(this).val(), function(){
                    $('[data-i18n]').localize();
                });
            });
        });
});
