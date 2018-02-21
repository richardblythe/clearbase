$ = jQuery;
var MyRouter = Backbone.Router.extend({
    routes: {
        'item=:slug':    'showItem',
        'search=:query': 'search'
    },

    // Map routes against the page URL
    baseUrl: function( url ) {
        return url;
    },

    // Respond to the search route by filling the search field and trigggering the input event
    search: function( query ) {
        $( '#media-search-input' ).val( query ).trigger( 'input' );
    },

    // Show the modal with a specific item
    showItem: function( query ) {
        var media = wp.media,
            library = media.frame.state().get('library'),
            item;

        // Trigger the media frame to open the correct item
        item = library.findWhere( { id: parseInt( query, 10 ) } );
        if ( item ) {
            media.frame.trigger( 'edit:attachment', item );
        } else {
            item = media.attachment( query );
            media.frame.listenTo( item, 'change', function( model ) {
                media.frame.stopListening( item );
                media.frame.trigger( 'edit:attachment', model );
            } );
            item.fetch();
        }
    }
});

wp.media.view.MediaFrame.Manage.Router = MyRouter;

wp.media.view.MoveSelectedButton = wp.media.view.Button.extend({
    initialize: function() {
        wp.media.view.Button.prototype.initialize.apply( this, arguments );
        this.listenTo( this.controller, 'selection:toggle', this.toggleDisabled );
    },

    toggleDisabled: function() {
        this.model.set( 'disabled', ! this.controller.state().get( 'selection' ).length );
    },

    render: function() {
        wp.media.view.Button.prototype.render.apply( this, arguments );
        if ( this.controller.isModeActive( 'select' ) ) {
            this.$el.addClass( 'move-selected-button' );
        } else {
            this.$el.addClass( 'move-selected-button hidden' );
        }
        this.toggleDisabled();
        return this;
    }
});

wp.media.model.Attachments = wp.media.model.Attachments.extend({
    _requery: function( refresh ) {
        var props;
        if ( this.props.get('query') ) {
            props = this.props.toJSON();
            props.cache = ( true !== refresh );
            var query = wp.media.model.Query.get( props );
            //Override the order filter to account for sorting: menuOrder DESC
            query.filters.order = function( attachment ) {
                var orderby = this.props.get('orderby'),
                    order = this.props.get('order');

                if ( ! this.comparator ) {
                    return true;
                }

                // We want any items that can be placed before the last
                // item in the set. If we add any items after the last
                // item, then we can't guarantee the set is complete.
                if ( this.length ) {
                    return 1 !== this.comparator( attachment, this.last(), { ties: true });

                // Handle the case where there are no items yet and
                // we're sorting for recent items. In that case, we want
                // changes that occurred after we created the query.
                } else if ( 'DESC' === order && ( 'date' === orderby || 'modified' === orderby ) ) {
                    return attachment.get( orderby ) >= this.created;

                //********** EDIT BELOW *****************************
                // If we're sorting by menu order and we have no items,
                // accept any items that have the default menu order (0).
                } else if ( 'DESC' === order && 'menuOrder' === orderby ) {
                    return true;//attachment.get( orderby ) === 0;
                }

                // Otherwise, we don't want any items yet.
                return false;
            };
            this.mirror( query );
        }
    },
    /**
     * If this collection is sorted by `menuOrder`, recalculates and saves
     * the menu order to the database.
     *
     * @returns {undefined|Promise}
     */
    saveMenuOrder: function() {
        if ( 'menuOrder' !== this.props.get('orderby') ) {
            return;
        }

        // Removes any uploading attachments, updates each attachment's
        // menu order, and returns an object with an { id: menuOrder }
        // mapping to pass to the request.
        var attachments = this.filter( function( attachment ) {
            return ! _.isUndefined( attachment.id );
        });

        //get the order direction
        var order_desc = ('DESC' == this.props.get('order'));
        var menu_order;
        if (order_desc) {
            //get the max menu order
            menu_order = _.max(attachments, function(m){
              return m.get('menuOrder');
            }).get('menuOrder');

            //If the menu_order has gotten out of sync.  We need to do a hard reset
            //of the menuOrder to ensure that sorting by DESC will perform correctly.
            if (order_desc && menu_order < wp.media.model.Attachments.all.length) {
                menu_order = wp.media.model.Attachments.all.length;
            }

        } else {
            //get the min menu order
            menu_order = _.min(attachments, function(m){
              return m.get('menuOrder');
            }).get('menuOrder');
        }

        attachments = _.chain(attachments).map( function( attachment, index ) {
            attachment.set( 'menuOrder', menu_order );
            menu_order = Math.max(order_desc ? menu_order - 1 : menu_order + 1, 0);
            return [ attachment.id, attachment.get( 'menuOrder') ];
        }).object().value();

        if ( _.isEmpty( attachments ) ) {
            return;
        }

        return wp.media.post( 'save-attachment-order', {
            nonce:       wp.media.model.settings.post.nonce,
            post_id:     wp.media.model.settings.post.id,
            attachments: attachments
        });
    }
});

