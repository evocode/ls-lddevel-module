
jQuery(document).ready(function($) {
    $('div.devel-heading').live('click', function() {
        if($(this).next().is(':visible')) {
            $(this).find('.min').text('+');
            $(this).next().toggle();
        } else {
            $(this).find('.min').text('-');
            $(this).next().toggle();
        }
    });
});

var LDDevel = LDDevel || {};

LDDevel.Config = {
    enableDebug: true,
	debugCount: 0,
    hasFirebug: false,
	forceFirebug: true,
	isFirefox: (/Firefox/i.test(navigator.userAgent))?true:false,
    cssclass: "#devel-log"
};

LDDevel.Logger = {
    current: null,
    firstRequest: true,
    firstHeader: true,

    init: function(options) {
        LDDevel.Config = $.extend(LDDevel.Config, options);

        if(!this.firstRequest) {
            this.minimizeAll();
        }

        this.firstRequest = false;
        this.firstHeader = true;
    },

    log: function (obj, msgtype) {
        if (LDDevel.Config.enableDebug) {
            if( msgtype == null )
                msgtype = "log";

            if (!LDDevel.Config.hasFirebug) {
                this.traceHtml(obj, msgtype);
            } else {
                try {
                    if( msgtype == "log" )
                        console.log(obj);
                    else if( msgtype == "debug" )
                        console.debug(obj);
                    else if( msgtype == "warn" )
                        console.warn(obj);
                    else if( msgtype == "info" )
                        console.info(obj);
                    else if( msgtype == "error" )
                        console.error(obj);
                } catch(e) {
                    this.traceHtml(obj, msgtype);
                }
            }
        }
    },

    logQuery: function(queryObj) {
        if (LDDevel.Config.enableDebug) {
            if (!LDDevel.Config.hasFirebug) {
                this.traceQueryHtml(queryObj);
            } else {
                try {
                    console.log(queryObj);
                } catch(e) {
                    this.traceQueryHtml(queryObj);
                }
            }
        }
    },

    logQueryTable: function(queryObj) {
        if (LDDevel.Config.enableDebug) {
            if (!LDDevel.Config.hasFirebug) {
                this.traceQueryHtml(queryObj);
            } else {
                try {
                    var columns = [
                        { property:"sql", label: "Sql Query" },
                        { property:"time", label: "Execution Time" },
                        { property:"memory", label: "Memory Allocated" }
                    ];
                    console.table(queryObj, columns);
                } catch(e) {
                    this.traceQueryHtml(queryObj);
                }
            }
        }
    },

    startGroup: function(titlename) {
        if (LDDevel.Config.enableDebug) {
            if (!LDDevel.Config.hasFirebug) {
                this.groupStartHtml(titlename);
            } else {
                try {
                    console.group(titlename);
                } catch(e) {
                    this.groupStartHtml(titlename);
                }
            }
        }
    },

    endGroup: function() {
        if (LDDevel.Config.enableDebug) {
            if (!LDDevel.Config.hasFirebug) {
                this.groupEndHtml();
            } else {
                try {
                    console.groupEnd();
                } catch(e) {
                    this.groupEndHtml();
                }
            }
        }
    },

    /* HTML Functions */

    htmlEncode: function(html) {
        return html;
    },

    /* Other Functions */

    traceHtml: function(str, atype) {
        if (LDDevel.Config.enableDebug) {
            if( atype != "html" ) {
                str = String(str);
                str = this.htmlEncode(str);
            }

            this.current.append('<div class="log-entry">' + str + '</div>');

            /*TODO for objects
            LDDevel.Debug.traceHtml(obj);
            for (var i in obj) {
                LDDevel.Debug.trace(i + ": " + obj[i]);
            }*/
        }
    },

    traceQueryHtml: function(queryObj) {
        if (LDDevel.Config.enableDebug) {
            var html = '<div class="query-log">';
            for (var i in queryObj) {
                if( typeof(queryObj[i]['id']) == "undefined" )
                    break;

                var cclass = i % 2 == 0 ? ' query-log-odd' : ' query-log-even';
                cclass += ' query-log-p' + queryObj[i]['priority'];
                html += '<div class="query-log-entry' + cclass + '"><div class="query-log-number">' + queryObj[i]['id'] + '</div><div class="query-log-query">' + queryObj[i]['sql'] + '</div><div class="query-log-time">' + queryObj[i]['single_time'] + 's [' + queryObj[i]['total_time'] + 's]<br />' + queryObj[i]['single_memory'] + ' [' + queryObj[i]['total_memory'] + ']</div><div class="devel-clear"></div></div>';
            }
            html += '</div>';

            this.current.append(html);
        }
    },

    groupStartHtml: function(titlename) {
        if (LDDevel.Config.enableDebug) {
            str = String(titlename);
            str = this.htmlEncode(str);

            var classes = 'devel-heading';
            if(this.firstHeader) {
                classes += ' devel-root';
                this.firstHeader = false;
            }

            var container = jQuery('<div class="' + classes + '">' + str + '<span class="min">-</span></div><div class="devel-container" style="display: block"></div>');

            if( !this.current )
                this.current = jQuery(LDDevel.Config.cssclass);

            container.appendTo(this.current);

            this.current = container.filter('.devel-container');
        }
    },

    groupEndHtml: function() {
        var parent = this.current.parent('.devel-container:first');
        this.current = parent.length > 0 ? parent : null;
    },

    minimizeAll: function() {
        $('div.devel-root').each(function(index) {
            if($(this).next().is(':visible')) {
                $(this).find('.min').text('+');
                $(this).next().toggle();
            }
        })
    }
};
