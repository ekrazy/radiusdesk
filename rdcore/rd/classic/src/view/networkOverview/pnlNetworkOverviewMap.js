Ext.define('Rd.view.networkOverview.pnlNetworkOverviewMap', {
    extend      : 'Ext.panel.Panel',
    alias       : 'widget.pnlNetworkOverviewMap',
    requires    : [
        'Rd.view.networkOverview.vcNetworkOverviewMap',
        'Rd.view.networkOverview.pnlNetworkOverviewMapLeaflet',
        'Rd.view.networkOverview.treeNetworkOverview'
    ],
    listeners   : {
       show        : 'reload'
    },
    controller  : 'vcNetworkOverviewMap',
	dockedItems : [   
        {
            xtype   : 'toolbar',
            dock    : 'top',
            items   : [        
                {
                    ui      : 'button-metal', 
                    glyph   : Rd.config.icnHome, 
                    scale   : 'small', 
                    itemId  : 'home', 
                    tooltip : i18n('sHome'),
                    listeners       : {
				        click : 'onBtnHome'
		            }
                },
                {
                    ui      : 'button-orange', 
                    glyph   : Rd.config.icnReload, 
                    scale   : 'small', 
                    itemId  : 'reload', 
                    tooltip : i18n('sReload'),
                    listeners       : {
				        click : 'onBtnReload'
		            }
                },
                '|',
                { 
                    xtype       : 'button', 
                    scale       : 'small',
                    toggleGroup : 'timeSpanDash',
                    pressed     : true,			
                    text        : 'NOW',
                    ui          : 'button-metal',
                    listeners   : {
                        click: 'onClickNowButton'
                    }
                },                           
                { 
                    xtype       : 'button', 
                    scale       : 'small',
                    toggleGroup : 'timeSpanDash',		
                    text        : '24 Hours',
                    ui          : 'button-metal',
                    listeners   : {
                        click: 'onClickDayButton'
                    }
                },
                { 
                    xtype       : 'button',
                    ui          : 'button-metal', 
                    text        : '7 Days',
                    toggleGroup : 'timeSpanDash',   
                    scale       : 'small',
                    listeners   : {
                         click: 'onClickWeekButton'
                    }
                }
            ]
        },
        {
            itemId      : 'cntBanner',
            reference   : 'cntBanner',
            xtype       : 'container',
            dock        : 'top',
            style       : { 
                background  : '#adc2eb'
            },
            height      : 70,
            tpl         : new Ext.XTemplate(            
                "<div class='info_bnr'>",
                    "<div class='info_bnr_row'>",     
                      "<div class=\"info_bnr_column\">",      
                        "<div class=\"info_bnr_container\" style='height:70px;float:left; width:50%;'>",
                          "<div class=\"info_bnr_vertical-center\" style='padding-left:10px;'>",
                            "<div class=\"info_bnr_place\">",
                                "<i class=\"fa fa-{fa_icon}\"></i> {text}",
                            "</div>",
                          "</div>",
                        "</div>",
                        '<div style="float:left; width:50%;">',
                            '<ul class="fa-ul">',
                            '<tpl for="level_stats">',
                                '<li><i class="fa-li fa fa-{fa_icon}"></i> {name} {count}</li>',
                            '</tpl>',
                            '</ul>',
                        '</div>',      
                      "</div>",
                      '<div class="info_bnr_column style="text-align:center;">',     
                        '<div style="font-size:larger;">',
                            '<ul class="fa-ul">',
                              '<li style="padding:2px;"><span class="fa-li" style="font-family:FontAwesome;">&#xf20e</span> MESHES {total_networks} <span style="color:green;">({total_networks_online} ONLINE)</span></li>',
                              '<li style="padding:2px;"><i class="fa-li fa fa-cube"></i> Nodes {total_nodes} <span style="color:green;">({total_nodes_online} ONLINE)</span></li>',
                            '</ul>',
                        '</div>', 
                      '</div>',
                      '<div class="info_bnr_column">',
                        '<div style="font-size:larger;">',
                            '<ul class="fa-ul">',
                              '<li style="padding:2px;"><i class="fa-li fa fa-wifi"></i> APs/ROUTERS {total_aps} <span style="color:green;">({total_aps_online} ONLINE)</span></li>',
                            '</ul>',
                        '</div>',                  
                      '</div>',
                    '</div>',
                '</div>'
            ),
            data    : {
                'text': 'HOME',
                'fa_icon': 'home'
            }
        }
    ],
	initComponent: function(){
        var me      = this;	
	    me.callParent(arguments);
    },
    layout: 'border',
    items: [
        {
            region  : 'west',
            xtype   : 'treeNetworkOverview',
            width   : 270,
            split   : true,
            layout  : 'fit',
            ui      : 'panel-green',
            title   : 'NAVIGATION',
            padding : 10,
            location: 'map',
            tools   : [
                {
                    tooltip : 'Edit Item',
                    itemId  : 'toolEditNode',
                    glyph   : Rd.config.icnEdit
                }
            ]
        },
        {
            region  : 'center',     // center region is required, no width/height specified
            xtype   : 'pnlNetworkOverviewMapLeaflet',
            itemId  : 'pnlMap',
            layout  : 'fit',
            metaData: {},
            padding : 10
        }
    ]
});
