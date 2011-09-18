<?php

class P2P_Connection_Type {

	protected $args;

	public function __construct( $args ) {
		$this->args = $args;
	}

	public function __get( $key ) {
		return $this->args[$key];
	}

	public function get_title( $direction ) {
		$title = $this->args['title'];

		if ( is_array( $title ) ) {
			$key = ( 'to' == $direction ) ? 'to' : 'from';

			if ( isset( $title[ $key ] ) )
				$title = $title[ $key ];
			else
				$title = '';
		}

		return $title;
	}

	public function get_connected( $post_id ) {
		$direction = $this->get_direction_from_id( $post_id );
		if ( !$direction )
			return array();

		$args = array_merge( $this->get_base_args( $direction ), array(
			P2P_Query::get_qv( $direction ) => $post_id,
			'connected_meta' => $this->data,
			'nopaging' => true,
		) );

		if ( $this->sortable && 'to' != $direction ) {
			$args['connected_orderby'] = $this->sortable;
			$args['connected_order'] = 'ASC';
			$args['connected_order_num'] = true;
		}

		$args = apply_filters( 'p2p_connected_args', $args, $this );

		$q = new WP_Query( $args );

		return $q->posts;
	}

	public function get_connectable( $post_id, $page, $search ) {
		$direction = $this->get_direction_from_id( $post_id );
		if ( !$direction )
			return array();

		$args = array_merge( $this->get_base_args( $direction ), array(
			'paged' => $page,
			'posts_per_page' => 5,
		) );

		if ( $search ) {
			add_filter( 'posts_search', array( __CLASS__, '_search_by_title' ), 10, 2 );
			$args['s'] = $search;
		}

		if ( $this->prevent_duplicates )
			$args['post__not_in'] = P2P_Storage::get( $post_id, $direction, $this->data );

		$args = apply_filters( 'p2p_connectable_args', $args, $this );

		$query = new WP_Query( $args );

		remove_filter( 'posts_search', array( __CLASS__, '_search_by_title' ), 10, 2 );

		return (object) array(
			'posts' => $query->posts,
			'current_page' => max( 1, $query->get('paged') ),
			'total_pages' => $query->max_num_pages
		);
	}

	/**
	 * Optimized inner query, after the outer query was executed.
	 *
	 * Populates each of the outer querie's $post objects with a 'connected' property, containing a list of connected posts
	 *
	 * @param object $query WP_Query instance.
	 * @param string|array $search Additional query vars for the inner query.
	 */
	public function each_connected( $query, $search = array() ) {
		if ( empty( $query->posts ) || !is_object( $query->posts[0] ) )
			return;

		$post_type = $query->get( 'post_type' );
		if ( is_array( $post_type ) )
			return;

		$direction = $this->get_direction( $post_type );
		if ( !$direction )
			return;

		$search['post_type'] = $this->get_other_post_type( $direction );

		$prop_name = 'connected';

		$posts = array();

		foreach ( $query->posts as $post ) {
			$post->$prop_name = array();
			$posts[ $post->ID ] = $post;
		}

		// ignore other 'connected' query vars for the inner query
		foreach ( array_keys( P2P_Query::$qv_map ) as $qv )
			unset( $search[ $qv ] );

		$search[ P2P_Query::get_qv( $direction ) ] = array_keys( $posts );

		// ignore pagination
		foreach ( array( 'showposts', 'posts_per_page', 'posts_per_archive_page' ) as $disabled_qv ) {
			if ( isset( $search[ $disabled_qv ] ) ) {
				trigger_error( "Can't use '$disabled_qv' in an inner query", E_USER_WARNING );
			}
		}
		$search['nopaging'] = true;

		$search['ignore_sticky_posts'] = true;

		$q = new WP_Query( $search );

		foreach ( $q->posts as $inner_post ) {
			if ( $inner_post->ID == $inner_post->p2p_from )
				$outer_post_id = $inner_post->p2p_to;
			elseif ( $inner_post->ID == $inner_post->p2p_to )
				$outer_post_id = $inner_post->p2p_from;
			else
				throw new Exception( 'Corrupted data.' );

			if ( $outer_post_id == $inner_post->ID )
				throw new Exception( 'Post connected to itself.' );

			array_push( $posts[ $outer_post_id ]->$prop_name, $inner_post );
		}
	}

	private function get_base_args( $direction ) {
		return array(
			'post_type' => $this->get_other_post_type( $direction ),
			'post_status' => 'any',
			'suppress_filters' => false,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false
		);
	}

	public function get_direction( $post_type ) {
		if ( $post_type == $this->to && $this->from == $post_type )
			return 'any';

		if ( $this->to == $post_type )
			return 'to';

		if ( $this->from == $post_type )
			return 'from';

		return false;
	}

	public function get_other_post_type( $direction ) {
		return 'from' == $direction ? $this->to : $this->from;
	}

	public function connect( $from, $to ) {
		$post_from = get_post( $from );
		$post_to = get_post( $to );

		if ( !$post_from || !$post_to ) {
			return false;
		}

		$args = array( $from, $to );

		if ( $post_from->post_type == $this->to )
			$args = array_reverse( $args );

		$p2p_id = false;

		if ( $this->prevent_duplicates ) {
			$p2p_ids = P2P_Storage::get( $args[0], $args[1], $this->data );

			if ( !empty( $p2p_ids ) )
				$p2p_id = $p2p_ids[0];
		}

		if ( !$p2p_id ) {
			$p2p_id = P2P_Storage::connect( $args[0], $args[1], $this->data );
		}

		return $p2p_id;
	}

	public function disconnect( $post_id ) {
		p2p_disconnect( $post_id, $this->get_direction_from_id( $post_id ), $this->data );
	}

	private function get_direction_from_id( $post_id ) {
		$post = get_post( $post_id );
		if ( !$post )
			return false;

		$direction = $this->get_direction( $post->post_type );

		if ( !$direction ) {
			trigger_error( sprintf( "Invalid post type. Expected '%s' or '%s', but received '%s'.",
				$this->args['from'],
				$this->args['to'],
				$post->post_type
			), E_USER_WARNING );
		}

		return $direction;
	}

	function _search_by_title( $sql, $wp_query ) {
		if ( $wp_query->is_search ) {
			list( $sql ) = explode( ' OR ', $sql, 2 );
			return $sql . '))';
		}

		return $sql;
	}
}
