var clearbase = clearbase || {};
window.cb_counter = 0;

jQuery(document).ready( function($) {
    var isMobile = $(document.body).hasClass('mobile');

    clearbase.handleResponse = function(response) {
        if (_.isString(response))
            response = $.parseJSON( response );
        
        if (_.isUndefined(response.data) || _.isUndefined(response.data.message))
            return response;

        if ('html' === response.data.message_type)
            $('.workspace-h2').after(response.data.message);
        else {
            var $notice = $('#clearbase_ajax_notice');
            if (!$notice.length) {
                $notice = $('<div id="clearbase_ajax_notice"style="position: relative; display:none;"><p></p>' +
                    '<button type="button" class="notice-dismiss">' +
                        '<span class="screen-reader-text">'+ clearbase.l10n.notice_dismiss + '</span>' +
                    '</button>' +
                '</div>').insertAfter('.workspace-h2');
            }
            $notice.removeClass().addClass('notice-box').addClass(response.success ? 'updated' : 'error');
            $notice.find('p').html(response.data.message);
            $notice.fadeIn();
        }
            
        return response;
    };

    clearbase.spinner = function(show) {
        show = typeof show !== 'undefined' ? show : true;
        if (show)
            $('body').append('<img class="clearbase-global-spinner" style="display: block; position: fixed; top: 50%; left: 50%; z-index: 100000;" src="' + clearbase.url + '/images/spinner.gif">');
        else
            $('body .clearbase-global-spinner').remove();
    };

    clearbase.moveToFolder = function(folderID, posts) {
        clearbase.spinner();
        return $.post(ajaxurl, 
            {
                action:'clearbase_move_to_folder',
                cbnonce: clearbase.cbnonce,
                folderid: folderID,
                posts: posts
            },
            function(response) {
                var response = clearbase.handleResponse(response);
                if (response.success) {
                    window.location.href = clearbase.workspaceUrl + '&id=' + folderID;
                } else {
                    clearbase.spinner(false);
                }
            }
        );
    };

    $(window).on('beforeunload', function(){
        if (clearbase.unloadSaveAlert)
            return clearbase.l10n.saveAlert;
    });


    $( '#clearbase-workspace').on('change', ':input',  function() {
        clearbase.unloadSaveAlert = true;
    })
    .on('click', 'input[type="submit"]', function() {
        clearbase.unloadSaveAlert = false;
    })
    .on( 'click', '.notice-dismiss', function() {
        $(this).parents('.notice-box').fadeOut();
    })
    .on( 'change', '.clearbase-controller-switcher', function() {
        $('#cb_controller_editor_fields')
            .animate({opacity: 0.3})
            .find('.waiting-overlay').show();

        $('#clearbase-workspace').find('input[type="submit"][name="save-editor"]').trigger('click');
    })
    .on('click', '#edit-slug-buttons', function( event ) {
        event.preventDefault();
        var $target = $( event.target );

        if ( $target.is('#editable-post-name') || $target.hasClass('edit-slug') ) {
            clearbase.editPermalink();
        }

        return false;
    });

    // prevent wp script errors
    if (_.isUndefined(window.editPermalink))
        var makeSlugeditClickable = editPermalink = function(){};

    clearbase.editPermalink = function() {
        var i, slug_value,
        $el, revert_e,
        c = 0,
        real_slug = $('#post-post_name'),
        revert_slug = real_slug.val(),
        permalink = $( '#sample-permalink' ),
        permalinkOrig = permalink.html(),
        permalinkInner = $( '#sample-permalink a' ).html(),
        buttons = $('#edit-slug-buttons'),
        buttonsOrig = buttons.html(),
        full = $('#editable-post-name-full');

        // Deal with Twemoji in the post-name
        full.find( 'img' ).replaceWith( function() { return this.alt; } );
        full = full.html();

        permalink.html( permalinkInner );
        $el = $( '#editable-post-name' );
        revert_e = $el.html();

        buttons.html( '<button type="button" class="save button button-small">' + clearbase.l10n.ok + '</button> <button type="button" class="cancel button-link">' + clearbase.l10n.cancel + '</button>' );
        buttons.children( '.save' ).click( function() {
            var new_slug = $el.children( 'input' ).val();

            if ( new_slug == $('#editable-post-name-full').text() ) {
                buttons.children('.cancel').click();
                return;
            }
            $.post(ajaxurl, {
                action: 'sample-permalink',
                post_id: clearbase.post.ID,
                new_slug: new_slug,
                new_title: $('#post-post_title').val(),
                samplepermalinknonce: clearbase.permalink_nonce
            }, function(data) {
                var box = $('#edit-slug-box');
                box.html(data);
                if (box.hasClass('hidden')) {
                    box.fadeIn('fast', function () {
                        box.removeClass('hidden');
                    });
                }

                buttons.html(buttonsOrig);
                permalink.html(permalinkOrig);
                real_slug.val(new_slug);
                $( '.edit-slug' ).focus();
                //wp.a11y.speak( postL10n.permalinkSaved );
            });
        });

        buttons.children( '.cancel' ).click( function() {
            $('#view-post-btn').show();
            $el.html(revert_e);
            buttons.html(buttonsOrig);
            permalink.html(permalinkOrig);
            real_slug.val(revert_slug);
            $( '.edit-slug' ).focus();
        });

        for ( i = 0; i < full.length; ++i ) {
            if ( '%' == full.charAt(i) )
                c++;
        }

        slug_value = ( c > full.length / 4 ) ? '' : full;
        $el.html( '<input type="text" id="new-post-slug" value="' + slug_value + '" autocomplete="off" />' ).children( 'input' ).keydown( function( e ) {
            var key = e.which;
            // On enter, just save the new slug, don't save the post.
            if ( 13 === key ) {
                e.preventDefault();
                buttons.children( '.save' ).click();
            }
            if ( 27 === key ) {
                buttons.children( '.cancel' ).click();
            }
        } ).keyup( function() {
            real_slug.val( this.value );
        }).focus();
    };



    //fixes a table row when being dragsorted
    var fixHelperModified = function(e, tr) {
        var originals = tr.children(),
            helper = tr.clone();
        helper.children().each(function(index) {
          $(this).width(originals.eq(index).width());
        });
        return helper;
    };

    $sortables = $(".ui-sortable-container").add(
        //make the WP media sortable only if there is a td with class: column-dragsort
        $('td.column-dragsort').closest('tbody')
    );
    $sortables.sortable({
        handle: '.ui-draggable-handle',
        delay: ( isMobile ? 200 : 0 ),
        distance: 2,
        tolerance: 'default',
        revert: true,
        revertDuration: 5,
        start: function(e, ui){
            ui.placeholder.height(ui.item.height());
        },
        update: function(event, ui) {
            var serialized = {};

            $(this).children().each( function(index, el) {
                serialized[el.id.substring(el.id.lastIndexOf('-') + 1)] = index;
            });

            $.post(ajaxurl, 
                {
                    action:'clearbase_sort_posts',
                    cb_post: clearbase.post,
                    cbnonce: clearbase.cbnonce,
                    posts: serialized
                },
                function(response) {
                    clearbase.handleResponse(response);
                }
            );
        }
    }).draggable({
        handle: '.ui-draggable-handle',
        helper: fixHelperModified,
        revert: true,
        revertDuration: 5,
        connectToSortable: ".clearbase-folder",
    });

    //refresh folder positions during sorting so that droppable operations will work correctly
    $sortables.filter('.folders').sortable('option', { refreshPositions: true });

    
    $(".clearbase-folder.allow-folders").droppable({
        hoverClass: "ui-droppable-hover",
        tolerance: 'pointer',
        drop: function(event, ui) {
            var $self = $(this);
            //hide the dragged item
            var $el = ui.draggable.css('display', 'none');
            //hide the placeholder from the list where the item was dragged from
            $el.parent('.ui-sortable-container').find('.ui-sortable-placeholder').hide();
            //get the icon from the dragged item
            $icon = $el.find('.media-icon img');
            //insert the icon of the dragged item onto the folder for a visual drop effect
            $self.find('.thumbnail .centered').append($icon);
            //fire the visual drop effect
            $icon.animate({opacity: .0, width: "10%", height: "10%"}, 1000);
            //save our changes to the database
            var serialized = [];
            for (i = 0; i < ui.draggable.length; i++) {
                serialized.push(ui.draggable[i].id.substring(ui.draggable[i].id.lastIndexOf('-') + 1));
            }

            clearbase.moveToFolder($self.attr('id').substring($self.attr('id').lastIndexOf('-') + 1), serialized);
        }
    });

    $('.clearbase-folder .submitdelete').on('click', function(){
        return confirm(clearbase.l10n.deleteWarning);
    });

    //------------  WP Media Integration  -------------------
    if (typeof wp.media !== "undefined") {
        var media = clearbase.media = {};
        _.extend( media, { controller: {}, view: { toolbar: {} } } );

        media.view.FolderTree = wp.media.View.extend( {
            className: 'clearbase-folder-tree',
            template:  _.template('<div id="clearbase-folder-tree">Folder frame coming soon!</div>'),

            render: function() {
                self = this;
                this.$el.empty().jstree({
                'core' : {
                  "themes": {
                        "name": "default",
                        "dots": true,
                        "icons": true
                    },
                  'data' : {
                    'url' : ajaxurl,
                    'data' : { action:'clearbase_get_folder_tree' }
                  }
                },
                "plugins" : ["types"]
                }).on('ready.jstree', function (event, data) { 
                    var node = data.instance.get_node("[id$='-" + clearbase.post.ID +"']");
                    data.instance.disable_node(node);
                    data.instance.open_node(node);
                    data.instance._open_to(node);
                    self.update_nodes(data.instance, self.controller);

                    self.controller.state().on('change:origin_folder', function(){
                        self.update_nodes(data.instance, self.controller);
                    }, this);

                }).on('changed.jstree', function (e, data) {
                    node = data.selected.length == 1 ? data.instance.get_node(data.selected[0]) : false;
                    self.controller.state().set('folderid', node ? 
                        node.id.substring(node.id.lastIndexOf('-') + 1) : false );
                });
            },

            update_nodes : function(jstree, controller) {
                var prev_id = controller.state().previous('origin_folder');
                var origin_folder  = controller.state().get('origin_folder');
                if (!_.isUndefined(prev_id)) 
                    jstree.show_node("[id$='-" + prev_id +"']");
                
                if (origin_folder != clearbase.post.ID) {
                    var $node = $("[id$='-" + origin_folder +"']");
                    jstree.deselect_node($node);
                    jstree.deselect_node($node.find('li'));
                    jstree.hide_node($node);
                }
            }
        });


        // custom toolbar : contains the buttons at the bottom
        media.view.toolbar.FolderTree = wp.media.view.Toolbar.extend({
            initialize: function() {
                _.defaults( this.options, {
                    event: 'move_event',
                    close: false,
                    items: {
                        move_event: {
                            text: clearbase.folderTree.l10n.button,
                            style: 'primary',
                            priority: 80,
                            requires: false,
                            click: function() {
                                var controller = this.controller,
                                state = controller.state();
                                controller.close();
                                state.trigger( 'move', this.controller.state().get('folderid') );
                                // Restore and reset the default state.
                                controller.setState( controller.options.state );
                                controller.reset();
                            }
                        }
                    }
                });

                wp.media.view.Toolbar.prototype.initialize.apply( this, arguments );
            },

            // called each time the model changes
            refresh: function() {
                // you can modify the toolbar behaviour in response to user actions here
                // disable the button if there is no custom data
                var folderid = this.controller.state().get('folderid');
                this.get('move_event').model.set( 'disabled', ! folderid );
                
                // call the parent refresh
                wp.media.view.Toolbar.prototype.refresh.apply( this, arguments );
            },
        });

        media.controller.FolderTree = wp.media.controller.State.extend( {
            defaults: {
                id:       'clearbase-folder-tree-state',
                menu:     'default',
                content:  'folder_tree',
                title:      clearbase.folderTree.l10n.title, // added via 'media_view_strings' filter
                toolbar:    'folder_tree'
            },

            initialize : function() {
                this.set('folderid', '');
                this.on( 'change:folderid change:origin_folder', this.refresh, this );
            },

            // called each time the model changes
            refresh: function() {
                // update the toolbar
                this.frame.toolbar.get().refresh();
            },
        });


        media.controller.Uploader = wp.media.controller.State.extend( {
            defaults: {
                id:       'clearbase-uploader',
                menu:     'default',
                content:  'inline_uploader'
            },
            activate: function() {
                this.frame.on( 'content:create:inline_uploader', this.createUploader, this );
            },

            createUploader: function() {
                var view = new wp.media.view.UploaderInline({
                    controller: this.frame
                } );
                this.frame.content.set( view );
            }
        });


        _.extend( media, {
            uploadFrame: function() {
                if ( this._uploadFrame )
                    return this._uploadFrame;

                var controller = new media.controller.Uploader( {
                    title: clearbase.uploader.l10n.title
                });

                this._uploadFrame = wp.media( {
                    className: 'media-frame no-sidebar',
                    states: [ controller ]
                } ); 

                this._uploadFrame.setState( controller.id );

                wp.Uploader.queue.on( 'reset', function(){
                    //refresh the current page...
                    clearbase.post.has_new_uploads = true;
                    clearbase.media.uploadFrame().close();
                });


                this._uploadFrame.on('ready', function() { $( '.media-modal' ).addClass( 'smaller' ); });

                this._uploadFrame.on('close', function(){
                    if (clearbase.post.has_new_uploads == true) {
                        if (clearbase.post.has_new_uploads == true) {
                            clearbase.spinner();
                            window.location.reload();
                        }
                    }
                });

                return this._uploadFrame;
            },

            folderFrame: function() {
                if ( this._folderFrame )
                    return this._folderFrame;

                var controller = new media.controller.FolderTree();

                this._folderFrame = wp.media( {
                    className: 'media-frame no-sidebar',
                    states: [ controller ]
                } ); 

                this._folderFrame.on('ready', function() { 
                    $( '.media-modal' ).addClass( 'smaller' ); 
                })
                .on( 'content:create:folder_tree', function(){
                    var view = new media.view.FolderTree({
                        controller: this._folderFrame
                    });
                    this._folderFrame.content.set( view );
                }, this )
                .on( 'toolbar:create:folder_tree', function(toolbar) {
                    toolbar.view = new media.view.toolbar.FolderTree({
                        controller: this._folderFrame
                    });
                }, this )
                .on('move', function(folderid){
                    var origin = media.folderFrame().state().get('origin');
                    //if the origin object has a controller object then we know 
                    //that the origin is from the WP attachment browser
                    if (_.isObject(origin.controller)) {

                        var selection = origin.controller.state().get( 'selection' );
                        var serialized = [];

                        selection.each( function( model ) {
                            serialized.push(model.get('id'));
                        });
                        $('.media-toolbar .media-toolbar-secondary .spinner').show();
                        clearbase.moveToFolder(folderid, serialized);

                    } else if ($(origin).is('a')) {
                        clearbase.spinner();
                        window.location.href = ($(origin).attr('href') + '&folderid=' + folderid);
                    }
                    else {
                        clearbase.spinner();
                        $form = $(origin).parents('form');
                        $form.append('<input type="hidden" name="folderid" value="' + folderid +'">');
                        $form.submit();
                    }
                });

                this._folderFrame.setState( controller.id );

                return this._folderFrame;


            },

        });

        //-------------   Media Uploader  ------------------------  
        if ($('#wp-media-grid').length) {
             wp.Uploader.queue.on("add", function(attachment){
                //disable sorting while uploads are in progress
                $('#wp-media-grid ul.attachments.ui-sortable').sortable( 'option', 'disabled', true);
                //set a menu_order value to the pending upload so it will display in the grid properly
                var max = wp.media.model.Attachments.all.max(function(m){
                  return m.get('menuOrder');
                });
                attachment.set('menuOrder', max.get('menuOrder') + 1);
             }).on('remove reset', function(){
                //go ahead and sort to your heart's content
                $('#wp-media-grid ul.attachments.ui-sortable').sortable( 'option', 'disabled', false);
             });
        }

        // Bind to our click event in order to open up the new media experience.
        $(document.body).on('click', '#clearbase-workspace .addnew.media', function(e){
            // Prevent the default action from occuring.
            e.preventDefault();
            if ($('#wp-media-grid').length)
                $('#wp-media-grid .uploader-inline').toggle();
            else 
                media.uploadFrame().open();
        })
        .on('click', '#doaction, #doaction2, a.move-post', function(e){
            if (('doaction'  === this.id && 'move' === $('#bulk-action-selector-top').val()) ||
                ('doaction2' === this.id && 'move' === $('#bulk-action-selector-bottom').val()) || 
                $(this).hasClass('move-post'))  {
                //
                e.preventDefault();
                $self = $(this);
                media.folderFrame().state().set('origin', this);
                //specify the clicked item's origin folder to be disabled in the tree view 
                media.folderFrame().state().set('origin_folder', 1 == $self.parents('.clearbase-folder').length ?
                    $self.parents('.clearbase-folder').attr('id').split("-")[1] : clearbase.post.ID
                );
                //bring up the folder tree view selector
                media.folderFrame().open();

                //prevent default action
                return false; 
            }
           
        });

    }



} );
