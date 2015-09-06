<?php

class FacetWP_Facet_P2P {

	function __construct() {
		$this->label = __( 'Posts to Posts', 'fwp' );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 50 );

		add_filter( 'facetwp_store_unfiltered_post_ids', array( $this, 'store_unfiltered_post_ids' ) );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @param string $hook current page's hook
	 *
	 * @return bool
	 */
	function enqueue_scripts( $hook ) {
		if ( 'settings_page_facetwp' !== $hook ) {
			return false;
		}

		//Register underscore
		wp_enqueue_script( 'underscore' );

		//Print object with P2P connexions details
		$connexions     = P2P_Connection_Type_Factory::get_all_instances();
		$connexions_obj = array();

		foreach ( $connexions as $connexion ) {
			foreach ( array( 'from', 'to' ) as $side ) {
				$ptype_slug                                  = $connexion->side[ $side ]->first_post_type();
				$ptype                                       = get_post_type_object( $ptype_slug );
				$connexions_obj[ $connexion->name ][ $side ] = array(
					'slug' => $ptype_slug,
					'name' => $ptype->labels->singular_name,
				);
			}
		}

		return wp_localize_script( 'jquery', 'p2pConnexionsData', $connexions_obj );
	}

	/**
	 * Load the available choices
	 */
	function load_values( $params ) {
		/* @var wpdb $wpdb */
		global $wpdb;

		$facet        = $params['facet'];
		$from_clause  = $wpdb->prefix . 'facetwp_index f';

		// Where
		$where_clause = $params['where_clause'];
		$where_clause = apply_filters( 'facetwp_facet_where', $where_clause, $facet );

		// Orderby
		$orderby      = 'counter DESC, f.facet_display_value ASC';
		$orderby      = apply_filters( 'facetwp_facet_orderby', $orderby, $facet );

		// Limit
		$limit = 10;

		$sql = "
        SELECT f.facet_value, f.facet_display_value, f.parent_id, f.depth, COUNT(*) AS counter
        FROM {$from_clause}
        WHERE f.facet_name = '{$facet['name']}' $where_clause
        GROUP BY f.facet_value
        ORDER BY $orderby
        LIMIT $limit";

		$output = $wpdb->get_results( $sql, ARRAY_A );

		// Show "ghost" facet choices (those that return zero results)
		if ( 'yes' === $facet['ghosts'] && ! empty( FWP()->unfiltered_post_ids ) ) {
			$raw_post_ids = implode( ',', FWP()->unfiltered_post_ids );

			$sql = "
            SELECT f.facet_value, f.facet_display_value, f.parent_id, f.depth, 0 AS counter
            FROM {$from_clause}
            WHERE f.facet_name = '{$facet['name']}' AND post_id IN ($raw_post_ids)
            GROUP BY f.facet_value
            ORDER BY $orderby
            LIMIT $limit";

			$ghost_output = $wpdb->get_results( $sql, ARRAY_A );

			// Keep the facet placement intact
			if ( isset( $facet['preserve_ghosts'] ) && 'yes' === $facet['preserve_ghosts'] ) {
				$tmp = array();
				foreach ( $ghost_output as $row ) {
					$tmp[ $row['facet_value'] . ' ' ] = $row;
				}

				foreach ( $output as $row ) {
					$tmp[ $row['facet_value'] . ' ' ] = $row;
				}

				$output = $tmp;
			} else {
				// Make the array key equal to the facet_value (for easy lookup)
				$tmp = array();
				foreach ( $output as $row ) {
					$tmp[ $row['facet_value'] . ' ' ] = $row; // Force a string array key
				}
				$output = $tmp;

				foreach ( $ghost_output as $row ) {
					$facet_value = $row['facet_value'];
					if ( ! isset( $output[ "$facet_value " ] ) ) {
						$output[ "$facet_value " ] = $row;
					}
				}
			}

			$output = array_splice( $output, 0, $limit );
			$output = array_values( $output );
		}

		return $output;
	}

	/**
	 * Generate the facet HTML
	 *
	 * @params array $params
	 *
	 * @return string
	 */
	function render( $params ) {

		$facet = $params['facet'];

		if ( isset( $facet['hierarchical'] ) && 'yes' === $facet['hierarchical'] ) {
			return $this->render_hierarchy( $params );
		}

		$output = '';
		$values = (array) $params['values'];
		$selected_values = (array) $params['selected_values'];

		foreach ( $values as $result ) {
			$selected = in_array( $result['facet_value'], $selected_values ) ? ' checked' : '';
			$selected .= ( 0 === absint( $result['counter'] ) ) ? ' disabled' : '';
			$output .= '<div class="facetwp-p2p' . $selected . '" data-value="' . $result['facet_value'] . '">';
			$output .= $result['facet_display_value'] . ' <span class="facetwp-counter">(' . $result['counter'] . ')</span>';
			$output .= '</div>';
		}

		return $output;
	}

