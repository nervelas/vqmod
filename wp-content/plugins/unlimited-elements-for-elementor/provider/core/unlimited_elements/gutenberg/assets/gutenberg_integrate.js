var uelm_WidgetSettingsCache = [];
var uelm_WidgetSettingsCacheFlags = [];

(function (wp) {
    
	var g_debug = true;
    
    function trace(str){ console.log(str); }
    function debug(){ if(!g_debug) return; console.log.apply(console, arguments); }
    
    var wbe = wp.blockEditor;
    var wc  = wp.components;
    var wd  = wp.data;
    var we  = wp.element;
    var el  = we.createElement;

    jQuery(document).on("click", ".ue-gutenberg-widget-wrapper", function () {
        jQuery(this).closest("[tabindex]").focus();
    });
    // prevent navigation inside preview
    jQuery(document).on("click", ".ue-gutenberg-widget-wrapper a", function (event) {
        event.preventDefault();
    });

    jQuery(function(){ jQuery("body").append("<div id='div_debug' class='unite-div-debug'></div>"); });

    var edit = function (props) {

        var previewUrl = props.attributes._preview;
        if (previewUrl)
            return el("img", { src: previewUrl, style: { width: "100%", height: "auto" } });

        const blockProps = wbe.useBlockProps();

        // block options cache key
        const cacheKeyBase = props.clientId || props.attributes._id;

        // state
        const [widgetContent,     setWidgetContent]     = we.useState(null);
        const [settingsVisible,   setSettingsVisible]   = we.useState(false);
        const [settingsContent,   setSettingsContent]   = we.useState(null);
        const [isLoadingSettings, setIsLoadingSettings] = we.useState(false);

        // refs
        const widgetRef         = we.useRef(null);
        const widgetLoaderRef   = we.useRef(null);
        const widgetRequestRef  = we.useRef(null);

        const ucSettingsRef     = we.useRef(new UniteSettingsUC());
        const ucHelperRef       = we.useRef(new UniteCreatorHelper());

        const settingsInitedRef        = we.useRef(false);
        const initedSettingsElementRef = we.useRef(null);
        const lastSentDataRef          = we.useRef(null);
        const settingsObserverRef      = we.useRef(null);

        const ucSettings = ucSettingsRef.current;
        const ucHelper   = ucHelperRef.current;

        // orchestrator refs
        const didFirstPreviewRef    = we.useRef(false);
        const firstPreviewReadyRef  = we.useRef(false);
        const lastPreviewPayloadRef = we.useRef(null);
        const firstPreviewTimerRef  = we.useRef(null);

        function isSettingsReady() {
            return !!(settingsInitedRef.current &&
                      ucSettings &&
                      typeof ucSettings.isInited === 'function' &&
                      ucSettings.isInited());
        }

        function getWidgetRootEl(){
            const $root = jQuery(widgetRef.current);

            const $inner = $root.find('[data-uc-root]').first();
            return ($inner.length ? $inner : $root);
        }

        function applyStylePreviewOnce(maxTries = 30, delay = 50){

            if (!props.isSelected) {
                return;
            }

            let tries = 0;
            (function tick(){
                const $root = jQuery(widgetRef.current);
                const ready = isSettingsReady();

                if ($root.length && ready) {
                    updateSelectorsPreview();
                    return;
                }
                if (++tries < maxTries) setTimeout(tick, delay);
            })();
        }

        // --- delay for color/range/dimensions ---
        const saveDelayTimerRef = we.useRef(null);
        const lastChangeTypeRef    = we.useRef(null);
        const suppressNextReloadRef= we.useRef(false);

        const lastSelectorsCssRef  = we.useRef('');
        const lastIncludesHashRef  = we.useRef('');

        function applyLiveBoxModelInline(){
        	
            try{
                if (!isSettingsReady()) return;
                const vals = ucSettings.getSettingsValues ? ucSettings.getSettingsValues() : {};
                const pad  = vals?.advanced_padding || vals?.advanced_padding_row || vals?.padding || null;
                const mar  = vals?.advanced_margin  || vals?.advanced_margin_row  || vals?.margin  || null;

                const $root = getWidgetRootEl();
                if (!$root.length) return;

                const style = $root[0].style;

                function setBox(v, prefix){
                    if (!v || typeof v !== 'object') return;
                    const unit = v.unit || 'px';
                    const set = (prop, key) => {
                        if (v[key] === '' || typeof v[key] === 'undefined' || v[key] === null) {
                            style.removeProperty(prop);
                        } else {
                            style.setProperty(prop, String(v[key]) + unit);
                        }
                    };
                    set(prefix+'-top',    'top');
                    set(prefix+'-right',  'right');
                    set(prefix+'-bottom', 'bottom');
                    set(prefix+'-left',   'left');
                }

                setBox(pad, 'padding');
                setBox(mar, 'margin');
            }catch(e){}
        }

        // update oldCss with props from newCss
        function mergeCss(oldCss, newCss) {
            function createEmptyMap() {
                return {
                    scopesOrder: [],         
                    selectorsOrder: {},       
                    rules: {}               
                };
            }

            function ensureScope(map, scope) {
                if (!map.rules[scope]) {
                    map.rules[scope] = {};
                    map.selectorsOrder[scope] = [];
                    map.scopesOrder.push(scope);
                }
            }

            function addRuleToMap(map, scope, selector, body) {
                selector = selector && selector.trim();
                if (!selector) return;

                var decls = {};
                var parts = body.split(';');
                for (var i = 0; i < parts.length; i++) {
                    var part = parts[i].trim();
                    if (!part) continue;
                    var colonPos = part.indexOf(':');
                    if (colonPos === -1) continue;
                    var prop = part.slice(0, colonPos).trim();
                    var val  = part.slice(colonPos + 1).trim();
                    if (!prop || !val) continue;
                    decls[prop] = val;
                }

                if (!Object.keys(decls).length) return;

                ensureScope(map, scope);
                if (!map.rules[scope][selector]) {
                    map.rules[scope][selector] = {};
                    map.selectorsOrder[scope].push(selector);
                }

                var target = map.rules[scope][selector];
                for (var p in decls) {
                    target[p] = decls[p];
                }
            }

            function parseSimpleRulesInto(map, scope, css) {
                var len = css.length;
                var i = 0;
                while (i < len) {

                    while (i < len && /\s|;/.test(css[i])) i++;
                    if (i >= len) break;

                    if (css[i] === '}') {
                        i++;
                        continue;
                    }

                    var selStart = i;
                    while (i < len && css[i] !== '{' && css[i] !== '}') i++;
                    if (i >= len || css[i] === '}') {
                        i++;
                        continue;
                    }
                    var selector = css.slice(selStart, i).trim();
                    i++; 

                    var bodyStart = i;
                    var depth = 1;
                    while (i < len && depth > 0) {
                        if (css[i] === '{') depth++;
                        else if (css[i] === '}') depth--;
                        i++;
                    }
                    var body = css.slice(bodyStart, i - 1);

                    addRuleToMap(map, scope, selector, body);
                }
            }

            function parseCssToMap(css) {
                var map = createEmptyMap();
                if (!css) return map;

                var len = css.length;
                var i = 0;

                while (i < len) {

                    while (i < len && /\s|;/.test(css[i])) i++;
                    if (i >= len) break;

                    if (css[i] === '@') {
                        var atStart = i;
                        while (i < len && css[i] !== '{') i++;
                        if (i >= len) break;
                        var prelude = css.slice(atStart, i).trim(); 
                        i++; // '{'

                        var bodyStart = i;
                        var depth = 1;
                        while (i < len && depth > 0) {
                            if (css[i] === '{') depth++;
                            else if (css[i] === '}') depth--;
                            i++;
                        }
                        var body = css.slice(bodyStart, i - 1);

                        parseSimpleRulesInto(map, prelude, body);
                    } else if (css[i] === '}') {
                        i++;
                        continue;
                    } else {

                        var selStart = i;
                        while (i < len && css[i] !== '{' && css[i] !== '}') i++;
                        if (i >= len || css[i] === '}') {
                            i++;
                            continue;
                        }
                        var selector = css.slice(selStart, i).trim();
                        i++; 

                        var bodyStart2 = i;
                        var depth2 = 1;
                        while (i < len && depth2 > 0) {
                            if (css[i] === '{') depth2++;
                            else if (css[i] === '}') depth2--;
                            i++;
                        }
                        var body2 = css.slice(bodyStart2, i - 1);

                        addRuleToMap(map, '', selector, body2);
                    }
                }

                return map;
            }

            function mapToCss(map) {
                var out = '';

                for (var s = 0; s < map.scopesOrder.length; s++) {
                    var scope = map.scopesOrder[s];
                    var selectors = map.selectorsOrder[scope];
                    if (!selectors || !selectors.length) continue;

                    if (scope === '') {

                        for (var i = 0; i < selectors.length; i++) {
                            var sel = selectors[i];
                            var props = map.rules[scope][sel];
                            var keys = Object.keys(props);
                            if (!keys.length) continue;
                            out += sel + '{';
                            for (var k = 0; k < keys.length; k++) {
                                var prop = keys[k];
                                out += prop + ':' + props[prop] + ';';
                            }
                            out += '}';
                        }
                    } else {

                        var inner = '';
                        for (var j = 0; j < selectors.length; j++) {
                            var sel2 = selectors[j];
                            var props2 = map.rules[scope][sel2];
                            var keys2 = Object.keys(props2);
                            if (!keys2.length) continue;
                            inner += sel2 + '{';
                            for (var k2 = 0; k2 < keys2.length; k2++) {
                                var prop2 = keys2[k2];
                                inner += prop2 + ':' + props2[prop2] + ';';
                            }
                            inner += '}';
                        }
                        if (inner) {
                            out += scope + '{' + inner + '}';
                        }
                    }
                }

                return out;
            }

            var oldMap = parseCssToMap(oldCss || '');
            var newMap = parseCssToMap(newCss || '');

            for (var s = 0; s < newMap.scopesOrder.length; s++) {
                var scope = newMap.scopesOrder[s];
                var newSelectors = newMap.selectorsOrder[scope];
                if (!newSelectors || !newSelectors.length) continue;

                ensureScope(oldMap, scope);

                for (var i = 0; i < newSelectors.length; i++) {
                    var sel = newSelectors[i];
                    var newProps = newMap.rules[scope][sel];

                    if (!oldMap.rules[scope][sel]) {
                        oldMap.rules[scope][sel] = {};
                        oldMap.selectorsOrder[scope].push(sel);
                    }

                    var target = oldMap.rules[scope][sel];
                    for (var p in newProps) {
                        target[p] = newProps[p];
                    }
                }
            }

            let ret = mapToCss(oldMap);

            return ret;
        }

       /**
        * update selectors preview
        */
        function updateSelectorsPreview() {
        	
        	var isDetailedDebug = false;
        	
        	debug("run func: updateSelectorsPreview");
        	                    	
            if (!props.isSelected) {
            	
            	if(isDetailedDebug == true)
            		trace("not selected, exit");
            	
                return;
            }

            if (!isSettingsReady()) {
            	
            	if(isDetailedDebug == true)
            		trace("not ready, exit");
            	
                return;
            }
            const $root = jQuery(widgetRef.current);
            if ($root.length === 0) {
            	
            	if(isDetailedDebug == true)
            		trace("not root, exit");
            	
                return;
            }
                        
            const css = (ucSettings.getSelectorsCss && ucSettings.getSelectorsCss()) || '';
            
            if (css && css !== lastSelectorsCssRef.current) {

                if ($root.find("[name=uc_selectors_css]").length === 0) {
                    $root.prepend('<style name="uc_selectors_css"></style>');
                }
                const $style = $root.find('[name=uc_selectors_css]');
                const prevCss = $style.text() || '';

                const mergedCss = mergeCss(prevCss, css);

                $style.text(mergedCss);
                lastSelectorsCssRef.current = mergedCss;
                
            	if(isDetailedDebug == true)
            		trace("merge css!");
                
            }else{
            	
            	if(isDetailedDebug == true)
            		trace("not root, exit");
            	
            }

            const includes = ucSettings.getSelectorsIncludes && ucSettings.getSelectorsIncludes();
            const includesHash = stableStringify(includes || {});
            if (includes && includesHash !== lastIncludesHashRef.current) {
                ucHelper.putIncludes(getPreviewWindowElement(), includes);
                lastIncludesHashRef.current = includesHash;
            }

            applyLiveBoxModelInline();

        }
        
        /**
         * flush save new
         * @param typeOverride
         */
        function flushSaveNow(typeOverride) {
            if (!isSettingsReady()) return;
            if (saveDelayTimerRef.current) {
                clearTimeout(saveDelayTimerRef.current);
                saveDelayTimerRef.current = null;
            }
            try {
                const currentObj = (function(){
                    try { return props.attributes.data ? JSON.parse(props.attributes.data) : {}; }
                    catch(e){ return {}; }
                })();
                const snapshot  = ucSettings.getSettingsValues() || {};
                const mergedObj = { ...currentObj, ...snapshot };
                const mergedStr = JSON.stringify(mergedObj);

                if (lastSentDataRef.current === mergedStr) return;

                try {
                    delete uelm_WidgetSettingsCache[cacheKeyBase];
                    delete uelm_WidgetSettingsCache[cacheKeyBase + '_settings'];

                    uelm_WidgetSettingsCacheFlags[cacheKeyBase] = false;
                    uelm_WidgetSettingsCacheFlags[cacheKeyBase + '_settings'] = false;
                } catch(e){}

                lastSentDataRef.current = mergedStr;

                const tRaw = (typeOverride ?? lastChangeTypeRef.current ?? '').toString().toLowerCase().trim();

                suppressNextReloadRef.current = (tRaw == 'styles'); // suppress block reloading

                props.setAttributes({ data: mergedStr });

            } finally { }
        }

        var isEditorSidebarOpened = wd.useSelect(function (select) {
            return select("core/edit-post").isEditorSidebarOpened();
        });
        var activeGeneralSidebarName = wd.useSelect(function (select) {
            return select("core/edit-post").getActiveGeneralSidebarName();
        });
        var previewDeviceType = wd.useSelect((select) => {
            const editor = select(wp.editPost?.store || "core/edit-post");
            return editor.getDeviceType?.() || editor.__experimentalGetPreviewDeviceType?.() || "Desktop";
        }, []);

        var widgetId      = "ue-gutenberg-widget-"   + props.clientId;
        var settingsId    = "ue-gutenberg-settings-" + props.clientId;
        var settingsErrorId = settingsId + "-error";

        function stableStringify(obj){
            try { return JSON.stringify(obj, Object.keys(obj).sort()); }
            catch(e){ return ""; }
        }

        var getSettings = function () {
            try { return props.attributes.data ? JSON.parse(props.attributes.data) : null; }
            catch (e) { return null; }
        };
        var getSettingsElement = function () {
            return jQuery("#" + settingsId);
        };
        var getPreviewWindowElement = function () {
            return window.frames["editor-canvas"] || window;
        };

        var initSettings = function () {
        	
        	debug("init settings!");
        	
            var $settingsElement = getSettingsElement();
            if (!$settingsElement || $settingsElement.length === 0) return;

            var elem = $settingsElement[0];

            if (ucSettings.isInited() && initedSettingsElementRef.current === elem) {
                return;
            }

            if (ucSettings.isInited() && initedSettingsElementRef.current !== elem) {
                ucSettings.destroy();
            }

            // get values before init
            var values = getSettings();

            if (values === null) {
                try {
                    // get defaults from data-itemvalues
                    var defaultValues = {};
                    
                    $settingsElement.find('.unite-setting-repeater').each(function() {
                        var $repeater = jQuery(this);
                        var repeaterName = $repeater.attr('name') || $repeater.data('name');
                        var defaultItems = $repeater.data('itemvalues');
                        
                        if (repeaterName && defaultItems && Array.isArray(defaultItems) && defaultItems.length > 0) {
                            defaultValues[repeaterName] = defaultItems;
                            debug('from integrate: loaded default items for', repeaterName);
                            debug(defaultItems);
                        }
                    });
                    
                    // if defaults exists then use it
                    if (Object.keys(defaultValues).length > 0) {
                        values = defaultValues;
                    }
                    
                } catch (e) {
                    console.warn('Failed to get default settings before init', e);
                    values = null;
                }
            }

            ucSettings.init($settingsElement, { cacheValues: values });
            initedSettingsElementRef.current = elem;

            // apply show/hide rules based on initial setting values
            ucSettings.runInitialControls();

            ucSettings.setSelectorWrapperID(widgetId);
            ucSettings.setResponsiveType(previewDeviceType.toLowerCase());

            function handleSettingsEvent(evt, payload) {

                const name = (payload?.name ?? '').toString();

                const type = (payload?.type ?? '').toString().toLowerCase().trim();
                const STYLE_ONLY_TYPES = ['color','range','dimensions'];
                const $c = jQuery('#' + settingsId +' [data-name="' + payload.name + '"] [data-selectors]');

                if (STYLE_ONLY_TYPES.includes(type) && $c.length > 0) {
                    // style only fields
                    lastChangeTypeRef.current = 'styles';

                    if (saveDelayTimerRef.current) {
                        clearTimeout(saveDelayTimerRef.current);
                        saveDelayTimerRef.current = null;
                    }
                    saveDelayTimerRef.current = setTimeout(() => {
                        flushSaveNow('styles'); 
                    }, 180);
                } else {
                    // content fields (reload block preview)
                    lastChangeTypeRef.current = 'content';

                    if (saveDelayTimerRef.current) {
                        clearTimeout(saveDelayTimerRef.current);
                        saveDelayTimerRef.current = null;
                    }
                    flushSaveNow(type); 
                }
            }
            
            ucSettings.setEventOnChange(handleSettingsEvent);
            if (typeof ucSettings.onEvent === 'function') {
                ucSettings.onEvent('settings_instant_change', handleSettingsEvent);
            }

            ucSettings.setEventOnSelectorsChange(function () {
            	
                if (!isSettingsReady()) 
                	return;

                updateSelectorsPreview();

                lastChangeTypeRef.current = 'styles';

                flushSaveNow('styles');
            });

            ucSettings.setEventOnResponsiveTypeChange(function (event, type) {
                uelm_WidgetSettingsCacheFlags[cacheKeyBase] = true;
                uelm_WidgetSettingsCacheFlags[cacheKeyBase + '_settings'] = true;

                var deviceType = type.charAt(0).toUpperCase() + type.substring(1);
                const editorStore = wp.editPost?.store || "core/edit-post";
                const dispatcher = wp.data.dispatch(editorStore);

                if (typeof dispatcher.setDeviceType === "function") {
                    dispatcher.setDeviceType(deviceType);
                } else if (typeof wp.data.dispatch("core/edit-post").__experimentalSetPreviewDeviceType === "function") {
                    wp.data.dispatch("core/edit-post").__experimentalSetPreviewDeviceType(deviceType);
                }
            });

            if (values !== null) {
                debug('from integrate: setting cache after init');
                debug(values);
                ucSettings.setCacheValues(values);
                
                // save attrs for the new block
                if (!props.attributes.data) {
                    props.setAttributes({ data: JSON.stringify(values) });
                }
            }

            settingsInitedRef.current = true;
        };

        // init settings when panel visible + settings HTML ready + real DOM exists
        function maybeInitSettings(){
        	
            if (!settingsVisible) 
            	return;
            
            if (!settingsContent) 
            	return;
            
            var $settingsElement = getSettingsElement();
            if (!$settingsElement || $settingsElement.length === 0) return;

            var elem = $settingsElement[0];
            if (!ucSettings.isInited() || initedSettingsElementRef.current !== elem) {
                initSettings();
            }
        }

        function attachSettingsObserver(){
            detachSettingsObserver();
            var observer = new MutationObserver(function(){
                maybeInitSettings();
            });
            observer.observe(document.body, { childList: true, subtree: true });
            settingsObserverRef.current = observer;
        }

        function detachSettingsObserver(){
            if (settingsObserverRef.current) {
                settingsObserverRef.current.disconnect();
                settingsObserverRef.current = null;
            }
        }

        /**
         * generate settings panel HTML from server JSON (json.settings / json.saps / json.controls / json.render_options)
         */
        function generateSettingsHtmlFromJson(json, isForDialog) {
            if (isForDialog === undefined) isForDialog = false;

            function expandResponsiveSettings(arr) {
                var result = [];
                arr.forEach(function(s) {
                    var hasTab = s._tablet !== undefined;
                    var hasMob = s._mobile !== undefined;
                    if (!hasTab && !hasMob) { result.push(s); return; }
                    var base = Object.assign({}, s);
                    delete base._tablet;
                    delete base._mobile;
                    result.push(base);
                    if (hasTab) {
                        var t = Object.assign({}, base, s._tablet);
                        Object.keys(s._tablet).forEach(function(k) { if (t[k] === null) delete t[k]; });
                        result.push(t);
                    }
                    if (hasMob) {
                        var m = Object.assign({}, base, s._mobile);
                        Object.keys(s._mobile).forEach(function(k) { if (m[k] === null) delete m[k]; });
                        result.push(m);
                    }
                });
                return result;
            }

            var settings          = expandResponsiveSettings(json.settings || []);
            var saps              = json.saps               || [];
            var controls          = json.controls           || {};
            var renderOptions     = json.render_options     || {};
            var repeaterTemplates = json.repeater_templates || {};

            var idPrefix    = renderOptions.id_prefix  || '';
            var showSaps    = (renderOptions.show_saps !== false);
            var isAccordion = ((renderOptions.saps_type || '') === 'saps_type_accordion');

            var serial    = String(Date.now()).slice(-8) + String(Math.random()).slice(2, 5);
            var wrapperID = 'uc_form_settings_addon_' + serial;

            // escape HTML attribute value
            function ea(v) {
                return String(v == null ? '' : v)
                    .replace(/&/g,'&amp;').replace(/"/g,'&quot;')
                    .replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }
            // escape HTML text
            function eh(v) {
                return String(v == null ? '' : v)
                    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }

            // build data-test / data-default / data-initval + responsive + selectors attrs
            function defaultAttrs(s) {
                var def = s.default_value;

                var val = (s.value !== undefined) ? s.value : s.default_value;
                if (def !== null && typeof def === 'object') def = JSON.stringify(def);
                if (val !== null && typeof val === 'object') val = JSON.stringify(val);
                var str = ' data-test="' + ea(def) + '" data-default="' + ea(def) + '" data-initval="' + ea(val) + '"';
                if (s.is_responsive) {
                    str += ' data-isresponsive="true"';
                    str += ' data-responsivetype="' + ea(s.responsive_type || '') + '"';
                    str += ' data-responsiveid="'   + ea(s.responsive_id   || '') + '"';
                }

                var selData = {};
                Object.keys(s).forEach(function(k) {
                    if (k.indexOf('selector') !== -1 && s[k]) selData[k] = s[k];
                });
                if (Object.keys(selData).length) {
                    str += ' data-selectors="' + ea(JSON.stringify(selData)) + '"';
                }
                return str;
            }

            // render the type-specific input element(s)
            function renderInput(s) {
                var t    = s.type;

                var id   = s.id   || (idPrefix + (s.name || ''));
                var name = s.name || '';
                var val  = (s.value !== undefined) ? s.value
                         : (s.default_value !== null && s.default_value !== undefined ? s.default_value : '');

                // --- color ---
                if (t === 'color') {
                    var v = String(val).replace('0x', '#');
                    return '<div class="unite-color-picker-wrapper">' +
                        '<input type="text" class="unite-color-picker"' +
                        ' id="' + ea(id) + '" name="' + ea(name) + '" value="' + ea(v) + '"' +
                        defaultAttrs(s) + '></input></div>';
                }

                // --- text ---
                if (t === 'text') {
                    var inputType = 'text';
                    if (s.type_number) inputType = 'number';
                    if (s.ispassword)  inputType = 'password';
                    var units = s.units || (s.unit ? [s.unit] : []);
                    var wrapCls  = units.length ? ' with-units' : '';
                    var inputCls = units.length ? 'unite-input-number' : 'unite-input-regular';
                    if(s.class) {
                        inputCls = s.class;
                    }
                    var h = '<div class="unite-input-wrapper' + wrapCls + '">';
                    h += '<input class="' + inputCls + '"';
                    h += ' id="' + ea(id) + '" type="' + inputType + '"';
                    h += ' name="' + ea(name) + '" value="' + ea(String(val)) + '"';
                    h += defaultAttrs(s);
                    if (s.placeholder) h += ' placeholder="' + ea(s.placeholder) + '"';
                    if (s.disabled)    h += ' disabled';
                    if (s.readonly)    h += ' readonly';
                    h += ' /></div>';

                    if(name == 'number_of_items') { 
                        console.log('D: ');
                        console.log(units);
                        console.log(s);
                    }
                    return h;
                }

                // --- list (select) ---
                if (t === 'list') {
                    var items = s.items || {};
                    // for font_family, use global fonts if items not provided
                    if (name === 'font_family' && !s.items && typeof g_ucGoogleFonts !== 'undefined' && g_ucGoogleFonts.fonts) {
                        items = Object.keys(g_ucGoogleFonts.fonts).reduce(function(acc, font) {
                            acc[font] = font;
                            return acc;
                        }, {});
                    }
                    var h = '<select id="' + ea(id) + '" name="' + ea(name) + '"' + defaultAttrs(s) + '>';
                    Object.keys(items).forEach(function(label) {
                        var optVal = items[label];
                        var sel = (String(optVal) === String(val)) ? ' selected' : '';
                        h += '<option value="' + ea(optVal) + '"' + sel + '>' + eh(label) + '</option>';
                    });
                    h += '</select>';
                    return h;
                }

                // --- switcher ---
                if (t === 'switcher') {
                    var items     = s.items || [];
                    var itemVals  = Array.isArray(items) ? items : Object.values(items);
                    var uncheckVal = itemVals.length > 0 ? itemVals[0] : '';
                    var checkVal   = itemVals.length > 1 ? itemVals[1] : '1';
                    return '<div id="' + ea(id) + '" class="unite-setting-switcher unite-setting-input-object"' +
                        ' data-settingtype="switcher" data-name="' + ea(name) + '"' +
                        ' data-value="' + ea(String(val)) + '"' +
                        ' data-checkedvalue="' + ea(checkVal) + '"' +
                        ' data-uncheckedvalue="' + ea(uncheckVal) + '"' +
                        defaultAttrs(s) + '>' +
                        '<div class="unite-setting-switcher-toggle"></div></div>';
                }

                // --- range ---
                if (t === 'range') {
                    var rawVal    = val;
                    var min       = s.min  !== undefined ? s.min  : 0;
                    var max       = s.max  !== undefined ? s.max  : 0;
                    var step      = s.step !== undefined ? s.step : 1;
                    var units     = s.units || [];
                    var unit      = units.length ? units[0] : 'px';
                    var showSlider = (s.show_slider !== false);

                    var sCopy = Object.assign({}, s, {
                        default_value: {size: s.default_value, unit: unit},
                        value:         {size: rawVal,          unit: unit}
                    });
                    var wrapCls = 'unite-setting-range unite-setting-input-object unite-settings-exclude';
                    if (showSlider)   wrapCls += ' with-slider';
                    if (units.length) wrapCls += ' with-units';
                    var h = '<div id="' + ea(id) + '" class="' + wrapCls + '"' +
                        ' data-settingtype="range" data-name="' + ea(name) + '"' +
                        defaultAttrs(sCopy) + '>';
                    if (showSlider) {
                        h += '<div class="unite-setting-range-slider t1"' +
                            ' data-value="' + ea(rawVal) + '"' +
                            ' data-min="' + ea(min) + '" data-max="' + ea(max) + '" data-step="' + ea(step) + '"' +
                            '></div>';
                    }
                    h += '<input class="unite-setting-range-input" type="number" value="' + ea(rawVal) + '" />';
                    if (units.length) {
                        h += '<div class="unite-setting-range-units">';
                        h += '<select class="unite-units-picker" data-value="' + ea(unit) + '"></select>';
                        h += '</div>';
                    }
                    h += '</div>';
                    return h;
                }

                // --- dimentions ---
                if (t === 'dimentions') {
                    var defVal = s.default_value;
                    var curVal = val;
                    if (typeof curVal === 'string') { try { curVal = JSON.parse(curVal); } catch(e) { curVal = {}; } }
                    if (!curVal || typeof curVal !== 'object') curVal = {};
                    if (!defVal || typeof defVal !== 'object') defVal = {};
                    var emptyDim = {top:'',right:'',bottom:'',left:'',unit:'px',is_linked:true};
                    curVal = Object.assign({}, emptyDim, defVal, curVal);

                    var dims   = ['top','right','bottom','left'];
                    var labels = {top:'Top',right:'Right',bottom:'Bottom',left:'Left'};
                    var isLinked = (curVal.is_linked !== false && curVal.is_linked !== 'false');

                    var h = '<div class="unite-dimentions unite-setting-input-object unite-settings-exclude"' +
                        ' data-settingtype="dimentions" data-name="' + ea(name) + '"' +
                        defaultAttrs(s) + '>';
                    dims.forEach(function(key) {
                        var fid  = id + '-' + key;
                        var fval = curVal[key] !== undefined ? curVal[key] : '';
                        h += '<div class="unite-dimentions-field">';
                        h += '<input class="unite-dimentions-field-input"' +
                            ' id="' + ea(fid) + '" type="number" name="" value="' + ea(fval) + '"' +
                            ' data-key="' + key + '" />';
                        h += '<label class="unite-dimentions-field-label" for="' + ea(fid) + '">' + labels[key] + '</label>';
                        h += '</div>';
                    });
                    h += '<div class="unite-dimentions-units">';
                    h += '<select class="unite-units-picker" data-value="' + ea(curVal.unit || 'px') + '"></select>';
                    h += '</div>';
                    h += '<div class="unite-dimentions-link unite-setting-button uc-tip' + (isLinked ? ' unite-active' : '') + '"' +
                        ' data-key="is_linked" data-title-link="Link Values" data-title-unlink="Unlink Values">';
                    h += '<svg class="unite-dimentions-icon-link" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12">' +
                        '<path d="m3.5 8.5 5-5M5 3l1.672-1.672a2.829 2.829 0 0 1 4 4L9 7M3 5 1.328 6.672a2.829 2.829 0 0 0 4 4L7 9" /></svg>';
                    h += '<svg class="unite-dimentions-icon-unlink" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12">' +
                        '<path d="m5.5 2.5 1.172-1.172a2.829 2.829 0 0 1 4 4L9.5 6.5M2.5 5.5 1.328 6.672a2.829 2.829 0 0 0 4 4L6.5 9.5M3.5 8.5l1-1M7.5 4.5l1-1M.5.5l11 11" /></svg>';
                    h += '</div>';
                    h += '</div>';
                    return h;
                }

                // --- button ---
                if (t === 'button') {
                    var href    = s.url || '#';
                    var btnCls  = s['class'] || 'unite-button-secondary';
                    var target  = s.newwindow ? ' target="_blank"' : '';
                    return '<a id="' + ea(id) + '" href="' + ea(href) + '" name="' + ea(name) + '"' +
                        target + ' class="' + ea(btnCls) + '">' + eh(String(val)) + '</a>';
                }

                // --- image ---
                if (t === 'image') {
                    var toImgObj = function(v) {
                        if (v && typeof v === 'object') return Object.assign({id: null, url: ''}, v);
                        return {id: null, url: (v && typeof v === 'string' ? v : '')};
                    };
                    var imgDef = toImgObj(s.default_value);
                    var imgVal = toImgObj(s.value !== undefined ? s.value : s.default_value);
                    var sCopyImg = Object.assign({}, s, {default_value: imgDef, value: imgVal});
                    return '<div id="' + ea(id) + '"' +
                        ' class="unite-setting-image unite-setting-input-object unite-settings-exclude"' +
                        ' data-settingtype="image" data-name="' + ea(name) + '"' +
                        ' data-urlname="" data-urlvalue="" data-sizename="" data-sizevalue="full"' +
                        defaultAttrs(sCopyImg) + '> </div>';
                }

                // --- icon ---
                if (t === 'icon') {
                    var iconsType = s.icons_type || '';
                    var iconsTypeAttr = iconsType ? ' data-icons_type="' + ea(iconsType) + '"' : '';
                    var h = '<div class="unite-iconpicker">';
                    h += '<div class="unite-setting-buttons-group">';
                    h += '<div class="unite-iconpicker-button unite-setting-button uc-tip" title="None" data-action="none">';
                    h += '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12">';
                    h += '<path d="m2.111 9.889 7.778-7.778" />';
                    h += '<path d="M6 11.5a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11Z" /></svg></div>';
                    h += '<div class="unite-iconpicker-button unite-setting-button uc-tip" title="Upload SVG" data-action="upload">';
                    h += '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12">';
                    h += '<path d="M2.5.5H2A1.5 1.5 0 0 0 .5 2v8A1.5 1.5 0 0 0 2 11.5h8a1.5 1.5 0 0 0 1.5-1.5V2A1.5 1.5 0 0 0 10 .5h-.5M.5 8.5h11" />';
                    h += '<path d="M3.5 3 6 .5 8.5 3M6 6.5v-6" /></svg>';
                    h += '<img class="unite-iconpicker-uploaded-icon" src="" alt="" /></div>';
                    h += '<div class="unite-iconpicker-button unite-setting-button uc-tip" title="Icon Library" data-action="library">';
                    h += '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 11">';
                    h += '<path d="m5.5 2.5-1-2h-4v9a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-7h-6Z" /></svg>';
                    h += '<div class="unite-iconpicker-library-icon"></div></div>';
                    h += '</div>'; 
                    h += '<div class="unite-iconpicker-error unite-setting-error"></div>';
                    h += '<input id="' + ea(id) + '" type="hidden" name="' + ea(name) + '"';
                    h += ' value="' + ea(String(val)) + '" class="unite-iconpicker-input"';
                    h += defaultAttrs(s) + iconsTypeAttr + ' />';
                    h += '</div>'; 
                    return h;
                }

                // --- hidden ---
                if (t === 'hidden') {
                    return '<input type="hidden" id="' + ea(id) + '" name="' + ea(name) + '"' +
                        ' value="' + ea(String(val)) + '"' + defaultAttrs(s) + ' />';
                }

                // --- textarea ---
                if (t === 'textarea') {
                    return '<textarea id="' + ea(id) + '" name="' + ea(name) + '"' +
                        defaultAttrs(s) + '>' + eh(String(val)) + '</textarea>';
                }

                // --- editor (rendered as plain textarea; TinyMCE not available client-side) ---
                if (t === 'editor') {
                    return '<div class="unite-editor-setting-wrapper">' +
                        '<textarea id="' + ea(id) + '" name="' + ea(name) + '"' +
                        defaultAttrs(s) + '>' + eh(String(val)) + '</textarea>' +
                        '</div>';
                }

                // --- repeater ---
                if (t === 'repeater') {
                    var i18n = (wp && wp.i18n && wp.i18n.__) ? wp.i18n.__ : function(v) { return v; };
                    var itemTitle     = s.item_title          || i18n('Item',            'unlimited-elements-for-elementor');
                    var addText       = s.add_button_text     || i18n('Add Item',        'unlimited-elements-for-elementor');
                    var emptyText     = s.empty_text          || i18n('No items found.', 'unlimited-elements-for-elementor');
                    var deleteText    = s.delete_button_text  || i18n('Delete',          'unlimited-elements-for-elementor');
                    var duplicateText = s.duplicate_button_text || i18n('Duplicate',     'unlimited-elements-for-elementor');
                    var templateHtml  = repeaterTemplates[name] || '';
                    var itemsVal      = s.items_values;
                    var itemsJson     = (itemsVal && itemsVal.length) ? JSON.stringify(itemsVal) : '';
                    var h = '<div id="' + ea(id) + '" class="unite-setting-repeater unite-setting-input-object"';
                    h += ' data-settingtype="repeater" data-name="' + ea(name) + '"';
                    h += ' data-item-title="' + ea(itemTitle) + '"';
                    h += ' data-text-delete="' + ea(deleteText) + '"';
                    h += ' data-text-duplicate="' + ea(duplicateText) + '"';
                    if (itemsJson) h += ' data-itemvalues="' + ea(itemsJson) + '"';
                    h += '>';
                    h += '<div class="unite-repeater-template unite-hidden">' + templateHtml + '</div>';
                    h += '<div class="unite-repeater-empty">' + eh(emptyText) + '</div>';
                    h += '<div class="unite-repeater-items"></div>';
                    h += '<div class="unite-repeater-actions">';
                    h += '<button class="unite-button-primary unite-repeater-add" type="button">' + eh(addText) + '</button>';
                    h += '</div>';
                    h += '</div>';
                    return h;
                }

                // --- buttons group ---
                if (t === 'buttons_group') {
                    var items = s.items || {};
                    var deselectable = (s.deselectable === true || s.deselectable === 'true') ? 'true' : 'false';
                    var h = '<div id="' + ea(id) + '" class="unite-setting-buttons-group unite-setting-input-object unite-settings-exclude"';
                    h += ' data-settingtype="buttons_group" data-name="' + ea(name) + '"';
                    h += ' data-deselectable="' + deselectable + '"';
                    h += defaultAttrs(s) + '>';
                    Object.keys(items).forEach(function(itemVal) {
                        var item = items[itemVal];
                        var itemTitle = (item && item.title) ? item.title : itemVal;
                        var itemIcon  = (item && item.icon)  ? item.icon  : '';
                        h += '<div class="unite-setting-button uc-tip" title="' + ea(itemTitle) + '" data-value="' + ea(itemVal) + '">';
                        h += itemIcon; // raw SVG — trusted server output
                        h += '</div>';
                    });
                    h += '</div>';
                    return h;
                }

                // --- sub-settings (typography, textshadow, textstroke, boxshadow, css_filters) ---
                if (SUB_SETTINGS_TYPES[t]) {
                    var resetSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12"><path d="M10.606 9.008a5.5 5.5 0 1 1 .68-4.535"/><path d="M11.5.5v4l-3.969-.493"/></svg>';
                    var editSvg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12"><path d="m9 1 2 2-7 7-3 1 1-3 7-7Z" /></svg>';
                    var i18n_ss  = (wp && wp.i18n && wp.i18n.__) ? wp.i18n.__ : function(v) { return v; };
                    var h = '<div id="' + ea(id) + '" class="unite-sub-settings unite-setting-input-object"';
                    h += ' data-settingtype="' + ea(t) + '" data-name="' + ea(name) + '" data-dialog-id="' + ea(t) + '"';
                    h += defaultAttrs(s) + '>';
                    h += '<button class="unite-sub-settings-reset unite-setting-button uc-tip unite-hidden" type="button"';
                    h += ' title="' + ea(i18n_ss('Reset', 'unlimited-elements-for-elementor')) + '">' + resetSvg + '</button>';
                    h += '<button class="unite-sub-settings-edit unite-setting-button uc-tip" type="button"';
                    h += ' title="' + ea(i18n_ss('Edit', 'unlimited-elements-for-elementor')) + '">' + editSvg + '</button>';
                    h += '</div>';
                    return h;
                }

                return '';
            }

            var SKIP_TYPES = {
                items:1, font_panel:1, gallery:1,
                tabs:1, group_selector:1, custom:1, addon:1, map:1,
                post:1, link:1, mp3:1, checkbox:1, radio:1,
                file:1, date_time:1, multiselect:1, boolean:1
            };

            var SUB_SETTINGS_TYPES = {
                typography:1, textshadow:1, textstroke:1, boxshadow:1, css_filters:1
            };

            // render a single <li> setting row
            function renderRow(s) {
                var t    = s.type;

                var id   = s.id   || (idPrefix + (s.name || ''));
                var name = s.name || '';
                var text = s.text || '';
                var description = s.description || '';

                // hr
                if (t === 'hr') {
                    var hrClass = s.hidden ? ' class="unite-setting-hidden"' : '';
                    return '<li id="' + ea(id) + '_row"' + hrClass + '><hr id="' + ea(id) + '"></li>';
                }
                // static text / html
                if (t === 'statictext' || t === 'html') {
                    var stClass = s.hidden ? ' class="unite-setting-hidden"' : '';
                    return '<li id="' + ea(id) + '_row"' + stClass + '>' +
                        '<span class="unite-settings-static-text">' +
                        (t === 'html' ? (s.text || '') : eh(text)) + '</span></li>';
                }
                // skip complex / unsupported types
                if (SKIP_TYPES[t]) return '';

                var labelBlock  = !!s.label_block;
                var isResponsive = !!s.is_responsive;
                var isHidden    = !!s.hidden;

                var baseClass = 'unite-setting-row';
                if (!labelBlock)  baseClass += ' unite-inline-setting';
                if (isHidden)     baseClass += ' unite-setting-hidden';

                var addAttr = '';
                if (isResponsive) {
                    addAttr += ' data-responsive-id="'   + ea(s.responsive_id   || '') + '"';
                    addAttr += ' data-responsive-type="' + ea(s.responsive_type || 'desktop') + '"';
                }

                var hasText = !!(text) && (t !== 'button');

                var h = '<li id="' + ea(id) + '_row" class="' + ea(baseClass) + '"' + addAttr;
                h += ' data-name="' + ea(name) + '" data-type="' + ea(t) + '">';
                h += '<div class="unite-setting-field">';

                if (hasText) {
                    h += '<div class="unite-setting-text-wrapper">';
                    h += '<div id="' + ea(id) + '_text" class="unite-setting-text">' + eh(text) + '</div>';
                    if (isResponsive) h += '<select class="unite-responsive-picker"></select>';
                    h += '</div>';
                }

                h += '<div class="unite-setting-input">';
                h += renderInput(s);
                h += '</div>';
                h += '</div>'; 

                if (description) {
                    h += '<div class="unite-setting-helper">' + description + '</div>';
                }

                h += '</li>';
                return h;
            }

            // group settings by SAP index
            var sapGroups = {};
            settings.forEach(function(s) {
                var idx = (s.sap !== undefined && s.sap !== null) ? s.sap : -1;
                if (!sapGroups[idx]) sapGroups[idx] = [];
                sapGroups[idx].push(s);
            });

            // build html
            var html = '';
            html += '<div id="' + ea(wrapperID) + '" autofocus="true"';
            html += ' class="unite_settings_wrapper unite-settings-sidebar unite-settings unite-inputs">';

            // only output controls template when non-empty — mirrors PHP drawWrapperStart() behavior.
            // empty controls template causes settings.js to set g_arrControls = undefined.
            if (controls && Object.keys(controls).length > 0) {
                html += '<template id="' + ea(wrapperID) + '-controls" class="tpl-controls">';
                html += JSON.stringify(controls);
                html += '</template>';
            }

            html += '<template id="' + ea(wrapperID) + '-options" class="tpl-options">';
            html += JSON.stringify(renderOptions);
            html += '</template>';

            // tabs navigation
            var hasStyleTab = saps.some(function(sap) { return sap.tab === 'style'; });
            if (hasStyleTab) {
                var i18n   = (wp && wp.i18n && wp.i18n.__) ? wp.i18n.__ : function(s) { return s; };
                var tLabel = i18n('Content',  'unlimited-elements-for-elementor');
                var sLabel = i18n('Style',    'unlimited-elements-for-elementor');
                var aLabel = i18n('Advanced', 'unlimited-elements-for-elementor');
                html += '<div class="unite-settings-accordion-saps-tabs">';
                html += '<a href="javascript:void(0)" class="unite-settings-tab unite-active" data-id="content">' + eh(tLabel) + '</a>';
                html += '<a href="javascript:void(0)" class="unite-settings-tab" data-id="style">'               + eh(sLabel) + '</a>';
                html += '<a href="javascript:void(0)" class="unite-settings-tab" data-id="advanced">'            + eh(aLabel) + '</a>';
                html += '</div>';
            }

            var firstContentSapIdx = -1;
            if (hasStyleTab) {
                saps.forEach(function(sap, idx) {
                    if (firstContentSapIdx === -1 && (sap.tab || 'content') === 'content') {
                        firstContentSapIdx = idx;
                    }
                });
            }

            // if no saps defined (e.g. dialog settings), render all settings directly
            if (saps.length === 0) {
                var allRows = [];
                Object.keys(sapGroups).forEach(function(k) {
                    allRows = allRows.concat(sapGroups[k]);
                });
                if (allRows.length) {
                    html += '<div class="unite-postbox-inside">';
                    html += '<ul class="unite-list-settings">';
                    allRows.forEach(function(s) { html += renderRow(s); });
                    html += '</ul>';
                    html += '</div>';
                }
            }

            saps.forEach(function(sap, idx) {
                var sapName = sap.name || '';
                var sapText = sap.text || '';
                var sapTab  = sap.tab  || 'content';
                var sapRows = sapGroups[idx] || [];

                var sapID = idPrefix + 'ucsap_' + sapName;

                var isHiddenPostbox = hasStyleTab && (sapTab !== 'content');
                var isFirstActive   = hasStyleTab && (idx === firstContentSapIdx);
                var postboxStyle    = isHiddenPostbox ? ' style="display:none"' : '';
                var postboxClass    = 'unite-postbox' + (isFirstActive ? ' unite-active' : '');

                var insideStyle     = (hasStyleTab && !isFirstActive) ? ' style="display:none"' : '';

                html += '<div id="' + ea(sapID) + '" class="' + postboxClass + '" data-tab="' + ea(sapTab) + '"' + postboxStyle + '>';

                if (showSaps) {
                    html += '<div class="unite-postbox-title">';
                    html += '<span>' + eh(sapText) + '</span>';
                    if (isAccordion) html += '<div class="unite-postbox-arrow"></div>';
                    html += '</div>';
                }

                html += '<div class="unite-postbox-inside"' + insideStyle + '>';
                html += '<ul class="unite-list-settings">';
                sapRows.forEach(function(s) { html += renderRow(s); });
                html += '</ul>';
                html += '</div>'; 
                html += '</div>'; 
            });

            // append sub-settings dialogs (typography, textshadow, etc.)
            // prefer global (loaded once at page init); fall back to per-request json.dialog_settings
            if (!isForDialog) {
                var dialogSettings = (typeof g_uelm_dialogSettings !== 'undefined' && g_uelm_dialogSettings)
                    ? g_uelm_dialogSettings
                    : (json.dialog_settings || {});
                Object.keys(dialogSettings).forEach(function(type) {
                    var dialogHtml = generateSettingsHtmlFromJson(dialogSettings[type], true);
                    html += '<div class="unite-sub-settings-dialog unite-settings-exclude" data-id="' + ea(type) + '">';
                    html += '<div class="unite-settings">';
                    html += dialogHtml;
                    html += '</div>';
                    html += '</div>';
                });
            }

            html += '</div>';
            return html;
        }

        // AJAX: load settings HTML
        var loadSettingsContent = function () {
            var widgetCacheKey = cacheKeyBase + '_settings';

            setIsLoadingSettings(true);

            if ( uelm_WidgetSettingsCache[widgetCacheKey] && uelm_WidgetSettingsCacheFlags[widgetCacheKey] ) {
                uelm_WidgetSettingsCacheFlags[widgetCacheKey] = false;
                setSettingsContent( uelm_WidgetSettingsCache[widgetCacheKey] );
                setIsLoadingSettings(false);
                return;
            }

            g_ucAdmin.setErrorMessageID(settingsErrorId);

            const urlParams = new URLSearchParams(window.location.search);
            const isTestFreeVersion = urlParams.get("testfreeversion") === "true";

            var requestData = {
                id: props.attributes._id,
                config: getSettings(),
                platform: "gutenberg",
                source: "editor"
            };
            if (isTestFreeVersion) requestData.testfreeversion = true;

            g_ucAdmin.ajaxRequest("get_addon_settings_html", requestData, function (response) {

                var json = g_ucAdmin.getVal(response, "json");
                var html;

                // use JSON to generate HTML client-side; fall back to server HTML if not available
                if (json && json.settings && json.settings.length > 0) {
                    // set global dialog settings if not already set
                    if (typeof window.g_uelm_dialogSettings === 'undefined' && json.dialog_settings) {
                        window.g_uelm_dialogSettings = json.dialog_settings;
                    }
                    html = generateSettingsHtmlFromJson(json);
                } else {
                    html = g_ucAdmin.getVal(response, "html");
                }

                uelm_WidgetSettingsCache[widgetCacheKey] = html;
                uelm_WidgetSettingsCacheFlags[widgetCacheKey] = true;

                setSettingsContent(html);
                setIsLoadingSettings(false);
            }).fail(function() {
                 setIsLoadingSettings(false);
            });
        };

        // AJAX: load widget HTML
        var loadWidgetContent = function (overrideSettings) {
            var widgetCacheKey = cacheKeyBase;

            if ( uelm_WidgetSettingsCache[widgetCacheKey] && uelm_WidgetSettingsCacheFlags[widgetCacheKey] ) {
                uelm_WidgetSettingsCacheFlags[widgetCacheKey] = false;
                initWidget( uelm_WidgetSettingsCache[widgetCacheKey] );
                debug('loadWidgetContent loaded from cache');
                return;
            }

            if (!widgetContent) {

                if (typeof window.uelm_setBlocks === 'undefined') {
                    window.uelm_setBlocks = new Set();
                }
                
                var blockKey = props.name + '_' + props.clientId;

                if (window.uelm_setBlocks.has(blockKey)) {
                    // Block already processed, skipping
                    return;
                }
                
                for (var i = 0; i < g_gutenbergParsedBlocks.length; i++) {
                    var block = g_gutenbergParsedBlocks[i];

                    if (block && block.name === props.name && block.html) {
                        
                        setWidgetContent(block.html);

                        try {
                            if (isSettingsReady()) {
                                var values = getSettings(); 
                                if (values !== null) {
                                    ucSettings.setCacheValues(values);
                                }
                            }
                        } catch (e) {
                            console.warn('Failed to sync ucSettings from data after load-from-page', e);
                        }

                        uelm_WidgetSettingsCache[widgetCacheKey] = {
                            html: block.html,
                            includes: {}   
                        };
                        uelm_WidgetSettingsCacheFlags[widgetCacheKey] = true;
                        
                        delete g_gutenbergParsedBlocks[i];
                        
                        window.uelm_setBlocks.add(blockKey);
                        
                        debug('loadWidgetContent loaded from page');
                        return;
                    }
                }
                
                console.warn('No content found for block:', blockKey);
            }

            var settings = overrideSettings ?? getSettings();

            if (widgetRequestRef.current !== null)
                widgetRequestRef.current.abort();

            var loaderElement = jQuery(widgetLoaderRef.current);
            loaderElement.show();

            debug('loadWidgetContent load from server');

            widgetRequestRef.current = g_ucAdmin.ajaxRequest("get_addon_output_data", {
                id: props.attributes._id,
                root_id: props.attributes._rootId,
                platform: "gutenberg",
                source: "editor",
                settings: settings || null,
                selectors: true,
            }, function (response) {
                uelm_WidgetSettingsCache[widgetCacheKey] = response;
                uelm_WidgetSettingsCacheFlags[widgetCacheKey] = true;
                initWidget(response);
            }).always(function () {
                loaderElement.hide();
            });

        };

        var initWidget = function (response) {
            var html = g_ucAdmin.getVal(response, "html");
            var includes = g_ucAdmin.getVal(response, "includes");
            var win = getPreviewWindowElement();

            if (win.jQuery && Array.isArray(includes?.scripts)) {
                includes.scripts = includes.scripts.filter(function (src) {
                    return !/jquery(\.min)?\.js/i.test(src);
                });
            }

            ucHelper.putIncludes(win, includes, function () {
                setWidgetContent(html);
            });

            applyStylePreviewOnce();
        };

        we.useEffect(function () {
            if (!props.attributes._rootId) {
                props.setAttributes({ _rootId: 'ue-' + props.clientId });
            }
        }, []);

        we.useEffect(function () {
            debug('[effect 1]');

            jQuery("#unlimited-elements-styles").remove();

            attachSettingsObserver();
            loadWidgetContent();

            return function () {
                // cleanup on unmount
                if (firstPreviewTimerRef.current) {
                    clearTimeout(firstPreviewTimerRef.current);
                    firstPreviewTimerRef.current = null;
                }

                if (isSettingsReady()) flushSaveNow();

                ucSettings.destroy();
                initedSettingsElementRef.current = null;
                settingsInitedRef.current = false;
                detachSettingsObserver();
            };
        }, []);

        we.useEffect(function () {
            debug('[effect 2]');
            if (didFirstPreviewRef.current) return;

            const attr = (() => {
                try { return props.attributes.data ? JSON.parse(props.attributes.data) : {}; }
                catch(e){ return {}; }
            })();

            if (attr && Object.keys(attr).length > 0) {
                const payloadStr = JSON.stringify(attr);
                lastPreviewPayloadRef.current = payloadStr;

                loadWidgetContent(attr);               
                didFirstPreviewRef.current    = true; 
                firstPreviewReadyRef.current  = true;  
            }
        }, []);

        // mark color/range/dimensions to suppress reload
        we.useEffect(function () {

            debug('[effect 3]');

            function checkEventType(e){

                const t = e.target;
                if (!t) return;

                const settingsRoot = document.getElementById(settingsId);
                if (!settingsRoot || !settingsRoot.contains(t)) return;

                const type         = (t.type || '').toLowerCase();
                const hasColor     = t.classList?.contains('unite-color-picker');
                const isRange      = type === 'range' || t.classList?.contains('unite-range-slider');
                const isDimensions = !!t.closest?.('.unite-dimensions');

                if (hasColor) {
                    lastChangeTypeRef.current = 'color';
                } else if (isRange || isDimensions) {
                    lastChangeTypeRef.current = isDimensions ? 'dimensions' : 'range';
                }
            }

            document.addEventListener('input',  checkEventType, true);
            document.addEventListener('change', checkEventType, true);

            return () => {
                document.removeEventListener('input',  checkEventType, true);
                document.removeEventListener('change', checkEventType, true);
            };
        }, [settingsId]);

        // insert widget HTML into DOM
        we.useEffect(function () {
            debug('[effect 4]');

            if (!widgetContent) return;
            jQuery(widgetRef.current).html(widgetContent);

        }, [widgetContent]);

        // sidebar visibility logic
        we.useEffect(function () {
            debug('[effect 5]');
            const isVisible = props.isSelected
                && isEditorSidebarOpened
                && activeGeneralSidebarName === "edit-post/block";

            setSettingsVisible(isVisible);

            if (isVisible && !settingsContent && !isLoadingSettings) {
                loadSettingsContent();
            } else if (!isVisible) {
                if (isSettingsReady()) flushSaveNow();
            }
        }, [props.isSelected, isEditorSidebarOpened, activeGeneralSidebarName]);

        we.useEffect(function () {
            debug('[effect 6]');
            if (ucSettings.isInited())
                ucSettings.setResponsiveType(previewDeviceType.toLowerCase());
        }, [previewDeviceType]);

        we.useEffect(function () {
            
        	debug('[effect 7]');
            
            maybeInitSettings();
                        
        }, [settingsVisible, settingsContent, previewDeviceType, props.attributes.data]);

        we.useEffect(function () {
        	
            debug('[effect 8]');
            
            if (!settingsContent) return;
            	runFirstPreviewOnce();
            
        }, [settingsContent]);

        we.useEffect(function () {
            
        	debug('[effect 9]');
        	
            if (!firstPreviewReadyRef.current) {
                if (widgetContent) {
                    firstPreviewReadyRef.current = true;
                } else {
                    return;
                }
            }
            
            debug("check the settings");
            
            if (suppressNextReloadRef.current) {

                suppressNextReloadRef.current = false;

                debug("effect 9 - supress next reload");
                
                updateSelectorsPreview();
                                
                return;
            }

            const settings = (() => {
                try { 
                    return props.attributes.data ? JSON.parse(props.attributes.data) : {}; 
                } catch(e){ 
                    return {}; 
                }
            })();
            
            trace("the settings");
            trace(settings);
            
            const payloadStr = JSON.stringify(settings || {});
            if (payloadStr === lastPreviewPayloadRef.current) {
            	
            	debug("Update Selectors Preview");
            	
                updateSelectorsPreview();
                return;
            }
            lastPreviewPayloadRef.current = payloadStr;

            loadWidgetContent(settings);

        }, [props.attributes.data]);

        // set focus and mouse events
        we.useEffect(function () {
        	
            debug('[effect 10]');

            function onBlur(e){
                const t = e.target;
                if (!t) return;
                const tag = (t.tagName || '').toLowerCase();
                if (tag === 'input' || tag === 'textarea' || tag === 'select') {
                    if (isSettingsReady()) flushSaveNow();
                }
            }

            function onBeforeUnload() {
                if (isSettingsReady()) flushSaveNow();
            }

            function onPointerUpOrTouchEnd() {
                const t = (lastChangeTypeRef.current || '').toLowerCase();
                if (t === 'styles') {
                    flushSaveNow(t); 
                }
            }

            document.addEventListener('blur', onBlur, true);
            window.addEventListener('beforeunload', onBeforeUnload);
            window.addEventListener('pointerup', onPointerUpOrTouchEnd, { passive: true });
            window.addEventListener('touchend',  onPointerUpOrTouchEnd, { passive: true });

            return () => {
                window.removeEventListener('pointerup', onPointerUpOrTouchEnd);
                window.removeEventListener('touchend',  onPointerUpOrTouchEnd);
                window.removeEventListener('beforeunload', onBeforeUnload);
                document.removeEventListener('blur', onBlur, true);
            };
        }, []);

        function runFirstPreviewOnce(maxTries = 30, delay = 50) {
            if (didFirstPreviewRef.current) return;
            if (firstPreviewTimerRef.current) return;

            let tries = 0;

            const tick = () => {
                if (didFirstPreviewRef.current) { firstPreviewTimerRef.current = null; return; }

                if (!isSettingsReady()) {
                    if (++tries < maxTries) firstPreviewTimerRef.current = setTimeout(tick, delay);
                    else firstPreviewTimerRef.current = null;
                    return;
                }

                const attr = (() => { try { return props.attributes.data ? JSON.parse(props.attributes.data) : {}; } catch(e){ return {}; } })();

                if (attr?.uc_items?.length) {
                    const payloadStr = JSON.stringify(attr);
                    lastPreviewPayloadRef.current = payloadStr;
                    loadWidgetContent(attr);
                    didFirstPreviewRef.current    = true;
                    firstPreviewReadyRef.current  = true;
                    firstPreviewTimerRef.current  = null;
                    return;
                }

                const def = ucSettings.getSettingsValues?.() || {};
                if (!def?.uc_items?.length) {
                    if (++tries < maxTries) firstPreviewTimerRef.current = setTimeout(tick, delay);
                    else firstPreviewTimerRef.current = null;
                    return;
                }

                const merged = { ...def, ...attr };
                const mergedStr = JSON.stringify(merged);

                if (lastSentDataRef.current !== mergedStr) {
                    suppressNextReloadRef.current = true;
                    lastSentDataRef.current = mergedStr;
                    props.setAttributes({ data: mergedStr });
                }

                lastPreviewPayloadRef.current = mergedStr;
                
                loadWidgetContent(merged); 

                didFirstPreviewRef.current    = true;
                firstPreviewReadyRef.current  = true;
                firstPreviewTimerRef.current  = null;
            };

            tick();
        }

        var settings = el(
            wbe.InspectorControls, {},
            el("div", { className: "ue-gutenberg-settings-error", id: settingsErrorId }),
            settingsContent && el("div", { id: settingsId, dangerouslySetInnerHTML: { __html: settingsContent } }),
            !settingsContent && isLoadingSettings && el("div", { className: "ue-gutenberg-settings-spinner" }, el(wc.Spinner)),
            !settingsContent && !isLoadingSettings && el("div", null, "No settings found or error occured."),
        );

        var widget = el(
            "div", { className: "ue-gutenberg-widget-wrapper" },
            widgetContent && el("div", { className: "ue-gutenberg-widget-content", id: widgetId, ref: widgetRef }),
            widgetContent && el("div", { className: "ue-gutenberg-widget-loader", ref: widgetLoaderRef }, el(wc.Spinner)),
            !widgetContent && el("div", { className: "ue-gutenberg-widget-placeholder" }, el(wc.Spinner)),
        );

        return el("div", blockProps, settings, widget);
    };

    for (var name in g_gutenbergBlocks) {
        var block = g_gutenbergBlocks[name];
        var args  = jQuery.extend(block, { edit: edit });

        // convert inline SVG icon string to element
        if (typeof args.icon === 'string' && args.icon.trim().startsWith('<svg')) {
            try {
                const sanitized = args.icon.trim();
                args.icon = el('span', { dangerouslySetInnerHTML: { __html: sanitized } });
            } catch (e) {
                args.icon = '';
            }
        }
        wp.blocks.registerBlockType(name, args);
    }
})(wp);