//update Attachments.all using the updated Attachments collection 
wp.media.model.Attachments.all = new wp.media.model.Attachments();

wp.media.query = function( props ) {
    return new wp.media.model.Attachments( null, {
        props: _.extend( _.defaults( props || {}, { orderby: 'date' } ), { query: true } )
    });
};

//override wp.media.View.Attachments to allow sorting on touch devices
wp.media.view.Attachments = wp.media.view.Attachments.extend({
    initSortable: function() {
        var collection = this.collection;

        if ( /*wp.media.isTouchDevice || */! this.options.sortable || ! $.fn.sortable ) {
            return;
        }

        this.$el.sortable( _.extend({
            // If the `collection` has a `comparator`, disable sorting.
            disabled: !! collection.comparator,

            // Change the position of the attachment as soon as the
            // mouse pointer overlaps a thumbnail.
            tolerance: 'pointer',

            delay: 250,

            // Record the initial `index` of the dragged model.
            start: function( event, ui ) {
                ui.item.data('sortableIndexStart', ui.item.index());
                ui.placeholder.css('height', ui.item.innerHeight() - 1);
            },

            // Update the model's index in the collection.
            // Do so silently, as the view is already accurate.
            update: function( event, ui ) {
                var model = collection.at( ui.item.data('sortableIndexStart') ),
                    comparator = collection.comparator;

                // Temporarily disable the comparator to prevent `add`
                // from re-sorting.
                delete collection.comparator;

                // Silently shift the model to its new index.
                collection.remove( model, {
                    silent: true
                });
                collection.add( model, {
                    silent: true,
                    at:     ui.item.index()
                });

                // Restore the comparator.
                collection.comparator = comparator;

                // Fire the `reset` event to ensure other collections sync.
                collection.trigger( 'reset', collection );

                // If the collection is sorted by menu order,
                // update the menu order.
                collection.saveMenuOrder();
            }
        }, this.options.sortable ) );

        // If the `orderby` property is changed on the `collection`,
        // check to see if we have a `comparator`. If so, disable sorting.
        collection.props.on( 'change:orderby', function() {
            this.$el.sortable( 'option', 'disabled', !! collection.comparator );
        }, this );

        this.collection.props.on( 'change:orderby', this.refreshSortable, this );
        this.refreshSortable();
    },

    refreshSortable: function() {
        if ( /*wp.media.isTouchDevice ||*/ ! this.options.sortable || ! $.fn.sortable ) {
            return;
        }

        // If the `collection` has a `comparator`, disable sorting.
        var collection = this.collection,
            orderby = collection.props.get('orderby'),
            enabled = 'menuOrder' === orderby || ! collection.comparator;

        this.$el.sortable( 'option', 'disabled', ! enabled );
    }
});


var origin_AttachmentsBrowser = wp.media.view.AttachmentsBrowser;
wp.media.view.AttachmentsBrowser = wp.media.view.AttachmentsBrowser.extend({
    createToolbar: function() {
        origin_AttachmentsBrowser.prototype.createToolbar.apply( this, arguments );
        //now add our move button
        this.toolbar.set( 'moveSelectedButton', new wp.media.view.MoveSelectedButton({
            style: 'primary',
            disabled: true,
            text: 'Move Selected',
            controller: this.controller,
            priority: -40,
            click: function() {
                clearbase.media.folderFrame().state().set('origin', this);
                //specify the clicked item's origin folder to be disabled in the tree view 
                clearbase.media.folderFrame().state().set('origin_folder', clearbase.post.ID
                );
                //bring up the folder tree view selector
                clearbase.media.folderFrame().open();

            }
        }).render() );
    }
});


