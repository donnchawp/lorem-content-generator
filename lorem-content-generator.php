<?php
/**
 * Plugin Name: Lorem Content Generator
 * Plugin URI: https://odd.blog/
 * Description: Generates lorem posts and comments with Lorem Ipsum content
 * Version: 1.0.0
 * Author: Donncha O Caoimh
 * Author URI: https://odd.blog/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lorem-content-generator
 * Domain Path: /languages
 *
 * @package Lorem_Content_Generator
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Main plugin class
 */
class Lorem_Content_Generator {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
	}

	/**
	 * Add menu item
	 */
	public function add_admin_menu() {
		add_management_page(
			__( 'Lorem Content Generator', 'lorem-content-generator' ),
			__( 'Lorem Content Generator', 'lorem-content-generator' ),
			'manage_options',
			'lorem-content-generator',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * Handle form submission
	 */
	public function handle_form_submission() {
		if (
			isset( $_POST['generate_content'] ) &&
			isset( $_POST['lorem_content_generator_nonce'] ) &&
			wp_verify_nonce( sanitize_key( $_POST['lorem_content_generator_nonce'] ), 'lorem_content_generator_action' ) &&
			current_user_can( 'manage_options' )
		) {
			$num_posts    = isset( $_POST['num_posts'] ) ? absint( $_POST['num_posts'] ) : 0;
			$num_comments = isset( $_POST['num_comments'] ) ? absint( $_POST['num_comments'] ) : 0;
			$category_id  = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
			$tags         = isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '';

			$this->generate_content( $num_posts, $num_comments, $category_id, $tags );
			wp_safe_redirect( add_query_arg( 'generated', 'true', wp_get_referer() ) );
			exit;
		}

		if (
			isset( $_POST['action'] ) &&
			'delete_test_comments' === $_POST['action'] &&
			isset( $_POST['delete_test_comments_nonce'] ) &&
			wp_verify_nonce( sanitize_key( $_POST['delete_test_comments_nonce'] ), 'delete_test_comments_action' ) &&
			current_user_can( 'manage_options' )
		) {
			$this->delete_test_comments();
			wp_safe_redirect( add_query_arg( 'deleted', 'true', wp_get_referer() ) );
			exit;
		}
	}

	/**
	 * Admin page
	 */
	public function admin_page() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Show notice if content was generated.
		if ( isset( $_GET['generated'] ) && 'true' === $_GET['generated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error(
				'lorem_content_generator',
				'content_generated',
				__( 'Content has been generated successfully.', 'lorem-content-generator' ),
				'updated'
			);
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors( 'lorem_content_generator' ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=lorem-content-generator' ) ); ?>">
				<?php
				wp_nonce_field( 'lorem_content_generator_action', 'lorem_content_generator_nonce' );
				?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="num_posts"><?php esc_html_e( 'Number of Posts:', 'lorem-content-generator' ); ?></label></th>
						<td><input type="number" id="num_posts" name="num_posts" min="1" max="100" value="10"></td>
					</tr>
					<tr>
						<th scope="row"><label for="num_comments"><?php esc_html_e( 'Number of Comments per Post:', 'lorem-content-generator' ); ?></label></th>
						<td><input type="number" id="num_comments" name="num_comments" min="0" max="20" value="5"></td>
					</tr>
					<tr>
						<th scope="row"><label for="category"><?php esc_html_e( 'Category:', 'lorem-content-generator' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_categories(
								array(
									'show_option_none' => __( 'Select Category', 'lorem-content-generator' ),
									'name'             => 'category',
									'id'               => 'category',
									'hide_empty'       => 0,
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="tags"><?php esc_html_e( 'Tags (comma-separated):', 'lorem-content-generator' ); ?></label></th>
						<td><input type="text" id="tags" name="tags" class="regular-text"></td>
					</tr>
				</table>
				<?php submit_button( __( 'Generate Content', 'lorem-content-generator' ), 'primary', 'generate_content' ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=lorem-content-generator' ) ); ?>">
				<?php wp_nonce_field( 'delete_test_comments_action', 'delete_test_comments_nonce' ); ?>
				<input type="hidden" name="action" value="delete_test_comments">
				<input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Delete All Test Comments', 'lorem-content-generator' ); ?>">
			</form>
		</div>
		<?php
	}

	/**
	 * Generate content
	 *
	 * @param int $num_posts Number of posts to generate
	 * @param int $num_comments Number of comments per post
	 * @param int $category_id Category ID
	 * @param string $tags Comma-separated tags
	 */
	private function generate_content($num_posts, $num_comments, $category_id, $tags) {
		$tag_array = array_filter(array_map('trim', explode(',', $tags)));

		for ($i = 0; $i < $num_posts; $i++) {
			$post_id = wp_insert_post(array(
				'post_title'   => $this->generate_lorem_ipsum(5, 8),
				'post_content' => $this->generate_lorem_ipsum(100, 500),
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_date'    => $this->generate_past_date(),
				'post_category'=> $category_id ? array($category_id) : array(),
			));

			if (!empty($tag_array)) {
				wp_set_post_tags($post_id, $tag_array);
			}

			for ($j = 0; $j < $num_comments; $j++) {
				$comment_content = $this->generate_lorem_ipsum(20, 100);

				wp_insert_comment(array(
					'comment_post_ID'      => $post_id,
					'comment_author'       => 'Test User',
					'comment_author_url'   => $this->generate_random_url(),
					'comment_author_email' => 'testuser@example.com',
					'comment_content'      => $comment_content,
					'comment_date'         => $this->generate_past_date($post_id)
				));
			}
		}
	}

	/**
	 * Generate Lorem Ipsum text
	 *
	 * @param int $min_words Minimum number of words
	 * @param int $max_words Maximum number of words
	 * @return string Generated text
	 */
	private function generate_lorem_ipsum($min_words, $max_words) {
		$words = array(
			'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit',
			'sed', 'do', 'eiusmod', 'tempor', 'incididunt', 'ut', 'labore', 'et', 'dolore',
			'magna', 'aliqua', 'enim', 'ad', 'minim', 'veniam', 'quis', 'nostrud', 'exercitation',
			'ullamco', 'laboris', 'nisi', 'aliquip', 'ex', 'ea', 'commodo', 'consequat'
		);
		
		$num_words = wp_rand($min_words, $max_words);
		$text = '';
		
		for ($i = 0; $i < $num_words; $i++) {
			$text .= $words[array_rand($words)] . ' ';
		}
		
		return ucfirst(trim($text)) . '.';
	}

	/**
	 * Generate a random past date
	 *
	 * @param int|null $post_id Post ID (optional)
	 * @return string Formatted date
	 */
	private function generate_past_date( $post_id = null ) {
		if ( $post_id ) {
			$start_date = get_the_date( 'U', $post_id );
			$end_date   = time();
		} else {
			$start_date = strtotime( '-10 years' );
			$end_date   = time();
		}
		$random_date = wp_rand( $start_date, $end_date );
		return gmdate( 'Y-m-d H:i:s', $random_date );
	}

	/**
	 * Generate a random URL
	 *
	 * @return string Random URL
	 */
	private function generate_random_url() {
		$paths = array(
			'about', 'contact', 'services', 'products', 'blog', 'faq',
			'terms', 'privacy', 'portfolio', 'team', 'careers', 'support'
		);

		$random_path = $paths[array_rand($paths)];
		$random_query = wp_rand(0, 1) ? '?id=' . wp_rand(1, 100) : '';

		return 'https://example.com/' . $random_path . $random_query;
	}

	/**
	 * Delete test comments
	 */
	private function delete_test_comments() {
		$comments = get_comments(array(
			'author_email' => 'testuser@example.com',
		));

		foreach ($comments as $comment) {
			wp_delete_comment($comment->comment_ID);
		}
	}
}

// Initialize the plugin
new Lorem_Content_Generator();
