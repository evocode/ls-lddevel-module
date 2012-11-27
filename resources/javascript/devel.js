
var lddevel = {

    el: {
        main: jQuery('.lddevel'),
        close: jQuery('#lddevel-close'),
        zoom: jQuery('#lddevel-zoom'),
        hide: jQuery('#lddevel-hide'),
        show: jQuery('#lddevel-show'),
        config: jQuery('#lddevel-config'),
        tab: jQuery('.lddevel-tab'),
        tabs: jQuery('.lddevel-tabs'),
        tab_links: jQuery('.lddevel-tabs a'),
        window: jQuery('.lddevel-window'),
        closed_tabs: jQuery('#lddevel-closed-tabs'),
        open_tabs: jQuery('#lddevel-open-tabs'),
        requests: jQuery('.lddevel-requests')
    },

    //main configuration
    config: {
        collapsed: false
    },

    //reference objects used in displaying output
    ref: {},

    // is lddevel in full screen mode
    is_zoomed: false,

    // initial height of content area
    small_height: jQuery('.lddevel-content-area').height(),

    // the name of the active tab css
    active_tab: 'lddevel-active-tab',

    // the data attribute of the tab link
    tab_data: 'data-lddevel-tab',

    // size of lddevel when compact
    mini_button_width: '2.6em',

    // is the top window open?
    window_open: false,

    // current active pane
    active_pane: '',

    // array of requests with stats
    stats: [],

    // the data attribute of the request link
    request_data: 'data-lddevel-request',

    // current active request
    active_request: '',

    /*
     * Start it
     */
    start: function() {

        // hide initial elements
        lddevel.el.close.hide();
        lddevel.el.zoom.hide();
        jQuery('.lddevel-tab-pane').hide();

        // bind all click events
        lddevel.el.close.click(function(event) {
            lddevel.close_window();
            event.preventDefault();
        });
        lddevel.el.hide.click(function(event) {
            lddevel.hide();
            event.preventDefault();
        });
        lddevel.el.show.click(function(event) {
            lddevel.show();
            event.preventDefault();
        });
        lddevel.el.zoom.click(function(event) {
            lddevel.zoom();
            event.preventDefault();
        });
        lddevel.el.tab.click(function(event) {
            lddevel.clicked_tab(jQuery(this));
            event.preventDefault();
        });
        lddevel.el.config.click(function(event) {
            lddevel.clicked_config();
            event.preventDefault();
        });

    },

    /*
     * Set configuration values
     */
    set_config: function(vars) {

        jQuery.extend(lddevel.config, vars);

        //check if it should start collapsed
        if (lddevel.config.collapsed) {
            lddevel.hide(false);
        }

    },

    /*
     * Open a new request
     */
    request: function(page_id) {

        if (lddevel.window_open)
        {
            if (lddevel.active_pane != 'lddevel-request-options')
            {
                lddevel.active_request = page_id;
                lddevel.open_request(jQuery('#lddevel-rtab-' + page_id + ''));
            }
            else
            {
                for (var i in lddevel.stats)
                {
                    if (lddevel.stats[i]['id'] == page_id)
                    {
                        lddevel.update_stats(lddevel.stats[i]['data']);
                        break;
                    }
                }
            }
        }

    },

    /*
     * Add new sql
     */
    add_queries: function(page_id, querylist) {

        if (querylist.length == 0)
            return;

        var html = '<table> <tr><th>Time</th><th>Query</th></tr> ';

        for (var i in querylist) {
            if (typeof(querylist[i]['id']) == "undefined")
                break;

            var cclass = i % 2 == 0 ? 'row-odd' : 'row-even';
            cclass += ' p-' + querylist[i]['priority'];

            html += '<tr class="' + cclass + '"><td>' + querylist[i]['time'] + 'ms</td><td><pre>' + querylist[i]['sql'] + '</pre></td></tr> ';
        }

        html += '</table>';

        jQuery('.lddevel-content-container #lddevel-request-' + page_id + ' .lddevel-sql').html(html);

    },

    /*
     * Add new timers
     */
    add_timers: function(page_id, timerlist) {

        if (timerlist.length == 0)
            return;

        var html = '<table> <tr><th>Name</th><th>Running Time (ms)</th><th>Difference</th></tr> ';

        for (var i in timerlist) {
            if (typeof(timerlist[i]['time']) == "undefined")
                break;

            var cclass = i % 2 == 0 ? 'row-odd' : 'row-even';
            var name = '';
            var diff = '';

            if (timerlist[i]['tick'] == null)
            {
                name =  timerlist[i]['name'];
                diff = timerlist[i]['diff'] == null ? '&nbsp;' : timerlist[i]['diff'];
            }
            else
            {
                name = timerlist[i]['name'] + ' - Tick ' + timerlist[i]['tick'];
                diff = timerlist[i]['tick'] == '1' || timerlist[i]['diff'] == null ? '&nbsp;' : '+ ' + timerlist[i]['diff'];
            }

            html += '<tr class="' + cclass + '"><td>' + name + '</td><td>' + timerlist[i]['time'] + '</td><td>' + diff + '</td></tr> ';
        }

        html += '</table>';

        jQuery('.lddevel-content-container #lddevel-request-' + page_id + ' .lddevel-timers').html(html);

    },

    /*
     * Add new timers
     */
    add_memory: function(page_id, memorylist) {

        if (memorylist.length == 0)
            return;

        var html = '<table> <tr><th>Name</th><th>Memory Usage (Peak)</th><th>Difference</th></tr> ';

        for (var i in memorylist) {
            if (typeof(memorylist[i]['memory']) == "undefined")
                break;

            var cclass = i % 2 == 0 ? 'row-odd' : 'row-even';
            var name = '';
            var diff = '';
            var diffpeak = '';

            if (memorylist[i]['tick'] == null)
            {
                name =  memorylist[i]['name'];
                diff = memorylist[i]['diff'] == null ? '' : memorylist[i]['diff'];
                diffpeak = memorylist[i]['diffpeak'] == null ? '' : memorylist[i]['diffpeak'];
            }
            else
            {
                name = memorylist[i]['name'] + ' - Tick ' + memorylist[i]['tick'];
                diff = memorylist[i]['tick'] == '1' || memorylist[i]['diff'] == null ? '' : '+ ' + memorylist[i]['diff'];
                diffpeak = memorylist[i]['tick'] == '1' || memorylist[i]['diffpeak'] == null ? '' : memorylist[i]['diffpeak'];
            }

            var memstr = memorylist[i]['memory'] + ' (' + memorylist[i]['memorypeak'] + ')';
            var diffstr = (diff == '' && diffpeak == '' ? '&nbsp;' : diff + ' (' + diffpeak + ')');

            html += '<tr class="' + cclass + '"><td>' + name + '</td><td>' + memstr + '</td><td>' + diffstr + '</td></tr> ';
        }

        html += '</table>';

        jQuery('.lddevel-content-container #lddevel-request-' + page_id + ' .lddevel-memory').html(html);

    },

    /*
     * Add log entries
     */
    add_logs: function(page_id, loglist) {

        if (loglist.length == 0)
            return;

        var html = '<table> <tr><th>Type</th><th>Message</th></tr> ';

        for (var i in loglist) {
            if (typeof(loglist[i]['type']) == "undefined")
                break;

            var cclass = i % 2 == 0 ? 'row-odd' : 'row-even';
            cclass += ' t-' + loglist[i]['type'];

            html += '<tr class="' + cclass + '"><td>' + loglist[i]['type'] + '</td><td><pre>' + loglist[i]['message'] + '</pre></td></tr> ';
        }

        html += '</table>';

        jQuery('.lddevel-content-container #lddevel-request-' + page_id + ' .lddevel-log').html(html);

    },

    /*
     * Add variables
     */
    add_variables: function(page_id, variablelist) {

        if (variablelist.length == 0)
            return;

        lddevel.ref = {};
        var html = '<table> ';
        
        for (var i in variablelist) {
            if (typeof(variablelist[i]['type']) == "undefined")
                break;

            var type = variablelist[i]['type'];

            if (lddevel.ref[type] == undefined)
            {
                var title = '';
                if (type == 'get')
                    title = 'GET Data';
                else if (type == 'post')
                    title = 'POST Data';
                else if (type == 'session')
                    title = 'SESSION Data';
                else if (type == 'cookie')
                    title = 'COOKIE Data';
                else if (type == 'lsconfig')
                    title = 'LemonStand Config';
                else if (type == 'header')
                    title = 'Headers';
                else if (type == 'constant')
                    title = 'Defined Constants';
                else if (type == 'function')
                    title = 'Defined Functions';
                else if (type == 'include')
                    title = 'Include Files';
                else if (type == 'interface')
                    title = 'Declared Interfaces';
                else if (type == 'classes')
                    title = 'Declared Classes';

                html += '<tr class="head"><th>' + title + '</th></tr> ';

                lddevel.ref[type] = true;
            }

            var cclass = i % 2 == 0 ? 'row-odd' : 'row-even';

            html += '<tr class="' + cclass + '"><td>' + variablelist[i]['value'] + '</td></tr> ';
        }

        html += '</table>';

        jQuery('.lddevel-content-container #lddevel-request-' + page_id + ' .lddevel-var').html(html);

    },

    /*
     * When a tab has been clicked for the different pages
     */
    add_request: function(page_id, stats) {

        var first = lddevel.stats.length;
        var title = first > 0 ? 'Ajax' : 'Page';
        var cclass = first > 0 ? '' : ' lddevel-active-tab';

        lddevel.stats.push({id: page_id, data: stats});
        lddevel.update_stats(stats);

        if (first == 0)
            lddevel.active_request = page_id;

        jQuery('<li><a id="lddevel-rtab-' + page_id + '" data-lddevel-request="' + page_id + '" class="lddevel-request' + cclass + '" href="#">' + title + ' ' + stats.name + '</a></li>')
            .appendTo(lddevel.el.requests)
            .find('a')
            .click(function(event) {
                lddevel.clicked_request(jQuery(this));
                event.preventDefault();
            });

        var html = '<div id="lddevel-request-' + page_id + '" class="lddevel-content-area">';

        html += '<div class="lddevel-tab-pane lddevel-table lddevel-timers" style="display:none"><div class="lddevel-empty">You have no timers set</div></div> ';
        html += '<div class="lddevel-tab-pane lddevel-table lddevel-sql" style="display:none"><div class="lddevel-empty">You have no sql queries</div></div> ';
        html += '<div class="lddevel-tab-pane lddevel-table lddevel-memory" style="display:none"><div class="lddevel-empty">You have no memory usage set</div></div> ';
        html += '<div class="lddevel-tab-pane lddevel-table lddevel-log" style="display:none"><div class="lddevel-empty">You have no log entries</div></div> ';
        html += '<div class="lddevel-tab-pane lddevel-table lddevel-var" style="display:none"><div class="lddevel-empty">No variables were set</div></div> ';

        html += '</div>';

        var content = jQuery(html)
            .appendTo(jQuery('.lddevel-content-container'));

        if (first > 0)
            content.css('display', 'none');

    },

    /*
     * When a tab has been clicked
     */
    clicked_config: function(tab) {

        // if the tab is closed
        if (lddevel.window_open && lddevel.active_pane == 'lddevel-request-options') {
            lddevel.close_window();
        } else {
            lddevel.open_config();
        }

    },

    /*
     * When a tab has been clicked
     */
    clicked_tab: function(tab) {

        // if the tab is closed
        if (lddevel.window_open && lddevel.active_pane == tab.attr(lddevel.tab_data)) {
            lddevel.close_window();
        } else {
            lddevel.close_config();
            lddevel.open_window(tab);
        }

    },

    /*
     * When a request tab has been clicked
     */
    clicked_request: function(tab) {

        // if the tab is open
        if (lddevel.window_open)
        {
            if (lddevel.active_request != tab.attr(lddevel.request_data)) {
                lddevel.open_request(tab);
            }
        }

    },

    /*
     * Open window to tab
     */
    open_config: function() {

        jQuery('.lddevel-content-menu').hide();
        jQuery('.lddevel-content-area').hide();
        jQuery('.lddevel-tab-pane:visible').hide();
        
        jQuery('#lddevel-request-options').fadeIn(300);
        lddevel.el.tab_links.removeClass(lddevel.active_tab);
        //tab.addClass(lddevel.active_tab);
        lddevel.el.window.slideDown(300);
        lddevel.el.close.fadeIn(300);
        lddevel.el.zoom.fadeIn(300);
        lddevel.active_pane = 'lddevel-request-options';
        lddevel.window_open = true;

    },


    /*
     * Close config window if opened
     */
    close_config: function() {

        //close config if opened
        if (lddevel.active_pane == 'lddevel-request-options')
        {
            jQuery('.lddevel-content-menu').fadeIn(200);
            jQuery('#lddevel-request-options').hide();
            jQuery('#lddevel-request-' + lddevel.active_request).show();
            jQuery('.lddevel-tab-pane:visible').hide();
        }

    },

    /*
     * Open window to tab
     */
    open_window: function(tab) {

        jQuery('.lddevel-tab-pane:visible').fadeOut(200);
        jQuery('#lddevel-request-' + lddevel.active_request + ' .' + tab.attr(lddevel.tab_data)).delay(220).fadeIn(300);
        lddevel.el.tab_links.removeClass(lddevel.active_tab);
        tab.addClass(lddevel.active_tab);
        lddevel.el.window.slideDown(300);
        lddevel.el.close.fadeIn(300);
        lddevel.el.zoom.fadeIn(300);
        lddevel.active_pane = tab.attr(lddevel.tab_data);
        lddevel.window_open = true;

    },

    /*
     * Close a window
     */
    close_window: function() {

        jQuery('.lddevel-tab-pane').fadeOut(100);
        lddevel.el.window.slideUp(300);
        lddevel.el.close.fadeOut(300);
        lddevel.el.zoom.fadeOut(300);
        lddevel.el.tab_links.removeClass(lddevel.active_tab);
        lddevel.active_pane = '';
        lddevel.window_open = false;

    },

    /*
     * Open a new request
     */
    open_request: function(tab) {

        var page_id = tab.attr(lddevel.request_data);
        var area = jQuery('.lddevel-content-area:visible');
        var area_height = area.height();

        area.hide();
        jQuery('#lddevel-request-' + page_id).height(area_height).show();

        jQuery('.lddevel-tab-pane').hide();
        jQuery('#lddevel-request-' + page_id + ' .' + lddevel.active_pane).fadeIn(200);

        jQuery('.lddevel-requests a').removeClass(lddevel.active_tab);
        tab.addClass(lddevel.active_tab);
        lddevel.active_request = page_id;

        for (var i in lddevel.stats)
        {
            if (lddevel.stats[i]['id'] == page_id)
            {
                lddevel.update_stats(lddevel.stats[i]['data']);
                break;
            }
        }

    },

    /*
     * Update stats bar
     */
    update_stats: function(stats) {

        jQuery('#lddevel-count-time').fadeOut(200, function() {
            jQuery(this).text(stats.time + 'ms');
        }).fadeIn(200);

        jQuery('#lddevel-count-query').fadeOut(200, function() {
            jQuery(this).text(stats.sql);
        }).fadeIn(200);

        jQuery('#lddevel-count-querytime').fadeOut(200, function() {
            jQuery(this).text(stats.sqltime + 'ms');
        }).fadeIn(200);

        jQuery('#lddevel-count-mem').fadeOut(200, function() {
            jQuery(this).text(stats.memory + ' (' + stats.memorypeak + ')');
        }).fadeIn(200);

        jQuery('#lddevel-count-log').fadeOut(200, function() {
            jQuery(this).text(stats.log);
        }).fadeIn(200);

    },

    /*
     * Show the bar after being collpased
     */
    show: function() {

        lddevel.el.closed_tabs.fadeOut(600, function () {
            lddevel.el.main.removeClass('lddevel-hidden');
            lddevel.el.open_tabs.fadeIn(200);
        });
        lddevel.el.main.animate({width: '100%'}, 700);

    },

    /*
     * Hide the bar
     */
    hide: function(animation) {

        lddevel.close_config();
        lddevel.close_window();

        if (animation == false) {
            lddevel.el.main.addClass('lddevel-hidden');
            lddevel.el.open_tabs.hide();
            lddevel.el.closed_tabs.show();
            lddevel.el.main.css({width: lddevel.mini_button_width});
        } else {
            setTimeout(function() {
                lddevel.el.window.slideUp(400, function () {
                    lddevel.close_window();
                    lddevel.el.main.addClass('lddevel-hidden');
                    lddevel.el.open_tabs.fadeOut(200, function () {
                        lddevel.el.closed_tabs.fadeIn(200);
                    });
                    lddevel.el.main.animate({width: lddevel.mini_button_width}, 700);
                });
            }, 100);
        }

    },

    /*
     * Set fullscreen or not
     */
    zoom: function() {

        if (lddevel.is_zoomed) {
            height = lddevel.small_height;
            lddevel.is_zoomed = false;
        } else {
            // the 6px is padding on the top of the window
            height = (jQuery(window).height() - lddevel.el.tabs.height() - 6) + 'px';
            lddevel.is_zoomed = true;
        }

        var sel = '';
        if (lddevel.active_pane == 'lddevel-request-options')
        {
            sel = '#' + lddevel.active_pane;
        }
        else
        {
            sel = '#lddevel-request-' + lddevel.active_request;
        }

        jQuery(sel).animate({height: height}, 700);

    }

};

// launch lddevel on jquery dom ready
jQuery(document).ready(function() {
    lddevel.start();
});