//override
wp.media.view.AttachmentFilters.All = wp.media.view.AttachmentFilters.extend({
    createFilters: function() {
        var filters = {};

        _.each( wp.media.view.settings.mimeTypes || {}, function( text, key ) {
            filters[ key ] = {
                text: text,
                props: {
                    status:  null,
                    type:    key,
                    uploadedTo: null,
                    orderby: 'menuOrder',
                    order:   'DESC'
                }
            };
        });

        filters.all = {
            text:  wp.media.view.l10n.allMediaItems,
            props: {
                status:  null,
                type:    null,
                uploadedTo: wp.media.view.settings.post.id,
                orderby: 'menuOrder',
                order:   'DESC'
            },
            priority: 10
        };


        if ( wp.media.view.settings.mediaTrash &&
            this.controller.isModeActive( 'grid' ) ) {

            filters.trash = {
                text:  wp.media.view.l10n.trash,
                props: {
                    uploadedTo: null,
                    status:     'trash',
                    type:       null,
                    orderby:    'date',
                    order:      'DESC'
                },
                priority: 50
            };
        }

        this.filters = filters;
    }
});


wp.media.view.DateFilter = wp.media.view.DateFilter.extend({

    createFilters: function() {
        var filters = {};
        _.each( wp.media.view.settings.months || {}, function( value, index ) {
            filters[ index ] = {
                text: value.text,
                props: {
                    year: value.year,
                    monthnum: value.month
                }
            };
        });
        filters.all = {
            text:  wp.media.view.l10n.allDates,
            props: {
                monthnum: false,
                year:  false,
                orderby: 'menuOrder',
                order:   'DESC'
            },
            priority: 10
        };
        this.filters = filters;
    }
});


wp.media.view.SelectModeToggleButton = wp.media.view.SelectModeToggleButton.extend({
    toggleBulkEditHandler: function() {
        //alert('We have extended!');
        var toolbar = this.controller.content.get().toolbar, children;

        children = toolbar.$( '.media-toolbar-secondary > *, .media-toolbar-primary > *' );

        // TODO: the Frame should be doing all of this.
        if ( this.controller.isModeActive( 'select' ) ) {
            this.model.set( {
                size: 'large',
                text: wp.media.view.l10n.cancelSelection
            } );
            children.not( '.spinner, .media-button ' ).hide();
            this.$el.show();
            toolbar.$( '.delete-selected-button, .move-selected-button' ).removeClass( 'hidden' );
        } else {
            this.model.set( {
                size: '',
                text: wp.media.view.l10n.bulkSelect
            } );
            this.controller.content.get().$el.removeClass( 'fixed' );
            toolbar.$el.css( 'width', '' );
            toolbar.$( '.delete-selected-button, .move-selected-button' ).addClass( 'hidden' );
            children.not( '.media-button' ).show();
            this.controller.state().get( 'selection' ).reset();
        }
    }
});


var origin_TwoColumn = wp.media.view.Attachment.Details.TwoColumn;
wp.media.view.Attachment.Details.TwoColumn = wp.media.view.Attachment.Details.TwoColumn.extend({
    render: function() {
        origin_TwoColumn.prototype.render.apply( this, arguments );
        this.$el.find("a[href^='post.php'][href*='action=edit']").attr('href', clearbase.workspaceUrl + 
            '&id=' + this.model.get('id') + 
            '&action=edit');
    }
});

jQuery(document).ready( function($) {

  var settings, $mediaGridWrap = $( '#wp-media-grid' );

  // Open up a manage media frame into the grid.
  if ( $mediaGridWrap.length) {
      settings = _wpMediaGridSettings;

      window.wp.media({
          frame: 'manage',
          container: $mediaGridWrap,
          library: settings.queryVars
      }).open();

  }

} );