	/**
	 * Generate the facet HTML (hierarchical)
	 */
	function render_hierarchy( $params ) {

		$output = '';
		$facet = $params['facet'];
		$selected_values = (array) $params['selected_values'];

		//@TODO: maybe should need to do the same for the posts
		//$values = FWP()->helper->sort_taxonomy_values( $params['values'], $facet['orderby'] );
		$values = $params['values'];

		$last_depth = 0;
		foreach ( $values as $result ) {
			$depth = (int) $result['depth'];

			if ( $depth > $last_depth ) {
				$output .= '<div class="facetwp-depth">';
			}
			elseif ( $depth < $last_depth ) {
				for ( $i = $last_depth; $i > $depth; $i-- ) {
					$output .= '</div>';
				}
			}

			$selected = in_array( $result['facet_value'], $selected_values ) ? ' checked' : '';
			$selected .= ( 0 === absint( $result['counter'] ) ) ? ' disabled' : '';
			$output .= '<div class="facetwp-p2p' . $selected . '" data-value="' . $result['facet_value'] . '">';
			$output .= $result['facet_display_value'] . ' <span class="facetwp-counter">(' . $result['counter'] . ')</span>';
			$output .= '</div>';

			$last_depth = $depth;
		}

		for ( $i = $last_depth; $i > 0; $i-- ) {
			$output .= '</div>';
		}

		return $output;
	}

	/**
	 * Filter the query based on selected values
	 *
	 * @params array $params
	 *
	 * @return array
	 */
	function filter_posts( $params ) {
		global $wpdb;

		$output          = array();
		$facet           = $params['facet'];
		$selected_values = $params['selected_values'];

		$sql = $wpdb->prepare(
			"
			SELECT DISTINCT post_id
            FROM {$wpdb->prefix}facetwp_index
            WHERE facet_name = %s
            ",
			$facet['name']
		);


		$selected_values = implode( "','", $selected_values );
		$output          = $wpdb->get_col( $sql . " AND facet_value IN ('$selected_values')" );

		return $output;
	}

