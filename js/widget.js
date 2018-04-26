(function($){
	let refreshTime = $( '.mpc-list' ).data( 'refresh' ),
		endpointUrl = $( 'link[rel="https://api.w.org/"]' ).attr( 'href' ) +
			$( '.mpc-list' ).data('endpoint' ),
		doAjax = function( endpointUrl ) {
			$.ajax( endpointUrl )
				.done( function( data ) {
					console.log( data );
					$.each( data, function( blogId, blogInfo ) {
						$('.mpc-list-item[data-blog_id=' + blogId + '] .mpc-list-item-link')
							.attr('href', blogInfo['url'] )
							.text( blogInfo['name'] );
						$('.mpc-list-item[data-blog_id=' + blogId + '] .mpc-list-item-posts')
							.text( blogInfo['post_count'] );
						$('.mpc-list-item[data-blog_id=' + blogId + '] .mpc-list-item-users')
							.text( blogInfo['user_count'] );
					} )
				} )
		},
		foo = window.setInterval(
			doAjax( endpointUrl ),
			refreshTime * 1000
		);
})(jQuery);
