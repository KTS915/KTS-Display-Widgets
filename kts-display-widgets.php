<?php
/**
 * Plugin Name:       KTS Display Widgets
 * Description:       Adds checkboxes to each widget to show or hide it on specific pages.
 * Author:            Tim Kaye
 * Author URI:        https://timkaye.org
 * Version:           0.2.0
 * Requires CP:       2.1
 * Requires at least: 6.2.3
 * Requires PHP:      7.4
 * License:           GPL2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       display-widgets
 * Domain Path:       /languages
 */

/*
Change the hook this is triggered on with a bit of custom code. Copy and paste into your theme functions.php or a new plugin.
add_filter('dw_callback_trigger', 'dw_callback_trigger');
function dw_callback_trigger(){
	return 'wp_head'; //plugins_loaded, after_setup_theme, wp_loaded, wp_head
}
*/

class KTS_Display_Widgets extends WP_Widget {

	public $transient_name = 'kts_dw_details';
	public $checked = array();
	public $id_base = '';
	public $number = '';

	// pages on site
	public $pages = array();

	// custom post types
	public $cposts = array();

	// taxonomies
	public $taxes = array();

	// categories
	public $cats = array();

	// WPML languages
	public $langs = array();

	public function __construct() {

		add_filter( 'widget_display_callback', array( $this, 'show_widget' ) );

		// change the hook that triggers widget check
		$hook = apply_filters( 'dw_callback_trigger', 'wp_loaded' );

		add_action( $hook, array( $this, 'trigger_widget_checks' ) );
		add_action( 'in_widget_form', array( $this, 'hidden_widget_options'), 10, 3 );
		add_filter( 'widget_update_callback', array( $this, 'update_widget_options' ), 10, 3 );
		add_action( 'wp_ajax_dw_show_widget', array( $this, 'show_widget_options' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_and_styles' ) );

		// when a page is saved
		add_action( 'save_post_page', array( $this, 'delete_transient' ) );

		// when a new category/taxonomy is created
		add_action( 'created_term', array( $this, 'delete_transient' ) );

		// when a custom post type is added
		add_action( 'update_option_rewrite_rules', array( $this, 'delete_transient' ) );

		// reset transient after activating the plugin
		register_activation_hook( dirname(__FILE__) . '/display-widgets.php', array( $this, 'delete_transient' ) );

		add_action( 'plugins_loaded', array( $this, 'load_lang' ) );
	}

	function trigger_widget_checks() {
		add_filter( 'sidebars_widgets', array( $this, 'sidebars_widgets' ) );
	}


	function show_widget( $instance ) {
		$instance['dw_logged'] = self::show_logged( $instance );

		// check logged in first
		if ( in_array( $instance['dw_logged'], array( 'in', 'out' ) ) ) {
			$user_ID = is_user_logged_in();
			if ( ( 'out' == $instance['dw_logged'] && $user_ID ) || ( 'in' == $instance['dw_logged'] && ! $user_ID ) ) {
				return false;
			}
		}

		$post_id = get_queried_object_id();
		$post_id = self::get_lang_id( $post_id, 'page' );

		if ( is_home() ) {
			$show = isset( $instance['page-home'] ) ? $instance['page-home'] : false;
			if ( ! $show && $post_id ) {
				$show = isset( $instance[ 'page-' . $post_id ] ) ? $instance[ 'page-' . $post_id ] : false;
			}

			// check if blog page is front page too
			if ( ! $show && is_front_page() && isset( $instance['page-front'] ) ) {
				$show = $instance['page-front'];
			}
		} else if ( is_front_page() ) {
			$show = isset( $instance['page-front'] ) ? $instance['page-front'] : false;
			if ( ! $show && $post_id ) {
				$show = isset( $instance[ 'page-' . $post_id ] ) ? $instance[ 'page-' . $post_id ] : false;
			}
		} else if ( is_category() ) {
			$show = isset( $instance['cat-all'] ) ? $instance['cat-all'] : false;

			if ( ! $show ) {
				$show = isset( $instance['cat-' . get_query_var('cat') ] ) ? $instance[ 'cat-' . get_query_var('cat') ] : false;
			}
		} else if ( is_tax() ) {
			$term = get_queried_object();
			$show = isset( $instance[ 'tax-' . $term->taxonomy ] ) ? $instance[ 'tax-'. $term->taxonomy] : false;
			unset( $term );
		} else if ( is_post_type_archive() ) {
			$type = get_post_type();
			$show = isset( $instance[ 'type-' . $type . '-archive' ] ) ? $instance[ 'type-' . $type . '-archive' ] : false;
		} else if ( is_archive() ) {
			$show = isset( $instance['page-archive'] ) ? $instance['page-archive'] : false;
		} else if ( is_single() ) {
			$type = get_post_type();
			if ( $type != 'page' && $type != 'post' ) {
				$show = isset( $instance[ 'type-' . $type ] ) ? $instance[ 'type-' . $type ] : false;
			}

			if ( ! isset( $show ) ) {
				$show = isset( $instance['page-single'] ) ? $instance['page-single'] : false;
			}

			if ( ! $show ) {
				$cats = get_the_category();
				foreach ( $cats as $cat ) {
					if ( $show ) {
						break;
					}
					$c_id = self::get_lang_id( $cat->cat_ID, 'category' );
					if ( isset( $instance[ 'cat-' . $c_id ] ) ) {
						$show = $instance[ 'cat-' . $c_id ];
					}
					unset( $c_id, $cat );
				}
			}

		} else if ( is_404() ) {
			$show = isset( $instance['page-404'] ) ? $instance['page-404'] : false;
		} else if ( is_search() ) {
			$show = isset( $instance['page-search'] ) ? $instance['page-search'] : false;
		} else if ( $post_id ) {
			$show = isset( $instance[ 'page-' . $post_id ] ) ? $instance[ 'page-' . $post_id ] : false;
		} else {
			$show = false;
		}

		if ( $post_id && ! $show && isset( $instance['other_ids'] ) && ! empty( $instance['other_ids'] ) ) {
			$other_ids = explode( ',', $instance['other_ids'] );
			foreach ( $other_ids as $other_id ) {
				if ( $post_id == (int) $other_id ) {
					$show = true;
				}
			}
		}

		$show = apply_filters( 'dw_instance_visibility', $show, $instance );

		if ( ! $show && defined( 'ICL_LANGUAGE_CODE' ) ) {
			// check for WPML widgets
			$show = isset( $instance[ 'lang-' . ICL_LANGUAGE_CODE ] ) ? $instance[ 'lang-' . ICL_LANGUAGE_CODE ] : false;
		}

		if ( ! isset( $show ) ) {
			$show = false;
		}

		$instance['dw_include'] = isset( $instance['dw_include'] ) ? $instance['dw_include'] : 0;

		if ( ( $instance['dw_include'] && false == $show ) || ( 0 == $instance['dw_include'] && $show ) ) {
			return false;
		} else if ( defined('ICL_LANGUAGE_CODE') && $instance['dw_include'] && $show && ! isset( $instance[ 'lang-' . ICL_LANGUAGE_CODE ] ) ) {
			//if the widget has to be visible here, but the current language has not been checked, return false
			return false;
		}

		return $instance;
	}

	function sidebars_widgets( $sidebars ) {
		if ( is_admin() ) {
			return $sidebars;
		}

		global $wp_registered_widgets;

		foreach ( $sidebars as $s => $sidebar ) {
			if ( $s == 'wp_inactive_widgets' || strpos( $s, 'orphaned_widgets' ) === 0 || empty( $sidebar ) ) {
				continue;
			}

			foreach ( $sidebar as $w => $widget ) {
				// $widget is the id of the widget
				if ( ! isset( $wp_registered_widgets[ $widget ] ) ) {
					continue;
				}

				if ( isset( $this->checked[ $widget ] ) ) {
					$show = $this->checked[ $widget ];
				} else {
					$opts = $wp_registered_widgets[ $widget ];
					$id_base = is_array( $opts['callback'] ) ? $opts['callback'][0]->id_base : $opts['callback'];

					if ( ! $id_base ) {
						continue;
					}

					$instance = get_option( 'widget_' . $id_base );

					if ( ! $instance || ! is_array( $instance ) ) {
						continue;
					}

					if ( isset( $instance['_multiwidget'] ) && $instance['_multiwidget'] ) {
						$number = $opts['params'][0]['number'];
						if ( ! isset( $instance[ $number ] ) ) {
							continue;
						}

						$instance = $instance[ $number ];
						unset( $number );
					}

					unset( $opts );

					$show = self::show_widget( $instance );

					$this->checked[ $widget ] = $show ? true : false;
				}

				if ( ! $show ) {
					unset( $sidebars[ $s ][ $w ] );
				}

				unset( $widget );
			}
			unset( $sidebar );
		}

		return $sidebars;
	}


	function hidden_widget_options( $widget, $return, $instance ) {
		wp_nonce_field( 'display-widget' );

		self::register_globals();

		$instance['dw_logged'] = self::show_logged( $instance );
		$instance['dw_include'] = isset( $instance['dw_include'] ) ? $instance['dw_include'] : 0;
		$instance['other_ids'] = isset( $instance['other_ids'] ) ? $instance['other_ids'] : '';
		
		// Check if widget was just saved so it's open.
		if ( empty( $instance['dw_logged'] ) ) {
			self::show_hide_widget_options( $widget, $return, $instance );
			return;
		}
		?>

		<div class="dw_opts">
			<input type="hidden" name="<?php echo esc_attr( $widget->get_field_name( 'dw_include' ) ); ?>" id="<?php echo esc_attr( $widget->get_field_id( 'dw_include' ) ); ?>" value="<?php echo esc_attr( $instance['dw_include'] ); ?>">
			<input type="hidden" id="<?php echo esc_attr( $widget->get_field_id( 'dw_logged' ) ); ?>" name="<?php echo esc_attr( $widget->get_field_name( 'dw_logged' ) ); ?>" value="<?php echo esc_attr( $instance['dw_logged'] ); ?>">
	
			<?php
			foreach ( $instance as $k => $v ) {
				if ( ! $v ) {
					continue;
				}
			
				if ( strpos( $k, 'page-' ) === 0 || strpos( $k, 'type-' ) === 0 || strpos( $k, 'cat-' ) === 0 || strpos( $k, 'tax-' ) === 0 || strpos( $k, 'lang-' ) === 0 ) {
				?>

					<input type="hidden" id="<?php echo esc_attr( $widget->get_field_id( $k ) ); ?>" name="<?php echo esc_attr( $widget->get_field_name( $k ) ); ?>" value="<?php echo esc_attr( $v ); ?>">

				<?php
				}
			}
			?>

			<input type="hidden" name="<?php echo esc_attr( $widget->get_field_name( 'other_ids' ) ); ?>" id="<?php echo esc_attr( $widget->get_field_id( 'other_ids' ) ); ?>" value="<?php echo esc_attr( $instance['other_ids'] ); ?>">
		</div>

	<?php
	}


	function show_widget_options() {
		check_admin_referer( 'display-widget' );

		if ( empty( $_POST['id_base'] ) || empty( $_POST['widget_number'] ) || empty( $_POST[ 'widget-' . $_POST['id_base'] ][ $_POST['widget_number'] ] ) ) {
			return;
		}

		$instance = array_map( 'sanitize_text_field', wp_unslash( $_POST[ 'widget-' . $_POST['id_base'] ][ $_POST['widget_number'] ] ) );

		self::show_hide_widget_options( $this, '', $instance );
		wp_die();
	}


	function show_hide_widget_options( $widget, $return, $instance ) {
		self::register_globals();
		$wp_page_types = self::page_types();
			
		$instance['dw_include'] = isset( $instance['dw_include'] ) ? $instance['dw_include'] : 0;
		$instance['dw_logged'] = self::show_logged( $instance );
		$instance['other_ids'] = isset( $instance['other_ids'] ) ? $instance['other_ids'] : '';
		?>   

		<p>
			<label for="<?php echo esc_attr( $widget->get_field_id( 'dw_include' ) ); ?>"><?php esc_html_e( 'Show Widget for:', 'display-widgets' ); ?></label>
			<select name="<?php echo esc_attr( $widget->get_field_name( 'dw_logged' ) ); ?>" id="<?php echo esc_attr( $widget->get_field_id( 'dw_logged' ) ); ?>" class="widefat">
				<option value=""><?php esc_html_e( 'Everyone', 'display-widgets' ); ?></option>
				<option value="out" <?php echo selected( $instance['dw_logged'], 'out' ); ?>><?php esc_html_e( 'Logged-out users', 'display-widgets' ); ?></option>
				<option value="in" <?php echo selected( $instance['dw_logged'], 'in' ); ?>><?php esc_html_e( 'Logged-in users', 'display-widgets' ); ?></option>
			</select>
		</p>

		<p>
			<select name="<?php echo esc_attr( $widget->get_field_name( 'dw_include' ) ); ?>" id="<?php echo esc_attr( $widget->get_field_id( 'dw_include' ) ); ?>" class="widefat">
				<option value="0"><?php esc_html_e( 'Hide on checked pages', 'display-widgets' ); ?></option>
				<option value="1" <?php echo selected( $instance['dw_include'], 1 ); ?>><?php esc_html_e( 'Show on checked pages', 'display-widgets' ); ?></option>
			</select>
		</p>	

		<div class="dw-container">
			<details class="dw-collapse">
				<summary>
					<h4><?php esc_html_e( 'Miscellaneous', 'display-widgets' ); ?></h4>
				</summary>
				<ul>

					<?php
					foreach ( $wp_page_types as $key => $label ) {
						$instance[ 'page-' . $key ] = isset( $instance[ 'page-' . $key ] ) ? $instance[ 'page-' . $key ] : false;
					?>

					<li>
						<input class="checkbox" type="checkbox" <?php checked( $instance[ 'page-' . $key ], true ); ?> id="<?php echo esc_attr( $widget->get_field_id( 'page-'. $key ) ); ?>" name="<?php echo esc_attr( $widget->get_field_name( 'page-'. $key ) ); ?>">
						<label for="<?php echo esc_attr( $widget->get_field_id( 'page-' . $key ) ); ?>"><?php echo esc_html( $label ); ?></label>
					</li>

					<?php
					}
					?>

				</ul>
			</details>

			<details class="dw-collapse">
				<summary>
					<h4><?php esc_html_e( 'Pages', 'display-widgets' ); ?></h4>
				</summary>
				<ul>

					<?php 
					foreach ( $this->pages as $page ) {
						$instance[ 'page-' . $page->ID ] = isset( $instance[ 'page-' . $page->ID ] ) ? $instance[ 'page-' . $page->ID ] : false;
						?>
						
						<li>
							<input class="checkbox" type="checkbox" <?php checked( $instance[ 'page-'. $page->ID ], true ); ?> id="<?php echo esc_attr( $widget->get_field_id( 'page-'. $page->ID ) ); ?>" name="<?php echo esc_attr( $widget->get_field_name( 'page-'. $page->ID ) ); ?>">
							<label for="<?php echo esc_attr( $widget->get_field_id( 'page-'. $page->ID ) ); ?>"><?php echo esc_html( $page->post_title ); ?></label>
						</li>

						<?php
					}
					?>

				</ul>
			</details>
	
			<?php
			if ( ! empty( $this->cposts ) ) {
			?>

			<details class="dw-collapse">
				<summary>
					<h4><?php esc_html_e( 'Custom Post Types', 'display-widgets' ); ?></h4>
				</summary>
				<ul>

					<?php
					foreach ( $this->cposts as $post_key => $custom_post ) {
						$instance[ 'type-' . $post_key ] = isset( $instance[ 'type-' . $post_key ] ) ? $instance[ 'type-' . $post_key ] : false;
						?>

						<li>
							<input class="checkbox" type="checkbox" <?php checked( $instance[ 'type-'. $post_key ], true ); ?> id="<?php echo esc_attr( $widget->get_field_id( 'type-'. $post_key ) ); ?>" name="<?php echo esc_attr( $widget->get_field_name( 'type-'. $post_key ) ); ?>">
							<label for="<?php echo esc_attr( $widget->get_field_id( 'type-'. $post_key ) ); ?>"><?php echo esc_html( $custom_post->labels->name ); ?></label>
						</li>

						<?php
						unset( $post_key, $custom_post );
					}
					?>

				</ul>
			</details>

			<details class="dw-collapse">
				<summary>
					<h4><?php esc_html_e( 'Custom Post Type Archives', 'display-widgets' ); ?></h4>
				</summary>
				<ul>

					<?php
					foreach ( $this->cposts as $post_key => $custom_post ) {
						if ( ! $custom_post->has_archive ) {
							// don't give the option if there is no archive page
							continue;
						}
						$instance[ 'type-' . $post_key . '-archive' ] = isset( $instance[ 'type-' . $post_key . '-archive' ] ) ? $instance[ 'type-' . $post_key . '-archive' ] : false;
						?>

						<li>
							<input class="checkbox" type="checkbox" <?php checked( $instance[ 'type-' . $post_key . '-archive' ], true ); ?> id="<?php echo esc_attr( $widget->get_field_id( 'type-'. $post_key . '-archive' ) ); ?>" name="<?php echo esc_attr( $widget->get_field_name( 'type-' . $post_key . '-archive' ) ); ?>">
							<label for="<?php echo esc_attr( $widget->get_field_id( 'type-' . $post_key . '-archive' ) ); ?>"><?php echo esc_html( $custom_post->labels->name ); ?> <?php esc_html_e( 'Archive', 'display-widgets' ); ?></label>
						</li>
					<?php
					}
					?>
				</ul>
			</details>

			<?php
			}
			?>

			<details class="dw-collapse">
				<summary>
					<h4><?php esc_html_e( 'Categories', 'display-widgets' ); ?></h4>
				</summary>
				<ul>

					<?php
					$instance['cat-all'] = isset( $instance['cat-all'] ) ? $instance['cat-all'] : false;
					?>

					<li>
						<input class="checkbox" type="checkbox" <?php checked( $instance['cat-all'], true ); ?> id="<?php echo esc_attr( $widget->get_field_id( 'cat-all' ) ); ?>" name="<?php echo esc_attr( $widget->get_field_name( 'cat-all' ) ); ?>">
						<label for="<?php echo esc_attr( $widget->get_field_id( 'cat-all' ) ); ?>"><?php esc_html_e( 'All Categories', 'display-widgets' ); ?></label>
					</li>

					<?php
					foreach ( $this->cats as $cat ) {
						$instance[ 'cat-' . $cat->cat_ID ] = isset( $instance[ 'cat-' . $cat->cat_ID ] ) ? $instance[ 'cat-' . $cat->cat_ID ] : false;
						?>

					<li>
						<input class="checkbox" type="checkbox" <?php checked( $instance[ 'cat-'. $cat->cat_ID ], true ); ?> id="<?php echo esc_attr( $widget->get_field_id( 'cat-' . $cat->cat_ID ) ); ?>" name="<?php echo esc_attr( $widget->get_field_name( 'cat-'. $cat->cat_ID ) ); ?>">
						<label for="<?php echo esc_attr( $widget->get_field_id( 'cat-'. $cat->cat_ID ) ); ?>"><?php echo esc_html( $cat->cat_name ); ?></label>
					</li>

					<?php
					unset( $cat );
					}
					?>
				</ul>
			</details>
	
			<?php
			if ( ! empty( $this->taxes ) ) {
			?>

			<details class="dw-collapse">
				<summary>
					<h4><?php esc_html_e( 'Taxonomies', 'display-widgets' ); ?></h4>
				</summary>
				<ul>

					<?php
					foreach ( $this->taxes as $tax => $taxname ) {
						$instance[ 'tax-' . $tax ] = isset( $instance[ 'tax-' . $tax ] ) ? $instance[ 'tax-' . $tax ] : false;
						?>

					<li>
						<input class="checkbox" type="checkbox" <?php checked( $instance['tax-'. $tax], true ); ?> id="<?php echo esc_attr( $widget->get_field_id( 'tax-'. $tax ) ); ?>" name="<?php echo esc_attr( $widget->get_field_name( 'tax-'. $tax ) ); ?>">
						<label for="<?php echo esc_attr( $widget->get_field_id( 'tax-' . $tax ) ); ?>"><?php echo esc_html( str_replace( array( '_','-' ), ' ', ucfirst( $taxname ) ) ); ?></label>
					</li>

						<?php
						unset( $tax );
					}
					?>

				</ul>
			</details>

			<?php
			}
	
			if ( ! empty( $this->langs ) ) {
			?>

			<details class="dw-collapse">
				<summary>
					<h4><?php esc_html_e( 'Languages', 'display-widgets' ); ?></h4>
				</summary>
				<ul>

					<?php
					foreach ( $this->langs as $lang ) {
						$key = $lang['language_code'];
						$instance[ 'lang-' . $key ] = isset( $instance[ 'lang-' . $key ] ) ? $instance[ 'lang-' . $key ] : false;
						?>

						<li>
							<input class="checkbox" type="checkbox" <?php checked( $instance[ 'lang-' . $key ], true ); ?> id="<?php echo esc_attr( $widget->get_field_id( 'lang-'. $key ) ); ?>" name="<?php echo esc_attr( $widget->get_field_name( 'lang-'. $key ) ); ?>">
							<label for="<?php echo esc_attr( $widget->get_field_id( 'lang-' . $key ) ); ?>"><?php echo esc_html( $lang[ 'native_name' ] ); ?></label>
						</li>

						<?php 
						unset( $lang, $key );
					}
					?>

				</ul>
			</details>

			<?php
			}
			?>
	
			<p>
				<label for="<?php echo esc_attr( $widget->get_field_id( 'other_ids' ) ); ?>"><?php esc_html_e( 'Comma Separated list of IDs of posts not listed above', 'display-widgets' ); ?>:</label>
				<input type="text" value="<?php echo esc_attr( $instance['other_ids'] ); ?>" name="<?php echo esc_attr( $widget->get_field_name( 'other_ids' ) ); ?>" id="<?php echo esc_attr( $widget->get_field_id( 'other_ids' ) ); ?>">
			</p>
		</div>

	<?php
	}


	function update_widget_options( $instance, $new_instance, $old_instance ) {
		self::register_globals();
	
		if ( ! empty( $this->pages ) ) {
			foreach ( $this->pages as $page ) {
				if ( isset( $new_instance[ 'page-' . $page->ID ] ) ) {
					$instance[ 'page-' . $page->ID ] = 1;
				} else if ( isset( $instance[ 'page-' . $page->ID ] ) ) {
					unset( $instance[ 'page-' . $page->ID ] );
				}
				unset( $page );
			}
		}

		if ( isset( $new_instance['cat-all'] ) ) {
			$instance['cat-all'] = 1;

			foreach ( $this->cats as $cat ) {
				if ( isset( $new_instance[ 'cat-' . $cat->cat_ID ] ) ) {
					unset( $instance['cat-' . $cat->cat_ID ] );
				}
			}
		} else {
			unset( $instance['cat-all'] );

			foreach ( $this->cats as $cat ) {
				if ( isset( $new_instance[ 'cat-' . $cat->cat_ID ] ) ) {
					$instance[ 'cat-' . $cat->cat_ID ] = 1;
				} else if ( isset( $instance[ 'cat-' . $cat->cat_ID ] ) ) {
					unset( $instance['cat-' . $cat->cat_ID ] );
				}
				unset( $cat );
			}
		}

		if ( ! empty( $this->cposts ) ) {
			foreach ( $this->cposts as $post_key => $custom_post ) {
				if ( isset( $new_instance[ 'type-' . $post_key ] ) ) {
					$instance['type-'. $post_key] = 1;
				} else if ( isset( $instance['type-' . $post_key ] ) ) {
					unset( $instance[ 'type-' . $post_key ] );
				}

				if ( isset( $new_instance['type-' . $post_key . '-archive' ] ) ) {
					$instance[ 'type-' . $post_key . '-archive' ] = 1;
				} else if ( isset( $instance[ 'type-' . $post_key . '-archive' ] ) ) {
					unset( $instance[ 'type-' . $post_key . '-archive' ] );
				}
				
				unset( $custom_post );
			}
		}

		if ( ! empty( $this->taxes ) ) {
			foreach ( $this->taxes as $tax => $taxname ) {
				if ( isset( $new_instance[ 'tax-' . $tax ] ) ) {
					$instance['tax-'. $tax] = 1;
				} else if ( isset( $instance[ 'tax-' . $tax ] ) ) {
					unset( $instance[ 'tax-' . $tax ] );
				}
				unset( $tax );
			}
		}

		if ( ! empty( $this->langs ) ) {
			foreach ( $this->langs as $lang ) {
				if ( isset( $new_instance[ 'lang-' . $lang['language_code'] ] ) ) {
					$instance[ 'lang-' . $lang['language_code'] ] = 1;
				} else if ( isset( $instance[ 'lang-'. $lang['language_code'] ] ) ) {
					unset( $instance[ 'lang-' . $lang['language_code'] ] ) ;
				}
				unset( $lang );
			}
		}

		$instance['dw_include'] = ( isset( $new_instance['dw_include'] ) && $new_instance['dw_include'] ) ? 1 : 0;
		$instance['dw_logged'] = ( isset( $new_instance['dw_logged'] ) && $new_instance['dw_logged'] ) ? $new_instance['dw_logged'] : '';
		$instance['other_ids'] = ( isset( $new_instance['other_ids'] ) && $new_instance['other_ids'] ) ? $new_instance['other_ids'] : '';
		
		$page_types = self::page_types();
		foreach ( array_keys( $page_types ) as $page ) {
			if ( isset( $new_instance[ 'page-'. $page ] ) ) {
				$instance[ 'page-' . $page ] = 1;
			} else if ( isset( $instance['page-' . $page ] ) ) {
				unset( $instance[ 'page-' . $page ] );
			}
		}
		unset( $page_types );

		return $instance;
	}
	
	function get_field_name( $field_name ) {
		return 'widget-' . $this->id_base . '[' . $this->number . '][' . $field_name . ']';
	}
	
	function get_field_id( $field_name ) {
		return 'widget-' . $this->id_base . '-' . $this->number . '-' . $field_name;
	}
	
	function enqueue_scripts_and_styles() {
		global $pagenow;

		// Only load the JS and CSS on the widgets page.
		if ( $pagenow != 'widgets.php' ) {
			return;
		}
		wp_enqueue_style( 'kts-display-widgets', plugin_dir_url( __FILE__ ) . 'css/display-widgets-styles.css' );
		wp_enqueue_script( 'kts-display-widgets', plugin_dir_url( __FILE__ ) . 'js/display-widgets-scripts.js' );
	}


	function show_logged( $instance ) {
		if ( isset( $instance['dw_logged'] ) ) {
			return $instance['dw_logged'];
		}

		if ( isset( $instance['dw_logout'] ) && $instance['dw_logout'] ) {
			$instance['dw_logged'] = 'out';
		} else if ( isset( $instance['dw_login'] ) && $instance['dw_login'] ) {
			$instance['dw_logged'] = 'in';
		} else {
			$instance['dw_logged'] = '';
		}

		return $instance['dw_logged'];
	}


	function page_types(){
		$page_types = array(
			'front'	  => __( 'Front', 'display-widgets' ),
			'home'	  => __( 'Blog', 'display-widgets' ),
			'archive' => __( 'Archives', 'display-widgets' ),
			'single'  => __( 'Single Post', 'display-widgets' ),
			'404'	  => '404',
			'search'  => __( 'Search', 'display-widgets' ),
		);

		return apply_filters( 'dw_pages_types_register', $page_types );
	}


	function register_globals(){
		if ( ! empty( $this->checked ) ) {
			return;
		}

		$saved_details = get_transient( $this->transient_name );
		if ( $saved_details ) {
			foreach ( $saved_details as $k => $d ) {
				if ( empty( $this->{$k} ) ) {
					$this->{$k} = $d;
				}
				
				unset( $k, $d );
			}
		}

		if ( empty( $this->pages ) ) {
			$this->pages = get_posts(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'numberposts' => -1,
					'orderby'     => 'title', 
					'order'       => 'ASC',
					'fields'      => array( 'ID', 'name'),
				)
			);
		}

		if ( empty( $this->cats ) ) {
			$this->cats = get_categories( array(
				'hide_empty'	=> false,
				//'fields'		=> 'id=>name', //added in 3.8
			) );
		}

		if ( empty( $this->cposts ) ) {
			$this->cposts = get_post_types( array(
				'public' => true,
			), 'object');

			foreach ( array( 'revision', 'attachment', 'nav_menu_item' ) as $unset ) {
				unset( $this->cposts[ $unset ] );
			}

			foreach ( $this->cposts as $c => $type ) {
				$post_taxes = get_object_taxonomies( $c );
				foreach ( $post_taxes as $post_tax) {
					if ( in_array( $post_tax, array( 'category', 'post_format' ) ) ) {
						continue;
					}

					$taxonomy = get_taxonomy( $post_tax );
					$name = $post_tax;

					if ( isset( $taxonomy->labels->name ) && ! empty( $taxonomy->labels->name ) ) {
						$name = $taxonomy->labels->name;
					}

					$this->taxes[ $post_tax ] = $name;
				}
			}
		}

		if ( empty( $this->langs ) && function_exists( 'icl_get_languages' ) ) {
			$this->langs = icl_get_languages( 'skip_missing=0&orderby=code' );
		}

		// Save for one week
		set_transient( $this->transient_name, array(
			'pages'	 => $this->pages,
			'cats'   => $this->cats,
			'cposts' => $this->cposts,
			'taxes'	 => $this->taxes,
		), WEEK_IN_SECONDS );

		if ( empty( $this->checked ) ) {
			$this->checked[] = true;
		}
	}


	function delete_transient() {
		delete_transient( $this->transient_name );
	}


	function load_lang(){
		load_plugin_textdomain( 'display-widgets', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}


	/* WPML support */
	function get_lang_id( $id, $type = 'page' ) {
		if ( function_exists( 'icl_object_id' ) ) {
			$id = icl_object_id( $id, $type, true );
		}

		return $id;
	}
}

new KTS_Display_Widgets();