	/**
	 * Output admin settings HTML
	 */
	function settings_html() {
		$connexions = P2P_Connection_Type_Factory::get_all_instances();
		$options    = array();
		foreach ( $connexions as $connexion ) {
			$from_ptype                  = get_post_type_object( $connexion->side['from']->first_post_type() );
			$to_ptype                    = get_post_type_object( $connexion->side['to']->first_post_type() );
			$options[ $connexion->name ] = sprintf( '%s to %s', $from_ptype->labels->singular_name, $to_ptype->labels->singular_name );
		}
		?>
		<tr class="facetwp-conditional type-p2p">
			<td>
				<?php _e( 'Data source', 'fwp' ); ?>:
				<div class="facetwp-tooltip">
					<span class="icon-question">?</span>

					<div
						class="facetwp-tooltip-content"><?php _e( 'The data used to populate this facet', 'fwp' ); ?></div>
				</div>
			</td>
			<td>
				<select class="facet-source-p2p">
					<optgroup label="Connexions">
						<?php foreach ( $options as $connexion_slug => $connexion_name ) : ?>
							<option
								value="p2p/<?php echo esc_attr( $connexion_slug ); ?>"><?php echo $connexion_name; ?></option>
						<?php endforeach; ?>
					</optgroup>
				</select>
			</td>
		</tr>
		<tr class="facetwp-conditional type-p2p">
			<td>
				<?php _e( 'Choose connexion side', 'fwp' ); ?>:
				<div class="facetwp-tooltip">
					<span class="icon-question">?</span>

					<div class="facetwp-tooltip-content">
						Choose which side of the connexion
						should be indexed by FacetWP.
					</div>
				</div>
			</td>
			<td>
				<select class="facet-connexion-side">
					<option><?php _e( 'Select a P2P connexion' ); ?></option>
				</select>
			</td>
		</tr>
		<tr class="facetwp-conditional type-p2p">
			<td>
				<?php _e( 'Hierarchical', 'fwp' ); ?>:
				<div class="facetwp-tooltip">
					<span class="icon-question">?</span>

					<div
						class="facetwp-tooltip-content"><?php _e( 'Display as hierarchical in front. Will only work with hierarchical post type.', 'fwp' ); ?></div>
				</div>
			</td>
			<td>
				<select class="facet-hierarchical">
					<option value="no"><?php _e( 'No', 'fwp' ); ?></option>
					<option value="yes"><?php _e( 'Yes', 'fwp' ); ?></option>
				</select>
			</td>
		</tr>
		<tr class="facetwp-conditional type-p2p">
			<td>
				<?php _e( 'Show ghosts', 'fwp' ); ?>:
				<div class="facetwp-tooltip">
					<span class="icon-question">?</span>

					<div
						class="facetwp-tooltip-content"><?php _e( 'Show choices that would return zero results?', 'fwp' ); ?></div>
				</div>
			</td>
			<td>
				<select class="facet-ghosts">
					<option value="no"><?php _e( 'No', 'fwp' ); ?></option>
					<option value="yes"><?php _e( 'Yes', 'fwp' ); ?></option>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Output any admin scripts
	 */
	function admin_scripts() {
		?>
		<script>
			(function ($) {
				//execute when FacetWP load the settings
				wp.hooks.addAction('facetwp/load/p2p', function ($this, obj) {
					$this.find('.type-p2p .facet-source-p2p').val(obj.source);
					$this.find('.type-p2p .facet-hierarchical').val(obj.hierarchical);
					$this.find('.type-p2p .facet-ghosts').val(obj.ghosts);

					//if we have a P2P connexion
					if (!_.isUndefined(obj.source) && !_.isUndefined(obj.connexion_side)) {
						//get the select
						var $connexion_side = $this.find('.type-p2p .facet-connexion-side');

						//populate its values
						_update_connexion_side(obj.source.substr(4), $connexion_side);

						//select the correct option
						$connexion_side.val(obj.connexion_side);
					}
				});

				//execute when FacetWP save the settings
				wp.hooks.addFilter('facetwp/save/p2p', function ($this, obj) {
					obj['source'] = $this.find('.type-p2p .facet-source-p2p').val();
					obj['connexion_side'] = $this.find('.type-p2p .facet-connexion-side').val();
					obj['hierarchical'] = $this.find('.type-p2p .facet-hierarchical').val();
					obj['ghosts'] = $this.find('.type-p2p .facet-ghosts').val();
					return obj;
				});

				//execute when a user select the type P2P for a facet
				wp.hooks.addAction('facetwp/change/p2p', function ($this) {
					var $facet = $this.closest('.facetwp-row');
					if (0 >= $facet.length) {
						$facet = $this.closest('.facetwp-facet');
					}

					//hide the default source
					$facet.find('.name-source').hide();

					//get the first connexion
					var connexion = $facet.find('.facet-source-p2p').val().substr(4),
						$connection_side = $facet.find('.type-p2p .facet-connexion-side');

					_update_connexion_side(connexion, $connection_side);
				});

				//execute when a user choose another P2P connexion
				$(document).on('change', '.facet-source-p2p', function () {
					var connexion = $(this).val().substr(4);

					if (!_.isEmpty(connexion)) {
						_update_connexion_side(connexion);
					}
				});

				/**
				 * Update input content with the relevant choices for selected connexion.
				 *
				 * @param {string} connexion the connexion's name
				 * @param {Object} $facet    the input wrap in a jQuery object
				 *
				 * @returns {Boolean} TRUE on success, FALSE otherwise
				 * @private
				 */
				function _update_connexion_side(connexion, $facet) {
					var sides = _load_connexion_sides(connexion),
						$connexion_side = $facet || $('.facet-connexion-side:visible');

					if (_.isEmpty(sides) || false === sides) {
						return false;
					}

					//clear the input content
					$connexion_side.empty();

					//populate the input with the new options
					$.each(sides, function (side, data) {
						jQuery('<option />', {
							val: data.slug,
							text: data.name
						}).appendTo($connexion_side);
					});

					return true;
				}

				/**
				 * Try to get the connexion's datas from the p2pConnexionData object.
				 *
				 * @param {string} connexion the connexion's name
				 *
				 * @returns {Object|Boolean} the connexion's datas or FALSE
				 * @private
				 */
				function _load_connexion_sides(connexion) {
					if (_.isEmpty(connexion)) {
						return false;
					}

					if (!_.isUndefined(p2pConnexionsData[connexion])) {
						return p2pConnexionsData[connexion];
					}

					return false;
				}
			})(jQuery);
		</script>
		<?php
	}

	/**
	 * Output any front-end scripts
	 */
	function front_scripts() {
		?>
		<script>
			(function ($) {
				wp.hooks.addAction('facetwp/refresh/p2p', function ($this, facet_name) {
					var selected_values = [];
					$this.find('.facetwp-p2p.checked').each(function () {
						selected_values.push($(this).attr('data-value'));
					});
					FWP.facets[facet_name] = selected_values;
				});

				wp.hooks.addFilter('facetwp/selections/p2p', function(output, params) {
					var labels = [];
					$.each(params.selected_values, function(idx, val) {
						var label = params.el.find('.facetwp-p2p[data-value="' + val + '"]').clone();
						label.find('.facetwp-counter').remove();
						labels.push(label.text());
					});
					return labels.join(' / ');
				});

				wp.hooks.addAction('facetwp/ready', function () {
					$(document).on('click', '.facetwp-facet .facetwp-p2p:not(.disabled)', function () {
						$(this).toggleClass('checked');
						var $facet = $(this).closest('.facetwp-facet');
						FWP.autoload();
					});
				});
			})(jQuery);
		</script>
		<?php
	}

	/**
	 * Store unfiltered post IDs if a p2p facet exists with ghosts mode enabled
	 *
	 * @return bool
	 */
	function store_unfiltered_post_ids( $boolean ) {
		if ( FWP()->helper->facet_setting_exists( 'ghosts', 'yes' ) ) {
			return true;
		}

		return $boolean;
	}
}