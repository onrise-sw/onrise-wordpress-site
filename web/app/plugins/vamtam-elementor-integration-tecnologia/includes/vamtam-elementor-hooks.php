<?php
namespace VamtamElementor\ElementorHooks;

// Elementor actions.
add_action( 'elementor/editor/before_enqueue_scripts',   __NAMESPACE__ . '\enqueue_editor_scripts' );
add_action( 'elementor/frontend/before_enqueue_scripts', __NAMESPACE__ . '\frontend_before_enqueue_scripts' );
add_action( 'elementor/init', __NAMESPACE__ . '\elementor_init' );


// TODO: To be removed when https://github.com/elementor/elementor/issues/9907 is fixed by Elementor.
add_action( 'elementor/frontend/after_enqueue_styles', __NAMESPACE__ . '\force_enqueue_fa4_icons', -10 );

// Elementor filters
add_filter( 'elementor/controls/animations/additional_animations', __NAMESPACE__ . '\vamtam_elementor_additional_animations' );

function elementor_init() {
	// Theme-dependant.
	set_experiments_default_state();
}

/*
	Sets all Stable features to their default value & disables all Ongoing experiments by default.
	Happens only once (based on option).
*/
function set_experiments_default_state() {
	if ( get_option( 'vamtam-set-experiments-default-state', false ) ) {
		return;
	}

	$exps     = \Elementor\Plugin::$instance->experiments;
	$features = $exps->get_features();

	foreach ( $features as $fname => $fdata ) {
		if ( $fdata['release_status'] === 'stable' ) {
			// Stable experiments.

			// Additional Custom Breakpoints
			if ( $fname === 'additional_custom_breakpoints' ) {
				// Force-disable.
				update_option( 'elementor_experiment-' . $fname, $exps::STATE_INACTIVE );
				continue;
			}

			// Force default state.
			update_option( 'elementor_experiment-' . $fname, $exps::STATE_DEFAULT );

		} else {
			// Ongoing experiments.

			// Force-disable.
			update_option( 'elementor_experiment-' . $fname, $exps::STATE_INACTIVE );

			// Set it's current default state to inactive
			$exps->set_feature_default_state( $fname, $exps::STATE_INACTIVE );
		}
	}

	update_option( 'vamtam-set-experiments-default-state', true );
}

function vamtam_elementor_additional_animations( $additional_anims ) {
	if ( vamtam_theme_supports( 'image--grow-with-scale-anims' ) && \Vamtam_Elementor_Utils::is_widget_mod_active( 'image' ) ) {
		if ( ! isset( $additional_anims[ 'Vamtam' ] ) ) {
			$additional_anims[ 'Vamtam' ] = [];
		}
		$additional_anims[ 'Vamtam' ] = $additional_anims[ 'Vamtam' ] + [
			'imageGrowWithScaleLeft' => __( 'Image - Grow With Scale (Left)', 'vamtam-elementor-integration' ),
			'imageGrowWithScaleRight' => __( 'Image - Grow With Scale (Right)', 'vamtam-elementor-integration' ),
			'imageGrowWithScaleTop' => __( 'Image - Grow With Scale (Top)', 'vamtam-elementor-integration' ),
			'imageGrowWithScaleBottom' => __( 'Image - Grow With Scale (Bottom)', 'vamtam-elementor-integration' ),
		];
	}
	return $additional_anims;
}

function frontend_before_enqueue_scripts() {
	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	// Enqueue JS for Elementor (frontend).
	wp_enqueue_script(
		'vamtam-elementor-frontend',
		VAMTAM_ELEMENTOR_INT_URL . 'assets/js/vamtam-elementor-frontend' . $suffix . '.js',
		[
			'elementor-frontend', // dependency
			'elementor-dialog', // dependency
		],
		\VamtamElementorIntregration::PLUGIN_VERSION,
		true //in footer
	);
}

function enqueue_editor_scripts() {
	// Enqueue JS for Elementor editor.
	wp_enqueue_script( 'vamtam-elementor', VAMTAM_ELEMENTOR_INT_URL . 'assets/js/vamtam-elementor.js', [], \VamtamElementorIntregration::PLUGIN_VERSION, true );
}

function force_enqueue_fa4_icons() {
	if ( empty( get_option( 'elementor_load_fa4_shim', false ) ) ) {
		update_option( 'elementor_load_fa4_shim', 'yes' );
	}
}
