<?php
/**
 * @package Teda_Core
 */

declare(strict_types=1);

namespace Teda_Core\Publications;

/**
 * Server-side rendering helpers for Publications (P21, SPEC §9, §13).
 *
 * A publication is a downloadable PDF, but the page must be useful WITHOUT the
 * download: the summary renders as on-page text (indexable), and every download link
 * shows the file's size next to it — so a reader on metered mobile data decides
 * before tapping (SPEC §9) — opens in a new tab, and is labelled for screen readers.
 *
 * Pure static helpers; no hooks. All PDF access goes through the teda-core Accessor
 * (never rwmb_meta directly, P03), and file size is computed from the attachment so
 * it is always correct, never a hand-typed placeholder.
 */
final class Presenter {

	/**
	 * The publication's PDF as { id, url, size } — or null when none is attached.
	 *
	 * @return array{id:int, url:string, size:int}|null
	 */
	public static function pdf( int $post_id ): ?array {
		if ( ! function_exists( 'teda_field_list' ) ) {
			return null;
		}

		foreach ( teda_field_list( 'teda_pub_file', $post_id ) as $value ) {
			$id = self::to_attachment_id( $value );
			if ( $id <= 0 ) {
				continue;
			}
			$url = wp_get_attachment_url( $id );
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}
			$path = get_attached_file( $id );
			$size = ( is_string( $path ) && is_readable( $path ) ) ? (int) filesize( $path ) : 0;

			return array(
				'id'   => $id,
				'url'  => $url,
				'size' => $size,
			);
		}

		return null;
	}

	/**
	 * The on-page summary text (may be '').
	 */
	public static function summary( int $post_id ): string {
		return function_exists( 'teda_field' ) ? trim( (string) teda_field( 'teda_pub_summary', $post_id, '' ) ) : '';
	}

	/**
	 * The year this document is filed under: the explicit field if set, else the
	 * post's published year — so the archive can always group it.
	 */
	public static function year( int $post_id ): int {
		$explicit = function_exists( 'teda_field_int' ) ? teda_field_int( 'teda_pub_year', $post_id, 0 ) : 0;
		if ( $explicit >= 2000 ) {
			return $explicit;
		}
		$ts = (int) get_post_timestamp( $post_id, 'date' );
		return $ts > 0 ? (int) wp_date( 'Y', $ts ) : (int) gmdate( 'Y' );
	}

	/**
	 * The download link (or a plain note when no file is attached yet). Shows the
	 * file size, opens in a new tab (rel/target set), and carries screen-reader text
	 * naming the document and the new-tab behaviour.
	 *
	 * @param string $btn_class Button classes to apply (theme-provided styling).
	 */
	public static function download_link( int $post_id, string $btn_class = 'teda-btn teda-btn--brown' ): string {
		$pdf   = self::pdf( $post_id );
		$title = get_the_title( $post_id );

		if ( null === $pdf ) {
			return '<p class="teda-pub__nofile">' . esc_html__( 'The PDF for this document will be added soon.', 'teda-core' ) . '</p>';
		}

		$size = $pdf['size'] > 0 ? self::human_size( $pdf['size'] ) : '';

		$sr = sprintf(
			/* translators: %s: the document title. */
			__( '%s — opens the PDF in a new tab', 'teda-core' ),
			$title
		);

		$label = '' !== $size
			/* translators: %s: a human file size, e.g. "2.1 MB". */
			? sprintf( __( 'Open PDF (%s)', 'teda-core' ), $size )
			: __( 'Open PDF', 'teda-core' );

		return '<a class="' . esc_attr( $btn_class ) . '" href="' . esc_url( $pdf['url'] ) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html( $label )
			. '<span class="screen-reader-text"> ' . esc_html( $sr ) . '</span></a>';
	}

	/**
	 * A one-line meta string: "PDF · 2.1 MB" (size omitted when unknown).
	 */
	public static function file_meta( int $post_id ): string {
		$pdf = self::pdf( $post_id );
		if ( null === $pdf ) {
			return '';
		}
		$parts = array( 'PDF' );
		if ( $pdf['size'] > 0 ) {
			$parts[] = self::human_size( $pdf['size'] );
		}
		return implode( ' · ', $parts );
	}

	/**
	 * Human-readable file size (KB / MB, one decimal for MB).
	 */
	public static function human_size( int $bytes ): string {
		if ( $bytes >= 1048576 ) {
			return number_format_i18n( $bytes / 1048576, 1 ) . ' MB';
		}
		if ( $bytes >= 1024 ) {
			return number_format_i18n( (float) round( $bytes / 1024 ) ) . ' KB';
		}
		/* translators: %s: a number of bytes. */
		return sprintf( __( '%s bytes', 'teda-core' ), number_format_i18n( $bytes ) );
	}

	/**
	 * Normalise a teda_pub_file entry (Meta Box file_advanced) to an attachment ID.
	 * Meta Box may hand back an int ID or a file-info array; be defensive.
	 *
	 * @param mixed $value
	 */
	private static function to_attachment_id( $value ): int {
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}
		if ( is_array( $value ) ) {
			if ( isset( $value['ID'] ) && is_numeric( $value['ID'] ) ) {
				return (int) $value['ID'];
			}
			if ( isset( $value['id'] ) && is_numeric( $value['id'] ) ) {
				return (int) $value['id'];
			}
			if ( isset( $value['url'] ) && is_string( $value['url'] ) ) {
				return (int) attachment_url_to_postid( $value['url'] );
			}
		}
		return 0;
	}
}
