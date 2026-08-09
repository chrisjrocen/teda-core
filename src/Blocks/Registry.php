<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Blocks;

use Teda_Core\Support\Bootable;

/**
 * Discovers and registers TEDA dynamic blocks (D4): scans blocks/&ast;/block.json,
 * registers each with register_block_type() and a PHP render_callback resolved by
 * convention (teda/stat-band -> Teda_Core\Blocks\Stat_Band). No build step, no
 * node_modules, no compiled JS — the one editor script is hand-written ES5.
 */
final class Registry implements Bootable {

	/**
	 * Directory holding one subdirectory per block.
	 */
	private string $blocks_dir;

	public function __construct() {
		$this->blocks_dir = TEDA_CORE_PATH . 'blocks';
	}

	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_filter( 'block_categories_all', array( $this, 'register_category' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );

		// Bump the block render cache namespace whenever any post is saved, so
		// archives-in-blocks refresh even before their transients expire.
		add_action( 'save_post', array( $this, 'flush_render_cache' ) );
	}

	/**
	 * Register every block found under blocks/.
	 */
	public function register_blocks(): void {
		foreach ( $this->block_dirs() as $dir ) {
			$meta = $this->read_meta( $dir );
			if ( null === $meta || empty( $meta['name'] ) ) {
				continue;
			}

			$args     = array();
			$renderer = $this->resolve_renderer( (string) $meta['name'] );
			if ( null !== $renderer ) {
				$args['render_callback'] = array( $renderer, 'render' );
			}

			register_block_type( $dir, $args );
		}
	}

	/**
	 * Prepend the TEDA block category so TEDA blocks group together above core.
	 *
	 * @param array<int, array<string, mixed>> $categories Existing categories.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_category( array $categories ): array {
		array_unshift(
			$categories,
			array(
				'slug'  => 'teda',
				'title' => __( 'TEDA', 'teda-core' ),
				'icon'  => null,
			)
		);
		return $categories;
	}

	/**
	 * Enqueue the single hand-written editor script and hand it each block's
	 * name + attribute schema, so it can register a ServerSideRender preview and
	 * auto-generate InspectorControls — no per-block JS, no JSX, no build.
	 */
	public function enqueue_editor_assets(): void {
		$handle = 'teda-blocks-editor';
		wp_enqueue_script(
			$handle,
			TEDA_CORE_URL . 'blocks/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			TEDA_CORE_VERSION,
			true
		);
		wp_localize_script( $handle, 'tedaBlocks', $this->editor_data() );
	}

	/**
	 * Invalidate the block render cache namespace on save.
	 *
	 * @param int $post_id Saved post ID.
	 */
	public function flush_render_cache( int $post_id ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		wp_cache_set_last_changed( 'posts' );
	}

	/* --------------------------------------------------------------------- */
	/* Internals                                                             */
	/* --------------------------------------------------------------------- */

	/**
	 * @return array<int, string> Absolute block directory paths.
	 */
	private function block_dirs(): array {
		if ( ! is_dir( $this->blocks_dir ) ) {
			return array();
		}
		$found = glob( $this->blocks_dir . '/*/block.json' );
		if ( false === $found ) {
			return array();
		}
		return array_map( 'dirname', $found );
	}

	/**
	 * @return array<string, mixed>|null Decoded block.json, or null.
	 */
	private function read_meta( string $dir ): ?array {
		$file = $dir . '/block.json';
		if ( ! is_readable( $file ) ) {
			return null;
		}
		$data = json_decode( (string) file_get_contents( $file ), true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Resolve a renderer instance by convention: teda/stat-band -> Stat_Band.
	 */
	private function resolve_renderer( string $block_name ): ?Block_Renderer {
		$parts = explode( '/', $block_name );
		$slug  = end( $parts );
		$class = __NAMESPACE__ . '\\' . implode( '_', array_map( 'ucfirst', explode( '-', $slug ) ) );

		if ( class_exists( $class ) && is_subclass_of( $class, Block_Renderer::class ) ) {
			/** @var Block_Renderer $instance */
			$instance = new $class();
			return $instance;
		}
		return null;
	}

	/**
	 * Block metadata for the editor: name, title, icon and attribute schema.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function editor_data(): array {
		$data = array();
		foreach ( $this->block_dirs() as $dir ) {
			$meta = $this->read_meta( $dir );
			if ( null === $meta || empty( $meta['name'] ) ) {
				continue;
			}
			$data[] = array(
				'name'       => $meta['name'],
				'title'      => $meta['title'] ?? $meta['name'],
				'icon'       => $meta['icon'] ?? 'block-default',
				'attributes' => $meta['attributes'] ?? (object) array(),
			);
		}
		return $data;
	}
}
