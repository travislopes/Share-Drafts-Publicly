function make_draft_public( post_id ) {

	var $ = jQuery;

	/* Show activity spinner. */
	$( '#share_drafts_publicly .spinner' ).css( 'visibility', 'visible' );

	/* Process AJAX action. */
	$.ajax( ajaxurl, {
		'data':     {
			'action': 'share_drafts_publicly',
			'make':   'public',
			'post_id': post_id
		},
		'dataType': 'json',
		'success':  function ( response ) {
			
			if ( response.success ) {
				
				/* Change link box value to new permalink. */
				$( '#share_drafts_publicly #sdp_link' ).val( response.data.permalink );
				
				/* Hide "Make Draft Public" button. */
				$( '#share_drafts_publicly #sdp_make_public' ).hide();
				
				/* Show "Make Draft Private" button and link box. */
				$( '#share_drafts_publicly #sdp_link, #share_drafts_publicly #sdp_make_private' ).show();
								
			} else {
				
				/* Send error alert. */
				alert( response.data.message );
				
			}
			
			/* Hide spinner. */
			$( '#share_drafts_publicly .spinner' ).css( 'visibility', 'hidden' );

			
		}
		
	} );

}

function make_draft_private( post_id ) {
	
	var $ = jQuery;

	/* Show activity spinner. */
	$( '#share_drafts_publicly .spinner' ).css( 'visibility', 'visible' );

	/* Process AJAX action. */
	$.ajax( ajaxurl, {
		'data':     {
			'action': 'share_drafts_publicly',
			'make':   'private',
			'post_id': post_id
		},
		'dataType': 'json',
		'success':  function ( response ) {
			
			if ( response.success ) {
				
				/* Show "Make Draft Public" button. */
				$( '#share_drafts_publicly #sdp_make_public' ).show();
				
				/* Hide "Make Draft Private" button and link box. */
				$( '#share_drafts_publicly #sdp_link, #share_drafts_publicly #sdp_make_private' ).hide();
								
			} else {
				
				/* Send error alert. */
				alert( response.data.message );
				
			}
			
			/* Hide spinner. */
			$( '#share_drafts_publicly .spinner' ).css( 'visibility', 'hidden' );

			
		}
		
	} );
	
}