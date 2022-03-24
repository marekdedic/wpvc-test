<?php

declare( strict_types=1 );

namespace SkautisIntegration\Modules\Visibility\Frontend;

use SkautisIntegration\Rules\Rules_Manager;
use SkautisIntegration\Auth\Skautis_Login;
use SkautisIntegration\Auth\WP_Login_Logout;
use SkautisIntegration\Utils\Helpers;

final class Frontend {

	private $post_types;
	private $rules_manager;
	private $skautis_login;
	private $wp_login_logout;

	public function __construct( array $postTypes, Rules_Manager $rulesManager, Skautis_Login $skautisLogin, WP_Login_Logout $wpLoginLogout ) {
		$this->post_types      = $postTypes;
		$this->rules_manager   = $rulesManager;
		$this->skautis_login   = $skautisLogin;
		$this->wp_login_logout = $wpLoginLogout;
	}

	public function init_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'posts_results', array( $this, 'filter_posts' ), 10, 2 );
	}

	private function get_login_form( bool $forceLogoutFromSkautis = false ): string {
		$loginUrlArgs = add_query_arg( 'noWpLogin', true, Helpers::get_current_url() );
		if ( $forceLogoutFromSkautis ) {
			$loginUrlArgs = add_query_arg( 'logoutFromSkautis', true, $loginUrlArgs );
		}

		return '
		<div class="wp-core-ui">
			<p style="margin-bottom: 0.3em;">
				<a class="button button-primary button-hero button-skautis"
				   href="' . $this->wp_login_logout->get_login_url( $loginUrlArgs ) . '">' . __( 'Log in with skautIS', 'skautis-integration' ) . '</a>
			</p>
		</div>
		<br/>
		';
	}

	private function get_login_required_message(): string {
		return '<p>' . __( 'To view this content you must be logged in skautIS', 'skautis-integration' ) . '</p>';
	}

	private function get_unauthorized_message(): string {
		return '<p>' . __( 'You do not have permission to access this content', 'skautis-integration' ) . '</p>';
	}

	private function get_posts_hierarchy_tree_with_rules( int $postId, $postType ): array {
		$ancestors = get_ancestors( $postId, $postType, 'post_type' );
		$ancestors = array_map(
			function ( $ancestorPostId ) {
				return array(
					'id'              => $ancestorPostId,
					'rules'           => (array) get_post_meta( $ancestorPostId, SKAUTISINTEGRATION_NAME . '_rules', true ),
					'includeChildren' => get_post_meta( $ancestorPostId, SKAUTISINTEGRATION_NAME . '_rules_includeChildren', true ),
					'visibilityMode'  => get_post_meta( $ancestorPostId, SKAUTISINTEGRATION_NAME . '_rules_visibilityMode', true ),
				);
			},
			$ancestors
		);

		return array_reverse( $ancestors );
	}

	private function get_rules_from_parent_posts_with_impact_by_child_post_id( int $childPostId, $postType ): array {
		$ancestors = $this->get_posts_hierarchy_tree_with_rules( $childPostId, $postType );

		$ancestors = array_filter(
			$ancestors,
			function ( $ancestor ) {
				if ( ! empty( $ancestor['rules'] ) && isset( $ancestor['rules'][0][ SKAUTISINTEGRATION_NAME . '_rules' ] ) ) {
					if ( '1' === $ancestor['includeChildren'] ) {
						return true;
					}
				}

				return false;
			}
		);

		return array_values( $ancestors );
	}

	private function hide_content_excerpt_comments( int $postId, string $newContent = '', string $newExcerpt = '' ) {
		add_filter(
			'the_content',
			function ( string $content = '' ) use ( $postId, $newContent ) {
				if ( get_the_ID() === $postId ) {
					return $newContent;
				}

				return $content;
			}
		);

		add_filter(
			'the_excerpt',
			function ( string $excerpt = '' ) use ( $postId, $newExcerpt ) {
				if ( get_the_ID() === $postId ) {
					return $newExcerpt;
				}

				return $excerpt;
			}
		);

		add_action(
			'pre_get_comments',
			function ( \WP_Comment_Query $wpCommentQuery ) use ( $postId ) {
				if ( $wpCommentQuery->query_vars['post_id'] === $postId ) {
					if ( ! isset( $wpCommentQuery->query_vars['post__not_in'] ) || empty( $wpCommentQuery->query_vars['post__not_in'] ) ) {
						$wpCommentQuery->query_vars['post__not_in'] = array();
					} elseif ( ! is_array( $wpCommentQuery->query_vars['post__not_in'] ) ) {
						$wpCommentQuery->query_vars['post__not_in'] = array( $wpCommentQuery->query_vars['post__not_in'] );
					}
					$wpCommentQuery->query_vars['post__not_in'][] = $postId;
				}
			}
		);
	}

	private function process_rules_and_hide_posts( bool $userIsLoggedInSkautis, array $rule, array &$posts, int $postKey, \WP_Query $wpQuery, string $postType, &$postsWereFiltered = false ) {
		if ( ! empty( $rules ) && isset( $rules[0][ SKAUTISINTEGRATION_NAME . '_rules' ] ) ) {
			if ( ! $userIsLoggedInSkautis ||
				! $this->rules_manager->check_if_user_passed_rules( $rules ) ) {
				unset( $posts[ $postKey ] );
				unset( $wpQuery->posts[ $postKey ] );
				if ( $wpQuery->found_posts > 0 ) {
					$wpQuery->found_posts --;
				}
				$postsWereFiltered = true;
			}
		}
	}

	private function process_rules_and_hide_content( bool $userIsLoggedInSkautis, array $rules, int $postId ) {
		if ( ! empty( $rules ) && isset( $rules[0][ SKAUTISINTEGRATION_NAME . '_rules' ] ) ) {
			if ( ! $userIsLoggedInSkautis ) {
				$this->hide_content_excerpt_comments( $postId, $this->get_login_required_message() . $this->get_login_form(), $this->get_login_required_message() );
			} elseif ( ! $this->rules_manager->check_if_user_passed_rules( $rules ) ) {
				$this->hide_content_excerpt_comments( $postId, $this->get_unauthorized_message() . $this->get_login_form( true ), $this->get_unauthorized_message() );
			}
		}
	}

	public function enqueue_styles() {
		wp_enqueue_style( 'buttons' );
		wp_enqueue_style( SKAUTISINTEGRATION_NAME, SKAUTISINTEGRATION_URL . 'src/frontend/public/css/skautis-frontend.css', array(), SKAUTISINTEGRATION_VERSION, 'all' );
	}

	public function get_parent_posts_with_rules( int $childPostId, string $childPostType ): array {
		$result = array();

		$parentPostsWithRules = $this->get_rules_from_parent_posts_with_impact_by_child_post_id( $childPostId, $childPostType );

		foreach ( $parentPostsWithRules as $parentPostWithRules ) {
			$result[ $parentPostWithRules['id'] ] = array(
				'parentPostTitle' => get_the_title( $parentPostWithRules['id'] ),
				'rules'           => array(),
			);

			foreach ( $parentPostWithRules['rules'] as $rule ) {
				$result[ $parentPostWithRules['id'] ]['rules'][ $rule['skautis-integration_rules'] ] = get_the_title( $rule['skautis-integration_rules'] );
			}
		}

		return $result;
	}

	public function filter_posts( array $posts, \WP_Query $wpQuery ): array {
		if ( empty( $posts ) ) {
			return $posts;
		}

		$userIsLoggedInSkautis = $this->skautis_login->is_user_logged_in_skautis();

		$postsWereFiltered = false;

		foreach ( $wpQuery->posts as $key => $post ) {
			if ( ! is_a( $post, 'WP_Post' ) ) {
				$wpPost = get_post( $post );
			} else {
				$wpPost = $post;
			}

			if ( in_array( $wpPost->post_type, $this->post_types, true ) ) {
				if ( ! current_user_can( 'edit_' . $wpPost->post_type . 's' ) ) {
					$rulesGroups = array();

					if ( $wpPost->post_parent > 0 ) {
						$rulesGroups = $this->get_rules_from_parent_posts_with_impact_by_child_post_id( $wpPost->ID, $wpPost->post_type );
					}

					$currentPostRules = (array) get_post_meta( $wpPost->ID, SKAUTISINTEGRATION_NAME . '_rules', true );
					if ( ! empty( $currentPostRules ) ) {
						$currentPostRule = array(
							'id'              => $wpPost->ID,
							'rules'           => $currentPostRules,
							'includeChildren' => get_post_meta( $wpPost->ID, SKAUTISINTEGRATION_NAME . '_rules_includeChildren', true ),
							'visibilityMode'  => get_post_meta( $wpPost->ID, SKAUTISINTEGRATION_NAME . '_rules_visibilityMode', true ),
						);
						$rulesGroups[]   = $currentPostRule;
					}

					foreach ( $rulesGroups as $rulesGroup ) {
						if ( 'content' === $rulesGroup['visibilityMode'] ) {
							$this->process_rules_and_hide_content( $userIsLoggedInSkautis, $rulesGroup['rules'], $wpPost->ID );
						} else {
							$this->process_rules_and_hide_posts( $userIsLoggedInSkautis, $rulesGroup['rules'], $posts, $key, $wpQuery, $wpPost->post_type, $postsWereFiltered );
						}
					}
				}
			}
		}

		if ( $postsWereFiltered ) {
			$wpQuery->posts = array_values( $wpQuery->posts );
			$posts          = array_values( $posts );
		}

		return $posts;
	}

}