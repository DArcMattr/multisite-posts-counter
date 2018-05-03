( function( $ ) {
	var refreshTime = $( '.mpc-list' ).data( 'refresh' ),
		poll = window.setInterval(
			function() {
				var endpointUrl = $( 'link[rel="https://api.w.org/"]' ).attr( 'href' ) +
					$( '.mpc-list' ).data( 'endpoint' );

				$.ajax( endpointUrl )
					.done( function( data ) {
						$.each( data, function( blogId, blogInfo ) {
							$( '.mpc-list-item[data-blog_id=' + blogId + '] .mpc-list-item-link' )
								.attr( 'href', blogInfo.url )
								.text( blogInfo.name );
							$( '.mpc-list-item[data-blog_id=' + blogId + '] .mpc-list-item-posts' )
								.text(  blogInfo.post_count );
							$( '.mpc-list-item[data-blog_id=' + blogId + '] .mpc-list-item-users' )
								.text( blogInfo.user_count );
						});
					});
				},
			refreshTime * 1000
		);
}( jQuery ) );

