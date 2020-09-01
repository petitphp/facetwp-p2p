<?php
/*
Plugin Name: FacetWP - Posts 2 Posts
Plugin URI:  https://github.com/petitphp/facetwp-p2p
Description: Add a P2P connexion facet for the plugin FacetWP
Version:     2.1.2
Author:      Clément Boirie
Author URI:  https://github.com/petitphp
License:     GPL2+
*/

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

define( 'FWP_P2P_VER', '2.1.2' );
define( 'FWP_P2P_URL', plugin_dir_url( __FILE__ ) );
define( 'FWP_P2P_DIR', plugin_dir_path( __FILE__ ) );

class FWP_P2P {

	/**
	 * @var FWP_P2P
	 */
	private static $instance;

	/**
	 * FWP_P2P constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * @return FWP_P2P
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Register all hooks
	 */
	public function init() {
		add_filter( 'facetwp_facet_sources', array( $this, 'p2p_sources' ) );
		add_filter( 'facetwp_facet_sources', array( $this, 'p2pmetas_sources' ) );

		add_filter( 'facetwp_indexer_post_facet', array( $this, 'p2p_indexer' ), 10, 2 );
		add_filter( 'facetwp_indexer_post_facet', array( $this, 'p2pmetas_indexer' ), 10, 2 );

		add_action( 'p2p_created_connection', array( $this, 'p2p_created_connection' ) );

		add_action( 'p2p_delete_connections', array( $this, 'p2p_delete_connections' ) );
		add_action( 'p2p_delete_connections', array( $this, 'p2pmeta_delete_connections' ) );
	}

	/**
	 * Add P2P sources.
	 *
	 * @param array $sources
	 *
	 * @return array
	 */
	public function p2p_sources( $sources = array() ) {
		$options    = array();
		$connexions = P2P_Connection_Type_Factory::get_all_instances();
		foreach ( $connexions as $connexion ) {
			$from_ptype = $this->parse_side( $connexion->side['from'] );
			$to_ptype = $this->parse_side( $connexion->side['to'] );
			$options[ sprintf( 'p2p/%s/%s', $connexion->name, $from_ptype->name ) ] = sprintf( "[%s → %s] %s", $from_ptype->singular_name, $to_ptype->singular_name, $from_ptype->singular_name );
			$options[ sprintf( 'p2p/%s/%s', $connexion->name, $to_ptype->name ) ] = sprintf( "[%s → %s] %s", $from_ptype->singular_name, $to_ptype->singular_name, $to_ptype->singular_name );
		}

		// don't add source if no options available
		if ( empty( $options ) ) {
			return $sources;
		}

		$sources['p2p'] = array(
			'label'   => __( 'Posts 2 Posts', 'facetwp-p2p' ),
			'choices' => $options,
			'weight'  => 40,
		);

		return $sources;
	}

	/**
	 * Add P2P metas sources
	 *
	 * @param array $sources
	 *
	 * @return array
	 */
	public function p2pmetas_sources( $sources = array() ) {
		$options    = array();
		$connexions = P2P_Connection_Type_Factory::get_all_instances();
		foreach ( $connexions as $connexion ) {
			if ( empty( $connexion->fields ) ) {
				continue;
			}

			$from_ptype = $this->parse_side( $connexion->side['from'] );
			$to_ptype   = $this->parse_side( $connexion->side['to'] );
			foreach ( $connexion->fields as $field_name => $field_options ) {
				$field_title = ! empty( $field_options['title'] ) ? $field_options['title'] : $field_name;
				$options[ sprintf( 'p2pmeta/%s/%s', $connexion->name, $field_name ) ] = sprintf( "[%s → %s] %s", $from_ptype->singular_name, $to_ptype->singular_name, $field_title );
			}
		}

		// don't add source if no options available
		if ( empty( $options ) ) {
			return $sources;
		}

		$sources['p2p_meta'] = array(
			'label'   => __( 'Posts 2 Posts Meta', 'facetwp-p2p' ),
			'choices' => $options,
			'weight'  => 50,
		);

		return $sources;
	}

	/**
	 * Index values from a P2P connexion.
	 *
	 * @param bool $bypass
	 * @param array $defaults
	 *
	 * @return bool
	 */
	public function p2p_indexer( $bypass, $defaults ) {
		$params = $defaults['defaults'];

		$source = explode( '/', $params['facet_source'] );
		if ( count( $source ) !== 3 || 'p2p' !== $source[0] ) {
			return $bypass;
		}

		$connexion      = $source[1];
		$connexion_side = $source[2];
		$post_ptype     = get_post_type( (int) $params['post_id'] );

		if ( $post_ptype !== $connexion_side ) {
			return true;
		}

		$connected = get_posts( array(
			'connected_type'   => $connexion,
			'connected_items'  => (int) $params['post_id'],
			'nopaging'         => true,
			'suppress_filters' => false
		) );

		//Index each connected posts
		foreach ( $connected as $p ) {
			$new_params = wp_parse_args( array(
				'facet_value'         => $p->ID,
				'facet_display_value' => $p->post_title,
			), $params );

			/**
			 * Filters values send to the indexer.
			 *
			 * @since 2.1.0
			 *
			 * @param array $new_params Associative array of parameters.
			 * @param string $connexion P2P connexion's name.
			 */
			$new_params = apply_filters( 'facetp2p_p2p_index_params', $new_params, $connexion );
			FWP()->indexer->index_row( $new_params );
		}

		return true;
	}

