<?php
/**
 * Redux Metabox Extension Class
 *
 * @package Redux Extentions
 * @author  Dovy Paukstys <dovy@reduxframework.com> & Kevin Provance <kevin.provance@gmail.com>
 * @class   Redux_Extension_Metaboxes_Lite
 *
 * @version 4.0.0
 */

defined( 'ABSPATH' ) || exit;

// Don't duplicate me!
if ( ! class_exists( 'Redux_Extension_Metaboxes_Lite', false ) ) {

	/**
	 * Main Redux_Extension_Metaboxes_Lite class
	 *
	 * @since       1.0.0
	 */
	class Redux_Extension_Metaboxes_Lite {

		/**
		 * Extension version.
		 *
		 * @var string
		 */
		public static $version = '4.0.0';

		/**
		 * Extension friendly name.
		 *
		 * @var string
		 */
		public $ext_name = 'Metaboxes Lite';

		/**
		 * Boxes array.
		 *
		 * @var array
		 */
		public $boxes = array();

		/**
		 * Post types array.
		 *
		 * @var array
		 */
		public $post_types = array();

		/**
		 * Post type.
		 *
		 * @var string
		 */
		public $post_type;

		/**
		 * Sections array.
		 *
		 * @var array
		 */
		public $sections = array();

		/**
		 * CSS output array.
		 *
		 * @var array
		 */
		public $output = array();

		/**
		 * ReduxFramework object pointer.
		 *
		 * @var object
		 */
		public $parent = null;

		/**
		 * Options array.
		 *
		 * @var array
		 */
		public $options = array();

		/**
		 * Parent options array.
		 *
		 * @var array
		 */
		public $parent_options = array();

		/**
		 * Parent defaults array.
		 *
		 * @var array
		 */
		public $parent_defaults = array();

		/**
		 * Post type fields array.
		 *
		 * @var array
		 */
		public $post_type_fields = array();

		/**
		 * Options defaults array.
		 *
		 * @var array
		 */
		public $options_defaults = array();

		/**
		 * Replace array.
		 *
		 * @var array
		 */
		public $to_replace = array();

		/**
		 * Extension URI.
		 *
		 * @var string|void
		 */
		public $_extension_url;

		/**
		 * Extension Directory.
		 *
		 * @var string
		 */
		public $_extension_dir;

		/**
		 * Meta data array.
		 *
		 * @var array
		 */
		public $meta = array();

		/**
		 * Post ID.
		 *
		 * @var int
		 */
		public $post_id = 0;

		/**
		 * Base URI.
		 *
		 * @var string
		 */
		public $base_url;

		/**
		 * ReduxFramework_extension_metaboxes constructor.
		 *
		 * @param object $parent ReduxFramework object.
		 */
		public function __construct( $parent ) {
			global $pagenow;

			Redux_Core::$wp_nonce = wp_create_nonce( __CLASS__ );

			$this->parent = $parent;

			$this->parent->extensions['metaboxes_lite'] = $this;

			if ( empty( self::$_extension_dir ) ) {
				$this->_extension_dir = trailingslashit( str_replace( '\\', '/', dirname( __FILE__ ) ) );
				$this->_extension_url = site_url( str_replace( trailingslashit( str_replace( '\\', '/', ABSPATH ) ), '', $this->_extension_dir ) );
			}

			// Only run metaboxes on the pages/posts, not the front-end.
			// The DOING_AJAX check allows for redux_post_meta to work inside
			// AJAX calls. - kp.
			if ( 'post-new.php' !== $pagenow && 'post.php' !== $pagenow ) {
				if ( is_admin() ) {
					if ( defined( 'DOING_AJAX' ) && ! DOING_AJAX ) {
						return;
					}

					return;
				}
			}

			// Must not update the DB when just updating metaboxes. Sheesh.
			if ( is_admin() && ( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) ) {
				$this->parent->never_save_to_db = true;
			}

			add_action( 'add_' . 'meta_' . 'boxes', array( $this, 'add' ) );
			add_action( 'save_post', array( $this, 'meta_boxes_save' ), 1, 2 );
			add_action( 'pre_post_update', array( $this, 'pre_post_update' ) );
			add_action( 'admin_notices', array( $this, 'meta_boxes_show_errors' ), 0 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 20 );

			// Global variable overrides for within loops.
			add_action( 'the_post', array( $this, 'loop_start' ), 0 );
			add_action( 'loop_end', array( $this, 'loop_end' ), 0 );

			$this->init();
		}

		/**
		 * Added class names to metabox DIV.
		 *
		 * @param array $classes Class array.
		 *
		 * @return array
		 */
		public function add_box_classes( $classes ) {
			$classes[] = 'redux-metabox';
			$classes[] = 'redux-' . $this->parent->args['opt_name'];

			if ( '' !== $this->parent->args['class'] ) {
				$classes[] = $this->parent->args['class'];
			}

			return $classes;
		}

		/**
		 * Class init.
		 */
		public function init() {
			global $pagenow;

			// phpcs:ignore WordPress.NamingConventions.ValidHookName
			$this->boxes = apply_filters( 'redux/metaboxes/' . $this->parent->args['opt_name'] . '/boxes', $this->boxes, $this->parent->args['opt_name'] );

			if ( empty( $this->boxes ) && class_exists( 'Redux_Metaboxes' ) ) {
				$this->boxes = Redux_Metaboxes::construct_boxes( $this->parent->args['opt_name'] );
			}

			if ( empty( $this->boxes ) || ! is_array( $this->boxes ) ) {
				return;
			}
			if ( isset( self::$_server['HTTP_HOST'] ) && isset( self::$_server['REQUEST_URI'] ) ) {
				$this->base_url = ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( self::$_server['HTTP_HOST'] ) ) . sanitize_text_field( wp_unslash( self::$_server['REQUEST_URI'] ) ); // Safe & Reliable
				$this->post_id  = $this->url_to_postid( ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( self::$_server['HTTP_HOST'] ) ) . sanitize_text_field( wp_unslash( self::$_server['REQUEST_URI'] ) ) );
			}

			if ( is_admin() && isset( $_GET['post_type'] ) && ! empty( $_GET['post_type'] ) ) { // WPCS: CSRF ok.
				$this->post_type = sanitize_text_field( wp_unslash( $_GET['post_type'] ) ); // WPCS: CSRF ok.
			} else {
				$this->post_type = get_post_type( $this->post_id );
			}

			foreach ( $this->boxes as $bk => $box ) {

				// If the post ids for this box are set, we're limiting to the current post id.
				if ( isset( $box['post_ids'] ) && ! empty( $box['post_ids'] ) ) {
					if ( ! is_array( $box['post_ids'] ) ) {
						$box['post_ids'] = array( $box['post_ids'] );
					}
					if ( ! in_array( $this->post_id, $box['post_ids'], true ) ) {
						continue;
					}
				}

				if ( ! empty( $box['sections'] ) ) {
					$this->sections = $box['sections'];

					array_merge( $this->parent->sections, $box['sections'] );

					$this->post_types = wp_parse_args( $this->post_types, $box['post_types'] );

					// Checking to overide the parent variables.
					$add_field = false;

					foreach ( $box['post_types'] as $type ) {
						if ( $this->post_type === $type ) {
							$add_field = true;
						}
					}

					// Replacing all the fields.
					if ( $add_field || ( ( is_admin() && ( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) ) || ( ! is_admin() ) ) ) {
						$run_hooks = true;

						$box_id = 'redux-' . $this->parent->args['opt_name'] . '-metabox-' . $box['id'];

						// phpcs:ignore WordPress.NamingConventions.ValidHookName
						do_action( 'redux/' . $this->parent->args['opt_name'] . '/extensions/metabox/init', $this, $box );

						$this->meta[ $this->post_id ] = $this->get_meta( $this->post_id );

						foreach ( $box['sections'] as $sk => $section ) {
							if ( isset( $section['fields'] ) && ! empty( $section['fields'] ) ) {
								foreach ( $section['fields'] as $fk => $field ) {
									if ( ! isset( $field['class'] ) ) {
										$field['class'] = '';

										$this->boxes[ $bk ]['sections'][ $sk ]['fields'][ $fk ] = $field;
									}

									if ( $add_field || ( ( is_admin() && ( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) ) || ( ! is_admin() ) ) ) {
										if ( empty( $field['id'] ) ) {
											continue;
										}

										if ( isset( $field['default'] ) ) {
											$this->options_defaults[ $field['id'] ] = $field['default'];
										} else {
											$this->options_defaults[ $field['id'] ] = $this->field_default( $field );
										}

										foreach ( $box['post_types'] as $type ) {
											$this->post_type_fields[ $type ][ $field['id'] ] = 1;
										}

										if ( isset( $field['output'] ) && ! empty( $field['output'] ) ) {
											$this->output[ $field['id'] ] = isset( $this->output[ $field['id'] ] ) ? array_merge( $field['output'], $this->output[ $field['id'] ] ) : $field['output'];
										}

										// Detect what field types are being used.
										if ( ! isset( $this->parent->fields[ $field['type'] ][ $field['id'] ] ) ) {
											$this->parent->fields[ $field['type'] ][ $field['id'] ] = 1;
										} else {
											$this->parent->fields[ $field['type'] ] = array( $field['id'] => 1 );
										}

										if ( isset( $this->options_defaults[ $field['id'] ] ) ) {
											$this->to_replace[ $field['id'] ] = $field;
										}
									}

									if ( ! isset( $this->parent->options[ $field['id'] ] ) ) {
										$this->parent->sections[ ( count( $this->parent->sections ) - 1 ) ]['fields'][] = $field;
									}

									if ( ! isset( $this->meta[ $this->post_id ][ $field['id'] ] ) ) {
										$this->meta[ $this->post_id ][ $field['id'] ] = $this->options_defaults[ $field['id'] ];
									}

									// Only override if it exists and it's not the default.
									// phpcs:ignore Squiz.PHP.CommentedOutCode
									// if ( isset( $this->meta[ $this->post_id ][ $field['id'] ] ) && isset( $field['default'] ) && $this->meta[ $this->post_id ][ $field['id'] ] === $field['default'] ) {
									// unset($this->meta[$this->post_id][$field['id']]);
									// } .
								}
							}
						}
					}
				}
			}

			if ( isset( $run_hooks ) && true === $run_hooks ) {
				$this->parent_options = '';

				if ( ! empty( $this->to_replace ) ) {
					foreach ( $this->to_replace as $id => $field ) {

						// phpcs:ignore WordPress.NamingConventions.ValidHookName
						add_filter( "redux/options/{$this->parent->args['opt_name']}/field/{$id}/register", array( $this, 'replace_field' ) );
					}
				}

				add_filter( "redux/options/{$this->parent->args['opt_name']}/options", array( $this, 'override_options' ) );
				add_filter( "redux/field/{$this->parent->args['opt_name']}/_can_output_css", array( $this, 'override_can_output_css' ) );
				add_filter( "redux/field/{$this->parent->args['opt_name']}/output_css", array( $this, 'output_css' ) );
			}
		}

		/**
		 * Replace Field.
		 *
		 * @param array $field Field array.
		 *
		 * @return mixed
		 */
		public function replace_field( $field ) {
			if ( isset( $this->to_replace[ $field['id'] ] ) ) {
				$field = $this->to_replace[ $field['id'] ];
			}

			return $field;
		}

		/**
		 * Override CSS output.
		 *
		 * @param array $field Field array.
		 *
		 * @return mixed
		 */
		public function override_can_output_css( $field ) {
			if ( isset( $this->output[ $field['id'] ] ) ) {
				$field['force_output'] = true;
			}

			return $field;
		}

		/**
		 * Output CSS.
		 *
		 * @param array $field Field array.
		 *
		 * @return mixed
		 */
		public function output_css( $field ) {
			if ( isset( $this->output[ $field['id'] ] ) ) {
				$field['output'] = $this->output[ $field['id'] ];
			}

			return $field;
		}

		/**
		 * Make sure the defaults are the defaults
		 *
		 * @param array $options Options array.
		 *
		 * @return array
		 */
		public function override_options( $options ) {
			$this->parent->_default_values();
			$this->parent_defaults = $this->parent->options_defaults;

			$meta = $this->get_meta( $this->post_id );
			$data = wp_parse_args( $meta, $this->options_defaults );

			foreach ( $data as $key => $value ) {
				if ( isset( $meta[ $key ] ) && '' !== $meta[ $key ] ) {
					$data[ $key ] = $meta[ $key ];
					continue;
				}

				if ( isset( $options[ $key ] ) ) {
					if ( isset( $options[ $key ] ) ) {
						$data[ $key ] = $options[ $key ];
					}
				}
			}

			$this->parent->options_defaults = wp_parse_args( $this->options_defaults, $this->parent->options_defaults );

			$options = wp_parse_args( $data, $options );

			return $options;
		}

		/**
		 * Loop start.
		 *
		 * @param array $the_post WP_Post object.
		 *
		 * @return array|void|WP_Post|null
		 */
		public function loop_start( $the_post = array() ) {
			if ( is_admin() ) {
				return $the_post;
			}

			if ( isset( $the_post ) && is_array( $the_post ) ) {
				global $post;
				$the_post = $post;
			}

			if ( isset( $GLOBALS[ $this->parent->args['global_variable'] . '-loop' ] ) ) {
				$GLOBALS[ $this->parent->args['global_variable'] ] = $GLOBALS[ $this->parent->args['global_variable'] . '-loop' ];
				unset( $GLOBALS[ $this->parent->args['global_variable'] . '-loop' ] );
			}

			// Override these values if they differ from the admin panel defaults.  ;) .
			if ( in_array( $the_post->post_type, $this->post_types, true ) ) {
				$meta = $this->get_meta( $the_post->ID );
				if ( empty( $meta ) ) {
					return;
				}

				// Backup the args.
				$GLOBALS[ $this->parent->args['global_variable'] . '-loop' ] = $GLOBALS[ $this->parent->args['global_variable'] ];
				$GLOBALS[ $this->parent->args['global_variable'] ]           = wp_parse_args( $meta, $GLOBALS[ $this->parent->args['global_variable'] . '-loop' ] );
			}
		}

		/**
		 * Loop end.
		 */
		public function loop_end() {
			if ( isset( $GLOBALS[ $this->parent->args['global_variable'] . '-loop' ] ) ) {
				$GLOBALS[ $this->parent->args['global_variable'] ] = $GLOBALS[ $this->parent->args['global_variable'] . '-loop' ];

				unset( $GLOBALS[ $this->parent->args['global_variable'] . '-loop' ] );
			}
		}

		/**
		 * Enqueue fields.
		 */
		public function enqueue() {
			global $pagenow;

			$types = array();

			// Enqueue CSS.
			foreach ( $this->boxes as $key => $box ) {
				if ( empty( $box['sections'] ) ) {
					continue;
				}

				if ( isset( $box['post_types'] ) ) {
					$types = array_merge( $box['post_types'], $types );
				}

				if ( isset( $box['post_types'] ) && ! empty( $box['post_types'] ) ) {
					if ( ! is_array( $box['post_types'] ) ) {
						$box['post_types']                 = array( $box['post_types'] );
						$this->boxes[ $key ]['post_types'] = $box['post_types'];
					}
				}
			}

			if ( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) {
				global $post;

				if ( in_array( $post->post_type, $types, true ) ) {
					$this->parent->transients       = get_transient( $this->parent->args['opt_name'] . '-transients-metaboxes' );
					$this->parent->transients_check = $this->parent->transients;

					if ( isset( $this->parent->transients['notices'] ) ) {
						$this->notices                              = $this->parent->transients['notices'];
						$this->parent->transients['last_save_mode'] = 'metaboxes';
					}

					delete_transient( $this->parent->args['opt_name'] . '-transients-metaboxes' );
					$this->parent->_enqueue();

					// phpcs:ignore WordPress.NamingConventions.ValidHookName
					do_action( "redux/metaboxes/{$this->parent->args['opt_name']}/enqueue" );

					// phpcs:ignore WordPress.NamingConventions.ValidHookName
					do_action( "redux/{$this->parent->args['opt_name']}/extensions/metaboxes/enqueue" );

					/**
					 * Redux metaboxes CSS
					 * filter 'redux/page/{opt_name}/enqueue/redux-extension-metaboxes-css'
					 *
					 * @param string  bundled stylesheet src
					 */
					if ( $this->parent->args['dev_mode'] ) {
						wp_enqueue_style(
							'redux-extension-metaboxes-js',
							apply_filters( "redux/metaboxes/{$this->parent->args['opt_name']}/enqueue/redux-extension-metaboxes-css", $this->_extension_url . 'redux-metaboxes.css' ), // phpcs:ignore: WordPress.NamingConventions.ValidHookName
							array(),
							self::$version,
							'all'
						);
					}

					/**
					 * Redux metaboxes JS
					 * filter 'redux/page/{opt_name}/enqueue/redux-extension-metaboxes-js
					 *
					 * @param string  bundled javscript
					 */
					wp_enqueue_script(
						'redux-extension-metaboxes-js',
						apply_filters( "redux/metaboxes/{$this->parent->args['opt_name']}/enqueue/redux-extension-metaboxes-js", $this->_extension_url . 'redux-metaboxes' . Redux_Functions::isMin() . '.js' ), // phpcs:ignore: WordPress.NamingConventions.ValidHookName
						array( 'jquery', 'redux-js' ),
						self::$version,
						true
					);
				}
			}
		}

		/* Post URLs to IDs function, supports custom post types - borrowed and modified from url_to_postid() in wp-includes/rewrite.php */

		// Taken from http://betterwp.net/wordpress-tips/url_to_postid-for-custom-post-types/
		// Customized to work with non-rewrite URLs
		// Copyright Dovy Paukstys (@dovy) of Redux Framework.

		/**
		 * URL to PostID.
		 *
		 * @param string $url URL.
		 *
		 * @return int|mixed|void
		 */
		private function url_to_postid( $url ) {
			global $wp_rewrite, $pagenow;

			if ( ! empty( $this->post_id ) ) {
				return $this->post_id;
			}

			if ( isset( $_GET['post'] ) ) {
				$post = sanitize_text_field( wp_unslash( $_GET['post'] ) );  // WPCS: CSRF ok.

				if ( ! empty( $post ) && is_numeric( $post ) ) {
					return $post;
				}
			} else {
				return;
			}

			if ( is_admin() && 'post.php' === $pagenow && isset( $_POST['post_ID'] ) ) {
				$post_id = sanitize_text_field( wp_unslash( $_POST['post_ID'] ) ); // WPCS: CSRF ok.

				if ( ! empty( $post_id ) && is_numeric( $post_id ) ) {
					return $post_id;
				}
			}

			$post_id = url_to_postid( $url );
			if ( isset( $post_id ) && '' !== $post_id && 0 !== $post_id ) {
				return $post_id;
			}

			// First, check to see if there is a 'p=N' or 'page_id=N' to match against.
			if ( preg_match( '#[?&](p|page_id|attachment_id)=(\d+)#', $url, $values ) ) {
				$id = absint( $values[2] );
				if ( $id ) {
					return $id;
				}
			}

			// Check to see if we are using rewrite rules.
			if ( isset( $wp_rewrite ) ) {
				$rewrite = $wp_rewrite->wp_rewrite_rules();
			}

			// Not using rewrite rules, and 'p=N' and 'page_id=N' methods failed, so we're out of options.
			if ( empty( $rewrite ) ) {
				if ( isset( $_GET ) && ! empty( $_GET ) ) { // WPCS: CSRF ok.

					/************************************************************************
					 * ADDED: Trys checks URL for ?posttype=postname
					 ************************************************************************ */

					// Assign $url to $temp_url just incase. :) .
					$temp_url = $url;

					// Get rid of the #anchor.
					$url_split = explode( '#', $temp_url );
					$temp_url  = $url_split[0];

					// Get rid of URL ?query=string.
					$url_query = explode( '&', $temp_url );
					$temp_url  = $url_query[0];

					// Get rid of ? mark.
					$url_query = explode( '?', $temp_url );

					if ( isset( $url_query[1] ) && ! empty( $url_query[1] ) && strpos( $url_query[1], '=' ) ) {
						$url_query = explode( '=', $url_query[1] );

						if ( isset( $url_query[0] ) && isset( $url_query[1] ) ) {
							$args = array(
								'name'      => $url_query[1],
								'post_type' => $url_query[0],
								'showposts' => 1,
							);

							if ( get_posts( $args ) === $post ) {
								return $post[0]->ID;
							}
						}
					}

					foreach ( $GLOBALS['wp_post_types'] as $key => $value ) {
						if ( isset( $_GET[ $key ] ) && ! empty( $_GET[ $key ] ) ) { // WPCS: CSRF ok.
							$args = array(
								'name'      => sanitize_text_field( wp_unslash( $_GET[ $key ] ) ), // WPCS: CSRF ok.
								'post_type' => $key,
								'showposts' => 1,
							);

							if ( get_posts( $args ) === $post ) {
								return $post[0]->ID;
							}
						}
					}
				}
			}

			// Get rid of the #anchor.
			$url_split = explode( '#', $url );
			$url       = $url_split[0];

			// Get rid of URL ?query=string.
			$url_query = explode( '?', $url );
			$url       = $url_query[0];

			// Set the correct URL scheme.
			$scheme = wp_parse_url( home_url(), PHP_URL_SCHEME );
			$url    = set_url_scheme( $url, $scheme );

			// Add 'www.' if it is absent and should be there.
			if ( false !== strpos( home_url(), '://www.' ) && false === strpos( $url, '://www.' ) ) {
				$url = str_replace( '://', '://www.', $url );
			}

			// Strip 'www.' if it is present and shouldn't be.
			if ( false === strpos( home_url(), '://www.' ) ) {
				$url = str_replace( '://www.', '://', $url );
			}

			// Strip 'index.php/' if we're not using path info permalinks.
			if ( isset( $wp_rewrite ) && ! $wp_rewrite->using_index_permalinks() ) {
				$url = str_replace( 'index.php/', '', $url );
			}

			if ( false !== strpos( $url, home_url() ) ) {
				// Chop off http://domain.com.
				$url = str_replace( home_url(), '', $url );
			} else {
				// Chop off /path/to/blog.
				$home_path = wp_parse_url( home_url() );
				$home_path = isset( $home_path['path'] ) ? $home_path['path'] : '';
				$url       = str_replace( $home_path, '', $url );
			}

			// Trim leading and lagging slashes.
			$url = trim( $url, '/' );

			$request = $url;
			if ( empty( $request ) && ( ! isset( $_GET ) || empty( $_GET ) ) ) { // WPCS: CSRF ok.
				return get_option( 'page_on_front' );
			}

			// Look for matches.
			$request_match = $request;

			foreach ( (array) $rewrite as $match => $query ) {
				// If the requesting file is the anchor of the match, prepend it
				// to the path info.
				if ( ! empty( $url ) && ( $url !== $request ) && ( strpos( $match, $url ) === 0 ) ) {
					$request_match = $url . '/' . $request;
				}

				if ( preg_match( "!^$match!", $request_match, $matches ) ) {
					global $wp;

					// Got a match.
					// Trim the query of everything up to the '?'.
					$query = preg_replace( '!^.+\?!', '', $query );

					// Substitute the substring matches into the query.
					$query = addslashes( WP_MatchesMapRegex::apply( $query, $matches ) );

					// Filter out non-public query vars.
					parse_str( $query, $query_vars );

					$query = array();

					foreach ( (array) $query_vars as $key => $value ) {
						if ( in_array( $key, $wp->public_query_vars, true ) ) {
							$query[ $key ] = $value;
						}
					}

					/************************************************************************
					 * ADDED: $GLOBALS['wp_post_types'] doesn't seem to have custom postypes
					 * Trying below to find posttypes in $rewrite rules
					 ************************************************************************ */

					// PostType Array.
					$custom_post_type = false;
					$post_types       = array();

					foreach ( $rewrite as $key => $value ) {
						if ( preg_match( '/post_type=([^&]+)/i', $value, $matched ) ) {
							if ( isset( $matched[1] ) && ! in_array( $matched[1], $post_types, true ) ) {
								$post_types[] = $matched[1];
							}
						}
					}

					foreach ( (array) $query_vars as $key => $value ) {
						if ( in_array( $key, $post_types, true ) ) {
							$custom_post_type = true;

							$query['post_type'] = $key;
							$query['postname']  = $value;
						}
					}

					/************************************************************************
					 * END ADD
					 ************************************************************************ */

					// Taken from class-wp.php.
					foreach ( $GLOBALS['wp_post_types'] as $post_type => $t ) {
						if ( isset( $t->query_var ) ) {
							$post_type_query_vars[ $t->query_var ] = $post_type;
						}
					}

					foreach ( $wp->public_query_vars as $wpvar ) {
						if ( isset( $wp->extra_query_vars[ $wpvar ] ) ) {
							$query[ $wpvar ] = $wp->extra_query_vars[ $wpvar ];
						} elseif ( isset( $_POST[ $wpvar ] ) ) { // WPCS: CSRF ok.
							$query[ $wpvar ] = sanitize_text_field( wp_unslash( $_POST[ $wpvar ] ) ); // WPCS: CSRF ok.
						} elseif ( isset( $_GET[ $wpvar ] ) ) { // WPCS: CSRF ok.
							$query[ $wpvar ] = sanitize_text_field( wp_unslash( $_GET[ $wpvar ] ) ); // WPCS: CSRF ok.
						} elseif ( isset( $query_vars[ $wpvar ] ) ) {
							$query[ $wpvar ] = $query_vars[ $wpvar ];
						}

						if ( ! empty( $query[ $wpvar ] ) ) {
							if ( ! is_array( $query[ $wpvar ] ) ) {
								$query[ $wpvar ] = (string) $query[ $wpvar ];
							} else {
								foreach ( $query[ $wpvar ] as $vkey => $v ) {
									if ( ! is_object( $v ) ) {
										$query[ $wpvar ][ $vkey ] = (string) $v;
									}
								}
							}

							if ( isset( $post_type_query_vars[ $wpvar ] ) ) {
								$query['post_type'] = $post_type_query_vars[ $wpvar ];
								$query['name']      = $query[ $wpvar ];
							}
						}
					}
					// Do the query.
					if ( isset( $query['pagename'] ) && ! empty( $query['pagename'] ) ) {
						$args = array(
							'name'      => $query['pagename'],
							'post_type' => 'page',
							'showposts' => 1,
						);

						if ( get_posts( $args ) === $post ) {
							return $post[0]->ID;
						}
					}

					if ( ( ! isset( $query['page'] ) || empty( $query['page'] ) ) && ( ! isset( $query['pagename'] ) || empty( $query['pagename'] ) ) ) {
						return 0;
					}

					$query = new WP_Query( $query );

					if ( ! empty( $query->posts ) && $query->is_singular ) {
						return $query->post->ID;
					} else {

						// WooCommerce override.
						if ( isset( $query->query['post_type'] ) && 'product' === $query->query['post_type'] && class_exists( 'WooCommerce' ) ) {
							return get_option( 'woocommerce_shop_page_id' );
						}

						return 0;
					}
				}
			}

			return 0;
		}

		/**
		 * Get default values.
		 */
		public function default_values() {
			if ( ! empty( $this->boxes ) && empty( $this->options_defaults ) ) {
				foreach ( $this->boxes as $key => $box ) {
					if ( empty( $box['sections'] ) ) {
						continue;
					}

					// fill the cache.
					foreach ( $box['sections'] as $sk => $section ) {
						if ( ! isset( $section['id'] ) ) {
							if ( ! is_numeric( $sk ) || ! isset( $section['title'] ) ) {
								$section['id'] = $sk;
							} else {
								$section['id'] = sanitize_text_field( $section['title'], $sk );
							}
							$this->boxes[ $key ]['sections'][ $sk ] = $section;
						}
						if ( isset( $section['fields'] ) ) {
							foreach ( $section['fields'] as $k => $field ) {

								if ( empty( $field['id'] ) && empty( $field['type'] ) ) {
									continue;
								}

								if ( in_array( $field['type'], array( 'ace_editor' ), true ) && isset( $field['options'] ) ) {
									$this->boxes[ $key ]['sections'][ $sk ]['fields'][ $k ]['args'] = $field['options'];
									unset( $this->boxes[ $key ]['sections'][ $sk ]['fields'][ $k ]['options'] );
								}

								if ( 'section' === $field['type'] && isset( $field['indent'] ) && ( true === $field['indent'] || 'true' === $field['indent'] ) ) {
									$field['class']  = isset( $field['class'] ) ? $field['class'] : '';
									$field['class'] .= 'redux-section-indent-start';

									$this->boxes[ $key ]['sections'][ $sk ]['fields'][ $k ] = $field;
								}

								$this->parent->field_default_values( $field );
							}
						}
					}
				}
			}

			if ( empty( $this->meta[ $this->post_id ] ) ) {
				$this->meta[ $this->post_id ] = $this->get_meta( $this->post_id );
			}
		}

		/**
		 * Add Meta Boxes
		 */
		public function add() {
			if ( empty( $this->boxes ) || ! is_array( $this->boxes ) ) {
				return;
			}

			foreach ( $this->boxes as $key => $box ) {
				if ( empty( $box['sections'] ) ) {
					continue;
				}

				// Save users from themselves.
				if ( isset( $box['position'] ) && ! in_array( strtolower( $box['position'] ), array( 'normal', 'advanced', 'side' ), true ) ) {
					unset( $box['position'] );
				}

				if ( isset( $box['priority'] ) && ! in_array( strtolower( $box['priority'] ), array( 'high', 'core', 'default', 'low' ), true ) ) {
					unset( $box['priority'] );
				}

				$defaults = array(
					'id'         => $key . '-' . $this->parent->args['opt_name'],
					'post_types' => array( 'page', 'post' ),
					'position'   => 'normal',
					'priority'   => 'high',
				);

				$box = wp_parse_args( $box, $defaults );
				if ( isset( $box['post_types'] ) && ! empty( $box['post_types'] ) ) {
					foreach ( $box['post_types'] as $posttype ) {
						if ( isset( $box['title'] ) ) {
							$title = $box['title'];
						} else {
							if ( isset( $box['sections'] ) && 1 === count( $box['sections'] ) && isset( $box['sections'][0]['fields'] ) && 1 === count( $box['sections'][0]['fields'] ) && isset( $box['sections'][0]['fields'][0]['title'] ) ) {

								// If only one field in this box.
								$title = $box['sections'][0]['fields'][0]['title'];
							} else {
								$title = ucfirst( $posttype ) . ' ' . __( 'Options', 'redux-framework' );
							}
						}

						$args = array(
							'position' => $box['position'],
							'sections' => $box['sections'],
							'key'      => $key,
						);

						// Override the parent args on a metabox level.
						if ( ! isset( $this->orig_args ) || empty( $this->orig_args ) ) {
							$this->orig_args = $this->parent->args;
						}

						if ( isset( $box['args'] ) ) {
							$this->parent->args = wp_parse_args( $box['args'], $this->orig_args );
						} elseif ( $this->parent->args !== $this->orig_args ) {
							$this->parent->args = $this->orig_args;
						}

						add_filter( 'postbox_classes_' . $posttype . '_redux-' . $this->parent->args['opt_name'] . '-metabox-' . $box['id'], array( $this, 'add_box_classes' ) );

						// phpcs:ignore WordPress.NamingConventions.ValidHookName
						do_action( 'redux/' . $this->parent->args['opt_name'] . '/extensions/metabox/add', $this, $box, $posttype );

						call_user_func( 'add' . '_meta' . '_box', 'redux-' . $this->parent->args['opt_name'] . '-metabox-' . $box['id'], $title, array( $this, 'generate_boxes' ), $posttype, $box['position'], $box['priority'], $args );
					}
				}
			}
		}

		/**
		 * Field Defaults.
		 *
		 * @param string $field_id ID.
		 *
		 * @return mixed|string
		 */
		private function field_default( $field_id ) {
			if ( ! isset( $this->parent->options_defaults ) ) {
				$this->parent->options_defaults = $this->parent->_default_values();
			}

			if ( ! isset( $this->parent->options ) || empty( $this->parent->options ) ) {
				$this->parent->get_options();
			}

			$this->options = $this->parent->options;

			if ( isset( $this->parent->options[ $field_id['id'] ] ) && isset( $this->parent->options_defaults[ $field_id['id'] ] ) && $this->parent->options[ $field_id['id'] ] !== $this->parent->options_defaults[ $field_id['id'] ] ) {
				return $this->parent->options[ $field_id['id'] ];
			} else {
				if ( empty( $this->options_defaults ) ) {
					$this->default_values(); // fill cache.
				}

				$data = '';
				if ( ! empty( $this->options_defaults ) ) {
					$data = isset( $this->options_defaults[ $field_id['id'] ] ) ? $this->options_defaults[ $field_id['id'] ] : '';
				}

				if ( empty( $data ) && isset( $this->parent->options_defaults[ $field_id['id'] ] ) ) {
					$data = isset( $this->parent->options_defaults[ $field_id['id'] ] ) ? $this->parent->options_defaults[ $field_id['id'] ] : '';
				}

				return $data;
			}

		}

		/**
		 * Function to get and cache the post meta.
		 *
		 * @param string $id ID.
		 *
		 * @return mixed
		 */
		private function get_meta( $id ) {
			if ( ! isset( $this->meta[ $id ] ) ) {
				$this->meta[ $id ] = array();
				$o_data            = get_post_meta( $id );

				// phpcs:ignore WordPress.NamingConventions.ValidHookName
				$o_data = apply_filters( "redux/metaboxes/{$this->parent->args['opt_name']}/get_meta", $o_data );

				if ( ! empty( $o_data ) ) {
					foreach ( $o_data as $key => $value ) {
						if ( 1 === count( $value ) ) {
							$this->meta[ $id ][ $key ] = maybe_unserialize( $value[0] );
						} else {
							$new_value = array_map( 'maybe_unserialize', $value );

							if ( is_array( $new_value ) ) {
								$this->meta[ $id ][ $key ] = $new_value[0];
							} else {
								$this->meta[ $id ][ $key ] = $new_value;
							}
						}
					}
				}

				if ( isset( $this->meta[ $id ][ $this->parent->args['opt_name'] ] ) ) {
					$data = maybe_unserialize( $this->meta[ $id ][ $this->parent->args['opt_name'] ] );

					foreach ( $data as $key => $value ) {
						$this->meta[ $id ][ $key ] = $value;
						update_post_meta( $id, $key, $value );
					}

					unset( $this->meta[ $id ][ $this->parent->args['opt_name'] ] );

					delete_post_meta( $id, $this->parent->args['opt_name'] );
				}
			}

			return $this->meta[ $id ];
		}

		/**
		 * Get values.
		 *
		 * @param mixed  $the_post Post oject/id.
		 * @param string $meta_key Meta key.
		 * @param string $def_val Def value.
		 *
		 * @return array|mixed|string
		 */
		public function get_values( $the_post, $meta_key = '', $def_val = '' ) {

			// Override these values if they differ from the admin panel defaults.  ;) .
			if ( isset( $the_post->post_type ) && in_array( $the_post->post_type, $this->post_types, true ) ) {
				if ( isset( $this->post_type_values[ $the_post->post_type ] ) ) {
					$meta = $this->post_type_fields[ $the_post->post_type ];
				} else {
					$defaults = array();
					if ( ! empty( $this->post_type_fields[ $the_post->post_type ] ) ) {
						foreach ( $this->post_type_fields[ $the_post->post_type ] as $key => $null ) {
							if ( isset( $this->options_defaults[ $key ] ) ) {
								$defaults[ $key ] = $this->options_defaults[ $key ];
							}
						}
					}

					$meta = wp_parse_args( $this->get_meta( $the_post->ID ), $defaults );
					$this->post_type_fields[ $the_post->post_type ] = $meta;
				}

				if ( ! empty( $meta_key ) ) {
					if ( ! isset( $meta[ $meta_key ] ) ) {
						$meta[ $meta_key ] = $def_val;
					}

					return $meta[ $meta_key ];
				} else {
					return $meta;
				}
			}

			return $def_val;
		}

		/**
		 * Generate Boxes.
		 *
		 * @param string $post ID.
		 * @param array  $metabox Metabox array.
		 */
		public function generate_boxes( $post, $metabox ) {
			global $wpdb;

			if ( isset( $metabox['args']['permissions'] ) && ! empty( $metabox['args']['permissions'] ) && ! Redux_Helpers::current_user_can( $metabox['args']['permissions'] ) ) {
				return;
			}

			$sections = $metabox['args']['sections'];

			wp_nonce_field( 'redux_metaboxes_meta_nonce', 'redux_metaboxes_meta_nonce' );

			wp_dequeue_script( 'json-view-js' );

			$sidebar = true;

			if ( 'side' === $metabox['args']['position'] || 1 === count( $sections ) || ( isset( $metabox['args']['sidebar'] ) && false === $metabox['args']['sidebar'] ) ) {
				$sidebar = false; // Show the section dividers or not.
			}

			?>
			<input
					type="hidden"
					id="currentSection"
					name="<?php echo esc_attr( $this->parent->args['opt_name'] ); ?>[redux-metabox-section]" value=""/>
			<div
					data-index="<?php echo esc_attr( $metabox['args']['key'] ); ?>"
					data-opt-name="<?php echo esc_attr( $this->parent->args['opt_name'] ); ?>"
					class="redux-container<?php echo esc_attr(( $sidebar ) ? ' redux-has-sections' : ' redux-no-sections'); ?> redux-box-<?php echo esc_attr( $metabox['args']['position'] ); ?>">
				<div class="redux-notices">
					<?php if ( 'side' !== $metabox['args']['position'] || ( isset( $metabox['args']['sidebar'] ) && false !== $metabox['args']['sidebar'] ) ) { ?>
						<div class="saved_notice admin-notice notice-blue" style="display:none;">
							<?php // phpcs:ignore WordPress.NamingConventions.ValidHookName ?>
							<strong><?php echo esc_html( apply_filters( "redux-imported-text-{$this->parent->args['opt_name']}", esc_html__( 'Settings Imported!', 'redux-framework' ) ) ); ?></strong>
						</div>
						<div class="redux-save-warn notice-yellow">
							<?php // phpcs:ignore WordPress.NamingConventions.ValidHookName ?>
							<strong><?php echo esc_html( apply_filters( "redux-changed-text-{$this->parent->args['opt_name']}", esc_html__( 'Settings have changed, you should save them!', 'redux-framework' ) ) ); ?></strong>
						</div>
					<?php } ?>
					<div class="redux-field-errors notice-red">
						<strong> <span></span> <?php echo esc_html__( 'error(s) were found!', 'redux-framework' ); ?>
						</strong>
					</div>
					<div class="redux-field-warnings notice-yellow">
						<strong> <span></span> <?php echo esc_html__( 'warning(s) were found!', 'redux-framework' ); ?>
						</strong>
					</div>
				</div>
				<?php
				echo '<a href="javascript:void(0);" class="expand_options hide" style="display:none;">' . esc_html__( 'Expand', 'redux-framework' ) . '</a>';
				if ( $sidebar ) {
					?>
					<div class="redux-sidebar">
						<ul class="redux-group-menu">
							<?php
							foreach ( $sections as $s_key => $section ) {
								if ( isset( $section['permissions'] ) && ! empty( $section['permissions'] ) && ! Redux_Helpers::current_user_can( $section['permissions'] ) ) {
									continue;
								}

								echo $this->parent->section_menu( $s_key, $section, '_box_' . $metabox['id'], $sections ); // WPCS: XSS ok.
							}
							?>
						</ul>
					</div>
				<?php } ?>

				<div class="redux-main">
					<?php
					$update_localize = false;

					foreach ( $sections as $s_key => $section ) {
						if ( isset( $section['permissions'] ) && ! empty( $section['permissions'] ) && ! Redux_Helpers::current_user_can( $section['permissions'] ) ) {
							continue;
						}
						if ( isset( $section['fields'] ) && ! empty( $section['fields'] ) ) {
							if ( isset( $section['args'] ) ) {
								$this->parent->args = wp_parse_args( $section['args'], $this->orig_args );
							} elseif ( $this->parent->args !== $this->orig_args ) {
								$this->parent->args = $this->orig_args;
							}

							$hide             = $sidebar ? '' : ' display-group';
							$section['class'] = isset( $section['class'] ) ? ' ' . $section['class'] : '';
							echo '<div id="' . esc_attr( $s_key ) . '_box_' . esc_attr( $metabox['id'] ) . '_section_group" class="redux-group-tab' . esc_attr( $section['class'] ) . ' redux_metabox_panel' . esc_attr( $hide ) . '">';

							if ( isset( $section['title'] ) && ! empty( $section['title'] ) ) {
								echo '<h3 class="redux-section-title">' . wp_kses_post( $section['title'] ) . '</h3>';
							}

							if ( isset( $section['desc'] ) && ! empty( $section['desc'] ) ) {
								echo '<div class="redux-section-desc">' . wp_kses_post( $section['desc'] ) . '</div>';
							}

							echo '<table class="form-table"><tbody>';
							foreach ( $section['fields'] as $f_key => $field ) {
								if ( isset( $field['permissions'] ) && ! empty( $field['permissions'] ) && ! Redux_Helpers::current_user_can( $field['permissions'] ) ) {
									continue;
								}
								$field['name'] = $this->parent->args['opt_name'] . '[' . $field['id'] . ']';

								$is_hidden = false;
								$ex_style  = '';
								if ( isset( $field['hidden'] ) && $field['hidden'] ) {
									$is_hidden = true;
									$ex_style  = ' style="border-bottom: none;"';
								}

								echo '<tr valign="top"' . $ex_style . '>'; // WPCS: XSS ok.

								$th = $this->parent->get_header_html( $field );

								if ( $is_hidden ) {
									$str_pos = strpos( $th, 'redux_field_th' );

									if ( $str_pos > - 1 ) {
										$th = str_replace( 'redux_field_th', 'redux_field_th hide', $th );
									}
								}

								if ( $sidebar ) {
									if ( ! ( isset( $metabox['args']['sections'] ) && 1 === count( $metabox['args']['sections'] ) && isset( $metabox['args']['sections'][0]['fields'] ) && 1 === count( $metabox['args']['sections'][0]['fields'] ) ) && isset( $field['title'] ) ) {
										echo '<th scope="row">';
										if ( ! empty( $th ) ) {
											echo $th; // WPCS: XSS ok.
										}
										echo '</th>';
										echo '<td>';
									}
								} else {
									echo '<td>' . $th . ''; // WPCS: XSS ok.
								}

								if ( 'section' === $field['type'] && ( 'true' === $field['indent'] || true === $field['indent'] ) ) {
									$field['class']  = isset( $field['class'] ) ? $field['class'] : '';
									$field['class'] .= 'redux-section-indent-start';
								}

								if ( ! isset( $this->meta[ $this->post_id ][ $field['id'] ] ) ) {
									$this->meta[ $this->post_id ][ $field['id'] ] = '';
								}

								$this->parent->render_class->field_input( $field, $this->meta[ $this->post_id ][ $field['id'] ], true );
								echo '</td></tr>';
							}
							echo '</tbody></table>';
							echo '</div>';
						}
					}
					?>
				</div>
				<div class="clear"></div>
			</div>
			<?php
		}

		/**
		 * Save meta boxes
		 * Runs when a post is saved and does an action which the write panel save scripts can hook into.
		 *
		 * @access public
		 *
		 * @param mixed $post_id Post ID.
		 * @param mixed $post Post.
		 *
		 * @return mixed
		 */
		public function meta_boxes_save( $post_id, $post ) {

			if ( isset( $_POST['vc_inline'] ) && sanitize_text_field( wp_unslash( $_POST['vc_inline'] ) ) ) { // WPCS: CSRF ok.
				return $post_id;
			}

			if ( isset( $_POST['post_ID'] ) && strval( $post_id ) !== $_POST['post_ID'] ) { // WPCS: CSRF ok.
				return $post_id;
			}

			// Check if our nonce is set.
			if ( ! isset( $_POST['redux_metaboxes_meta_nonce'] ) || ! isset( $_POST[ $this->parent->args['opt_name'] ] ) ) {
				return $post_id;
			}

			$meta = $this->get_meta( $post_id );

			// Verify that the nonce is valid.
			// Validate fields (if needed).
			if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['redux_metaboxes_meta_nonce'] ) ), 'redux_metaboxes_meta_nonce' ) ) {
				return $post_id;
			}

			// If this is an autosave, our form has not been submitted, so we don't want to do anything.
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return $post_id;
			}

			// Check the user's permissions, even allowing custom capabilities.
			$obj = get_post_type_object( $post->post_type );
			if ( ! current_user_can( $obj->cap->edit_post, $post_id ) ) {
				return $post_id;
			}

			// Import.
			if ( isset( $_POST[ $this->parent->args['opt_name'] ]['import_code'] ) && ! empty( $_POST[ $this->parent->args['opt_name'] ]['import_code'] ) ) {
				$import = json_decode( sanitize_text_field( wp_unslash( $_POST[ $this->parent->args['opt_name'] ]['import_code'] ) ), true );
				unset( $_POST[ $this->parent->args['opt_name'] ]['import_code'] );

				foreach ( Redux_Helpers::sanitize_array( wp_unslash( $_POST[ $this->parent->args['opt_name'] ] ) ) as $key => $value ) { // WPCS: sanitization ok.
					if ( ! isset( $import[ $key ] ) ) {
						$import[ $key ] = $value;
					}
				}

				$_POST[ $this->parent->args['opt_name'] ] = $import;
			}

			$to_save    = array();
			$to_compare = array();
			$to_delete  = array();
			$dont_save  = true;

			if ( isset( $this->parent->args['metaboxes_save_defaults'] ) && $this->parent->args['metaboxes_save_defaults'] ) {
				$dont_save = false;
			}

			foreach ( Redux_Helpers::sanitize_array( wp_unslash( $_POST[ $this->parent->args['opt_name'] ] ) ) as $key => $value ) { // WPCS: sanitization ok.

				// Have to remove the escaping for array comparison.
				if ( is_array( $value ) ) {
					foreach ( $value as $k => $v ) {
						if ( ! is_array( $v ) ) {
							$value[ $k ] = wp_unslash( $v );
						}
					}
				}

				$save = true;

				// parent_options.
				if ( ! $dont_save && isset( $this->options_defaults[ $key ] ) && $value === $this->options_defaults[ $key ] ) {
					$save = false;
				}

				if ( $save && isset( $this->parent_options[ $key ] ) && $this->parent_options[ $key ] !== $value ) {
					$save = false;
				}

				if ( $save ) {
					$to_save[ $key ]    = $value;
					$to_compare[ $key ] = isset( $this->parent->options[ $key ] ) ? $this->parent->options[ $key ] : '';
				} else {
					$to_delete[ $key ] = $value;
				}
			}

			// phpcs:ignore WordPress.NamingConventions.ValidHookName
			$to_save = apply_filters( 'redux/metaboxes/save/before_validate', $to_save, $to_compare, $this->sections );

			$validate = $this->parent->_validate_values( $to_save, $to_compare, $this->sections );

			// Validate fields (if needed).
			foreach ( $to_save as $key => $value ) {
				if ( isset( $validate[ $key ] ) && $to_save[ $key ] !== $validate[ $key ] ) {
					if ( isset( $this->parent->options[ $key ] ) && $validate[ $key ] === $this->parent->options[ $key ] ) {
						unset( $to_save[ $key ] );
					} else {
						$to_save[ $key ] = $validate[ $key ];
					}
				}
			}

			if ( ! empty( $this->parent->errors ) || ! empty( $this->parent->warnings ) ) {
				$this->parent->transients['notices'] = ( isset( $this->parent->transients['notices'] ) && is_array( $this->parent->transients['notices'] ) ) ? $this->parent->transients['notices'] : array();

				if ( ! isset( $this->parent->transients['notices']['errors'] ) || $this->parent->transients['notices']['errors'] !== $this->parent->errors ) {
					$this->parent->transients['notices']['errors'] = $this->parent->errors;
					$update_transients                             = true;
				}

				if ( ! isset( $this->parent->transients['notices']['warnings'] ) || $this->parent->transients['notices']['warnings'] !== $this->parent->warnings ) {
					$this->parent->transients['notices']['warnings'] = $this->parent->warnings;
					$update_transients                               = true;
				}

				if ( isset( $update_transients ) ) {
					$this->parent->transients['notices']['override'] = 1;
					set_transient( $this->parent->args['opt_name'] . '-transients-metaboxes', $this->parent->transients );
				}
			}

			if ( isset( $_POST['post_type'] ) ) {
				$check = $this->post_type_fields[ sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) ];
			}

			// phpcs:ignore WordPress.NamingConventions.ValidHookName
			$to_save = apply_filters( 'redux/metaboxes/save', $to_save, $to_compare, $this->sections );

			foreach ( $to_save as $key => $value ) {
				$prev_value = isset( $this->meta[ $post_id ][ $key ] ) ? $this->meta[ $post_id ][ $key ] : '';

				if ( isset( $check[ $key ] ) ) {
					unset( $check[ $key ] );
				}

				update_post_meta( $post_id, $key, $value, $prev_value );
			}

			foreach ( $to_delete as $key => $value ) {
				if ( isset( $check[ $key ] ) ) {
					unset( $check[ $key ] );
				}

				$prev_value = isset( $this->meta[ $post_id ][ $key ] ) ? $this->meta[ $post_id ][ $key ] : '';
				delete_post_meta( $post_id, $key, $prev_value );
			}

			foreach ( $check as $key => $value ) {
				delete_post_meta( $post_id, $key );
			}
		}

		/**
		 * Some functions, like the term recount, require the visibility to be set prior. Lets save that here.
		 *
		 * @access public
		 *
		 * @param mixed $post_id Post ID.
		 *
		 * @return void
		 */
		public function pre_post_update( $post_id ) {
			if ( isset( $_POST['_visibility'] ) ) { // WPCS: CSRF ok.
				update_post_meta( $post_id, '_visibility', sanitize_text_field( wp_unslash( $_POST['_visibility'] ) ) ); // WPCS: CSRF ok.
			}

			if ( isset( $_POST['_stock_status'] ) ) { // WPCS: CSRF ok.
				update_post_meta( $post_id, '_stock_status', sanitize_text_field( wp_unslash( $_POST['_stock_status'] ) ) ); // WPCS: CSRF ok.
			}
		}

		/**
		 * Show any stored error messages.
		 *
		 * @access public
		 * @return void
		 */
		public function meta_boxes_show_errors() {
			if ( isset( $this->notices['errors'] ) && ! empty( $this->notices['errors'] ) ) {
				echo '<div id="redux_metaboxes_errors" class="error fade">';
				echo '<p><strong><span></span> ' . count( $this->notices['errors'] ) . ' ' . esc_html__( 'error(s) were found!', 'redux-framework' ) . '</strong></p>';
				echo '</div>';
			}

			if ( isset( $this->notices['warnings'] ) && ! empty( $this->notices['warnings'] ) ) {
				echo '<div id="redux_metaboxes_warnings" class="error fade" style="border-left-color: #E8E20C;">';
				echo '<p><strong><span></span> ' . count( $this->notices['warnings'] ) . ' ' . esc_html__( 'warnings(s) were found!', 'redux-framework' ) . '</strong></p>';
				echo '</div>';
			}
		}
	}
}

