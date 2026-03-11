/* SEO AI Bulk — Admin JS */
/* global seoaiBulk, jQuery */

(function ($) {
	'use strict';

	var SEOAIBulk = {

		posts: [],
		totalPosts: 0,
		completedPosts: 0,

		init: function () {
			this.bindSettings();

			if ( typeof seoaiBulk === 'undefined' ) return;

			if ( seoaiBulk.trigger && seoaiBulk.postIds && seoaiBulk.postIds.length > 0 ) {
				this.posts = seoaiBulk.postIds.slice();
				this.totalPosts = this.posts.length;
				this.completedPosts = 0;
				this.openModal();
				this.startGenerating();
			}

			this.bindModalEvents();
		},

		/* ---------------------------------------------------------------
		   Settings page
		--------------------------------------------------------------- */
		bindSettings: function () {
			var $provider = $( '#seoai_provider' );
			if ( ! $provider.length ) return;

			$provider.on( 'change', this.toggleProviderFields.bind( this ) );
			this.toggleProviderFields();

			$( '#seoai-test-connection' ).on( 'click', this.testConnection.bind( this ) );

			// Warn if form is changed but not saved
			var formDirty = false;
			$( '#seoai_provider, #seoai_api_key, #seoai_model, #seoai_ollama_endpoint' ).on( 'change input', function () {
				formDirty = true;
				$( '#seoai-unsaved-notice' ).show();
			} );
			$( 'form' ).on( 'submit', function () { formDirty = false; $( '#seoai-unsaved-notice' ).hide(); } );

			// Inject unsaved notice after the h1
			$( '<div id="seoai-unsaved-notice" class="notice notice-warning" style="display:none;margin-left:0"><p><strong>You have unsaved changes.</strong> Click "Save Settings" before running bulk generation.</p></div>' )
				.insertAfter( '.wrap h1' );
		},

		toggleProviderFields: function () {
			var provider = $( '#seoai_provider' ).val();
			$( '.seoai-api-key-row' ).toggle( provider !== 'ollama' );
			$( '.seoai-ollama-row' ).toggle( provider === 'ollama' );

			// Show the right API key hint
			$( '.seoai-key-hint' ).hide();
			$( '.seoai-key-hint-' + provider ).show();
		},

		testConnection: function () {
			var $btn    = $( '#seoai-test-connection' );
			var $result = $( '#seoai-test-result' );

			$btn.prop( 'disabled', true );
			$result.text( ( seoaiBulk.i18n && seoaiBulk.i18n.testing ) || 'Testing...' ).css( 'color', '#50575e' );

			$.post( seoaiBulk.ajaxUrl, {
				action:    'seoai_test',
				nonce:     seoaiBulk.nonce,
				// Send current form values so the test uses what's on screen, not what's saved.
				provider:  $( '#seoai_provider' ).val(),
				api_key:   $( '#seoai_api_key' ).val(),
				model:     $( '#seoai_model' ).val(),
				endpoint:  $( '#seoai_ollama_endpoint' ).val(),
			} )
			.done( function ( resp ) {
				if ( resp.success ) {
					$result.text( ( seoaiBulk.i18n && seoaiBulk.i18n.testOk ) || resp.data.message ).css( 'color', '#1a7931' );
				} else {
					$result.text( ( resp.data && resp.data.message ) || ( ( seoaiBulk.i18n && seoaiBulk.i18n.testFail ) || 'Failed.' ) ).css( 'color', '#d63638' );
				}
			} )
			.fail( function () {
				$result.text( ( seoaiBulk.i18n && seoaiBulk.i18n.testFail ) || 'Connection failed.' ).css( 'color', '#d63638' );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
		},

		/* ---------------------------------------------------------------
		   Modal
		--------------------------------------------------------------- */
		openModal: function () {
			$( '#seoai-modal-overlay' ).fadeIn( 200 );
			$( 'body' ).css( 'overflow', 'hidden' );
		},

		closeModal: function () {
			$( '#seoai-modal-overlay' ).fadeOut( 200 );
			$( 'body' ).css( 'overflow', '' );
		},

		bindModalEvents: function () {
			$( document )
				.on( 'click', '#seoai-modal-close, #seoai-close-modal', this.closeModal.bind( this ) )
				.on( 'click', '#seoai-modal-overlay', function ( e ) {
					if ( $( e.target ).is( '#seoai-modal-overlay' ) ) {
						SEOAIBulk.closeModal();
					}
				} )
				.on( 'click', '#seoai-apply-all', this.applyAll.bind( this ) )
				.on( 'click', '.seoai-apply-btn', function () {
					SEOAIBulk.saveRow( $( this ).closest( 'tr' ) );
				} )
				.on( 'click', '.seoai-skip-btn', function () {
					SEOAIBulk.skipRow( $( this ).closest( 'tr' ) );
				} )
				.on( 'click', '.seoai-regenerate-btn', function () {
					var $row   = $( this ).closest( 'tr' );
					var postId = $row.data( 'post-id' );
					SEOAIBulk.generateForRow( $row, postId );
				} )
				.on( 'input', '.seoai-title-input', function () {
					SEOAIBulk.updateCharCount( $( this ), 60 );
				} )
				.on( 'input', '.seoai-desc-input', function () {
					SEOAIBulk.updateCharCount( $( this ), 160 );
				} );
		},

		/* ---------------------------------------------------------------
		   Generation flow
		--------------------------------------------------------------- */
		startGenerating: function () {
			var self     = this;
			var $tbody   = $( '#seoai-review-tbody' );
			$tbody.empty();

			$.each( this.posts, function ( idx, postId ) {
				$tbody.append( self.buildRow( postId ) );
			} );

			this.updateProgress( 0 );

			// Sequential generation to respect rate limits
			this.generateNext( 0 );
		},

		generateNext: function ( index ) {
			if ( index >= this.posts.length ) {
				this.updateProgress( this.totalPosts );
				return;
			}

			var self   = this;
			var postId = this.posts[ index ];
			var $row   = $( '#seoai-row-' + postId );

			this.generateForRow( $row, postId, function () {
				self.completedPosts++;
				self.updateProgress( self.completedPosts );
				self.generateNext( index + 1 );
			} );
		},

		generateForRow: function ( $row, postId, callback ) {
			this.setRowStatus( $row, 'generating' );
			this.setRowInputsDisabled( $row, true );

			$.post( seoaiBulk.ajaxUrl, {
				action:  'seoai_generate',
				nonce:   seoaiBulk.nonce,
				post_id: postId,
			} )
			.done( function ( resp ) {
				if ( resp.success && resp.data ) {
					$row.find( '.seoai-title-input' ).val( resp.data.title || '' );
					$row.find( '.seoai-desc-input' ).val( resp.data.description || '' );
					$row.find( '.seoai-keyword-input' ).val( resp.data.keyword || '' );
					SEOAIBulk.updateCharCount( $row.find( '.seoai-title-input' ), 60 );
					SEOAIBulk.updateCharCount( $row.find( '.seoai-desc-input' ), 160 );
					SEOAIBulk.setRowStatus( $row, 'done' );
					SEOAIBulk.setRowInputsDisabled( $row, false );
					$row.data( 'generated', true );
				} else {
					var msg = ( resp.data && resp.data.message ) ? resp.data.message : 'Unknown error';
					SEOAIBulk.setRowStatus( $row, 'error', msg );
					SEOAIBulk.setRowInputsDisabled( $row, false );
				}
			} )
			.fail( function () {
				SEOAIBulk.setRowStatus( $row, 'error', 'Request failed.' );
				SEOAIBulk.setRowInputsDisabled( $row, false );
			} )
			.always( function () {
				if ( typeof callback === 'function' ) callback();
			} );
		},

		/* ---------------------------------------------------------------
		   Row helpers
		--------------------------------------------------------------- */
		buildRow: function ( postId ) {
			var editUrl  = window.location.origin + '/wp-admin/post.php?post=' + postId + '&action=edit';
			var postTitle = 'Post #' + postId; // placeholder; title shown after load

			var row = $( '<tr>' )
				.attr( 'id', 'seoai-row-' + postId )
				.data( 'post-id', postId );

			row.html(
				'<td class="seoai-post-title"><a href="' + editUrl + '" target="_blank">#' + postId + '</a></td>' +
				'<td>' +
					'<input type="text" class="seoai-title-input" maxlength="80" placeholder="SEO Title" disabled />' +
					'<span class="seoai-char-count">0 / 60</span>' +
				'</td>' +
				'<td>' +
					'<textarea class="seoai-desc-input" maxlength="250" placeholder="Meta Description" disabled></textarea>' +
					'<span class="seoai-char-count">0 / 160</span>' +
				'</td>' +
				'<td><input type="text" class="seoai-keyword-input" placeholder="Focus Keyword" disabled /></td>' +
				'<td><span class="seoai-status seoai-status-pending">Pending</span></td>' +
				'<td class="seoai-row-actions">' +
					'<button type="button" class="button button-small seoai-apply-btn" disabled>' + this.i18n( 'apply' ) + '</button>' +
					'<button type="button" class="button button-small seoai-skip-btn">' + this.i18n( 'skip' ) + '</button>' +
					'<button type="button" class="button button-small seoai-regenerate-btn" disabled>' + this.i18n( 'regenerate' ) + '</button>' +
				'</td>'
			);

			return row;
		},

		setRowStatus: function ( $row, status, msg ) {
			var labels = {
				pending:    this.i18n( 'pending', 'Pending' ),
				generating: '<span class="seoai-spinner"></span>' + this.i18n( 'generating' ),
				done:       this.i18n( 'done' ),
				saved:      this.i18n( 'saved' ),
				skipped:    this.i18n( 'skipped' ),
				error:      this.i18n( 'error' ),
			};

			var label = labels[ status ] || status;
			if ( status === 'error' && msg ) {
				label += ': ' + $( '<span>' ).text( msg ).html();
			}

			$row.find( '.seoai-status' )
				.attr( 'class', 'seoai-status seoai-status-' + status )
				.html( label );

			// Toggle action buttons
			var isReady = ( status === 'done' || status === 'error' );
			$row.find( '.seoai-apply-btn' ).prop( 'disabled', ! isReady );
			$row.find( '.seoai-regenerate-btn' ).prop( 'disabled', ! isReady );
		},

		setRowInputsDisabled: function ( $row, disabled ) {
			$row.find( 'input, textarea' ).prop( 'disabled', disabled );
		},

		saveRow: function ( $row ) {
			var postId = $row.data( 'post-id' );
			this.setRowStatus( $row, 'generating' );

			$.post( seoaiBulk.ajaxUrl, {
				action:          'seoai_save',
				nonce:           seoaiBulk.nonce,
				post_id:         postId,
				seo_title:       $row.find( '.seoai-title-input' ).val(),
				seo_description: $row.find( '.seoai-desc-input' ).val(),
				seo_keyword:     $row.find( '.seoai-keyword-input' ).val(),
			} )
			.done( function ( resp ) {
				if ( resp.success ) {
					SEOAIBulk.setRowStatus( $row, 'saved' );
					$row.data( 'saved', true );
				} else {
					var msg = ( resp.data && resp.data.message ) ? resp.data.message : 'Save failed.';
					SEOAIBulk.setRowStatus( $row, 'error', msg );
				}
			} )
			.fail( function () {
				SEOAIBulk.setRowStatus( $row, 'error', 'Request failed.' );
			} );
		},

		skipRow: function ( $row ) {
			this.setRowStatus( $row, 'skipped' );
			$row.find( '.seoai-apply-btn, .seoai-regenerate-btn' ).prop( 'disabled', true );
		},

		applyAll: function () {
			var $rows = $( '#seoai-review-tbody tr' );
			var self  = this;
			var count = 0;

			$rows.each( function () {
				var $row   = $( this );
				var status = $row.find( '.seoai-status' ).attr( 'class' ) || '';
				if ( status.indexOf( 'saved' ) === -1 && status.indexOf( 'skipped' ) === -1 && $row.data( 'generated' ) ) {
					count++;
					self.saveRow( $row );
				}
			} );

			if ( count === 0 ) {
				$( '#seoai-apply-all-status' ).text( 'Nothing to apply.' );
			} else {
				$( '#seoai-apply-all-status' ).text( 'Saving ' + count + ' post(s)...' );
			}
		},

		/* ---------------------------------------------------------------
		   Progress bar
		--------------------------------------------------------------- */
		updateProgress: function ( done ) {
			var pct = this.totalPosts > 0 ? Math.round( ( done / this.totalPosts ) * 100 ) : 0;
			$( '#seoai-progress-fill' ).css( 'width', pct + '%' );
			$( '#seoai-progress-label' ).text( done + ' / ' + this.totalPosts );
		},

		/* ---------------------------------------------------------------
		   Char counter
		--------------------------------------------------------------- */
		updateCharCount: function ( $input, max ) {
			var len    = $input.val().length;
			var $count = $input.siblings( '.seoai-char-count' );
			$count.text( len + ' / ' + max );
			$count.toggleClass( 'over', len > max );
		},

		/* ---------------------------------------------------------------
		   i18n helper
		--------------------------------------------------------------- */
		i18n: function ( key, fallback ) {
			return ( seoaiBulk && seoaiBulk.i18n && seoaiBulk.i18n[ key ] ) ? seoaiBulk.i18n[ key ] : ( fallback || key );
		},
	};

	$( function () {
		SEOAIBulk.init();
	} );

}( jQuery ));