	/**
	 * Index values from a P2P connexion metas.
	 *
	 * @param bool $bypass
	 * @param array $defaults
	 *
	 * @return bool
	 */
	public function p2pmetas_indexer( $bypass, $defaults ) {
		/* @var wpdb $wpdb */
		global $wpdb;

		$params = $defaults['defaults'];

		$source = explode( '/', $params['facet_source'] );
		if ( count( $source ) !== 3 || 'p2pmeta' !== $source[0] ) {
			return $bypass;
		}

		$connexion  = $source[1];
		$field_name = $source[2];
		$post_id    = (int) $params['post_id'];
		$post_ptype = get_post_type( $post_id );

		$connexion_type = P2P_Connection_Type_Factory::get_instance( $connexion );
		if ( empty( $connexion_type->fields ) && ! isset( $connexion_type->fields[ $field_name ] ) ) {
			return true;
		}

		$p2p_column = $this->get_post_type( $connexion_type->side['from'] ) === $post_ptype ? 'p2p_from' : 'p2p_to';

		$p2p_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p2p_id FROM $wpdb->p2p WHERE p2p_type = %s AND $p2p_column = %d",
				$connexion, $post_id
			)
		);

		foreach ( $p2p_ids as $p2p_id ) {

			$meta_value = p2p_get_meta( $p2p_id, $field_name );
			if ( ! $meta_value && isset( $connexion_type->fields[ $field_name ]['default'] ) ) {
				$meta_value = array( $connexion_type->fields[ $field_name ]['default'] );
			}

			foreach ( $meta_value as $value ) {
				$new_params = wp_parse_args( array(
					'facet_value'         => FWP()->helper->safe_value( $value ),
					'facet_display_value' => $value,
					'parent_id'           => $p2p_id,
				), $params );

				/**
				 * Filters values send to the indexer.
				 *
				 * @since 2.1.0
				 *
				 * @param array $new_params Associative array of parameters.
				 * @param int $p2p_id P2P connexion's id.
				 * @param string $field_name P2P connexion's meta name.
				 * @param P2P_Connection_Type $connexion_type P2P connexion's object.
				 * @param string $p2p_column Current P2P connexion side.
				 */
				$new_params = apply_filters( 'facetp2p_p2pmeta_index_params', $new_params, (int) $p2p_id, $field_name, $connexion_type, $p2p_column );
				FWP()->indexer->index_row( $new_params );
			}
		}

		return true;
	}

	/**
	 * Index a newly created P2P connexion.
	 *
	 * @param int $p2p_id
	 *
	 * @return bool
	 */
	public function p2p_created_connection( $p2p_id ) {
		$connexion = p2p_get_connection( $p2p_id );
		$sources   = $this->get_facet_source_for_p2p_connection( $connexion );
		if ( is_wp_error( $sources ) ) {
			return false;
		}

		foreach ( FWP()->helper->get_facets() as $facet ) {
			if ( $sources['from'] === $facet['source'] ) {
				FWP()->indexer->index( $connexion->p2p_from );
			}

			if ( $sources['to'] === $facet['source'] ) {
				FWP()->indexer->index( $connexion->p2p_to );
			}
		}

		return true;
	}

	/**
	 * Delete index entries for the deleted connexions.
	 *
	 * @param $p2p_ids
	 */
	public function p2p_delete_connections( $p2p_ids ) {
		/* @var wpdb $wpdb */
		global $wpdb;

		foreach ( $p2p_ids as $p2p_id ) {

			$connexion = p2p_get_connection( $p2p_id );
			$sources   = $this->get_facet_source_for_p2p_connection( $connexion );
			if ( is_wp_error( $sources ) ) {
				continue;
			}

			foreach ( $sources as $source ) {
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
					$source
				) );
			}
		}
	}

	public function p2pmeta_delete_connections( $p2p_ids ) {
		/* @var wpdb $wpdb */
		global $wpdb;

		foreach ( $p2p_ids as $p2p_id ) {

			$connexion      = p2p_get_connection( $p2p_id );
			$connexion_type = P2P_Connection_Type_Factory::get_instance( $connexion->p2p_type );
			if ( ! $connexion_type || empty( $connexion_type->fields ) ) {
				continue;
			}

			foreach ( $connexion_type->fields as $field_name => $field_options ) {

				$source = sprintf( 'p2pmeta/%s/%s', $connexion->p2p_type, $field_name );

				$wpdb->query( $wpdb->prepare(
					"
				DELETE FROM {$wpdb->prefix}facetwp_index
				WHERE post_id IN (%d, %d)
				AND facet_source = %s
				AND parent_id = %d
				",
					$connexion->p2p_from,
					$connexion->p2p_to,
					$source,
					$p2p_id
				) );
			}
		}
	}

	/**
	 * @param int|object $p2p_id
	 * @param bool $direction
	 *
	 * @return array|string|WP_Error
	 */
	protected function get_facet_source_for_p2p_connection( $p2p_id, $direction = false ) {
		if ( false !== $direction && ! in_array( $direction, array( 'from', 'to' ) ) ) {
			return new WP_Error(
				'facetwp_p2p_invalid_p2p_direction',
				sprintf( 'The direction %s is invalid. Allowed directions are "from" and "to".', $direction )
			);
		}

		$connexion      = ( isset( $p2p_id->p2p_type ) ) ? $p2p_id : p2p_get_connection( $p2p_id );
		$connexion_type = P2P_Connection_Type_Factory::get_instance( $connexion->p2p_type );
		if ( ! $connexion_type ) {
			return new WP_Error(
				'facetwp_p2p_invalid_connexion',
				sprintf( 'The connexion %s does not exist', $connexion->p2p_type )
			);
		}

		if ( false !== $direction ) {
			return sprintf(
				'p2p/%s/%s',
				$connexion->p2p_type,
				$this->get_post_type( $connexion_type->side[ $direction ] )
			);
		}

		return array(
			'from' => sprintf(
				'p2p/%s/%s',
				$connexion->p2p_type,
				$this->get_post_type( $connexion_type->side['from'] )
			),
			'to'   => sprintf(
				'p2p/%s/%s',
				$connexion->p2p_type,
				$this->get_post_type( $connexion_type->side['to'] )
			)
		);
	}

	/**
	 * Get post type for a P2P_Side.
	 *
	 * Handle special case for P2P_Side_User.
	 *
	 * @param P2P_Side $side
	 *
	 * @return string
	 */
	protected function get_post_type( $side ) {
		return ( is_a( $side, 'P2P_Side_User' ) ) ? 'user' : $side->first_post_type();
	}

	/**
	 * Prepare data for a P2P_Side.
	 *
	 * @param P2P_Side $side
	 *
	 * @return object
	 */
	protected function parse_side( $side ) {
		$data = [];

		$type = $this->get_post_type( $side );

		if ( 'user' === $type ) {
			$data['name'] = 'user';
			$data['singular_name'] = 'User';
		} else {
			$ptype = get_post_type_object( $type );
			$data['name'] = $ptype->name;
			$data['singular_name'] = $ptype->labels->singular_name;
		}

		return (object) $data;
	}

	/**
	 * Get available P2P connexion.
	 *
	 * @return \P2P_Connection_Type[]
	 */
	protected function get_connexions() {

		$connexions = P2P_Connection_Type_Factory::get_all_instances();

		/**
		 * Filters available P2P connexions.
		 *
		 * @param \P2P_Connection_Type[] $connexions List of P2P connexion.
		 *
		 * @since 3.0.0
		 *
		 */
		return apply_filters( 'facetp2p_p2p_connexions', $connexions );
	}
}

/**
 * FWP P2P accessor.
 *
 * @return FWP_P2P
 */
function FWP_P2P() {
	return FWP_P2P::instance();
}

/**
 * Print warning notice if requirements are not met.
 */
function FWP_P2P_notice() {
	$message = __( 'FWP P2P requires FacetWP 2.0.4 or above to work.', 'facetwp-p2p' );
	if ( ! defined( 'FACETWP_VERSION' ) ) {
		$message .= ' ';
		$message .= __( 'FacetWP doesn\'t seem to be install on your site.', 'facetwp-p2p' );
	} else {
		$message .= ' ';
		$message .= sprintf(
			__( 'You currently have FacetWP %s.', 'facetwp-p2p' ),
			FACETWP_VERSION
		);
	}
	echo '<div class="error"><p>' . $message . '</p></div>';
}

/**
 * Init FWP P2P.
 */
function FWP_P2P_init() {
	// Check
	if (
		! defined( 'FACETWP_VERSION' )
		|| version_compare( FACETWP_VERSION, '2.0.4', '<' )
	) {
		add_action( 'admin_notices', 'FWP_P2P_notice' );

		return;
	}

	FWP_P2P();
}

add_action( 'plugins_loaded', 'FWP_P2P_init' );
