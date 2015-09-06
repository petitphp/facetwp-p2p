<?php
/*
Plugin Name: FacetWP - Posts 2 Posts
Plugin URI:  https://github.com/petitphp/facetwp-p2p
Description: Add a P2P connexion facet for the plugin FacetWP
Version:     1.2.0-beta
Author:      PetitPHP
Author URI:  https://github.com/petitphp
License:     GPL2+
*/

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

define( 'FWP_P2P_VER', '1.0.0' );
define( 'FWP_P2P_URL', plugin_dir_url( __FILE__ ) );
define( 'FWP_P2P_DIR', plugin_dir_path( __FILE__ ) );

class FWP_P2P {

	function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_css' ) );
	}


	/**
	 * Intialize.
	 */
	function init() {
		add_filter( 'facetwp_facet_types', array( $this, 'register_facet_type' ) );
		add_filter( 'facetwp_indexer_post_facet', array( $this, 'index_p2p_connexion' ), 10, 2 );

		add_action( 'p2p_created_connection', array( $this, 'p2p_created_connection' ) );
		add_action( 'p2p_delete_connections', array( $this, 'p2p_delete_connections' ) );
	}

	/**
	 * Enqueue assets files.
	 */
	function enqueue_css() {
		wp_enqueue_style( 'facetwp-p2p-style', FWP_P2P_URL . 'assets/css/front.css' );
	}

	/**
	 * Index values from a P2P connexion.
	 *
	 * @param bool  $skip_facet
	 * @param array $default
	 *
	 * @return bool if TRUE, FacetWP indexer will skip this facet
	 */
	function index_p2p_connexion( $skip_facet, $default ) {

		//Default indexer params
		$params = $default['defaults'];

		//Current facet's informations
		$facet = $default['facet'];

		//Don't index facets other than P2P
		if ( 'p2p' !== $facet['type'] ) {
			return false;
		}

		//Get current post's post_type
		$post_ptype = get_post_type( $params['post_id'] );

		//Only index P2P connexion if the current post's post_type
		//match the one choose by the user when creating the facet.
		if ( $post_ptype !== $facet['connexion_side'] ) {
			return true;
		}

		//Get the p2p connexion name
		$connexion = substr( $params['facet_source'], 4 );

		//Get all connected posts for this connection
		$connected = get_posts( array(
			'connected_type'   => $connexion,
			'connected_items'  => $params['post_id'],
			'nopaging'         => true,
			'suppress_filters' => false,
		) );

		$hierarchical = false;
		if ( isset( $facet['hierarchical'] ) && 'yes' === $facet['hierarchical'] ) {
			$hierarchical = true;
		}

		$rows = $this->get_rows_to_index( $connected, $params, $hierarchical );
		if ( empty( $rows ) ) {
			return true;
		}

		foreach ( $rows as $row ) {
			FWP()->indexer->insert( $row );
		}

		//Tell FacetWP to skip this facet and move to the next one.
		return true;
	}

	/**
	 * Index a newly created P2P connexion.
	 *
	 * @param int $p2p_id the p2p connexion ID
	 *
	 * @return bool
	 */
	function p2p_created_connection( $p2p_id ) {
		$connexion = p2p_get_connection( $p2p_id );
		$facet     = $this->get_facet_by_p2p_connexion_name( $connexion->p2p_type );
		if ( ! $facet ) {
			return false;
		}

		$from_ptype = get_post_type( $connexion->p2p_from );
		if ( $facet['connexion_side'] === $from_ptype ) {
			FWP()->indexer->index( $connexion->p2p_from );
		} else {
			FWP()->indexer->index( $connexion->p2p_to );
		}

		return true;
	}

	/**
	 * Delete index entry for deleted connexions.
	 *
	 * @param int[] $p2p_ids
	 */
	function p2p_delete_connections( $p2p_ids ) {
		/* @var wpdb $wpdb */
		global $wpdb;

		foreach ( $p2p_ids as $p2p_id ) {
			$connexion = p2p_get_connection( $p2p_id );
			$wpdb->query( $wpdb->prepare(
				"
				DELETE FROM {$wpdb->prefix}facetwp_index
				WHERE post_id IN (%d, %d)
				AND facet_value IN (%d, %d)
				AND facet_source = %s
				",
				$connexion->p2p_from,
				$connexion->p2p_to,
				$connexion->p2p_from,
				$connexion->p2p_to,
				'p2p/' . $connexion->p2p_type
			) );
		}
	}

	/**
	 * Register the "p2p" facet type.
	 *
	 * @param array $facet_types list of registered facets
	 *
	 * @return array
	 */
	function register_facet_type( $facet_types ) {
		include( dirname( __FILE__ ) . '/classes/p2p.php' );
		$facet_types['p2p'] = new FacetWP_Facet_P2P();

		return $facet_types;
	}

	/**
	 *
	 *
	 * @param WP_Post[] $items
	 * @param array     $default_params
	 * @param bool      $is_hierarchical
	 *
	 * @return array
	 */
	protected function get_rows_to_index( $items, $default_params, $is_hierarchical = false ) {
		if ( ! is_array( $items ) ) {
			$items = array( $items );
		}

		$rows = array();

		if ( empty( $items ) ) {
			return $rows;
		}

		//Index each connected posts
		foreach ( $items as $item ) {
			$new_row = wp_parse_args( array(
				'facet_value'         => $item->ID,
				'facet_display_value' => $item->post_title,
			), $default_params );

			if ( $item->post_parent > 0 && is_post_type_hierarchical( $item->post_type ) && $is_hierarchical ) {
				$ancestors_ids = get_post_ancestors( $item );
				if ( ! empty( $ancestors_ids ) ) {
					$ancestors = array_map( 'get_post', $ancestors_ids );
					$ancestors_depth = count( $ancestors );
					$new_row['parent_id'] = $item->post_parent;
					$new_row['depth'] = $ancestors_depth;

					foreach ( $ancestors as $ancestor ) {
						$ancestors_depth--;
						$new_ancestor = wp_parse_args( array(
							'facet_value'         => $ancestor->ID,
							'facet_display_value' => $ancestor->post_title,
						), $default_params );
						if ( 0 >= $ancestor->post_parent ) {
							$new_ancestor['parent_id'] = $ancestor->post_parent;
							$new_ancestor['depth'] = $ancestors_depth;
						}
						$rows[] = $new_ancestor;
					}
				}
			}

			$rows[] = $new_row;
		}

		return $rows;
	}

	/**
	 * Get a facet from a p2p connexion name.
	 *
	 * @param string $connexion the connexion name
	 *
	 * @return array|bool facet's informations or FALSE if no facets found
	 */
	protected function get_facet_by_p2p_connexion_name( $connexion ) {
		$facets = FWP()->helper->get_facets();
		foreach ( $facets as $facet ) {
			if ( 'p2p/' . $connexion === $facet['source'] ) {
				return $facet;
			}
		}

		return false;
	}
}

$fwp_p2p = new FWP_P2P();