if ( ! function_exists( 'redux_metaboxes_loop_start' ) ) {
	/**
	 * Start loop.
	 *
	 * @param string $opt_name Panel opt_name.
	 * @param array  $the_post Post object.
	 */
	function redux_metaboxes_loop_start( $opt_name, $the_post = array() ) {
		$redux     = ReduxFrameworkInstances::get_instance( $opt_name );
		$metaboxes = $redux->extensions['metaboxes'];

		$metaboxes->loop_start( $the_post );
	}
}

if ( ! function_exists( 'redux_metaboxes_loop_end' ) ) {
	/**
	 * End loop.
	 *
	 * @param string $opt_name Panel opt_name.
	 * @param array  $the_post Post object.
	 */
	function redux_metaboxes_loop_end( $opt_name, $the_post = array() ) {
		$redux     = ReduxFrameworkInstances::get_instance( $opt_name );
		$metaboxes = $redux->extensions['metaboxes'];

		$metaboxes->loop_end();
	}
}

if ( ! function_exists( 'redux_post_meta' ) ) {
	/**
	 * Retrieve post meta values/settings.
	 *
	 * @param string $opt_name Panel opt_name.
	 * @param array  $the_post Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $def_val Default value.
	 *
	 * @return string|void
	 */
	function redux_post_meta( $opt_name = '', $the_post = array(), $meta_key = '', $def_val = '' ) {
		global $post;

		if ( empty( $opt_name ) ) {
			return;
		}

		$redux     = ReduxFrameworkInstances::get_instance( $opt_name );
		$metaboxes = $redux->extensions['metaboxes'];

		if ( isset( $the_post ) && is_array( $the_post ) ) {
			$the_post = $post;
		} elseif ( ! isset( $the_post ) || 0 === $the_post ) {
			return $def_val;
		} elseif ( is_numeric( $the_post ) ) {
			$the_post = get_post( $the_post );
		} elseif ( ! is_object( $the_post ) ) {
			$the_post = $post;
		}

		return $metaboxes->get_values( $the_post, $meta_key, $def_val );
	}
}
